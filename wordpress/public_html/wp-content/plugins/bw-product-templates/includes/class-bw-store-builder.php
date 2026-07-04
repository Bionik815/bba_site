<?php
/**
 * BW Store Builder — guided product creation for client stores.
 *
 * Workflow (for the Barebones worker):
 *   1) Pick the client (WooCommerce category) and a template product.
 *   2) Tick the colors offered for this client (list limited to the
 *      template's color matrix); sizes come pre-loaded from the template.
 *   3) Optionally tick logo/decoration options the customer can choose.
 *   4) Enter base price (and an optional 2XL+ upcharge).
 *   5) Attach an image per color, then create the product: a variable
 *      product with color x size variations lands in the client's store.
 *
 * The matrix itself lives on template products as ordinary WooCommerce
 * attributes (Color / Size), so refining a template's available colors or
 * sizes is done on the normal product edit screen.
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
    const META_TEMPLATE_SOURCE = '_bw_sb_template_id';
    const BIG_SIZES = ['2XL', '3XL', '4XL', '5XL', '6XL'];

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
            // Attribute taxonomies register on init; make it usable right now.
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

    /* ---------------- Builder page ---------------- */

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

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }

        $templates = $this->get_template_matrix();
        $logo_options = $this->get_logo_options();
        $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        $notice = sanitize_text_field(wp_unslash($_GET['bwsb'] ?? ''));
        ?>
        <div class="wrap bw-sb">
            <h1><?php esc_html_e('Store Builder', 'bw'); ?></h1>
            <p><?php esc_html_e('Build a client store product in one pass: template → colors → sizes → logos → price → images.', 'bw'); ?></p>

            <?php if ($notice === 'seeded') : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    printf(
                        esc_html__('Catalog synced: %1$d attribute terms and %2$d template products created.', 'bw'),
                        (int) ($_GET['terms'] ?? 0),
                        (int) ($_GET['tpls'] ?? 0)
                    );
                ?></p></div>
            <?php elseif ($notice === 'created') : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e('Product created:', 'bw'); ?>
                    <a href="<?php echo esc_url(get_edit_post_link((int) ($_GET['product'] ?? 0))); ?>"><?php echo esc_html(get_the_title((int) ($_GET['product'] ?? 0))); ?></a>
                    (<?php echo (int) ($_GET['variations'] ?? 0); ?> <?php esc_html_e('variations', 'bw'); ?>)
                    &mdash; <a href="<?php echo esc_url(get_permalink((int) ($_GET['product'] ?? 0))); ?>" target="_blank"><?php esc_html_e('View in store', 'bw'); ?></a>
                </p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['reason'] ?? __('Could not create the product.', 'bw')))); ?></p></div>
            <?php endif; ?>

            <?php if (!$templates) : ?>
                <div class="card" style="padding:16px;max-width:640px">
                    <h2><?php esc_html_e('First run: load the catalog matrix', 'bw'); ?></h2>
                    <p><?php esc_html_e('This creates the Color and Size attributes (76 colors, 15 sizes from the Barebones catalog) and one hidden template product per catalog item. Afterwards you can refine each template\'s available colors/sizes on its product edit screen.', 'bw'); ?></p>
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

                <div class="bw-sb-grid">
                    <div class="card bw-sb-card">
                        <h2><?php esc_html_e('1. Client & Template', 'bw'); ?></h2>
                        <p>
                            <label><strong><?php esc_html_e('Client store (category)', 'bw'); ?></strong></label><br>
                            <select name="client_cat" required style="width:100%">
                                <option value=""><?php esc_html_e('— Select client —', 'bw'); ?></option>
                                <?php foreach ($cats as $cat) : ?>
                                    <option value="<?php echo (int) $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Product template', 'bw'); ?></strong></label><br>
                            <select name="template_id" id="bw-sb-template" required style="width:100%">
                                <option value=""><?php esc_html_e('— Select template —', 'bw'); ?></option>
                                <?php foreach ($templates as $tpl) : ?>
                                    <option value="<?php echo (int) $tpl['id']; ?>"><?php echo esc_html($tpl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Product name', 'bw'); ?></strong></label><br>
                            <input type="text" name="product_name" id="bw-sb-name" style="width:100%" placeholder="<?php esc_attr_e('Auto: Template — Client', 'bw'); ?>">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Status', 'bw'); ?></strong></label><br>
                            <select name="post_status">
                                <option value="publish"><?php esc_html_e('Publish now', 'bw'); ?></option>
                                <option value="draft"><?php esc_html_e('Draft', 'bw'); ?></option>
                            </select>
                        </p>
                    </div>

                    <div class="card bw-sb-card">
                        <h2><?php esc_html_e('2. Colors', 'bw'); ?></h2>
                        <p class="description"><?php esc_html_e('Only colors available for the chosen template are listed.', 'bw'); ?></p>
                        <div id="bw-sb-colors" class="bw-sb-choices"><em><?php esc_html_e('Pick a template first.', 'bw'); ?></em></div>

                        <h2 style="margin-top:18px"><?php esc_html_e('Sizes (pre-loaded)', 'bw'); ?></h2>
                        <div id="bw-sb-sizes" class="bw-sb-chips"><em><?php esc_html_e('Pick a template first.', 'bw'); ?></em></div>
                    </div>

                    <div class="card bw-sb-card">
                        <h2><?php esc_html_e('3. Logo Options & Pricing', 'bw'); ?></h2>
                        <?php if ($logo_options) : ?>
                            <p class="description"><?php esc_html_e('Customer-selectable decoration options for this product.', 'bw'); ?></p>
                            <div class="bw-sb-choices">
                                <?php foreach ($logo_options as $opt) : ?>
                                    <label><input type="checkbox" name="logo_options[]" value="<?php echo (int) $opt['id']; ?>"> <?php echo esc_html($opt['name']); ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="description"><?php esc_html_e('No logo options defined yet (Products → Attributes → Logo Options).', 'bw'); ?></p>
                        <?php endif; ?>
                        <p>
                            <label><strong><?php esc_html_e('Base price ($)', 'bw'); ?></strong></label><br>
                            <input type="number" name="base_price" step="0.01" min="0" required style="width:140px">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('2XL+ upcharge ($)', 'bw'); ?></strong></label><br>
                            <input type="number" name="big_size_upcharge" step="0.01" min="0" value="2.00" style="width:140px">
                            <span class="description"><?php esc_html_e('Added to 2XL through 6XL.', 'bw'); ?></span>
                        </p>
                    </div>

                    <div class="card bw-sb-card">
                        <h2><?php esc_html_e('4. Images per color', 'bw'); ?></h2>
                        <p class="description"><?php esc_html_e('Attach a product photo for each selected color. The first becomes the featured image; each color\'s variations use its photo.', 'bw'); ?></p>
                        <div id="bw-sb-images"><em><?php esc_html_e('Select colors first.', 'bw'); ?></em></div>
                    </div>
                </div>

                <p style="margin-top:18px">
                    <button type="submit" class="button button-primary button-hero"><?php esc_html_e('Create Store Product', 'bw'); ?></button>
                </p>
            </form>
        </div>

        <style>
            .bw-sb-grid { display: grid; grid-template-columns: repeat(2, minmax(320px, 560px)); gap: 18px; }
            .bw-sb-card { padding: 16px; margin: 0; }
            .bw-sb-choices { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 4px 12px; max-height: 260px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
            .bw-sb-choices label { display: flex; align-items: center; gap: 6px; }
            .bw-sb-chips { display: flex; flex-wrap: wrap; gap: 6px; }
            .bw-sb-chip { background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 999px; padding: 4px 12px; font-weight: 600; }
            .bw-sb-swatch { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 1px solid #cbd5e1; vertical-align: middle; }
            .bw-sb-img-row { display: flex; align-items: center; gap: 10px; margin: 6px 0; }
            .bw-sb-img-row img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #dcdcde; }
            .bw-sb-img-row .bw-sb-img-name { min-width: 140px; font-weight: 600; }
        </style>

        <script>
        (function () {
            var TEMPLATES = <?php echo wp_json_encode($templates); ?>;
            var templateSelect = document.getElementById('bw-sb-template');
            var clientSelect = document.querySelector('[name=client_cat]');
            var nameInput = document.getElementById('bw-sb-name');
            var colorsBox = document.getElementById('bw-sb-colors');
            var sizesBox = document.getElementById('bw-sb-sizes');
            var imagesBox = document.getElementById('bw-sb-images');

            function currentTemplate() {
                var id = parseInt(templateSelect.value, 10);
                return TEMPLATES.find(function (t) { return t.id === id; }) || null;
            }

            function updateName() {
                var tpl = currentTemplate();
                var client = clientSelect.options[clientSelect.selectedIndex];
                if (tpl && client && client.value && !nameInput.dataset.touched) {
                    nameInput.value = tpl.name + ' — ' + client.textContent.trim();
                }
            }

            nameInput.addEventListener('input', function () { nameInput.dataset.touched = '1'; });
            clientSelect.addEventListener('change', updateName);

            function renderTemplate() {
                var tpl = currentTemplate();
                if (!tpl) { return; }

                colorsBox.innerHTML = '';
                tpl.colors.forEach(function (c) {
                    var label = document.createElement('label');
                    var swatch = c.hex ? '<span class="bw-sb-swatch" style="background:' + c.hex + '"></span> ' : '';
                    label.innerHTML = '<input type="checkbox" name="colors[]" value="' + c.id + '" data-color-name="' + c.name.replace(/"/g, '&quot;') + '"> ' + swatch + c.name;
                    colorsBox.appendChild(label);
                });

                sizesBox.innerHTML = '';
                if (tpl.sizes.length) {
                    tpl.sizes.forEach(function (s) {
                        var chip = document.createElement('span');
                        chip.className = 'bw-sb-chip';
                        chip.textContent = s.name;
                        sizesBox.appendChild(chip);
                    });
                } else {
                    sizesBox.innerHTML = '<em><?php echo esc_js(__('No sizes for this product (one-size / accessory).', 'bw')); ?></em>';
                }

                renderImageRows();
                updateName();
            }

            function selectedColors() {
                return Array.prototype.map.call(colorsBox.querySelectorAll('input:checked'), function (input) {
                    return { id: input.value, name: input.getAttribute('data-color-name') };
                });
            }

            function renderImageRows() {
                var chosen = selectedColors();
                imagesBox.innerHTML = chosen.length ? '' : '<em><?php echo esc_js(__('Select colors first.', 'bw')); ?></em>';
                chosen.forEach(function (color) {
                    var row = document.createElement('div');
                    row.className = 'bw-sb-img-row';
                    row.innerHTML = '<span class="bw-sb-img-name">' + color.name + '</span>' +
                        '<img src="" alt="" style="display:none">' +
                        '<button type="button" class="button bw-sb-pick"><?php echo esc_js(__('Choose image', 'bw')); ?></button>' +
                        '<input type="hidden" name="color_image[' + color.id + ']" value="">';
                    imagesBox.appendChild(row);

                    row.querySelector('.bw-sb-pick').addEventListener('click', function () {
                        var frame = wp.media({ title: '<?php echo esc_js(__('Image for', 'bw')); ?> ' + color.name, multiple: false, library: { type: 'image' } });
                        frame.on('select', function () {
                            var att = frame.state().get('selection').first().toJSON();
                            row.querySelector('input[type=hidden]').value = att.id;
                            var img = row.querySelector('img');
                            img.src = (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
                            img.style.display = 'inline-block';
                        });
                        frame.open();
                    });
                });
            }

            templateSelect.addEventListener('change', renderTemplate);
            colorsBox.addEventListener('change', renderImageRows);
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

        $template_id = absint($_POST['template_id'] ?? 0);
        $client_cat = absint($_POST['client_cat'] ?? 0);
        $color_ids = array_filter(array_map('absint', (array) ($_POST['colors'] ?? [])));
        $logo_ids = array_filter(array_map('absint', (array) ($_POST['logo_options'] ?? [])));
        $base_price = round((float) ($_POST['base_price'] ?? 0), 2);
        $upcharge = round((float) ($_POST['big_size_upcharge'] ?? 0), 2);
        $status = in_array($_POST['post_status'] ?? 'publish', ['publish', 'draft'], true) ? $_POST['post_status'] : 'publish';
        $color_images = array_map('absint', (array) ($_POST['color_image'] ?? []));

        $template = wc_get_product($template_id);
        $cat = get_term($client_cat, 'product_cat');
        if (!$template || !$cat || is_wp_error($cat)) {
            $this->redirect_error(__('Select a template and a client store.', 'bw'));
        }
        if (!$color_ids) {
            $this->redirect_error(__('Select at least one color.', 'bw'));
        }
        if ($base_price <= 0) {
            $this->redirect_error(__('Enter a base price.', 'bw'));
        }

        // Validate colors against the template's matrix.
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
            $this->redirect_error(__('Selected colors are not available for this template.', 'bw'));
        }

        $name = sanitize_text_field(wp_unslash($_POST['product_name'] ?? ''));
        if ($name === '') {
            $name = $template->get_name() . ' — ' . $cat->name;
        }

        // --- Create the variable product ---
        $product = new WC_Product_Variable();
        $product->set_name($name);
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
                $logo_attr->set_variation(true); // variations use "any", so no combo explosion
                $attributes[] = $logo_attr;
            }
        }

        $product->set_attributes($attributes);
        $product_id = $product->save();
        if (!$product_id) {
            $this->redirect_error(__('Could not create the product.', 'bw'));
        }

        wp_set_object_terms($product_id, [(int) $cat->term_id], 'product_cat');
        update_post_meta($product_id, self::META_TEMPLATE_SOURCE, $template_id);

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

        // --- Variations: color x size (or color only) ---
        $variations_created = 0;
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
                // Logo attribute left unset = "Any" (customer picks, price unchanged).

                $variation->set_attributes($var_attrs);
                $variation->set_regular_price((string) $price);
                $variation->set_status('publish');
                if (!empty($color_images[$color_id])) {
                    $variation->set_image_id((int) $color_images[$color_id]);
                }
                $variation->save();
                $variations_created++;
            }
        }

        WC_Product_Variable::sync($product_id);
        wc_delete_product_transients($product_id);

        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'bwsb' => 'created',
            'product' => $product_id,
            'variations' => $variations_created,
        ], admin_url('edit.php?post_type=product')));
        exit;
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
