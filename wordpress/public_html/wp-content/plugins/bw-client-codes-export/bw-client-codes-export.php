<?php
/**
 * Plugin Name: BW Client Codes, Commissions & Production Export
 * Description: Map client categories to Codes and default Commission (percent or fixed). Stamp orders with Client/Code/Production SKU/Spec/Personalization. Exports: Production CSV + Commissions (line items and summary by client).
 * Version: 1.2.1
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

class BW_Client_Codes_Export {
  /* ============ Options ============ */
  const OPT_GROUP   = 'bwcc_settings';

  // Maps: term_id => code, term_id => ['type'=>'percent|fixed|none','value'=>float]
  const OPT_MAP_CODE   = 'bwcc_client_map_code';    // array: term_id => CODE
  const OPT_MAP_COMM   = 'bwcc_client_map_comm';    // array: term_id => ['type'=>'percent|fixed|none','value'=>float]

  const OPT_EMAIL   = 'bwcc_show_emails'; // bool
  const OPT_ADMIN   = 'bwcc_show_admin';  // bool

  /* ============ Product meta (override) ============ */
  const PM_COMM_MODE  = '_bw_commission_mode';  // inherit|percent|fixed|none
  const PM_COMM_VALUE = '_bw_commission_value'; // float

  /* ============ Stored line item meta keys ============ */
  const META_CLIENT = '_bw_client_name';
  const META_CODE   = '_bw_client_code';
  const META_PSKU   = '_bw_production_sku';
  const META_SPEC   = '_bw_spec_line';
  const META_PERS   = '_bw_personalization';

  const META_COMM_MODE  = '_bw_commission_mode';
  const META_COMM_BASE  = '_bw_commission_base';   // line total excl tax used
  const META_COMM_RATE  = '_bw_commission_rate';   // percent number or fixed amount per item
  const META_COMM_TYPE  = '_bw_commission_type';   // percent|fixed|none
  const META_COMM_AMT   = '_bw_commission_amount'; // computed amount for this line (numeric)

  public function __construct(){
    // Settings + admin pages
    add_action('admin_menu',  [$this,'settings_page']);
    add_action('admin_init',  [$this,'register_settings']);

    // Product meta box (override commission)
    add_action('add_meta_boxes', [$this,'product_metabox']);
    add_action('save_post_product', [$this,'save_product_meta']);

    // Stamp order items
    add_action('woocommerce_checkout_create_order_line_item', [$this,'stamp_item_meta'], 12, 4);

    // Decorate item names in admin/emails
    add_filter('woocommerce_order_item_name', [$this,'decorate_item_name'], 10, 3);
    add_action('admin_head', [$this,'admin_css']);

    // Export screens
    add_action('admin_menu', [$this,'export_menus']);
    add_action('admin_post_bwcc_export_csv',    [$this,'handle_export_production']);
    add_action('admin_post_bwcc_export_comm',   [$this,'handle_export_commissions']);
    
    add_action('wp_dashboard_setup', [$this,'register_dashboard_widget']);
  }

  /* ================= Settings page ================= */
  public function settings_page(){
    add_submenu_page(
      'woocommerce',
      __('Client Codes & Commissions','bwcc'),
      __('Client Codes & Commissions','bwcc'),
      'manage_woocommerce',
      'bwcc-settings',
      [$this,'render_settings']
    );
  }

  public function register_settings(){
    register_setting(self::OPT_GROUP, self::OPT_MAP_CODE, [
      'type'=>'array',
      'sanitize_callback'=>function($in){
        $out = [];
        foreach ((array)$in as $term_id=>$code){
          $tid = (int)$term_id;
          $c   = strtoupper(preg_replace('/[^A-Z0-9\-]/i','', (string)$code));
          if ($tid && $c !== '') $out[$tid] = $c;
        }
        return $out;
      },
      'default'=>[],
    ]);

    register_setting(self::OPT_GROUP, self::OPT_MAP_COMM, [
      'type'=>'array',
      'sanitize_callback'=>function($in){
        $out = [];
        foreach ((array)$in as $term_id=>$arr){
          $tid = (int)$term_id;
          $type = isset($arr['type']) && in_array($arr['type'], ['percent','fixed','none'], true) ? $arr['type'] : 'none';
          $val  = isset($arr['value']) ? floatval($arr['value']) : 0;
          if ($val < 0) $val = 0;
          $out[$tid] = ['type'=>$type, 'value'=>$val];
        }
        return $out;
      },
      'default'=>[],
    ]);

    register_setting(self::OPT_GROUP, self::OPT_EMAIL, ['type'=>'boolean','default'=>true, 'sanitize_callback'=>[$this,'bool']]);
    register_setting(self::OPT_GROUP, self::OPT_ADMIN, ['type'=>'boolean','default'=>true, 'sanitize_callback'=>[$this,'bool']]);
  }

  public function bool($v){ return (bool) (is_string($v) ? ($v==='1' || $v==='on') : $v); }

  public function render_settings(){
    if (!current_user_can('manage_woocommerce')) return;
    $cats  = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
    $codes = (array) get_option(self::OPT_MAP_CODE, []);
    $comms = (array) get_option(self::OPT_MAP_COMM, []);
    $email = get_option(self::OPT_EMAIL, true);
    $admin = get_option(self::OPT_ADMIN, true);
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Client Codes & Commissions','bwcc'); ?></h1>
      <form method="post" action="options.php">
        <?php settings_fields(self::OPT_GROUP); ?>
        <table class="widefat striped" style="max-width:980px;">
          <thead>
            <tr>
              <th><?php esc_html_e('Client Category','bwcc'); ?></th>
              <th><?php esc_html_e('Code','bwcc'); ?></th>
              <th><?php esc_html_e('Default Commission Type','bwcc'); ?></th>
              <th><?php esc_html_e('Default Commission Value','bwcc'); ?></th>
            </tr>
          </thead>
          <tbody>
          <?php if ($cats && !is_wp_error($cats)): foreach($cats as $c):
            $code = $codes[$c->term_id] ?? '';
            $comm = $comms[$c->term_id] ?? ['type'=>'none','value'=>0];
          ?>
            <tr>
              <td><?php echo esc_html($c->name . ' ('.$c->slug.')'); ?></td>
              <td><input type="text" name="<?php echo esc_attr(self::OPT_MAP_CODE); ?>[<?php echo (int)$c->term_id; ?>]" value="<?php echo esc_attr($code); ?>" placeholder="e.g., JN" style="width:100px;text-transform:uppercase"></td>
              <td>
                <select name="<?php echo esc_attr(self::OPT_MAP_COMM); ?>[<?php echo (int)$c->term_id; ?>][type]">
                  <?php foreach (['none'=>'None','percent'=>'Percent (%)','fixed'=>'Fixed ($ per item)'] as $k=>$lbl): ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($comm['type'] ?? 'none', $k); ?>><?php echo esc_html($lbl); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" step="0.01" min="0" name="<?php echo esc_attr(self::OPT_MAP_COMM); ?>[<?php echo (int)$c->term_id; ?>][value]" value="<?php echo esc_attr($comm['value'] ?? 0); ?>" style="width:120px"></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>

        <table class="form-table" role="presentation" style="margin-top:16px;max-width:980px;">
          <tbody>
            <tr>
              <th scope="row"><?php esc_html_e('Show in Emails','bwcc'); ?></th>
              <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_EMAIL); ?>" value="1" <?php checked($email,true); ?>> <?php esc_html_e('Append [CLIENT: CODE] to items in customer/admin emails', 'bwcc'); ?></label></td>
            </tr>
            <tr>
              <th scope="row"><?php esc_html_e('Show in Admin','bwcc'); ?></th>
              <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_ADMIN); ?>" value="1" <?php checked($admin,true); ?>> <?php esc_html_e('Show client badges on order items in admin', 'bwcc'); ?></label></td>
            </tr>
          </tbody>
        </table>

        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /* ================= Product meta: commission override ================= */
  public function product_metabox(){
    add_meta_box('bwcc_comm', __('Commission (Admin Only)','bwcc'), [$this,'render_product_meta'], 'product', 'side', 'high');
  }

  public function render_product_meta($post){
    wp_nonce_field('bwcc_comm_save','bwcc_comm_nonce');
    $mode  = get_post_meta($post->ID, self::PM_COMM_MODE, true) ?: 'inherit';
    $value = get_post_meta($post->ID, self::PM_COMM_VALUE, true);
    if ($value === '') $value = '';
    ?>
    <p>
      <label for="bwcc_comm_mode"><strong><?php esc_html_e('Commission Mode','bwcc'); ?></strong></label><br>
      <select id="bwcc_comm_mode" name="bwcc_comm_mode">
        <option value="inherit" <?php selected($mode,'inherit'); ?>><?php esc_html_e('Inherit from Client default','bwcc'); ?></option>
        <option value="percent" <?php selected($mode,'percent'); ?>><?php esc_html_e('Percent (%)','bwcc'); ?></option>
        <option value="fixed"   <?php selected($mode,'fixed');   ?>><?php esc_html_e('Fixed ($ per item)','bwcc'); ?></option>
        <option value="none"    <?php selected($mode,'none');    ?>><?php esc_html_e('No commission','bwcc'); ?></option>
      </select>
    </p>
    <p>
      <label for="bwcc_comm_value"><strong><?php esc_html_e('Commission Value','bwcc'); ?></strong></label><br>
      <input type="number" step="0.01" min="0" id="bwcc_comm_value" name="bwcc_comm_value" value="<?php echo esc_attr($value); ?>" placeholder="<?php esc_attr_e('Leave empty to use client default','bwcc'); ?>" style="width:100%;">
    </p>
    <p class="howto"><?php esc_html_e('Admins only. Customers never see these fields.','bwcc'); ?></p>
    <?php
  }

  public function save_product_meta($post_id){
    if (!isset($_POST['bwcc_comm_nonce']) || !wp_verify_nonce($_POST['bwcc_comm_nonce'],'bwcc_comm_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;

    $mode  = isset($_POST['bwcc_comm_mode']) ? sanitize_text_field($_POST['bwcc_comm_mode']) : 'inherit';
    if (!in_array($mode, ['inherit','percent','fixed','none'], true)) $mode = 'inherit';
    $value = isset($_POST['bwcc_comm_value']) && $_POST['bwcc_comm_value'] !== '' ? max(0, floatval($_POST['bwcc_comm_value'])) : '';

    update_post_meta($post_id, self::PM_COMM_MODE, $mode);
    if ($value === '') delete_post_meta($post_id, self::PM_COMM_VALUE);
    else update_post_meta($post_id, self::PM_COMM_VALUE, $value);
  }

  /* ================= Helpers ================= */

  private function get_client_for_product($product_id){
    // read product_cat terms; find first that has a code configured (or commission entry)
    $code_map = (array) get_option(self::OPT_MAP_CODE, []);
    $comm_map = (array) get_option(self::OPT_MAP_COMM, []);
    $terms = wp_get_post_terms($product_id, 'product_cat', ['fields'=>'all']);
    if (is_wp_error($terms) || !$terms) return [null,null,null,['type'=>'none','value'=>0]];

    foreach ($terms as $t){
      $code = $code_map[$t->term_id] ?? '';
      $comm = $comm_map[$t->term_id] ?? ['type'=>'none','value'=>0];
      if ($code || isset($comm_map[$t->term_id])) {
        return [$t->name, $t->slug, $code, $comm];
      }
    }
    // Fallback: first term (no code/commission set)
    $t = $terms[0];
    return [$t->name ?? null, $t->slug ?? null, '', ['type'=>'none','value'=>0]];
  }

  private function build_spec_from_item($item){
    $specs = [];
    foreach ($item->get_formatted_meta_data() as $m){
      $k = $m->display_key;
      $v = wp_strip_all_tags($m->display_value);
      if (in_array($k, ['Client','Client Code','Production SKU','Personalization','Spec','Commission','Commission Amount'], true)) continue;
      $specs[] = "$k: $v";
    }
    return $specs ? implode(' | ', $specs) : '';
  }

  private function collect_personalization($item){
    $keys = ['Personalization','Custom Text','Name Text','Number Text','Line 1','Line 2'];
    $found = [];
    foreach ($item->get_formatted_meta_data() as $m){
      $k = $m->display_key;
      $v = trim(wp_strip_all_tags($m->display_value));
      if ($v === '') continue;
      if (in_array($k, $keys, true)) $found[] = "$k: $v";
    }
    return $found ? implode(' | ', $found) : '';
  }

  private function resolve_commission_for_product($product_id){
    // Returns [type, value] after applying product override or client default
    $mode  = get_post_meta($product_id, self::PM_COMM_MODE, true) ?: 'inherit';
    $value = get_post_meta($product_id, self::PM_COMM_VALUE, true);
    if ($value === '') $value = null;

    if ($mode === 'percent' || $mode === 'fixed' || $mode==='none') {
      return [$mode, $value !== null ? (float)$value : 0.0];
    }

    // inherit from client
    [, , , $client_comm] = $this->get_client_for_product($product_id);
    $type  = $client_comm['type'] ?? 'none';
    $val   = (float)($client_comm['value'] ?? 0);
    return [$type, $val];
  }

  /* ================= Stamp order items ================= */

  public function stamp_item_meta($item, $cart_item_key, $values, $order){
    $product = $item->get_product();
    if (!$product) return;

    [$client_name, $client_slug, $code] = $this->get_client_for_product($product->get_id());

    // Production SKU
    $sku = $product->get_sku();
    if (!$sku) $sku = 'ID'.$product->get_id();
    $psku = $code ? ($code . '-' . $sku) : $sku;

    // Spec + personalization
    $spec = $this->build_spec_from_item($item);
    $pers = $this->collect_personalization($item);

    // Commission
    list($ctype, $cval) = $this->resolve_commission_for_product($product->get_id());

    // Woo stores line totals on the item; get_total() excludes tax
    $line_total_ex_tax = (float) $item->get_total();
    if (!is_numeric($line_total_ex_tax)) $line_total_ex_tax = 0.0;

    $qty = (int) $item->get_quantity();
    $commission_amount = 0.0;
    if ($ctype === 'percent' && $cval > 0){
      $commission_amount = round(($cval / 100.0) * $line_total_ex_tax, 2);
    } elseif ($ctype === 'fixed' && $cval > 0){
      $commission_amount = round($cval * $qty, 2);
    } elseif ($ctype === 'none'){
      $commission_amount = 0.0;
    }

    // Visible meta (nice labels)
    if ($client_name)            $item->add_meta_data('Client', $client_name, true);
    if ($code)                   $item->add_meta_data('Client Code', $code, true);
    if ($psku)                   $item->add_meta_data('Production SKU', $psku, true);
    if ($spec !== '')            $item->add_meta_data('Spec', $spec, true);
    if ($pers !== '')            $item->add_meta_data('Personalization', $pers, true);
    if ($ctype !== 'none') {
      $label = ($ctype==='percent') ? sprintf('Commission: %s%%', $cval) : sprintf('Commission: $%0.2f per item', $cval);
      $item->add_meta_data('Commission', $label, true);
      $item->add_meta_data('Commission Amount', '$'.number_format($commission_amount,2), true);
    }

    // Internal numeric copies for export
    if ($client_name) $item->add_meta_data(self::META_CLIENT, $client_name, true);
    if ($code)        $item->add_meta_data(self::META_CODE,   $code, true);
    if ($psku)        $item->add_meta_data(self::META_PSKU,   $psku, true);
    if ($spec)        $item->add_meta_data(self::META_SPEC,   $spec, true);
    if ($pers)        $item->add_meta_data(self::META_PERS,   $pers, true);

    $item->add_meta_data(self::META_COMM_MODE,  $ctype, true);
    $item->add_meta_data(self::META_COMM_BASE,  $line_total_ex_tax, true);
    $item->add_meta_data(self::META_COMM_RATE,  $cval, true);
    $item->add_meta_data(self::META_COMM_TYPE,  $ctype, true);
    $item->add_meta_data(self::META_COMM_AMT,   $commission_amount, true);
  }

  /* ================= Decorate item names ================= */

  public function decorate_item_name($item_name, $item, $is_visible){
    $order = $item->get_order();
    if (!$order) return $item_name;

    $is_email = did_action('woocommerce_email_order_items_table') || did_action('woocommerce_email_order_meta');
    $is_admin = is_admin();

    $show_email = get_option(self::OPT_EMAIL, true);
    $show_admin = get_option(self::OPT_ADMIN, true);

    if (($is_email && !$show_email) || ($is_admin && !$show_admin)) return $item_name;

    $client = $item->get_meta(self::META_CLIENT, true) ?: $item->get_meta('Client', true);
    $code   = $item->get_meta(self::META_CODE, true)   ?: $item->get_meta('Client Code', true);
    if (!$client && !$code) return $item_name;

    $label = $code ? "CLIENT: $code" : "CLIENT: $client";
    $badge = $is_admin
      ? '<span class="bwcc-badge">'.$label.'</span>'
      : '<strong>['.esc_html($label).']</strong>';

    return $is_admin ? ($item_name . '<div>'.$badge.'</div>') : ($item_name . '<br>' . $badge);
  }

  public function admin_css(){
    echo '<style>.bwcc-badge{display:inline-block;background:#111;color:#fff;border-radius:8px;padding:2px 8px;font-weight:700;font-size:11px;letter-spacing:.02em;margin-top:4px}</style>';
  }

  /* ================= Export menus ================= */

  public function export_menus(){
    add_submenu_page(
      'woocommerce',
      __('Production Export','bwcc'),
      __('Production Export','bwcc'),
      'manage_woocommerce',
      'bwcc-export',
      [$this,'render_export_production']
    );
    add_submenu_page(
      'woocommerce',
      __('Commissions Export','bwcc'),
      __('Commissions Export','bwcc'),
      'manage_woocommerce',
      'bwcc-comm',
      [$this,'render_export_commissions']
    );
  }

  /* ================= Production Export UI ================= */

  public function render_export_production(){
    if (!current_user_can('manage_woocommerce')) return; ?>
    <div class="wrap">
      <h1><?php esc_html_e('Production Export (CSV)','bwcc'); ?></h1>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="max-width:740px;padding:16px;">
        <input type="hidden" name="action" value="bwcc_export_csv">
        <?php wp_nonce_field('bwcc_export','bwcc_nonce'); ?>
        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row"><label for="bw_from"><?php esc_html_e('From date','bwcc'); ?></label></th>
              <td><input type="date" id="bw_from" name="from" required></td>
            </tr>
            <tr>
              <th scope="row"><label for="bw_to"><?php esc_html_e('To date','bwcc'); ?></label></th>
              <td><input type="date" id="bw_to" name="to" required></td>
            </tr>
            <tr>
              <th scope="row"><label for="bw_status"><?php esc_html_e('Order status','bwcc'); ?></label></th>
              <td>
                <select id="bw_status" name="status">
                  <option value="processing"><?php esc_html_e('Processing','bwcc'); ?></option>
                  <option value="completed"><?php esc_html_e('Completed','bwcc'); ?></option>
                  <option value="any"><?php esc_html_e('Any','bwcc'); ?></option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
        <?php submit_button(__('Download CSV','bwcc')); ?>
      </form>
    </div>
    <?php
  }

  /* ================= Commissions Export UI ================= */

  public function render_export_commissions(){
    if (!current_user_can('manage_woocommerce')) return; ?>
    <div class="wrap">
      <h1><?php esc_html_e('Commissions Export (CSV)','bwcc'); ?></h1>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="max-width:820px;padding:16px;">
        <input type="hidden" name="action" value="bwcc_export_comm">
        <?php wp_nonce_field('bwcc_comm_export','bwcc_comm_nonce'); ?>
        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row"><label for="bw_comm_from"><?php esc_html_e('From date','bwcc'); ?></label></th>
              <td><input type="date" id="bw_comm_from" name="from" required></td>
            </tr>
            <tr>
              <th scope="row"><label for="bw_comm_to"><?php esc_html_e('To date','bwcc'); ?></label></th>
              <td><input type="date" id="bw_comm_to" name="to" required></td>
            </tr>
            <tr>
              <th scope="row"><label for="bw_comm_status"><?php esc_html_e('Order status','bwcc'); ?></label></th>
              <td>
                <select id="bw_comm_status" name="status">
                  <option value="processing"><?php esc_html_e('Processing','bwcc'); ?></option>
                  <option value="completed"><?php esc_html_e('Completed','bwcc'); ?></option>
                  <option value="any"><?php esc_html_e('Any','bwcc'); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><?php esc_html_e('Mode','bwcc'); ?></th>
              <td>
                <label><input type="radio" name="mode" value="line" checked> <?php esc_html_e('Line items (detailed)','bwcc'); ?></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="mode" value="summary"> <?php esc_html_e('Summary by client','bwcc'); ?></label>
              </td>
            </tr>
          </tbody>
        </table>
        <?php submit_button(__('Download CSV','bwcc')); ?>
      </form>
    </div>
    <?php
  }

  /* ================= Production CSV handler ================= */

  public function handle_export_production(){
    if (!current_user_can('manage_woocommerce')) wp_die('No permission');
    if (!isset($_POST['bwcc_nonce']) || !wp_verify_nonce($_POST['bwcc_nonce'],'bwcc_export')) wp_die('Bad nonce');

    $from   = sanitize_text_field($_POST['from'] ?? '');
    $to     = sanitize_text_field($_POST['to'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? 'processing');
    if (!$from || !$to) wp_die('Missing dates');

    $args = [
      'limit'        => -1,
      'type'         => 'shop_order',
      'date_created' => $from.'...'.$to,
    ];
    if ($status !== 'any') $args['status'] = [$status];

    $orders = wc_get_orders($args);

    // CSV headers
    $filename = 'production-' . $from . '_to_' . $to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$filename);
    $out = fopen('php://output', 'w');

    fputcsv($out, ['Order #','Date','Client','Client Code','Production SKU','Product','Spec','Personalization','Qty','Notes']);

    foreach ($orders as $order){
      $order_id = $order->get_id();
      $date     = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '';
      $note     = '';

      foreach ($order->get_items() as $item_id => $item){
        $client = $item->get_meta(self::META_CLIENT, true) ?: $item->get_meta('Client', true);
        $code   = $item->get_meta(self::META_CODE, true)   ?: $item->get_meta('Client Code', true);
        $psku   = $item->get_meta(self::META_PSKU, true)   ?: $item->get_meta('Production SKU', true);
        $spec   = $item->get_meta(self::META_SPEC, true)   ?: $item->get_meta('Spec', true);
        $pers   = $item->get_meta(self::META_PERS, true)   ?: $item->get_meta('Personalization', true);

        $prod_name = wp_strip_all_tags($item->get_name());
        $qty       = (int) $item->get_quantity();

        fputcsv($out, [
          $order->get_order_number(),
          $date,
          $client,
          $code,
          $psku,
          $prod_name,
          $spec,
          $pers,
          $qty,
          $note,
        ]);
      }
    }
    fclose($out);
    exit;
  }

  /* ================= Commissions CSV handler ================= */

  public function handle_export_commissions(){
    if (!current_user_can('manage_woocommerce')) wp_die('No permission');
    if (!isset($_POST['bwcc_comm_nonce']) || !wp_verify_nonce($_POST['bwcc_comm_nonce'],'bwcc_comm_export')) wp_die('Bad nonce');

    $from   = sanitize_text_field($_POST['from'] ?? '');
    $to     = sanitize_text_field($_POST['to'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? 'processing');
    $mode   = sanitize_text_field($_POST['mode'] ?? 'line');
    if (!$from || !$to) wp_die('Missing dates');

    $args = [
      'limit'        => -1,
      'type'         => 'shop_order',
      'date_created' => $from.'...'.$to,
    ];
    if ($status !== 'any') $args['status'] = [$status];

    $orders = wc_get_orders($args);

    if ($mode === 'summary'){
      $this->export_commissions_summary($orders, $from, $to);
    } else {
      $this->export_commissions_line($orders, $from, $to);
    }
  }

  private function export_commissions_line($orders, $from, $to){
    $filename = 'commissions-line-' . $from . '_to_' . $to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$filename);
    $out = fopen('php://output', 'w');

    fputcsv($out, ['Order #','Date','Client','Client Code','Product','Qty','Line Total (ex tax)','Commission Type','Rate','Commission Amount']);

    foreach ($orders as $order){
      $date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '';
      foreach ($order->get_items() as $item){
        $client = $item->get_meta(self::META_CLIENT, true) ?: $item->get_meta('Client', true);
        $code   = $item->get_meta(self::META_CODE, true)   ?: $item->get_meta('Client Code', true);

        $line_total = (float) $item->get_total();
                $ctype = $item->get_meta(self::META_COMM_TYPE, true) ?: 'none';
        $crate = (float) ($item->get_meta(self::META_COMM_RATE, true) ?: 0);
        $camt  = (float) ($item->get_meta(self::META_COMM_AMT, true)  ?: 0);
        $qty   = (int) $item->get_quantity();
        $pname = wp_strip_all_tags($item->get_name());

        fputcsv($out, [
          $order->get_order_number(),
          $date,
          $client,
          $code,
          $pname,
          $qty,
          number_format($line_total, 2, '.', ''),
          $ctype,
          ($ctype === 'percent' ? number_format($crate, 2, '.', '') : number_format($crate, 2, '.', '')),
          number_format($camt, 2, '.', ''),
        ]);
      }
    }
    fclose($out);
    exit;
  }

  private function export_commissions_summary($orders, $from, $to){
    $summary = []; // key by code+name for stability
    $order_counts = []; // orders per client
    $item_counts  = []; // items per client

    foreach ($orders as $order){
      $clients_seen_in_order = [];
      foreach ($order->get_items() as $item){
        $client = $item->get_meta(self::META_CLIENT, true) ?: $item->get_meta('Client', true) ?: 'Unassigned';
        $code   = $item->get_meta(self::META_CODE, true)   ?: $item->get_meta('Client Code', true) ?: '';
        $key    = $code.'|'.$client;

        $line_total = (float) $item->get_total();
        $camt       = (float) ($item->get_meta(self::META_COMM_AMT, true) ?: 0);
        $qty        = (int) $item->get_quantity();

        if (!isset($summary[$key])) {
          $summary[$key] = ['client'=>$client, 'code'=>$code, 'gross'=>0.0, 'commission'=>0.0];
          $item_counts[$key]  = 0;
          $order_counts[$key] = 0;
        }

        $summary[$key]['gross']      += $line_total;
        $summary[$key]['commission'] += $camt;
        $item_counts[$key]           += $qty;

        // count this order once per client
        if (!isset($clients_seen_in_order[$key])) {
          $order_counts[$key] += 1;
          $clients_seen_in_order[$key] = true;
        }
      }
    }

    // Output CSV
    $filename = 'commissions-summary-' . $from . '_to_' . $to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$filename);
    $out = fopen('php://output', 'w');

    fputcsv($out, ['Client','Client Code','Orders (#)','Items (#)','Gross (ex tax)','Commission Total','Period From','Period To']);

    $tot_orders = 0; $tot_items = 0; $tot_gross = 0.0; $tot_comm = 0.0;

    foreach ($summary as $key => $row){
      $client = $row['client'];
      $code   = $row['code'];
      $orders_n = (int) ($order_counts[$key] ?? 0);
      $items_n  = (int) ($item_counts[$key] ?? 0);
      $gross    = (float) $row['gross'];
      $comm     = (float) $row['commission'];

      $tot_orders += $orders_n;
      $tot_items  += $items_n;
      $tot_gross  += $gross;
      $tot_comm   += $comm;

      fputcsv($out, [
        $client,
        $code,
        $orders_n,
        $items_n,
        number_format($gross, 2, '.', ''),
        number_format($comm,  2, '.', ''),
        $from,
        $to,
      ]);
    }

    // Totals row
    fputcsv($out, [
      'TOTAL',
      '',
      $tot_orders,
      $tot_items,
      number_format($tot_gross, 2, '.', ''),
      number_format($tot_comm,  2, '.', ''),
      $from,
      $to,
    ]);

    fclose($out);
    exit;
  }
  /* ================= Dashboard Widget ================= */

public function register_dashboard_widget(){
  wp_add_dashboard_widget(
    'bwcc_widget',
    __('Client Sales & Commissions','bwcc'),
    [$this,'render_dashboard_widget']
  );
}

private function bwcc_range_dates($range){
  // Calculate date range in site timezone
  $now = current_time('timestamp');
  $y = (int) date('Y', $now);
  $m = (int) date('n', $now);

  switch ($range) {
    case 'lastmonth':
      $start = mktime(0,0,0, $m-1, 1, $y);
      $end   = mktime(23,59,59, $m, 0, $y); // last day prev month
      break;
    case 'quarter':
      $q = (int) ceil($m/3);
      $q_start_month = ($q-1)*3 + 1;
      $start = mktime(0,0,0, $q_start_month, 1, $y);
      $end   = mktime(23,59,59, $q_start_month+3, 0, $y);
      break;
    case 'ytd':
      $start = mktime(0,0,0, 1, 1, $y);
      $end   = $now;
      break;
    case 'month':
    default:
      $start = mktime(0,0,0, $m, 1, $y);
      $end   = mktime(23,59,59, $m+1, 0, $y); // last day of this month
      break;
  }
  return [ date('Y-m-d', $start), date('Y-m-d', $end) ];
}

private function bwcc_compute_stats($from_date, $to_date){
  // 10-minute cache
  $key = 'bwcc_dash_' . md5($from_date.'|'.$to_date);
  $cached = get_transient($key);
  if ($cached) return $cached;

  $args = [
    'limit'        => -1,
    'type'         => 'shop_order',
    'date_created' => $from_date.'...'.$to_date,
    'status'       => ['processing','completed'], // feel free to tweak
    'return'       => 'objects',
  ];
  $orders = wc_get_orders($args);

  $summary = []; // key code|name
  $order_seen = []; // order->client counted once

  $tot_orders = 0;
  $tot_items  = 0;
  $tot_gross  = 0.0;
  $tot_comm   = 0.0;

  foreach ($orders as $order){
    $order_id = $order->get_id();
    $clients_seen_in_order = [];

    foreach ($order->get_items() as $item){
      $client = $item->get_meta(self::META_CLIENT, true) ?: $item->get_meta('Client', true) ?: 'Unassigned';
      $code   = $item->get_meta(self::META_CODE,   true) ?: $item->get_meta('Client Code', true) ?: '';
      $key    = $code.'|'.$client;

      $line_total = (float) $item->get_total(); // ex tax
      $camt       = (float) ($item->get_meta(self::META_COMM_AMT, true) ?: 0);
      $qty        = (int) $item->get_quantity();

      if (!isset($summary[$key])) {
        $summary[$key] = ['client'=>$client, 'code'=>$code, 'orders'=>0, 'items'=>0, 'gross'=>0.0, 'commission'=>0.0];
      }

      $summary[$key]['items']      += $qty;
      $summary[$key]['gross']      += $line_total;
      $summary[$key]['commission'] += $camt;

      if (!isset($clients_seen_in_order[$key])) {
        $summary[$key]['orders'] += 1;
        $clients_seen_in_order[$key] = true;
      }

      $tot_items += $qty;
      $tot_gross += $line_total;
      $tot_comm  += $camt;
    }

    // Count this order once in totals if it had any items
    if (!empty($clients_seen_in_order)) $tot_orders += 1;
  }

  // Sort by gross desc and take top 10 for display
  uasort($summary, function($a,$b){
    if ($a['gross'] == $b['gross']) return 0;
    return ($a['gross'] > $b['gross']) ? -1 : 1;
  });
  $top = array_slice($summary, 0, 10, true);

  $data = [
    'top'        => $top,
    'tot_orders' => $tot_orders,
    'tot_items'  => $tot_items,
    'tot_gross'  => $tot_gross,
    'tot_comm'   => $tot_comm,
  ];
  set_transient($key, $data, MINUTE_IN_SECONDS * 10);
  return $data;
}

public function render_dashboard_widget(){
  // Range picker via query arg on Dashboard
  $range = isset($_GET['bwcc_range']) ? sanitize_text_field($_GET['bwcc_range']) : 'month';
  if (!in_array($range, ['month','lastmonth','quarter','ytd'], true)) $range = 'month';
  list($from, $to) = $this->bwcc_range_dates($range);

  $stats = $this->bwcc_compute_stats($from, $to);

  // Build base URL to dashboard
  $base = admin_url('index.php');

  // Basic styles
  echo '<style>
    .bwcc-dash small{opacity:.8}
    .bwcc-dash .totals{display:flex;gap:16px;flex-wrap:wrap;margin:8px 0 14px}
    .bwcc-dash .pill{background:#f5f7fa;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px}
    .bwcc-dash table{width:100%;border-collapse:collapse}
    .bwcc-dash th,.bwcc-dash td{padding:8px;border-bottom:1px solid #eef2f7;text-align:left}
    .bwcc-dash th{text-transform:uppercase;font-size:11px;letter-spacing:.04em;color:#556}
    .bwcc-dash .right{text-align:right}
    .bwcc-range a{margin-right:8px;text-decoration:none}
    .bwcc-range a.active{font-weight:700;text-decoration:underline}
  </style>';

  echo '<div class="bwcc-dash">';

  echo '<div class="bwcc-range">';
  foreach (['month'=>'This Month','lastmonth'=>'Last Month','quarter'=>'This Quarter','ytd'=>'YTD'] as $k=>$label){
    $url = esc_url(add_query_arg('bwcc_range', $k, $base));
    $cls = $range === $k ? 'class="active"' : '';
    echo "<a href=\"$url\" $cls>$label</a>";
  }
  echo '<small style="margin-left:8px;">Range: '.esc_html($from).' → '.esc_html($to).'</small>';
  echo '</div>';

  echo '<div class="totals">';
  echo '<div class="pill"><strong>Orders</strong><br>'.number_format($stats['tot_orders']).'</div>';
  echo '<div class="pill"><strong>Items</strong><br>'.number_format($stats['tot_items']).'</div>';
  echo '<div class="pill"><strong>Gross (ex)</strong><br>$'.number_format($stats['tot_gross'],2).'</div>';
  echo '<div class="pill"><strong>Commission</strong><br>$'.number_format($stats['tot_comm'],2).'</div>';
  echo '</div>';

  echo '<table>';
  echo '<thead><tr><th>Client</th><th class="right">Orders</th><th class="right">Items</th><th class="right">Gross (ex)</th><th class="right">Commission</th></tr></thead><tbody>';

  if (!empty($stats['top'])){
    foreach ($stats['top'] as $row){
      $label = $row['code'] ? '['.esc_html($row['code']).'] '.esc_html($row['client']) : esc_html($row['client']);
      echo '<tr>';
      echo '<td>'.$label.'</td>';
      echo '<td class="right">'.number_format($row['orders']).'</td>';
      echo '<td class="right">'.number_format($row['items']).'</td>';
      echo '<td class="right">$'.number_format($row['gross'],2).'</td>';
      echo '<td class="right">$'.number_format($row['commission'],2).'</td>';
      echo '</tr>';
    }
  } else {
    echo '<tr><td colspan="5"><em>No orders in this period.</em></td></tr>';
  }

  echo '</tbody></table>';
  echo '</div>';
}

}



/* bootstrap */
new BW_Client_Codes_Export();

