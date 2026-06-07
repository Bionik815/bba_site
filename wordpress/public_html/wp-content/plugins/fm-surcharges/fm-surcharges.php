<?php
/**
 * Plugin Name: FM Surcharges (Attribute Surcharges + Custom Text + Optional Vinyl)
 * Description: Attribute term surcharges (e.g., 2XL +$). One live “Total” price under Custom Text, with notes. Custom Text add-on (price, fields, per-field limits/counters, validation). Optional Vinyl upgrade. Correct pricing in cart/checkout.
 * Version: 1.7.1
 * Author: You
 * License: GPL-2.0+
 */
if (!defined('ABSPATH')) exit;

class FM_Surcharges {
  const SUR_META   = '_fm_term_surcharge';
  const VINYL_META = '_fm_vinyl_price';
  const CT_ENABLED = '_fm_ct_enabled';
  const CT_PRICE   = '_fm_ct_price';
  const CT_MAX     = '_fm_ct_max_chars';
  const CT_MAX1    = '_fm_ct_max1';
  const CT_MAX2    = '_fm_ct_max2';
  const CT_LINES   = '_fm_ct_lines';
  const CT_UI      = '_fm_ct_ui';
  const CT_L1      = '_fm_ct_label1';
  const CT_L2      = '_fm_ct_label2';
  const CT_UPPER   = '_fm_ct_uppercase';
  const CT_COUNTER = '_fm_ct_counter';
  const CT_ALLOWED = '_fm_ct_allowed';

  public function __construct(){
    add_action('admin_init', [$this,'hook_attribute_term_fields']);
    add_action('created_term', [$this,'save_term_meta'], 10, 3);
    add_action('edited_term',  [$this,'save_term_meta'], 10, 3);
    add_action('add_meta_boxes',    [$this,'add_product_meta_boxes']);
    add_action('save_post_product', [$this,'save_product_meta']);
    add_action('woocommerce_before_add_to_cart_button', [$this,'render_custom_text_block'], 12);
    add_action('woocommerce_before_add_to_cart_button', [$this,'render_price_wrap'], 12);
    add_action('woocommerce_before_add_to_cart_button', [$this,'render_vinyl_checkbox'], 13);
    add_filter('woocommerce_add_to_cart_validation', [$this,'validate_before_add_to_cart'], 10, 3);
    add_filter('woocommerce_add_cart_item_data',     [$this,'add_cart_item_data'], 10, 3);
    add_filter('woocommerce_get_item_data',          [$this,'display_cart_item_data'], 10, 2);
    add_action('woocommerce_before_calculate_totals', [$this,'apply_surcharges'], 20);
    add_filter('woocommerce_available_variation', [$this,'inject_display_surcharges_into_variation'], 10, 3);
    add_filter('woocommerce_variable_price_html', [$this,'filter_variable_price_range_html'], 10, 2);
    add_action('wp_enqueue_scripts', [$this,'enqueue_front_assets']);
  }

  public function hook_attribute_term_fields(){
    $taxes = wc_get_attribute_taxonomies(); if (!$taxes) return;
    foreach ($taxes as $att) {
      $tax = wc_attribute_taxonomy_name($att->attribute_name);
      add_action("{$tax}_add_form_fields", function(){ ?>
        <div class="form-field term-surcharge-wrap">
          <label for="fm_term_surcharge"><?php esc_html_e('Surcharge (USD)', 'fm'); ?></label>
          <input type="number" step="0.01" min="0" name="fm_term_surcharge" id="fm_term_surcharge" value="">
          <p class="description"><?php esc_html_e('Added per item when this term is selected (e.g., 2XL +$2).', 'fm'); ?></p>
        </div><?php
      });
      add_action("{$tax}_edit_form_fields", function($term){ $val = get_term_meta($term->term_id, FM_Surcharges::SUR_META, true); ?>
        <tr class="form-field term-surcharge-wrap">
          <th><label for="fm_term_surcharge"><?php esc_html_e('Surcharge (USD)', 'fm'); ?></label></th>
          <td><input type="number" step="0.01" min="0" name="fm_term_surcharge" id="fm_term_surcharge" value="<?php echo esc_attr($val); ?>">
            <p class="description"><?php esc_html_e('Added per item when this term is selected.', 'fm'); ?></p></td>
        </tr><?php
      });
    }
  }
  public function save_term_meta($term_id){
    if (!isset($_POST['fm_term_surcharge'])) return;
    $val = wc_format_decimal(wp_unslash($_POST['fm_term_surcharge']));
    if ($val === '') delete_term_meta($term_id, self::SUR_META);
    else update_term_meta($term_id, self::SUR_META, $val);
  }

