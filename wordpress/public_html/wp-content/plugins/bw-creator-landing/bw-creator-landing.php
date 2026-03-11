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
  const PM_SUBTITLE  = '_bw_creator_subtitle';
  const PM_BANNER    = '_bw_creator_banner_url';
  const PM_BANNER_MOBILE = '_bw_creator_banner_mobile_url';
  const PM_BRAND     = '_bw_creator_brand_color';
  const PM_BG        = '_bw_creator_bg_color';
  const PM_TEXT      = '_bw_creator_text_color';

  public function __construct(){
    add_action('add_meta_boxes',        [$this,'meta_box']);
    add_action('save_post_'.self::CPT,  [$this,'save_meta']);
    add_filter('template_include',      [$this,'template']);
    add_action('wp_enqueue_scripts',    [$this,'assets']);
    add_action('admin_enqueue_scripts', [$this,'admin_assets']);
    add_action('wp_footer',             [$this,'force_mobile_product_stack'], 999);
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
    $subtitle = get_post_meta($post->ID, self::PM_SUBTITLE, true) ?: 'Official storefront';
    $banner = get_post_meta($post->ID, self::PM_BANNER, true) ?: '';
    $banner_mobile = get_post_meta($post->ID, self::PM_BANNER_MOBILE, true) ?: '';
    $brand = get_post_meta($post->ID, self::PM_BRAND, true) ?: '#111111';
    $bg = get_post_meta($post->ID, self::PM_BG, true) ?: '#0b0f14';
    $text = get_post_meta($post->ID, self::PM_TEXT, true) ?: '#ffffff';
    ?>
    <style>.bwcl-field{margin:10px 0}.bwcl-field input[type=text]{width:100%}</style>
    <p class="bwcl-field"><label><strong><?php esc_html_e('Store URL (from Mega Menu)', 'bw'); ?></strong></label><br>
      <input type="text" value="<?php echo esc_attr($store); ?>" readonly class="regular-text" />
      <em style="opacity:.75;display:block"><?php esc_html_e('Edit this on the Creator sidebar “Store URL”.', 'bw'); ?></em>
    </p>

    <p class="bwcl-field"><label for="bw_creator_cta_text"><strong><?php esc_html_e('CTA Button Text', 'bw'); ?></strong></label><br>
      <input id="bw_creator_cta_text" name="bw_creator_cta_text" type="text" value="<?php echo esc_attr($cta); ?>" placeholder="Shop Now" />
    </p>

    <p class="bwcl-field"><label for="bw_creator_subtitle"><strong><?php esc_html_e('Hero Subtitle', 'bw'); ?></strong></label><br>
      <input id="bw_creator_subtitle" name="bw_creator_subtitle" type="text" value="<?php echo esc_attr($subtitle); ?>" placeholder="Official storefront" />
    </p>

    <p class="bwcl-field"><label for="bw_creator_banner_url"><strong><?php esc_html_e('Banner Image URL', 'bw'); ?></strong></label><br>
      <input id="bw_creator_banner_url" name="bw_creator_banner_url" type="text" value="<?php echo esc_attr($banner); ?>" placeholder="https://..." />
      <em style="opacity:.75;display:block"><?php esc_html_e('Optional. Adds a branded hero background image.', 'bw'); ?></em>
    </p>
    <p class="bwcl-field">
      <button type="button" class="button" id="bwcl_pick_banner"><?php esc_html_e('Choose Banner', 'bw'); ?></button>
      <button type="button" class="button" id="bwcl_clear_banner"><?php esc_html_e('Clear', 'bw'); ?></button>
    </p>
    <div class="bwcl-field" id="bwcl_banner_preview">
      <?php if (!empty($banner)): ?>
        <img src="<?php echo esc_url($banner); ?>" alt="" style="max-width:100%;height:auto;border:1px solid #e5e7eb;border-radius:6px;" />
      <?php endif; ?>
    </div>

    <p class="bwcl-field"><label for="bw_creator_banner_mobile_url"><strong><?php esc_html_e('Mobile Banner Image URL', 'bw'); ?></strong></label><br>
      <input id="bw_creator_banner_mobile_url" name="bw_creator_banner_mobile_url" type="text" value="<?php echo esc_attr($banner_mobile); ?>" placeholder="https://..." />
      <em style="opacity:.75;display:block"><?php esc_html_e('Optional. Used on phones/tablets when set.', 'bw'); ?></em>
    </p>
    <p class="bwcl-field">
      <button type="button" class="button" id="bwcl_pick_mobile_banner"><?php esc_html_e('Choose Mobile Banner', 'bw'); ?></button>
      <button type="button" class="button" id="bwcl_clear_mobile_banner"><?php esc_html_e('Clear', 'bw'); ?></button>
    </p>
    <div class="bwcl-field" id="bwcl_mobile_banner_preview">
      <?php if (!empty($banner_mobile)): ?>
        <img src="<?php echo esc_url($banner_mobile); ?>" alt="" style="max-width:100%;height:auto;border:1px solid #e5e7eb;border-radius:6px;" />
      <?php endif; ?>
    </div>

    <script>
      (function($){
        const field = $('#bw_creator_banner_url');
        const preview = $('#bwcl_banner_preview');
        const mobileField = $('#bw_creator_banner_mobile_url');
        const mobilePreview = $('#bwcl_mobile_banner_preview');
        $('#bwcl_pick_banner').on('click', function(e){
          e.preventDefault();
          const frame = wp.media({
            title: 'Select Banner Image',
            multiple: false,
            library: { type: 'image' }
          });
          frame.on('select', function(){
            const att = frame.state().get('selection').first().toJSON();
            field.val(att.url);
            preview.html('<img src="'+att.url+'" alt="" style="max-width:100%;height:auto;border:1px solid #e5e7eb;border-radius:6px;" />');
          });
          frame.open();
        });
        $('#bwcl_clear_banner').on('click', function(e){
          e.preventDefault();
          field.val('');
          preview.html('');
        });

        $('#bwcl_pick_mobile_banner').on('click', function(e){
          e.preventDefault();
          const frame = wp.media({
            title: 'Select Mobile Banner Image',
            multiple: false,
            library: { type: 'image' }
          });
          frame.on('select', function(){
            const att = frame.state().get('selection').first().toJSON();
            mobileField.val(att.url);
            mobilePreview.html('<img src="'+att.url+'" alt="" style="max-width:100%;height:auto;border:1px solid #e5e7eb;border-radius:6px;" />');
          });
          frame.open();
        });
        $('#bwcl_clear_mobile_banner').on('click', function(e){
          e.preventDefault();
          mobileField.val('');
          mobilePreview.html('');
        });
      })(jQuery);
    </script>

    <p class="bwcl-field"><label for="bw_creator_brand_color"><strong><?php esc_html_e('Brand Color', 'bw'); ?></strong></label><br>
      <input id="bw_creator_brand_color" name="bw_creator_brand_color" type="text" value="<?php echo esc_attr($brand); ?>" placeholder="#111111" />
    </p>

    <p class="bwcl-field"><label for="bw_creator_bg_color"><strong><?php esc_html_e('Hero Background Color', 'bw'); ?></strong></label><br>
      <input id="bw_creator_bg_color" name="bw_creator_bg_color" type="text" value="<?php echo esc_attr($bg); ?>" placeholder="#0b0f14" />
    </p>

    <p class="bwcl-field"><label for="bw_creator_text_color"><strong><?php esc_html_e('Hero Text Color', 'bw'); ?></strong></label><br>
      <input id="bw_creator_text_color" name="bw_creator_text_color" type="text" value="<?php echo esc_attr($text); ?>" placeholder="#ffffff" />
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
    $brand = sanitize_hex_color($_POST['bw_creator_brand_color'] ?? '') ?: '#111111';
    $bg = sanitize_hex_color($_POST['bw_creator_bg_color'] ?? '') ?: '#0b0f14';
    $text = sanitize_hex_color($_POST['bw_creator_text_color'] ?? '') ?: '#ffffff';

    update_post_meta($post_id, self::PM_CTA_TXT, sanitize_text_field($_POST['bw_creator_cta_text'] ?? 'Shop Now'));
    update_post_meta($post_id, self::PM_SUBTITLE, sanitize_text_field($_POST['bw_creator_subtitle'] ?? 'Official storefront'));
    update_post_meta($post_id, self::PM_BANNER, esc_url_raw($_POST['bw_creator_banner_url'] ?? ''));
    update_post_meta($post_id, self::PM_BANNER_MOBILE, esc_url_raw($_POST['bw_creator_banner_mobile_url'] ?? ''));
    update_post_meta($post_id, self::PM_BRAND, $brand);
    update_post_meta($post_id, self::PM_BG, $bg);
    update_post_meta($post_id, self::PM_TEXT, $text);
    update_post_meta($post_id, self::PM_WC_CAT, sanitize_title($_POST['bw_creator_wc_category'] ?? ''));
    update_post_meta($post_id, self::PM_TAG, sanitize_title($_POST['bw_creator_wc_tag'] ?? ''));
  }

  public function admin_assets($hook){
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== self::CPT) return;
    wp_enqueue_media();
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
    $css = <<<CSS
