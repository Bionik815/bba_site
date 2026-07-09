<?php
/**
 * BW Wix Import — bring the live Wix Stores catalog into WooCommerce.
 *
 * Reads a Wix Stores product CSV export (Store Products -> Export). Each
 * product spans a "Product" row (name, description, images, collections,
 * price, option definitions) plus one "Variant" row per option combination.
 * The importer groups by handleId, maps Wix "Color"/"Size" options to our
 * pa_color / pa_size terms, creates a WooCommerce variable product per item,
 * files it under the collection's client category (creating the client and
 * flagging the product), sideloads images, and builds the variations.
 *
 * Two-step + safe: upload -> DRY RUN preview (creates nothing) -> confirm.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Wix_Import
{
    const PAGE_SLUG = 'bw-wix-import';
    const ACTION_UPLOAD = 'bw_wix_upload';
    const ACTION_IMPORT = 'bw_wix_import';
    const NONCE = 'bw_wix_nonce';
    const TRANSIENT = 'bw_wix_parsed_';

    // Reuse the shared client/product-flag convention.
    const META_CREATOR_ID = '_bwcsb_creator_id';
    const META_MANAGED = '_bwcsb_managed_product';
    const META_CREATOR_CAT = '_bw_creator_wc_category';
    const CPT_CREATOR = 'bw_creator';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_' . self::ACTION_UPLOAD, [$this, 'handle_upload']);
        add_action('admin_post_' . self::ACTION_IMPORT, [$this, 'handle_import']);
    }

    public function add_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Import from Wix', 'bw'),
            __('Import from Wix', 'bw'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /* ---------------- CSV parsing ---------------- */

    /**
     * Parse the uploaded Wix CSV into a normalized structure:
     * [ handleId => [ name, description, images[], collections[], price,
     *   options[ label => values[] ], variants[ [values=>[], price, sku] ] ] ]
     */
    private function parse_csv($path)
    {
        $rows = [];
        if (($fh = fopen($path, 'r')) === false) {
            return ['error' => __('Could not open the uploaded file.', 'bw')];
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['error' => __('The file appears to be empty.', 'bw')];
        }
        // Normalize headers: lowercase, strip BOM/spaces.
        $header = array_map(static function ($h) {
            return strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h)));
        }, $header);

        while (($data = fgetcsv($fh)) !== false) {
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($fh);

        if (!$rows) {
            return ['error' => __('No product rows found.', 'bw')];
        }

        // Detect option columns present (productoptionname1..N).
        $option_slots = [];
        for ($i = 1; $i <= 6; $i++) {
            if (array_key_exists('productoptionname' . $i, $rows[0])) {
                $option_slots[] = $i;
            }
        }

        $products = [];
        $order = [];
        foreach ($rows as $row) {
            $handle = $row['handleid'] ?? $row['handle'] ?? '';
            if ($handle === '') {
                continue;
            }
            $field_type = strtolower($row['fieldtype'] ?? '');

            if (!isset($products[$handle])) {
                $products[$handle] = [
                    'name' => '',
                    'description' => '',
                    'images' => [],
                    'collections' => [],
                    'price' => 0.0,
                    'options' => [],   // label => [values]
                    'variants' => [],  // [ 'values' => [label=>value], 'price'=>, 'sku'=> ]
                ];
                $order[] = $handle;
            }
            $p = &$products[$handle];

            $is_variant = ($field_type === 'variant');

            if (!$is_variant) {
                // Product-defining row.
                if (($row['name'] ?? '') !== '') {
                    $p['name'] = $row['name'];
                }
                if (($row['description'] ?? '') !== '') {
                    $p['description'] = wp_kses_post($row['description']);
                }
                if (($row['price'] ?? '') !== '') {
                    $p['price'] = (float) preg_replace('/[^0-9.]/', '', $row['price']);
                }
                foreach ($this->split_multi($row['productimageurl'] ?? '') as $img) {
                    $p['images'][] = $img;
                }
                foreach ($this->split_multi($row['collection'] ?? '') as $col) {
                    $p['collections'][] = $col;
                }
                foreach ($option_slots as $i) {
                    $label = $row['productoptionname' . $i] ?? '';
                    $vals = $this->split_multi($row['productoptiondescription' . $i] ?? '');
                    if ($label !== '' && $vals) {
                        $p['options'][$label] = $vals;
                    }
                }
            } else {
                // Variant row: read chosen values + its price/sku.
                $values = [];
                foreach ($option_slots as $i) {
                    $label = $row['productoptionname' . $i] ?? '';
                    $val = $row['productoptiondescription' . $i] ?? '';
                    if ($label !== '' && $val !== '') {
                        $values[$label] = $val;
                    }
                }
                $vprice = ($row['price'] ?? '') !== '' ? (float) preg_replace('/[^0-9.]/', '', $row['price']) : null;
                $surcharge = ($row['surcharge'] ?? '') !== '' ? (float) preg_replace('/[^0-9.]/', '', $row['surcharge']) : null;
                if ($values) {
                    $p['variants'][] = [
                        'values' => $values,
                        'price' => $vprice,
                        'surcharge' => $surcharge,
                        'sku' => $row['sku'] ?? '',
                    ];
                }
            }
            unset($p);
        }

        // Keep insertion order.
        $ordered = [];
        foreach ($order as $handle) {
            if ($products[$handle]['name'] !== '') {
                $ordered[$handle] = $products[$handle];
            }
        }

        return ['products' => $ordered, 'option_slots' => $option_slots];
    }

    private function split_multi($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return [];
        }
        // Wix separates multi-values with ';'.
        $parts = array_map('trim', explode(';', $value));
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }

    /** Which option label maps to which of our taxonomies. */
    private function classify_option($label)
    {
        $l = strtolower($label);
        if (strpos($l, 'color') !== false || strpos($l, 'colour') !== false) {
            return 'pa_color';
        }
        if (strpos($l, 'size') !== false) {
            return 'pa_size';
        }
        return '';
    }

    /* ---------------- Upload + preview ---------------- */

    public function handle_upload()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        if (empty($_FILES['wix_csv']['tmp_name']) || (int) $_FILES['wix_csv']['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('error', ['reason' => __('Please choose a CSV file to upload.', 'bw')]);
        }

        $check = wp_check_filetype_and_ext($_FILES['wix_csv']['tmp_name'], $_FILES['wix_csv']['name'], ['csv' => 'text/csv', 'txt' => 'text/plain']);
        $name = strtolower((string) $_FILES['wix_csv']['name']);
        if (substr($name, -4) !== '.csv' && substr($name, -4) !== '.txt') {
            $this->redirect('error', ['reason' => __('That does not look like a CSV file.', 'bw')]);
        }

        $parsed = $this->parse_csv($_FILES['wix_csv']['tmp_name']);
        if (!empty($parsed['error'])) {
            $this->redirect('error', ['reason' => $parsed['error']]);
        }

        $token = wp_generate_password(12, false);
        set_transient(self::TRANSIENT . $token, $parsed, HOUR_IN_SECONDS);

        $this->redirect('preview', ['token' => $token]);
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }

        $state = sanitize_text_field(wp_unslash($_GET['bwwix'] ?? ''));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import from Wix', 'bw'); ?></h1>

            <?php if ($state === 'error') : ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['reason'] ?? __('Import failed.', 'bw')))); ?></p></div>
            <?php elseif ($state === 'done') : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(
                        esc_html__('Imported %1$d products across %2$d client stores as drafts. Review, add prices/photos where needed, then publish.', 'bw'),
                        (int) ($_GET['products'] ?? 0),
                        (int) ($_GET['clients'] ?? 0)
                    );
                ?> <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>"><?php esc_html_e('View products', 'bw'); ?></a></p></div>
            <?php endif; ?>

            <?php
            $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
            $parsed = $token ? get_transient(self::TRANSIENT . $token) : false;
            if ($state === 'preview' && is_array($parsed)) {
                $this->render_preview($token, $parsed);
                return;
            }
            ?>

            <div class="card" style="padding:16px;max-width:720px">
                <h2><?php esc_html_e('Step 1 — Upload your Wix export', 'bw'); ?></h2>
                <ol>
                    <li><?php esc_html_e('In Wix: Store Products → More Actions → Export to CSV.', 'bw'); ?></li>
                    <li><?php esc_html_e('Upload the CSV here. Nothing is created yet — you get a preview first.', 'bw'); ?></li>
                </ol>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_UPLOAD); ?>">
                    <p><input type="file" name="wix_csv" accept=".csv,text/csv" required></p>
                    <?php submit_button(__('Preview import', 'bw')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_preview($token, $parsed)
    {
        $products = $parsed['products'] ?? [];
        $clients = [];
        $color_opts = 0;
        $size_opts = 0;
        $with_images = 0;
        $unmapped = [];

        foreach ($products as $p) {
            foreach ($p['collections'] ?: ['(no collection)'] as $c) {
                $clients[$c] = ($clients[$c] ?? 0) + 1;
            }
            foreach (array_keys($p['options']) as $label) {
                $tax = $this->classify_option($label);
                if ($tax === 'pa_color') {
                    $color_opts++;
                } elseif ($tax === 'pa_size') {
                    $size_opts++;
                } else {
                    $unmapped[$label] = true;
                }
            }
            if ($p['images']) {
                $with_images++;
            }
        }
        $sample = array_slice($products, 0, 8, true);
        ?>
        <div class="card" style="padding:16px;max-width:960px">
            <h2><?php esc_html_e('Step 2 — Preview (nothing created yet)', 'bw'); ?></h2>
            <ul style="list-style:disc;margin-left:20px">
                <li><strong><?php echo (int) count($products); ?></strong> <?php esc_html_e('products found', 'bw'); ?></li>
                <li><strong><?php echo (int) count($clients); ?></strong> <?php esc_html_e('client stores (from collections)', 'bw'); ?></li>
                <li><?php echo (int) $color_opts; ?> <?php esc_html_e('products with a Color option, ', 'bw'); ?><?php echo (int) $size_opts; ?> <?php esc_html_e('with a Size option', 'bw'); ?></li>
                <li><strong><?php echo (int) $with_images; ?></strong> <?php esc_html_e('products with image URLs', 'bw'); ?></li>
                <?php if ($unmapped) : ?>
                    <li style="color:#b26a00"><?php esc_html_e('Options that are not Color/Size (kept as product attributes): ', 'bw'); ?><?php echo esc_html(implode(', ', array_keys($unmapped))); ?></li>
                <?php endif; ?>
            </ul>

            <h3><?php esc_html_e('Client stores detected', 'bw'); ?></h3>
            <p><?php
                $bits = [];
                foreach ($clients as $name => $n) {
                    $bits[] = esc_html($name) . ' (' . (int) $n . ')';
                }
                echo implode(' · ', $bits); // phpcs:ignore -- escaped above
            ?></p>

            <h3><?php esc_html_e('Sample products', 'bw'); ?></h3>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php esc_html_e('Name', 'bw'); ?></th>
                    <th><?php esc_html_e('Client', 'bw'); ?></th>
                    <th><?php esc_html_e('Price', 'bw'); ?></th>
                    <th><?php esc_html_e('Colors', 'bw'); ?></th>
                    <th><?php esc_html_e('Sizes', 'bw'); ?></th>
                    <th><?php esc_html_e('Images', 'bw'); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($sample as $p) :
                        $colors = $sizes = [];
                        foreach ($p['options'] as $label => $vals) {
                            $tax = $this->classify_option($label);
                            if ($tax === 'pa_color') { $colors = $vals; }
                            elseif ($tax === 'pa_size') { $sizes = $vals; }
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($p['name']); ?></td>
                            <td><?php echo esc_html(implode(', ', $p['collections']) ?: '—'); ?></td>
                            <td><?php echo $p['price'] ? esc_html('$' . number_format($p['price'], 2)) : '—'; ?></td>
                            <td><?php echo esc_html((string) count($colors)); ?></td>
                            <td><?php echo esc_html((string) count($sizes)); ?></td>
                            <td><?php echo esc_html((string) count($p['images'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_IMPORT); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                <label><input type="checkbox" name="fetch_images" value="1" checked> <?php esc_html_e('Download product images from Wix (slower, but brings photos over)', 'bw'); ?></label>
                <p><button type="submit" class="button button-primary button-hero"><?php esc_html_e('Run import (creates drafts)', 'bw'); ?></button>
                    <span class="description" style="margin-left:8px"><?php esc_html_e('Products are created as drafts so you can review before they go live.', 'bw'); ?></span></p>
            </form>
        </div>
        <?php
    }

    /* ---------------- Import ---------------- */

    public function handle_import()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $parsed = $token ? get_transient(self::TRANSIENT . $token) : false;
        if (!is_array($parsed) || empty($parsed['products'])) {
            $this->redirect('error', ['reason' => __('Preview expired — please upload the CSV again.', 'bw')]);
        }
        $fetch_images = !empty($_POST['fetch_images']);

        @set_time_limit(0);
        $clients = [];
        $count = 0;
        foreach ($parsed['products'] as $product) {
            $created = $this->import_one($product, $fetch_images);
            if ($created) {
                $count++;
                foreach ($product['collections'] ?: ['Uncategorized'] as $c) {
                    $clients[$c] = true;
                }
            }
        }

        delete_transient(self::TRANSIENT . $token);
        $this->redirect('done', ['products' => $count, 'clients' => count($clients)]);
    }

    private function import_one($p, $fetch_images)
    {
        if (empty($p['name'])) {
            return false;
        }

        // Client + category from the first collection.
        $collection = $p['collections'][0] ?? '';
        $creator_id = 0;
        $cat_id = 0;
        if ($collection !== '') {
            $slug = sanitize_title($collection);
            $cat_id = $this->ensure_category($slug, $collection);
            $creator_id = $this->ensure_creator($collection, $slug);
        }

        // Map options to terms.
        $color_term_ids = [];
        $size_term_ids = [];
        $other_attrs = [];
        foreach ($p['options'] as $label => $values) {
            $tax = $this->classify_option($label);
            if ($tax === 'pa_color') {
                $color_term_ids = $this->term_ids('pa_color', $values, true);
            } elseif ($tax === 'pa_size') {
                $size_term_ids = $this->term_ids('pa_size', $values, true);
            } else {
                $other_attrs[$label] = $values; // custom (non-taxonomy) attribute
            }
        }

        $product = new WC_Product_Variable();
        $client_name = $collection !== '' ? $collection : '';
        $product->set_name(($client_name !== '' ? $client_name . ' - ' : '') . $p['name']);
        $product->set_status('draft');
        if ($p['description']) {
            $product->set_description($p['description']);
        }
        if ($p['price'] > 0) {
            // base price for variations; stored via variations below
        }

        $attributes = [];
        if ($color_term_ids) {
            $a = new WC_Product_Attribute();
            $a->set_id(wc_attribute_taxonomy_id_by_name('color'));
            $a->set_name('pa_color');
            $a->set_options($color_term_ids);
            $a->set_visible(true);
            $a->set_variation(true);
            $attributes[] = $a;
        }
        if ($size_term_ids) {
            $a = new WC_Product_Attribute();
            $a->set_id(wc_attribute_taxonomy_id_by_name('size'));
            $a->set_name('pa_size');
            $a->set_options($size_term_ids);
            $a->set_visible(true);
            $a->set_variation(true);
            $attributes[] = $a;
        }
        foreach ($other_attrs as $label => $values) {
            $a = new WC_Product_Attribute();
            $a->set_name($label);
            $a->set_options($values);
            $a->set_visible(true);
            $a->set_variation(false);
            $attributes[] = $a;
        }
        $product->set_attributes($attributes);

        // Simple product if no variation attributes.
        if (!$color_term_ids && !$size_term_ids) {
            $simple = new WC_Product_Simple();
            $simple->set_name(($client_name !== '' ? $client_name . ' - ' : '') . $p['name']);
            $simple->set_status('draft');
            if ($p['description']) {
                $simple->set_description($p['description']);
            }
            if ($p['price'] > 0) {
                $simple->set_regular_price((string) $p['price']);
            }
            if ($other_attrs) {
                $simple->set_attributes($attributes);
            }
            $product_id = $simple->save();
        } else {
            $product_id = $product->save();
        }

        if (!$product_id) {
            return false;
        }

        if ($cat_id) {
            wp_set_object_terms($product_id, [(int) $cat_id], 'product_cat');
        }
        if ($creator_id) {
            update_post_meta($product_id, self::META_CREATOR_ID, $creator_id);
            update_post_meta($product_id, self::META_MANAGED, '1');
        }
        update_post_meta($product_id, '_bw_wix_import', '1');

        // Images.
        if ($fetch_images && $p['images']) {
            $ids = [];
            foreach (array_slice($p['images'], 0, 6) as $url) {
                $att = $this->sideload_image($url, $product_id);
                if ($att) {
                    $ids[] = $att;
                }
            }
            if ($ids) {
                set_post_thumbnail($product_id, $ids[0]);
                if (count($ids) > 1) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($ids, 1)));
                }
            }
        }

        // Variations for variable products.
        if ($color_term_ids || $size_term_ids) {
            $this->build_variations($product_id, $p, $color_term_ids, $size_term_ids);
            WC_Product_Variable::sync($product_id);
        }
        wc_delete_product_transients($product_id);

        return true;
    }

    private function build_variations($product_id, $p, $color_ids, $size_ids)
    {
        $colors = $color_ids ?: [0];
        $sizes = $size_ids ?: [0];
        $base = $p['price'] > 0 ? $p['price'] : 0;

        // Index explicit variant prices by value-set for overrides.
        $variant_price = [];
        foreach ($p['variants'] as $v) {
            $key = strtolower(implode('|', array_map('strval', $v['values'])));
            if ($v['price'] !== null) {
                $variant_price[$key] = $v['price'];
            } elseif ($v['surcharge'] !== null) {
                $variant_price[$key] = $base + $v['surcharge'];
            }
        }

        foreach ($colors as $cid) {
            foreach ($sizes as $sid) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $attrs = [];
                if ($cid) {
                    $ct = get_term($cid, 'pa_color');
                    if ($ct && !is_wp_error($ct)) { $attrs['pa_color'] = $ct->slug; }
                }
                if ($sid) {
                    $st = get_term($sid, 'pa_size');
                    if ($st && !is_wp_error($st)) { $attrs['pa_size'] = $st->slug; }
                }
                $variation->set_attributes($attrs);
                $variation->set_regular_price((string) ($base > 0 ? $base : 0));
                $variation->set_status('publish');
                $variation->save();
            }
        }
    }

    /* ---------------- Helpers ---------------- */

    private function term_ids($taxonomy, $values, $create)
    {
        $ids = [];
        foreach ($values as $value) {
            $term = get_term_by('name', $value, $taxonomy);
            if ((!$term || is_wp_error($term)) && $create) {
                $new = wp_insert_term($value, $taxonomy);
                if (!is_wp_error($new)) {
                    $ids[] = (int) $new['term_id'];
                }
                continue;
            }
            if ($term && !is_wp_error($term)) {
                $ids[] = (int) $term->term_id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function ensure_category($slug, $name)
    {
        $existing = get_term_by('slug', $slug, 'product_cat');
        if ($existing && !is_wp_error($existing)) {
            return (int) $existing->term_id;
        }
        $created = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
        return is_wp_error($created) ? 0 : (int) $created['term_id'];
    }

    private function ensure_creator($name, $slug)
    {
        $existing = get_page_by_path($slug, OBJECT, self::CPT_CREATOR);
        if ($existing) {
            $id = (int) $existing->ID;
        } else {
            $id = wp_insert_post([
                'post_type' => self::CPT_CREATOR,
                'post_status' => 'publish',
                'post_title' => $name,
                'post_name' => $slug,
            ]);
            if (is_wp_error($id) || !$id) {
                return 0;
            }
        }
        update_post_meta($id, self::META_CREATOR_CAT, $slug);
        return $id;
    }

    private function sideload_image($url, $product_id)
    {
        $url = trim((string) $url);
        // Wix exports may give bare filenames or wix: refs we can't fetch.
        if (!preg_match('#^https?://#i', $url)) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            return 0;
        }
        $file = ['name' => wp_basename(parse_url($url, PHP_URL_PATH)) ?: 'wix-image.jpg', 'tmp_name' => $tmp];
        $id = media_handle_sideload($file, $product_id);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return 0;
        }
        return (int) $id;
    }

    private function redirect($state, $args = [])
    {
        wp_safe_redirect(add_query_arg(array_merge([
            'post_type' => 'product',
            'page' => self::PAGE_SLUG,
            'bwwix' => $state,
        ], array_map('rawurlencode', $args)), admin_url('edit.php')));
        exit;
    }
}

new BW_Wix_Import();
