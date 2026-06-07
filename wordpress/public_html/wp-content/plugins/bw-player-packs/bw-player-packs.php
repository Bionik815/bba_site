<?php
/**
 * Plugin Name: BW Player Packs
 * Description: Adds a "Player Pack" product type. Build packs with required/optional items (each item can be a simple or variable product). One Add-to-Cart submits all child items with validation and grouping.
 * Version: 1.0.0
 * Author: You
 * License: GPL-2.0+
 */
if (!defined('ABSPATH')) exit;
// Do NOT gate on class_exists('WooCommerce') at include time. This plugin loads
// before WooCommerce in the active-plugins order, so an include-time guard makes
// the whole plugin silently no-op: BW_Player_Packs_Run (which extends BW_Player_Packs)
// is late-bound and never gets declared, so its hooks never register and the product
// type never appears. Classes are safe to declare now; we instantiate on
// plugins_loaded below, once WooCommerce is confirmed available.


class BW_Player_Packs {
  const TYPE     = 'bw_player_pack';
  const META_KEY = '_bw_pack_items'; // array of items

  public function __construct(){
    // product type
    add_filter('product_type_selector', [$this,'product_type_selector']);
    add_filter('woocommerce_product_class', [$this,'product_class'], 10, 2);

    // admin UI
    add_action('add_meta_boxes', [$this,'metabox']);
    add_action('save_post_product', [$this,'save_pack']);

    // single product template + cart handling
    add_action('woocommerce_single_product_summary', [$this,'render_pack_form'], 25);
    add_filter('woocommerce_is_purchasable', [$this,'is_purchasable'], 10, 2);
    add_action('template_redirect', [$this,'handle_add_to_cart']);

    // group visibility/names in cart/order (optional polish)
    add_filter('woocommerce_get_item_data', [$this,'show_pack_name_on_items'], 10, 2);
    add_action('woocommerce_checkout_create_order_line_item', [$this,'stamp_order_item_pack_meta'], 10, 4);
  }

  /* ---------------- Product type registration ---------------- */

  public function product_type_selector($types){
    $types[self::TYPE] = __('Player Pack','bwpp');
    return $types;
  }

  public function product_class($classname, $product_type){
  if ($product_type === self::TYPE && class_exists('WC_Product_BW_Pack')) {
    return 'WC_Product_BW_Pack';
  }
  return $classname;
}


}

/** Minimal product class (virtual/container, no price) */
// Define custom product class after WooCommerce has loaded.
add_action('plugins_loaded', function(){
  if (class_exists('WC_Product') && !class_exists('WC_Product_BW_Pack')) {
    class WC_Product_BW_Pack extends WC_Product {
      public function get_type(){ return BW_Player_Packs::TYPE; }
      public function is_purchasable(){ return true; }
      public function is_virtual(){ return true; }
      public function get_price($context = 'view'){ return 0; } // container has no price
    }
  }
}, 20);