/* BW Creator Landing */
.bwcl-hero{ --brand:#111; --bg:#0b0f14; --text:#fff; background:var(--bg); color:var(--text); padding:80px 20px; position:relative; overflow:hidden; }
.bwcl-hero.has-banner::before{content:'';position:absolute;inset:0;background-image:var(--banner-image);background-size:cover;background-position:center;opacity:.32}
.bwcl-hero::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.28),rgba(0,0,0,.56))}
.bwcl-inner{ max-width:1200px; margin:0 auto; display:flex; gap:28px; align-items:center; }
.bwcl-inner{ position:relative; z-index:1; }
.bwcl-logo{ width:160px; min-width:120px; }
.bwcl-logo img{ width:100%; height:auto; display:block; border-radius:14px; box-shadow:0 12px 28px rgba(0,0,0,.25); background:#fff; padding:8px; }
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

/* Product page polish */
body.single-product div.product{display:block}
body.single-product div.product .summary{margin-top:0;padding:16px;border:1px solid #ececf1;border-radius:16px;background:#fff}
body.single-product div.product .summary > *:first-child{margin-top:0}
body.single-product div.product .product_title{line-height:1.15;letter-spacing:-.01em}
body.single-product div.product p.price{font-size:clamp(24px,2vw,32px);font-weight:800;margin:0 0 10px}
body.single-product .woocommerce-product-details__short-description p{line-height:1.58}
body.single-product .product_meta{padding-top:12px;border-top:1px solid #ececf1;font-size:13px}
body.single-product div.product .summary .quantity .qty{min-height:44px;border-radius:10px}
body.single-product div.product form.cart .button{min-height:44px;border-radius:10px}
body.single-product div.product table.variations{margin:12px 0 8px;border-collapse:separate;border-spacing:0 8px}
body.single-product div.product table.variations th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#616174;padding-right:12px}
body.single-product div.product table.variations td select{min-height:42px;border-radius:10px;border:1px solid #d9d9e2;background:#fff}
body.single-product .single_variation_wrap .woocommerce-variation-price{margin:8px 0 10px}
body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
body.single-product .fm-ct-wrap .fm-ct-select,
body.single-product .fm-vinyl-upgrade{background:#fafafc!important;border:1px solid #e6e7ef!important;border-radius:12px!important}
body.single-product .fm-ct-wrap .fm-ct-fields input{border:1px solid #d7d9e5!important;border-radius:10px!important;min-height:42px}
body.single-product #fm-price-wrap{padding:10px 12px;border:1px dashed #d6d9eb;border-radius:12px;background:#f7f9ff}
body.single-product div.product .woocommerce-tabs{margin-top:24px}
body.single-product div.product .woocommerce-tabs ul.tabs{display:flex;gap:6px;flex-wrap:wrap}
body.single-product div.product .woocommerce-tabs ul.tabs li{margin:0!important;border:none!important}
body.single-product div.product .woocommerce-tabs ul.tabs li a{padding:10px 14px;border:1px solid #dfe2ef;border-radius:999px;background:#fff}
body.single-product div.product .woocommerce-tabs ul.tabs li.active a{background:#111;color:#fff;border-color:#111}
body.single-product .related.products{margin-top:24px}

@media (max-width:921px){
  body.single-product .site-content,
  body.single-product .ast-container,
  body.single-product #content{
    overflow-x:hidden;
  }
  body.single-product .woocommerce,
  body.single-product .woocommerce-page{
    overflow-x:hidden;
  }
  body.single-product .woocommerce div.product,
  body.single-product .woocommerce-page div.product{
    display:block!important;
    width:100%!important;
    max-width:100%!important;
  }
  body.single-product div.product{
    display:block!important;
    width:100%!important;
    max-width:100%!important;
  }
  body.single-product .woocommerce div.product div.images,
  body.single-product .woocommerce-page div.product div.images,
  body.single-product .woocommerce div.product div.summary,
  body.single-product .woocommerce-page div.product div.summary{
    float:none!important;
    width:100%!important;
    max-width:100%!important;
    margin-right:0!important;
    margin-left:0!important;
    clear:both;
    min-width:0!important;
    box-sizing:border-box;
  }
  body.single-product div.product div.images,
  body.single-product div.product div.summary{
    float:none!important;
    width:100%!important;
    max-width:100%!important;
    margin-right:0!important;
    margin-left:0!important;
    clear:both;
    min-width:0!important;
    box-sizing:border-box;
  }
  body.single-product .woocommerce div.product div.summary{
    position:relative!important;
    left:0!important;
    right:0!important;
    transform:none!important;
    overflow-x:hidden!important;
  }
  body.single-product div.product div.summary{
    position:relative!important;
    left:0!important;
    right:0!important;
    transform:none!important;
    overflow-x:hidden!important;
  }
  body.single-product .woocommerce div.product div.images .woocommerce-product-gallery__wrapper{
    max-width:100%!important;
  }
  body.single-product .woocommerce div.product div.images img{
    width:100%!important;
    height:auto!important;
  }
  body.single-product .woocommerce-product-gallery{
    width:100%!important;
    padding:0!important;
    margin:0!important;
    overflow:visible!important;
    background:transparent!important;
    border-radius:0!important;
  }
  body.single-product .woocommerce-product-gallery--with-images,
  body.single-product .woocommerce-product-gallery--columns-4,
  body.single-product .woocommerce-product-gallery.images{
    display:block!important;
    position:relative!important;
  }
  body.single-product .woocommerce-product-gallery .flexslider{
    display:block!important;
    margin:0!important;
    border:0!important;
    background:transparent!important;
  }
  body.single-product .woocommerce-product-gallery .flex-viewport{
    width:100%!important;
    max-width:100%!important;
    float:none!important;
    margin:0 0 10px 0!important;
    padding:0!important;
    overflow:hidden!important;
    border-radius:0!important;
    background:transparent!important;
  }
  body.single-product .woocommerce-product-gallery .woocommerce-product-gallery__wrapper{
    width:100%!important;
    max-width:100%!important;
    margin:0!important;
    padding:0!important;
    background:transparent!important;
  }
  body.single-product .woocommerce-product-gallery .flex-control-thumbs{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px;
    width:100%!important;
    max-width:100%!important;
    float:none!important;
    clear:both!important;
    position:static!important;
    margin:0!important;
    padding:0!important;
    background:transparent!important;
  }
  body.single-product .woocommerce-product-gallery .flex-control-nav{
    width:100%!important;
    max-width:100%!important;
    position:static!important;
    right:auto!important;
    left:auto!important;
    top:auto!important;
    bottom:auto!important;
    transform:none!important;
    float:none!important;
    clear:both!important;
    margin:0!important;
    padding:0!important;
    background:transparent!important;
  }
  body.single-product .woocommerce-product-gallery .flex-control-thumbs li{
    width:100%!important;
    float:none!important;
    margin:0!important;
    background:transparent!important;
  }
  body.single-product div.product{grid-template-columns:1fr;gap:12px}
  body.single-product div.product .summary{
    padding:6px 0 0;
    border:0;
    border-radius:0;
    background:transparent;
    box-shadow:none;
  }
  body.single-product div.product .product_title{
    font-size:56px;
    line-height:1.06;
    letter-spacing:-0.02em;
    margin:8px 0 4px;
    color:#1c2c5a;
  }
  body.single-product div.product p.price{
    font-size:18px;
    font-weight:700;
    color:#1c2c5a;
    margin:0 0 14px;
  }
  body.single-product div.product form.cart{display:flex;flex-direction:column;gap:12px}
  body.single-product div.product form.cart .quantity{width:100%;margin:0}
  body.single-product div.product form.cart .quantity .qty{width:100%}
  body.single-product div.product form.cart .button{
    width:100%;
    border-radius:10px;
    min-height:46px;
    background:#2f5ecb;
    color:#fff;
    font-weight:700;
  }
  body.single-product div.product .woocommerce-product-gallery{
    padding:0 0 8px;
    border:0;
    border-radius:0;
    background:transparent!important;
  }
  body.single-product div.product .woocommerce-product-gallery__wrapper,
  body.single-product div.product .flex-viewport,
  body.single-product div.product .woocommerce-product-gallery__image,
  body.single-product div.product .woocommerce-product-gallery__image a{
    background:transparent!important;
  }
  /* Elementor wrapper around product gallery can carry the faint panel background */
  body.single-product div.product .elementor-widget-woocommerce-product-images,
  body.single-product div.product .elementor-widget-woocommerce-product-images > .elementor-widget-container,
  body.single-product div.product .elementor-widget-woocommerce-product-images .woocommerce-product-gallery,
  body.single-product div.product .elementor-widget-woocommerce-product-images .woocommerce-product-gallery--with-images{
    background:transparent!important;
    border:0!important;
    border-radius:0!important;
    box-shadow:none!important;
    padding:0!important;
  }
  body.single-product div.product div.images,
  body.single-product div.product div.images::before,
  body.single-product div.product div.images::after,
  body.single-product div.product .woocommerce-product-gallery::before,
  body.single-product div.product .woocommerce-product-gallery::after{
    background:transparent!important;
    box-shadow:none!important;
    border:0!important;
    content:none!important;
  }
  body.single-product div.product .woocommerce-product-gallery__image{
    border-radius:0;
    overflow:hidden;
    background:transparent;
  }
  body.single-product div.product .woocommerce-product-gallery__image a{
    display:flex;
    justify-content:center;
    align-items:center;
  }
  body.single-product div.product .woocommerce-product-gallery__image img{
    display:block;
    margin:0 auto;
    object-fit:contain;
    object-position:center center;
  }
  body.single-product div.product .woocommerce-product-gallery__trigger{
    top:8px!important;
    right:8px!important;
  }
  body.single-product div.product table.variations{
    margin:8px 0 4px;
    border-spacing:0 10px;
  }
  body.single-product div.product table.variations td,
  body.single-product div.product table.variations th{display:block;width:100%;padding:2px 0}
  body.single-product div.product table.variations th{
    font-size:12px;
    color:#2f3f68;
    letter-spacing:.05em;
    text-transform:uppercase;
  }
  body.single-product div.product table.variations tr{display:block;margin-bottom:10px}
  body.single-product div.product table.variations td select{
    width:100%;
    min-height:44px;
    border:1px solid #b7bfd1;
    border-radius:8px;
    background:#fff;
    padding:0 12px;
  }
  body.single-product div.product table.variations td.value,
  body.single-product div.product table.variations td.value > *{
    max-width:100%!important;
    min-width:0!important;
    box-sizing:border-box;
  }
  body.single-product div.product form.variations_form,
  body.single-product div.product .single_variation_wrap,
  body.single-product div.product .summary .fm-ct-wrap,
  body.single-product div.product .summary .fm-vinyl-upgrade,
  body.single-product div.product .summary #fm-price-wrap{
    max-width:100%!important;
    min-width:0!important;
    width:100%!important;
    box-sizing:border-box;
  }
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart .quantity,
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart .button{width:100%!important}
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart .single_add_to_cart_button,
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart button.single_add_to_cart_button.button{
    display:block!important;
    width:100%!important;
    max-width:100%!important;
    margin:0!important;
    left:0!important;
    right:0!important;
    align-self:stretch!important;
    box-sizing:border-box!important;
  }
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart{
    display:grid!important;
    grid-template-columns:1fr;
    gap:10px;
    align-items:stretch;
  }
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart .quantity{
    margin:0!important;
    width:100%!important;
  }
  body.single-product .single_variation_wrap .woocommerce-variation-add-to-cart .quantity .qty{
    width:100%!important;
    min-height:50px;
    border:1px solid #9daccc;
    border-radius:8px;
    text-align:center;
    font-size:32px;
    background:#fff;
    color:#243552;
    box-sizing:border-box;
  }
  body.single-product #fm-price-wrap{
    width:100%!important;
    margin:0 0 10px;
    padding:12px 14px;
    min-height:50px;
    border:1px solid #9daccc;
    border-radius:8px;
    background:#fff;
    color:#243552;
    box-sizing:border-box;
    display:flex;
    flex-wrap:wrap;
    align-items:baseline;
    column-gap:4px;
    row-gap:2px;
    justify-content:flex-start;
  }
  body.single-product #fm-price-wrap > *{
    margin:0!important;
    line-height:1.15;
  }
  body.single-product #fm-price-wrap > :first-child{
    font-size:17px;
    font-weight:700;
    color:#30425f;
    white-space:nowrap;
  }
  body.single-product #fm-price-wrap > :nth-child(2){
    font-size:56px;
    font-weight:800;
    color:#243552;
    letter-spacing:-0.01em;
    white-space:nowrap;
  }
  body.single-product #fm-price-wrap > :nth-child(n+3){
    flex-basis:100%;
    margin-top:2px!important;
    font-size:12px;
    line-height:1.35;
    color:#76839e;
  }
  body.single-product #fm-price-wrap small,
  body.single-product #fm-price-wrap em,
  body.single-product #fm-price-wrap [class*="note"],
  body.single-product #fm-price-wrap [class*="adjust"],
  body.single-product #fm-price-wrap [class*="include"]{
    flex-basis:100%;
    margin-top:2px!important;
    font-size:12px!important;
    line-height:1.35!important;
    color:#76839e!important;
  }
  body.single-product .bwsg-inline{
    margin-top:8px!important;
  }
  body.single-product .related.products{
    margin-top:30px;
  }
  body.single-product .related.products > h2,
  body.single-product .up-sells > h2{
    font-size:40px;
    color:#1c2c5a;
    margin-bottom:12px;
  }
  body.single-product .related.products ul.products li.product{width:100%!important}
  body.single-product div.product .elementor,
  body.single-product div.product .elementor-section,
  body.single-product div.product .elementor-container,
  body.single-product div.product .e-con,
  body.single-product div.product .elementor-element.e-con,
  body.single-product div.product .e-con > .elementor-element{
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    box-sizing:border-box!important;
    flex-basis:100%!important;
  }
  body.single-product div.product .e-con.e-flex,
  body.single-product div.product .e-con.e-parent{
    flex-direction:column!important;
    flex-wrap:nowrap!important;
    align-items:stretch!important;
    justify-content:flex-start!important;
    gap:14px!important;
  }
  body.single-product div.product [class*="elementor-widget-woocommerce-product-"],
  body.single-product div.product [class*="elementor-widget-woocommerce-"]{
    width:100%!important;
    max-width:100%!important;
  }
}

