<?php
/**
 * Plugin Name: BW Dark Mode
 * Description: Site-wide dark mode with a floating toggle. Defaults to the visitor's OS preference, persists their choice, and keeps dark client logos readable on light tile backings.
 * Version: 1.1.0
 * Author: Barebones Apparel
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Dark_Mode
{
    public function __construct()
    {
        add_action('wp_head', [$this, 'no_flash_boot'], 0);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'render_toggle']);
    }

    /**
     * Runs before paint: apply the stored (or OS-preferred) theme so the
     * page never flashes the wrong mode.
     */
    public function no_flash_boot()
    {
        ?>
        <script>
        (function () {
            try {
                var saved = localStorage.getItem('bw-theme');
                var theme = saved === 'dark' || saved === 'light'
                    ? saved
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bw-theme', theme);
            } catch (e) {}
        }());
        </script>
        <?php
    }

    public function enqueue()
    {
        wp_register_style('bw-dark-mode', false, [], '1.0.0');
        wp_enqueue_style('bw-dark-mode');
        wp_add_inline_style('bw-dark-mode', $this->css());

        wp_register_script('bw-dark-mode', '', [], '1.0.0', true);
        wp_enqueue_script('bw-dark-mode');
        wp_add_inline_script('bw-dark-mode', $this->js());
    }

    public function render_toggle()
    {
        ?>
        <button type="button" class="bw-theme-toggle" data-bw-theme-toggle aria-label="<?php esc_attr_e('Toggle dark mode', 'bw'); ?>">
            <span class="bw-theme-toggle__icon bw-theme-toggle__icon--sun" aria-hidden="true">&#9728;</span>
            <span class="bw-theme-toggle__icon bw-theme-toggle__icon--moon" aria-hidden="true">&#9789;</span>
        </button>
        <?php
    }

    private function js()
    {
        return <<<'JS'
(function () {
    var toggle = document.querySelector('[data-bw-theme-toggle]');
    if (!toggle) { return; }
    toggle.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-bw-theme') === 'dark' ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bw-theme', next);
        try { localStorage.setItem('bw-theme', next); } catch (e) {}
    });
}());
JS;
    }

    private function css()
    {
        return <<<'CSS'
/* ---------- Toggle button ---------- */
.bw-theme-toggle {
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 99990;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 1px solid rgba(120, 120, 140, 0.35);
    background: #ffffff;
    color: #1f2a44;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.22);
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease;
}
.bw-theme-toggle:hover { transform: translateY(-2px); }
.bw-theme-toggle__icon--moon { display: inline; }
.bw-theme-toggle__icon--sun { display: none; }
[data-bw-theme="dark"] .bw-theme-toggle { background: #1c2230; color: #f3f4f6; }
[data-bw-theme="dark"] .bw-theme-toggle__icon--moon { display: none; }
[data-bw-theme="dark"] .bw-theme-toggle__icon--sun { display: inline; }

/* ---------- Dark palette ---------- */
[data-bw-theme="dark"] body {
    background-color: #0f1216;
    color: #d7dce3;
}
[data-bw-theme="dark"] body,
[data-bw-theme="dark"] .site-content,
[data-bw-theme="dark"] .ast-container {
    color: #d7dce3;
}
[data-bw-theme="dark"] h1,
[data-bw-theme="dark"] h2,
[data-bw-theme="dark"] h3,
[data-bw-theme="dark"] h4,
[data-bw-theme="dark"] h5,
[data-bw-theme="dark"] h6 {
    color: #f3f4f6;
}
[data-bw-theme="dark"] a:not(.bw-dtf-btn):not(.bwmm-card):not(.bw-gsb-button) { color: #c99a63; }

/* Header bars: dark surface, inverted (black -> white) logo */
[data-bw-theme="dark"] .site-header,
[data-bw-theme="dark"] .ast-primary-header-bar,
[data-bw-theme="dark"] .ast-below-header-bar,
[data-bw-theme="dark"] .ast-above-header-bar,
[data-bw-theme="dark"] .main-header-bar {
    background: #141922 !important;
    border-color: #232b3a;
}
[data-bw-theme="dark"] .custom-logo,
[data-bw-theme="dark"] .ast-logo-title-inline .site-logo-img img {
    filter: invert(1) hue-rotate(180deg);
}

/* Content surfaces / cards */
[data-bw-theme="dark"] .entry-content,
[data-bw-theme="dark"] .wp-block-group,
[data-bw-theme="dark"] .wp-block-columns {
    background-color: transparent;
}
[data-bw-theme="dark"] .wp-block-group.has-background,
[data-bw-theme="dark"] .wp-block-column.has-background,
[data-bw-theme="dark"] .wp-block-cover__inner-container .has-background {
    background-color: #171d28 !important;
    color: #d7dce3 !important;
}

/* Footer (Astra renders each footer row as its own wrap with a white bg) */
[data-bw-theme="dark"] .site-footer,
[data-bw-theme="dark"] .site-above-footer-wrap,
[data-bw-theme="dark"] .site-primary-footer-wrap,
[data-bw-theme="dark"] .site-below-footer-wrap,
[data-bw-theme="dark"] .ast-footer-copyright,
[data-bw-theme="dark"] [data-section="section-footer-builder"] {
    background: #141922 !important;
    color: #aab3bf;
}

/* ---------- Mega menu (CLIENT STORES) ---------- */
[data-bw-theme="dark"] .bw-mm {
    --bw-ink: #e5e9ef;
    --bw-ink-soft: #b3bcc9;
    --bw-line: #2a3345;
    --bw-line-soft: #232b3a;
    --bw-surface: #141922;
    --bw-surface-hover: #1c2330;
    --bw-accent: #c99a63;
}
/* Keep dark client banner artwork readable: light backing behind each logo */
[data-bw-theme="dark"] .bwmm-tile {
    background: #e9ebee;
    border-radius: 10px;
    padding: 8px;
}
[data-bw-theme="dark"] .bwmm-card:hover,
[data-bw-theme="dark"] .bwmm-card:focus-visible {
    box-shadow: 0 12px 26px rgba(0, 0, 0, 0.55);
}

/* Product cards keep their white surface in dark mode, so everything inside
   must stay DARK text — the global dark-mode heading/link colors made titles
   white-on-white. */
[data-bw-theme="dark"] .woocommerce ul.products li.product .woocommerce-loop-product__title,
[data-bw-theme="dark"] ul.products li.product .woocommerce-loop-product__title,
[data-bw-theme="dark"] ul.products li.product .price,
[data-bw-theme="dark"] ul.products li.product .price .amount {
    color: #1f2430 !important;
}
[data-bw-theme="dark"] ul.products li.product .ast-woo-product-category {
    color: #6b7280 !important;
}
[data-bw-theme="dark"] ul.products li.product a.ast-loop-product__link,
[data-bw-theme="dark"] ul.products li.product a.woocommerce-LoopProduct-link {
    color: #1f2430;
}

/* ---------- WooCommerce + forms ---------- */
[data-bw-theme="dark"] input[type="text"],
[data-bw-theme="dark"] input[type="email"],
[data-bw-theme="dark"] input[type="tel"],
[data-bw-theme="dark"] input[type="number"],
[data-bw-theme="dark"] input[type="password"],
[data-bw-theme="dark"] select,
[data-bw-theme="dark"] textarea {
    background-color: #1a212d;
    border-color: #2a3345;
    color: #e5e9ef;
}
[data-bw-theme="dark"] .woocommerce table.shop_table,
[data-bw-theme="dark"] .woocommerce-checkout .woocommerce {
    color: #d7dce3;
}
[data-bw-theme="dark"] .woocommerce table.shop_table td,
[data-bw-theme="dark"] .woocommerce table.shop_table th {
    border-color: #2a3345;
}

/* ---------- Gang sheet builder ---------- */
[data-bw-theme="dark"] .bw-gsb-wrap { color: #d7dce3; }
[data-bw-theme="dark"] .bw-gsb-card {
    background: #171d28;
    border-color: #2a3345;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
    color: #d7dce3;
}
[data-bw-theme="dark"] .bw-gsb-form input[type="text"],
[data-bw-theme="dark"] .bw-gsb-form input[type="email"],
[data-bw-theme="dark"] .bw-gsb-form input[type="file"],
[data-bw-theme="dark"] .bw-gsb-form select,
[data-bw-theme="dark"] .bw-gsb-form textarea {
    background: #1a212d;
    border-color: #2a3345;
    color: #e5e9ef;
}
/* Canvas stays white: it represents the physical print sheet */
[data-bw-theme="dark"] .bw-gsb-canvas { background: #ffffff; }
[data-bw-theme="dark"] .bw-gsb-item-panel { background: #1a212d; border-color: #2a3345; }
[data-bw-theme="dark"] .bw-gsb-tool-button { background: #1c2330; border-color: #2a3345; color: #e5e9ef; }
CSS;
    }
}

new BW_Dark_Mode();
