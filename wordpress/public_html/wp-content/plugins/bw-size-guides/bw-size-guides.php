<?php
/**
 * Plugin Name: BW Size Guide Manager
 * Description: Admin-manageable size-guide images per clothing company. Select a company on the product edit screen (with optional per-product image override). Adds a “Size Guide” tab on the product page.
 * Version: 1.0.0
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

class BW_Size_Guides {
  const OPT_KEY = 'bwsg_companies'; // array of companies
  // Company entry shape: ['id'=>uniqid, 'name'=>string, 'image_id'=>int]
  const META_COMPANY   = '_bwsg_company_id';
  const META_OVERRIDE  = '_bwsg_image_override_id';

  public function __construct(){
    // Settings page
    add_action('admin_menu',  [$this,'add_settings_page']);
    add_action('admin_init',  [$this,'register_settings']);
    add_action('admin_enqueue_scripts', [$this,'admin_assets']);

    // Product meta
    add_action('add_meta_boxes', [$this,'add_product_metabox']);
    add_action('save_post_product', [$this,'save_product_meta']);

    // Frontend tab
    add_filter('woocommerce_product_tabs', [$this,'add_size_tab']);
    add_action('wp_enqueue_scripts', [$this,'frontend_assets']);
    
    add_action('woocommerce_after_add_to_cart_form', [$this,'render_inline_size_guide'], 12);
  }

  /* ================= Settings ================= */

  public function add_settings_page(){
    add_submenu_page(
      'woocommerce',
      __('Size Guides','bwsg'),
      __('Size Guides','bwsg'),
      'manage_woocommerce',
      'bw-size-guides',
      [$this,'render_settings']
    );
  }

  public function register_settings(){
    register_setting('bwsg_group', self::OPT_KEY, [
      'type'=>'array',
      'sanitize_callback'=>[$this,'sanitize_companies'],
      'default'=>[],
    ]);
  }

  public function sanitize_companies($in){
    $out = [];
    if (!is_array($in)) return $out;
    foreach ($in as $row){
      $id   = isset($row['id']) && $row['id'] !== '' ? sanitize_text_field($row['id']) : wp_generate_uuid4();
      $name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
      $img  = isset($row['image_id']) ? intval($row['image_id']) : 0;
      if ($name === '') continue;
      $out[] = ['id'=>$id, 'name'=>$name, 'image_id'=>$img];
    }
    return $out;
  }

  public function render_settings(){
    if (!current_user_can('manage_woocommerce')) return;
    $companies = get_option(self::OPT_KEY, []);
    // Ensure array indexed
    if (!is_array($companies)) $companies = [];
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Size Guides','bwsg'); ?></h1>
      <p class="description"><?php esc_html_e('Add each clothing company/brand and attach its size guide image. Then choose the company on each product.', 'bwsg'); ?></p>

      <form method="post" action="options.php">
        <?php settings_fields('bwsg_group'); ?>

        <table class="widefat striped" id="bwsg-table" style="max-width:960px;">
          <thead>
            <tr>
              <th style="width:40%"><?php esc_html_e('Company/Brand Name','bwsg'); ?></th>
              <th style="width:40%"><?php esc_html_e('Size Guide Image','bwsg'); ?></th>
              <th style="width:20%"><?php esc_html_e('Actions','bwsg'); ?></th>
            </tr>
          </thead>
          <tbody id="bwsg-rows">
            <?php if ($companies): foreach ($companies as $i=>$c): 
              $img_id = intval($c['image_id'] ?? 0);
              $thumb  = $img_id ? wp_get_attachment_image($img_id, [120,120]) : '';
            ?>
              <tr class="bwsg-row">
                <td>
                  <input type="hidden" name="<?php echo esc_attr(self::OPT_KEY); ?>[<?php echo $i; ?>][id]" value="<?php echo esc_attr($c['id']); ?>">
                  <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPT_KEY); ?>[<?php echo $i; ?>][name]" value="<?php echo esc_attr($c['name']); ?>" placeholder="e.g., Gildan">
                </td>
                <td>
                  <div class="bwsg-imgwrap">
                    <input type="hidden" class="bwsg-image-id" name="<?php echo esc_attr(self::OPT_KEY); ?>[<?php echo $i; ?>][image_id]" value="<?php echo esc_attr($img_id); ?>">
                    <div class="bwsg-thumb"><?php echo $thumb ?: '<em>'.esc_html__('No image selected','bwsg').'</em>'; ?></div>
                    <p class="bwsg-actions" style="margin:.5em 0 0;">
                      <button type="button" class="button bwsg-choose"><?php esc_html_e('Choose Image','bwsg'); ?></button>
                      <button type="button" class="button bwsg-clear"><?php esc_html_e('Clear','bwsg'); ?></button>
                    </p>
                  </div>
                </td>
                <td><button type="button" class="button link-delete bwsg-delete" style="color:#b32d2e"><?php esc_html_e('Remove','bwsg'); ?></button></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

        <p style="margin-top:12px;">
          <button type="button" class="button button-primary" id="bwsg-add"><?php esc_html_e('Add Company','bwsg'); ?></button>
        </p>

        <?php submit_button(__('Save Changes','bwsg')); ?>
      </form>
    </div>

    <script type="text/template" id="bwsg-row-template">
      <tr class="bwsg-row">
        <td>
          <input type="hidden" name="<?php echo esc_js(self::OPT_KEY); ?>[__i__][id]" value="">
          <input type="text" class="regular-text" name="<?php echo esc_js(self::OPT_KEY); ?>[__i__][name]" value="" placeholder="e.g., Gildan">
        </td>
        <td>
          <div class="bwsg-imgwrap">
            <input type="hidden" class="bwsg-image-id" name="<?php echo esc_js(self::OPT_KEY); ?>[__i__][image_id]" value="">
            <div class="bwsg-thumb"><em><?php echo esc_js(__('No image selected','bwsg')); ?></em></div>
            <p class="bwsg-actions" style="margin:.5em 0 0;">
              <button type="button" class="button bwsg-choose"><?php echo esc_js(__('Choose Image','bwsg')); ?></button>
              <button type="button" class="button bwsg-clear"><?php echo esc_js(__('Clear','bwsg')); ?></button>
            </p>
          </div>
        </td>
        <td><button type="button" class="button link-delete bwsg-delete" style="color:#b32d2e"><?php echo esc_js(__('Remove','bwsg')); ?></button></td>
      </tr>
    </script>
    <?php
  }