/* Responsive */
@media (max-width:900px){
  .bwcl-hero.has-mobile-banner::before{background-image:var(--banner-image-mobile)}
  .bwcl-inner{ flex-direction:column; text-align:center; }
  .bwcl-logo{ width:120px; }
}
CSS;
    $js = <<<JS
(function(){
  function forceSingleColumnProduct(){
    if (!window.matchMedia('(max-width: 921px)').matches) return;
    var product = document.querySelector('body.single-product div.product');
    if (!product) return;

    product.style.setProperty('display', 'block', 'important');
    product.style.setProperty('grid-template-columns', '1fr', 'important');
    product.style.setProperty('width', '100%', 'important');
    product.style.setProperty('max-width', '100%', 'important');
    product.style.setProperty('overflow-x', 'hidden', 'important');

    var sections = product.querySelectorAll('div.images, div.summary');
    sections.forEach(function(el){
      el.style.setProperty('float', 'none', 'important');
      el.style.setProperty('position', 'static', 'important');
      el.style.setProperty('transform', 'none', 'important');
      el.style.setProperty('width', '100%', 'important');
      el.style.setProperty('max-width', '100%', 'important');
      el.style.setProperty('min-width', '0', 'important');
      el.style.setProperty('margin-left', '0', 'important');
      el.style.setProperty('margin-right', '0', 'important');
      el.style.setProperty('box-sizing', 'border-box', 'important');
      el.style.setProperty('overflow-x', 'hidden', 'important');
      el.style.setProperty('clear', 'both', 'important');
      el.style.setProperty('flex', '0 0 100%', 'important');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', forceSingleColumnProduct);
  } else {
    forceSingleColumnProduct();
  }
  window.addEventListener('resize', forceSingleColumnProduct);
})();
JS;
    if (!is_singular(self::CPT) && !function_exists('is_product')) return;
    if (!is_singular(self::CPT) && !is_product()) return;

    wp_register_style('bwcl-css', false, [], '1.1.1');
    wp_add_inline_style('bwcl-css', $css);
    wp_enqueue_style('bwcl-css');
    wp_add_inline_script('jquery-core', $js, 'after');
  }

  public function force_mobile_product_stack(){
    if (!function_exists('is_product') || !is_product()) return;
    ?>
    <script id="bwcl-mobile-force-stack">
    (function(){
      function pickSummary(product){
        return product.querySelector('div.summary.entry-summary, div.entry-summary, div.summary, .woocommerce-product-details__short-description');
      }
      function pickImages(product){
        return product.querySelector('div.images, .woocommerce-product-gallery, [class*="gallery"]');
      }

      function apply(){
        var product = document.querySelector('body.single-product div.product');
        if (!product) return;

        if (!window.matchMedia('(max-width: 921px)').matches) return;

        product.style.setProperty('display', 'block', 'important');
        product.style.setProperty('grid-template-columns', '1fr', 'important');
        product.style.setProperty('width', '100%', 'important');
        product.style.setProperty('max-width', '100%', 'important');
        product.style.setProperty('overflow-x', 'hidden', 'important');

        var images = pickImages(product);
        var summary = pickSummary(product);

        // If we accidentally matched the size-guide <summary>, discard it.
        if (summary && summary.tagName && summary.tagName.toLowerCase() === 'summary') {
          summary = null;
        }

        // Fallback: if standard wrappers aren't found, force first two children to stack.
        if (!images || !summary) {
          var kids = Array.prototype.filter.call(product.children || [], function(el){ return el && el.nodeType === 1; });
          if (!images && kids[0]) images = kids[0];
          if (!summary) {
            var candidate = product.querySelector('div.entry-summary, div.summary, [class~="entry-summary"]');
            summary = candidate || kids[1] || null;
          }
        }

        [images, summary].forEach(function(el){
          if (!el) return;
          if (el.tagName && el.tagName.toLowerCase() === 'summary') return;
          el.style.setProperty('display', 'block', 'important');
          el.style.setProperty('float', 'none', 'important');
          el.style.setProperty('position', 'relative', 'important');
          el.style.setProperty('left', '0', 'important');
          el.style.setProperty('right', '0', 'important');
          el.style.setProperty('transform', 'none', 'important');
          el.style.setProperty('width', '100%', 'important');
          el.style.setProperty('max-width', '100%', 'important');
          el.style.setProperty('min-width', '0', 'important');
          el.style.setProperty('margin-left', '0', 'important');
          el.style.setProperty('margin-right', '0', 'important');
          el.style.setProperty('box-sizing', 'border-box', 'important');
          el.style.setProperty('clear', 'both', 'important');
          el.style.setProperty('overflow-x', 'hidden', 'important');
          el.style.setProperty('flex', '0 0 100%', 'important');
        });

        // Clear panel backgrounds from gallery wrapper ancestry (Elementor/Woo wrappers).
        if (images) {
          var node = images;
          while (node && node !== product) {
            node.style.setProperty('background', 'transparent', 'important');
            node.style.setProperty('background-color', 'transparent', 'important');
            node.style.setProperty('border', '0', 'important');
            node.style.setProperty('box-shadow', 'none', 'important');
            node.style.setProperty('border-radius', '0', 'important');
            node = node.parentElement;
          }
        }

        var econ = product.querySelectorAll('.e-con, .elementor-element.e-con, .elementor-container, .elementor-section');
        econ.forEach(function(el){
          el.style.setProperty('width', '100%', 'important');
          el.style.setProperty('max-width', '100%', 'important');
          el.style.setProperty('min-width', '0', 'important');
          el.style.setProperty('box-sizing', 'border-box', 'important');
          el.style.setProperty('flex-basis', '100%', 'important');
          if (el.classList.contains('e-flex') || el.classList.contains('e-parent')) {
            el.style.setProperty('flex-direction', 'column', 'important');
            el.style.setProperty('flex-wrap', 'nowrap', 'important');
            el.style.setProperty('align-items', 'stretch', 'important');
            el.style.setProperty('justify-content', 'flex-start', 'important');
          }
        });

      }

      apply();
      setTimeout(apply, 200);
      setTimeout(apply, 800);
      window.addEventListener('resize', apply);
    })();
    </script>
    <?php
  }
}

new BW_Creator_Landing();
