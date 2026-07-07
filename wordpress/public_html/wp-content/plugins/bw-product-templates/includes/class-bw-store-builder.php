<?php
/**
 * BW Store Builder — guided product creation for client stores.
 *
 * Workflow (for the Barebones worker):
 *   1) Pick the Client (creator). Their store category auto-fills, and every
 *      product built here is flagged to that client so the client's landing
 *      page can group them into focused sections. A list of the client's
 *      existing products shows so you don't rebuild something they have.
 *   2) Tick every product to add (stores usually carry 20+). Each ticked
 *      product expands its own options.
 *   3) Per product: pick colors (limited to that template's matrix), see the
 *      pre-loaded sizes, tick logo options, set base price + 2XL+ upcharge,
 *      and attach an image per color.
 *   4) One click builds them all as "Client - Product" variable products in
 *      the client's store.
 *
 * The size/color matrix lives on template products as ordinary WooCommerce
 * attributes, so refining a template's available colors or sizes is done on
 * its normal product edit screen. Client<->category<->product-flag reuses the
 * bw-creator-store-builder convention so both tools agree on a client's store.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Store_Builder
{
    const PAGE_SLUG = 'bw-store-builder';
    const ACTION_BUILD = 'bw_sb_build';
    const ACTION_SEED = 'bw_sb_seed';
    const NONCE = 'bw_sb_nonce';
    const BIG_SIZES = ['2XL', '3XL', '4XL', '5XL', '6XL'];

    // Shared with bw-creator-store-builder so a client's store is one set.
    const CPT_CREATOR = 'bw_creator';
    const META_CREATOR_CAT = '_bw_creator_wc_category';
    const META_CREATOR_ID = '_bwcsb_creator_id';
    const META_MANAGED = '_bwcsb_managed_product';
    const META_TEMPLATE_ID = '_bwcsb_template_id';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_' . self::ACTION_BUILD, [$this, 'handle_build']);
        add_action('admin_post_' . self::ACTION_SEED, [$this, 'handle_seed']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media']);
    }

    public function add_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Store Builder', 'bw'),
            __('Store Builder', 'bw'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function enqueue_media($hook)
    {
        if (isset($_GET['page']) && $_GET['page'] === self::PAGE_SLUG) {
            wp_enqueue_media();
        }
    }

    /* ---------------- Catalog seeding ---------------- */

    /**
     * Product catalog from the Barebones ops database. `sized` products get
     * the default size run; accessories are color-only (or plain).
     */
    private function catalog_products()
    {
        $no_size = ['Backpack', 'Bottle', 'Decals', 'Duffel Bag', 'Flat Bill Snapback', 'FlexFit Curved Bill Cap', 'Lanyard', 'Mug', 'Rally Towel', 'Socks', 'Tote Bag', 'Visor'];
        $names = [
            '1/4 Zip Shirt', '2 Button Jersey', '3 Button Evelution Henley', 'Backpack', 'Baseball Jersey', 'Beanie', 'Bottle',
            'Competitor Shorts', 'Crew Neck Sweater', 'Decals', 'Dri-Fit Tee', 'Duffel Bag', 'Featherweight T-Shirt Hoodie',
            'Flat Bill Snapback', 'Fleece Lettermans Jacket', 'FlexFit Curved Bill Cap', 'Hooded Tee', 'Hyperform Sleeveless Compression',
            'Insulated Leathermans Jacket', 'Jersey Knit Polo', 'Ladies 3/4 Sleeve Tee', 'Ladies Full Button Dress Shirt',
            'Ladies Full Zip Jacket', 'Ladies Polo', 'Ladies Racer Back Tank', 'Ladies Rocket Tank', 'Ladies V-Neck Tee',
            'Ladies Zephyr Zip-Up', 'Lanyard', 'Long Sleeve Tee', 'Loose Pullover Hoodie', 'Mens 3/4 Sleeve Tee',
            'Mens Football Jersey', 'Mens Full Button Dress Shirt', 'Mens Full Zip Jacket', 'Mens Microfleece Jacket', 'Mens Polo',
            'Mens Quarter-Zip Sweatshirt', 'Mens Tri-Blend Hoodie', 'Mens Zephyr Zip-Up', 'Mug', 'New Era Tee',
            'Nike Pullover Hoodie', 'Nike Sports Tee', 'Nike Zip-Up Hoodie', 'Perfect Weight Fleece Cropped Crew',
            'Posicharge Mesh 7" Shorts', 'Pullover Hoodie', 'Rally Towel', 'Shorts w/ Pockets', 'Socks', 'Sport-Tek Wind Pants',
            'Sweat Pants', 'Tote Bag', 'Unisex Tee', 'Visor', 'Womens Flex Waist Bike Shorts', 'Womens Football Jersey',
            'Womens Microfleece Jacket', 'Womens Quarter-Zip Sweatshirt', 'Womens Tri-Blend Hoodie', 'Zip-Up Hoodie',
        ];

        $out = [];
        foreach ($names as $name) {
            $out[] = ['name' => $name, 'sized' => !in_array($name, $no_size, true)];
        }

        return $out;
    }

    private function catalog_colors()
    {
        return [
            'Black' => '#000000', 'White' => '#FFFFFF', 'Aquatic Blue' => '', 'Athletic Maroon' => '', 'Cardinal' => '',
            'Coal Grey' => '', 'Dark Chocolate Brown' => '', 'Gold' => '', 'Heather Navy' => '', 'Heather Sangria' => '',
            'Kelly' => '', 'Light Blue' => '', 'Navy' => '', 'Neon Pink' => '', 'Olive' => '', 'Pale Blush' => '',
            'S. Green' => '', 'Sapphire' => '', 'Teal' => '', 'True Navy' => '', 'Woodland Brown' => '', 'Ash' => '',
            'Athletic Heather' => '', 'Athletic Kelly' => '', 'Black Heather' => '', 'Bright Aqua' => '', 'Candy Pink' => '',
            'Carolina Blue' => '', 'Charcoal' => '', 'Clover Green' => '', 'Coral' => '', 'Coyote Brown' => '', 'Crème' => '',
            'Dark Green' => '', 'Dark Heather Grey' => '', 'Flush Pink' => '', 'Grapphite Heather' => '',
            'Heather Athletic Maroon' => '', 'Heather Dark Chocolate Brown' => '', 'Heather Purple' => '', 'Hether Red' => '',
            'Heather Royal' => '', 'Heathered Dusty Peach' => '', 'Jadeite' => '', 'Jet Black' => '', 'Laurel Green' => '',
            'Lavender' => '', 'Lemon Yellow' => '', 'Lime' => '', 'Medium Grey' => '', 'Natural' => '', 'Neon Blue' => '',
            'Neon Green' => '', 'Neon Orange' => '', 'Neon Yellow' => '', 'Neptune Blue' => '', 'Oatmel Heather' => '',
            'Olive Drab Green' => '', 'Olive Drab Green Heather' => '', 'Orange' => '', 'Purple' => '', 'Red' => '',
            'Royal' => '', 'S. Orange' => '', 'Sand' => '', 'Sangria' => '', 'Silver' => '', 'Steel Blue' => '',
            'Stonewashed Blue' => '', 'Team Purple' => '', 'Tenessee Orange' => '', 'True Celadon' => '', 'True Royal' => '',
            'Tundra Blue' => '', 'Yellow' => '', 'Zinnia' => '',
        ];
    }

    private function catalog_sizes()
    {
        return [
            ['Youth X-Small', 10], ['Youth Small', 20], ['Youth Medium', 30], ['Youth Large', 40], ['Youth X-Large', 50],
            ['X-Small', 60], ['Adult Small', 70], ['Adult Medium', 80], ['Adult Large', 90], ['Adult XL', 100],
            ['Adult 2XL', 110], ['Adult 3XL', 120], ['Adult 4XL', 130], ['Adult 5XL', 140], ['Adult 6XL', 150],
        ];
    }

    /** Default size run assigned to sized templates (refine per template later). */
    private function default_size_names()
    {
        return ['Adult Small', 'Adult Medium', 'Adult Large', 'Adult XL', 'Adult 2XL', 'Adult 3XL', 'Adult 4XL'];
    }

    private function ensure_attribute($name, $label)
    {
        $taxonomy = wc_attribute_taxonomy_name($name); // pa_{name}
        if (!taxonomy_exists($taxonomy)) {
            $existing = wc_get_attribute_taxonomies();
            $found = false;
            foreach ($existing as $attr) {
                if ($attr->attribute_name === $name) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                wc_create_attribute(['name' => $label, 'slug' => $name, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
            }
            register_taxonomy($taxonomy, ['product'], ['hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false]);
            delete_transient('wc_attribute_taxonomies');
        }

        return $taxonomy;
    }

    public function handle_seed()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $summary = $this->seed_catalog();

        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'bwsb' => 'seeded',
            'terms' => $summary['terms'],
            'tpls' => $summary['templates'],
        ], admin_url('edit.php?post_type=product')));
        exit;
    }

    /**
     * Idempotent: creates the Color/Size attributes + terms and one template
     * product per catalog item. Safe to run again after adding colors.
     */
    public function seed_catalog()
    {
        $color_tax = $this->ensure_attribute('color', 'Color');
        $size_tax = $this->ensure_attribute('size', 'Size');
        $terms_created = 0;

        foreach ($this->catalog_colors() as $color => $hex) {
            if (!term_exists($color, $color_tax)) {
                $t = wp_insert_term($color, $color_tax);
                if (!is_wp_error($t)) {
                    $terms_created++;
                    if ($hex) {
                        update_term_meta($t['term_id'], 'bw_hex', $hex);
                    }
                }
            }
        }

        foreach ($this->catalog_sizes() as $i => [$size, $order]) {
            $t = term_exists($size, $size_tax);
            if (!$t) {
                $t = wp_insert_term($size, $size_tax);
                if (is_wp_error($t)) {
                    continue;
                }
                $terms_created++;
            }
            $term_id = is_array($t) ? (int) $t['term_id'] : (int) $t;
            update_term_meta($term_id, 'order', $order);
            wp_update_term($term_id, $size_tax, []);
        }

        $all_color_ids = get_terms(['taxonomy' => $color_tax, 'hide_empty' => false, 'fields' => 'ids']);
        $default_size_ids = [];
        foreach ($this->default_size_names() as $size) {
            $t = term_exists($size, $size_tax);
            if ($t) {
                $default_size_ids[] = is_array($t) ? (int) $t['term_id'] : (int) $t;
            }
        }

        $templates_created = 0;
        foreach ($this->catalog_products() as $item) {
            if ($this->template_exists($item['name'])) {
                continue;
            }

            $product = new WC_Product_Variable();
            $product->set_name($item['name']);
            $product->set_status('publish');
            $product->set_catalog_visibility('hidden');

            $attributes = [];

            $color_attr = new WC_Product_Attribute();
            $color_attr->set_id(wc_attribute_taxonomy_id_by_name('color'));
            $color_attr->set_name($color_tax);
            $color_attr->set_options(array_map('intval', (array) $all_color_ids));
            $color_attr->set_visible(true);
            $color_attr->set_variation(true);
            $attributes[] = $color_attr;

            if ($item['sized'] && $default_size_ids) {
                $size_attr = new WC_Product_Attribute();
                $size_attr->set_id(wc_attribute_taxonomy_id_by_name('size'));
                $size_attr->set_name($size_tax);
                $size_attr->set_options($default_size_ids);
                $size_attr->set_visible(true);
                $size_attr->set_variation(true);
                $attributes[] = $size_attr;
            }

            $product->set_attributes($attributes);
            $product_id = $product->save();

            if ($product_id) {
                update_post_meta($product_id, BW_Product_Templates::META_IS_TEMPLATE, '1');
                $templates_created++;
            }
        }

        return ['terms' => $terms_created, 'templates' => $templates_created];
    }

    private function template_exists($title)
    {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = '1'
             WHERE p.post_type = 'product' AND p.post_title = %s AND p.post_status != 'trash' LIMIT 1",
            BW_Product_Templates::META_IS_TEMPLATE,
            $title
        ));

        return (int) $id > 0;
    }

    /* ---------------- Data for the builder page ---------------- */

    private function get_template_matrix()
    {
        $templates = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'meta_key' => BW_Product_Templates::META_IS_TEMPLATE,
            'meta_value' => '1',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $out = [];
        foreach ($templates as $tpl) {
            $product = wc_get_product($tpl->ID);
            if (!$product) {
                continue;
            }

            $colors = [];
            $sizes = [];
            foreach ($product->get_attributes() as $attribute) {
                if (!$attribute->is_taxonomy()) {
                    continue;
                }
                $terms = $attribute->get_terms() ?: [];
                if ($attribute->get_name() === 'pa_color') {
                    foreach ($terms as $term) {
                        $colors[] = ['id' => $term->term_id, 'name' => $term->name, 'hex' => (string) get_term_meta($term->term_id, 'bw_hex', true)];
                    }
                } elseif ($attribute->get_name() === 'pa_size') {
                    $ordered = [];
                    foreach ($terms as $term) {
                        $ordered[] = ['id' => $term->term_id, 'name' => $term->name, 'order' => (int) get_term_meta($term->term_id, 'order', true)];
                    }
                    usort($ordered, static fn($a, $b) => $a['order'] <=> $b['order']);
                    $sizes = $ordered;
                }
            }

            $out[] = ['id' => $tpl->ID, 'name' => $tpl->post_title, 'colors' => $colors, 'sizes' => $sizes];
        }

        return $out;
    }

    private function get_logo_options()
    {
        $taxonomy = wc_attribute_taxonomy_name('logo-options');
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            return [];
        }

        return array_map(static fn($t) => ['id' => $t->term_id, 'name' => $t->name], $terms);
    }

    /**
     * Clients (creators) with their resolved store category term id (0 if the
     * category does not exist yet — it will be created on build).
     */
    private function get_clients()
    {
        $creators = get_posts([
            'post_type' => self::CPT_CREATOR,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $out = [];
        foreach ($creators as $creator) {
            $slug = get_post_meta($creator->ID, self::META_CREATOR_CAT, true);
            if ($slug === '') {
                $slug = sanitize_title($creator->post_name ?: $creator->post_title);
            }
            $term = $slug !== '' ? get_term_by('slug', $slug, 'product_cat') : null;

            $out[] = [
                'id' => $creator->ID,
                'name' => $creator->post_title,
                'cat_slug' => $slug,
                'cat_term_id' => ($term && !is_wp_error($term)) ? (int) $term->term_id : 0,
                'cat_name' => ($term && !is_wp_error($term)) ? $term->name : ucwords(str_replace('-', ' ', $slug)),
            ];
        }

        return $out;
    }

    /** Map of product_cat term_id => list of existing (non-template) products. */
    private function get_existing_products_map()
    {
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
        ]);

        $map = [];
        foreach ($products as $product) {
            if (get_post_meta($product->ID, BW_Product_Templates::META_IS_TEMPLATE, true) === '1') {
                continue;
            }
            $term_ids = wp_get_object_terms($product->ID, 'product_cat', ['fields' => 'ids']);
            if (is_wp_error($term_ids) || !$term_ids) {
                continue;
            }
            $thumb = get_the_post_thumbnail_url($product->ID, 'thumbnail');
            $entry = [
                'id' => $product->ID,
                'name' => $product->post_title,
                'thumb' => $thumb ?: '',
                'status' => $product->post_status,
                'edit' => get_edit_post_link($product->ID, 'raw'),
                'template_id' => (int) get_post_meta($product->ID, self::META_TEMPLATE_ID, true),
            ];
            foreach ($term_ids as $term_id) {
                $map[(int) $term_id][] = $entry;
            }
        }

        return $map;
    }

    private function ensure_category($slug, $name)
    {
        $existing = get_term_by('slug', $slug, 'product_cat');
        if ($existing && !is_wp_error($existing)) {
            return (int) $existing->term_id;
        }
        $created = wp_insert_term($name !== '' ? $name : ucwords(str_replace('-', ' ', $slug)), 'product_cat', ['slug' => $slug]);
        return is_wp_error($created) ? 0 : (int) $created['term_id'];
    }

    /* ---------------- Builder page ---------------- */

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }

        $templates = $this->get_template_matrix();
        $logo_options = $this->get_logo_options();
        $clients = $this->get_clients();
        $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        $existing_map = $this->get_existing_products_map();
        $notice = sanitize_text_field(wp_unslash($_GET['bwsb'] ?? ''));
        ?>
        <div class="wrap bw-sb">
            <h1><?php esc_html_e('Store Builder', 'bw'); ?></h1>
            <p><?php esc_html_e('Build a client store in one pass: pick the client, tick every product to add, set colors, prices and images per product, then create them all.', 'bw'); ?></p>

            <?php if ($notice === 'seeded') : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(
                        esc_html__('Catalog synced: %1$d attribute terms and %2$d template products created.', 'bw'),
                        (int) ($_GET['terms'] ?? 0),
                        (int) ($_GET['tpls'] ?? 0)
                    );
                ?></p></div>
            <?php elseif ($notice === 'created') : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(
                        esc_html__('%1$d product(s) created for %2$s.', 'bw'),
                        (int) ($_GET['count'] ?? 0),
                        esc_html(sanitize_text_field(wp_unslash($_GET['client'] ?? '')))
                    );
                ?> <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>"><?php esc_html_e('View products', 'bw'); ?></a></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['reason'] ?? __('Could not create the products.', 'bw')))); ?></p></div>
            <?php endif; ?>

            <?php if (!$templates) : ?>
                <div class="card" style="padding:16px;max-width:640px">
                    <h2><?php esc_html_e('First run: load the catalog matrix', 'bw'); ?></h2>
                    <p><?php esc_html_e('This creates the Color and Size attributes (76 colors, 15 sizes from the Barebones catalog) and one hidden template product per catalog item.', 'bw'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SEED); ?>">
                        <?php submit_button(__('Load Catalog Matrix', 'bw')); ?>
                    </form>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bw-sb-form">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_BUILD); ?>">

                <div class="card bw-sb-card">
                    <h2><?php esc_html_e('1. Client & Store', 'bw'); ?></h2>
                    <div class="bw-sb-row">
                        <div>
                            <label><strong><?php esc_html_e('Client', 'bw'); ?></strong></label><br>
                            <select name="client_id" id="bw-sb-client" style="min-width:260px">
                                <option value=""><?php esc_html_e('— None (category only) —', 'bw'); ?></option>
                                <?php foreach ($clients as $client) : ?>
                                    <option value="<?php echo (int) $client['id']; ?>"
                                        data-cat-term="<?php echo (int) $client['cat_term_id']; ?>"
                                        data-cat-name="<?php echo esc_attr($client['cat_name']); ?>"
                                        data-cat-slug="<?php echo esc_attr($client['cat_slug']); ?>">
                                        <?php echo esc_html($client['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Products get flagged to this client for their landing-page sections.', 'bw'); ?></p>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Store category', 'bw'); ?></strong></label><br>
                            <select name="client_cat" id="bw-sb-cat" style="min-width:260px">
                                <option value=""><?php esc_html_e('— Select or auto from client —', 'bw'); ?></option>
                                <?php foreach ($cats as $cat) : ?>
                                    <option value="<?php echo (int) $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" id="bw-sb-cat-note"></p>
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Status', 'bw'); ?></strong></label><br>
                            <select name="post_status" style="min-width:160px">
                                <option value="publish"><?php esc_html_e('Publish now', 'bw'); ?></option>
                                <option value="draft"><?php esc_html_e('Draft', 'bw'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div id="bw-sb-existing" class="bw-sb-existing" hidden>
                        <h3><?php esc_html_e('Already in this store', 'bw'); ?></h3>
                        <p class="description"><?php esc_html_e('These products already exist here — avoid rebuilding them.', 'bw'); ?></p>
                        <div id="bw-sb-existing-list" class="bw-sb-existing-list"></div>
                    </div>
                </div>

                <div class="card bw-sb-card">
                    <h2><?php esc_html_e('2. Products', 'bw'); ?></h2>
                    <p class="description"><?php esc_html_e('Tick each product to add. Ticked products open their options below the list.', 'bw'); ?></p>
                    <input type="search" id="bw-sb-filter" placeholder="<?php esc_attr_e('Filter products…', 'bw'); ?>" style="width:280px;margin-bottom:10px">
                    <div id="bw-sb-template-list" class="bw-sb-template-list">
                        <?php foreach ($templates as $tpl) : ?>
                            <label class="bw-sb-tpl-row" data-tpl-name="<?php echo esc_attr(strtolower($tpl['name'])); ?>">
                                <input type="checkbox" class="bw-sb-tpl-check" value="<?php echo (int) $tpl['id']; ?>">
                                <span class="bw-sb-tpl-name"><?php echo esc_html($tpl['name']); ?></span>
                                <span class="bw-sb-tpl-badge" data-tpl-badge="<?php echo (int) $tpl['id']; ?>" hidden><?php esc_html_e('in store', 'bw'); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="bw-sb-items"></div>

                <p style="margin-top:18px">
                    <button type="submit" class="button button-primary button-hero" id="bw-sb-submit"><?php esc_html_e('Create Store Products', 'bw'); ?></button>
                    <span id="bw-sb-count" class="description" style="margin-left:10px"></span>
                </p>
            </form>
        </div>

        <style>
            .bw-sb-card { padding: 16px; margin: 0 0 18px; max-width: 1180px; }
            .bw-sb-row { display: flex; flex-wrap: wrap; gap: 22px; align-items: flex-start; }
            .bw-sb-existing { margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
            .bw-sb-existing-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; }
            .bw-sb-existing-item { display: flex; align-items: center; gap: 8px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px 8px; font-size: 12px; }
            .bw-sb-existing-item img { width: 34px; height: 34px; object-fit: cover; border-radius: 5px; border: 1px solid #dcdcde; }
            .bw-sb-existing-item .st { color: #b26a00; }
            .bw-sb-template-list { max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; column-count: 3; column-gap: 20px; }
            .bw-sb-tpl-row { display: flex; align-items: center; gap: 8px; padding: 3px 0; break-inside: avoid; }
            .bw-sb-tpl-badge { background: #fff3cd; color: #7a5b00; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700; }
            .bw-sb-item { border: 1px solid #dcdcde; border-radius: 10px; margin: 0 0 12px; max-width: 1180px; background: #fff; }
            .bw-sb-item-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #f6f7f7; border-radius: 10px 10px 0 0; cursor: pointer; }
            .bw-sb-item-head h3 { margin: 0; font-size: 14px; }
            .bw-sb-item-head .dup { color: #b26a00; font-weight: 700; font-size: 12px; }
            .bw-sb-item-body { padding: 14px 16px; display: grid; grid-template-columns: 1.4fr 1fr; gap: 18px; }
            .bw-sb-item-body.is-collapsed { display: none; }
            .bw-sb-choices { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 3px 12px; max-height: 220px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; }
            .bw-sb-choices label { display: flex; align-items: center; gap: 6px; font-size: 13px; }
            .bw-sb-chips { display: flex; flex-wrap: wrap; gap: 5px; margin: 6px 0; }
            .bw-sb-chip { background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
            .bw-sb-swatch { display: inline-block; width: 13px; height: 13px; border-radius: 3px; border: 1px solid #cbd5e1; }
            .bw-sb-img-row { display: flex; align-items: center; gap: 10px; margin: 5px 0; }
            .bw-sb-img-row img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #dcdcde; display: none; }
            .bw-sb-img-row .nm { min-width: 130px; font-weight: 600; font-size: 13px; }
            .bw-sb-price-row { display: flex; gap: 16px; margin-top: 8px; }
            .bw-sb-field-label { font-weight: 600; display: block; margin: 8px 0 4px; }
        </style>

        <script>
        (function () {
            var TEMPLATES = <?php echo wp_json_encode($templates); ?>;
            var LOGOS = <?php echo wp_json_encode($logo_options); ?>;
            var EXISTING = <?php echo wp_json_encode($existing_map); ?>;

            var clientSel = document.getElementById('bw-sb-client');
            var catSel = document.getElementById('bw-sb-cat');
            var catNote = document.getElementById('bw-sb-cat-note');
            var existingWrap = document.getElementById('bw-sb-existing');
            var existingList = document.getElementById('bw-sb-existing-list');
            var itemsWrap = document.getElementById('bw-sb-items');
            var countEl = document.getElementById('bw-sb-count');
            var filter = document.getElementById('bw-sb-filter');

            function tpl(id) { return TEMPLATES.find(function (t) { return t.id === id; }); }

            function activeCatTerm() {
                var opt = clientSel.options[clientSel.selectedIndex];
                var fromClient = opt ? parseInt(opt.getAttribute('data-cat-term') || '0', 10) : 0;
                var fromCat = parseInt(catSel.value || '0', 10);
                return fromCat || fromClient || 0;
            }

            function clientLabel() {
                var opt = clientSel.options[clientSel.selectedIndex];
                if (clientSel.value && opt) { return opt.textContent.trim(); }
                var copt = catSel.options[catSel.selectedIndex];
                return (catSel.value && copt) ? copt.textContent.trim() : '';
            }

            function existingTemplateIds() {
                var term = activeCatTerm();
                var list = (term && EXISTING[term]) ? EXISTING[term] : [];
                return list.map(function (p) { return p.template_id; }).filter(Boolean);
            }

            function renderExisting() {
                var term = activeCatTerm();
                var list = (term && EXISTING[term]) ? EXISTING[term] : [];
                if (!list.length) {
                    existingWrap.hidden = true;
                    existingList.innerHTML = '';
                } else {
                    existingWrap.hidden = false;
                    existingList.innerHTML = '';
                    list.forEach(function (p) {
                        var el = document.createElement('div');
                        el.className = 'bw-sb-existing-item';
                        var img = p.thumb ? '<img src="' + p.thumb + '" alt="">' : '';
                        var st = p.status !== 'publish' ? ' <span class="st">(' + p.status + ')</span>' : '';
                        el.innerHTML = img + '<span>' + p.name + st + '</span>';
                        existingList.appendChild(el);
                    });
                }
                // badge templates already in the store
                var owned = existingTemplateIds();
                document.querySelectorAll('[data-tpl-badge]').forEach(function (b) {
                    b.hidden = owned.indexOf(parseInt(b.getAttribute('data-tpl-badge'), 10)) === -1;
                });
                document.querySelectorAll('.bw-sb-item').forEach(function (panel) {
                    var dup = panel.querySelector('.dup');
                    if (dup) { dup.hidden = owned.indexOf(parseInt(panel.getAttribute('data-tpl'), 10)) === -1; }
                });
            }

            function syncCatFromClient() {
                var opt = clientSel.options[clientSel.selectedIndex];
                if (!clientSel.value || !opt) { catNote.textContent = ''; return; }
                var term = parseInt(opt.getAttribute('data-cat-term') || '0', 10);
                if (term) {
                    catSel.value = String(term);
                    catNote.textContent = '';
                } else {
                    catSel.value = '';
                    catNote.textContent = '"' + opt.getAttribute('data-cat-name') + '" will be created on build.';
                }
            }

            function updateHeaders() {
                var label = clientLabel();
                document.querySelectorAll('.bw-sb-item .name-preview').forEach(function (h) {
                    var t = tpl(parseInt(h.getAttribute('data-tpl'), 10));
                    h.textContent = (label ? label + ' - ' : '') + (t ? t.name : '');
                });
            }

            function updateCount() {
                var n = document.querySelectorAll('.bw-sb-tpl-check:checked').length;
                countEl.textContent = n ? (n + ' product' + (n > 1 ? 's' : '') + ' selected') : '';
            }

            function imageRows(panel, t) {
                var box = panel.querySelector('.bw-sb-images');
                var chosen = Array.prototype.map.call(panel.querySelectorAll('.bw-sb-color:checked'), function (c) {
                    return { id: c.value, name: c.getAttribute('data-name') };
                });
                box.innerHTML = chosen.length ? '' : '<em>Select colors to attach images.</em>';
                chosen.forEach(function (color) {
                    var row = document.createElement('div');
                    row.className = 'bw-sb-img-row';
                    row.innerHTML = '<span class="nm">' + color.name + '</span>' +
                        '<img alt="">' +
                        '<button type="button" class="button bw-sb-pick">Choose image</button>' +
                        '<input type="hidden" name="items[' + t.id + '][color_image][' + color.id + ']" value="">';
                    box.appendChild(row);
                    row.querySelector('.bw-sb-pick').addEventListener('click', function () {
                        var frame = wp.media({ title: 'Image for ' + color.name, multiple: false, library: { type: 'image' } });
                        frame.on('select', function () {
                            var att = frame.state().get('selection').first().toJSON();
                            row.querySelector('input').value = att.id;
                            var im = row.querySelector('img');
                            im.src = (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
                            im.style.display = 'inline-block';
                        });
                        frame.open();
                    });
                });
            }

            function buildPanel(t) {
                var panel = document.createElement('div');
                panel.className = 'bw-sb-item';
                panel.setAttribute('data-tpl', t.id);

                var colorsHtml = t.colors.map(function (c) {
                    var sw = c.hex ? '<span class="bw-sb-swatch" style="background:' + c.hex + '"></span>' : '';
                    return '<label><input type="checkbox" class="bw-sb-color" name="items[' + t.id + '][colors][]" value="' + c.id + '" data-name="' + c.name.replace(/"/g, '&quot;') + '"> ' + sw + ' ' + c.name + '</label>';
                }).join('');

                var sizesHtml = t.sizes.length
                    ? t.sizes.map(function (s) { return '<span class="bw-sb-chip">' + s.name + '</span>'; }).join('')
                    : '<em>One size / accessory (color only).</em>';

                var logosHtml = LOGOS.length
                    ? LOGOS.map(function (l) { return '<label><input type="checkbox" name="items[' + t.id + '][logo_options][]" value="' + l.id + '"> ' + l.name + '</label>'; }).join('')
                    : '<em>No logo options defined.</em>';

                panel.innerHTML =
                    '<div class="bw-sb-item-head">' +
                        '<h3><span class="name-preview" data-tpl="' + t.id + '"></span> <span class="dup" hidden>• already in store</span></h3>' +
                        '<span class="description">click to collapse</span>' +
                    '</div>' +
                    '<div class="bw-sb-item-body">' +
                        '<div>' +
                            '<span class="bw-sb-field-label">Colors</span>' +
                            '<div class="bw-sb-choices bw-sb-colors">' + colorsHtml + '</div>' +
                            '<span class="bw-sb-field-label">Sizes (pre-loaded)</span>' +
                            '<div class="bw-sb-chips">' + sizesHtml + '</div>' +
                        '</div>' +
                        '<div>' +
                            '<span class="bw-sb-field-label">Logo options</span>' +
                            '<div class="bw-sb-choices">' + logosHtml + '</div>' +
                            '<div class="bw-sb-price-row">' +
                                '<div><span class="bw-sb-field-label">Base price ($)</span>' +
                                    '<input type="number" step="0.01" min="0" name="items[' + t.id + '][base_price]" style="width:120px"></div>' +
                                '<div><span class="bw-sb-field-label">2XL+ upcharge ($)</span>' +
                                    '<input type="number" step="0.01" min="0" value="2.00" name="items[' + t.id + '][big_size_upcharge]" style="width:120px"></div>' +
                            '</div>' +
                            '<span class="bw-sb-field-label">Image per color</span>' +
                            '<div class="bw-sb-images"><em>Select colors to attach images.</em></div>' +
                        '</div>' +
                    '</div>';

                itemsWrap.appendChild(panel);

                panel.querySelector('.bw-sb-item-head').addEventListener('click', function () {
                    panel.querySelector('.bw-sb-item-body').classList.toggle('is-collapsed');
                });
                panel.querySelector('.bw-sb-colors').addEventListener('change', function () { imageRows(panel, t); });
                updateHeaders();
                renderExisting();
            }

            function removePanel(id) {
                var panel = itemsWrap.querySelector('.bw-sb-item[data-tpl="' + id + '"]');
                if (panel) { panel.remove(); }
            }

            document.getElementById('bw-sb-template-list').addEventListener('change', function (e) {
                if (!e.target.classList.contains('bw-sb-tpl-check')) { return; }
                var id = parseInt(e.target.value, 10);
                if (e.target.checked) { buildPanel(tpl(id)); } else { removePanel(id); }
                updateCount();
            });

            clientSel.addEventListener('change', function () { syncCatFromClient(); renderExisting(); updateHeaders(); });
            catSel.addEventListener('change', function () { renderExisting(); updateHeaders(); });

            filter.addEventListener('input', function () {
                var q = filter.value.toLowerCase();
                document.querySelectorAll('.bw-sb-tpl-row').forEach(function (row) {
                    row.style.display = row.getAttribute('data-tpl-name').indexOf(q) > -1 ? '' : 'none';
                });
            });

            document.querySelector('.bw-sb-form').addEventListener('submit', function (e) {
                if (!document.querySelectorAll('.bw-sb-tpl-check:checked').length) {
                    e.preventDefault();
                    alert('Tick at least one product to build.');
                    return;
                }
                if (!activeCatTerm() && !clientSel.value) {
                    e.preventDefault();
                    alert('Select a client or a store category.');
                }
            });
        }());
        </script>
        <?php
    }

    /* ---------------- Build handler ---------------- */

    public function handle_build()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $client_id = absint($_POST['client_id'] ?? 0);
        $cat_id = absint($_POST['client_cat'] ?? 0);
        $status = in_array($_POST['post_status'] ?? 'publish', ['publish', 'draft'], true) ? $_POST['post_status'] : 'publish';
        $items = (array) ($_POST['items'] ?? []);

        // Resolve client + category + naming label.
        $creator = null;
        $label = '';
        if ($client_id > 0) {
            $creator = get_post($client_id);
            if (!$creator || $creator->post_type !== self::CPT_CREATOR) {
                $this->redirect_error(__('Selected client not found.', 'bw'));
            }
            $label = $creator->post_title;
            if ($cat_id <= 0) {
                $slug = get_post_meta($client_id, self::META_CREATOR_CAT, true);
                if ($slug === '') {
                    $slug = sanitize_title($creator->post_name ?: $creator->post_title);
                }
                $cat_id = $this->ensure_category($slug, $creator->post_title);
                if ($cat_id > 0) {
                    update_post_meta($client_id, self::META_CREATOR_CAT, $slug);
                }
            }
        }

        if ($cat_id <= 0) {
            $this->redirect_error(__('Select a client or a store category.', 'bw'));
        }
        $cat = get_term($cat_id, 'product_cat');
        if (!$cat || is_wp_error($cat)) {
            $this->redirect_error(__('Store category not found.', 'bw'));
        }
        if ($label === '') {
            $label = $cat->name;
        }

        $selected = array_filter($items, static function ($cfg) {
            return is_array($cfg);
        });
        if (!$selected) {
            $this->redirect_error(__('Tick at least one product to build.', 'bw'));
        }

        $created = 0;
        foreach ($selected as $template_id => $cfg) {
            $template_id = absint($template_id);
            $template = wc_get_product($template_id);
            if (!$template || get_post_meta($template_id, BW_Product_Templates::META_IS_TEMPLATE, true) !== '1') {
                continue;
            }
            $result = $this->build_one_product($template, $cfg, $label, (int) $cat->term_id, $client_id, $status);
            if ($result) {
                $created++;
            }
        }

        if ($created === 0) {
            $this->redirect_error(__('No products were created. Check that each product has colors and a base price.', 'bw'));
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'bwsb' => 'created',
            'count' => $created,
            'client' => rawurlencode($label),
        ], admin_url('edit.php?post_type=product')));
        exit;
    }

    /**
     * Build a single variable product from one template config. Returns the
     * new product id, or 0 when the config is incomplete (skipped, not fatal).
     */
    private function build_one_product($template, array $cfg, $label, $cat_id, $creator_id, $status)
    {
        $color_ids = array_filter(array_map('absint', (array) ($cfg['colors'] ?? [])));
        $logo_ids = array_filter(array_map('absint', (array) ($cfg['logo_options'] ?? [])));
        $base_price = round((float) ($cfg['base_price'] ?? 0), 2);
        $upcharge = round((float) ($cfg['big_size_upcharge'] ?? 0), 2);
        $color_images = array_map('absint', (array) ($cfg['color_image'] ?? []));

        if (!$color_ids || $base_price <= 0) {
            return 0; // incomplete row — skip
        }

        // Validate colors + read sizes from the template matrix.
        $allowed_colors = [];
        $template_sizes = [];
        foreach ($template->get_attributes() as $attribute) {
            if (!$attribute->is_taxonomy()) {
                continue;
            }
            if ($attribute->get_name() === 'pa_color') {
                $allowed_colors = array_map('intval', $attribute->get_options());
            } elseif ($attribute->get_name() === 'pa_size') {
                foreach ($attribute->get_terms() ?: [] as $term) {
                    $template_sizes[] = ['id' => (int) $term->term_id, 'name' => $term->name, 'order' => (int) get_term_meta($term->term_id, 'order', true)];
                }
                usort($template_sizes, static fn($a, $b) => $a['order'] <=> $b['order']);
            }
        }
        $color_ids = array_values(array_intersect($color_ids, $allowed_colors));
        if (!$color_ids) {
            return 0;
        }

        $product = new WC_Product_Variable();
        $product->set_name($label . ' - ' . $template->get_name());
        $product->set_status($status);
        $product->set_description($template->get_description());
        $product->set_short_description($template->get_short_description());

        $attributes = [];

        $color_attr = new WC_Product_Attribute();
        $color_attr->set_id(wc_attribute_taxonomy_id_by_name('color'));
        $color_attr->set_name('pa_color');
        $color_attr->set_options($color_ids);
        $color_attr->set_visible(true);
        $color_attr->set_variation(true);
        $attributes[] = $color_attr;

        if ($template_sizes) {
            $size_attr = new WC_Product_Attribute();
            $size_attr->set_id(wc_attribute_taxonomy_id_by_name('size'));
            $size_attr->set_name('pa_size');
            $size_attr->set_options(array_column($template_sizes, 'id'));
            $size_attr->set_visible(true);
            $size_attr->set_variation(true);
            $attributes[] = $size_attr;
        }

        if ($logo_ids) {
            $logo_tax_id = wc_attribute_taxonomy_id_by_name('logo-options');
            if ($logo_tax_id) {
                $logo_attr = new WC_Product_Attribute();
                $logo_attr->set_id($logo_tax_id);
                $logo_attr->set_name(wc_attribute_taxonomy_name('logo-options'));
                $logo_attr->set_options($logo_ids);
                $logo_attr->set_visible(true);
                $logo_attr->set_variation(true); // "any" per variation, no combo explosion
                $attributes[] = $logo_attr;
            }
        }

        $product->set_attributes($attributes);
        $product_id = $product->save();
        if (!$product_id) {
            return 0;
        }

        wp_set_object_terms($product_id, [(int) $cat_id], 'product_cat');
        update_post_meta($product_id, self::META_TEMPLATE_ID, $template->get_id());
        if ($creator_id > 0) {
            update_post_meta($product_id, self::META_CREATOR_ID, $creator_id);
            update_post_meta($product_id, self::META_MANAGED, '1');
        }

        // Featured image + gallery from the per-color images.
        $gallery = [];
        foreach ($color_ids as $color_id) {
            if (!empty($color_images[$color_id])) {
                $gallery[] = (int) $color_images[$color_id];
            }
        }
        if ($gallery) {
            set_post_thumbnail($product_id, $gallery[0]);
            if (count($gallery) > 1) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($gallery, 1)));
            }
        }

        // Variations: color x size (color only for accessories).
        $size_rows = $template_sizes ?: [null];
        foreach ($color_ids as $color_id) {
            $color_term = get_term($color_id, 'pa_color');
            if (!$color_term || is_wp_error($color_term)) {
                continue;
            }
            foreach ($size_rows as $size) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);

                $var_attrs = ['pa_color' => $color_term->slug];
                $price = $base_price;
                if ($size) {
                    $size_term = get_term($size['id'], 'pa_size');
                    if (!$size_term || is_wp_error($size_term)) {
                        continue;
                    }
                    $var_attrs['pa_size'] = $size_term->slug;
                    if ($upcharge > 0 && $this->is_big_size($size['name'])) {
                        $price += $upcharge;
                    }
                }

                $variation->set_attributes($var_attrs);
                $variation->set_regular_price((string) $price);
                $variation->set_status('publish');
                if (!empty($color_images[$color_id])) {
                    $variation->set_image_id((int) $color_images[$color_id]);
                }
                $variation->save();
            }
        }

        WC_Product_Variable::sync($product_id);
        wc_delete_product_transients($product_id);

        return $product_id;
    }

    private function is_big_size($size_name)
    {
        foreach (self::BIG_SIZES as $big) {
            if (stripos($size_name, $big) !== false) {
                return true;
            }
        }

        return false;
    }

    private function redirect_error($reason)
    {
        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'bwsb' => 'error',
            'reason' => rawurlencode($reason),
        ], admin_url('edit.php?post_type=product')));
        exit;
    }
}

new BW_Store_Builder();
