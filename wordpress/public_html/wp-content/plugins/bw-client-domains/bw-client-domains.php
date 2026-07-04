<?php
/**
 * Plugin Name: BW Client Domains
 * Description: Serves each client storefront at {client}.barebones-apparel.com from this single WordPress/WooCommerce install. The subdomain maps to the matching Creator landing page; all other links (products, cart, checkout) stay on the main domain so every customer shares one cart across all client stores.
 * Version: 1.0.0
 * Author: Barebones Apparel
 * License: GPL-2.0+
 *
 * Production requirements (one-time, outside this plugin):
 * - Wildcard DNS record: *.barebones-apparel.com -> same server as the main site
 * - Hostinger: add wildcard subdomain (*.barebones-apparel.com) pointing at this webroot
 * - Wildcard SSL covering *.barebones-apparel.com
 * - In wp-config.php: define('COOKIE_DOMAIN', ''); must NOT be set to a fixed host,
 *   and for a shared cart across subdomains WooCommerce needs nothing extra because
 *   cart/checkout links resolve to the main domain (home_url) — customers land back
 *   on barebones-apparel.com to buy, keeping one session/cart for all stores.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Client_Domains
{
    /** Subdomains that must never be treated as client stores. */
    const RESERVED = ['www', 'shop', 'store', 'mail', 'smtp', 'ftp', 'api', 'admin', 'staging', 'dev', 'test', 'cdn', 'cpanel', 'webmail', 'autodiscover'];

    private $client_slug = '';

    public function __construct()
    {
        add_filter('request', [$this, 'map_subdomain_request']);
        add_action('parse_request', [$this, 'maybe_disable_canonical']);
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

    /**
     * Extract the client slug from the request host, or '' when the request
     * is for the main site (or a reserved/unknown subdomain).
     */
    private function detect_client_slug()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
        $host = preg_replace('/:\d+$/', '', $host);
        $base = preg_replace('/:\d+$/', '', $this->base_domain());

        if ($host === '' || $host === $base || !str_ends_with($host, '.' . $base)) {
            return '';
        }

        $sub = substr($host, 0, -strlen('.' . $base));
        if ($sub === '' || strpos($sub, '.') !== false || in_array($sub, self::RESERVED, true)) {
            return '';
        }

        if (!preg_match('/^[a-z0-9-]{1,80}$/', $sub)) {
            return '';
        }

        // Only map when a published creator with this slug exists.
        $creator = get_page_by_path($sub, OBJECT, 'bw_creator');
        if (!$creator || $creator->post_status !== 'publish') {
            return '';
        }

        return $sub;
    }

    /**
     * Root request on a client subdomain -> serve that creator's landing
     * page. Any deeper path on the subdomain (cart, products, ...) is
     * redirected to the same path on the main domain so checkout and
     * sessions always live in one place.
     */
    public function map_subdomain_request($query_vars)
    {
        $this->client_slug = $this->detect_client_slug();
        if ($this->client_slug === '') {
            return $query_vars;
        }

        $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if ($path !== '/' && $path !== '') {
            // Deep paths (cart, checkout, products, ...) always live on the
            // main domain so every store shares one session and cart.
            $target = rtrim($this->base_home(), '/') . ($_SERVER['REQUEST_URI'] ?? '/');
            wp_redirect(esc_url_raw($target), 301);
            exit;
        }

        return [
            'post_type' => 'bw_creator',
            'bw_creator' => $this->client_slug,
            'name' => $this->client_slug,
        ];
    }

    /**
     * Stop WordPress canonical redirect from bouncing the subdomain root
     * over to /creator/{slug}/ on the main domain.
     */
    public function maybe_disable_canonical()
    {
        if ($this->client_slug !== '') {
            remove_action('template_redirect', 'redirect_canonical');
        }
    }
}

new BW_Client_Domains();
