<?php
/**
 * Plugin Name: BW Contact & Chat
 * Description: Contact form for custom-job requests plus a site-wide help widget with instant canned answers and an offline "leave a message" fallback. All submissions are stored and emailed to the team.
 * Version: 1.0.0
 * Author: Barebones Apparel
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Contact_Chat
{
    const CPT = 'bw_message';
    const ACTION = 'bw_cc_submit';
    const NONCE = 'bw_cc_nonce';
    const SHORTCODE = 'bw_contact_form';
    const CAP = 'edit_shop_orders';

    public function __construct()
    {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('init', [$this, 'register_cpt']);
        add_shortcode(self::SHORTCODE, [$this, 'render_contact_form']);
        add_action('wp_footer', [$this, 'render_chat_widget']);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_submit']);
        add_action('admin_post_nopriv_' . self::ACTION, [$this, 'handle_submit']);
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    public static function activate()
    {
        (new self())->register_cpt();

        // Ensure a Contact page exists with the form shortcode.
        $existing = get_page_by_path('contact');
        if (!$existing) {
            wp_insert_post([
                'post_title' => 'Contact',
                'post_name' => 'contact',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '[' . self::SHORTCODE . ']',
            ]);
        }
        flush_rewrite_rules();
    }

    public function register_cpt()
    {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => __('Messages', 'bw'),
                'singular_name' => __('Message', 'bw'),
            ],
            'public' => false,
            'show_ui' => false, // custom admin screen below
            'capability_type' => 'post',
            'supports' => ['title'],
        ]);
    }

    /* ---------------- Canned chat answers ---------------- */

    private function canned_answers()
    {
        return apply_filters('bw_cc_canned', [
            [
                'q' => __('How fast can you turn an order around?', 'bw'),
                'a' => __('Most orders ship in a few business days. Rush and 24-hour jobs are possible — tell us your deadline and we will confirm.', 'bw'),
            ],
            [
                'q' => __('How do DTF gang sheets work?', 'bw'),
                'a' => __('Build your sheet in our DTF Builder, add it to the cart, and check out. We review every sheet before it prints and reach out if anything needs a fix.', 'bw'),
            ],
            [
                'q' => __('Can you do a custom job / quote?', 'bw'),
                'a' => __('Absolutely — custom work is our specialty. Leave us a message below with the details and we will get you a quote.', 'bw'),
            ],
            [
                'q' => __('Do you offer team or bulk pricing?', 'bw'),
                'a' => __('Yes. Non-profit (501c) and team/bulk orders get special rates — send us the details and quantities below.', 'bw'),
            ],
            [
                'q' => __('Where is my order?', 'bw'),
                'a' => __('Reply with your order number and email and we will check the status for you.', 'bw'),
            ],
        ]);
    }

    /* ---------------- Contact form ---------------- */

    public function render_contact_form($atts = [])
    {
        $notice = '';
        if (($_GET['bw_cc'] ?? '') === 'sent') {
            $notice = '<div class="bw-cc-ok">' . esc_html__('Thanks! Your message is in — we will get back to you shortly.', 'bw') . '</div>';
        }

        ob_start();
        ?>
        <div class="bw-cc-contact">
            <?php echo $notice; // phpcs:ignore -- built from safe literal above ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bw-cc-form">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="source" value="contact">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($this->current_url()); ?>">
                <!-- honeypot -->
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label>Company website<input type="text" name="bw_website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="bw-cc-grid">
                    <label><span><?php esc_html_e('Name', 'bw'); ?> *</span>
                        <input type="text" name="name" required></label>
                    <label><span><?php esc_html_e('Email', 'bw'); ?> *</span>
                        <input type="email" name="email" required></label>
                    <label><span><?php esc_html_e('Phone', 'bw'); ?></span>
                        <input type="text" name="phone"></label>
                    <label><span><?php esc_html_e('Request type', 'bw'); ?></span>
                        <select name="request_type">
                            <option><?php esc_html_e('General question', 'bw'); ?></option>
                            <option><?php esc_html_e('Custom job / quote', 'bw'); ?></option>
                            <option><?php esc_html_e('Team / bulk order', 'bw'); ?></option>
                            <option><?php esc_html_e('DTF transfers', 'bw'); ?></option>
                            <option><?php esc_html_e('Order question', 'bw'); ?></option>
                        </select>
                    </label>
                </div>
                <label class="bw-cc-full"><span><?php esc_html_e('How can we help?', 'bw'); ?> *</span>
                    <textarea name="message" rows="6" required></textarea></label>
                <button type="submit" class="bw-cc-btn"><?php esc_html_e('Send message', 'bw'); ?></button>
            </form>
        </div>
        <?php echo $this->contact_styles(); ?>
        <?php
        return ob_get_clean();
    }

    /* ---------------- Chat widget ---------------- */

    public function render_chat_widget()
    {
        if (is_admin()) {
            return;
        }
        $answers = $this->canned_answers();
        $contact_page = get_page_by_path('contact');
        $contact_url = $contact_page ? get_permalink($contact_page) : home_url('/contact/');
        ?>
        <div class="bw-chat" data-bw-chat>
            <button type="button" class="bw-chat__launch" data-bw-chat-launch aria-label="<?php esc_attr_e('Chat with us', 'bw'); ?>">
                <span class="bw-chat__launch-icon">&#128172;</span>
                <span class="bw-chat__launch-text"><?php esc_html_e('Chat', 'bw'); ?></span>
            </button>

            <div class="bw-chat__panel" data-bw-chat-panel hidden>
                <div class="bw-chat__head">
                    <strong><?php esc_html_e('Barebones Apparel', 'bw'); ?></strong>
                    <button type="button" class="bw-chat__close" data-bw-chat-close aria-label="<?php esc_attr_e('Close', 'bw'); ?>">&times;</button>
                </div>
                <div class="bw-chat__body" data-bw-chat-body>
                    <div class="bw-chat__msg bw-chat__msg--bot"><?php esc_html_e('Hi! Pick a question for an instant answer, or leave us a message and we will reply by email.', 'bw'); ?></div>
                    <div class="bw-chat__faqs" data-bw-chat-faqs>
                        <?php foreach ($answers as $i => $item) : ?>
                            <button type="button" class="bw-chat__faq" data-bw-faq="<?php echo (int) $i; ?>"><?php echo esc_html($item['q']); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bw-chat__foot">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bw-chat__form" data-bw-chat-form>
                        <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                        <input type="hidden" name="source" value="chat">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($this->current_url()); ?>">
                        <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="bw_website" tabindex="-1" autocomplete="off"></div>
                        <input type="text" name="name" placeholder="<?php esc_attr_e('Your name', 'bw'); ?>" required>
                        <input type="email" name="email" placeholder="<?php esc_attr_e('Your email', 'bw'); ?>" required>
                        <textarea name="message" rows="2" placeholder="<?php esc_attr_e('Type your message…', 'bw'); ?>" required></textarea>
                        <button type="submit" class="bw-cc-btn"><?php esc_html_e('Send', 'bw'); ?></button>
                        <a class="bw-chat__full" href="<?php echo esc_url($contact_url); ?>"><?php esc_html_e('Open full contact form', 'bw'); ?></a>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var ANSWERS = <?php echo wp_json_encode(array_map(static fn($a) => $a['a'], $answers)); ?>;
            var wrap = document.querySelector('[data-bw-chat]');
            if (!wrap) { return; }
            var launch = wrap.querySelector('[data-bw-chat-launch]');
            var panel = wrap.querySelector('[data-bw-chat-panel]');
            var body = wrap.querySelector('[data-bw-chat-body]');
            var sent = <?php echo (($_GET['bw_cc'] ?? '') === 'sent') ? 'true' : 'false'; ?>;

            function open() { panel.hidden = false; wrap.classList.add('is-open'); }
            function close() { panel.hidden = true; wrap.classList.remove('is-open'); }

            launch.addEventListener('click', function () { panel.hidden ? open() : close(); });
            wrap.querySelector('[data-bw-chat-close]').addEventListener('click', close);

            wrap.querySelectorAll('[data-bw-faq]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var i = parseInt(btn.getAttribute('data-bw-faq'), 10);
                    var q = document.createElement('div'); q.className = 'bw-chat__msg bw-chat__msg--user'; q.textContent = btn.textContent;
                    var a = document.createElement('div'); a.className = 'bw-chat__msg bw-chat__msg--bot'; a.textContent = ANSWERS[i] || '';
                    var faqs = wrap.querySelector('[data-bw-chat-faqs]');
                    body.insertBefore(q, faqs); body.insertBefore(a, faqs);
                    body.scrollTop = body.scrollHeight;
                });
            });

            // Reopen with a thank-you after an offline message submit.
            if (sent) {
                open();
                var ok = document.createElement('div'); ok.className = 'bw-chat__msg bw-chat__msg--bot';
                ok.textContent = <?php echo wp_json_encode(__('Thanks! Your message is in — we will reply by email shortly.', 'bw')); ?>;
                body.appendChild(ok); body.scrollTop = body.scrollHeight;
            }
        }());
        </script>
        <?php
        echo $this->widget_styles();
    }

    /* ---------------- Submit handler ---------------- */

    public function handle_submit()
    {
        check_admin_referer(self::NONCE, self::NONCE);

        // Honeypot: real users leave it empty.
        if (!empty($_POST['bw_website'])) {
            $this->redirect_back();
        }

        $this->rate_limit();

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $type = sanitize_text_field(wp_unslash($_POST['request_type'] ?? 'General'));
        $source = (($_POST['source'] ?? '') === 'chat') ? 'chat' : 'contact';
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        if (!$name || !$email || !$message) {
            $this->redirect_back();
        }

        $post_id = wp_insert_post([
            'post_type' => self::CPT,
            'post_status' => 'private',
            'post_title' => sprintf('%s — %s', $name, $type),
            'post_content' => $message,
        ]);

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_bw_name', $name);
            update_post_meta($post_id, '_bw_email', $email);
            update_post_meta($post_id, '_bw_phone', $phone);
            update_post_meta($post_id, '_bw_type', $type);
            update_post_meta($post_id, '_bw_source', $source);
            update_post_meta($post_id, '_bw_status', 'new');
            update_post_meta($post_id, '_bw_ip', isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');

            $this->notify($name, $email, $phone, $type, $source, $message);
        }

        $this->redirect_back('sent');
    }

    private function notify($name, $email, $phone, $type, $source, $message)
    {
        $to = apply_filters('bw_cc_notify_email', get_option('admin_email'));
        $subject = sprintf('[%s] %s from %s', wp_specialchars_decode(get_bloginfo('name')), $source === 'chat' ? 'Chat message' : 'Contact request', $name);
        $lines = [
            'Source: ' . $source,
            'Name: ' . $name,
            'Email: ' . $email,
            'Phone: ' . ($phone ?: '—'),
            'Type: ' . $type,
            '',
            $message,
        ];
        wp_mail($to, $subject, implode("\n", $lines), ['Reply-To: ' . $name . ' <' . $email . '>']);
    }

    private function rate_limit()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') {
            return;
        }
        $key = 'bw_cc_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= (int) apply_filters('bw_cc_max_per_hour', 10)) {
            wp_die(esc_html__('Too many messages from your network. Please try again later.', 'bw'), '', ['response' => 429]);
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    private function current_url()
    {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return esc_url_raw($scheme . $host . $uri);
    }

    private function redirect_back($status = '')
    {
        $url = !empty($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        if ($status) {
            $url = add_query_arg('bw_cc', $status, remove_query_arg('bw_cc', $url));
        }
        wp_safe_redirect($url);
        exit;
    }

    /* ---------------- Admin: messages list ---------------- */

    public function admin_menu()
    {
        add_menu_page(
            __('Messages', 'bw'),
            __('Messages', 'bw'),
            self::CAP,
            'bw-messages',
            [$this, 'render_admin'],
            'dashicons-email',
            26
        );
    }

    public function render_admin()
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }

        $messages = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'private',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Messages', 'bw'); ?></h1>
            <p><?php esc_html_e('Contact requests and chat messages from the site.', 'bw'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('When', 'bw'); ?></th>
                        <th><?php esc_html_e('Source', 'bw'); ?></th>
                        <th><?php esc_html_e('Name', 'bw'); ?></th>
                        <th><?php esc_html_e('Email', 'bw'); ?></th>
                        <th><?php esc_html_e('Phone', 'bw'); ?></th>
                        <th><?php esc_html_e('Type', 'bw'); ?></th>
                        <th><?php esc_html_e('Message', 'bw'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($messages) : foreach ($messages as $m) : ?>
                        <tr>
                            <td><?php echo esc_html(get_the_date('M j, g:ia', $m)); ?></td>
                            <td><?php echo esc_html(get_post_meta($m->ID, '_bw_source', true)); ?></td>
                            <td><?php echo esc_html(get_post_meta($m->ID, '_bw_name', true)); ?></td>
                            <td><a href="mailto:<?php echo esc_attr(get_post_meta($m->ID, '_bw_email', true)); ?>"><?php echo esc_html(get_post_meta($m->ID, '_bw_email', true)); ?></a></td>
                            <td><?php echo esc_html(get_post_meta($m->ID, '_bw_phone', true) ?: '—'); ?></td>
                            <td><?php echo esc_html(get_post_meta($m->ID, '_bw_type', true)); ?></td>
                            <td><?php echo esc_html(wp_trim_words($m->post_content, 24)); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7"><em><?php esc_html_e('No messages yet.', 'bw'); ?></em></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ---------------- Styles ---------------- */

    private function contact_styles()
    {
        return '<style>
            .bw-cc-contact { max-width: 760px; }
            .bw-cc-ok { background: #e8f7ef; border: 1px solid #9ad5b1; color: #21543b; padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; }
            .bw-cc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
            .bw-cc-form label { display: grid; gap: 6px; margin-bottom: 14px; }
            .bw-cc-form label span { font-weight: 600; }
            .bw-cc-form input, .bw-cc-form select, .bw-cc-form textarea { width: 100%; padding: 11px 13px; border: 1px solid #cbd2dd; border-radius: 10px; box-sizing: border-box; font-size: 16px; }
            .bw-cc-full { display: block; }
            .bw-cc-btn { background: #8d5b2c; color: #fff; border: 0; border-radius: 999px; padding: 12px 26px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; cursor: pointer; }
            .bw-cc-btn:hover { background: #7a4c22; }
            @media (max-width: 640px) { .bw-cc-grid { grid-template-columns: 1fr; } }
            [data-bw-theme="dark"] .bw-cc-form input, [data-bw-theme="dark"] .bw-cc-form select, [data-bw-theme="dark"] .bw-cc-form textarea { background: #1a212d; border-color: #2a3345; color: #e5e9ef; }
        </style>';
    }

    private function widget_styles()
    {
        return '<style>
            .bw-chat { position: fixed; right: 20px; bottom: 78px; z-index: 99980; }
            .bw-chat__launch { display: inline-flex; align-items: center; gap: 8px; background: #8d5b2c; color: #fff; border: 0; border-radius: 999px; padding: 12px 18px; font-weight: 800; letter-spacing: .04em; cursor: pointer; box-shadow: 0 8px 22px rgba(141,91,44,.4); }
            .bw-chat__launch:hover { background: #7a4c22; }
            .bw-chat__launch-icon { font-size: 18px; line-height: 1; }
            .bw-chat__panel { position: absolute; right: 0; bottom: 60px; width: 340px; max-width: calc(100vw - 40px); background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 24px 60px rgba(15,23,42,.28); overflow: hidden; display: flex; flex-direction: column; max-height: 70vh; }
            .bw-chat__head { display: flex; justify-content: space-between; align-items: center; background: #14213d; color: #fff; padding: 12px 14px; }
            .bw-chat__close { background: none; border: 0; color: #fff; font-size: 22px; line-height: 1; cursor: pointer; }
            .bw-chat__body { padding: 14px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
            .bw-chat__msg { padding: 9px 12px; border-radius: 12px; font-size: 14px; line-height: 1.4; max-width: 90%; }
            .bw-chat__msg--bot { background: #f1f5f9; color: #14213d; align-self: flex-start; }
            .bw-chat__msg--user { background: #8d5b2c; color: #fff; align-self: flex-end; }
            .bw-chat__faqs { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
            .bw-chat__faq { text-align: left; background: #fff; border: 1px solid #d6cab4; border-radius: 10px; padding: 8px 11px; cursor: pointer; font-size: 13px; color: #14213d; }
            .bw-chat__faq:hover { background: #fbf6ee; border-color: #8d5b2c; }
            .bw-chat__foot { border-top: 1px solid #eee; padding: 12px 14px; }
            .bw-chat__form { display: grid; gap: 8px; }
            .bw-chat__form input, .bw-chat__form textarea { width: 100%; padding: 9px 11px; border: 1px solid #cbd2dd; border-radius: 9px; box-sizing: border-box; font-size: 14px; }
            .bw-chat__full { display: inline-block; text-align: center; font-size: 12px; color: #8d5b2c; }
            [data-bw-theme="dark"] .bw-chat__panel { background: #141922; border-color: #2a3345; }
            [data-bw-theme="dark"] .bw-chat__msg--bot { background: #1c2330; color: #e5e9ef; }
            [data-bw-theme="dark"] .bw-chat__faq { background: #1a212d; color: #e5e9ef; border-color: #2a3345; }
            [data-bw-theme="dark"] .bw-chat__form input, [data-bw-theme="dark"] .bw-chat__form textarea { background: #1a212d; border-color: #2a3345; color: #e5e9ef; }
            @media (max-width: 480px) { .bw-chat { right: 12px; bottom: 72px; } }
        </style>';
    }
}

new BW_Contact_Chat();
