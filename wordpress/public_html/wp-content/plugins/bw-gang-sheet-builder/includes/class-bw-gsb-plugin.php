<?php

if (!defined('ABSPATH')) {
    exit;
}

class BW_GSB_Plugin
{
    private static $instance = null;

    const VERSION = BW_GSB_VERSION;
    const SHORTCODE = 'bw_gang_sheet_builder';
    const MENU_SLUG = 'bw-gang-sheet-submissions';
    const SETTINGS_SLUG = 'bw-gang-sheet-settings';
    const OPTION_VERSION = 'bw_gsb_version';
    const ACTION_SUBMIT = 'bw_gsb_submit';
    const ACTION_UPDATE_SUBMISSION = 'bw_gsb_update_submission';
    const ACTION_CREATE_ORDER = 'bw_gsb_create_order';
    const ACTION_GENERATE_PRINT_FILE = 'bw_gsb_generate_print_file';
    const STAFF_CAPABILITY = 'edit_shop_orders';
    const EXPORT_DPI = 300;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        register_activation_hook(BW_GSB_FILE, [__CLASS__, 'activate']);

        add_action('init', [$this, 'maybe_upgrade_schema'], 1);
        add_action('init', [$this, 'register_shortcode']);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_post_' . self::ACTION_SUBMIT, [$this, 'handle_submission']);
        add_action('admin_post_nopriv_' . self::ACTION_SUBMIT, [$this, 'handle_submission']);
        add_action('admin_post_' . self::ACTION_UPDATE_SUBMISSION, [$this, 'handle_submission_update']);
        add_action('admin_post_' . self::ACTION_CREATE_ORDER, [$this, 'handle_create_order']);
        add_action('admin_post_' . self::ACTION_GENERATE_PRINT_FILE, [$this, 'handle_generate_print_file']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_order_submission_files']);

        // Cart/checkout flow: pay first, review before production.
        add_action('woocommerce_before_calculate_totals', [$this, 'apply_cart_item_pricing'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'render_cart_item_data'], 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'cart_item_thumbnail'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_line_item_meta'], 10, 3);
        add_action('woocommerce_checkout_order_processed', [$this, 'link_order_to_submissions'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'link_store_api_order']);
        add_action('woocommerce_order_status_processing', [$this, 'mark_submissions_paid']);
        add_action('woocommerce_order_status_completed', [$this, 'mark_submissions_paid']);
    }

    public static function activate()
    {
        $instance = self::get_instance();
        $instance->install_or_upgrade();
    }

    public function maybe_upgrade_schema()
    {
        if (get_option(self::OPTION_VERSION) === self::VERSION && $this->schema_is_current()) {
            return;
        }

        $this->install_or_upgrade();
    }

    public function register_shortcode()
    {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    public function register_assets()
    {
        wp_register_style(
            'bw-gsb-style',
            BW_GSB_URL . 'assets/css/bw-gsb.css',
            [],
            self::VERSION
        );

        wp_register_script(
            'bw-gsb-script',
            BW_GSB_URL . 'assets/js/bw-gsb.js',
            [],
            self::VERSION,
            true
        );
    }

    public function register_admin_pages()
    {
        add_submenu_page(
            'woocommerce',
            __('Gang Sheet Submissions', 'bw-gsb'),
            __('Gang Sheet Submissions', 'bw-gsb'),
            self::STAFF_CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render_submissions_page']
        );

        add_submenu_page(
            'woocommerce',
            __('Gang Sheet Settings', 'bw-gsb'),
            __('Gang Sheet Settings', 'bw-gsb'),
            self::STAFF_CAPABILITY,
            self::SETTINGS_SLUG,
            [$this, 'render_settings_page']
        );
    }

    private function table($name)
    {
        global $wpdb;
        return $wpdb->prefix . 'bw_gsb_' . $name;
    }

    private function install_or_upgrade()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $submissions = $this->table('submissions');
        $assets = $this->table('assets');

        dbDelta("
            CREATE TABLE {$submissions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status VARCHAR(40) NOT NULL DEFAULT 'submitted',
                customer_name VARCHAR(190) NOT NULL DEFAULT '',
                customer_email VARCHAR(190) NOT NULL DEFAULT '',
                customer_phone VARCHAR(80) NOT NULL DEFAULT '',
                company_name VARCHAR(190) NOT NULL DEFAULT '',
                sheet_code VARCHAR(80) NOT NULL DEFAULT '',
                sheet_width DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                sheet_height DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(12) NOT NULL DEFAULT 'USD',
                notes LONGTEXT NULL,
                admin_notes LONGTEXT NULL,
                layout_json LONGTEXT NULL,
                preview_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                export_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                woocommerce_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                woocommerce_order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                session_key VARCHAR(120) NOT NULL DEFAULT '',
                PRIMARY KEY (id),
                KEY status (status),
                KEY customer_email (customer_email),
                KEY woocommerce_order_id (woocommerce_order_id)
            ) {$charset};
        ");

        $this->ensure_submission_schema_columns();

        dbDelta("
            CREATE TABLE {$assets} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                submission_id BIGINT UNSIGNED NOT NULL,
                attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                original_filename VARCHAR(255) NOT NULL DEFAULT '',
                mime_type VARCHAR(120) NOT NULL DEFAULT '',
                width_px INT UNSIGNED NOT NULL DEFAULT 0,
                height_px INT UNSIGNED NOT NULL DEFAULT 0,
                filesize_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY submission_id (submission_id),
                KEY attachment_id (attachment_id)
            ) {$charset};
        ");

        update_option(self::OPTION_VERSION, self::VERSION);
    }

    public function render_shortcode($atts = [])
    {
        wp_enqueue_style('bw-gsb-style');
        wp_enqueue_script('bw-gsb-script');

        $settings = $this->get_default_settings();
        wp_add_inline_script(
            'bw-gsb-script',
            'window.BWGangSheetBuilder=' . wp_json_encode([
                'sheets' => $settings['sheet_sizes'],
                'currencySymbol' => $settings['currency_symbol'],
                'version' => self::VERSION,
                'dpiGood' => 250,
                'dpiOk' => 150,
                'messages' => [
                    'uploadCountSingle' => __('1 file selected', 'bw-gsb'),
                    'uploadCountPlural' => __('files selected', 'bw-gsb'),
                    'dpiGood' => __('Print ready', 'bw-gsb'),
                    'dpiOk' => __('Acceptable, 300 DPI recommended', 'bw-gsb'),
                    'dpiLow' => __('Too low, will print blurry', 'bw-gsb'),
                    'dpiUnknown' => __('DPI unknown', 'bw-gsb'),
                    'warnOffSheet' => __('This artwork extends past the sheet edge and would be cut off.', 'bw-gsb'),
                    'warnOverlap' => __('This artwork overlaps another design on the sheet.', 'bw-gsb'),
                    'needArtwork' => __('Add at least one artwork to the sheet before adding it to your cart.', 'bw-gsb'),
                    'blockOffSheet' => __('Some artwork extends past the sheet edge and would be cut off. Please move or resize it before checking out.', 'bw-gsb'),
                    'confirmIssues' => __('Heads up: some artwork overlaps another design or is below the recommended print resolution. Add to cart anyway?', 'bw-gsb'),
                ],
            ]) . ';',
            'before'
        );

        $notice = $this->get_frontend_notice();
        $default_sheet = reset($settings['sheet_sizes']);

        ob_start();
        ?>
        <div class="bw-gsb-wrap">
            <?php if ($notice) : ?>
                <div class="bw-gsb-notice"><?php echo esc_html($notice); ?></div>
            <?php endif; ?>

            <form class="bw-gsb-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field(self::ACTION_SUBMIT, 'bw_gsb_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SUBMIT); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($this->current_url()); ?>">
                <input type="hidden" name="layout_json" value="">

                <section class="bw-gsb-hero">
                    <div>
                        <p class="bw-gsb-kicker"><?php esc_html_e('DTF Gang Sheet Builder', 'bw-gsb'); ?></p>
                        <h2><?php esc_html_e('Build Your Gang Sheet And Order It Online', 'bw-gsb'); ?></h2>
                        <p><?php esc_html_e('Upload your artwork, lay out the sheet exactly how you want it, and check out right away. Our team double-checks every sheet before it goes to press.', 'bw-gsb'); ?></p>
                    </div>
                    <div class="bw-gsb-card bw-gsb-summary">
                        <div><span><?php esc_html_e('Selected Sheet', 'bw-gsb'); ?></span><strong data-bw-gsb-sheet-label><?php echo esc_html($default_sheet['label']); ?></strong></div>
                        <div><span><?php esc_html_e('Price', 'bw-gsb'); ?></span><strong data-bw-gsb-price><?php echo esc_html($this->format_money($default_sheet['price'])); ?></strong></div>
                        <p><?php esc_html_e('Flat pricing per sheet size. Fill the sheet with as many designs as fit.', 'bw-gsb'); ?></p>
                    </div>
                </section>

                <section class="bw-gsb-grid">
                    <div class="bw-gsb-card">
                        <h3><?php esc_html_e('Sheet Setup', 'bw-gsb'); ?></h3>
                        <label>
                            <span><?php esc_html_e('Sheet Size', 'bw-gsb'); ?></span>
                            <select name="sheet_code" data-bw-gsb-sheet-select>
                                <?php foreach ($settings['sheet_sizes'] as $sheet) : ?>
                                    <option
                                        value="<?php echo esc_attr($sheet['code']); ?>"
                                        data-label="<?php echo esc_attr($sheet['label']); ?>"
                                        data-price="<?php echo esc_attr($this->number_string($sheet['price'])); ?>"
                                        data-width="<?php echo esc_attr($this->number_string($sheet['width'])); ?>"
                                        data-height="<?php echo esc_attr($this->number_string($sheet['height'])); ?>"
                                    >
                                        <?php echo esc_html($sheet['label'] . ' - ' . $this->format_money($sheet['price'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span><?php esc_html_e('Artwork Files', 'bw-gsb'); ?></span>
                            <input type="file" name="artwork_files[]" multiple accept=".png,image/png" data-bw-gsb-files>
                        </label>
                        <p class="bw-gsb-help" data-bw-gsb-file-summary><?php esc_html_e('No files selected yet.', 'bw-gsb'); ?></p>

                        <label>
                            <span><?php esc_html_e('Notes', 'bw-gsb'); ?></span>
                            <textarea name="notes" rows="4" placeholder="<?php esc_attr_e('Anything our team should know about this gang sheet?', 'bw-gsb'); ?>"></textarea>
                        </label>
                    </div>

                    <div class="bw-gsb-card">
                        <h3><?php esc_html_e('How It Works', 'bw-gsb'); ?></h3>
                        <ol class="bw-gsb-steps">
                            <li><?php esc_html_e('Pick your sheet size and upload PNG artwork.', 'bw-gsb'); ?></li>
                            <li><?php esc_html_e('Place, resize, rotate, and duplicate designs until the sheet is full.', 'bw-gsb'); ?></li>
                            <li><?php esc_html_e('Add the sheet to your cart and check out securely.', 'bw-gsb'); ?></li>
                            <li><?php esc_html_e('We review every sheet before printing and reach out if anything needs attention.', 'bw-gsb'); ?></li>
                        </ol>
                    </div>
                </section>

                <section class="bw-gsb-card bw-gsb-library">
                    <div class="bw-gsb-library-head">
                        <div>
                            <p class="bw-gsb-kicker"><?php esc_html_e('Artwork Library', 'bw-gsb'); ?></p>
                            <h3><?php esc_html_e('Uploaded Files Ready For Placement', 'bw-gsb'); ?></h3>
                        </div>
                        <p><?php esc_html_e('Upload files above, then use this library to add them to the gang sheet as many times as needed.', 'bw-gsb'); ?></p>
                    </div>
                    <div class="bw-gsb-upload-list" data-bw-gsb-upload-list></div>
                </section>

                <section class="bw-gsb-card bw-gsb-builder">
                    <div class="bw-gsb-builder-head">
                        <div>
                            <h3><?php esc_html_e('Builder Canvas', 'bw-gsb'); ?></h3>
                            <p><?php esc_html_e('Upload art, add it to the sheet, drag it into place, resize it, and rotate it. This is the first workable canvas pass for review and POC testing.', 'bw-gsb'); ?></p>
                        </div>
                        <div class="bw-gsb-meta">
                            <span data-bw-gsb-sheet-dimensions><?php echo esc_html($default_sheet['width'] . '" x ' . $default_sheet['height'] . '"'); ?></span>
                        </div>
                    </div>
                    <div class="bw-gsb-builder-tools">
                        <div class="bw-gsb-tool-group">
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-width-preset="4"><?php esc_html_e('4in Wide', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-width-preset="10"><?php esc_html_e('10in Wide', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-width-preset="12"><?php esc_html_e('12in Wide', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-rotate="-90"><?php esc_html_e('Rotate -90°', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-rotate="-15"><?php esc_html_e('-15°', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-rotate="15"><?php esc_html_e('+15°', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-rotate="90"><?php esc_html_e('Rotate +90°', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-duplicate><?php esc_html_e('Duplicate', 'bw-gsb'); ?></button>
                            <button type="button" class="bw-gsb-tool-button" data-bw-gsb-remove><?php esc_html_e('Remove Selected', 'bw-gsb'); ?></button>
                        </div>
                        <p class="bw-gsb-help"><?php esc_html_e('Tip: select an artwork to move, resize, or rotate it (drag the round handle, hold Shift to snap). Arrow keys nudge; Delete removes. Each artwork shows its live print size and DPI.', 'bw-gsb'); ?></p>
                    </div>
                    <div class="bw-gsb-item-panel" data-bw-gsb-item-panel hidden>
                        <div class="bw-gsb-item-panel-head">
                            <strong data-bw-gsb-item-name></strong>
                            <span class="bw-gsb-dpi-badge" data-bw-gsb-item-dpi></span>
                        </div>
                        <div class="bw-gsb-item-fields">
                            <label>
                                <span><?php esc_html_e('Width (in)', 'bw-gsb'); ?></span>
                                <input type="number" step="0.05" min="0.5" data-bw-gsb-item-width>
                            </label>
                            <label>
                                <span><?php esc_html_e('Height (in)', 'bw-gsb'); ?></span>
                                <input type="number" step="0.05" min="0.5" data-bw-gsb-item-height>
                            </label>
                            <label>
                                <span><?php esc_html_e('Rotation (°)', 'bw-gsb'); ?></span>
                                <input type="number" step="1" data-bw-gsb-item-rotation>
                            </label>
                        </div>
                        <p class="bw-gsb-item-warnings" data-bw-gsb-item-warnings hidden></p>
                    </div>
                    <div class="bw-gsb-canvas-shell">
                        <div class="bw-gsb-canvas" data-bw-gsb-canvas>
                            <div class="bw-gsb-canvas-grid"></div>
                            <div class="bw-gsb-canvas-empty">
                                <?php esc_html_e('Upload PNG files above to start placing artwork on the sheet.', 'bw-gsb'); ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bw-gsb-actions">
                    <button type="submit" class="bw-gsb-button">
                        <?php esc_html_e('Add To Cart', 'bw-gsb'); ?>
                        <span data-bw-gsb-button-price><?php echo esc_html('- ' . $this->format_money($default_sheet['price'])); ?></span>
                    </button>
                    <p><?php esc_html_e('Pay online now. Every sheet is reviewed by our team before it prints, and we will contact you if anything needs a fix.', 'bw-gsb'); ?></p>
                </section>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public function handle_submission()
    {
        check_admin_referer(self::ACTION_SUBMIT, 'bw_gsb_nonce');

        $this->enforce_submission_rate_limit();

        $settings = $this->get_default_settings();
        $sheet_code = sanitize_text_field(wp_unslash($_POST['sheet_code'] ?? ''));
        $sheet = $this->get_sheet_by_code($sheet_code, $settings['sheet_sizes']);

        if (!$sheet) {
            wp_die(esc_html__('Invalid gang sheet size selected.', 'bw-gsb'), 400);
        }

        $layout_json = $this->sanitize_layout_json($_POST['layout_json'] ?? '');
        $layout = $this->decode_layout_json($layout_json);
        if (empty($layout['items']) || !is_array($layout['items'])) {
            wp_die(esc_html__('Add at least one artwork to the sheet before adding it to your cart.', 'bw-gsb'), 400);
        }

        $submission = [
            'customer_name' => sanitize_text_field(wp_unslash($_POST['customer_name'] ?? '')),
            'customer_email' => sanitize_email(wp_unslash($_POST['customer_email'] ?? '')),
            'customer_phone' => sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? '')),
            'company_name' => sanitize_text_field(wp_unslash($_POST['company_name'] ?? '')),
            'sheet_code' => $sheet['code'],
            'sheet_width' => (float) $sheet['width'],
            'sheet_height' => (float) $sheet['height'],
            'price' => (float) $sheet['price'],
            'currency' => $settings['currency'],
            'notes' => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
            'layout_json' => $layout_json,
            'status' => 'awaiting_payment',
            'session_key' => wp_generate_uuid4(),
        ];

        global $wpdb;
        $inserted = $wpdb->insert($this->table('submissions'), $submission);

        if ($inserted === false) {
            wp_die(esc_html($wpdb->last_error ?: __('Unable to save submission.', 'bw-gsb')), 500);
        }

        $submission_id = (int) $wpdb->insert_id;
        $this->handle_uploaded_assets($submission_id);

        if (!$this->get_submission_assets($submission_id)) {
            $wpdb->delete($this->table('submissions'), ['id' => $submission_id]);
            wp_die(esc_html__('Your artwork files could not be processed. Please upload PNG files and try again.', 'bw-gsb'), 400);
        }

        $redirect = $this->add_submission_to_cart($submission_id);

        if (!$redirect) {
            // WooCommerce unavailable: fall back to the review-only flow.
            $wpdb->update($this->table('submissions'), ['status' => 'submitted'], ['id' => $submission_id]);
            $redirect = !empty($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
            $redirect = add_query_arg([
                'bw_gsb_notice' => 'submitted',
                'bw_gsb_submission' => $submission_id,
            ], $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Put a saved submission into the WooCommerce cart. Returns the cart URL
     * on success or empty string when WooCommerce cannot take the item.
     */
    private function add_submission_to_cart($submission_id)
    {
        if (!function_exists('WC') || !function_exists('wc_load_cart')) {
            return '';
        }

        // admin-post.php requests do not boot the frontend cart/session.
        if (null === WC()->cart) {
            wc_load_cart();
        }

        if (!WC()->cart) {
            return '';
        }

        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }

        $product = $this->get_or_create_order_product();
        if (!$product) {
            return '';
        }

        $cart_item_key = WC()->cart->add_to_cart($product->get_id(), 1, 0, [], [
            'bw_gsb_submission_id' => $submission_id,
            'bw_gsb_unique_key' => wp_generate_uuid4(),
        ]);

        if (!$cart_item_key) {
            return '';
        }

        return wc_get_cart_url();
    }

    public function apply_cart_item_pricing($cart)
    {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['bw_gsb_submission_id'])) {
                // Direct adds of the hidden gang sheet product bypass the
                // builder; drop them so a $0 line can never be purchased.
                $gsb_product_id = (int) get_option('bw_gsb_order_product_id', 0);
                if ($gsb_product_id > 0 && !empty($cart_item['product_id']) && (int) $cart_item['product_id'] === $gsb_product_id) {
                    $cart->remove_cart_item($cart_item_key);
                }
                continue;
            }

            $submission = $this->get_submission((int) $cart_item['bw_gsb_submission_id']);
            if (!$submission) {
                $cart->remove_cart_item($cart_item_key);
                continue;
            }

            if (isset($cart_item['data']) && is_object($cart_item['data'])) {
                $cart_item['data']->set_name(sprintf(
                    /* translators: %s: sheet size label */
                    __('Custom Gang Sheet (%s)', 'bw-gsb'),
                    $this->format_dimension($submission['sheet_width']) . ' x ' . $this->format_dimension($submission['sheet_height'])
                ));
                $cart_item['data']->set_price((float) $submission['price']);
            }
        }
    }

    public function render_cart_item_data($item_data, $cart_item)
    {
        if (empty($cart_item['bw_gsb_submission_id'])) {
            return $item_data;
        }

        $submission = $this->get_submission((int) $cart_item['bw_gsb_submission_id']);
        if (!$submission) {
            return $item_data;
        }

        $layout = $this->decode_layout_json($submission['layout_json']);
        $item_count = !empty($layout['items']) && is_array($layout['items']) ? count($layout['items']) : 0;

        $item_data[] = [
            'key' => __('Sheet Size', 'bw-gsb'),
            'value' => $this->format_dimension($submission['sheet_width']) . ' x ' . $this->format_dimension($submission['sheet_height']),
        ];
        $item_data[] = [
            'key' => __('Artwork Placements', 'bw-gsb'),
            'value' => (string) $item_count,
        ];

        return $item_data;
    }

    public function cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key)
    {
        if (empty($cart_item['bw_gsb_submission_id'])) {
            return $thumbnail;
        }

        $assets = $this->get_submission_assets((int) $cart_item['bw_gsb_submission_id']);
        if (!$assets) {
            return $thumbnail;
        }

        $image = wp_get_attachment_image((int) $assets[0]['attachment_id'], 'woocommerce_thumbnail');

        return $image ?: $thumbnail;
    }

    public function add_order_line_item_meta($item, $cart_item_key, $values)
    {
        if (empty($values['bw_gsb_submission_id'])) {
            return;
        }

        $submission_id = (int) $values['bw_gsb_submission_id'];
        $item->add_meta_data('_bw_gsb_submission_id', $submission_id, true);

        $submission = $this->get_submission($submission_id);
        if ($submission) {
            $item->add_meta_data(__('Sheet Size', 'bw-gsb'), $submission['sheet_code'], true);
        }
    }

    public function link_order_to_submissions($order_id, $posted_data = null, $order = null)
    {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        $first_submission_id = 0;

        foreach ($order->get_items() as $item) {
            $submission_id = (int) $item->get_meta('_bw_gsb_submission_id');
            if ($submission_id <= 0) {
                continue;
            }

            if ($first_submission_id === 0) {
                $first_submission_id = $submission_id;
            }

            global $wpdb;
            $wpdb->update(
                $this->table('submissions'),
                [
                    'woocommerce_order_id' => $order->get_id(),
                    'woocommerce_product_id' => (int) $item->get_product_id(),
                    'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    'customer_email' => $order->get_billing_email(),
                    'customer_phone' => $order->get_billing_phone(),
                    'company_name' => $order->get_billing_company(),
                ],
                ['id' => $submission_id]
            );
        }

        if ($first_submission_id > 0) {
            $order->update_meta_data('_bw_gsb_submission_id', $first_submission_id);
            $order->save();
        }
    }

    public function link_store_api_order($order)
    {
        if ($order instanceof WC_Order) {
            $this->link_order_to_submissions($order->get_id(), null, $order);
        }
    }

    public function mark_submissions_paid($order_id)
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table('submissions')}
                 SET status = 'paid'
                 WHERE woocommerce_order_id = %d
                   AND status IN ('awaiting_payment', 'submitted')",
                $order_id
            )
        );
    }

    private function handle_uploaded_assets($submission_id)
    {
        if (empty($_FILES['artwork_files']) || !is_array($_FILES['artwork_files']['name'])) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $files = $_FILES['artwork_files'];
        $allowed_mimes = $this->get_allowed_upload_mimes();
        $allowed_mime_values = array_values($allowed_mimes);

        // Abuse limits for this anonymous endpoint (filterable).
        $max_files = max(1, (int) apply_filters('bw_gsb_max_upload_files', 25));
        $max_bytes = max(1, (int) apply_filters('bw_gsb_max_upload_bytes', 25 * MB_IN_BYTES));

        $count = min(count($files['name']), $max_files);

        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i]) || (int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Reject oversized files before doing any processing.
            if ((int) $files['size'][$i] > $max_bytes) {
                continue;
            }

            $file_array = [
                'name' => $files['name'][$i],
                'type' => '', // Never trust the client-supplied MIME; let WP derive it.
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            $check = wp_check_filetype_and_ext($file_array['tmp_name'], $file_array['name'], $allowed_mimes);
            if (empty($check['type']) || !in_array($check['type'], $allowed_mime_values, true)) {
                continue;
            }

            // Verify the bytes are a real image whose true type is allowed
            // (blocks polyglots / mislabeled SVG/HTML smuggled past the extension check).
            $image_info = @getimagesize($file_array['tmp_name']);
            if ($image_info === false || empty($image_info['mime']) || !in_array($image_info['mime'], $allowed_mime_values, true)) {
                continue;
            }

            $attachment_id = media_handle_sideload($file_array, 0);
            if (is_wp_error($attachment_id)) {
                continue;
            }

            $meta = wp_get_attachment_metadata($attachment_id);
            $path = get_attached_file($attachment_id);

            global $wpdb;
            $wpdb->insert($this->table('assets'), [
                'submission_id' => $submission_id,
                'attachment_id' => $attachment_id,
                'original_filename' => sanitize_file_name((string) $files['name'][$i]),
                'mime_type' => get_post_mime_type($attachment_id) ?: '',
                'width_px' => (int) ($meta['width'] ?? 0),
                'height_px' => (int) ($meta['height'] ?? 0),
                'filesize_bytes' => $path && file_exists($path) ? (int) filesize($path) : 0,
            ]);
        }
    }

    public function render_submissions_page()
    {
        if (!current_user_can(self::STAFF_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to view gang sheet submissions.', 'bw-gsb'), 403);
        }

        wp_enqueue_style('bw-gsb-style');
        wp_enqueue_script('bw-gsb-script');

        $submission_id = absint($_GET['submission_id'] ?? 0);
        if ($submission_id > 0) {
            $this->render_submission_detail_page($submission_id);
            return;
        }

        $submissions = $this->get_recent_submissions();
        $notice = $this->get_admin_notice();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gang Sheet Submissions', 'bw-gsb'); ?></h1>
            <p><?php esc_html_e('This queue is ready for the customer-facing builder to submit into. It will later connect approved submissions into WooCommerce orders.', 'bw-gsb'); ?></p>
            <?php if ($notice) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Created', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Customer', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Email', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Sheet', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Price', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Status', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Assets', 'bw-gsb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions) : ?>
                        <?php foreach ($submissions as $submission) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->submission_admin_url((int) $submission['id'])); ?>">
                                        <?php echo esc_html((string) $submission['id']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html((string) $submission['created_at']); ?></td>
                                <td><?php echo esc_html((string) $submission['customer_name']); ?></td>
                                <td><?php echo esc_html((string) $submission['customer_email']); ?></td>
                                <td><?php echo esc_html((string) $submission['sheet_code']); ?></td>
                                <td><?php echo esc_html($this->format_money($submission['price'])); ?></td>
                                <td><?php echo esc_html((string) $submission['status']); ?></td>
                                <td><?php echo esc_html((string) $submission['asset_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8"><em><?php esc_html_e('No submissions yet. The public builder can already start feeding this queue once the shortcode is placed on a page.', 'bw-gsb'); ?></em></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_submission_detail_page($submission_id)
    {
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            wp_die(esc_html__('Gang sheet submission not found.', 'bw-gsb'), 404);
        }

        $assets = $this->get_submission_assets($submission_id);
        $layout = $this->decode_layout_json($submission['layout_json']);
        $preview_items = $this->build_layout_preview_items($layout, $assets);
        $notice = $this->get_admin_notice();
        $statuses = $this->get_status_options();
        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: submission ID */
                        __('Gang Sheet Submission #%d', 'bw-gsb'),
                        $submission_id
                    )
                );
                ?>
            </h1>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>">
                    <?php esc_html_e('Back to submissions', 'bw-gsb'); ?>
                </a>
            </p>

            <?php if ($notice) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>

            <div class="bw-gsb-admin-grid">
                <div class="bw-gsb-admin-card">
                    <h2><?php esc_html_e('Customer', 'bw-gsb'); ?></h2>
                    <p><strong><?php esc_html_e('Name:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['customer_name']); ?></p>
                    <p><strong><?php esc_html_e('Email:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['customer_email']); ?></p>
                    <p><strong><?php esc_html_e('Phone:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['customer_phone'] ?: '—'); ?></p>
                    <p><strong><?php esc_html_e('Company:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['company_name'] ?: '—'); ?></p>
                    <p><strong><?php esc_html_e('Created:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['created_at']); ?></p>
                </div>

                <div class="bw-gsb-admin-card">
                    <h2><?php esc_html_e('Sheet', 'bw-gsb'); ?></h2>
                    <p><strong><?php esc_html_e('Size:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['sheet_code']); ?></p>
                    <p><strong><?php esc_html_e('Dimensions:', 'bw-gsb'); ?></strong> <?php echo esc_html($this->format_dimension($submission['sheet_width']) . ' x ' . $this->format_dimension($submission['sheet_height'])); ?></p>
                    <p><strong><?php esc_html_e('Price:', 'bw-gsb'); ?></strong> <?php echo esc_html($this->format_money($submission['price'])); ?></p>
                    <p><strong><?php esc_html_e('Status:', 'bw-gsb'); ?></strong> <?php echo esc_html($submission['status']); ?></p>
                    <p><strong><?php esc_html_e('Print File:', 'bw-gsb'); ?></strong>
                        <?php $export_attachment_id = $this->get_submission_export_attachment_id($submission); ?>
                        <?php if ($export_attachment_id > 0) : ?>
                            <?php $export_url = wp_get_attachment_url($export_attachment_id); ?>
                            <a
                                href="<?php echo esc_url($export_url); ?>"
                                download="<?php echo esc_attr(wp_basename((string) $export_url)); ?>"
                            >
                                <?php esc_html_e('Download Print File', 'bw-gsb'); ?>
                            </a>
                        <?php else : ?>
                            <?php esc_html_e('Not generated yet', 'bw-gsb'); ?>
                        <?php endif; ?>
                    </p>
                    <p><strong><?php esc_html_e('WooCommerce Order:', 'bw-gsb'); ?></strong>
                        <?php if ((int) $submission['woocommerce_order_id'] > 0) : ?>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $submission['woocommerce_order_id'] . '&action=edit')); ?>">
                                <?php echo esc_html('#' . (int) $submission['woocommerce_order_id']); ?>
                            </a>
                        <?php else : ?>
                            <?php esc_html_e('Not created yet', 'bw-gsb'); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="bw-gsb-admin-grid">
                <div class="bw-gsb-admin-card">
                    <h2><?php esc_html_e('Review Actions', 'bw-gsb'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::ACTION_UPDATE_SUBMISSION, 'bw_gsb_update_nonce'); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_UPDATE_SUBMISSION); ?>">
                        <input type="hidden" name="submission_id" value="<?php echo esc_attr((string) $submission_id); ?>">

                        <p>
                            <label for="bw-gsb-status"><strong><?php esc_html_e('Status', 'bw-gsb'); ?></strong></label><br>
                            <select id="bw-gsb-status" name="status">
                                <?php foreach ($statuses as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($submission['status'], $status); ?>>
                                        <?php echo esc_html($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label for="bw-gsb-admin-notes"><strong><?php esc_html_e('Internal Notes', 'bw-gsb'); ?></strong></label><br>
                            <textarea id="bw-gsb-admin-notes" name="admin_notes" rows="8" class="large-text"><?php echo esc_textarea($submission['admin_notes']); ?></textarea>
                        </p>

                        <p><button type="submit" class="button button-primary"><?php esc_html_e('Save Review Update', 'bw-gsb'); ?></button></p>
                    </form>

                    <hr>
                    <p><?php esc_html_e('Generate or refresh the production PNG from the approved layout.', 'bw-gsb'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::ACTION_GENERATE_PRINT_FILE, 'bw_gsb_generate_print_nonce'); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_GENERATE_PRINT_FILE); ?>">
                        <input type="hidden" name="submission_id" value="<?php echo esc_attr((string) $submission_id); ?>">
                        <button type="submit" class="button">
                            <?php echo $export_attachment_id > 0 ? esc_html__('Regenerate Print File', 'bw-gsb') : esc_html__('Generate Print File', 'bw-gsb'); ?>
                        </button>
                    </form>

                    <?php if ((int) $submission['woocommerce_order_id'] === 0) : ?>
                        <hr>
                        <p><?php esc_html_e('Once the submission is approved, we can create a WooCommerce order shell from it.', 'bw-gsb'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field(self::ACTION_CREATE_ORDER, 'bw_gsb_create_order_nonce'); ?>
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_CREATE_ORDER); ?>">
                            <input type="hidden" name="submission_id" value="<?php echo esc_attr((string) $submission_id); ?>">
                            <button type="submit" class="button" <?php disabled($submission['status'] !== 'approved'); ?>>
                                <?php esc_html_e('Create WooCommerce Order', 'bw-gsb'); ?>
                            </button>
                        </form>
                        <?php if ($submission['status'] !== 'approved') : ?>
                            <p><em><?php esc_html_e('Set status to approved before creating the WooCommerce order.', 'bw-gsb'); ?></em></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="bw-gsb-admin-card">
                    <h2><?php esc_html_e('Submission Notes', 'bw-gsb'); ?></h2>
                    <p><?php echo nl2br(esc_html($submission['notes'] ?: 'No customer notes submitted.')); ?></p>
                </div>
            </div>

            <div class="bw-gsb-admin-grid bw-gsb-admin-grid-wide">
                <div class="bw-gsb-admin-card">
                    <h2><?php esc_html_e('Uploaded Assets', 'bw-gsb'); ?></h2>
                    <?php if ($assets) : ?>
                        <div class="bw-gsb-asset-grid">
                            <?php foreach ($assets as $asset) : ?>
                                <div class="bw-gsb-asset-card">
                                    <?php echo wp_get_attachment_image((int) $asset['attachment_id'], 'medium'); ?>
                                    <p><strong><?php echo esc_html($asset['original_filename']); ?></strong></p>
                                    <p><?php echo esc_html($asset['mime_type']); ?></p>
                                    <p><?php echo esc_html((int) $asset['width_px'] . ' x ' . (int) $asset['height_px'] . ' px'); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p><em><?php esc_html_e('No uploaded assets are attached yet.', 'bw-gsb'); ?></em></p>
                    <?php endif; ?>
                </div>

                <div class="bw-gsb-admin-card">
                    <h2><?php esc_html_e('Layout Snapshot', 'bw-gsb'); ?></h2>
                    <?php if (!empty($layout['sheet'])) : ?>
                        <p>
                            <strong><?php esc_html_e('Sheet:', 'bw-gsb'); ?></strong>
                            <?php echo esc_html(($layout['sheet']['label'] ?? $submission['sheet_code']) . ' / ' . $this->format_money($layout['sheet']['price'] ?? $submission['price'])); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($preview_items) && !empty($submission['sheet_width']) && !empty($submission['sheet_height'])) : ?>
                        <button type="button" class="bw-gsb-admin-preview-trigger" data-bw-gsb-preview-open>
                            <div
                                class="bw-gsb-admin-preview"
                                style="--bw-gsb-sheet-ratio: <?php echo esc_attr($this->number_string((float) $submission['sheet_height'] / max((float) $submission['sheet_width'], 0.01))); ?>;"
                            >
                                <div class="bw-gsb-admin-preview-grid"></div>
                                <?php foreach ($preview_items as $item) : ?>
                                    <div
                                        class="bw-gsb-admin-preview-item"
                                        style="<?php echo esc_attr($this->build_preview_item_style($item, (float) $submission['sheet_width'], (float) $submission['sheet_height'])); ?>"
                                    >
                                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['label']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </button>
                        <p class="bw-gsb-help"><?php esc_html_e('Click the snapshot to open a larger preview.', 'bw-gsb'); ?></p>

                        <div class="bw-gsb-admin-modal" data-bw-gsb-preview-modal hidden>
                            <div class="bw-gsb-admin-modal-backdrop" data-bw-gsb-preview-close></div>
                            <div class="bw-gsb-admin-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Large layout preview', 'bw-gsb'); ?>">
                                <button type="button" class="bw-gsb-admin-modal-close" data-bw-gsb-preview-close aria-label="<?php esc_attr_e('Close preview', 'bw-gsb'); ?>">
                                    <?php esc_html_e('Close', 'bw-gsb'); ?>
                                </button>
                                <div
                                    class="bw-gsb-admin-preview bw-gsb-admin-preview-large"
                                    style="--bw-gsb-sheet-ratio: <?php echo esc_attr($this->number_string((float) $submission['sheet_height'] / max((float) $submission['sheet_width'], 0.01))); ?>;"
                                >
                                    <div class="bw-gsb-admin-preview-grid"></div>
                                    <?php foreach ($preview_items as $item) : ?>
                                        <div
                                            class="bw-gsb-admin-preview-item"
                                            style="<?php echo esc_attr($this->build_preview_item_style($item, (float) $submission['sheet_width'], (float) $submission['sheet_height'])); ?>"
                                        >
                                            <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['label']); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!empty($layout['items']) && is_array($layout['items'])) : ?>
                        <p><em><?php esc_html_e('Layout data exists, but the preview could not be composed from the uploaded assets.', 'bw-gsb'); ?></em></p>
                    <?php endif; ?>

                    <?php if (!empty($layout['items']) && is_array($layout['items'])) : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Artwork', 'bw-gsb'); ?></th>
                                    <th><?php esc_html_e('Position', 'bw-gsb'); ?></th>
                                    <th><?php esc_html_e('Size', 'bw-gsb'); ?></th>
                                    <th><?php esc_html_e('Rotation', 'bw-gsb'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($layout['items'] as $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($item['label'] ?? ($item['file_name'] ?? 'Artwork')); ?></td>
                                        <td><?php echo esc_html('x ' . $this->format_dimension($item['x'] ?? 0) . ', y ' . $this->format_dimension($item['y'] ?? 0)); ?></td>
                                        <td><?php echo esc_html($this->format_dimension($item['width'] ?? 0) . ' x ' . $this->format_dimension($item['height'] ?? 0)); ?></td>
                                        <td><?php echo esc_html((string) ($item['rotation'] ?? 0) . '°'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><em><?php esc_html_e('No saved layout items yet. This will populate as the canvas builder is used.', 'bw-gsb'); ?></em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page()
    {
        if (!current_user_can(self::STAFF_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to view gang sheet settings.', 'bw-gsb'), 403);
        }

        wp_enqueue_style('bw-gsb-style');

        $settings = $this->get_default_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gang Sheet Builder Settings', 'bw-gsb'); ?></h1>
            <p><?php esc_html_e('These are code-level defaults for the scaffold. The questionnaire answers will drive the next pass of configurable business rules.', 'bw-gsb'); ?></p>

            <h2><?php esc_html_e('Default Sheet Sizes', 'bw-gsb'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Code', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Label', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Dimensions', 'bw-gsb'); ?></th>
                        <th><?php esc_html_e('Price', 'bw-gsb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings['sheet_sizes'] as $sheet) : ?>
                        <tr>
                            <td><?php echo esc_html($sheet['code']); ?></td>
                            <td><?php echo esc_html($sheet['label']); ?></td>
                            <td><?php echo esc_html($sheet['width'] . '" x ' . $sheet['height'] . '"'); ?></td>
                            <td><?php echo esc_html($this->format_money($sheet['price'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Current Defaults', 'bw-gsb'); ?></h2>
            <ul>
                <li><?php echo esc_html__('Accepted MIME types: image/png', 'bw-gsb'); ?></li>
                <li><?php echo esc_html__('Status flow: submitted, reviewing, needs_changes, approved, rejected, in_production, completed', 'bw-gsb'); ?></li>
                <li><?php echo esc_html__('WooCommerce integration path: approval-first with future order linkage', 'bw-gsb'); ?></li>
            </ul>
        </div>
        <?php
    }

    private function get_recent_submissions()
    {
        global $wpdb;
        $submissions = $this->table('submissions');
        $assets = $this->table('assets');

        return $wpdb->get_results("
            SELECT
                s.*,
                (
                    SELECT COUNT(*)
                    FROM {$assets} a
                    WHERE a.submission_id = s.id
                ) AS asset_count
            FROM {$submissions} s
            ORDER BY s.created_at DESC
            LIMIT 50
        ", ARRAY_A);
    }

    public function handle_submission_update()
    {
        if (!current_user_can(self::STAFF_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to update gang sheet submissions.', 'bw-gsb'), 403);
        }

        check_admin_referer(self::ACTION_UPDATE_SUBMISSION, 'bw_gsb_update_nonce');

        $submission_id = absint($_POST['submission_id'] ?? 0);
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            wp_die(esc_html__('Gang sheet submission not found.', 'bw-gsb'), 404);
        }

        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? 'submitted'));
        if (!in_array($status, $this->get_status_options(), true)) {
            $status = 'submitted';
        }

        global $wpdb;
        $wpdb->update(
            $this->table('submissions'),
            [
                'status' => $status,
                'admin_notes' => sanitize_textarea_field(wp_unslash($_POST['admin_notes'] ?? '')),
            ],
            ['id' => $submission_id]
        );

        wp_safe_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'submission_id' => $submission_id,
            'bw_gsb_admin_notice' => 'submission_updated',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_create_order()
    {
        if (!current_user_can(self::STAFF_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to create WooCommerce orders for gang sheet submissions.', 'bw-gsb'), 403);
        }

        check_admin_referer(self::ACTION_CREATE_ORDER, 'bw_gsb_create_order_nonce');

        if (!function_exists('wc_create_order')) {
            wp_die(esc_html__('WooCommerce is required to create an order.', 'bw-gsb'), 500);
        }

        $submission_id = absint($_POST['submission_id'] ?? 0);
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            wp_die(esc_html__('Gang sheet submission not found.', 'bw-gsb'), 404);
        }

        if ($submission['status'] !== 'approved') {
            wp_die(esc_html__('Submission must be approved before creating a WooCommerce order.', 'bw-gsb'), 400);
        }

        if ((int) $submission['woocommerce_order_id'] > 0) {
            wp_safe_redirect(add_query_arg([
                'page' => self::MENU_SLUG,
                'submission_id' => $submission_id,
                'bw_gsb_admin_notice' => 'order_exists',
            ], admin_url('admin.php')));
            exit;
        }

        $product = $this->get_or_create_order_product();
        $order = wc_create_order();
        if (is_wp_error($order) || !$product) {
            wp_die(esc_html__('Unable to create the WooCommerce order.', 'bw-gsb'), 500);
        }

        $export_attachment_id = $this->get_submission_export_attachment_id($submission);
        if ($export_attachment_id <= 0) {
            $generated = $this->generate_print_file($submission_id);
            if (!is_wp_error($generated)) {
                $export_attachment_id = (int) $generated;
                $submission = $this->get_submission($submission_id);
            }
        }

        $order_item_id = $order->add_product($product, 1, [
            'subtotal' => (float) $submission['price'],
            'total' => (float) $submission['price'],
        ]);

        if ($order_item_id) {
            wc_add_order_item_meta($order_item_id, __('Gang Sheet Submission ID', 'bw-gsb'), (string) $submission_id);
            wc_add_order_item_meta($order_item_id, __('Gang Sheet Size', 'bw-gsb'), (string) $submission['sheet_code']);
            wc_add_order_item_meta($order_item_id, __('Customer Email', 'bw-gsb'), (string) $submission['customer_email']);
            if ($export_attachment_id > 0) {
                $export_url = wp_get_attachment_url($export_attachment_id);
                if ($export_url) {
                    wc_add_order_item_meta($order_item_id, __('Gang Sheet Print File', 'bw-gsb'), $export_url);
                }
            }
        }

        $order->set_address([
            'first_name' => $submission['customer_name'],
            'email' => $submission['customer_email'],
            'phone' => $submission['customer_phone'],
            'company' => $submission['company_name'],
        ], 'billing');
        $order->set_customer_note($submission['notes']);
        $order->update_meta_data('_bw_gsb_submission_id', $submission_id);
        $order->update_meta_data('_bw_gsb_sheet_code', $submission['sheet_code']);
        $order->update_meta_data('_bw_gsb_customer_email', $submission['customer_email']);
        if ($export_attachment_id > 0) {
            $order->update_meta_data('_bw_gsb_export_attachment_id', $export_attachment_id);
        }
        $order->calculate_totals(false);
        $order->save();

        global $wpdb;
        $wpdb->update(
            $this->table('submissions'),
            [
                'woocommerce_order_id' => $order->get_id(),
                'woocommerce_product_id' => $product->get_id(),
            ],
            ['id' => $submission_id]
        );

        wp_safe_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'submission_id' => $submission_id,
            'bw_gsb_admin_notice' => 'order_created',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_generate_print_file()
    {
        if (!current_user_can(self::STAFF_CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to generate print files.', 'bw-gsb'), 403);
        }

        check_admin_referer(self::ACTION_GENERATE_PRINT_FILE, 'bw_gsb_generate_print_nonce');

        $submission_id = absint($_POST['submission_id'] ?? 0);
        $result = $this->generate_print_file($submission_id);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), 400);
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'submission_id' => $submission_id,
            'bw_gsb_admin_notice' => 'print_file_generated',
        ], admin_url('admin.php')));
        exit;
    }

    private function get_default_settings()
    {
        $defaults = [
            'currency' => 'USD',
            'currency_symbol' => '$',
            'sheet_sizes' => [
                [
                    'code' => '22x24',
                    'label' => '22" x 24"',
                    'width' => 22,
                    'height' => 24,
                    'price' => 24.00,
                ],
                [
                    'code' => '22x36',
                    'label' => '22" x 36"',
                    'width' => 22,
                    'height' => 36,
                    'price' => 36.00,
                ],
                [
                    'code' => '22x48',
                    'label' => '22" x 48"',
                    'width' => 22,
                    'height' => 48,
                    'price' => 48.00,
                ],
                [
                    'code' => '22x60',
                    'label' => '22" x 60"',
                    'width' => 22,
                    'height' => 60,
                    'price' => 60.00,
                ],
            ],
            'statuses' => [
                'awaiting_payment',
                'paid',
                'submitted',
                'reviewing',
                'needs_changes',
                'approved',
                'rejected',
                'in_production',
                'completed',
            ],
            'allowed_mimes' => [
                'png' => 'image/png',
            ],
        ];

        return apply_filters('bw_gsb_default_settings', $defaults);
    }

    private function get_allowed_upload_mimes()
    {
        $settings = $this->get_default_settings();
        return (array) ($settings['allowed_mimes'] ?? ['png' => 'image/png']);
    }

    /**
     * Basic per-IP throttle for the public (nopriv) submission endpoint to limit
     * automated flooding. Uses REMOTE_ADDR rather than spoofable client headers.
     */
    private function enforce_submission_rate_limit()
    {
        $max_per_window = max(1, (int) apply_filters('bw_gsb_max_submissions_per_hour', 15));
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') {
            return;
        }

        $key = 'bw_gsb_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= $max_per_window) {
            wp_die(
                esc_html__('Too many submissions from your network. Please wait a little while and try again.', 'bw-gsb'),
                esc_html__('Slow down', 'bw-gsb'),
                ['response' => 429]
            );
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    private function get_sheet_by_code($sheet_code, $sheets)
    {
        foreach ($sheets as $sheet) {
            if (($sheet['code'] ?? '') === $sheet_code) {
                return $sheet;
            }
        }

        return null;
    }

    private function sanitize_layout_json($raw)
    {
        $raw = is_string($raw) ? wp_unslash($raw) : '';
        if ($raw === '') {
            return wp_json_encode([
                'sheet' => null,
                'items' => [],
                'builder_version' => self::VERSION,
            ]);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [
                'sheet' => null,
                'items' => [],
                'builder_version' => self::VERSION,
            ];
        }

        return wp_json_encode($decoded);
    }

    private function get_frontend_notice()
    {
        $notice = sanitize_text_field(wp_unslash($_GET['bw_gsb_notice'] ?? ''));
        if ($notice !== 'submitted') {
            return '';
        }

        $submission_id = absint($_GET['bw_gsb_submission'] ?? 0);
        if ($submission_id > 0) {
            return sprintf(
                /* translators: %d: submission ID */
                __('Gang sheet submission #%d saved. Our team can now review it.', 'bw-gsb'),
                $submission_id
            );
        }

        return __('Gang sheet submission saved. Our team can now review it.', 'bw-gsb');
    }

    private function current_url()
    {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        return esc_url_raw($scheme . $host . $uri);
    }

    private function format_money($value)
    {
        return '$' . number_format((float) $value, 2);
    }

    private function number_string($value)
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function get_submission($submission_id)
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table('submissions')} WHERE id = %d", $submission_id),
            ARRAY_A
        );
    }

    private function get_submission_assets($submission_id)
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table('assets')} WHERE submission_id = %d ORDER BY id ASC", $submission_id),
            ARRAY_A
        );
    }

    private function decode_layout_json($json)
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : ['sheet' => null, 'items' => []];
    }

    private function build_layout_preview_items($layout, $assets)
    {
        if (empty($layout['items']) || !is_array($layout['items']) || empty($assets)) {
            return [];
        }

        $assets_by_filename = [];
        foreach ($assets as $asset) {
            $filename = $this->normalize_filename_key((string) ($asset['original_filename'] ?? ''));
            if ($filename !== '' && empty($assets_by_filename[$filename])) {
                $assets_by_filename[$filename] = $asset;
            }
        }

        $preview_items = [];
        foreach ($layout['items'] as $item) {
            $file_name = $this->normalize_filename_key((string) ($item['file_name'] ?? ''));
            if ($file_name === '' || empty($assets_by_filename[$file_name])) {
                continue;
            }

            $asset = $assets_by_filename[$file_name];
            $image_url = wp_get_attachment_image_url((int) $asset['attachment_id'], 'large');
            if (!$image_url) {
                continue;
            }

            $preview_items[] = [
                'label' => (string) ($item['label'] ?? $item['file_name']),
                'attachment_id' => (int) $asset['attachment_id'],
                'source_path' => (string) get_attached_file((int) $asset['attachment_id']),
                'image_url' => $image_url,
                'x' => (float) ($item['x'] ?? 0),
                'y' => (float) ($item['y'] ?? 0),
                'width' => (float) ($item['width'] ?? 0),
                'height' => (float) ($item['height'] ?? 0),
                'rotation' => (float) ($item['rotation'] ?? 0),
                'z_index' => (int) ($item['z_index'] ?? 1),
            ];
        }

        usort($preview_items, static function ($left, $right) {
            return ($left['z_index'] ?? 0) <=> ($right['z_index'] ?? 0);
        });

        return $preview_items;
    }

    private function get_status_options()
    {
        $settings = $this->get_default_settings();
        return array_values((array) ($settings['statuses'] ?? []));
    }

    private function submission_admin_url($submission_id)
    {
        return add_query_arg([
            'page' => self::MENU_SLUG,
            'submission_id' => $submission_id,
        ], admin_url('admin.php'));
    }

    private function get_admin_notice()
    {
        $notice = sanitize_text_field(wp_unslash($_GET['bw_gsb_admin_notice'] ?? ''));
        $map = [
            'submission_updated' => __('Submission updated.', 'bw-gsb'),
            'order_created' => __('WooCommerce order created from approved submission.', 'bw-gsb'),
            'order_exists' => __('This submission already has a WooCommerce order linked.', 'bw-gsb'),
            'print_file_generated' => __('Print file generated.', 'bw-gsb'),
        ];

        return $map[$notice] ?? '';
    }

    private function format_dimension($value)
    {
        $number = (float) $value;
        $formatted = number_format($number, 2, '.', '');
        $formatted = preg_replace('/\.00$/', '', $formatted);
        return $formatted . '"';
    }

    private function build_preview_item_style($item, $sheet_width, $sheet_height)
    {
        $sheet_width = max($sheet_width, 0.01);
        $sheet_height = max($sheet_height, 0.01);

        $left = (($item['x'] ?? 0) / $sheet_width) * 100;
        $top = (($item['y'] ?? 0) / $sheet_height) * 100;
        $width = (($item['width'] ?? 0) / $sheet_width) * 100;
        $height = (($item['height'] ?? 0) / $sheet_height) * 100;
        $rotation = (float) ($item['rotation'] ?? 0);
        $z_index = (int) ($item['z_index'] ?? 1);

        return sprintf(
            'left: %.4f%%; top: %.4f%%; width: %.4f%%; height: %.4f%%; transform: rotate(%.2fdeg); z-index: %d;',
            $left,
            $top,
            $width,
            $height,
            $rotation,
            $z_index
        );
    }

    private function get_or_create_order_product()
    {
        if (!class_exists('WC_Product_Simple')) {
            return null;
        }

        $product_id = (int) get_option('bw_gsb_order_product_id', 0);
        if ($product_id > 0) {
            $product = wc_get_product($product_id);
            if ($product) {
                return $this->ensure_order_product_flags($product);
            }
        }

        $product = new WC_Product_Simple();
        $product->set_name(__('Custom Gang Sheet', 'bw-gsb'));
        $product->set_sold_individually(false);
        $product_id = $this->configure_order_product($product)->save();

        if ($product_id > 0) {
            update_option('bw_gsb_order_product_id', $product_id);
            return wc_get_product($product_id);
        }

        return null;
    }

    /**
     * The carrier product must be purchasable by guests (published, priced)
     * yet invisible in the catalog, and physical so checkout collects a
     * shipping address. Older installs created it private/virtual; heal that.
     */
    private function configure_order_product($product)
    {
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_regular_price('0');
        $product->set_virtual(false);
        $product->set_downloadable(false);

        return $product;
    }

    private function ensure_order_product_flags($product)
    {
        $needs_fix = $product->get_status() !== 'publish'
            || $product->get_catalog_visibility() !== 'hidden'
            || $product->is_virtual()
            || $product->is_downloadable()
            || $product->get_regular_price() === '';

        if ($needs_fix) {
            $this->configure_order_product($product)->save();
            $product = wc_get_product($product->get_id());
        }

        return $product;
    }

    private function normalize_filename_key($filename)
    {
        $filename = sanitize_file_name($filename);
        $filename = strtolower(trim($filename));
        return $filename;
    }

    public function render_order_submission_files($order)
    {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $submission_id = (int) $order->get_meta('_bw_gsb_submission_id');
        if ($submission_id <= 0) {
            return;
        }

        $submission = $this->get_submission($submission_id);
        $assets = $this->get_submission_assets($submission_id);
        $export_attachment_id = (int) $order->get_meta('_bw_gsb_export_attachment_id');
        if ($export_attachment_id <= 0) {
            $export_attachment_id = $this->get_submission_export_attachment_id($submission);
        }
        if (!$assets) {
            $assets = [];
        }
        ?>
        <div class="order_data_column">
            <h3><?php esc_html_e('Gang Sheet Files', 'bw-gsb'); ?></h3>
            <p><strong><?php esc_html_e('Submission ID:', 'bw-gsb'); ?></strong> <?php echo esc_html((string) $submission_id); ?></p>
            <?php if ($export_attachment_id > 0) : ?>
                <?php $export_url = wp_get_attachment_url($export_attachment_id); ?>
                <?php if ($export_url) : ?>
                    <p>
                        <strong><?php esc_html_e('Print File:', 'bw-gsb'); ?></strong>
                        <a
                            href="<?php echo esc_url($export_url); ?>"
                            download="<?php echo esc_attr(wp_basename((string) $export_url)); ?>"
                        >
                            <?php esc_html_e('Download Print PNG', 'bw-gsb'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($assets) : ?>
            <ul>
                <?php foreach ($assets as $asset) : ?>
                    <?php $url = wp_get_attachment_url((int) $asset['attachment_id']); ?>
                    <?php if (!$url) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($asset['original_filename']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    private function generate_print_file($submission_id)
    {
        if (!class_exists('Imagick')) {
            return new WP_Error('bw_gsb_no_imagick', __('Imagick is required to generate print files.', 'bw-gsb'));
        }

        // Cap Imagick resources so a malicious/oversized uploaded image cannot
        // exhaust memory/CPU (decompression-bomb DoS) during print generation.
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, 80 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_DISK, 1024 * 1024 * 1024);
        $max_source_pixels = (int) apply_filters('bw_gsb_max_source_pixels', 60 * 1000 * 1000);

        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            return new WP_Error('bw_gsb_submission_missing', __('Gang sheet submission not found.', 'bw-gsb'));
        }

        $assets = $this->get_submission_assets($submission_id);
        $layout = $this->decode_layout_json($submission['layout_json']);
        $preview_items = $this->build_layout_preview_items($layout, $assets);
        if (!$preview_items) {
            return new WP_Error('bw_gsb_no_layout_items', __('No layout items were found to generate the print file.', 'bw-gsb'));
        }

        $sheet_width = (float) $submission['sheet_width'];
        $sheet_height = (float) $submission['sheet_height'];
        if ($sheet_width <= 0 || $sheet_height <= 0) {
            return new WP_Error('bw_gsb_invalid_sheet', __('Submission is missing valid sheet dimensions.', 'bw-gsb'));
        }

        $width_px = (int) round($sheet_width * self::EXPORT_DPI);
        $height_px = (int) round($sheet_height * self::EXPORT_DPI);

        $canvas = new Imagick();
        $canvas->newImage($width_px, $height_px, new ImagickPixel('transparent'), 'png');
        $canvas->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
        $canvas->setImageResolution(self::EXPORT_DPI, self::EXPORT_DPI);
        $canvas->setImageFormat('png');

        foreach ($preview_items as $item) {
            $source_path = (string) ($item['source_path'] ?? '');
            if (!$source_path || !file_exists($source_path)) {
                continue;
            }

            // Bomb guard: skip source images with an implausibly large pixel area.
            $dims = @getimagesize($source_path);
            if ($dims && isset($dims[0], $dims[1]) && ($dims[0] * $dims[1]) > $max_source_pixels) {
                continue;
            }
            try {
                $asset = new Imagick($source_path);
            } catch (\Exception $e) {
                continue;
            }
            $asset->setImageBackgroundColor(new ImagickPixel('transparent'));
            $item_width_px = max(1, (int) round(((float) $item['width']) * self::EXPORT_DPI));
            $item_height_px = max(1, (int) round(((float) $item['height']) * self::EXPORT_DPI));
            $asset->resizeImage($item_width_px, $item_height_px, Imagick::FILTER_LANCZOS, 1, true);

            $rotation = (float) ($item['rotation'] ?? 0);
            if (abs($rotation) > 0.001) {
                $asset->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
                $asset->rotateImage(new ImagickPixel('transparent'), $rotation);
            }

            $x = (int) round(((float) $item['x']) * self::EXPORT_DPI);
            $y = (int) round(((float) $item['y']) * self::EXPORT_DPI);
            $canvas->compositeImage($asset, Imagick::COMPOSITE_DEFAULT, $x, $y);
            $asset->clear();
            $asset->destroy();
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return new WP_Error('bw_gsb_upload_dir', $upload['error']);
        }

        $filename = sanitize_file_name(sprintf('gang-sheet-%d-%s.png', $submission_id, current_time('Ymd-His')));
        $filepath = trailingslashit($upload['path']) . $filename;
        $canvas->writeImage($filepath);
        $canvas->clear();
        $canvas->destroy();

        $attachment_id = $this->insert_generated_attachment($filepath, $submission_id, $filename);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        global $wpdb;
        if ($this->submission_table_has_column('export_attachment_id')) {
            $wpdb->update(
                $this->table('submissions'),
                ['export_attachment_id' => $attachment_id],
                ['id' => $submission_id]
            );
        }

        if (!empty($submission['woocommerce_order_id'])) {
            $order = wc_get_order((int) $submission['woocommerce_order_id']);
            if ($order) {
                $order->update_meta_data('_bw_gsb_export_attachment_id', $attachment_id);
                $order->save();
            }
        }

        return $attachment_id;
    }

    private function insert_generated_attachment($filepath, $submission_id, $filename)
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'guid' => wp_upload_dir()['url'] . '/' . basename($filepath),
            'post_mime_type' => $filetype['type'] ?: 'image/png',
            'post_title' => 'Gang Sheet Print File #' . $submission_id,
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $filepath);
        if (!$attachment_id || is_wp_error($attachment_id)) {
            return new WP_Error('bw_gsb_attachment_insert', __('Unable to register generated print file in WordPress.', 'bw-gsb'));
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $filepath);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return (int) $attachment_id;
    }

    private function schema_is_current()
    {
        return $this->submission_table_has_column('export_attachment_id');
    }

    private function ensure_submission_schema_columns()
    {
        global $wpdb;
        $table = $this->table('submissions');

        if (!$this->submission_table_has_column('export_attachment_id')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN export_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER preview_attachment_id");
        }
    }

    private function submission_table_has_column($column)
    {
        global $wpdb;
        $table = $this->table('submissions');
        $result = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return !empty($result);
    }

    private function get_submission_export_attachment_id($submission)
    {
        $submission_id = (int) ($submission['id'] ?? 0);
        if ($submission_id <= 0) {
            return 0;
        }

        $existing = isset($submission['export_attachment_id']) ? (int) $submission['export_attachment_id'] : 0;
        if ($existing > 0) {
            return $existing;
        }

        $found = $this->find_generated_export_attachment_id($submission_id);
        if ($found > 0 && $this->submission_table_has_column('export_attachment_id')) {
            global $wpdb;
            $wpdb->update(
                $this->table('submissions'),
                ['export_attachment_id' => $found],
                ['id' => $submission_id]
            );
        }

        return $found;
    }

    private function find_generated_export_attachment_id($submission_id)
    {
        $posts = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'title' => 'Gang Sheet Print File #' . $submission_id,
            'fields' => 'ids',
        ]);

        if (!empty($posts[0])) {
            return (int) $posts[0];
        }

        return 0;
    }
}
