<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Single template for bw_creator (Creator landing)
 * Full-bleed branded hero: uses the Banner Image when set, otherwise falls
 * back to a blurred blow-up of the creator's logo so every store page feels
 * branded with zero setup. Brand color themes the page's buttons/accents.
 */

if (!function_exists('bwcl_contrast_color')) {
  /** White or near-black, whichever reads better on the given hex. */
  function bwcl_contrast_color($hex) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) $hex = preg_replace('/(.)/', '$1$1', $hex);
    if (strlen($hex) !== 6) return '#ffffff';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#111111' : '#ffffff';
  }
}

get_header();

$creator_id = get_the_ID();
$name       = get_the_title();
$logo       = get_the_post_thumbnail($creator_id, 'large', ['alt'=>$name]) ?: '';
$logo_url   = get_the_post_thumbnail_url($creator_id, 'full') ?: '';
$store_url  = get_post_meta($creator_id, '_bw_creator_link', true);
// Never send shoppers off-site from their own storefront: the CTA and
// "Shop All" prefer this client's full on-site catalog (category archive).
$wc_cat_for_link = get_post_meta($creator_id, '_bw_creator_wc_category', true);
$catalog_url = '';
if ($wc_cat_for_link) {
  $cat_term = get_term_by('slug', sanitize_title($wc_cat_for_link), 'product_cat');
  if ($cat_term && !is_wp_error($cat_term)) {
    $link = get_term_link($cat_term);
    if (!is_wp_error($link)) $catalog_url = $link;
  }
}
$store_url = $catalog_url ?: $store_url;
$cta_text   = get_post_meta($creator_id, '_bw_creator_cta_text', true) ?: 'Shop Now';
$wc_cat     = get_post_meta($creator_id, '_bw_creator_wc_category', true);
$wc_tag     = get_post_meta($creator_id, '_bw_creator_wc_tag', true);
$subtitle   = get_post_meta($creator_id, '_bw_creator_subtitle', true) ?: 'Official storefront';
$banner     = get_post_meta($creator_id, '_bw_creator_banner_url', true);
$banner_mobile = get_post_meta($creator_id, '_bw_creator_banner_mobile_url', true);
$brand      = get_post_meta($creator_id, '_bw_creator_brand_color', true) ?: '#c99a63';
$bg         = get_post_meta($creator_id, '_bw_creator_bg_color', true) ?: '#0b0f14';
$text       = get_post_meta($creator_id, '_bw_creator_text_color', true) ?: '#ffffff';
$btn_text   = bwcl_contrast_color($brand);

$hero_classes = 'bwcl-hero';
$hero_style   = '--brand:' . esc_attr($brand) . ';--bg:' . esc_attr($bg) . ';--text:' . esc_attr($text) . ';';

if (!empty($banner)) {
  $hero_classes .= ' has-banner';
  $hero_style   .= '--banner-image:url("' . esc_url($banner) . '");';
} elseif (!empty($logo_url)) {
  // No banner uploaded: blow the logo up as a soft blurred backdrop.
  $hero_classes .= ' has-banner is-logo-bg';
  $hero_style   .= '--banner-image:url("' . esc_url($logo_url) . '");';
}
if (!empty($banner_mobile)) {
  $hero_classes .= ' has-mobile-banner';
  $hero_style   .= '--banner-image-mobile:url("' . esc_url($banner_mobile) . '");';
}
?>
<style>
/* Per-client theming (colors come from the Creator Landing Settings box) */
body.single-bw_creator{
  --bwcl-brand: <?php echo esc_html($brand); ?>;
  --bwcl-btn-text: <?php echo esc_html($btn_text); ?>;
}
</style>

<main id="primary" class="site-main bwcl-page">

  <section class="<?php echo esc_attr($hero_classes); ?>" style="<?php echo esc_attr($hero_style); ?>">
    <div class="bwcl-inner">
      <?php if ($logo): ?>
        <div class="bwcl-logo"><?php echo $logo; ?></div>
      <?php endif; ?>
      <div class="bwcl-copy">
        <h1><?php echo esc_html($name); ?></h1>
        <p><?php echo esc_html($subtitle); ?></p>
        <?php if ($store_url): ?>
          <a class="bwcl-btn" href="<?php echo esc_url($store_url); ?>">
            <?php echo esc_html($cta_text); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="bwcl-wrap">
    <?php
      // Output the content (optional – in case you want a description per creator)
      while (have_posts()): the_post();
        if (get_the_content()) {
          echo '<div class="bwcl-content">';
          the_content();
          echo '</div>';
        }
      endwhile;
    ?>

    <?php if ($wc_cat): ?>
      <div class="bwcl-grid-head">
        <h2><?php echo esc_html__('Featured Products', 'bw'); ?></h2>
        <?php if ($store_url): ?>
          <a class="bwcl-btn" href="<?php echo esc_url($store_url); ?>"><?php echo esc_html__('Shop All', 'bw'); ?></a>
        <?php endif; ?>
      </div>

      <div class="bwcl-products">
        <?php
          // Build Woo shortcode
          $atts = [
            'category' => sanitize_title($wc_cat),
            'limit'    => '8',
            'columns'  => '4',
            'orderby'  => 'date',
            'order'    => 'DESC',
          ];
          if (!empty($wc_tag)) $atts['tag'] = sanitize_title($wc_tag);

          // Convert to shortcode string
          $short = '[products';
          foreach ($atts as $k=>$v) $short .= ' '.$k.'="'.esc_attr($v).'"';
          $short .= ']';

          echo do_shortcode($short);
        ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php
get_footer();
