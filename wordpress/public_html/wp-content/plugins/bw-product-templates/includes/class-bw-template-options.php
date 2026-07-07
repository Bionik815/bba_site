<?php
/**
 * BW Template Options — edit which colors and sizes each product template
 * offers, so the Store Builder only shows the right choices.
 *
 * A template is a hidden variable product whose pa_color / pa_size attribute
 * options ARE the matrix. This page is a friendly checkbox editor for that
 * matrix (e.g. mark Beanie as one-size, or trim a jacket's color list) without
 * touching the raw WooCommerce variable-product screen.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BW_Template_Options
{
    const PAGE_SLUG = 'bw-template-options';
    const ACTION_SAVE = 'bw_to_save';
    const NONCE = 'bw_to_nonce';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handle_save']);
    }

    public function add_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Template Options', 'bw'),
            __('Template Options', 'bw'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    private function get_templates()
    {
        return get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'meta_key' => BW_Product_Templates::META_IS_TEMPLATE,
            'meta_value' => '1',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    /** term_ids currently enabled on the template for a given attribute. */
    private function template_option_ids($product, $taxonomy)
    {
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->is_taxonomy() && $attribute->get_name() === $taxonomy) {
                return array_map('intval', $attribute->get_options());
            }
        }

        return [];
    }

    private function all_colors()
    {
        $terms = get_terms(['taxonomy' => 'pa_color', 'hide_empty' => false, 'orderby' => 'name']);
        return is_wp_error($terms) ? [] : $terms;
    }

    private function all_sizes()
    {
        $terms = get_terms(['taxonomy' => 'pa_size', 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            return [];
        }
        usort($terms, static function ($a, $b) {
            return (int) get_term_meta($a->term_id, 'order', true) <=> (int) get_term_meta($b->term_id, 'order', true);
        });

        return $terms;
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }

        $templates = $this->get_templates();
        $selected_id = absint($_GET['template'] ?? 0);
        $product = $selected_id ? wc_get_product($selected_id) : null;
        if ($product && get_post_meta($selected_id, BW_Product_Templates::META_IS_TEMPLATE, true) !== '1') {
            $product = null;
        }

        $notice = sanitize_text_field(wp_unslash($_GET['bwto'] ?? ''));
        $colors = $this->all_colors();
        $sizes = $this->all_sizes();
        $sel_colors = $product ? $this->template_option_ids($product, 'pa_color') : [];
        $sel_sizes = $product ? $this->template_option_ids($product, 'pa_size') : [];
        $one_size = $product && !$sel_sizes;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Template Options', 'bw'); ?></h1>
            <p><?php esc_html_e('Set which colors and sizes each product template offers. These choices drive what the Store Builder shows.', 'bw'); ?></p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Template options saved.', 'bw'); ?></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('Could not save the template.', 'bw'); ?></p></div>
            <?php endif; ?>

            <form method="get" style="margin:12px 0">
                <input type="hidden" name="post_type" value="product">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                <label><strong><?php esc_html_e('Template', 'bw'); ?></strong>
                    <select name="template" onchange="this.form.submit()" style="min-width:280px">
                        <option value="0"><?php esc_html_e('— Choose a template —', 'bw'); ?></option>
                        <?php foreach ($templates as $tpl) : ?>
                            <option value="<?php echo (int) $tpl->ID; ?>" <?php selected($selected_id, $tpl->ID); ?>><?php echo esc_html($tpl->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>

            <?php if (!$product) : ?>
                <p><em><?php esc_html_e('Pick a template above to edit its colors and sizes.', 'bw'); ?></em></p>
                <?php return; ?>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE); ?>">
                <input type="hidden" name="template" value="<?php echo (int) $selected_id; ?>">

                <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:22px;max-width:1100px">
                    <div class="card" style="padding:16px">
                        <h2><?php esc_html_e('Colors', 'bw'); ?>
                            <button type="button" class="button-link" id="bw-to-all-colors" style="font-size:12px;margin-left:8px"><?php esc_html_e('all', 'bw'); ?></button>/<button type="button" class="button-link" id="bw-to-no-colors" style="font-size:12px"><?php esc_html_e('none', 'bw'); ?></button>
                        </h2>
                        <div class="bw-to-grid">
                            <?php foreach ($colors as $c) : ?>
                                <label><input type="checkbox" class="bw-to-color" name="colors[]" value="<?php echo (int) $c->term_id; ?>" <?php checked(in_array((int) $c->term_id, $sel_colors, true)); ?>> <?php echo esc_html($c->name); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card" style="padding:16px">
                        <h2><?php esc_html_e('Sizes', 'bw'); ?></h2>
                        <p><label><input type="checkbox" id="bw-to-onesize" name="one_size" value="1" <?php checked($one_size); ?>> <strong><?php esc_html_e('One size / accessory (no sizes)', 'bw'); ?></strong></label></p>
                        <div class="bw-to-grid" id="bw-to-sizes" <?php echo $one_size ? 'style="opacity:.4;pointer-events:none"' : ''; ?>>
                            <?php foreach ($sizes as $s) : ?>
                                <label><input type="checkbox" class="bw-to-size" name="sizes[]" value="<?php echo (int) $s->term_id; ?>" <?php checked(in_array((int) $s->term_id, $sel_sizes, true)); ?>> <?php echo esc_html($s->name); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <p style="margin-top:16px"><button type="submit" class="button button-primary"><?php esc_html_e('Save Template Options', 'bw'); ?></button>
                    <span class="description" style="margin-left:8px"><?php esc_html_e('Existing products already built from this template are not changed.', 'bw'); ?></span>
                </p>
            </form>
        </div>

        <style>
            .bw-to-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 3px 12px; max-height: 360px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
            .bw-to-grid label { display: flex; align-items: center; gap: 6px; font-size: 13px; }
        </style>
        <script>
        (function () {
            var one = document.getElementById('bw-to-onesize');
            var sizes = document.getElementById('bw-to-sizes');
            if (one && sizes) {
                one.addEventListener('change', function () {
                    sizes.style.opacity = one.checked ? '.4' : '';
                    sizes.style.pointerEvents = one.checked ? 'none' : '';
                });
            }
            var all = document.getElementById('bw-to-all-colors');
            var none = document.getElementById('bw-to-no-colors');
            function setColors(v) { document.querySelectorAll('.bw-to-color').forEach(function (c) { c.checked = v; }); }
            if (all) { all.addEventListener('click', function () { setColors(true); }); }
            if (none) { none.addEventListener('click', function () { setColors(false); }); }
        }());
        </script>
        <?php
    }

    public function handle_save()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'bw'));
        }
        check_admin_referer(self::NONCE, self::NONCE);

        $template_id = absint($_POST['template'] ?? 0);
        $product = $template_id ? wc_get_product($template_id) : null;
        if (!$product || get_post_meta($template_id, BW_Product_Templates::META_IS_TEMPLATE, true) !== '1') {
            $this->redirect('error', $template_id);
        }

        $color_ids = array_values(array_filter(array_map('absint', (array) ($_POST['colors'] ?? []))));
        $one_size = !empty($_POST['one_size']);
        $size_ids = $one_size ? [] : array_values(array_filter(array_map('absint', (array) ($_POST['sizes'] ?? []))));

        if (!$color_ids) {
            $this->redirect('error', $template_id);
        }

        $attributes = [];

        $color_attr = new WC_Product_Attribute();
        $color_attr->set_id(wc_attribute_taxonomy_id_by_name('color'));
        $color_attr->set_name('pa_color');
        $color_attr->set_options($color_ids);
        $color_attr->set_visible(true);
        $color_attr->set_variation(true);
        $attributes[] = $color_attr;

        if ($size_ids) {
            $size_attr = new WC_Product_Attribute();
            $size_attr->set_id(wc_attribute_taxonomy_id_by_name('size'));
            $size_attr->set_name('pa_size');
            $size_attr->set_options($size_ids);
            $size_attr->set_visible(true);
            $size_attr->set_variation(true);
            $attributes[] = $size_attr;
        }

        $product->set_attributes($attributes);
        $product->save();

        $this->redirect('saved', $template_id);
    }

    private function redirect($status, $template_id)
    {
        wp_safe_redirect(add_query_arg([
            'post_type' => 'product',
            'page' => self::PAGE_SLUG,
            'template' => (int) $template_id,
            'bwto' => $status,
        ], admin_url('edit.php')));
        exit;
    }
}

new BW_Template_Options();
