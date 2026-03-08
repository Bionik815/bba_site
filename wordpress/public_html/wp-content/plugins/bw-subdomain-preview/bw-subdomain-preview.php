<?php
/**
 * Plugin Name: BW Subdomain Preview (Client Landing + Featured Carousel)
 * Description: Shortcode to render a branded client "subdomain preview" landing with hero + featured products carousel + CTA. Includes admin defaults.
 * Version: 1.1.0
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

class BW_Subdomain_Preview {
  const OPT_GROUP   = 'bwsp_settings_group';
  const OPT_BRAND   = 'bwsp_brand';     // e.g. #306C93
  const OPT_BUTTON  = 'bwsp_button';    // e.g. "Enter Store"
  const OPT_LIMIT   = 'bwsp_limit';     // e.g. 10
  const OPT_OVERLAY = 'bwsp_overlay';   // 0.0 - 0.9 (darkness)

  public function __construct(){
    add_shortcode('client_subdomain_preview', [$this,'shortcode']);
    add_action('wp_enqueue_scripts', [$this,'register_assets']);

    // Settings page
    add_action('admin_menu',  [$this,'add_settings_page']);
    add_action('admin_init',  [$this,'register_settings']);
  }

  /* ---------------- Assets ---------------- */
  public function register_assets(){
    // Load only when shortcode used (we enqueue inside shortcode)
    wp_register_style ('bwsp-css', plugins_url('bwsp.css', __FILE__), [], '1.0.0');
    wp_register_script('bwsp-js',  plugins_url('bwsp.js',  __FILE__), [], '1.0.0', true);
  }

  /* ---------------- Settings ---------------- */
  public function add_settings_page(){
    add_options_page('Subdomain Preview', 'Subdomain Preview', 'manage_options', 'bwsp-settings', [$this,'render_settings_page']);
  }

  public function register_settings(){
    // Register options with sanitizers + defaults
    register_setting(self::OPT_GROUP, self::OPT_BRAND, [
      'type'=>'string',
      'sanitize_callback'=>function($v){
        $v = trim($v);
        // accept #RRGGBB or rgb()/hsl() strings, but prefer hex pattern quick check
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',$v)) return $v;
        return '#111111'; // fallback
      },
      'default'=>'#111111'
    ]);
    register_setting(self::OPT_GROUP, self::OPT_BUTTON, [
      'type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'Enter Store'
    ]);
    register_setting(self::OPT_GROUP, self::OPT_LIMIT, [
      'type'=>'integer','sanitize_callback'=>function($v){ $n = (int)$v; return $n>0 ? $n : 10; },'default'=>10
    ]);
    register_setting(self::OPT_GROUP, self::OPT_OVERLAY, [
      'type'=>'number','sanitize_callback'=>function($v){ $f = floatval($v); if($f<0) $f=0; if($f>0.9) $f=0.9; return $f; },'default'=>0.45
    ]);
  }

  public function render_settings_page(){ ?>
    <div class="wrap">
      <h1>Subdomain Preview — Defaults</h1>
      <form method="post" action="options.php">
        <?php settings_fields(self::OPT_GROUP); ?>
        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row"><label for="bwsp_brand">Default Brand Color</label></th>
              <td>
                <input type="text" id="bwsp_brand" name="<?php echo esc_attr(self::OPT_BRAND); ?>"
                  value="<?php echo esc_attr(get_option(self::OPT_BRAND,'#111111')); ?>"
                  class="regular-text" placeholder="#111111">
                <p class="description">HEX color (e.g., #306C93). Used for buttons/accent if not provided in shortcode.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="bwsp_button">Default CTA Button Text</label></th>
              <td>
                <input type="text" id="bwsp_button" name="<?php echo esc_attr(self::OPT_BUTTON); ?>"
                  value="<?php echo esc_attr(get_option(self::OPT_BUTTON,'Enter Store')); ?>"
                  class="regular-text" placeholder="Enter Store">
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="bwsp_limit">Default Featured Items Limit</label></th>
              <td>
                <input type="number" id="bwsp_limit" name="<?php echo esc_attr(self::OPT_LIMIT); ?>"
                  value="<?php echo esc_attr(get_option(self::OPT_LIMIT,10)); ?>" class="small-text" min="1" step="1">
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="bwsp_overlay">Hero Overlay Darkness</label></th>
              <td>
                <input type="number" id="bwsp_overlay" name="<?php echo esc_attr(self::OPT_OVERLAY); ?>"
                  value="<?php echo esc_attr(get_option(self::OPT_OVERLAY,0.45)); ?>"
                  class="small-text" min="0" max="0.9" step="0.05">
                <p class="description">0.00 (no darkening) → 0.90 (very dark). Improves text readability over banners.</p>
              </td>
            </tr>
          </tbody>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
  <?php }

  /* ---------------- Shortcode ---------------- */
  public function shortcode($atts){
    // Pull defaults
    $def_brand   = get_option(self::OPT_BRAND,  '#111111');
    $def_button  = get_option(self::OPT_BUTTON, 'Enter Store');
    $def_limit   = (int) get_option(self::OPT_LIMIT, 10);
    $def_overlay = (float) get_option(self::OPT_OVERLAY, 0.45);

    // Attributes (can override defaults)
    $atts = shortcode_atts([
      'title'    => 'Client Store',
      'brand'    => $def_brand,
      'banner'   => '',
      'logo'     => '',
      'store'    => '/',
      'category' => '',
      'tag'      => '',
      'limit'    => $def_limit,
      'button'   => $def_button,      // NEW: CTA text from settings or override
      'overlay'  => $def_overlay,     // NEW: overlay darkness
    ], $atts, 'client_subdomain_preview');

    // Enqueue assets
    wp_enqueue_style('bwsp-css');
    wp_enqueue_script('bwsp-js');

    // Build WC product query
    if (!function_exists('wc_get_products')) {
      return '<p><em>WooCommerce not active: cannot render featured items.</em></p>';
    }
    $args = [
      'status'     => 'publish',
      'limit'      => max(1, (int)$atts['limit']),
      'orderby'    => 'date',
      'order'      => 'DESC',
      'return'     => 'objects',
      'paginate'   => false,
      'visibility' => 'catalog',
    ];
    if (!empty($atts['category'])) $args['category'] = array_map('trim', explode(',', $atts['category']));
    if (!empty($atts['tag']))      $args['tag']      = array_map('trim', explode(',', $atts['tag']));

    $products = wc_get_products($args);

    // Inline style bits
    $hero_style = '';
    if (!empty($atts['banner'])) {
      $hero_style .= "background-image:url('". esc_url($atts['banner']) ."');";
    }
    $brand_css = '--brand:'. esc_attr($atts['brand']) . ';';
    $overlay   = max(0, min(0.9, floatval($atts['overlay'])));

    ob_start(); ?>
    <section class="bwsp-hero" style="<?php echo $brand_css . $hero_style; ?>">
      <div class="bwsp-hero__overlay" style="background:linear-gradient(180deg, rgba(0,0,0,<?php echo esc_attr($overlay); ?>), rgba(0,0,0,<?php echo esc_attr($overlay); ?>));"></div>
      <div class="bwsp-hero__inner">
        <?php if (!empty($atts['logo'])): ?>
          <div class="bwsp-hero__logo"><img src="<?php echo esc_url($atts['logo']); ?>" alt="<?php echo esc_attr($atts['title']); ?>"></div>
        <?php endif; ?>
        <div class="bwsp-hero__copy">
          <h1><?php echo esc_html($atts['title']); ?></h1>
          <p>Official storefront preview</p>
          <a class="bwsp-btn" href="<?php echo esc_url($atts['store']); ?>"><?php echo esc_html($atts['button']); ?></a>
        </div>
      </div>
    </section>

    <section class="bwsp-wrap">
      <div class="bwsp-head">
        <h2>Featured Items</h2>
        <a class="bwsp-link" href="<?php echo esc_url($atts['store']); ?>">View All</a>
      </div>

      <div class="bwsp-row">
        <button class="bwsp-nav prev" aria-label="Scroll left">‹</button>
        <ul class="bwsp-caro" tabindex="0">
          <?php if (!empty($products)): foreach ($products as $p):
            $pid   = $p->get_id();
            $name  = $p->get_name();
            $url   = get_permalink($pid);
            $img   = get_the_post_thumbnail($pid, 'woocommerce_thumbnail', ['alt'=>$name]);
            if (!$img) $img = '<div class="bwsp-img-fallback">'.esc_html(mb_substr($name,0,1)).'</div>';
            $price = $p->get_price_html();
          ?>
            <li class="bwsp-card">
              <a href="<?php echo esc_url($url); ?>">
                <div class="bwsp-card__img"><?php echo $img; ?></div>
                <div class="bwsp-card__body">
                  <div class="bwsp-card__title"><?php echo esc_html($name); ?></div>
                  <div class="bwsp-card__price"><?php echo wp_kses_post($price); ?></div>
                </div>
              </a>
            </li>
          <?php endforeach; else: ?>
            <li class="bwsp-empty"><em>No featured items yet.</em></li>
          <?php endif; ?>
        </ul>
        <button class="bwsp-nav next" aria-label="Scroll right">›</button>
      </div>

      <div class="bwsp-cta">
        <a class="bwsp-btn big" href="<?php echo esc_url($atts['store']); ?>"><?php echo esc_html($atts['button']); ?></a>
      </div>
    </section>
    <?php
    return ob_get_clean();
  }
}
new BW_Subdomain_Preview();
