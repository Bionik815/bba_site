<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Single template for bw_creator (Creator landing)
 * Uses: Title, Featured Image (logo), Store URL (meta), optional WC category/tag for grid
 */

get_header();

$creator_id = get_the_ID();
$name       = get_the_title();
$logo       = get_the_post_thumbnail($creator_id, 'large', ['alt'=>$name]) ?: '';
$store_url  = get_post_meta($creator_id, '_bw_creator_link', true);
$cta_text   = get_post_meta($creator_id, '_bw_creator_cta_text', true) ?: 'Shop Now';
$wc_cat     = get_post_meta($creator_id, '_bw_creator_wc_category', true);
$wc_tag     = get_post_meta($creator_id, '_bw_creator_wc_tag', true);

// Allow site admins to set a brand color per page using Elementor Custom CSS var if they want.
// We just provide sensible default colors via CSS declared in assets.
?>
<main id="primary" class="site-main">

  <section class="bwcl-hero">
    <div class="bwcl-inner">
      <div class="bwcl-logo"><?php echo $logo; ?></div>
      <div class="bwcl-copy">
        <h1><?php echo esc_html($name); ?></h1>
        <p><?php echo esc_html__('Official storefront', 'bw'); ?></p>
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
