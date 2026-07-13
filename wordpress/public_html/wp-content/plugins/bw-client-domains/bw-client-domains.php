<?php
/**
 * Plugin Name: BW Client Domains
 * Description: Gives every client a direct link — {client}.bbaprintshop.com 301-redirects to that client's storefront page on the main domain. Everything stays on ONE WordPress/WooCommerce install and ONE domain for browsing/cart/checkout, so a single order can mix products from any client store. Subdomain defaults to the creator's slug; override per-creator with the Subdomain box.
 * Version: 2.0.0
 * Author: Barebones Apparel
 * License: GPL-2.0+
 *
 * One-time hosting setup per subdomain (Hostinger hPanel → Subdomains):
 * - Create the subdomain (e.g. dj-craig) with its document root pointed at the
 *   MAIN site's public_html (custom folder), so requests reach this WordPress.
 * - Issue the free SSL for the new subdomain.
 * DNS is added automatically when the domain uses Hostinger nameservers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Client_Domains
{
    /** Subdomains that must never be treated as client stores. */
    const RESERVED = ['www', 'shop', 'store', 'mail', 'smtp', 'ftp', 'api', 'admin', 'staging', 'dev', 'test', 'cdn', 'cpanel', 'webmail', 'autodiscover'];

    const PM_SUBDOMAIN = '_bw_creator_subdomain';

    public function __construct()
    {
        // Redirect before the preview gate (init 0) and before any output.
        add_action('plugins_loaded', [$this, 'maybe_redirect'], 20);

        // Per-creator subdomain override UI.
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_bw_creator', [$this, 'save_meta']);
    }

    /**
     * The apex/base URL client subdomains hang off. Read from the raw DB
     * `home` option (NOT home_url()) because local dev overrides WP_HOME
     * from the request's Host header, which would make the base equal to
     * the subdomain itself. Override explicitly with BW_CLIENT_BASE_DOMAIN.
     */
    private function base_home()
    {
        if (defined('BW_CLIENT_BASE_DOMAIN') && BW_CLIENT_BASE_DOMAIN) {
            return (string) BW_CLIENT_BASE_DOMAIN;
        }

        global $wpdb;
        static $raw = null;
        if ($raw === null) {
            $raw = (string) $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'home' LIMIT 1");
        }

        return (string) apply_filters('bw_client_domains_base', $raw);
    }

    private function base_domain()
    {
        return (string) wp_parse_url($this->base_home(), PHP_URL_HOST);
    }

    /** Subdomain label from the request host, '' when on the main host. */
    private function request_subdomain()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
        $host = preg_replace('/:\d+$/', '', $host);
        $base = preg_replace('/:\d+$/', '', $this->base_domain());

        if ($host === '' || $base === '' || $host === $base || !str_ends_with($host, '.' . $base)) {
            return '';
        }

        $sub = substr($host, 0, -strlen('.' . $base));
        if ($sub === '' || strpos($sub, '.') !== false || in_array($sub, self::RESERVED, true)) {
            return '';
        }
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $sub)) {
            return '';
        }

        return $sub;
    }

    /**
     * Resolve a subdomain label to a published creator: explicit subdomain
     * meta wins, then the creator's own slug. Direct SQL — this runs at
     * plugins_loaded, before post types are registered.
     */
    private function find_creator($sub)
    {
        global $wpdb;

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = %s
             WHERE p.post_type = 'bw_creator' AND p.post_status = 'publish' LIMIT 1",
            self::PM_SUBDOMAIN,
            $sub
        ));
        if (!$id) {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'bw_creator' AND post_status = 'publish' AND post_name = %s LIMIT 1",
                $sub
            ));
        }

        return $id ? (int) $id : 0;
    }

    /**
     * On a client subdomain:
     *  - root path  -> 301 to the creator's storefront page on the main domain
     *  - deep paths -> 301 to the same path on the main domain (cart, product
     *    links, anything) so sessions and the shared cart live in one place
     *  - unknown subdomain -> 301 to the main homepage
     */
    public function maybe_redirect()
    {
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        $sub = $this->request_subdomain();
        if ($sub === '') {
            return;
        }

        $base = rtrim($this->base_home(), '/');
        $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if ($path !== '/' && $path !== '') {
            $target = $base . ($_SERVER['REQUEST_URI'] ?? '/');
        } else {
            $creator_id = $this->find_creator($sub);
            if ($creator_id) {
                global $wpdb;
                $slug = (string) $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", $creator_id));
                $target = $base . '/creator/' . rawurlencode($slug) . '/';
            } else {
                $target = $base . '/';
            }
        }

        wp_redirect(esc_url_raw($target), 301);
        exit;
    }

    /* ---------- Admin: per-creator subdomain override ---------- */

    public function add_meta_box()
    {
        add_meta_box('bw_client_subdomain', 'Subdomain', [$this, 'render_meta_box'], 'bw_creator', 'side');
    }

    public function render_meta_box($post)
    {
        $value = get_post_meta($post->ID, self::PM_SUBDOMAIN, true);
        $base  = preg_replace('/:\d+$/', '', $this->base_domain()) ?: 'bbaprintshop.com';
        wp_nonce_field('bw_client_subdomain_save', 'bw_client_subdomain_nonce');
        ?>
        <p>
            <input type="text" name="bw_client_subdomain" class="widefat"
                   value="<?php echo esc_attr($value); ?>"
                   placeholder="<?php echo esc_attr($post->post_name); ?>"
                   pattern="[a-z0-9-]{1,80}">
        </p>
        <p class="description">
            <?php echo esc_html(($value ?: $post->post_name) . '.' . $base); ?> → this store.
            Blank = use the slug. Lowercase letters, numbers, dashes.
            Remember to create the subdomain in Hostinger hPanel too.
        </p>
        <?php
    }

    public function save_meta($post_id)
    {
        if (!isset($_POST['bw_client_subdomain_nonce']) || !wp_verify_nonce($_POST['bw_client_subdomain_nonce'], 'bw_client_subdomain_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw = strtolower(sanitize_text_field(wp_unslash($_POST['bw_client_subdomain'] ?? '')));
        if ($raw !== '' && (!preg_match('/^[a-z0-9-]{1,80}$/', $raw) || in_array($raw, self::RESERVED, true))) {
            $raw = '';
        }

        if ($raw === '') {
            delete_post_meta($post_id, self::PM_SUBDOMAIN);
        } else {
            update_post_meta($post_id, self::PM_SUBDOMAIN, $raw);
        }
    }
}

new BW_Client_Domains();