public function admin_assets($hook){
  if ( ! function_exists('get_current_screen') ) return;
  $screen = get_current_screen();
  if (!$screen) return;

  $is_settings = ($screen->id === 'woocommerce_page_bw-size-guides' || strpos($screen->id, 'bw-size-guides') !== false);
  $is_product  = ($screen->id === 'product' || (property_exists($screen, 'post_type') && $screen->post_type === 'product'));
  if (!$is_settings && !$is_product) return;

  wp_enqueue_media();
  wp_enqueue_script('bwsg-admin', plugins_url('admin.js', __FILE__), ['jquery'], '1.0.0', true);
  wp_add_inline_style('wp-admin', '.bwsg-thumb img{max-width:120px;height:auto;display:block;border:1px solid #e5e7eb;border-radius:6px;padding:2px;background:#fff}');
}



  /* ================= Product meta ================= */

  public function add_product_metabox(){
    add_meta_box('bwsg_meta', __('Size Guide','bwsg'), [$this,'render_product_meta'], 'product', 'side', 'default');
  }

  public function render_product_meta($post){
    wp_nonce_field('bwsg_product_save','bwsg_nonce');
    $companies = get_option(self::OPT_KEY, []);
    $selected  = get_post_meta($post->ID, self::META_COMPANY, true) ?: '';
    $override  = intval(get_post_meta($post->ID, self::META_OVERRIDE, true) ?: 0);
    $override_thumb = $override ? wp_get_attachment_image($override, [120,120]) : '';
    ?>
    <p>
      <label for="bwsg_company"><strong><?php esc_html_e('Clothing Company','bwsg'); ?></strong></label><br>
      <select id="bwsg_company" name="bwsg_company" class="widefat">
        <option value=""><?php esc_html_e('— None —','bwsg'); ?></option>
        <?php foreach ($companies as $c): ?>
          <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($selected, $c['id']); ?>>
            <?php echo esc_html($c['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </p>
    <p class="howto"><?php esc_html_e('Choose the company to use its default size guide image.', 'bwsg'); ?></p>
    <hr>
    <p><strong><?php esc_html_e('Override Image (optional)','bwsg'); ?></strong></p>
    <input type="hidden" id="bwsg_override_id" name="bwsg_override_id" value="<?php echo esc_attr($override); ?>">
    <div id="bwsg_override_thumb"><?php echo $override_thumb ?: '<em>'.esc_html__('No image selected','bwsg').'</em>'; ?></div>
    <p style="margin-top:.5em;">
      <button type="button" class="button" id="bwsg_override_choose"><?php esc_html_e('Choose Image','bwsg'); ?></button>
      <button type="button" class="button" id="bwsg_override_clear"><?php esc_html_e('Clear','bwsg'); ?></button>
    </p>
    <script>
      (function($){
        $('#bwsg_override_choose').on('click', function(e){
          e.preventDefault();
          var frame = wp.media({ title: 'Select Size Guide', multiple:false, library:{ type:'image' } });
          frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            $('#bwsg_override_id').val(att.id);
            $('#bwsg_override_thumb').html('<img style="max-width:120px;height:auto;border:1px solid #e5e7eb;border-radius:6px;padding:2px;background:#fff" src="'+(att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url)+'" alt="">');
          });
          frame.open();
        });
        $('#bwsg_override_clear').on('click', function(){
          $('#bwsg_override_id').val('');
          $('#bwsg_override_thumb').html('<em><?php echo esc_js(__('No image selected','bwsg')); ?></em>');
        });
      })(jQuery);
    </script>
    <?php
  }

  public function save_product_meta($post_id){
    if (!isset($_POST['bwsg_nonce']) || !wp_verify_nonce($_POST['bwsg_nonce'],'bwsg_product_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;

    $company = isset($_POST['bwsg_company']) ? sanitize_text_field($_POST['bwsg_company']) : '';
    $override = isset($_POST['bwsg_override_id']) ? intval($_POST['bwsg_override_id']) : 0;

    if ($company === '') delete_post_meta($post_id, self::META_COMPANY);
    else update_post_meta($post_id, self::META_COMPANY, $company);

    if ($override) update_post_meta($post_id, self::META_OVERRIDE, $override);
    else delete_post_meta($post_id, self::META_OVERRIDE);
  }

  /* ================= Frontend display ================= */

  public function frontend_assets(){
    $css = '.bwsg-tab img{max-width:100%;height:auto;border:1px solid #e5e7eb;border-radius:8px;padding:6px;background:#fff}';
    wp_add_inline_style('woocommerce-inline', $css);
  }

  public function add_size_tab($tabs){
    if (!is_product()) return $tabs;

    global $product;
    if (!$product) return $tabs;

    $image_id = $this->get_image_for_product($product->get_id());
    if (!$image_id) return $tabs;

    $tabs['bwsg'] = [
      'title'    => __('Size Guide','bwsg'),
      'priority' => 55,
      'callback' => function() use ($image_id){
        echo '<div class="bwsg-tab">';
        echo wp_get_attachment_image($image_id, 'large', false, ['alt'=>__('Size Guide','bwsg')]);
        echo '</div>';
      }
    ];
    return $tabs;
  }
  
  public function render_inline_size_guide(){
  if (!is_product()) return;
  global $product;
  if (!$product) return;

  $image_id = $this->get_image_for_product($product->get_id());
  if (!$image_id) return;

  // Collapsible inline block (keeps page tidy)
  $url = wp_get_attachment_image_url($image_id, 'large');
  $img = wp_get_attachment_image($image_id, 'large', false, ['alt'=>__('Size Guide','bwsg')]);

  echo '<div class="bwsg-inline" style="margin-top:14px">';
  echo '  <details class="bwsg-details">';
  echo '    <summary class="bwsg-summary">'.esc_html__('Size Guide','bwsg').'</summary>';
  echo '    <div class="bwsg-inline-wrap">'.$img.'</div>';
  echo '  </details>';
  echo '</div>';
}


  private function get_image_for_product($product_id){
    // Per-product override?
    $override = intval(get_post_meta($product_id, self::META_OVERRIDE, true));
    if ($override) return $override;

    // Company default
    $company_id = get_post_meta($product_id, self::META_COMPANY, true);
    if (!$company_id) return 0;

    $companies = get_option(self::OPT_KEY, []);
    if (!$companies) return 0;

    foreach ($companies as $c){
      if (!empty($c['id']) && $c['id'] == $company_id){
        return intval($c['image_id'] ?? 0);
      }
    }
    return 0;
  }
}

new BW_Size_Guides();
