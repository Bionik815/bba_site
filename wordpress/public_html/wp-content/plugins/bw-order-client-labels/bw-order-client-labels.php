<?php
/**
 * Plugin Name: BW Order Client Labels
 * Description: Adds clear client labels (from product categories) to order items, admin screens, and emails. Optional cart guard prevents mixing clients in one cart.
 * Version: 1.0.0
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

class BW_Order_Client_Labels {
  const OPT_GROUP      = 'bwocl_settings';
  const OPT_CLIENT_CATS= 'bwocl_client_cats'; // array of product_cat term IDs that represent clients
  const OPT_EMAIL_TAG  = 'bwocl_show_in_emails'; // bool
  const OPT_ADMIN_TAG  = 'bwocl_show_in_admin';  // bool
  const OPT_GUARD      = 'bwocl_cart_guard';     // bool
  const META_CLIENT    = '_bw_client_name';      // stored on order item
  const META_CLIENT_SL = '_bw_client_slug';

  public function __construct(){
    // Settings
    add_action('admin_menu',  [$this,'settings_page']);
    add_action('admin_init',  [$this,'register_settings']);

    // Stamp order items with client meta at checkout
    add_action('woocommerce_checkout_create_order_line_item', [$this,'add_item_meta'], 10, 4);

    // Show client next to item name (admin & emails)
    add_filter('woocommerce_order_item_name', [$this,'decorate_item_name'], 10, 3);

    // Admin Orders list: Clients column
    add_filter('manage_edit-shop_order_columns', [$this,'add_orders_column']);
    add_action('manage_shop_order_posts_custom_column', [$this,'render_orders_column'], 10, 2);

    // Admin single order: Item row badge CSS
    add_action('admin_head', [$this,'admin_css']);

    // Optional cart guard to prevent mixing clients
    add_filter('woocommerce_add_to_cart_validation', [$this,'validate_same_client_only'], 10, 4);
  }

  /* ================= Settings ================= */

  public function settings_page(){
    add_submenu_page(
      'woocommerce',
      __('Client Labels','bwocl'),
      __('Client Labels','bwocl'),
      'manage_woocommerce',
      'bwocl-settings',
      [$this,'render_settings']
    );
  }

  public function register_settings(){
    register_setting(self::OPT_GROUP, self::OPT_CLIENT_CATS, [
      'type'=>'array',
      'sanitize_callback'=>function($in){
        $ids = array_map('intval', (array)$in);
        return array_values(array_filter($ids));
      },
      'default'=>[],
    ]);
    register_setting(self::OPT_GROUP, self::OPT_EMAIL_TAG, ['type'=>'boolean','default'=>true, 'sanitize_callback'=>[$this,'bool']]);
    register_setting(self::OPT_GROUP, self::OPT_ADMIN_TAG, ['type'=>'boolean','default'=>true, 'sanitize_callback'=>[$this,'bool']]);
    register_setting(self::OPT_GROUP, self::OPT_GUARD,     ['type'=>'boolean','default'=>false,'sanitize_callback'=>[$this,'bool']]);
  }

  public function bool($v){ return (bool) (is_string($v) ? ($v==='1' || $v==='on') : $v); }

  public function render_settings(){
    if (!current_user_can('manage_woocommerce')) return;
    $cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
    $chosen = (array) get_option(self::OPT_CLIENT_CATS, []);
    $email  = get_option(self::OPT_EMAIL_TAG, true);
    $admin  = get_option(self::OPT_ADMIN_TAG, true);
    $guard  = get_option(self::OPT_GUARD, false);
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Client Labels & Cart Guard','bwocl'); ?></h1>
      <form method="post" action="options.php">
        <?php settings_fields(self::OPT_GROUP); ?>
        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row"><label><?php esc_html_e('Client Categories','bwocl'); ?></label></th>
              <td>
                <fieldset style="max-height:300px;overflow:auto;border:1px solid #e5e7eb;padding:10px;border-radius:8px;">
                <?php if ($cats && !is_wp_error($cats)): foreach($cats as $c): ?>
                  <label style="display:inline-flex;align-items:center;gap:6px;margin:6px 12px 6px 0;">
                    <input type="checkbox" name="<?php echo esc_attr(self::OPT_CLIENT_CATS); ?>[]" value="<?php echo (int)$c->term_id; ?>" <?php checked(in_array((int)$c->term_id, $chosen, true)); ?>>
                    <span><?php echo esc_html($c->name . ' ('.$c->slug.')'); ?></span>
                  </label>
                <?php endforeach; else: ?>
                  <p><?php esc_html_e('No product categories found. Create one per client first.', 'bwocl'); ?></p>
                <?php endif; ?>
                </fieldset>
                <p class="description"><?php esc_html_e('Select the WooCommerce product categories that correspond to clients/creators. A product should belong to exactly one of these to determine its client.', 'bwocl'); ?></p>
              </td>
            </tr>
            <tr>
              <th scope="row"><?php esc_html_e('Show Client in Emails','bwocl'); ?></th>
              <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_EMAIL_TAG); ?>" value="1" <?php checked($email,true); ?>> <?php esc_html_e('Append [Client: Name] to item names in customer/admin emails', 'bwocl'); ?></label></td>
            </tr>
            <tr>
              <th scope="row"><?php esc_html_e('Show Client in Admin','bwocl'); ?></th>
              <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_ADMIN_TAG); ?>" value="1" <?php checked($admin,true); ?>> <?php esc_html_e('Show badges next to items and add “Clients” column on Orders list', 'bwocl'); ?></label></td>
            </tr>
            <tr>
              <th scope="row"><?php esc_html_e('Cart Guard (single client per cart)','bwocl'); ?></th>
              <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_GUARD); ?>" value="1" <?php checked($guard,true); ?>> <?php esc_html_e('Prevent adding products from different clients in the same cart', 'bwocl'); ?></label></td>
            </tr>
          </tbody>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /* ================= Core: Determine client ================= */

  private function get_client_for_product($product_id){
    $client_cats = (array) get_option(self::OPT_CLIENT_CATS, []);
    if (!$client_cats) return [null, null]; // not configured
    $terms = wp_get_post_terms($product_id, 'product_cat', ['fields'=>'all']);
    if (is_wp_error($terms) || !$terms) return [null, null];
    foreach ($terms as $t){
      if (in_array((int)$t->term_id, $client_cats, true)){
        return [$t->name, $t->slug];
      }
    }
    return [null, null];
  }

  /* ================= Stamp item meta at checkout ================= */

  public function add_item_meta($item, $cart_item_key, $values, $order){
    $product = $item->get_product();
    if (!$product) return;
    [$name, $slug] = $this->get_client_for_product($product->get_id());
    if ($name){
      $item->add_meta_data('Client', $name, true); // visible meta
      $item->add_meta_data(self::META_CLIENT, $name, true); // internal copy
      $item->add_meta_data(self::META_CLIENT_SL, $slug, true);
    }
  }

  /* ================= Decorate item names ================= */

  public function decorate_item_name($item_name, $item, $is_visible){
    $order = $item->get_order();
    if (!$order) return $item_name;

    $show_email = get_option(self::OPT_EMAIL_TAG, true);
    $show_admin = get_option(self::OPT_ADMIN_TAG, true);

    $is_email = did_action('woocommerce_email_order_items_table') || did_action('woocommerce_email_order_meta');
    $is_admin = is_admin();

    if ((!$is_email && !$is_admin) || (!$show_email && $is_email) || (!$show_admin && $is_admin)) {
      return $item_name;
    }

    $client = $item->get_meta(self::META_CLIENT, true);
    if (!$client) $client = $item->get_meta('Client', true);
    if (!$client) return $item_name;

    $badge = '<span class="bwocl-badge">Client: '. esc_html($client) .'</span>';

    // Append badge on a new line to keep product name intact
    if ($is_admin) {
      return $item_name . '<div>'.$badge.'</div>';
    } else {
      return $item_name . '<br>'.$badge;
    }
  }

  /* ================= Admin Orders list column ================= */

  public function add_orders_column($cols){
    $before = [];
    foreach ($cols as $k=>$v){
      $before[$k] = $v;
      if ($k === 'order_total'){
        $before['bwocl_clients'] = __('Clients','bwocl');
      }
    }
    if (!isset($before['bwocl_clients'])) $before['bwocl_clients'] = __('Clients','bwocl');
    return $before;
  }

  public function render_orders_column($column, $post_id){
    if ($column !== 'bwocl_clients') return;
    $order = wc_get_order($post_id);
    if (!$order) { echo '—'; return; }
    $clients = [];
    foreach ($order->get_items() as $item){
      $c = $item->get_meta(self::META_CLIENT, true);
      if (!$c) $c = $item->get_meta('Client', true);
      if ($c) $clients[$c] = true;
    }
    if (!$clients) { echo '—'; return; }
    foreach (array_keys($clients) as $c){
      echo '<span class="bwocl-chip">'. esc_html($c) .'</span> ';
    }
  }

  public function admin_css(){
    $show_admin = get_option(self::OPT_ADMIN_TAG, true);
    if (!$show_admin) return;
    echo '<style>
      .bwocl-badge{display:inline-block;background:#111;color:#fff;border-radius:8px;padding:2px 8px;font-weight:700;font-size:11px;letter-spacing:.02em}
      .bwocl-chip{display:inline-block;background:#eef2f7;border:1px solid #dbe2ea;border-radius:999px;padding:2px 8px;font-weight:700;font-size:11px;margin:2px 0}
      .wc-order-item-name .bwocl-badge{margin-top:4px}
    </style>';
  }

  /* ================= Cart Guard (single client per cart) ================= */

  public function validate_same_client_only($passed, $product_id, $qty, $variation_id){
    $guard = get_option(self::OPT_GUARD, false);
    if (!$guard) return $passed;

    // client for the new item
    [$new_client, $new_slug] = $this->get_client_for_product($variation_id ?: $product_id);
    if (!$new_client) return $passed; // if no client detected, allow

    // detect existing client in cart
    if (WC()->cart && !WC()->cart->is_empty()){
      $existing = null;
      foreach (WC()->cart->get_cart() as $ci){
        $pid = $ci['variation_id'] ?: $ci['product_id'];
        [$cname] = $this->get_client_for_product($pid);
        if ($cname){ $existing = $cname; break; }
      }
      if ($existing && $existing !== $new_client){
        wc_add_notice(sprintf(
          __('You can only purchase items from one store at a time. Your cart has items for "%s".', 'bwocl'),
          $existing
        ), 'error');
        return false;
      }
    }
    return $passed;
  }
}

new BW_Order_Client_Labels();
