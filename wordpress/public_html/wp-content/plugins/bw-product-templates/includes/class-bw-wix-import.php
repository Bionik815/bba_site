<?php
/**
 * BW Wix Import — bring the live Wix Stores catalog into WooCommerce.
 *
 * Reads a Wix Stores product CSV export. Groups rows by handleId (a Product
 * row plus Variant rows), maps Wix Color/Size options to pa_color / pa_size,
 * creates a WooCommerce product per item, files it under its collections as
 * client categories, flags the client, downloads images from the Wix CDN,
 * and builds variations.
 *
 * Safe + scalable: upload -> DRY-RUN preview (creates nothing; you pick which
 * collections are client stores) -> batched AJAX import (drafts) that never
 * times out on a large catalog.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Wix_Import
{
    const PAGE_SLUG = 'bw-wix-import';
    const ACTION_UPLOAD = 'bw_wix_upload';
    const ACTION_START = 'bw_wix_start';
    const AJAX_BATCH = 'bw_wix_batch';
    const NONCE = 'bw_wix_nonce';
    const TRANSIENT = 'bw_wix_parsed_';

    const META_CREATOR_ID = '_bwcsb_creator_id';
    const META_MANAGED = '_bwcsb_managed_product';
    const META_CREATOR_CAT = '_bw_creator_wc_category';
    const CPT_CREATOR = 'bw_creator';
    const WIX_CDN = 'https://static.wixstatic.com/media/';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_' . self::ACTION_UPLOAD, [$this, 'handle_upload']);
        add_action('admin_post_' . self::ACTION_START, [$this, 'handle_start']);
        add_action('wp_ajax_' . self::AJAX_BATCH, [$this, 'ajax_batch']);
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

    private function parse_csv($path)
    {
        if (($fh = fopen($path, 'r')) === false) {
            return ['error' => __('Could not open the uploaded file.', 'bw')];
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['error' => __('The file appears to be empty.', 'bw')];
        }
        $header = array_map(static function ($h) {
            return strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h)));
        }, $header);

        $option_slots = [];
        for ($i = 1; $i <= 6; $i++) {
            if (in_array('productoptionname' . $i, $header, true)) {
                $option_slots[] = $i;
            }
        }

        $products = [];
        $order = [];
        while (($data = fgetcsv($fh)) !== false) {
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }

            $handle = $row['handleid'] ?? '';
            if ($handle === '') {
                continue;
            }
            $is_variant = strtolower($row['fieldtype'] ?? '') === 'variant';

            if (!isset($products[$handle])) {
                $products[$handle] = ['name' => '', 'description' => '', 'images' => [], 'collections' => [], 'price' => 0.0, 'options' => [], 'variants' => []];
                $order[] = $handle;
            }
            $p = &$products[$handle];

            if (!$is_variant) {
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
                    if (!in_array($col, $p['collections'], true)) {
                        $p['collections'][] = $col;
                    }
                }
                foreach ($option_slots as $i) {
                    $label = $row['productoptionname' . $i] ?? '';
                    $vals = $this->split_multi($row['productoptiondescription' . $i] ?? '');
                    if ($label !== '' && $vals) {
                        $p['options'][$label] = $vals;
                    }
                }
            } else {
                $values = [];
                foreach ($option_slots as $i) {
                    $label = $row['productoptionname' . $i] ?? '';
                    $val = $row['productoptiondescription' . $i] ?? '';
                    if ($label !== '' && $val !== '') {
                        $values[$label] = $val;
                    }
                }
                if ($values) {
                    $p['variants'][] = [
                        'values' => $values,
                        'price' => ($row['price'] ?? '') !== '' ? (float) preg_replace('/[^0-9.]/', '', $row['price']) : null,
                        'surcharge' => ($row['surcharge'] ?? '') !== '' ? (float) preg_replace('/[^0-9.]/', '', $row['surcharge']) : null,
                    ];
                }
            }
            unset($p);
        }
        fclose($fh);

        $ordered = [];
        foreach ($order as $handle) {
            if ($products[$handle]['name'] !== '') {
                $ordered[$handle] = $products[$handle];
            }
        }
        if (!$ordered) {
            return ['error' => __('No products found in the file.', 'bw')];
        }

        return ['products' => $ordered];
    }

    private function split_multi($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return [];
        }
        $parts = array_map('trim', explode(';', $value));
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }

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

    /** Guess whether a collection is a client store (vs a category/grouping). */
    private function looks_like_client($name)
    {
        $l = strtolower($name);
        if (strpos($l, 'all ') === 0 || strpos($l, 'template') !== false) {
            return false;
        }
        $generic = ['mens shirts', 'womens shirts', 'all shirts', 'all t-shirts', 't-shirts', 'hoodies', 'hats', 'accessories', 'apparel', 'new arrivals', 'best sellers', 'sale', 'featured'];
        return !in_array($l, $generic, true);
    }

    /* ---------------- Upload + preview ---------------- */

    public function handle_upload()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $err = isset($_FILES['wix_csv']['error']) ? (int) $_FILES['wix_csv']['error'] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $this->redirect('error', ['reason' => sprintf(
                /* translators: %s: server upload size limit */
                __('That file is larger than the server upload limit (%s). Raise upload_max_filesize / post_max_size in PHP settings and try again.', 'bw'),
                ini_get('upload_max_filesize')
            )]);
        }
        if ($err === UPLOAD_ERR_NO_FILE) {
            $this->redirect('error', ['reason' => __('Please choose a CSV file to upload.', 'bw')]);
        }
        if ($err !== UPLOAD_ERR_OK || empty($_FILES['wix_csv']['tmp_name'])) {
            $this->redirect('error', ['reason' => sprintf(__('Upload failed (error code %d). Please try again.', 'bw'), $err)]);
        }
        $name = strtolower((string) $_FILES['wix_csv']['name']);
        if (substr($name, -4) !== '.csv' && substr($name, -4) !== '.txt') {
            $this->redirect('error', ['reason' => __('That does not look like a CSV file.', 'bw')]);
        }

        $parsed = $this->parse_csv($_FILES['wix_csv']['tmp_name']);
        if (!empty($parsed['error'])) {
            $this->redirect('error', ['reason' => $parsed['error']]);
        }

        $token = wp_generate_password(12, false);
        set_transient(self::TRANSIENT . $token, $parsed, 2 * HOUR_IN_SECONDS);
        $this->redirect('preview', ['token' => $token]);
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        $state = sanitize_text_field(wp_unslash($_GET['bwwix'] ?? ''));
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import from Wix', 'bw'); ?></h1>
            <?php if ($state === 'error') : ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['reason'] ?? __('Import failed.', 'bw')))); ?></p></div>
            <?php endif; ?>

            <?php
            $parsed = $token ? get_transient(self::TRANSIENT . $token) : false;
            if ($state === 'preview' && is_array($parsed)) {
                $this->render_preview($token, $parsed);
                return;
            }
            if ($state === 'progress' && is_array($parsed)) {
                $this->render_progress($token, $parsed);
                return;
            }
            ?>

            <div class="card" style="padding:16px;max-width:720px">
                <h2><?php esc_html_e('Step 1 — Upload your Wix export', 'bw'); ?></h2>
                <ol>
                    <li><?php esc_html_e('In Wix: Store Products → More Actions → Export to CSV.', 'bw'); ?></li>
                    <li><?php esc_html_e('Upload it here. Nothing is created yet — you preview and choose client stores first.', 'bw'); ?></li>
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
        $products = $parsed['products'];
        $collections = [];
        $with_images = 0;
        foreach ($products as $p) {
            foreach ($p['collections'] as $c) {
                $collections[$c] = ($collections[$c] ?? 0) + 1;
            }
            if ($p['images']) {
                $with_images++;
            }
        }
        ksort($collections);
        ?>
        <div class="card" style="padding:16px;max-width:960px">
            <h2><?php esc_html_e('Step 2 — Preview & choose client stores', 'bw'); ?></h2>
            <ul style="list-style:disc;margin-left:20px">
                <li><strong><?php echo (int) count($products); ?></strong> <?php esc_html_e('products', 'bw'); ?></li>
                <li><strong><?php echo (int) count($collections); ?></strong> <?php esc_html_e('collections', 'bw'); ?></li>
                <li><strong><?php echo (int) $with_images; ?></strong> <?php esc_html_e('products have images (downloaded from the Wix CDN on import)', 'bw'); ?></li>
            </ul>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_START); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <h3><?php esc_html_e('Which collections are client stores?', 'bw'); ?></h3>
                <p class="description"><?php esc_html_e('Checked collections become client stores (a client + category is created and products are flagged to them). Uncheck grouping collections like "All T-Shirts" or "School Template" — products still import, just without a client for those.', 'bw'); ?>
                    <a href="#" id="bw-wix-all">all</a> / <a href="#" id="bw-wix-none">none</a></p>
                <div style="column-count:3;column-gap:24px;border:1px solid #e5e7eb;border-radius:8px;padding:12px;max-height:340px;overflow:auto">
                    <?php foreach ($collections as $name => $n) : ?>
                        <label style="display:block;break-inside:avoid;padding:2px 0">
                            <input type="checkbox" class="bw-wix-col" name="client_collections[]" value="<?php echo esc_attr($name); ?>" <?php checked($this->looks_like_client($name)); ?>>
                            <?php echo esc_html($name); ?> <span style="color:#888">(<?php echo (int) $n; ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin-top:18px"><?php esc_html_e('Options', 'bw'); ?></h3>
                <p><label><input type="checkbox" name="fetch_images" value="1" checked> <?php esc_html_e('Download product images from Wix (recommended — this is how photos come across)', 'bw'); ?></label></p>
                <p><label><input type="checkbox" name="status_publish" value="1"> <?php esc_html_e('Publish immediately (default: import as drafts to review first)', 'bw'); ?></label></p>

                <p style="margin-top:14px"><button type="submit" class="button button-primary button-hero"><?php esc_html_e('Continue to import', 'bw'); ?></button></p>
            </form>
        </div>
        <script>
        (function () {
            function set(v) { document.querySelectorAll('.bw-wix-col').forEach(function (c) { c.checked = v; }); }
            document.getElementById('bw-wix-all').addEventListener('click', function (e) { e.preventDefault(); set(true); });
            document.getElementById('bw-wix-none').addEventListener('click', function (e) { e.preventDefault(); set(false); });
        }());
        </script>
        <?php
    }

    public function handle_start()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $parsed = $token ? get_transient(self::TRANSIENT . $token) : false;
        if (!is_array($parsed)) {
            $this->redirect('error', ['reason' => __('Preview expired — please upload again.', 'bw')]);
        }

        $parsed['client_collections'] = array_map('sanitize_text_field', (array) wp_unslash($_POST['client_collections'] ?? []));
        $parsed['fetch_images'] = !empty($_POST['fetch_images']);
        $parsed['status'] = !empty($_POST['status_publish']) ? 'publish' : 'draft';
        $parsed['handles'] = array_keys($parsed['products']);
        $parsed['created'] = 0;
        set_transient(self::TRANSIENT . $token, $parsed, 3 * HOUR_IN_SECONDS);

        $this->redirect('progress', ['token' => $token]);
    }

    private function render_progress($token, $parsed)
    {
        $total = count($parsed['handles'] ?? $parsed['products']);
        $fetch = !empty($parsed['fetch_images']);
        $batch = $fetch ? 3 : 15; // image downloads are the slow part
        ?>
        <div class="card" style="padding:16px;max-width:720px">
            <h2><?php esc_html_e('Step 3 — Importing', 'bw'); ?></h2>
            <p><?php esc_html_e('Keep this tab open. Products import in batches so it will not time out.', 'bw'); ?></p>
            <div style="background:#eee;border-radius:999px;height:22px;overflow:hidden">
                <div id="bw-wix-bar" style="background:#8d5b2c;height:100%;width:0;transition:width .2s"></div>
            </div>
            <p id="bw-wix-status" style="font-weight:600;margin-top:10px"><?php esc_html_e('Starting…', 'bw'); ?></p>
            <p id="bw-wix-done" style="display:none"><a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button button-primary"><?php esc_html_e('View imported products', 'bw'); ?></a></p>
        </div>
        <script>
        (function () {
            var TOKEN = <?php echo wp_json_encode($token); ?>;
            var TOTAL = <?php echo (int) $total; ?>;
            var BATCH = <?php echo (int) $batch; ?>;
            var NONCE = <?php echo wp_json_encode(wp_create_nonce(self::AJAX_BATCH)); ?>;
            var AJAX = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var offset = 0, created = 0;
            var bar = document.getElementById('bw-wix-bar');
            var status = document.getElementById('bw-wix-status');

            function run() {
                var body = new URLSearchParams();
                body.set('action', <?php echo wp_json_encode(self::AJAX_BATCH); ?>);
                body.set('token', TOKEN);
                body.set('offset', offset);
                body.set('batch', BATCH);
                body.set('_nonce', NONCE);
                fetch(AJAX, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.success) { status.textContent = 'Error: ' + ((res && res.data) || 'unknown'); return; }
                        offset = res.data.offset; created += res.data.created;
                        var pct = TOTAL ? Math.round(offset / TOTAL * 100) : 100;
                        bar.style.width = pct + '%';
                        status.textContent = offset + ' / ' + TOTAL + ' products processed · ' + created + ' created';
                        if (res.data.done) {
                            status.textContent = 'Done — ' + created + ' products imported.';
                            document.getElementById('bw-wix-done').style.display = 'block';
                        } else {
                            run();
                        }
                    })
                    .catch(function (e) { status.textContent = 'Network error: ' + e.message + ' (retrying…)'; setTimeout(run, 2000); });
            }
            run();
        }());
        </script>
        <?php
    }

    /* ---------------- Batched import ---------------- */

    public function ajax_batch()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('permission');
        }
        check_ajax_referer(self::AJAX_BATCH, '_nonce');

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $offset = absint($_POST['offset'] ?? 0);
        $batch = max(1, min(25, absint($_POST['batch'] ?? 5)));
        $parsed = $token ? get_transient(self::TRANSIENT . $token) : false;
        if (!is_array($parsed) || empty($parsed['handles'])) {
            wp_send_json_error('expired');
        }

        @set_time_limit(0);
        $handles = $parsed['handles'];
        $client_cols = array_flip($parsed['client_collections'] ?? []);
        $fetch = !empty($parsed['fetch_images']);
        $status = $parsed['status'] ?? 'draft';

        $slice = array_slice($handles, $offset, $batch);
        $created = 0;
        foreach ($slice as $handle) {
            if (empty($parsed['products'][$handle])) {
                continue;
            }
            if ($this->import_one($parsed['products'][$handle], $client_cols, $fetch, $status)) {
                $created++;
            }
        }

        $new_offset = $offset + count($slice);
        $done = $new_offset >= count($handles);

        wp_send_json_success(['offset' => $new_offset, 'created' => $created, 'total' => count($handles), 'done' => $done]);
    }

    private function import_one($p, $client_cols, $fetch_images, $status)
    {
        if (empty($p['name'])) {
            return false;
        }

        // Categories = all collections; client = first collection marked as a client store.
        $category_ids = [];
        $client_collection = '';
        foreach ($p['collections'] as $col) {
            $slug = sanitize_title($col);
            $cid = $this->ensure_category($slug, $col);
            if ($cid) {
                $category_ids[] = $cid;
            }
            if ($client_collection === '' && isset($client_cols[$col])) {
                $client_collection = $col;
            }
        }
        $creator_id = 0;
        if ($client_collection !== '') {
            $creator_id = $this->ensure_creator($client_collection, sanitize_title($client_collection));
        }

        // Options -> terms.
        $color_ids = $size_ids = [];
        $other_attrs = [];
        foreach ($p['options'] as $label => $values) {
            $tax = $this->classify_option($label);
            if ($tax === 'pa_color') {
                $color_ids = $this->term_ids('pa_color', $values);
            } elseif ($tax === 'pa_size') {
                $size_ids = $this->term_ids('pa_size', $values);
            } else {
                $other_attrs[$label] = $values;
            }
        }

        $is_variable = $color_ids || $size_ids;
        // Wix names already read "Client - Product" — use as-is.
        $name = $p['name'];

        if ($is_variable) {
            $product = new WC_Product_Variable();
        } else {
            $product = new WC_Product_Simple();
            if ($p['price'] > 0) {
                $product->set_regular_price((string) $p['price']);
            }
        }
        $product->set_name($name);
        $product->set_status($status);
        if ($p['description']) {
            $product->set_description($p['description']);
        }

        $attributes = [];
        if ($color_ids) {
            $attributes[] = $this->tax_attribute('color', 'pa_color', $color_ids, true);
        }
        if ($size_ids) {
            $attributes[] = $this->tax_attribute('size', 'pa_size', $size_ids, true);
        }
        foreach ($other_attrs as $label => $values) {
            $a = new WC_Product_Attribute();
            $a->set_name($label);
            $a->set_options($values);
            $a->set_visible(true);
            $a->set_variation(false);
            $attributes[] = $a;
        }
        if ($attributes) {
            $product->set_attributes($attributes);
        }

        $product_id = $product->save();
        if (!$product_id) {
            return false;
        }

        if ($category_ids) {
            wp_set_object_terms($product_id, array_values(array_unique($category_ids)), 'product_cat');
        }
        if ($creator_id) {
            update_post_meta($product_id, self::META_CREATOR_ID, $creator_id);
            update_post_meta($product_id, self::META_MANAGED, '1');
        }
        update_post_meta($product_id, '_bw_wix_import', '1');

        if ($fetch_images && $p['images']) {
            $ids = [];
            foreach (array_slice($p['images'], 0, 6) as $img) {
                $att = $this->sideload_image($img, $product_id);
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

        if ($is_variable) {
            $this->build_variations($product_id, $p, $color_ids, $size_ids);
            WC_Product_Variable::sync($product_id);
        }
        wc_delete_product_transients($product_id);

        return true;
    }

    private function tax_attribute($slug, $taxonomy, $term_ids, $variation)
    {
        $a = new WC_Product_Attribute();
        $a->set_id(wc_attribute_taxonomy_id_by_name($slug));
        $a->set_name($taxonomy);
        $a->set_options($term_ids);
        $a->set_visible(true);
        $a->set_variation($variation);
        return $a;
    }

    private function build_variations($product_id, $p, $color_ids, $size_ids)
    {
        $colors = $color_ids ?: [0];
        $sizes = $size_ids ?: [0];
        $base = $p['price'] > 0 ? $p['price'] : 0;

        // Cap runaway matrices (a few pathological products) to keep imports sane.
        if (count($colors) * count($sizes) > 200) {
            $sizes = array_slice($sizes, 0, max(1, (int) floor(200 / max(1, count($colors)))));
        }

        foreach ($colors as $cid) {
            foreach ($sizes as $sid) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $attrs = [];
                if ($cid) {
                    $ct = get_term($cid, 'pa_color');
                    if ($ct && !is_wp_error($ct)) {
                        $attrs['pa_color'] = $ct->slug;
                    }
                }
                if ($sid) {
                    $st = get_term($sid, 'pa_size');
                    if ($st && !is_wp_error($st)) {
                        $attrs['pa_size'] = $st->slug;
                    }
                }
                $variation->set_attributes($attrs);
                $variation->set_regular_price((string) ($base > 0 ? $base : 0));
                $variation->set_status('publish');
                $variation->save();
            }
        }
    }

    /* ---------------- Helpers ---------------- */

    private function term_ids($taxonomy, $values)
    {
        $ids = [];
        foreach ($values as $value) {
            $term = get_term_by('name', $value, $taxonomy);
            if (!$term || is_wp_error($term)) {
                $new = wp_insert_term($value, $taxonomy);
                if (!is_wp_error($new)) {
                    $ids[] = (int) $new['term_id'];
                }
                continue;
            }
            $ids[] = (int) $term->term_id;
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

    /**
     * Sideload a Wix image. The export gives bare media handles
     * (f66de1_hash~mv2.jpg) that resolve under the Wix CDN base.
     */
    private function sideload_image($ref, $product_id)
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return 0;
        }
        if (!preg_match('#^https?://#i', $ref)) {
            $ref = self::WIX_CDN . ltrim($ref, '/');
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($ref, 30);
        if (is_wp_error($tmp)) {
            return 0;
        }
        // Give it a clean filename/extension (Wix handles carry ~mv2).
        $base = wp_basename(parse_url($ref, PHP_URL_PATH));
        $base = preg_replace('/~mv2/', '', $base);
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $base)) {
            $base .= '.jpg';
        }
        $file = ['name' => $base, 'tmp_name' => $tmp];
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
