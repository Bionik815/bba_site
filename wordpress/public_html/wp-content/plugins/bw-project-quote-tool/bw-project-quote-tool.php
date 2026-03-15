<?php
/**
 * Plugin Name: BW Project Quote Tool
 * Description: Staff-only quoting workspace with a DB-backed catalog for products, colors, print methods, and saved project quotes.
 * Version: 0.4.0
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Project_Quote_Tool
{
    private static $instance = null;

    const VERSION = '0.4.0';
    const POST_TYPE = 'bw_project_quote';
    const META_KEY = '_bw_project_quote_snapshot';
    const SHORTCODE = 'bw_quote_tool';
    const STAFF_CAPABILITY = 'edit_shop_orders';
    const ADMIN_CAPABILITY = 'manage_options';

    const ACTION_SAVE_QUOTE = 'bwqt_save_quote';
    const ACTION_SAVE_PRODUCT = 'bwqt_save_product';
    const ACTION_DELETE_PRODUCT = 'bwqt_delete_product';
    const ACTION_SAVE_PRINT = 'bwqt_save_print_method';
    const ACTION_DELETE_PRINT = 'bwqt_delete_print_method';

    public function __construct()
    {
        self::$instance = $this;
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);

        add_action('init', [$this, 'maybe_upgrade_schema'], 1);
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);

        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);

        add_action('admin_post_' . self::ACTION_SAVE_QUOTE, [$this, 'handle_quote_save']);
        add_action('admin_post_' . self::ACTION_SAVE_PRODUCT, [$this, 'handle_product_save']);
        add_action('admin_post_' . self::ACTION_DELETE_PRODUCT, [$this, 'handle_product_delete']);
        add_action('admin_post_' . self::ACTION_SAVE_PRINT, [$this, 'handle_print_method_save']);
        add_action('admin_post_' . self::ACTION_DELETE_PRINT, [$this, 'handle_print_method_delete']);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'register_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_columns'], 10, 2);
    }

    public static function get_instance()
    {
        return self::$instance;
    }

    public static function activate()
    {
        $instance = new self();
        $instance->install_or_upgrade();
        flush_rewrite_rules();
    }

    public function maybe_upgrade_schema()
    {
        $stored_version = get_option('bwqt_version');
        if ($stored_version === self::VERSION) {
            return;
        }

        $this->install_or_upgrade();
    }

    public function register_post_type()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Project Quotes', 'bwqt'),
                'singular_name' => __('Project Quote', 'bwqt'),
                'menu_name' => __('Project Quotes', 'bwqt'),
                'edit_item' => __('Quote Details', 'bwqt'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'map_meta_cap' => false,
            'capabilities' => [
                'edit_post' => self::STAFF_CAPABILITY,
                'read_post' => self::STAFF_CAPABILITY,
                'delete_post' => self::ADMIN_CAPABILITY,
                'edit_posts' => self::STAFF_CAPABILITY,
                'edit_others_posts' => self::STAFF_CAPABILITY,
                'publish_posts' => self::STAFF_CAPABILITY,
                'read_private_posts' => self::STAFF_CAPABILITY,
                'delete_posts' => self::ADMIN_CAPABILITY,
                'delete_private_posts' => self::ADMIN_CAPABILITY,
                'delete_published_posts' => self::ADMIN_CAPABILITY,
                'delete_others_posts' => self::ADMIN_CAPABILITY,
                'edit_private_posts' => self::STAFF_CAPABILITY,
                'edit_published_posts' => self::STAFF_CAPABILITY,
                'create_posts' => self::STAFF_CAPABILITY,
            ],
            'supports' => ['title', 'author'],
        ]);
    }

    public function register_admin_pages()
    {
        add_submenu_page(
            'woocommerce',
            __('Project Quotes', 'bwqt'),
            __('Project Quotes', 'bwqt'),
            self::STAFF_CAPABILITY,
            'edit.php?post_type=' . self::POST_TYPE
        );

        add_submenu_page(
            'woocommerce',
            __('Quote Catalog', 'bwqt'),
            __('Quote Catalog', 'bwqt'),
            self::ADMIN_CAPABILITY,
            'bwqt-catalog',
            [$this, 'render_catalog_page']
        );
    }

    public function register_meta_boxes()
    {
        add_meta_box(
            'bwqt_quote_snapshot',
            __('Quote Snapshot', 'bwqt'),
            [$this, 'render_quote_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    private function table($name)
    {
        global $wpdb;
        return $wpdb->prefix . 'bwqt_' . $name;
    }

    private function create_tables()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $products = $this->table('catalog_products');
        $prints = $this->table('print_methods');
        $quotes = $this->table('quotes');
        $quote_lines = $this->table('quote_lines');

        dbDelta("
            CREATE TABLE {$products} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_code VARCHAR(80) NOT NULL,
                product_name VARCHAR(190) NOT NULL,
                category_name VARCHAR(120) NOT NULL DEFAULT '',
                vendor_name VARCHAR(120) NOT NULL DEFAULT '',
                vendor_url TEXT NULL,
                base_unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                size_profile VARCHAR(120) NOT NULL DEFAULT '',
                extended_size_surcharge DECIMAL(10,2) NOT NULL DEFAULT 5.00,
                color_options LONGTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY product_code (product_code),
                KEY is_active (is_active)
            ) {$charset};
        ");

        dbDelta("
            CREATE TABLE {$prints} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                method_name VARCHAR(120) NOT NULL,
                placement_name VARCHAR(120) NOT NULL DEFAULT '',
                pricing_type VARCHAR(60) NOT NULL DEFAULT 'Per Print',
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                requires_color_count TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY method_name (method_name),
                KEY is_active (is_active)
            ) {$charset};
        ");

        dbDelta("
            CREATE TABLE {$quotes} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id BIGINT UNSIGNED NOT NULL,
                client_name VARCHAR(190) NOT NULL,
                project_name VARCHAR(190) NOT NULL,
                contact_email VARCHAR(190) NOT NULL DEFAULT '',
                contact_phone VARCHAR(80) NOT NULL DEFAULT '',
                need_by_date DATE NULL,
                quoted_by VARCHAR(120) NOT NULL DEFAULT '',
                quote_status VARCHAR(60) NOT NULL DEFAULT 'Pending Review',
                project_notes LONGTEXT NULL,
                internal_notes LONGTEXT NULL,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                manager_margin_percent DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                labor_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                design_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                tax_rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                review_notes LONGTEXT NULL,
                reviewed_by VARCHAR(120) NOT NULL DEFAULT '',
                reviewed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY post_id (post_id),
                KEY client_name (client_name)
            ) {$charset};
        ");

        dbDelta("
            CREATE TABLE {$quote_lines} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                quote_id BIGINT UNSIGNED NOT NULL,
                line_position INT UNSIGNED NOT NULL DEFAULT 0,
                product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                product_code VARCHAR(80) NOT NULL DEFAULT '',
                product_name VARCHAR(190) NOT NULL DEFAULT '',
                category_name VARCHAR(120) NOT NULL DEFAULT '',
                size_profile VARCHAR(120) NOT NULL DEFAULT '',
                color_name VARCHAR(120) NOT NULL DEFAULT '',
                regular_qty INT UNSIGNED NOT NULL DEFAULT 0,
                extended_qty INT UNSIGNED NOT NULL DEFAULT 0,
                total_qty INT UNSIGNED NOT NULL DEFAULT 0,
                base_unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                extended_size_surcharge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                print_method_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                print_method_name VARCHAR(120) NOT NULL DEFAULT '',
                print_placement VARCHAR(120) NOT NULL DEFAULT '',
                print_pricing_type VARCHAR(60) NOT NULL DEFAULT '',
                print_unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                print_color_count INT UNSIGNED NOT NULL DEFAULT 0,
                design_group VARCHAR(120) NOT NULL DEFAULT '',
                screen_locations_json LONGTEXT NULL,
                material_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                print_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                line_notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY quote_id (quote_id),
                KEY product_id (product_id),
                KEY print_method_id (print_method_id)
            ) {$charset};
        ");
    }

    private function seed_defaults()
    {
        global $wpdb;

        $products_table = $this->table('catalog_products');
        $prints_table = $this->table('print_methods');

        $product_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$products_table}");
        if ($product_count === 0) {
            $products = [
                ['BG1500', 'Tote Bag', 'Bags', 'SanMar', 'https://www.sanmar.com/search/?text=BG1500', 1.40, 'OSFA', 0.00, "Black\nNatural\nRed\nNavy"],
                ['C865', 'Flex Fit (Curved Bill)', 'Headwear', 'SanMar', 'https://www.sanmar.com/search/?text=C865', 8.48, 'S/M, L/XL', 0.00, "Black\nKhaki\nNavy\nRed\nWhite"],
                ['CP45', 'Visor', 'Headwear', 'SanMar', 'https://www.sanmar.com/search/?text=CP45', 3.09, 'OSFA', 0.00, "Black\nKhaki\nNavy\nRed\nWhite"],
                ['CP90', 'Knit Beanie', 'Headwear', 'SanMar', 'https://www.sanmar.com/search/?text=CP90', 2.06, 'OSFA', 0.00, "Black\nCharcoal\nNavy\nRed"],
                ['DM1170L', 'Ladies V-Neck Tee', 'Shirts', 'SanMar', 'https://www.sanmar.com/search/?text=DM1170L', 4.44, 'XS-4XL', 5.00, "Black\nWhite\nNavy\nRed\nAthletic Heather"],
                ['DM136', "Men's 3/4 Sleeve Tee", 'Shirts', 'SanMar', 'https://www.sanmar.com/search/?text=DM136', 6.20, 'XS-4XL', 5.00, "Black\nWhite\nHeather Grey\nNavy\nRed"],
                ['DM136L', 'Ladies 3/4 Sleeve Tee', 'Shirts', 'SanMar', 'https://www.sanmar.com/search/?text=DM136L', 6.20, 'XS-4XL', 5.00, "Black\nWhite\nHeather Grey\nNavy\nRed"],
                ['DM138L', 'Ladies Racerback Tank', 'Shirts', 'SanMar', 'https://www.sanmar.com/search/?text=DM138L', 5.16, 'XS-4XL', 5.00, "Black\nWhite\nNavy\nRed\nHeather Grey"],
                ['F223', "Men's Microfleece Jacket", 'Jackets', 'SanMar', 'https://www.sanmar.com/search/?text=F223', 13.96, 'XS-4XL', 5.00, "Black\nNavy\nCharcoal Grey"],
                ['J344', 'Zephyr Full Zip Jacket', 'Jackets', 'SanMar', 'https://www.sanmar.com/search/?text=J344', 12.93, 'XS-4XL', 5.00, "Black\nBattleship Grey\nNavy"],
                ['JST82', 'Insulated Leatherman Jacket', 'Jackets', 'SanMar', 'https://www.sanmar.com/search/?text=JST82', 41.39, 'XS-4XL', 5.00, "Black\nNavy\nDark Green"],
                ['PC54', 'Core Cotton Tee', 'Shirts', 'SanMar', 'https://www.sanmar.com/search/?text=PC54', 3.25, 'S-4XL', 5.00, "Aquatic Blue\nAsh\nAthletic Heather\nBlack\nCardinal\nCarolina Blue\nNavy\nRed\nWhite"],
            ];

            foreach ($products as $product) {
                $wpdb->insert($products_table, [
                    'product_code' => $product[0],
                    'product_name' => $product[1],
                    'category_name' => $product[2],
                    'vendor_name' => $product[3],
                    'vendor_url' => $product[4],
                    'base_unit_cost' => $product[5],
                    'size_profile' => $product[6],
                    'extended_size_surcharge' => $product[7],
                    'color_options' => $product[8],
                    'is_active' => 1,
                ]);
            }
        }

        $print_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prints_table}");
        if ($print_count === 0) {
            $methods = [
                ['DTF Transfer', 'Full Front', 'Per Print', 4.00, 0],
                ['DTF Transfer', 'Full Back', 'Per Print', 4.00, 0],
                ['DTF Transfer', 'Left Chest', 'Per Print', 2.50, 0],
                ['DTF Transfer', 'Right Chest', 'Per Print', 2.50, 0],
                ['DTF Transfer', 'Pocket', 'Per Print', 2.50, 0],
                ['Sublimation', 'Full Front', 'Per Print', 5.00, 0],
                ['Sublimation', 'Full Back', 'Per Print', 5.00, 0],
                ['Sublimation', 'Left Chest', 'Per Print', 3.00, 0],
                ['Vinyl', 'Full Front', 'Per Print', 4.50, 0],
                ['Glitter', 'Full Front', 'Per Print', 6.00, 0],
                ['Metal', 'Full Front', 'Per Print', 6.00, 0],
                ['Screen Print', 'Full Front', 'Per Print', 0.00, 1],
                ['Screen Print', 'Full Back', 'Per Print', 0.00, 1],
                ['Screen Print', 'Left Chest', 'Per Print', 0.00, 1],
            ];

            foreach ($methods as $method) {
                $wpdb->insert($prints_table, [
                    'method_name' => $method[0],
                    'placement_name' => $method[1],
                    'pricing_type' => $method[2],
                    'unit_price' => $method[3],
                    'requires_color_count' => $method[4],
                    'is_active' => 1,
                ]);
            }
        }
    }

    private function install_or_upgrade()
    {
        $this->create_tables();
        $this->seed_defaults();
        update_option('bwqt_version', self::VERSION);
    }

    public function register_columns($columns)
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'title' => __('Quote', 'bwqt'),
            'client' => __('Client', 'bwqt'),
            'project' => __('Project', 'bwqt'),
            'status' => __('Status', 'bwqt'),
            'total' => __('Total', 'bwqt'),
            'date' => __('Date', 'bwqt'),
        ];
    }

    public function render_columns($column, $post_id)
    {
        $snapshot = $this->get_quote_snapshot($post_id);

        if ($column === 'client') {
            echo esc_html($snapshot['client_name'] ?: '—');
            return;
        }

        if ($column === 'project') {
            echo esc_html($snapshot['project_name'] ?: '—');
            return;
        }

        if ($column === 'status') {
            echo esc_html($snapshot['quote_status'] ?: 'Pending Review');
            return;
        }

        if ($column === 'total') {
            echo esc_html($this->format_money($snapshot['totals']['grand_total'] ?? 0));
            return;
        }
    }

    public function render_quote_meta_box($post)
    {
        $snapshot = $this->get_quote_snapshot($post->ID);
        $lines = $snapshot['lines'] ?? [];
        ?>
        <style>
            .bwqt-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px}
            .bwqt-admin-card{background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px}
            .bwqt-admin-table{width:100%;border-collapse:collapse}
            .bwqt-admin-table th,.bwqt-admin-table td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
            .bwqt-admin-table th{text-transform:uppercase;font-size:11px;letter-spacing:.04em;color:#50575e}
        </style>

        <div class="bwqt-admin-grid">
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Client', 'bwqt'); ?></strong><br><?php echo esc_html($snapshot['client_name'] ?: '—'); ?></div>
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Project', 'bwqt'); ?></strong><br><?php echo esc_html($snapshot['project_name'] ?: '—'); ?></div>
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Quoted By', 'bwqt'); ?></strong><br><?php echo esc_html($snapshot['quoted_by'] ?: '—'); ?></div>
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Status', 'bwqt'); ?></strong><br><?php echo esc_html($snapshot['quote_status'] ?: 'Pending Review'); ?></div>
        </div>

        <table class="bwqt-admin-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'bwqt'); ?></th>
                    <th><?php esc_html_e('Color', 'bwqt'); ?></th>
                    <th><?php esc_html_e('Qty', 'bwqt'); ?></th>
                    <th><?php esc_html_e('Print', 'bwqt'); ?></th>
                    <th><?php esc_html_e('Line Total', 'bwqt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lines) : ?>
                    <?php foreach ($lines as $line) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($line['product_code'] . ' | ' . $line['product_name']); ?></strong>
                                <div><?php echo esc_html($line['size_profile']); ?></div>
                            </td>
                            <td><?php echo esc_html($line['color_name'] ?: '—'); ?></td>
                            <td>
                                <?php
                                echo esc_html('Total: ' . (int) $line['total_qty']);
                                echo '<br>';
                                echo esc_html('2XL+: ' . (int) $line['extended_qty']);
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html(trim($line['print_method_name'] . ' / ' . $line['print_placement'], ' /')); ?>
                                <?php if (!empty($line['print_color_count'])) : ?>
                                    <div><?php echo esc_html('Colors: ' . (int) $line['print_color_count']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($line['screen_locations'])) : ?>
                                    <?php foreach ($line['screen_locations'] as $location) : ?>
                                        <div><?php echo esc_html($location['label'] . ': ' . (int) $location['colors'] . ' colors'); ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($line['design_group'])) : ?>
                                    <div><?php echo esc_html('Design: ' . $line['design_group']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($this->format_money($line['line_total'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><em><?php esc_html_e('No saved line items.', 'bwqt'); ?></em></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="bwqt-admin-grid" style="margin-top:16px;">
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Subtotal', 'bwqt'); ?></strong><br><?php echo esc_html($this->format_money($snapshot['totals']['subtotal'] ?? 0)); ?></div>
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Grand Total', 'bwqt'); ?></strong><br><?php echo esc_html($this->format_money($snapshot['totals']['grand_total'] ?? 0)); ?></div>
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Need By', 'bwqt'); ?></strong><br><?php echo esc_html($snapshot['need_by_date'] ?: '—'); ?></div>
            <div class="bwqt-admin-card"><strong><?php esc_html_e('Email', 'bwqt'); ?></strong><br><?php echo esc_html($snapshot['contact_email'] ?: '—'); ?></div>
        </div>

        <?php if (!empty($snapshot['project_notes']) || !empty($snapshot['internal_notes'])) : ?>
            <div class="bwqt-admin-grid">
                <?php if (!empty($snapshot['project_notes'])) : ?>
                    <div class="bwqt-admin-card"><strong><?php esc_html_e('Project Notes', 'bwqt'); ?></strong><br><?php echo nl2br(esc_html($snapshot['project_notes'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($snapshot['internal_notes'])) : ?>
                    <div class="bwqt-admin-card"><strong><?php esc_html_e('Internal Notes', 'bwqt'); ?></strong><br><?php echo nl2br(esc_html($snapshot['internal_notes'])); ?></div>
                <?php endif; ?>
            </div>
        <?php endif;
    }

    public function render_catalog_page()
    {
        if (!current_user_can(self::ADMIN_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to manage the quote catalog.', 'bwqt'), 403);
        }

        $products = $this->get_catalog_products(true);
        $prints = $this->get_print_methods(true);
        $editing_product = $this->get_editing_product();
        $editing_print = $this->get_editing_print_method();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Quote Catalog', 'bwqt'); ?></h1>
            <p><?php esc_html_e('This catalog feeds the staff quote builder. Product costs, size profiles, color lists, and print methods should only be changed here by admin.', 'bwqt'); ?></p>
            <?php $this->render_admin_notice(); ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;">
                    <h2><?php echo $editing_product ? esc_html__('Edit Product', 'bwqt') : esc_html__('Add Product', 'bwqt'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE_PRODUCT); ?>">
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($editing_product['id'] ?? 0); ?>">
                        <?php wp_nonce_field(self::ACTION_SAVE_PRODUCT, 'bwqt_product_nonce'); ?>
                        <?php $this->render_product_form_fields($editing_product); ?>
                        <p><button type="submit" class="button button-primary"><?php esc_html_e('Save Product', 'bwqt'); ?></button></p>
                    </form>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;">
                    <h2><?php echo $editing_print ? esc_html__('Edit Print Method', 'bwqt') : esc_html__('Add Print Method', 'bwqt'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE_PRINT); ?>">
                        <input type="hidden" name="print_method_id" value="<?php echo esc_attr($editing_print['id'] ?? 0); ?>">
                        <?php wp_nonce_field(self::ACTION_SAVE_PRINT, 'bwqt_print_nonce'); ?>
                        <?php $this->render_print_form_fields($editing_print); ?>
                        <p><button type="submit" class="button button-primary"><?php esc_html_e('Save Print Method', 'bwqt'); ?></button></p>
                    </form>
                </div>
            </div>

            <h2 style="margin-top:28px;"><?php esc_html_e('Products', 'bwqt'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Code', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Name', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Base Cost', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Sizes', 'bwqt'); ?></th>
                        <th><?php esc_html_e('2XL+ Surcharge', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Colors', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Actions', 'bwqt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product) : ?>
                        <tr>
                            <td><?php echo esc_html($product['product_code']); ?></td>
                            <td><?php echo esc_html($product['product_name']); ?></td>
                            <td><?php echo esc_html($this->format_money($product['base_unit_cost'])); ?></td>
                            <td><?php echo esc_html($product['size_profile']); ?></td>
                            <td><?php echo esc_html($this->format_money($product['extended_size_surcharge'])); ?></td>
                            <td><?php echo esc_html(implode(', ', array_slice($product['color_list'], 0, 6))); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'bwqt-catalog', 'edit_product' => $product['id']], admin_url('admin.php'))); ?>"><?php esc_html_e('Edit', 'bwqt'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => self::ACTION_DELETE_PRODUCT, 'product_id' => $product['id']], admin_url('admin-post.php')), self::ACTION_DELETE_PRODUCT . '_' . $product['id'])); ?>" onclick="return confirm('Delete this product from the quote catalog?');"><?php esc_html_e('Delete', 'bwqt'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:28px;"><?php esc_html_e('Print Methods', 'bwqt'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Method', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Placement', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Unit Price', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Color Count', 'bwqt'); ?></th>
                        <th><?php esc_html_e('Actions', 'bwqt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prints as $print) : ?>
                        <tr>
                            <td><?php echo esc_html($print['method_name']); ?></td>
                            <td><?php echo esc_html($print['placement_name']); ?></td>
                            <td><?php echo esc_html($this->format_money($print['unit_price'])); ?></td>
                            <td><?php echo esc_html($print['requires_color_count'] ? 'Required' : 'No'); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'bwqt-catalog', 'edit_print' => $print['id']], admin_url('admin.php'))); ?>"><?php esc_html_e('Edit', 'bwqt'); ?></a>
                                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => self::ACTION_DELETE_PRINT, 'print_method_id' => $print['id']], admin_url('admin-post.php')), self::ACTION_DELETE_PRINT . '_' . $print['id'])); ?>" onclick="return confirm('Delete this print method from the quote catalog?');"><?php esc_html_e('Delete', 'bwqt'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_product_form_fields($product = [])
    {
        $product = wp_parse_args($product ?: [], [
            'product_code' => '',
            'product_name' => '',
            'category_name' => '',
            'vendor_name' => '',
            'vendor_url' => '',
            'base_unit_cost' => 0,
            'size_profile' => '',
            'extended_size_surcharge' => 5,
            'color_options' => '',
            'is_active' => 1,
        ]);
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr><th><label for="bwqt_product_code"><?php esc_html_e('Product Code', 'bwqt'); ?></label></th><td><input id="bwqt_product_code" name="product_code" type="text" class="regular-text" value="<?php echo esc_attr($product['product_code']); ?>" required></td></tr>
                <tr><th><label for="bwqt_product_name"><?php esc_html_e('Product Name', 'bwqt'); ?></label></th><td><input id="bwqt_product_name" name="product_name" type="text" class="regular-text" value="<?php echo esc_attr($product['product_name']); ?>" required></td></tr>
                <tr><th><label for="bwqt_category_name"><?php esc_html_e('Category', 'bwqt'); ?></label></th><td><input id="bwqt_category_name" name="category_name" type="text" class="regular-text" value="<?php echo esc_attr($product['category_name']); ?>"></td></tr>
                <tr><th><label for="bwqt_vendor_name"><?php esc_html_e('Vendor', 'bwqt'); ?></label></th><td><input id="bwqt_vendor_name" name="vendor_name" type="text" class="regular-text" value="<?php echo esc_attr($product['vendor_name']); ?>"></td></tr>
                <tr><th><label for="bwqt_vendor_url"><?php esc_html_e('Vendor URL', 'bwqt'); ?></label></th><td><input id="bwqt_vendor_url" name="vendor_url" type="url" class="regular-text" value="<?php echo esc_attr($product['vendor_url']); ?>"></td></tr>
                <tr><th><label for="bwqt_base_unit_cost"><?php esc_html_e('Base Unit Cost', 'bwqt'); ?></label></th><td><input id="bwqt_base_unit_cost" name="base_unit_cost" type="number" step="0.01" min="0" value="<?php echo esc_attr($this->number_string($product['base_unit_cost'])); ?>"></td></tr>
                <tr><th><label for="bwqt_size_profile"><?php esc_html_e('Size Profile', 'bwqt'); ?></label></th><td><input id="bwqt_size_profile" name="size_profile" type="text" class="regular-text" value="<?php echo esc_attr($product['size_profile']); ?>" placeholder="XS-4XL"></td></tr>
                <tr><th><label for="bwqt_extended_size_surcharge"><?php esc_html_e('2XL+ Surcharge', 'bwqt'); ?></label></th><td><input id="bwqt_extended_size_surcharge" name="extended_size_surcharge" type="number" step="0.01" min="0" value="<?php echo esc_attr($this->number_string($product['extended_size_surcharge'])); ?>"></td></tr>
                <tr><th><label for="bwqt_color_options"><?php esc_html_e('Colors', 'bwqt'); ?></label></th><td><textarea id="bwqt_color_options" name="color_options" rows="6" class="large-text" placeholder="One color per line"><?php echo esc_textarea($product['color_options']); ?></textarea></td></tr>
                <tr><th><?php esc_html_e('Active', 'bwqt'); ?></th><td><label><input name="is_active" type="checkbox" value="1" <?php checked((int) $product['is_active'], 1); ?>> <?php esc_html_e('Available in quote builder', 'bwqt'); ?></label></td></tr>
            </tbody>
        </table>
        <?php
    }

    private function render_print_form_fields($print = [])
    {
        $print = wp_parse_args($print ?: [], [
            'method_name' => '',
            'placement_name' => '',
            'pricing_type' => 'Per Print',
            'unit_price' => 0,
            'requires_color_count' => 0,
            'is_active' => 1,
        ]);
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr><th><label for="bwqt_method_name"><?php esc_html_e('Method', 'bwqt'); ?></label></th><td><input id="bwqt_method_name" name="method_name" type="text" class="regular-text" value="<?php echo esc_attr($print['method_name']); ?>" required></td></tr>
                <tr><th><label for="bwqt_placement_name"><?php esc_html_e('Placement', 'bwqt'); ?></label></th><td><input id="bwqt_placement_name" name="placement_name" type="text" class="regular-text" value="<?php echo esc_attr($print['placement_name']); ?>" required></td></tr>
                <tr><th><label for="bwqt_pricing_type"><?php esc_html_e('Pricing Type', 'bwqt'); ?></label></th><td><input id="bwqt_pricing_type" name="pricing_type" type="text" class="regular-text" value="<?php echo esc_attr($print['pricing_type']); ?>"></td></tr>
                <tr><th><label for="bwqt_unit_price"><?php esc_html_e('Unit Price', 'bwqt'); ?></label></th><td><input id="bwqt_unit_price" name="unit_price" type="number" step="0.01" min="0" value="<?php echo esc_attr($this->number_string($print['unit_price'])); ?>"></td></tr>
                <tr><th><?php esc_html_e('Requires Color Count', 'bwqt'); ?></th><td><label><input name="requires_color_count" type="checkbox" value="1" <?php checked((int) $print['requires_color_count'], 1); ?>> <?php esc_html_e('Show a print color count field in quote entry', 'bwqt'); ?></label></td></tr>
                <tr><th><?php esc_html_e('Active', 'bwqt'); ?></th><td><label><input name="is_active" type="checkbox" value="1" <?php checked((int) $print['is_active'], 1); ?>> <?php esc_html_e('Available in quote builder', 'bwqt'); ?></label></td></tr>
            </tbody>
        </table>
        <?php
    }

    public function handle_product_save()
    {
        $this->assert_admin();
        check_admin_referer(self::ACTION_SAVE_PRODUCT, 'bwqt_product_nonce');

        global $wpdb;

        $id = absint($_POST['product_id'] ?? 0);
        $data = [
            'product_code' => sanitize_text_field(wp_unslash($_POST['product_code'] ?? '')),
            'product_name' => sanitize_text_field(wp_unslash($_POST['product_name'] ?? '')),
            'category_name' => sanitize_text_field(wp_unslash($_POST['category_name'] ?? '')),
            'vendor_name' => sanitize_text_field(wp_unslash($_POST['vendor_name'] ?? '')),
            'vendor_url' => esc_url_raw(wp_unslash($_POST['vendor_url'] ?? '')),
            'base_unit_cost' => $this->money_value($_POST['base_unit_cost'] ?? 0),
            'size_profile' => sanitize_text_field(wp_unslash($_POST['size_profile'] ?? '')),
            'extended_size_surcharge' => $this->money_value($_POST['extended_size_surcharge'] ?? 5),
            'color_options' => $this->sanitize_multiline_text($_POST['color_options'] ?? ''),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $wpdb->update($this->table('catalog_products'), $data, ['id' => $id]);
        } else {
            $wpdb->insert($this->table('catalog_products'), $data);
        }

        wp_safe_redirect(add_query_arg(['page' => 'bwqt-catalog', 'bwqt_notice' => 'catalog_saved'], admin_url('admin.php')));
        exit;
    }

    public function handle_product_delete()
    {
        $this->assert_admin();
        $id = absint($_GET['product_id'] ?? 0);
        check_admin_referer(self::ACTION_DELETE_PRODUCT . '_' . $id);

        global $wpdb;
        if ($id) {
            $wpdb->delete($this->table('catalog_products'), ['id' => $id]);
        }

        wp_safe_redirect(add_query_arg(['page' => 'bwqt-catalog', 'bwqt_notice' => 'catalog_deleted'], admin_url('admin.php')));
        exit;
    }

    public function handle_print_method_save()
    {
        $this->assert_admin();
        check_admin_referer(self::ACTION_SAVE_PRINT, 'bwqt_print_nonce');

        global $wpdb;

        $id = absint($_POST['print_method_id'] ?? 0);
        $data = [
            'method_name' => sanitize_text_field(wp_unslash($_POST['method_name'] ?? '')),
            'placement_name' => sanitize_text_field(wp_unslash($_POST['placement_name'] ?? '')),
            'pricing_type' => sanitize_text_field(wp_unslash($_POST['pricing_type'] ?? 'Per Print')),
            'unit_price' => $this->money_value($_POST['unit_price'] ?? 0),
            'requires_color_count' => !empty($_POST['requires_color_count']) ? 1 : 0,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $wpdb->update($this->table('print_methods'), $data, ['id' => $id]);
        } else {
            $wpdb->insert($this->table('print_methods'), $data);
        }

        wp_safe_redirect(add_query_arg(['page' => 'bwqt-catalog', 'bwqt_notice' => 'print_saved'], admin_url('admin.php')));
        exit;
    }

    public function handle_print_method_delete()
    {
        $this->assert_admin();
        $id = absint($_GET['print_method_id'] ?? 0);
        check_admin_referer(self::ACTION_DELETE_PRINT . '_' . $id);

        global $wpdb;
        if ($id) {
            $wpdb->delete($this->table('print_methods'), ['id' => $id]);
        }

        wp_safe_redirect(add_query_arg(['page' => 'bwqt-catalog', 'bwqt_notice' => 'print_deleted'], admin_url('admin.php')));
        exit;
    }

    public function handle_quote_save()
    {
        if (!$this->can_access_staff_tool()) {
            wp_die(esc_html__('You are not allowed to use the quote tool.', 'bwqt'), 403);
        }

        check_admin_referer(self::ACTION_SAVE_QUOTE, 'bwqt_quote_nonce');

        $post_id = $this->save_quote_from_request($_POST);
        if (is_wp_error($post_id)) {
            wp_die(esc_html($post_id->get_error_message()), 400);
        }

        $redirect = !empty($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $redirect = add_query_arg([
            'bwqt_notice' => 'quote_saved',
            'bwqt_saved' => $post_id,
        ], $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    public function render_shortcode($atts = [])
    {
        if (!$this->can_access_staff_tool()) {
            return '<div class="bwqt-locked">Your account does not have access to the quoting tool.</div>';
        }

        return $this->render_quote_app([
            'form_action' => admin_url('admin-post.php'),
            'csrf_html' => wp_nonce_field(self::ACTION_SAVE_QUOTE, 'bwqt_quote_nonce', true, false),
            'hidden_fields' => [
                ['name' => 'action', 'value' => self::ACTION_SAVE_QUOTE],
                ['name' => 'redirect_to', 'value' => $this->current_url()],
            ],
            'notice_html' => $this->get_frontend_notice_html(),
        ]);
    }

    public function render_ops_page($form_action, $csrf_html = '', $notice_html = '')
    {
        if (!$this->can_access_staff_tool(true)) {
            return '<div class="bwqt-locked">Your account does not have access to the quoting tool.</div>';
        }

        return $this->render_quote_app([
            'form_action' => $form_action,
            'csrf_html' => $csrf_html,
            'hidden_fields' => [],
            'notice_html' => $notice_html,
        ]);
    }

    public function save_quote_from_request($request)
    {
        $payload = $this->sanitize_quote_request($request);
        if (!$payload['client_name'] || !$payload['project_name'] || !$payload['lines']) {
            return new WP_Error('bwqt_invalid', __('Client name, project name, and at least one valid line are required.', 'bwqt'));
        }

        return $this->persist_quote($payload);
    }

    private function persist_quote($payload)
    {
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $payload['client_name'] . ' - ' . $payload['project_name'] . ' - ' . current_time('Y-m-d'),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        global $wpdb;

        $inserted = $wpdb->insert($this->table('quotes'), [
            'post_id' => $post_id,
            'client_name' => $payload['client_name'],
            'project_name' => $payload['project_name'],
            'contact_email' => $payload['contact_email'],
            'contact_phone' => $payload['contact_phone'],
            'need_by_date' => $payload['need_by_date'] ?: null,
            'quoted_by' => $payload['quoted_by'],
            'quote_status' => $payload['quote_status'],
            'project_notes' => $payload['project_notes'],
            'internal_notes' => $payload['internal_notes'],
            'subtotal' => $payload['totals']['subtotal'],
            'total' => $payload['totals']['grand_total'],
            'manager_margin_percent' => $payload['review']['manager_margin_percent'] ?? 0,
            'labor_fee' => $payload['review']['labor_fee'] ?? 0,
            'delivery_fee' => $payload['review']['delivery_fee'] ?? 0,
            'design_fee' => $payload['review']['design_fee'] ?? 0,
            'discount_amount' => $payload['review']['discount_amount'] ?? 0,
            'tax_rate' => $payload['review']['tax_rate'] ?? 0,
            'review_notes' => $payload['review']['review_notes'] ?? '',
            'reviewed_by' => $payload['review']['reviewed_by'] ?? '',
            'reviewed_at' => !empty($payload['review']['reviewed_at']) ? $payload['review']['reviewed_at'] : null,
        ]);
        if ($inserted === false) {
            wp_delete_post($post_id, true);
            return new WP_Error('bwqt_quote_insert_failed', $wpdb->last_error ?: __('Unable to save quote header.', 'bwqt'));
        }

        $quote_id = (int) $wpdb->insert_id;
        foreach ($payload['lines'] as $index => $line) {
            $inserted = $wpdb->insert($this->table('quote_lines'), [
                'quote_id' => $quote_id,
                'line_position' => $index + 1,
                'product_id' => $line['product_id'],
                'product_code' => $line['product_code'],
                'product_name' => $line['product_name'],
                'category_name' => $line['category_name'],
                'size_profile' => $line['size_profile'],
                'color_name' => $line['color_name'],
                'regular_qty' => $line['regular_qty'],
                'extended_qty' => $line['extended_qty'],
                'total_qty' => $line['total_qty'],
                'base_unit_cost' => $line['base_unit_cost'],
                'extended_size_surcharge' => $line['extended_size_surcharge'],
                'print_method_id' => $line['print_method_id'],
                'print_method_name' => $line['print_method_name'],
                'print_placement' => $line['print_placement'],
                'print_pricing_type' => $line['print_pricing_type'],
                'print_unit_price' => $line['print_unit_price'],
                'print_color_count' => $line['print_color_count'],
                'design_group' => $line['design_group'],
                'screen_locations_json' => !empty($line['screen_locations']) ? wp_json_encode($line['screen_locations']) : null,
                'material_total' => $line['material_total'],
                'print_total' => $line['print_total'],
                'line_total' => $line['line_total'],
                'line_notes' => $line['line_notes'],
            ]);
            if ($inserted === false) {
                $wpdb->delete($this->table('quote_lines'), ['quote_id' => $quote_id]);
                $wpdb->delete($this->table('quotes'), ['id' => $quote_id]);
                wp_delete_post($post_id, true);
                return new WP_Error('bwqt_quote_line_insert_failed', $wpdb->last_error ?: __('Unable to save quote lines.', 'bwqt'));
            }
        }

        update_post_meta($post_id, self::META_KEY, $payload);

        return $post_id;
    }

    private function render_quote_app($args)
    {
        $args = wp_parse_args($args, [
            'form_action' => '',
            'csrf_html' => '',
            'hidden_fields' => [],
            'notice_html' => '',
        ]);

        $products = array_values($this->get_catalog_products(false));
        $prints = array_values($this->get_print_methods(false));
        $default_line = $this->default_line();

        ob_start();
        $this->render_frontend_styles();
        echo $args['notice_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <form class="bwqt-app" method="post" action="<?php echo esc_url($args['form_action']); ?>">
            <?php echo $args['csrf_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php foreach ($args['hidden_fields'] as $field) : ?>
                <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($field['value']); ?>">
            <?php endforeach; ?>

            <section class="bwqt-shell">
                <div class="bwqt-header">
                    <div>
                        <p class="bwqt-kicker"><?php esc_html_e('Staff Quote Workspace', 'bwqt'); ?></p>
                        <h2><?php esc_html_e('Product Quote Entry', 'bwqt'); ?></h2>
                        <p><?php esc_html_e('Build a quote line by line from the protected catalog. Product cost, color choices, and print pricing come from the admin catalog and are not editable by staff here.', 'bwqt'); ?></p>
                    </div>
                    <div class="bwqt-sidecard">
                        <strong><?php esc_html_e('Review Flow', 'bwqt'); ?></strong>
                        <span><?php esc_html_e('Quotes save as Pending Review so the manager can verify pricing logic before they go out.', 'bwqt'); ?></span>
                    </div>
                </div>

                <div class="bwqt-grid bwqt-grid-top">
                    <label><span><?php esc_html_e('Client Name', 'bwqt'); ?></span><input type="text" name="client_name" required></label>
                    <label><span><?php esc_html_e('Project Name', 'bwqt'); ?></span><input type="text" name="project_name" required></label>
                    <label><span><?php esc_html_e('Client Email', 'bwqt'); ?></span><input type="email" name="contact_email"></label>
                    <label><span><?php esc_html_e('Client Phone', 'bwqt'); ?></span><input type="text" name="contact_phone"></label>
                    <label><span><?php esc_html_e('Need-by Date', 'bwqt'); ?></span><input type="date" name="need_by_date"></label>
                    <label><span><?php esc_html_e('Quoted By', 'bwqt'); ?></span><input type="text" name="quoted_by" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>"></label>
                </div>

                <div class="bwqt-grid bwqt-grid-notes">
                    <label class="bwqt-span-2"><span><?php esc_html_e('Project Notes', 'bwqt'); ?></span><textarea name="project_notes" rows="3"></textarea></label>
                    <label><span><?php esc_html_e('Internal Notes', 'bwqt'); ?></span><textarea name="internal_notes" rows="3"></textarea></label>
                </div>

                <div class="bwqt-grid bwqt-grid-options">
                    <label class="bwqt-checkbox">
                        <input type="checkbox" name="artwork_needed" value="1" data-bwqt-artwork-needed>
                        <span><?php esc_html_e('Artwork Needed', 'bwqt'); ?></span>
                    </label>
                    <label data-bwqt-artwork-hours-wrap hidden>
                        <span><?php esc_html_e('Artwork Hours', 'bwqt'); ?></span>
                        <input type="number" min="1" step="1" name="artwork_hours" value="1" data-bwqt-artwork-hours>
                    </label>
                    <label class="bwqt-checkbox">
                        <input type="checkbox" name="barebones_logo_discount" value="1" data-bwqt-logo-discount>
                        <span><?php esc_html_e('Barebones Logo Applied', 'bwqt'); ?></span>
                    </label>
                </div>

                <input type="hidden" name="quote_status" value="Pending Review">

                <section class="bwqt-lines">
                    <div class="bwqt-section-head">
                        <div>
                            <p class="bwqt-kicker"><?php esc_html_e('Line Builder', 'bwqt'); ?></p>
                            <h3><?php esc_html_e('Products, Sizes, Colors, and Print Methods', 'bwqt'); ?></h3>
                        </div>
                        <button type="button" class="bwqt-add-line"><?php esc_html_e('Add Line', 'bwqt'); ?></button>
                    </div>

                    <div class="bwqt-line-list" data-next-index="1">
                        <?php $this->render_line_template(0, $default_line); ?>
                    </div>
                </section>

                <div class="bwqt-summary">
                    <div class="bwqt-summary-card">
                        <div><span><?php esc_html_e('Subtotal', 'bwqt'); ?></span><strong data-bwqt-subtotal>$0.00</strong></div>
                        <div><span><?php esc_html_e('Artwork', 'bwqt'); ?></span><strong data-bwqt-artwork-fee>$0.00</strong></div>
                        <div><span><?php esc_html_e('Delivery', 'bwqt'); ?></span><strong data-bwqt-delivery-fee>$0.00</strong></div>
                        <div><span><?php esc_html_e('Barebones Logo Discount', 'bwqt'); ?></span><strong data-bwqt-logo-fee>-$0.00</strong></div>
                        <div class="bwqt-grand"><span><?php esc_html_e('Estimated Total', 'bwqt'); ?></span><strong data-bwqt-grand>$0.00</strong></div>
                        <p><?php esc_html_e('Entry estimate includes line pricing plus artwork, screen print delivery, and Barebones logo discount defaults. Managers can still adjust labor, margin, tax, and final review values.', 'bwqt'); ?></p>
                    </div>
                    <div class="bwqt-summary-card">
                        <button type="submit" class="bwqt-save"><?php esc_html_e('Save For Review', 'bwqt'); ?></button>
                        <p><?php esc_html_e('Saving stores the quote in the database and in WooCommerce admin under Project Quotes.', 'bwqt'); ?></p>
                    </div>
                </div>
            </section>
        </form>

        <template id="bwqt-line-template">
            <?php $this->render_line_template('__INDEX__', $default_line); ?>
        </template>
        <script>
            window.BWQT_CATALOG = <?php echo wp_json_encode([
                'products' => $products,
                'prints' => $prints,
            ]); ?>;
        </script>
        <?php
        $this->render_frontend_script();
        return ob_get_clean();
    }

    private function render_line_template($index, $line)
    {
        ?>
        <article class="bwqt-line" data-bwqt-line>
            <div class="bwqt-line-grid">
                <label class="bwqt-span-2">
                    <span><?php esc_html_e('Product', 'bwqt'); ?></span>
                    <select name="lines[<?php echo esc_attr((string) $index); ?>][product_id]" data-bwqt-product>
                        <option value=""><?php esc_html_e('Select a product', 'bwqt'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Color', 'bwqt'); ?></span>
                    <select name="lines[<?php echo esc_attr((string) $index); ?>][color_name]" data-bwqt-color>
                        <option value=""><?php esc_html_e('Select color', 'bwqt'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Sizes', 'bwqt'); ?></span>
                    <input type="text" name="lines[<?php echo esc_attr((string) $index); ?>][size_profile]" value="<?php echo esc_attr($line['size_profile']); ?>" data-bwqt-size-profile readonly>
                </label>
                <label>
                    <span><?php esc_html_e('Qty', 'bwqt'); ?></span>
                    <input type="number" min="0" step="1" name="lines[<?php echo esc_attr((string) $index); ?>][regular_qty]" value="<?php echo esc_attr((string) $line['regular_qty']); ?>" data-bwqt-regular>
                </label>
                <label>
                    <span><?php esc_html_e('2XL+ Qty', 'bwqt'); ?></span>
                    <input type="number" min="0" step="1" name="lines[<?php echo esc_attr((string) $index); ?>][extended_qty]" value="<?php echo esc_attr((string) $line['extended_qty']); ?>" data-bwqt-extended>
                </label>
                <label>
                    <span><?php esc_html_e('Base Cost', 'bwqt'); ?></span>
                    <input type="text" name="lines[<?php echo esc_attr((string) $index); ?>][base_unit_cost_display]" value="<?php echo esc_attr($this->format_money($line['base_unit_cost'])); ?>" data-bwqt-base readonly>
                </label>
                <label>
                    <span><?php esc_html_e('2XL+ Fee', 'bwqt'); ?></span>
                    <input type="text" name="lines[<?php echo esc_attr((string) $index); ?>][extended_size_surcharge_display]" value="<?php echo esc_attr($this->format_money($line['extended_size_surcharge'])); ?>" data-bwqt-surcharge readonly>
                </label>
                <label class="bwqt-span-2">
                    <span><?php esc_html_e('Print Method', 'bwqt'); ?></span>
                    <select name="lines[<?php echo esc_attr((string) $index); ?>][print_method_id]" data-bwqt-print>
                        <option value=""><?php esc_html_e('Select print method', 'bwqt'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Print Price', 'bwqt'); ?></span>
                    <input type="text" name="lines[<?php echo esc_attr((string) $index); ?>][print_unit_price_display]" value="<?php echo esc_attr($this->format_money($line['print_unit_price'])); ?>" data-bwqt-print-price readonly>
                </label>
                <label>
                    <span><?php esc_html_e('Design Group', 'bwqt'); ?></span>
                    <input type="text" name="lines[<?php echo esc_attr((string) $index); ?>][design_group]" value="<?php echo esc_attr($line['design_group'] ?? ''); ?>" data-bwqt-design-group placeholder="<?php esc_attr_e('Optional shared design key', 'bwqt'); ?>">
                </label>
                <label class="bwqt-line-total-wrap">
                    <span><?php esc_html_e('Line Total', 'bwqt'); ?></span>
                    <div class="bwqt-line-total">
                        <strong data-bwqt-line-total>$0.00</strong>
                    </div>
                </label>
                <button type="button" class="bwqt-remove-line"><?php esc_html_e('Remove', 'bwqt'); ?></button>
            </div>
            <div class="bwqt-screenprint" data-bwqt-screenprint hidden>
                <p class="bwqt-screenprint-title"><?php esc_html_e('Screen Print Locations', 'bwqt'); ?></p>
                <div class="bwqt-screenprint-grid">
                    <?php foreach ($this->screen_print_locations() as $location_key => $location_label) : ?>
                        <?php
                        $location_data = $line['screen_locations'][$location_key] ?? ['enabled' => false, 'colors' => 0];
                        ?>
                        <div class="bwqt-screenprint-location">
                            <label class="bwqt-screenprint-toggle">
                                <input
                                    type="checkbox"
                                    name="lines[<?php echo esc_attr((string) $index); ?>][screen_locations][<?php echo esc_attr($location_key); ?>][enabled]"
                                    value="1"
                                    <?php checked(!empty($location_data['enabled'])); ?>
                                    data-bwqt-location-toggle="<?php echo esc_attr($location_key); ?>"
                                >
                                <span><?php echo esc_html($location_label); ?></span>
                            </label>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                name="lines[<?php echo esc_attr((string) $index); ?>][screen_locations][<?php echo esc_attr($location_key); ?>][colors]"
                                value="<?php echo esc_attr(!empty($location_data['colors']) ? (string) $location_data['colors'] : ''); ?>"
                                placeholder="<?php esc_attr_e('Colors', 'bwqt'); ?>"
                                data-bwqt-location-colors="<?php echo esc_attr($location_key); ?>"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="bwqt-screenprint-help"><?php esc_html_e('Full Front cannot be combined with Left Chest, Right Chest, or Pocket on the same line. Matching Design Group values will share screen fees and under-24 ink fees across products.', 'bwqt'); ?></p>
            </div>
            <label>
                <span><?php esc_html_e('Line Notes', 'bwqt'); ?></span>
                <input type="text" name="lines[<?php echo esc_attr((string) $index); ?>][line_notes]" value="<?php echo esc_attr($line['line_notes']); ?>" placeholder="<?php esc_attr_e('Decoration notes, size mix, art notes, etc.', 'bwqt'); ?>">
            </label>
        </article>
        <?php
    }

    private function sanitize_quote_request($request)
    {
        $products = $this->get_catalog_products(false, true);
        $prints = $this->get_print_methods(false, true);

        $payload = [
            'client_name' => sanitize_text_field(wp_unslash($request['client_name'] ?? '')),
            'project_name' => sanitize_text_field(wp_unslash($request['project_name'] ?? '')),
            'contact_email' => sanitize_email(wp_unslash($request['contact_email'] ?? '')),
            'contact_phone' => sanitize_text_field(wp_unslash($request['contact_phone'] ?? '')),
            'need_by_date' => sanitize_text_field(wp_unslash($request['need_by_date'] ?? '')),
            'quoted_by' => sanitize_text_field(wp_unslash($request['quoted_by'] ?? '')),
            'quote_status' => sanitize_text_field(wp_unslash($request['quote_status'] ?? 'Pending Review')),
            'project_notes' => sanitize_textarea_field(wp_unslash($request['project_notes'] ?? '')),
            'internal_notes' => sanitize_textarea_field(wp_unslash($request['internal_notes'] ?? '')),
            'lines' => [],
            'totals' => [
                'subtotal' => 0,
                'grand_total' => 0,
            ],
            'quote_options' => [
                'artwork_needed' => !empty($request['artwork_needed']),
                'artwork_hours' => 0,
                'barebones_logo_discount' => !empty($request['barebones_logo_discount']),
            ],
        ];

        if (empty($request['lines']) || !is_array($request['lines'])) {
            return $payload;
        }

        $subtotal = 0;
        foreach ($request['lines'] as $line) {
            $product_id = absint($line['product_id'] ?? 0);
            if (!$product_id || empty($products[$product_id])) {
                continue;
            }

            $product = $products[$product_id];
            $print_method_id = absint($line['print_method_id'] ?? 0);
            $print = $print_method_id && !empty($prints[$print_method_id]) ? $prints[$print_method_id] : null;
            $regular_qty = max(0, absint($line['regular_qty'] ?? 0));
            $extended_qty = max(0, absint($line['extended_qty'] ?? 0));
            $total_qty = $regular_qty;

            if ($total_qty === 0) {
                continue;
            }

            if ($extended_qty > $total_qty) {
                $extended_qty = $total_qty;
            }

            $color_name = sanitize_text_field(wp_unslash($line['color_name'] ?? ''));
            if ($color_name && !in_array($color_name, $product['color_list'], true)) {
                $color_name = '';
            }

            $screen_locations = [];
            if ($print && $this->is_screen_print_method($print)) {
                $submitted_locations = $line['screen_locations'] ?? [];
                foreach ($this->screen_print_locations() as $location_key => $location_label) {
                    $location_row = $submitted_locations[$location_key] ?? [];
                    if (empty($location_row['enabled'])) {
                        continue;
                    }
                    $colors = max(1, absint($location_row['colors'] ?? 0));
                    $screen_locations[$location_key] = [
                        'key' => $location_key,
                        'label' => $location_label,
                        'colors' => $colors,
                    ];
                }
                $screen_locations = $this->normalize_screen_print_locations($screen_locations);
            }

            $payload['lines'][] = [
                'product_id' => $product_id,
                'product_code' => $product['product_code'],
                'product_name' => $product['product_name'],
                'category_name' => $product['category_name'],
                'size_profile' => $product['size_profile'],
                'color_name' => $color_name,
                'regular_qty' => max(0, $total_qty - $extended_qty),
                'extended_qty' => $extended_qty,
                'total_qty' => $total_qty,
                'base_unit_cost' => (float) $product['base_unit_cost'],
                'extended_size_surcharge' => (float) $product['extended_size_surcharge'],
                'print_method_id' => $print ? (int) $print['id'] : 0,
                'print_method_name' => $print['method_name'] ?? '',
                'print_placement' => $print['placement_name'] ?? '',
                'print_pricing_type' => $print['pricing_type'] ?? '',
                'print_unit_price' => (float) ($print['unit_price'] ?? 0),
                'print_color_count' => 0,
                'design_group' => sanitize_text_field(wp_unslash($line['design_group'] ?? '')),
                'screen_locations' => $screen_locations,
                'line_total' => 0,
                'line_notes' => sanitize_text_field(wp_unslash($line['line_notes'] ?? '')),
            ];
        }

        $payload['lines'] = $this->apply_quote_pricing($payload['lines']);
        $total_qty = 0;
        $has_screen_print = false;
        foreach ($payload['lines'] as $priced_line) {
            $subtotal += (float) $priced_line['line_total'];
            $total_qty += (int) $priced_line['total_qty'];
            if (($priced_line['print_method_name'] ?? '') === 'Screen Print') {
                $has_screen_print = true;
            }
        }

        $artwork_hours = 0;
        if ($payload['quote_options']['artwork_needed']) {
            $artwork_hours = max(1, absint($request['artwork_hours'] ?? 1));
        }
        $payload['quote_options']['artwork_hours'] = $artwork_hours;

        $suggested_margin_percent = $this->suggested_margin_percent($total_qty);
        $delivery_fee = $has_screen_print ? 45.00 : 0.00;
        $design_fee = $artwork_hours > 0 ? round($artwork_hours * 45, 2) : 0.00;
        $discount_amount = $payload['quote_options']['barebones_logo_discount'] ? round($total_qty * 2, 2) : 0.00;

        $payload['totals']['subtotal'] = round($subtotal, 2);
        $payload['totals']['grand_total'] = round(max(0, $subtotal + $delivery_fee + $design_fee - $discount_amount), 2);
        $payload['review'] = array_merge($this->default_review_fields(), [
            'manager_margin_percent' => $suggested_margin_percent,
            'labor_fee' => 0,
            'delivery_fee' => $delivery_fee,
            'design_fee' => $design_fee,
            'discount_amount' => $discount_amount,
        ]);

        return $payload;
    }

    private function get_catalog_products($include_inactive = false, $key_by_id = false)
    {
        global $wpdb;
        $where = $include_inactive ? '' : 'WHERE is_active = 1';
        $rows = $wpdb->get_results("SELECT * FROM {$this->table('catalog_products')} {$where} ORDER BY category_name ASC, product_code ASC", ARRAY_A);
        $products = [];

        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['base_unit_cost'] = (float) $row['base_unit_cost'];
            $row['extended_size_surcharge'] = (float) $row['extended_size_surcharge'];
            $row['is_active'] = (int) $row['is_active'];
            $row['color_list'] = $this->color_list_from_string($row['color_options']);
            if ($key_by_id) {
                $products[$row['id']] = $row;
            } else {
                $products[] = $row;
            }
        }

        return $products;
    }

    private function get_print_methods($include_inactive = false, $key_by_id = false)
    {
        global $wpdb;
        $where = $include_inactive ? '' : 'WHERE is_active = 1';
        $rows = $wpdb->get_results("SELECT * FROM {$this->table('print_methods')} {$where} ORDER BY method_name ASC, placement_name ASC", ARRAY_A);
        $methods = [];

        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['unit_price'] = (float) $row['unit_price'];
            $row['requires_color_count'] = (int) $row['requires_color_count'];
            $row['is_active'] = (int) $row['is_active'];
            if ($key_by_id) {
                $methods[$row['id']] = $row;
            } else {
                $methods[] = $row;
            }
        }

        return $methods;
    }

    private function get_quote_snapshot($post_id)
    {
        $stored = get_post_meta($post_id, self::META_KEY, true);
        if (is_array($stored)) {
            return $stored;
        }

        return [
            'client_name' => '',
            'project_name' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'need_by_date' => '',
            'quoted_by' => '',
            'quote_status' => '',
            'project_notes' => '',
            'internal_notes' => '',
            'lines' => [],
            'totals' => ['subtotal' => 0, 'grand_total' => 0],
            'quote_options' => [
                'artwork_needed' => false,
                'artwork_hours' => 0,
                'barebones_logo_discount' => false,
            ],
            'review' => $this->default_review_fields(),
        ];
    }

    private function get_editing_product()
    {
        global $wpdb;
        $id = absint($_GET['edit_product'] ?? 0);
        if (!$id) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table('catalog_products')} WHERE id = %d", $id), ARRAY_A);
    }

    private function get_editing_print_method()
    {
        global $wpdb;
        $id = absint($_GET['edit_print'] ?? 0);
        if (!$id) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table('print_methods')} WHERE id = %d", $id), ARRAY_A);
    }

    private function default_line()
    {
        return [
            'size_profile' => '',
            'regular_qty' => 0,
            'extended_qty' => 0,
            'base_unit_cost' => 0,
            'extended_size_surcharge' => 0,
            'print_unit_price' => 0,
            'print_color_count' => 0,
            'design_group' => '',
            'screen_locations' => [],
            'material_total' => 0,
            'print_total' => 0,
            'line_notes' => '',
        ];
    }

    private function default_review_fields()
    {
        return [
            'manager_margin_percent' => 0,
            'labor_fee' => 0,
            'delivery_fee' => 0,
            'design_fee' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'review_notes' => '',
            'reviewed_by' => '',
            'reviewed_at' => '',
        ];
    }

    private function suggested_margin_percent($qty)
    {
        $qty = max(0, (int) $qty);
        $matrix = [
            ['min' => 1, 'max' => 5, 'margin' => 200],
            ['min' => 6, 'max' => 11, 'margin' => 100],
            ['min' => 12, 'max' => 36, 'margin' => 75],
            ['min' => 37, 'max' => 49, 'margin' => 50],
            ['min' => 50, 'max' => 99, 'margin' => 40],
            ['min' => 100, 'max' => 249, 'margin' => 35],
            ['min' => 250, 'max' => 499, 'margin' => 30],
            ['min' => 500, 'max' => 799, 'margin' => 25],
            ['min' => 800, 'max' => 99999, 'margin' => 20],
        ];

        foreach ($matrix as $tier) {
            if ($qty >= $tier['min'] && $qty <= $tier['max']) {
                return (float) $tier['margin'];
            }
        }

        return 0.0;
    }

    private function get_frontend_notice_html()
    {
        $notice = sanitize_text_field(wp_unslash($_GET['bwqt_notice'] ?? ''));
        if (!$notice) {
            return '';
        }

        $map = [
            'quote_saved' => __('Quote saved for review.', 'bwqt'),
        ];

        if (empty($map[$notice])) {
            return '';
        }

        $html = '<div class="bwqt-notice">' . esc_html($map[$notice]);
        $saved_id = absint($_GET['bwqt_saved'] ?? 0);
        if ($saved_id) {
            $link = get_edit_post_link($saved_id, '');
            if ($link) {
                $html .= ' <a href="' . esc_url($link) . '">' . esc_html__('Open in admin', 'bwqt') . '</a>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    private function render_admin_notice()
    {
        $notice = sanitize_text_field(wp_unslash($_GET['bwqt_notice'] ?? ''));
        if (!$notice) {
            return;
        }

        $map = [
            'catalog_saved' => __('Product catalog updated.', 'bwqt'),
            'catalog_deleted' => __('Product removed from catalog.', 'bwqt'),
            'print_saved' => __('Print method updated.', 'bwqt'),
            'print_deleted' => __('Print method removed.', 'bwqt'),
        ];

        if (empty($map[$notice])) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($map[$notice]) . '</p></div>';
    }

    private function render_frontend_styles()
    {
        ?>
        <style>
            .bwqt-locked,.bwqt-notice{max-width:1160px;margin:16px auto;padding:14px 16px;border-radius:14px}
            .bwqt-locked{background:#f4f4f5;border:1px solid #d4d4d8}
            .bwqt-notice{background:#edf7ed;border:1px solid #9ed39e}
            .bwqt-notice a{font-weight:700}
            .bwqt-app{max-width:1160px;margin:0 auto 32px}
            .bwqt-shell{background:linear-gradient(180deg,#f7f3ea 0%,#fff 18%);border:1px solid #d8ccb6;border-radius:24px;padding:24px;box-shadow:0 20px 50px rgba(46,35,19,.08)}
            .bwqt-header,.bwqt-section-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}
            .bwqt-header h2,.bwqt-section-head h3{margin:.1em 0;font-size:clamp(1.6rem,2vw,2.2rem);color:#1f1710}
            .bwqt-header p,.bwqt-section-head p{color:#2f2418}
            .bwqt-kicker{margin:0 0 8px;text-transform:uppercase;letter-spacing:.14em;font-size:.76rem;color:#5c3810;font-weight:700}
            .bwqt-sidecard,.bwqt-summary-card{background:#201912;color:#fff;border-radius:18px;padding:16px;max-width:320px}
            .bwqt-app .bwqt-sidecard,.bwqt-app .bwqt-sidecard strong,.bwqt-app .bwqt-sidecard span,.bwqt-app .bwqt-summary-card,.bwqt-app .bwqt-summary-card strong,.bwqt-app .bwqt-summary-card span,.bwqt-app .bwqt-summary-card div span,.bwqt-app .bwqt-summary-card div strong{color:#fff}
            .bwqt-sidecard > span{display:block;margin-top:8px}
            .bwqt-summary-card p{display:block;margin-top:8px;color:rgba(255,255,255,.86)}
            .bwqt-grid{display:grid;gap:14px;margin-top:20px}
            .bwqt-grid-top{grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}
            .bwqt-grid-notes{grid-template-columns:2fr 1fr}
            .bwqt-grid-options{grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}
            .bwqt-span-2{grid-column:span 2}
            .bwqt-app label{display:flex;flex-direction:column;gap:6px}
            .bwqt-app span{font-size:.9rem;font-weight:700;color:#1f1710}
            .bwqt-app input,.bwqt-app textarea,.bwqt-app select{width:100%;border:1px solid #bca98b;border-radius:12px;padding:11px 12px;background:#fffdf9;color:#1a1a1a;box-sizing:border-box}
            .bwqt-app input[readonly]{background:#f6f0e6}
            .bwqt-app input::placeholder,.bwqt-app textarea::placeholder{color:#6c6258;opacity:1}
            .bwqt-app .bwqt-checkbox{display:flex;flex-direction:row;align-items:center;gap:10px;padding-top:8px}
            .bwqt-app .bwqt-checkbox input{width:auto}
            .bwqt-lines{margin-top:28px}
            .bwqt-add-line,.bwqt-save,.bwqt-remove-line{border:0;border-radius:999px;padding:11px 16px;font-weight:700;cursor:pointer}
            .bwqt-add-line{background:#efe3cb;color:#5d3f15}
            .bwqt-save{background:#1e4d3b;color:#fff;width:100%}
            .bwqt-remove-line{background:#f9d9d4;color:#8a2d20}
            .bwqt-line-list{display:flex;flex-direction:column;gap:14px;margin-top:16px}
            .bwqt-line{border:1px solid #e7dcc7;border-radius:18px;padding:16px;background:#fff}
            .bwqt-line-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;align-items:end}
            .bwqt-line-total-wrap{display:flex;flex-direction:column;gap:6px;align-self:start}
            .bwqt-line-total{padding:11px 12px;border-radius:12px;background:#f0e6d8;border:1px solid #bca98b;color:#201912;min-height:54px;height:54px;display:flex;align-items:center;box-sizing:border-box}
            .bwqt-line-total strong{font-size:1rem;color:#201912}
            .bwqt-screenprint{margin-top:14px;padding:14px;border:1px dashed #cdbfa7;border-radius:14px;background:#faf6ef}
            .bwqt-screenprint-title{margin:0 0 10px;font-size:.95rem;font-weight:700;color:#1f1710}
            .bwqt-screenprint-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
            .bwqt-screenprint-location{display:flex;flex-direction:column;gap:6px}
            .bwqt-screenprint-toggle{display:flex;flex-direction:row;align-items:center;gap:8px}
            .bwqt-screenprint-toggle input{width:auto}
            .bwqt-screenprint-help{margin:10px 0 0;color:#55483b;font-size:.86rem}
            .bwqt-summary{display:grid;grid-template-columns:1fr 320px;gap:20px;margin-top:28px}
            .bwqt-summary-card div{display:flex;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.12)}
            .bwqt-summary-card div:last-of-type{border-bottom:0}
            .bwqt-grand strong{font-size:1.3rem}
            @media (max-width: 980px){
                .bwqt-grid-notes,.bwqt-summary{grid-template-columns:1fr}
                .bwqt-line-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
                .bwqt-span-2{grid-column:span 2}
                .bwqt-screenprint-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            }
            @media (max-width: 640px){
                .bwqt-shell{padding:18px}
                .bwqt-header,.bwqt-section-head{flex-direction:column}
                .bwqt-line-grid{grid-template-columns:1fr}
                .bwqt-span-2{grid-column:auto}
                .bwqt-screenprint-grid{grid-template-columns:1fr}
            }
        </style>
        <?php
    }

    private function render_frontend_script()
    {
        ?>
        <script>
            (function(){
                var root = document.querySelector('.bwqt-app');
                if (!root || !window.BWQT_CATALOG) return;

                var catalog = window.BWQT_CATALOG;
                var template = document.getElementById('bwqt-line-template');
                var list = root.querySelector('.bwqt-line-list');

                function money(value){
                    return new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(value || 0);
                }

                function number(value){
                    var parsed = parseFloat(value || '0');
                    return Number.isFinite(parsed) ? parsed : 0;
                }

                function integer(value){
                    var parsed = parseInt(value || '0', 10);
                    return Number.isFinite(parsed) ? parsed : 0;
                }

                function populateProducts(line){
                    var select = line.querySelector('[data-bwqt-product]');
                    if (!select || select.options.length > 1) return;
                    catalog.products.forEach(function(product){
                        var option = document.createElement('option');
                        option.value = product.id;
                        option.textContent = product.product_code + ' | ' + product.product_name;
                        select.appendChild(option);
                    });
                }

                function populatePrintMethods(line){
                    var select = line.querySelector('[data-bwqt-print]');
                    if (!select || select.options.length > 1) return;
                    var seenScreenPrint = false;
                    catalog.prints.forEach(function(print){
                        if (print.method_name === 'Screen Print') {
                            if (seenScreenPrint) return;
                            seenScreenPrint = true;
                        }
                        var option = document.createElement('option');
                        option.value = print.id;
                        option.textContent = print.method_name === 'Screen Print'
                            ? 'Screen Print'
                            : (print.method_name + ' / ' + print.placement_name);
                        select.appendChild(option);
                    });
                }

                function productById(id){
                    return catalog.products.find(function(product){ return String(product.id) === String(id); }) || null;
                }

                function printById(id){
                    return catalog.prints.find(function(print){ return String(print.id) === String(id); }) || null;
                }

                function fillColors(line, product){
                    var colorSelect = line.querySelector('[data-bwqt-color]');
                    var selected = colorSelect.value;
                    colorSelect.innerHTML = '<option value="">' + 'Select color' + '</option>';
                    if (!product || !product.color_list) return;
                    product.color_list.forEach(function(color){
                        var option = document.createElement('option');
                        option.value = color;
                        option.textContent = color;
                        if (selected === color) {
                            option.selected = true;
                        }
                        colorSelect.appendChild(option);
                    });
                }

                function screenPrintMatrixPrice(quantity, colors){
                    var tiers = [
                        {min: 12, max: 35, rates: {1: 2.30, 2: 3.30, 3: 4.90, 4: 7.15}},
                        {min: 36, max: 71, rates: {1: 2.20, 2: 2.70, 3: 3.30, 4: 3.70, 5: 4.75}},
                        {min: 72, max: 143, rates: {1: 1.95, 2: 2.30, 3: 2.75, 4: 3.45, 5: 4.00, 6: 4.50}},
                        {min: 144, max: 287, rates: {1: 1.85, 2: 2.20, 3: 2.35, 4: 3.20, 5: 3.30, 6: 3.80}},
                        {min: 288, max: 539, rates: {1: 1.55, 2: 1.80, 3: 2.15, 4: 2.60, 5: 2.70, 6: 3.35}},
                        {min: 540, max: 99999, rates: {1: 1.50, 2: 1.75, 3: 2.10, 4: 2.50, 5: 2.60, 6: 3.25}}
                    ];
                    var qty = Math.max(12, quantity || 0);
                    for (var i = 0; i < tiers.length; i++) {
                        if (qty >= tiers[i].min && qty <= tiers[i].max) {
                            return Number(tiers[i].rates[colors] || 0);
                        }
                    }
                    return 0;
                }

                function isScreenPrint(print){
                    return print && print.method_name === 'Screen Print';
                }

                function locationConflictMap(){
                    return {
                        full_front: ['left_chest', 'right_chest', 'pocket'],
                        left_chest: ['full_front'],
                        right_chest: ['full_front'],
                        pocket: ['full_front']
                    };
                }

                function syncScreenPrintVisibility(line, print){
                    var block = line.querySelector('[data-bwqt-screenprint]');
                    if (!block) return;
                    block.hidden = !isScreenPrint(print);
                }

                function selectedScreenLocations(line){
                    var locations = [];
                    line.querySelectorAll('[data-bwqt-location-toggle]').forEach(function(toggle){
                        if (!toggle.checked) return;
                        var key = toggle.getAttribute('data-bwqt-location-toggle');
                        var colorInput = line.querySelector('[data-bwqt-location-colors="' + key + '"]');
                        var colors = Math.max(1, integer(colorInput && colorInput.value));
                        locations.push({
                            key: key,
                            label: toggle.parentElement.querySelector('span').textContent,
                            colors: colors
                        });
                    });
                    return locations;
                }

                function locationGroupKey(lineIndex, location, designGroup){
                    return (designGroup ? designGroup.toLowerCase().trim() : ('line-' + lineIndex)) + '|' + location.key + '|' + location.colors;
                }

                function collectLineData(){
                    var lineData = [];
                    root.querySelectorAll('[data-bwqt-line]').forEach(function(line, lineIndex){
                        populateProducts(line);
                        populatePrintMethods(line);

                        var product = productById(line.querySelector('[data-bwqt-product]').value);
                        var print = printById(line.querySelector('[data-bwqt-print]').value);
                        var totalQty = integer(line.querySelector('[data-bwqt-regular]').value);
                        var extendedQty = integer(line.querySelector('[data-bwqt-extended]').value);
                        if (extendedQty > totalQty) {
                            extendedQty = totalQty;
                            line.querySelector('[data-bwqt-extended]').value = String(extendedQty);
                        }

                        fillColors(line, product);
                        syncScreenPrintVisibility(line, print);

                        var base = product ? number(product.base_unit_cost) : 0;
                        var surcharge = product ? number(product.extended_size_surcharge) : 0;
                        var printPrice = print && !isScreenPrint(print) ? number(print.unit_price) : 0;
                        var designGroup = (line.querySelector('[data-bwqt-design-group]') || {}).value || '';

                        line.querySelector('[data-bwqt-size-profile]').value = product ? product.size_profile : '';
                        line.querySelector('[data-bwqt-base]').value = money(base);
                        line.querySelector('[data-bwqt-surcharge]').value = money(surcharge);
                        line.querySelector('[data-bwqt-print-price]').value = isScreenPrint(print) ? 'Matrix + fees' : money(printPrice);

                        lineData.push({
                            line: line,
                            lineIndex: lineIndex,
                            product: product,
                            print: print,
                            totalQty: totalQty,
                            extendedQty: extendedQty,
                            base: base,
                            surcharge: surcharge,
                            printPrice: printPrice,
                            designGroup: designGroup,
                            screenLocations: isScreenPrint(print) ? selectedScreenLocations(line) : []
                        });
                    });
                    return lineData;
                }

                function artworkFee(){
                    var checkbox = root.querySelector('[data-bwqt-artwork-needed]');
                    if (!checkbox || !checkbox.checked) return 0;
                    var hours = integer((root.querySelector('[data-bwqt-artwork-hours]') || {}).value);
                    return Math.max(1, hours || 1) * 45;
                }

                function logoDiscount(lineData){
                    var checkbox = root.querySelector('[data-bwqt-logo-discount]');
                    if (!checkbox || !checkbox.checked) return 0;
                    return lineData.reduce(function(total, item){
                        return total + item.totalQty;
                    }, 0) * 2;
                }

                function deliveryFee(lineData){
                    var hasScreenPrint = lineData.some(function(item){
                        return item.print && isScreenPrint(item.print) && item.totalQty > 0;
                    });
                    return hasScreenPrint ? 45 : 0;
                }

                function syncQuoteOptionVisibility(){
                    var artworkWrap = root.querySelector('[data-bwqt-artwork-hours-wrap]');
                    var artworkNeeded = root.querySelector('[data-bwqt-artwork-needed]');
                    if (artworkWrap && artworkNeeded) {
                        artworkWrap.hidden = !artworkNeeded.checked;
                    }
                }

                function refreshLineTotalsFromData(lineData){
                    var groups = {};
                    lineData.forEach(function(item){
                        if (!item.print || !isScreenPrint(item.print) || item.totalQty <= 0) return;
                        item.screenLocations.forEach(function(location){
                            var key = locationGroupKey(item.lineIndex, location, item.designGroup);
                            if (!groups[key]) {
                                groups[key] = {qty: 0, colors: location.colors, members: []};
                            }
                            groups[key].qty += item.totalQty;
                            groups[key].members.push(item);
                        });
                    });

                    var subtotal = 0;
                    lineData.forEach(function(item){
                        var lineTotal = (item.totalQty * item.base) + (item.extendedQty * item.surcharge);
                        var printTotal = 0;

                        if (item.print && isScreenPrint(item.print)) {
                            item.screenLocations.forEach(function(location){
                                var key = locationGroupKey(item.lineIndex, location, item.designGroup);
                                var group = groups[key];
                                if (!group) return;
                                var pieceRate = screenPrintMatrixPrice(group.qty, location.colors);
                                var feeShare = item.totalQty / group.qty;
                                var screenFee = location.colors * 20;
                                var inkFee = group.qty < 24 ? location.colors * 15 : 0;
                                var locationTotal = (item.totalQty * pieceRate) + ((screenFee + inkFee) * feeShare);
                                printTotal += locationTotal;
                                lineTotal += locationTotal;
                            });
                        } else if (item.print) {
                            printTotal = item.totalQty * item.printPrice;
                            lineTotal += printTotal;
                        }

                        item.line.querySelector('[data-bwqt-print-price]').value = money(printTotal);
                        item.line.querySelector('[data-bwqt-line-total]').textContent = money(lineTotal);
                        subtotal += lineTotal;
                    });

                    var artwork = artworkFee();
                    var delivery = deliveryFee(lineData);
                    var discount = logoDiscount(lineData);
                    var grandTotal = Math.max(0, subtotal + artwork + delivery - discount);

                    root.querySelector('[data-bwqt-subtotal]').textContent = money(subtotal);
                    root.querySelector('[data-bwqt-artwork-fee]').textContent = money(artwork);
                    root.querySelector('[data-bwqt-delivery-fee]').textContent = money(delivery);
                    root.querySelector('[data-bwqt-logo-fee]').textContent = '-' + money(discount);
                    root.querySelector('[data-bwqt-grand]').textContent = money(grandTotal);
                }

                function enforceLocationConflicts(line, changedKey){
                    var map = locationConflictMap();
                    if (!map[changedKey]) return;
                    var source = line.querySelector('[data-bwqt-location-toggle="' + changedKey + '"]');
                    if (!source || !source.checked) return;
                    map[changedKey].forEach(function(targetKey){
                        var target = line.querySelector('[data-bwqt-location-toggle="' + targetKey + '"]');
                        var colors = line.querySelector('[data-bwqt-location-colors="' + targetKey + '"]');
                        if (target) target.checked = false;
                        if (colors) colors.value = '';
                    });
                }

                function refreshLine(line){
                    populateProducts(line);
                    populatePrintMethods(line);
                    refreshLineTotalsFromData(collectLineData());
                }

                function refreshTotals(){
                    refreshLineTotalsFromData(collectLineData());
                }

                function addLine(){
                    var nextIndex = parseInt(list.getAttribute('data-next-index') || '1', 10);
                    list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(nextIndex)));
                    list.setAttribute('data-next-index', String(nextIndex + 1));
                    refreshTotals();
                }

                root.addEventListener('input', function(event){
                    if (event.target.closest('[data-bwqt-line]')) {
                        refreshTotals();
                    }
                    if (event.target.hasAttribute('data-bwqt-artwork-hours')) {
                        refreshTotals();
                    }
                });

                root.addEventListener('change', function(event){
                    if (event.target.hasAttribute('data-bwqt-location-toggle')) {
                        enforceLocationConflicts(event.target.closest('[data-bwqt-line]'), event.target.getAttribute('data-bwqt-location-toggle'));
                    }
                    if (event.target.hasAttribute('data-bwqt-artwork-needed') || event.target.hasAttribute('data-bwqt-logo-discount')) {
                        syncQuoteOptionVisibility();
                    }
                    if (event.target.closest('[data-bwqt-line]')) {
                        refreshTotals();
                    } else if (
                        event.target.hasAttribute('data-bwqt-artwork-needed')
                        || event.target.hasAttribute('data-bwqt-logo-discount')
                    ) {
                        refreshTotals();
                    }
                });

                root.addEventListener('click', function(event){
                    if (event.target.classList.contains('bwqt-add-line')) {
                        addLine();
                    }
                    if (event.target.classList.contains('bwqt-remove-line')) {
                        var line = event.target.closest('[data-bwqt-line]');
                        if (line && root.querySelectorAll('[data-bwqt-line]').length > 1) {
                            line.remove();
                            refreshTotals();
                        }
                    }
                });

                refreshTotals();
                syncQuoteOptionVisibility();
            })();
        </script>
        <?php
    }

    private function color_list_from_string($value)
    {
        $parts = preg_split('/\r\n|\r|\n|,/', (string) $value);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, static function ($item) {
            return $item !== '';
        });

        return array_values(array_unique($parts));
    }

    private function sanitize_multiline_text($value)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) wp_unslash($value));
        $lines = array_map('sanitize_text_field', $lines);
        $lines = array_filter($lines, static function ($line) {
            return $line !== '';
        });

        return implode("\n", array_values(array_unique($lines)));
    }

    private function assert_admin()
    {
        if (!current_user_can(self::ADMIN_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to manage the quote catalog.', 'bwqt'), 403);
        }
    }

    private function current_url()
    {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        return $scheme . $host . $request_uri;
    }

    private function can_access_staff_tool($allow_ops_context = false)
    {
        if (is_user_logged_in() && current_user_can(self::STAFF_CAPABILITY)) {
            return true;
        }

        if ($allow_ops_context && $this->is_ops_context()) {
            return true;
        }

        return false;
    }

    private function is_ops_context()
    {
        return defined('BWQT_OPS_CONTEXT')
            && BWQT_OPS_CONTEXT
            && function_exists('is_logged_in')
            && is_logged_in();
    }

    private function money_value($value)
    {
        return round(max(0, $this->float_value($value)), 2);
    }

    private function float_value($value)
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) wp_unslash($value));
    }

    private function format_money($amount)
    {
        return '$' . number_format((float) $amount, 2);
    }

    private function number_string($value)
    {
        $number = (float) $value;
        return floor($number) == $number ? (string) (int) $number : number_format($number, 2, '.', '');
    }

    private function screen_print_locations()
    {
        return [
            'full_front' => 'Full Front',
            'left_chest' => 'Left Chest',
            'right_chest' => 'Right Chest',
            'full_back' => 'Full Back',
            'left_sleeve' => 'Left Sleeve',
            'right_sleeve' => 'Right Sleeve',
            'pocket' => 'Pocket',
        ];
    }

    private function is_screen_print_method($print)
    {
        return !empty($print['method_name']) && $print['method_name'] === 'Screen Print';
    }

    private function normalize_screen_print_locations($locations)
    {
        $has_full_front = !empty($locations['full_front']);
        if ($has_full_front) {
            unset($locations['left_chest'], $locations['right_chest'], $locations['pocket']);
        }

        return $locations;
    }

    private function apply_quote_pricing($lines)
    {
        $groups = [];
        foreach ($lines as $index => $line) {
            if ($line['print_method_name'] !== 'Screen Print' || empty($line['screen_locations']) || $line['total_qty'] <= 0) {
                continue;
            }

            $design_group = $this->normalized_design_group($line['design_group']);
            foreach ($line['screen_locations'] as $location) {
                $group_key = ($design_group ?: 'line-' . $index) . '|' . $location['key'] . '|' . $location['colors'];
                if (!isset($groups[$group_key])) {
                    $groups[$group_key] = [
                        'qty' => 0,
                        'colors' => (int) $location['colors'],
                    ];
                }
                $groups[$group_key]['qty'] += (int) $line['total_qty'];
            }
        }

        foreach ($lines as $index => $line) {
            $base_total = ($line['total_qty'] * $line['base_unit_cost']) + ($line['extended_qty'] * $line['extended_size_surcharge']);
            $print_total = 0;
            $line_total = $base_total;

            if ($line['print_method_name'] === 'Screen Print' && !empty($line['screen_locations'])) {
                $design_group = $this->normalized_design_group($line['design_group']);
                foreach ($line['screen_locations'] as $location) {
                    $group_key = ($design_group ?: 'line-' . $index) . '|' . $location['key'] . '|' . $location['colors'];
                    $group = $groups[$group_key] ?? ['qty' => $line['total_qty'], 'colors' => $location['colors']];
                    $piece_rate = $this->screen_print_matrix_price((int) $group['qty'], (int) $location['colors']);
                    $screen_fee = 20 * (int) $location['colors'];
                    $ink_fee = ((int) $group['qty'] < 24) ? 15 * (int) $location['colors'] : 0;
                    $share = $group['qty'] > 0 ? ($line['total_qty'] / $group['qty']) : 1;
                    $location_total = ($line['total_qty'] * $piece_rate) + (($screen_fee + $ink_fee) * $share);
                    $print_total += $location_total;
                    $line_total += $location_total;
                }
            } elseif (!empty($line['print_method_name'])) {
                $print_total = $line['total_qty'] * $line['print_unit_price'];
                $line_total += $print_total;
            }

            $lines[$index]['material_total'] = round($base_total, 2);
            $lines[$index]['print_total'] = round($print_total, 2);
            $lines[$index]['line_total'] = round($line_total, 2);
        }

        return $lines;
    }

    private function normalized_design_group($value)
    {
        $value = sanitize_title((string) $value);
        return $value ?: '';
    }

    private function screen_print_matrix_price($qty, $colors)
    {
        $qty = max(12, (int) $qty);
        $matrix = [
            ['min' => 12, 'max' => 35, 'rates' => [1 => 2.30, 2 => 3.30, 3 => 4.90, 4 => 7.15]],
            ['min' => 36, 'max' => 71, 'rates' => [1 => 2.20, 2 => 2.70, 3 => 3.30, 4 => 3.70, 5 => 4.75]],
            ['min' => 72, 'max' => 143, 'rates' => [1 => 1.95, 2 => 2.30, 3 => 2.75, 4 => 3.45, 5 => 4.00, 6 => 4.50]],
            ['min' => 144, 'max' => 287, 'rates' => [1 => 1.85, 2 => 2.20, 3 => 2.35, 4 => 3.20, 5 => 3.30, 6 => 3.80]],
            ['min' => 288, 'max' => 539, 'rates' => [1 => 1.55, 2 => 1.80, 3 => 2.15, 4 => 2.60, 5 => 2.70, 6 => 3.35]],
            ['min' => 540, 'max' => 99999, 'rates' => [1 => 1.50, 2 => 1.75, 3 => 2.10, 4 => 2.50, 5 => 2.60, 6 => 3.25]],
        ];

        foreach ($matrix as $tier) {
            if ($qty >= $tier['min'] && $qty <= $tier['max']) {
                return (float) ($tier['rates'][$colors] ?? 0);
            }
        }

        return 0.0;
    }

    public function get_review_queue($args = [])
    {
        global $wpdb;

        $limit = max(1, min(200, (int) ($args['limit'] ?? 100)));
        $status = sanitize_text_field((string) ($args['status'] ?? ''));
        $where = '';
        $params = [];

        if ($status !== '') {
            $where = 'WHERE quote_status = %s';
            $params[] = $status;
        }

        $sql = "SELECT * FROM {$this->table('quotes')} {$where} ORDER BY updated_at DESC LIMIT {$limit}";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        foreach ($rows as &$row) {
            $row = $this->normalize_quote_row($row);
        }

        return $rows;
    }

    public function get_review_quote($post_id)
    {
        global $wpdb;

        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return null;
        }

        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table('quotes')} WHERE post_id = %d", $post_id), ARRAY_A);
        if (!$quote) {
            return null;
        }

        $quote = $this->normalize_quote_row($quote);
        $snapshot = $this->get_quote_snapshot($post_id);
        $lines = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table('quote_lines')} WHERE quote_id = %d ORDER BY line_position ASC", $quote['id']), ARRAY_A);
        $quote['lines'] = $this->build_review_lines($lines, $snapshot['lines'] ?? []);
        if (!$quote['client_name'] && !empty($snapshot['client_name'])) {
            $quote['client_name'] = $snapshot['client_name'];
        }
        if (!$quote['project_name'] && !empty($snapshot['project_name'])) {
            $quote['project_name'] = $snapshot['project_name'];
        }
        if (!$quote['quote_status'] && !empty($snapshot['quote_status'])) {
            $quote['quote_status'] = $snapshot['quote_status'];
        }
        $quote['quote_options'] = $snapshot['quote_options'] ?? [
            'artwork_needed' => false,
            'artwork_hours' => 0,
            'barebones_logo_discount' => false,
        ];
        if (!empty($snapshot['review']) && is_array($snapshot['review'])) {
            foreach ($this->default_review_fields() as $field => $default) {
                if (
                    array_key_exists($field, $snapshot['review'])
                    && ($quote[$field] === '' || (float) $quote[$field] === (float) $default)
                ) {
                    $quote[$field] = $snapshot['review'][$field];
                }
            }
        }
        $quote['review'] = $this->extract_review_fields($quote);
        $quote['suggested_margin_percent'] = $this->suggested_margin_percent($this->quote_total_qty($quote['lines']));
        $quote['review_totals'] = $this->calculate_review_totals($quote);
        $quote['lines'] = $this->allocate_review_pricing_to_lines($quote['lines'], $quote['review'], $quote['review_totals']);

        return $quote;
    }

    public function save_review_adjustments($post_id, $data, $reviewed_by = '')
    {
        global $wpdb;

        $quote = $this->get_review_quote($post_id);
        if (!$quote) {
            return new WP_Error('bwqt_missing_quote', __('Quote not found.', 'bwqt'));
        }

        $review = [
            'manager_margin_percent' => $this->float_value($data['manager_margin_percent'] ?? 0),
            'labor_fee' => $this->money_value($data['labor_fee'] ?? 0),
            'delivery_fee' => $this->money_value($data['delivery_fee'] ?? 0),
            'design_fee' => $this->money_value($data['design_fee'] ?? 0),
            'discount_amount' => $this->money_value($data['discount_amount'] ?? 0),
            'tax_rate' => $this->float_value($data['tax_rate'] ?? 0),
            'review_notes' => sanitize_textarea_field(wp_unslash($data['review_notes'] ?? '')),
            'quote_status' => sanitize_text_field(wp_unslash($data['quote_status'] ?? $quote['quote_status'])),
            'reviewed_by' => sanitize_text_field((string) $reviewed_by),
            'reviewed_at' => current_time('mysql'),
        ];

        $wpdb->update($this->table('quotes'), $review, ['post_id' => (int) $post_id]);

        $snapshot = $this->get_quote_snapshot($post_id);
        $snapshot['quote_status'] = $review['quote_status'];
        $snapshot['review'] = array_merge($this->default_review_fields(), $review);
        update_post_meta($post_id, self::META_KEY, $snapshot);

        return true;
    }

    private function normalize_quote_row($row)
    {
        $row['id'] = (int) $row['id'];
        $row['post_id'] = (int) $row['post_id'];
        $row['subtotal'] = (float) $row['subtotal'];
        $row['total'] = (float) $row['total'];
        $row['manager_margin_percent'] = (float) ($row['manager_margin_percent'] ?? 0);
        $row['labor_fee'] = (float) ($row['labor_fee'] ?? 0);
        $row['delivery_fee'] = (float) ($row['delivery_fee'] ?? 0);
        $row['design_fee'] = (float) ($row['design_fee'] ?? 0);
        $row['discount_amount'] = (float) ($row['discount_amount'] ?? 0);
        $row['tax_rate'] = (float) ($row['tax_rate'] ?? 0);

        return $row;
    }

    private function normalize_quote_line_row($row)
    {
        $row['id'] = (int) $row['id'];
        $row['quote_id'] = (int) $row['quote_id'];
        $row['line_position'] = (int) $row['line_position'];
        $row['regular_qty'] = (int) $row['regular_qty'];
        $row['extended_qty'] = (int) $row['extended_qty'];
        $row['total_qty'] = (int) $row['total_qty'];
        $row['base_unit_cost'] = (float) $row['base_unit_cost'];
        $row['extended_size_surcharge'] = (float) $row['extended_size_surcharge'];
        $row['print_unit_price'] = (float) $row['print_unit_price'];
        $row['material_total'] = (float) ($row['material_total'] ?? 0);
        $row['print_total'] = (float) ($row['print_total'] ?? 0);
        $row['line_total'] = (float) $row['line_total'];
        $row['screen_locations'] = [];

        if (!empty($row['screen_locations']) && is_array($row['screen_locations'])) {
            $row['screen_locations'] = array_values($row['screen_locations']);
        } elseif (!empty($row['screen_locations_json'])) {
            $decoded = json_decode($row['screen_locations_json'], true);
            if (is_array($decoded)) {
                $row['screen_locations'] = array_values($decoded);
            }
        }

        $row['unit_price'] = $row['total_qty'] > 0 ? round($row['line_total'] / $row['total_qty'], 2) : 0;

        return $row;
    }

    private function build_review_lines($db_lines, $snapshot_lines)
    {
        $prepared = [];
        $db_lines = array_values(is_array($db_lines) ? $db_lines : []);
        $snapshot_lines = array_values(is_array($snapshot_lines) ? $snapshot_lines : []);
        $line_count = max(count($db_lines), count($snapshot_lines));

        for ($index = 0; $index < $line_count; $index++) {
            $db_line = $db_lines[$index] ?? [];
            $snapshot_line = $snapshot_lines[$index] ?? [];
            if (!$db_line && !$snapshot_line) {
                continue;
            }

            $prepared[] = $this->merge_review_line_sources($db_line, $snapshot_line, $index);
        }

        if (!$prepared) {
            return [];
        }

        $prepared = $this->apply_quote_pricing($prepared);
        return array_map([$this, 'normalize_quote_line_row'], $prepared);
    }

    private function merge_review_line_sources($db_line, $snapshot_line, $index)
    {
        $screen_locations = [];
        if (!empty($db_line['screen_locations']) && is_array($db_line['screen_locations'])) {
            $screen_locations = $db_line['screen_locations'];
        } elseif (!empty($db_line['screen_locations_json'])) {
            $decoded = json_decode($db_line['screen_locations_json'], true);
            if (is_array($decoded)) {
                $screen_locations = $decoded;
            }
        }
        if (!$screen_locations && !empty($snapshot_line['screen_locations']) && is_array($snapshot_line['screen_locations'])) {
            $screen_locations = $snapshot_line['screen_locations'];
        }

        if ($screen_locations) {
            $screen_locations = $this->normalize_screen_print_locations($screen_locations);
        }

        return [
            'id' => (int) ($db_line['id'] ?? 0),
            'quote_id' => (int) ($db_line['quote_id'] ?? 0),
            'line_position' => (int) ($db_line['line_position'] ?? ($index + 1)),
            'product_id' => (int) $this->review_line_value($db_line, $snapshot_line, 'product_id', 0),
            'product_code' => (string) $this->review_line_value($db_line, $snapshot_line, 'product_code', ''),
            'product_name' => (string) $this->review_line_value($db_line, $snapshot_line, 'product_name', ''),
            'category_name' => (string) $this->review_line_value($db_line, $snapshot_line, 'category_name', ''),
            'size_profile' => (string) $this->review_line_value($db_line, $snapshot_line, 'size_profile', ''),
            'color_name' => (string) $this->review_line_value($db_line, $snapshot_line, 'color_name', ''),
            'regular_qty' => max(0, (int) $this->review_line_value($db_line, $snapshot_line, 'regular_qty', 0)),
            'extended_qty' => max(0, (int) $this->review_line_value($db_line, $snapshot_line, 'extended_qty', 0)),
            'total_qty' => max(0, (int) $this->review_line_value($db_line, $snapshot_line, 'total_qty', 0)),
            'base_unit_cost' => (float) $this->review_line_value($db_line, $snapshot_line, 'base_unit_cost', 0),
            'extended_size_surcharge' => (float) $this->review_line_value($db_line, $snapshot_line, 'extended_size_surcharge', 0),
            'print_method_id' => (int) $this->review_line_value($db_line, $snapshot_line, 'print_method_id', 0),
            'print_method_name' => (string) $this->review_line_value($db_line, $snapshot_line, 'print_method_name', ''),
            'print_placement' => (string) $this->review_line_value($db_line, $snapshot_line, 'print_placement', ''),
            'print_pricing_type' => (string) $this->review_line_value($db_line, $snapshot_line, 'print_pricing_type', ''),
            'print_unit_price' => (float) $this->review_line_value($db_line, $snapshot_line, 'print_unit_price', 0),
            'print_color_count' => (int) $this->review_line_value($db_line, $snapshot_line, 'print_color_count', 0),
            'design_group' => (string) $this->review_line_value($db_line, $snapshot_line, 'design_group', ''),
            'screen_locations' => $screen_locations,
            'material_total' => (float) $this->review_line_value($db_line, $snapshot_line, 'material_total', 0),
            'print_total' => (float) $this->review_line_value($db_line, $snapshot_line, 'print_total', 0),
            'line_total' => (float) $this->review_line_value($db_line, $snapshot_line, 'line_total', 0),
            'line_notes' => (string) $this->review_line_value($db_line, $snapshot_line, 'line_notes', ''),
        ];
    }

    private function review_line_value($db_line, $snapshot_line, $key, $default = '')
    {
        if (array_key_exists($key, $db_line) && $db_line[$key] !== null && $db_line[$key] !== '') {
            return $db_line[$key];
        }

        if (is_array($snapshot_line) && array_key_exists($key, $snapshot_line)) {
            return $snapshot_line[$key];
        }

        return $default;
    }

    private function extract_review_fields($quote)
    {
        return [
            'manager_margin_percent' => (float) ($quote['manager_margin_percent'] ?? 0),
            'labor_fee' => (float) ($quote['labor_fee'] ?? 0),
            'delivery_fee' => (float) ($quote['delivery_fee'] ?? 0),
            'design_fee' => (float) ($quote['design_fee'] ?? 0),
            'discount_amount' => (float) ($quote['discount_amount'] ?? 0),
            'tax_rate' => (float) ($quote['tax_rate'] ?? 0),
            'review_notes' => (string) ($quote['review_notes'] ?? ''),
            'reviewed_by' => (string) ($quote['reviewed_by'] ?? ''),
            'reviewed_at' => (string) ($quote['reviewed_at'] ?? ''),
        ];
    }

    private function calculate_review_totals($quote)
    {
        $review = $this->extract_review_fields($quote);
        $base_subtotal = (float) $quote['subtotal'];
        $margin_base_subtotal = max(0, $base_subtotal - $this->quote_extended_surcharge_total($quote['lines'] ?? []));
        $margin_amount = round($margin_base_subtotal * ($review['manager_margin_percent'] / 100), 2);
        $pre_tax_total = $base_subtotal + $margin_amount + $review['labor_fee'] + $review['delivery_fee'] + $review['design_fee'] - $review['discount_amount'];
        $pre_tax_total = max(0, round($pre_tax_total, 2));
        $tax_amount = round($pre_tax_total * ($review['tax_rate'] / 100), 2);

        return [
            'base_subtotal' => $base_subtotal,
            'margin_base_subtotal' => $margin_base_subtotal,
            'margin_amount' => $margin_amount,
            'labor_fee' => $review['labor_fee'],
            'delivery_fee' => $review['delivery_fee'],
            'design_fee' => $review['design_fee'],
            'discount_amount' => $review['discount_amount'],
            'tax_rate' => $review['tax_rate'],
            'tax_amount' => $tax_amount,
            'final_total' => round($pre_tax_total + $tax_amount, 2),
        ];
    }

    private function quote_total_qty($lines)
    {
        $qty = 0;
        foreach ((array) $lines as $line) {
            $qty += (int) ($line['total_qty'] ?? 0);
        }

        return $qty;
    }

    private function quote_extended_surcharge_total($lines)
    {
        $total = 0.0;
        foreach ((array) $lines as $line) {
            $total += (int) ($line['extended_qty'] ?? 0) * (float) ($line['extended_size_surcharge'] ?? 0);
        }

        return round($total, 2);
    }

    private function allocate_review_pricing_to_lines($lines, $review, $review_totals)
    {
        $total_qty = $this->quote_total_qty($lines);
        if ($total_qty <= 0) {
            return $lines;
        }

        $margin_rate = ((float) ($review['manager_margin_percent'] ?? 0)) / 100;
        $shared_adjustment_per_piece = (
            (float) ($review['labor_fee'] ?? 0)
            + (float) ($review['delivery_fee'] ?? 0)
            + (float) ($review['design_fee'] ?? 0)
            - (float) ($review['discount_amount'] ?? 0)
        ) / $total_qty;
        $tax_per_piece = ((float) ($review_totals['tax_amount'] ?? 0)) / $total_qty;

        foreach ($lines as $index => $line) {
            $line_qty = max(0, (int) ($line['total_qty'] ?? 0));
            if ($line_qty === 0) {
                $lines[$index]['regular_customer_unit_price'] = 0.0;
                $lines[$index]['extended_customer_unit_price'] = 0.0;
                $lines[$index]['final_customer_unit_price'] = 0.0;
                $lines[$index]['final_customer_line_total'] = 0.0;
                $lines[$index]['margin_amount'] = 0.0;
                $lines[$index]['shared_adjustment_amount'] = 0.0;
                $lines[$index]['tax_share_amount'] = 0.0;
                continue;
            }

            $regular_qty = max(0, (int) ($line['regular_qty'] ?? 0));
            $extended_qty = max(0, (int) ($line['extended_qty'] ?? 0));
            $print_unit = $line_qty > 0 ? ((float) ($line['print_total'] ?? 0) / $line_qty) : 0.0;
            $raw_regular_unit = (float) ($line['base_unit_cost'] ?? 0) + $print_unit;
            $regular_margin_unit = $raw_regular_unit * $margin_rate;
            $regular_customer_unit = $raw_regular_unit + $regular_margin_unit + $shared_adjustment_per_piece + $tax_per_piece;
            $extended_customer_unit = $regular_customer_unit + (float) ($line['extended_size_surcharge'] ?? 0);
            $final_line_total = ($regular_qty * $regular_customer_unit) + ($extended_qty * $extended_customer_unit);
            $line_margin_base = max(0, ((float) ($line['line_total'] ?? 0)) - ($extended_qty * (float) ($line['extended_size_surcharge'] ?? 0)));

            $lines[$index]['raw_regular_unit_price'] = round($raw_regular_unit, 4);
            $lines[$index]['raw_extended_unit_price'] = round($raw_regular_unit + (float) ($line['extended_size_surcharge'] ?? 0), 4);
            $lines[$index]['regular_customer_unit_price'] = round($regular_customer_unit, 4);
            $lines[$index]['extended_customer_unit_price'] = round($extended_customer_unit, 4);
            $lines[$index]['final_customer_unit_price'] = round($final_line_total / $line_qty, 4);
            $lines[$index]['final_customer_line_total'] = round($final_line_total, 2);
            $lines[$index]['margin_amount'] = round($line_margin_base * $margin_rate, 2);
            $lines[$index]['shared_adjustment_amount'] = round($line_qty * $shared_adjustment_per_piece, 2);
            $lines[$index]['tax_share_amount'] = round($line_qty * $tax_per_piece, 2);
        }

        return $lines;
    }
}

new BW_Project_Quote_Tool();
