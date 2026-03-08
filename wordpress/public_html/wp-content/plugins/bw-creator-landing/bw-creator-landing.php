<?php
/**
 * Plugin Name: BW Creator Landing
 * Description: Pretty single page template for Creators (bw_creator). Hero + CTA + optional product grid by WooCommerce category. No theme overrides required.
 * Version: 1.0.0
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

class BW_Creator_Landing {
  const CPT = 'bw_creator';
  const PM_STORE_URL = '_bw_creator_link';          // already set by BW Mega Menu PRO
  const PM_WC_CAT    = '_bw_creator_wc_category';   // e.g. "jacknorthrop"
  const PM_CTA_TXT   = '_bw_creator_cta_text';      // e.g. "Shop Now"
  const PM_TAG       = '_bw_creator_wc_tag';        // optional tag filter

  public function __construct(){
    add_action('add_meta_boxes',        [$this,'meta_box']);
    add_action('save_post_'.self::CPT,  [$this,'save_meta']);
    add_filter('template_include',      [$this,'template']);
    add_action('wp_enqueue_scripts',    [$this,'assets']);
  }

  /* ---------- Meta box ---------- */
  public function meta_box(){
    add_meta_box('bw_creator_landing_box', __('Creator Landing Settings','bw'),
      [$this,'render_meta'], self::CPT, 'normal', 'high');
  }

  public function render_meta($post){
    wp_nonce_field('bw_creator_landing_save','bw_creator_landing_nonce');
    $store = get_post_meta($post->ID, self::PM_STORE_URL, true); // from Mega Menu plugin
    $cta   = get_post_meta($post->ID, self::PM_CTA_TXT, true) ?: 'Shop Now';
    $cat   = get_post_meta($post->ID, self::PM_WC_CAT, true) ?: '';
    $tag   = get_post_meta($post->ID, self::PM_TAG, true) ?: '';
    ?>
    <style>.bwcl-field{margin:10px 0}.bwcl-field input[type=text]{width:100%}</style>
    <p class="bwcl-field"><label><strong><?php esc_html_e('Store URL (from Mega Menu)', 'bw'); ?></strong></label><br>
      <input type="text" value="<?php echo esc_attr($store); ?>" readonly class="regular-text" />
      <em style="opacity:.75;display:block"><?php esc_html_e('Edit this on the Creator sidebar “Store URL”.', 'bw'); ?></em>
    </p>

    <p class="bwcl-field"><label for="bw_creator_cta_text"><strong><?php esc_html_e('CTA Button Text', 'bw'); ?></strong></label><br>
      <input id="bw_creator_cta_text" name="bw_creator_cta_text" type="text" value="<?php echo esc_attr($cta); ?>" placeholder="Shop Now" />
    </p>

    <p class="bwcl-field"><label for="bw_creator_wc_category"><strong><?php esc_html_e('WooCommerce Category Slug', 'bw'); ?></strong></label><br>
      <input id="bw_creator_wc_category" name="bw_creator_wc_category" type="text" value="<?php echo esc_attr($cat); ?>" placeholder="jacknorthrop" />
      <em style="opacity:.75;display:block"><?php esc_html_e('Optional. If set, a product grid will appear on the landing.', 'bw'); ?></em>
    </p>

    <p class="bwcl-field"><label for="bw_creator_wc_tag"><strong><?php esc_html_e('WooCommerce Tag (optional)', 'bw'); ?></strong></label><br>
      <input id="bw_creator_wc_tag" name="bw_creator_wc_tag" type="text" value="<?php echo esc_attr($tag); ?>" placeholder="featured" />
      <em style="opacity:.75;display:block"><?php esc_html_e('Use to narrow products (e.g., featured). Leave blank to show all in the category.', 'bw'); ?></em>
    </p>
    <?php
  }

  public function save_meta($post_id){
    if (!isset($_POST['bw_creator_landing_nonce']) || !wp_verify_nonce($_POST['bw_creator_landing_nonce'], 'bw_creator_landing_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, self::PM_CTA_TXT, sanitize_text_field($_POST['bw_creator_cta_text'] ?? 'Shop Now'));
    update_post_meta($post_id, self::PM_WC_CAT, sanitize_title($_POST['bw_creator_wc_category'] ?? ''));
    update_post_meta($post_id, self::PM_TAG, sanitize_title($_POST['bw_creator_wc_tag'] ?? ''));
  }

  /* ---------- Template loader ---------- */
  public function template($template){
    if (is_singular(self::CPT)) {
      $plugin_tpl = plugin_dir_path(__FILE__) . 'templates/single-bw_creator.php';
      if (file_exists($plugin_tpl)) return $plugin_tpl;
    }
    return $template;
  }

  /* ---------- Front assets ---------- */
  public function assets(){
    if (!is_singular(self::CPT)) return;

    $css = <<<CSS
/* BW Creator Landing */
.bwcl-hero{ --brand:#111; --bg:#0b0f14; --text:#fff; background:var(--bg); color:var(--text); padding:80px 20px; }
.bwcl-inner{ max-width:1200px; margin:0 auto; display:flex; gap:28px; align-items:center; }
.bwcl-logo{ width:160px; min-width:120px; }
.bwcl-logo img{ width:100%; height:auto; display:block; }
.bwcl-copy h1{ margin:0 0 8px; font-size:clamp(28px,4vw,40px); line-height:1.1; }
.bwcl-copy p{ margin:8px 0 18px; font-size:clamp(15px,1.8vw,18px); opacity:.95; }
.bwcl-btn{ display:inline-block; background:var(--brand); color:#fff; text-decoration:none; padding:12px 18px; border-radius:10px; font-weight:800; letter-spacing:.04em; }
.bwcl-btn:hover{ filter:brightness(.92); }

/* Content wrap */
.bwcl-wrap{ max-width:1200px; margin:24px auto; padding:0 20px; }

/* Products header */
.bwcl-grid-head{ display:flex; align-items:center; justify-content:space-between; margin:18px 0 10px; }
.bwcl-grid-head h2{ margin:0; font-size:1.25rem; }

/* Product shortcode spacing */
.bwcl-products{ margin:10px 0 40px; }

/* Responsive */
@media (max-width:900px){
  .bwcl-inner{ flex-direction:column; text-align:center; }
  .bwcl-logo{ width:120px; }
}
CSS;
    wp_register_style('bwcl-css', false, [], '1.0.0');
    wp_add_inline_style('bwcl-css', $css);
    wp_enqueue_style('bwcl-css');
  }
}

new BW_Creator_Landing();