  public function add_product_meta_boxes(){
    add_meta_box('fm_vinyl_box', __('Vinyl Upgrade', 'fm'), [$this,'render_vinyl_meta_box'], 'product', 'side');
    add_meta_box('fm_ct_box',    __('Custom Text Add-on', 'fm'), [$this,'render_ct_meta_box'], 'product', 'side');
  }
  public function render_vinyl_meta_box($post){
    $price = get_post_meta($post->ID, self::VINYL_META, true);
    wp_nonce_field('fm_meta_save','fm_meta_nonce'); ?>
    <p><label for="fm_vinyl_price"><strong><?php esc_html_e('Vinyl Upgrade Price (USD)', 'fm'); ?></strong></label></p>
    <input type="number" step="0.01" min="0" id="fm_vinyl_price" name="fm_vinyl_price" class="widefat" value="<?php echo esc_attr($price); ?>">
    <p class="description"><?php esc_html_e('Leave blank to hide Vinyl option.', 'fm'); ?></p><?php
  }
  public function render_ct_meta_box($post){
    $enabled = get_post_meta($post->ID, self::CT_ENABLED, true) === '1';
    $price   = get_post_meta($post->ID, self::CT_PRICE, true);
    $maxLegacy = get_post_meta($post->ID, self::CT_MAX, true) ?: 24;
    $max1   = get_post_meta($post->ID, self::CT_MAX1, true) ?: $maxLegacy;
    $max2   = get_post_meta($post->ID, self::CT_MAX2, true) ?: $maxLegacy;
    $lines  = get_post_meta($post->ID, self::CT_LINES, true) ?: 1;
    $ui     = get_post_meta($post->ID, self::CT_UI, true) ?: 'checkbox';
    $l1     = get_post_meta($post->ID, self::CT_L1, true) ?: __('Name', 'fm');
    $l2     = get_post_meta($post->ID, self::CT_L2, true) ?: __('Number / Year', 'fm');
    $upper  = get_post_meta($post->ID, self::CT_UPPER, true) === '1';
    $counter= get_post_meta($post->ID, self::CT_COUNTER, true) === '1';
    $allowed= get_post_meta($post->ID, self::CT_ALLOWED, true) ?: 'alnumdash';
    ?>
    <p><label><input type="checkbox" name="fm_ct_enabled" value="1" <?php checked($enabled,true); ?>> <strong><?php esc_html_e('Enable Custom Text add-on', 'fm'); ?></strong></label></p>
    <p><label for="fm_ct_price"><strong><?php esc_html_e('Add-on Price (USD)', 'fm'); ?></strong></label>
      <input type="number" step="0.01" min="0" id="fm_ct_price" name="fm_ct_price" class="widefat" value="<?php echo esc_attr($price); ?>"></p>
    <p><label for="fm_ct_ui"><strong><?php esc_html_e('Selector UI', 'fm'); ?></strong></label>
      <select id="fm_ct_ui" name="fm_ct_ui" class="widefat">
        <option value="checkbox" <?php selected($ui,'checkbox'); ?>><?php esc_html_e('Checkbox', 'fm'); ?></option>
        <option value="dropdown" <?php selected($ui,'dropdown'); ?>><?php esc_html_e('Dropdown', 'fm'); ?></option>
      </select></p>
    <p><label for="fm_ct_lines"><strong><?php esc_html_e('Number of Text Fields', 'fm'); ?></strong></label>
      <select id="fm_ct_lines" name="fm_ct_lines" class="widefat">
        <option value="1" <?php selected($lines,'1'); ?>>1</option>
        <option value="2" <?php selected($lines,'2'); ?>>2</option>
      </select></p>
    <p><label for="fm_ct_max1"><strong><?php esc_html_e('Max Characters: Field 1', 'fm'); ?></strong></label>
      <input type="number" min="1" id="fm_ct_max1" name="fm_ct_max1" class="widefat" value="<?php echo esc_attr($max1); ?>"></p>
    <p><label for="fm_ct_max2"><strong><?php esc_html_e('Max Characters: Field 2', 'fm'); ?></strong></label>
      <input type="number" min="1" id="fm_ct_max2" name="fm_ct_max2" class="widefat" value="<?php echo esc_attr($max2); ?>"></p>
    <p><label><input type="checkbox" name="fm_ct_uppercase" value="1" <?php checked($upper,true); ?>> <?php esc_html_e('Force UPPERCASE', 'fm'); ?></label></p>
    <p><label><input type="checkbox" name="fm_ct_counter" value="1" <?php checked($counter,true); ?>> <?php esc_html_e('Show character counters', 'fm'); ?></label></p>
    <p><label for="fm_ct_allowed"><strong><?php esc_html_e('Allowed Characters', 'fm'); ?></strong></label>
      <select id="fm_ct_allowed" name="fm_ct_allowed" class="widefat">
        <option value="alnumdash" <?php selected($allowed,'alnumdash'); ?>>A–Z, 0–9, space, hyphen</option>
        <option value="alnum"     <?php selected($allowed,'alnum'); ?>>A–Z and 0–9</option>
        <option value="letters"   <?php selected($allowed,'letters'); ?>>Letters + spaces</option>
        <option value="any"       <?php selected($allowed,'any'); ?>>Any</option>
      </select></p>
    <p><label for="fm_ct_label1"><strong><?php esc_html_e('Field 1 Label', 'fm'); ?></strong></label>
      <input type="text" id="fm_ct_label1" name="fm_ct_label1" class="widefat" value="<?php echo esc_attr($l1); ?>"></p>
    <p><label for="fm_ct_label2"><strong><?php esc_html_e('Field 2 Label', 'fm'); ?></strong></label>
      <input type="text" id="fm_ct_label2" name="fm_ct_label2" class="widefat" value="<?php echo esc_attr($l2); ?>"></p>
    <?php
  }
  public function save_product_meta($post_id){
    if (!isset($_POST['fm_meta_nonce']) || !wp_verify_nonce($_POST['fm_meta_nonce'], 'fm_meta_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;
    $vinyl = isset($_POST['fm_vinyl_price']) ? wc_format_decimal(wp_unslash($_POST['fm_vinyl_price'])) : '';
    if ($vinyl === '') delete_post_meta($post_id, self::VINYL_META); else update_post_meta($post_id, self::VINYL_META, $vinyl);
    update_post_meta($post_id, self::CT_ENABLED, isset($_POST['fm_ct_enabled']) ? '1' : '0');
    $price = isset($_POST['fm_ct_price']) ? wc_format_decimal(wp_unslash($_POST['fm_ct_price'])) : '';
    if ($price === '') delete_post_meta($post_id, self::CT_PRICE); else update_post_meta($post_id, self::CT_PRICE, $price);
    $max1 = max(1, intval($_POST['fm_ct_max1'] ?? 24));
    $max2 = max(1, intval($_POST['fm_ct_max2'] ?? 24));
    update_post_meta($post_id, self::CT_MAX1, $max1);
    update_post_meta($post_id, self::CT_MAX2, $max2);
    update_post_meta($post_id, self::CT_MAX, max($max1, $max2));
    $lines = in_array($_POST['fm_ct_lines'] ?? '1', ['1','2'], true) ? $_POST['fm_ct_lines'] : '1';
    update_post_meta($post_id, self::CT_LINES, $lines);
    $ui    = in_array($_POST['fm_ct_ui'] ?? 'checkbox', ['checkbox','dropdown'], true) ? $_POST['fm_ct_ui'] : 'checkbox';
    update_post_meta($post_id, self::CT_UI, $ui);
    update_post_meta($post_id, self::CT_L1, sanitize_text_field($_POST['fm_ct_label1'] ?? ''));
    update_post_meta($post_id, self::CT_L2, sanitize_text_field($_POST['fm_ct_label2'] ?? ''));
    update_post_meta($post_id, self::CT_UPPER,   isset($_POST['fm_ct_uppercase']) ? '1' : '0');
    update_post_meta($post_id, self::CT_COUNTER, isset($_POST['fm_ct_counter']) ? '1' : '0');
    $allowed = $_POST['fm_ct_allowed'] ?? 'alnumdash';
    if (!in_array($allowed, ['alnumdash','alnum','letters','any'], true)) $allowed = 'alnumdash';
    update_post_meta($post_id, self::CT_ALLOWED, $allowed);
  }

  public function render_custom_text_block(){
    global $product; if (!$product instanceof WC_Product) return;
    $enabled = get_post_meta($product->get_id(), self::CT_ENABLED, true) === '1';
    $price   = get_post_meta($product->get_id(), self::CT_PRICE, true);
    if (!$enabled || $price === '' || $price === null) return;
    $price_fmt = wc_price($price);
    $lines = intval(get_post_meta($product->get_id(), self::CT_LINES, true) ?: 1);
    $ui    = get_post_meta($product->get_id(), self::CT_UI, true) ?: 'checkbox';
    $l1    = get_post_meta($product->get_id(), self::CT_L1, true) ?: __('Name', 'fm');
    $l2    = get_post_meta($product->get_id(), self::CT_L2, true) ?: __('Number / Year', 'fm');
    $upper = get_post_meta($product->get_id(), self::CT_UPPER, true) === '1';
    $counter = get_post_meta($product->get_id(), self::CT_COUNTER, true) === '1';
    $allowed = get_post_meta($product->get_id(), self::CT_ALLOWED, true) ?: 'alnumdash';
    $maxLegacy = intval(get_post_meta($product->get_id(), self::CT_MAX, true) ?: 24);
    $max1 = intval(get_post_meta($product->get_id(), self::CT_MAX1, true) ?: $maxLegacy);
    $max2 = intval(get_post_meta($product->get_id(), self::CT_MAX2, true) ?: $maxLegacy);
    ?>
    <div class="fm-ct-wrap"
      data-ct-price="<?php echo esc_attr($price); ?>"
      data-ct-lines="<?php echo esc_attr($lines); ?>"
      data-ct-upper="<?php echo $upper ? '1' : '0'; ?>"
      data-ct-counter="<?php echo $counter ? '1' : '0'; ?>"
      data-ct-allowed="<?php echo esc_attr($allowed); ?>"
      data-ct-max1="<?php echo esc_attr($max1); ?>"
      data-ct-max2="<?php echo esc_attr($max2); ?>">
      <div class="fm-ct-select" style="margin:12px 0; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px;">
        <?php if ($ui === 'dropdown'): ?>
          <label style="display:block; margin-bottom:.4rem;"><strong><?php esc_html_e('Personalization', 'fm'); ?></strong></label>
          <select name="fm_ct_enable" id="fm_ct_enable" style="width:100%; max-width:320px;">
            <option value="0"><?php esc_html_e('No', 'fm'); ?></option>
            <option value="1"><?php echo sprintf(esc_html__('Yes (+ %s)', 'fm'), wp_kses_post($price_fmt)); ?></option>
          </select>
        <?php else: ?>
          <label style="display:flex; gap:.5rem; align-items:center;">
            <input type="checkbox" name="fm_ct_enable" id="fm_ct_enable" value="1">
            <span><strong><?php echo sprintf(esc_html__('Add Custom Text (+ %s)', 'fm'), wp_kses_post($price_fmt)); ?></strong></span>
          </label>
        <?php endif; ?>
      </div>
      <div class="fm-ct-fields" style="display:none; margin:8px 0 0;">
        <label style="display:block; margin:.4rem 0 .2rem;"><?php echo esc_html($l1); ?> (<?php printf(esc_html__('%d chars max','fm'), $max1); ?>)</label>
        <div style="display:flex; gap:.5rem; align-items:center;">
          <input type="text" name="fm_ct_line1" maxlength="<?php echo esc_attr($max1); ?>" class="fm-ct-input fm-ct-input1" style="flex:1; padding:.55rem .7rem;">
          <small class="ct-counter ct-count1" style="color:#64748b; display:none;">0/<?php echo esc_html($max1); ?></small>
        </div>
        <?php if ($lines >= 2): ?>
          <label style="display:block; margin:.6rem 0 .2rem;"><?php echo esc_html($l2); ?> (<?php printf(esc_html__('%d chars max','fm'), $max2); ?>)</label>
          <div style="display:flex; gap:.5rem; align-items:center;">
            <input type="text" name="fm_ct_line2" maxlength="<?php echo esc_attr($max2); ?>" class="fm-ct-input fm-ct-input2" style="flex:1; padding:.55rem .7rem;">
            <small class="ct-counter ct-count2" style="color:#64748b; display:none;">0/<?php echo esc_html($max2); ?></small>
          </div>
        <?php endif; ?>
        <p class="fm-ct-hint" style="color:#64748b; font-size:.9rem; margin:.5rem 0 0;"><?php esc_html_e('We will print exactly what you type (case & spelling).', 'fm'); ?></p>
      </div>
    </div><?php
  }
  public function render_price_wrap(){ echo '<div id="fm-price-wrap" class="fm-price-wrap" aria-live="polite" style="margin:14px 0;"></div>'; }
  public function render_vinyl_checkbox(){
    global $product; if (!$product instanceof WC_Product) return;
    $price = get_post_meta($product->get_id(), self::VINYL_META, true);
    if ($price === '' || $price === null) return;
    echo '<div class="fm-vinyl-upgrade" style="margin:12px 0 16px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px;">' .
         '<label style="display:flex; gap:.5rem; align-items:center;"><input type="checkbox" name="fm_vinyl_selected" value="1"> ' .
         sprintf(__('Add Vinyl Upgrade (+ %s)', 'fm'), wc_price($price)) .
         '</label></div>';
  }

  public function validate_before_add_to_cart($passed, $product_id){
    $ct_enabled = get_post_meta($product_id, self::CT_ENABLED, true) === '1';
    $ct_price   = get_post_meta($product_id, self::CT_PRICE, true);
    if ($ct_enabled && $ct_price !== '' && $ct_price !== null) {
      $want = isset($_POST['fm_ct_enable']) && $_POST['fm_ct_enable'] == '1';
      if ($want) {
        $lines = intval(get_post_meta($product_id, self::CT_LINES, true) ?: 1);
        $max1  = intval(get_post_meta($product_id, self::CT_MAX1, true) ?: get_post_meta($product_id, self::CT_MAX, true) ?: 24);
        $max2  = intval(get_post_meta($product_id, self::CT_MAX2, true) ?: get_post_meta($product_id, self::CT_MAX, true) ?: 24);
        $l1 = trim(wp_unslash($_POST['fm_ct_line1'] ?? ''));
        $l2 = trim(wp_unslash($_POST['fm_ct_line2'] ?? ''));
        if ($l1 === '') { wc_add_notice(__('Please enter your custom text.', 'fm'), 'error'); return false; }
        if (mb_strlen($l1) > $max1 || ($lines >=2 && mb_strlen($l2) > $max2)) {
          wc_add_notice(__('Your custom text exceeds the allowed length.', 'fm'), 'error'); return false;
        }
      }
    }
    return $passed;
  }
  public function add_cart_item_data($data, $product_id){
    $vinyl = get_post_meta($product_id, self::VINYL_META, true);
    if ($vinyl !== '' && isset($_POST['fm_vinyl_selected']) && $_POST['fm_vinyl_selected'] == '1') {
      $data['fm_vinyl_selected'] = true;
      $data['fm_vinyl_price']    = (float) wc_format_decimal($vinyl);
      $data['unique_key']        = md5(microtime().rand());
    }
    $ct_enabled = get_post_meta($product_id, self::CT_ENABLED, true) === '1';
    $ct_price   = get_post_meta($product_id, self::CT_PRICE, true);
    if ($ct_enabled && $ct_price !== '' && isset($_POST['fm_ct_enable']) && $_POST['fm_ct_enable'] == '1') {
      $max1 = intval(get_post_meta($product_id, self::CT_MAX1, true) ?: get_post_meta($product_id, self::CT_MAX, true) ?: 24);
      $max2 = intval(get_post_meta($product_id, self::CT_MAX2, true) ?: get_post_meta($product_id, self::CT_MAX, true) ?: 24);
      $l1   = sanitize_text_field(wp_unslash($_POST['fm_ct_line1'] ?? ''));
      $l2   = sanitize_text_field(wp_unslash($_POST['fm_ct_line2'] ?? ''));
      $data['fm_ct_selected'] = true;
      $data['fm_ct_price']    = (float) wc_format_decimal($ct_price);
      $data['fm_ct_line1']    = mb_substr($l1, 0, $max1);
      if ($l2 !== '') $data['fm_ct_line2'] = mb_substr($l2, 0, $max2);
      $data['unique_key']     = md5(microtime().rand());
    }
    return $data;
  }
  public function display_cart_item_data($items, $cart_item){
    if (!empty($cart_item['fm_vinyl_selected']))
      $items[] = [ 'name' => __('Vinyl Upgrade', 'fm'), 'value' => wc_price($cart_item['fm_vinyl_price']) ];
    if (!empty($cart_item['fm_ct_selected'])) {
      $items[] = [ 'name' => __('Personalization', 'fm'), 'value' => wc_price($cart_item['fm_ct_price']) ];
      if (!empty($cart_item['fm_ct_line1'])) $items[] = [ 'name' => __('Text',  'fm'), 'value' => esc_html($cart_item['fm_ct_line1']) ];
      if (!empty($cart_item['fm_ct_line2'])) $items[] = [ 'name' => __('Text 2','fm'), 'value' => esc_html($cart_item['fm_ct_line2']) ];
    }
    return $items;
  }

  private function sum_term_surcharges($attributes){
    $extra = 0.0; if (!is_array($attributes)) return 0.0;
    foreach ($attributes as $tax => $term_slug){
      if (!$term_slug) continue;
      $tax_name = (strpos($tax, 'attribute_') === 0) ? substr($tax, 10) : $tax;
      $term = get_term_by('slug', $term_slug, $tax_name);
      if ($term && !is_wp_error($term)) {
        $s = get_term_meta($term->term_id, self::SUR_META, true);
        if ($s !== '' && $s !== null) $extra += (float) $s;
      }
    }
    return $extra;
  }
  /** Per-request cache of each cart line's original (pre-surcharge) price. */
  private $base_price_cache = [];

  public function apply_surcharges($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $key => $item) {
      if (empty($item['data']) || !is_object($item['data'])) continue;
      $product = $item['data'];

      // Capture the unmodified base price the first time we see this line, so
      // repeated woocommerce_before_calculate_totals passes stay idempotent.
      // The cart's product object can remain mutated between passes, so reading
      // its current price again would compound the surcharge.
      if (!array_key_exists($key, $this->base_price_cache)) {
        $this->base_price_cache[$key] = (float) $product->get_price('edit');
      }
      $base = $this->base_price_cache[$key];

      $extra = $this->sum_term_surcharges($item['variation'] ?? []);
      if (!empty($item['fm_vinyl_selected'])) $extra += (float) $item['fm_vinyl_price'];
      if (!empty($item['fm_ct_selected']))    $extra += (float) $item['fm_ct_price'];
      if ($extra > 0) $product->set_price($base + $extra);
    }
  }
  public function inject_display_surcharges_into_variation($data, $product, $variation){
    $attrs = method_exists($variation, 'get_attributes') ? $variation->get_attributes() : [];
    $extra_attr = $this->sum_term_surcharges($attrs);
    $var_price  = (float) $variation->get_price();
    $adj_price  = wc_get_price_to_display($variation, ['price' => $var_price + $extra_attr]);
    $data['display_price'] = $adj_price;
    $data['display_regular_price'] = $adj_price;
    $data['price_html'] = sprintf('<span class="price"><span class="woocommerce-Price-amount amount">%s</span></span>', wc_price($adj_price));
    $data['fm_attr_surcharge'] = (float) $extra_attr;
    return $data;
  }
  public function filter_variable_price_range_html($html, $product){
    if (!$product instanceof WC_Product_Variable) return $html;
    $min = $max = null;
    foreach ($product->get_children() as $vid){
      $v = wc_get_product($vid); if (!$v || !$v->exists()) continue;
      $base = wc_get_price_to_display($v);
      $extra = $this->sum_term_surcharges($v->get_attributes());
      $p = $base + $extra;
      if ($min === null || $p < $min) $min = $p;
      if ($max === null || $p > $max) $max = $p;
    }
    return ($min !== null && $max !== null) ? (($min!==$max) ? wc_format_price_range($min,$max) : wc_price($min)) : $html;
  }

  public function enqueue_front_assets(){
    if (!is_product()) return;
    $currency = get_woocommerce_currency();
    $css = "
      .fm-ct-wrap .fm-ct-fields input{border:1px solid #e2e8f0;border-radius:6px}
      .fm-ct-bad{outline:2px solid #ef4444 !important}
      .fm-price-wrap .price{font-weight:800; font-size:1.35rem;}
      .fm-price-note{display:block; font-size:.9rem; color:#999; margin-top:.25rem}
      .ct-counter{min-width:4.5ch; text-align:right}
      .fm-hide-native-price .summary p.price,
      .fm-hide-native-price .woocommerce-variation-price { display:none !important; }
      #fm-price-wrap .price,
      #fm-price-wrap .woocommerce-Price-amount.amount { display:inline !important; visibility:visible !important; opacity:1 !important; }
    ";
    wp_register_style('fm-surch-inline', false, [], '1.7.1');
    wp_add_inline_style('fm-surch-inline', $css);
    wp_enqueue_style('fm-surch-inline');

    $js = <<<JS
(function(){
  function nfmt(n){try{return new Intl.NumberFormat(undefined,{style:'currency',currency:'{$currency}'}).format(n)}catch(e){return '$'+Number(n).toFixed(2)}}
  var baseDisplay=null, hasAttrNote=false, priceReady=false;
  function priceBox(){return document.getElementById('fm-price-wrap')}
  function ctWrap(){return document.querySelector('.fm-ct-wrap')}
  function ctEnabled(){var el=document.getElementById('fm_ct_enable'); if(!el) return false; return (el.tagName.toLowerCase()==='select')?(el.value==='1'):el.checked}
  function getNumFromText(txt){ if(!txt) return null; var m=(txt.replace(/[^0-9.,-]/g,'').match(/[0-9]+(?:[.,][0-9]{1,2})?/g)||[]); if(!m.length) return null; var s=m[0].replace(',', '.'); return Number(s); }
  function renderPrice(){
    var box=priceBox(); if(!box) return;
    if(baseDisplay===null){
      box.innerHTML='<strong>Total:</strong> <span class="price"><span class="woocommerce-Price-amount amount">—</span></span><small class="fm-price-note">Select options to see price</small>'; return;
    }
    var add = (ctEnabled() && ctWrap()) ? Number(ctWrap().getAttribute('data-ct-price')||'0') : 0;
    var final = baseDisplay + add;
    var html = '<strong>Total:</strong> <span class="price"><span class="woocommerce-Price-amount amount">'+nfmt(final)+'</span></span>';
    var notes=''; if(hasAttrNote) notes+='<small class="fm-price-note">Includes size/options adjustments</small>'; if(ctEnabled()) notes+='<small class="fm-price-note">Includes personalization</small>';
    box.innerHTML=html+notes; if(!priceReady){ document.body.classList.add('fm-hide-native-price'); priceReady=true; }
  }
  function setVariation(v){ if(!v) return; if(typeof v.display_price!=='undefined') baseDisplay=Number(v.display_price); hasAttrNote = !!(v.fm_attr_surcharge && Number(v.fm_attr_surcharge)>0); renderPrice(); bindCT(); }
  document.addEventListener('found_variation', function(e){ setVariation(e.detail||{}); }, false);
  if(window.jQuery){ jQuery(document).on('found_variation', function(_,v){ setVariation(v); }); }
  var nativePriceEl = document.querySelector('.woocommerce-variation-price .price');
  if(nativePriceEl && 'MutationObserver' in window){
    var obs=new MutationObserver(function(){ var val=getNumFromText(nativePriceEl.textContent); if(val!==null){ baseDisplay=val; renderPrice(); }});
    obs.observe(nativePriceEl,{childList:true,subtree:true,characterData:true});
  }
  function allowedRegex(name){ switch(name){ case 'alnumdash': return /[^A-Za-z0-9 \\-]/g; case 'alnum': return /[^A-Za-z0-9]/g; case 'letters': return /[^A-Za-z \\-]/g; default: return null; } }
  function bindCT(){
    var wrap=ctWrap(); if(!wrap) return;
    var enableEl=document.getElementById('fm_ct_enable'); var fields=wrap.querySelector('.fm-ct-fields');
    var input1=wrap.querySelector('.fm-ct-input1'); var input2=wrap.querySelector('.fm-ct-input2');
    var count1=wrap.querySelector('.ct-count1'); var count2=wrap.querySelector('.ct-count2');
    var lines=Number(wrap.getAttribute('data-ct-lines')||'1'); var upper=wrap.getAttribute('data-ct-upper')==='1';
    var counter=wrap.getAttribute('data-ct-counter')==='1'; var allowed=wrap.getAttribute('data-ct-allowed')||'any';
    var max1=Number(wrap.getAttribute('data-ct-max1')||'24'); var max2=Number(wrap.getAttribute('data-ct-max2')||'24');
    function toggle(){ if(fields) fields.style.display = ctEnabled() ? 'block' : 'none'; renderPrice(); updateCounters(); }
    function updateCounters(){ if(!counter){ if(count1)count1.style.display='none'; if(count2)count2.style.display='none'; return; }
      if(count1){ count1.style.display='inline'; count1.textContent=(input1?.value.length||0)+'/'+max1; }
      if(count2){ count2.style.display='inline'; count2.textContent=(input2?.value.length||0)+'/'+max2; } }
    function sanitize(ev){
      var el=ev.target; if(!el) return;
      if(upper){ var pos=el.selectionStart; el.value=el.value.toUpperCase(); try{ el.setSelectionRange(pos,pos);}catch(e){} }
      var re=allowedRegex(allowed); if(re){ var before=el.value; el.value=el.value.replace(re,''); if(before!==el.value){ el.classList.add('fm-ct-bad'); setTimeout(()=>el.classList.remove('fm-ct-bad'),120);} }
      updateCounters();
    }
    if(enableEl){ enableEl.removeEventListener('change',toggle); enableEl.addEventListener('change',toggle); }
    if(input1){ input1.removeEventListener('input',sanitize); input1.addEventListener('input',sanitize); input1.maxLength=max1; }
    if(lines>=2 && input2){ input2.removeEventListener('input',sanitize); input2.addEventListener('input',sanitize); input2.maxLength=max2; }
    toggle();
  }
  (function init(){ renderPrice(); bindCT(); })();
  if(window.jQuery){ jQuery(document).on('woocommerce_update_variation_values woocommerce_variation_has_changed', function(){ setTimeout(renderPrice,0); }); }
})();
JS;
    wp_register_script('fm-surch-js', false, ['jquery'], '1.7.1', true);
    wp_add_inline_script('fm-surch-js', $js);
    wp_enqueue_script('fm-surch-js');
  }
}
new FM_Surcharges();