if (!class_exists('BW_Player_Packs_Run')){
class BW_Player_Packs_Run extends BW_Player_Packs {

  /* ---------------- Admin: metabox ---------------- */

  public function metabox(){
    add_meta_box('bwpp_items', __('Player Pack Items','bwpp'), [$this,'metabox_render'], 'product', 'normal', 'high');
  }

  public function metabox_render($post){
    $product = wc_get_product($post->ID);
    if (!$product || $product->get_type() !== self::TYPE){
      echo '<p>'.esc_html__('Switch the product type to "Player Pack" to configure items.','bwpp').'</p>';
      return;
    }
    wp_nonce_field('bwpp_save','bwpp_nonce');
    $items = get_post_meta($post->ID, self::META_KEY, true);
    if (!is_array($items)) $items = [];

    // tiny css
    echo '<style>
      .bwpp-table{width:100%;border-collapse:collapse;margin:8px 0}
      .bwpp-table th,.bwpp-table td{padding:8px;border-bottom:1px solid #e8eaef;vertical-align:top}
      .bwpp-row input[type=number]{width:80px}
      .bwpp-actions{margin:8px 0}
    </style>';

    echo '<table class="bwpp-table" id="bwpp-table">';
    echo '<thead><tr><th>'.esc_html__('Product','bwpp').'</th><th>'.esc_html__('Required?','bwpp').'</th><th>'.esc_html__('Default Qty','bwpp').'</th><th>'.esc_html__('Min','bwpp').'</th><th>'.esc_html__('Max','bwpp').'</th><th></th></tr></thead><tbody id="bwpp-rows">';

    foreach ($items as $i=>$it){
      $pid = (int)($it['product_id'] ?? 0);
      $req = !empty($it['required']) ? '1':'0';
      $def = isset($it['default_qty']) ? (int)$it['default_qty'] : 1;
      $min = isset($it['min_qty']) ? (int)$it['min_qty'] : 0;
      $max = isset($it['max_qty']) ? (int)$it['max_qty'] : 0;

      echo '<tr class="bwpp-row">';
      echo '<td>'. $this->product_search_input("bwpp[$i][product_id]", $pid) .'</td>';
      echo '<td><label><input type="checkbox" name="bwpp['.$i.'][required]" value="1" '.checked($req,'1',false).'> '.esc_html__('Required','bwpp').'</label></td>';
      echo '<td><input type="number" min="0" name="bwpp['.$i.'][default_qty]" value="'.esc_attr($def).'"></td>';
      echo '<td><input type="number" min="0" name="bwpp['.$i.'][min_qty]" value="'.esc_attr($min).'"></td>';
      echo '<td><input type="number" min="0" name="bwpp['.$i.'][max_qty]" value="'.esc_attr($max).'"></td>';
      echo '<td><button type="button" class="button bwpp-delete">'.esc_html__('Remove','bwpp').'</button></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p class="bwpp-actions"><button type="button" class="button button-primary" id="bwpp-add">'.esc_html__('Add Item','bwpp').'</button></p>';

    // template
    $tmpl = '<tr class="bwpp-row">
      <td>%SEARCH%</td>
      <td><label><input type="checkbox" name="bwpp[__i__][required]" value="1"> '.esc_html__('Required','bwpp').'</label></td>
      <td><input type="number" min="0" name="bwpp[__i__][default_qty]" value="1"></td>
      <td><input type="number" min="0" name="bwpp[__i__][min_qty]" value="0"></td>
      <td><input type="number" min="0" name="bwpp[__i__][max_qty]" value="0"></td>
      <td><button type="button" class="button bwpp-delete">'.esc_html__('Remove','bwpp').'</button></td>
    </tr>';

    // enqueue scripts for product search fields
    wp_enqueue_script('select2'); wp_enqueue_style('select2');
    wp_enqueue_script('wc-admin-product-meta-boxes'); // for product ajax search
    ?>
    <script>
    (function($){
      function newRow(i){
        return `<?php echo esc_js($tmpl); ?>`.replace('%SEARCH%', prodSearch(`bwpp[${i}][product_id]`, ''));
      }
      function prodSearch(name, val){
        return `<select class="wc-product-search" name="${name}" data-placeholder="Search for a product" data-action="woocommerce_json_search_products_and_variations" style="width:320px"><option value="${val}" selected>${val? 'Loading…':''}</option></select>`;
      }
      $('#bwpp-add').on('click', function(){
        var i = $('#bwpp-rows .bwpp-row').length;
        $('#bwpp-rows').append(newRow(i));
        initSearch($('#bwpp-rows .bwpp-row').last().find('.wc-product-search'));
      });
      $('#bwpp-rows').on('click', '.bwpp-delete', function(){ $(this).closest('tr').remove(); });
      function initSearch($el){ $el.filter(':not(.select2-hidden-accessible)').each(function(){ $(this).wc_product_search(); }); }
      initSearch($('.wc-product-search'));
    })(jQuery);
    </script>
    <?php
  }

  private function product_search_input($name, $product_id){
    $label = $product_id ? get_the_title($product_id).' (#'.$product_id.')' : '';
    ob_start(); ?>
    <select class="wc-product-search" name="<?php echo esc_attr($name); ?>" data-placeholder="<?php esc_attr_e('Search for a product','bwpp'); ?>" data-action="woocommerce_json_search_products_and_variations" style="width:320px">
      <?php if ($product_id): ?>
        <option value="<?php echo (int)$product_id; ?>" selected><?php echo esc_html($label); ?></option>
      <?php endif; ?>
    </select>
    <?php return ob_get_clean();
  }

  public function save_pack($post_id){
    if (!isset($_POST['bwpp_nonce']) || !wp_verify_nonce($_POST['bwpp_nonce'], 'bwpp_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;

    $items = isset($_POST['bwpp']) ? (array) $_POST['bwpp'] : [];
    $clean = [];
    foreach ($items as $it){
      $pid = isset($it['product_id']) ? (int)$it['product_id'] : 0;
      if ($pid <= 0) continue;
      $clean[] = [
        'product_id'  => $pid,
        'required'    => isset($it['required']) ? '1' : '0',
        'default_qty' => max(0, (int)($it['default_qty'] ?? 1)),
        'min_qty'     => max(0, (int)($it['min_qty'] ?? 0)),
        'max_qty'     => max(0, (int)($it['max_qty'] ?? 0)),
      ];
    }
    update_post_meta($post_id, self::META_KEY, $clean);
  }

  /* ---------------- Purchasable + render ---------------- */

  public function is_purchasable($purchasable, $product){
    if ($product && $product->get_type() === self::TYPE) return true;
    return $purchasable;
  }

  public function render_pack_form(){
    global $product;
    if (!$product || $product->get_type() !== self::TYPE) return;

    $items = get_post_meta($product->get_id(), self::META_KEY, true);
    if (!$items || !is_array($items)){
      echo '<p><em>'.esc_html__('No items configured for this pack yet.','bwpp').'</em></p>';
      return;
    }

    echo '<form class="cart bwpp-form" method="post">';
    echo '<input type="hidden" name="add-bw-pack" value="'.esc_attr($product->get_id()).'">';
    wp_nonce_field('bwpp_add_to_cart', 'bwpp_cart_nonce');

    foreach ($items as $idx => $it){
      $child = wc_get_product((int)$it['product_id']);
      if (!$child) continue;

      $req    = ($it['required'] === '1');
      $defqty = max(0, (int)$it['default_qty']);

      echo '<div class="bwpp-item" style="border:1px solid #e6e8ef;border-radius:10px;padding:12px;margin:10px 0">';
      echo '<div style="display:flex;align-items:center;gap:12px;justify-content:space-between;flex-wrap:wrap;">';
      echo '  <div style="display:flex;align-items:center;gap:12px">';
      echo get_the_post_thumbnail($child->get_id(), [80,80], ['style'=>'border-radius:6px']) ?: '';
      echo '    <strong>'.esc_html($child->get_name()).'</strong>';
      if ($req) echo ' <span style="color:#b32d2e;font-weight:700;margin-left:6px">'.esc_html__('(Required)','bwpp').'</span>';
      echo '  </div>';

      // Optional checkbox
      if (!$req){
        echo '<label style="margin-left:auto;"><input type="checkbox" name="bwpp_sel['.$child->get_id().']" value="1" '.checked($defqty>0,true,false).'> '.esc_html__('Include','bwpp').'</label>';
      }
      echo '</div>';

      // Quantity (for both required & optional)
      echo '<div style="margin-top:8px">';
      echo '<label>'.esc_html__('Quantity','bwpp').': ';
      echo '<input type="number" min="'.(int)($it['min_qty']??0).'" '.($it['max_qty']?('max='.(int)$it['max_qty']):'').' name="bwpp_qty['.$child->get_id().']" value="'.($defqty?:($req?1:0)).'" style="width:90px">';
      echo '</label></div>';

      // Variation selectors for variable children. Fields are namespaced per
      // child (bwpp_attr[child_id][attr]) so multiple variable items in one pack
      // don't collide on shared attribute_* names — and we avoid nesting Woo's
      // own <form> inside the pack form (invalid HTML that breaks submission).
      if ($child->is_type('variable')){
        $cid      = $child->get_id();
        $defaults = $child->get_default_attributes();
        echo '<div class="bwpp-variations" style="margin-top:8px">';
        foreach ($child->get_variation_attributes() as $attr_name => $options){
          $field   = 'bwpp_attr['.esc_attr($cid).']['.esc_attr($attr_name).']';
          $default = $defaults[sanitize_title($attr_name)] ?? ($defaults[$attr_name] ?? '');
          echo '<p class="bwpp-attr" style="margin:6px 0"><label>'.esc_html(wc_attribute_label($attr_name)).': ';
          echo '<select name="'.$field.'" required>';
          echo '<option value="">'.esc_html__('Choose an option…','bwpp').'</option>';
          foreach ($options as $option){
            if (taxonomy_exists($attr_name)){
              $term  = get_term_by('slug', $option, $attr_name);
              $label = ($term && !is_wp_error($term)) ? $term->name : $option;
            } else {
              $label = $option;
            }
            echo '<option value="'.esc_attr($option).'"'.selected($default, $option, false).'>'.esc_html($label).'</option>';
          }
          echo '</select></label></p>';
        }
        echo '</div>';
      }

      echo '</div>'; // .bwpp-item
    }

    echo '<button type="submit" class="single_add_to_cart_button button alt" style="margin-top:12px">'.esc_html__('Add Pack to Cart','bwpp').'</button>';
    echo '</form>';

    // Small help text
    echo '<p class="bwpp-note" style="font-size:.9rem;opacity:.8">'.esc_html__('Required items must be included. Optional items are up to you.','bwpp').'</p>';
  }

  /* ---------------- Add-to-cart handler ---------------- */

  public function handle_add_to_cart(){
    if (!isset($_POST['add-bw-pack'])) return;
    $pack_id = (int) $_POST['add-bw-pack'];
    $pack = wc_get_product($pack_id);
    if (!$pack || $pack->get_type() !== self::TYPE) return;

    // CSRF: only process a genuine submission from the pack form.
    if (!isset($_POST['bwpp_cart_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bwpp_cart_nonce'])), 'bwpp_add_to_cart')){
      wc_add_notice(__('Your session expired. Please try adding the pack again.','bwpp'), 'error');
      wp_safe_redirect(wp_get_referer() ?: get_permalink($pack_id)); exit;
    }

    $items = get_post_meta($pack_id, self::META_KEY, true);
    if (!$items || !is_array($items)) return;

    $sel  = isset($_POST['bwpp_sel']) ? (array) $_POST['bwpp_sel'] : [];
    $qtys = isset($_POST['bwpp_qty']) ? (array) $_POST['bwpp_qty'] : [];

    // Validate & collect
    $to_add = [];
    foreach ($items as $it){
      $pid = (int) $it['product_id'];
      $child = wc_get_product($pid);
      if (!$child) continue;

      $req   = ($it['required'] === '1');
      $min   = max(0, (int) ($it['min_qty'] ?? 0));
      $max   = max(0, (int) ($it['max_qty'] ?? 0));
      $q     = max(0, (int) ($qtys[$pid] ?? 0));

      $include = $req ? ($q > 0) : (!empty($sel[$pid]) && $q > 0);

      if ($req && $q <= 0){
        wc_add_notice(sprintf(__('Please specify a quantity for required item: %s','bwpp'), $child->get_name()), 'error');
        wp_safe_redirect(wp_get_referer() ?: get_permalink($pack_id)); exit;
      }
      if (!$include) continue;

      // Enforce the configured min/max server-side; the HTML attributes are advisory only.
      $effective_min = max(1, $min);
      if ($q < $effective_min){
        wc_add_notice(sprintf(__('“%1$s” requires a minimum quantity of %2$d.','bwpp'), $child->get_name(), $effective_min), 'error');
        wp_safe_redirect(wp_get_referer() ?: get_permalink($pack_id)); exit;
      }
      if ($max > 0 && $q > $max){
        wc_add_notice(sprintf(__('“%1$s” allows a maximum quantity of %2$d.','bwpp'), $child->get_name(), $max), 'error');
        wp_safe_redirect(wp_get_referer() ?: get_permalink($pack_id)); exit;
      }

      $args = [];

      if ($child->is_type('variable')){
        // Read this child's chosen attributes from its namespaced fields only.
        $attributes  = $child->get_variation_attributes();
        $posted_attr = (isset($_POST['bwpp_attr'][$pid]) && is_array($_POST['bwpp_attr'][$pid]))
          ? (array) wp_unslash($_POST['bwpp_attr'][$pid])
          : [];
        $chosen = [];
        foreach ($attributes as $tax => $terms){
          if (isset($posted_attr[$tax]) && $posted_attr[$tax] !== ''){
            $chosen[$tax] = wc_clean($posted_attr[$tax]);
          }
        }
        // Require a selection for every attribute, then find the matching variation.
        $variation_id = 0;
        if (count($chosen) === count($attributes)){
          foreach ($child->get_available_variations() as $v){
            $match = true;
            foreach ($chosen as $tax => $val){
              $vk = 'attribute_'.sanitize_title($tax);
              // An empty value on the variation means "any", which matches anything.
              if (isset($v['attributes'][$vk]) && $v['attributes'][$vk] !== '' && $v['attributes'][$vk] != $val){
                $match = false; break;
              }
            }
            if ($match){ $variation_id = (int)$v['variation_id']; break; }
          }
        }
        if (!$variation_id){
          wc_add_notice(sprintf(__('Please choose options for: %s','bwpp'), $child->get_name()), 'error');
          wp_safe_redirect(wp_get_referer() ?: get_permalink($pack_id)); exit;
        }
        $args['variation_id'] = $variation_id;
        $args['variation']    = array_combine(
          array_map(function($k){ return 'attribute_'.sanitize_title($k); }, array_keys($chosen)),
          array_values($chosen)
        );
      }

      // Grouping meta
      $args['bw_pack_parent'] = $pack_id;
      $args['bw_pack_name']   = get_the_title($pack_id);

      $to_add[] = ['product_id'=>$pid, 'quantity'=>$q, 'args'=>$args];
    }

    if (empty($to_add)){
      wc_add_notice(__('Please select at least one item for the pack.','bwpp'),'error');
      wp_safe_redirect(wp_get_referer() ?: get_permalink($pack_id)); exit;
    }

    // Add each child
    foreach ($to_add as $line){
      WC()->cart->add_to_cart($line['product_id'], $line['quantity'], $line['args']['variation_id'] ?? 0, $line['args']['variation'] ?? [], [
        '_bw_pack_parent' => $line['args']['bw_pack_parent'],
        '_bw_pack_name'   => $line['args']['bw_pack_name'],
      ]);
    }

    // Success
    wc_add_notice(__('Player pack added to cart.','bwpp'),'success');
    wp_safe_redirect(wc_get_cart_url()); exit;
  }

  /* ---------------- Presentation in cart/order ---------------- */

  public function show_pack_name_on_items($item_data, $cart_item){
    if (!empty($cart_item['_bw_pack_name'])){
      $item_data[] = ['name'=>__('Pack','bwpp'),'value'=>wp_kses_post($cart_item['_bw_pack_name'])];
    }
    return $item_data;
  }

  public function stamp_order_item_pack_meta($item, $cart_item_key, $values, $order){
    if (!empty($values['_bw_pack_parent'])){
      $item->add_meta_data('Pack', $values['_bw_pack_name'] ?? __('Player Pack','bwpp'), true);
    }
  }
}}

// Instantiate once all plugins (incl. WooCommerce) are loaded, regardless of order.
add_action('plugins_loaded', function(){
  if (class_exists('WooCommerce')) {
    new BW_Player_Packs_Run();
  }
}, 25); // after WC_Product_BW_Pack is declared (priority 20)
