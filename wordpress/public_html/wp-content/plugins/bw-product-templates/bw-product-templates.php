<?php
/**
 * Plugin Name: BW Product Templates
 * Description: Mark WooCommerce products as "Templates" (hidden, not purchasable) and quickly clone them for clients. Supports bulk generation (multi-templates × multi-client categories) with title patterns.
 * Version: 2.2.0
 * Author: You
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

class BW_Product_Templates {
  const META_IS_TEMPLATE = '_bw_is_template';
  const META_NOTES       = '_bw_tpl_notes';
  const NONCE            = 'bw_tpl_nonce';

  public function __construct(){
    // Admin UI on product edit
    add_action('add_meta_boxes', [$this,'add_metabox']);
    add_action('save_post_product', [$this,'save_meta']);

    // List table columns + filters + row actions
    add_filter('manage_edit-product_columns', [$this,'cols']);
    add_action('manage_product_posts_custom_column', [$this,'col_content'], 10, 2);
    add_filter('views_edit-product', [$this,'add_views_filter']);
    add_filter('post_row_actions', [$this,'row_action'], 10, 2);

    // Hide templates from shop and make them not purchasable
    add_action('pre_get_posts', [$this,'exclude_templates_from_queries']);
    add_filter('woocommerce_is_purchasable', [$this,'not_purchasable_if_template'], 10, 2);

    // Admin page: Template Generator (now with bulk)
    add_action('admin_menu', [$this,'add_admin_page']);
    add_action('admin_post_bw_tpl_generate', [$this,'handle_generate']);
  }

  /* ---------------- Metabox ---------------- */
  public function add_metabox(){
    add_meta_box('bw_tpl_box', __('Product Template','bw'), [$this,'render_metabox'], 'product', 'side', 'high');
  }
  public function render_metabox($post){
    wp_nonce_field(self::NONCE, self::NONCE);
    $is_tpl = get_post_meta($post->ID, self::META_IS_TEMPLATE, true) === '1';
    $notes  = get_post_meta($post->ID, self::META_NOTES, true);
    ?>
    <p>
      <label><input type="checkbox" name="bw_is_template" value="1" <?php checked($is_tpl,true); ?>>
        <strong><?php esc_html_e('Mark this product as a Template', 'bw'); ?></strong>
      </label>
    </p>
    <p class="howto"><?php esc_html_e('Templates are hidden from the storefront and cannot be purchased. Use the Template Generator to clone for a client.', 'bw'); ?></p>
    <p>
      <label for="bw_tpl_notes"><strong><?php esc_html_e('Template Notes', 'bw'); ?></strong></label>
      <textarea id="bw_tpl_notes" name="bw_tpl_notes" class="widefat" rows="4" placeholder="<?php esc_attr_e('Sizing rules, surcharge notes, etc.', 'bw'); ?>"><?php echo esc_textarea($notes); ?></textarea>
    </p>
    <?php
  }
  public function save_meta($post_id){
    if (!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;
    update_post_meta($post_id, self::META_IS_TEMPLATE, isset($_POST['bw_is_template']) ? '1' : '0');
    if (isset($_POST['bw_tpl_notes'])) update_post_meta($post_id, self::META_NOTES, sanitize_textarea_field($_POST['bw_tpl_notes']));
  }

  /* ---------------- List table helpers ---------------- */
  public function cols($cols){
    $in = [];
    foreach($cols as $k=>$v){
      $in[$k] = $v;
      if ($k==='name') $in['bw_tpl'] = __('Template','bw');
    }
    return $in;
  }
  public function col_content($col, $post_id){
    if ($col==='bw_tpl'){
      $is_tpl = get_post_meta($post_id, self::META_IS_TEMPLATE, true) === '1';
      echo $is_tpl ? '<span class="dashicons dashicons-yes" style="color:#0a0"></span>' : '—';
    }
  }
  public function add_views_filter($views){
    $url = add_query_arg(['bw_tpl_only'=>'1']);
    $views['bw_tpl'] = '<a href="'.esc_url($url).'">'.esc_html__('Templates','bw').'</a>';
    return $views;
  }
  public function row_action($actions, $post){
    if ($post->post_type !== 'product') return $actions;
    $is_tpl = get_post_meta($post->ID, self::META_IS_TEMPLATE, true) === '1';
    if ($is_tpl) {
      $url = wp_nonce_url(admin_url('admin.php?action=bw_tpl_generate&template_id='.$post->ID), self::NONCE);
      $actions['bw_tpl_clone'] = '<a href="'.esc_url($url).'">'.esc_html__('Clone for Client','bw').'</a>';
    }
    return $actions;
  }

  /* ---------------- Query hiding ---------------- */
  public function exclude_templates_from_queries($q){
    if (is_admin() || !$q->is_main_query()) return;
    if (!$q->is_post_type_archive('product') && !$q->is_tax(get_object_taxonomies('product')) && !$q->is_search()) return;

    $meta_query = (array) $q->get('meta_query');
    $meta_query[] = [
      'relation' => 'OR',
      [ 'key' => self::META_IS_TEMPLATE, 'compare' => 'NOT EXISTS' ],
      [ 'key' => self::META_IS_TEMPLATE, 'value' => '1', 'compare' => '!=' ],
    ];
    $q->set('meta_query', $meta_query);
  }
  public function not_purchasable_if_template($purchasable, $product){
    if (!$product) return $purchasable;
    $is_tpl = get_post_meta($product->get_id(), self::META_IS_TEMPLATE, true) === '1';
    return $is_tpl ? false : $purchasable;
  }

  /* ---------------- Admin: Template Generator ---------------- */
  public function add_admin_page(){
    add_submenu_page(
      'edit.php?post_type=product',
      __('Template Generator','bw'),
      __('Template Generator','bw'),
      'manage_woocommerce',
      'bw-template-generator',
      [$this,'render_admin_page']
    );
  }

  private function get_templates(){
    return get_posts([
      'post_type'      => 'product',
      'post_status'    => ['publish','draft','private'],
      'posts_per_page' => -1,
      'meta_key'       => self::META_IS_TEMPLATE,
      'meta_value'     => '1',
      'orderby'        => 'title',
      'order'          => 'ASC',
    ]);
  }

  public function render_admin_page(){
    if (!current_user_can('manage_woocommerce')) wp_die(__('Insufficient permissions','bw'));

    $templates = $this->get_templates();
    $cats = get_terms(['taxonomy'=>'product_cat', 'hide_empty'=>false, 'orderby'=>'name', 'order'=>'ASC']);
    $msg  = isset($_GET['bwmsg']) ? sanitize_text_field($_GET['bwmsg']) : '';
    $done = isset($_GET['created']) ? (int)$_GET['created'] : 0;
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Template Generator','bw'); ?></h1>
      <p><?php esc_html_e('Clone one or more template products into one or more client categories. Variations, images, and meta (including surcharges) are copied.', 'bw'); ?></p>

      <?php if ($msg==='missing'): ?>
        <div class="notice notice-error"><p><?php esc_html_e('Please select at least one template and one client category, and provide a title pattern.', 'bw'); ?></p></div>
      <?php elseif ($msg==='fail'): ?>
        <div class="notice notice-error"><p><?php esc_html_e('Something went wrong while generating products.', 'bw'); ?></p></div>
      <?php elseif ($msg==='ok'): ?>
        <div class="notice notice-success"><p><?php echo esc_html(sprintf(_n('%d product created.', '%d products created.', $done, 'bw'), $done)); ?></p></div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
        <input type="hidden" name="action" value="bw_tpl_generate">

        <div class="card" style="padding:16px;">
          <h2><?php esc_html_e('1) Choose Templates','bw'); ?></h2>
          <p class="description"><?php esc_html_e('Select one or more template products to clone. Mark products as templates on the product edit screen.', 'bw'); ?></p>
          <div style="max-height:320px;overflow:auto;border:1px solid #e5e7eb;padding:10px;border-radius:8px;">
            <?php if ($templates): foreach($templates as $t): ?>
              <label style="display:flex;align-items:center;gap:8px;margin:6px 0;">
                <input type="checkbox" name="template_ids[]" value="<?php echo (int)$t->ID; ?>">
                <span><?php echo esc_html($t->post_title . ' (ID '.$t->ID.')'); ?></span>
              </label>
            <?php endforeach; else: ?>
              <p><?php esc_html_e('No templates found. Edit a product and check “Mark this product as a Template”.', 'bw'); ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="padding:16px;">
          <h2><?php esc_html_e('2) Choose Client Categories','bw'); ?></h2>
          <p class="description"><?php esc_html_e('Select one or more WooCommerce categories (one per client).', 'bw'); ?></p>
          <div style="max-height:320px;overflow:auto;border:1px solid #e5e7eb;padding:10px;border-radius:8px;">
            <?php if ($cats && !is_wp_error($cats)): foreach($cats as $c): ?>
              <label style="display:flex;align-items:center;gap:8px;margin:6px 0;">
                <input type="checkbox" name="cat_ids[]" value="<?php echo (int)$c->term_id; ?>">
                <span><?php echo esc_html($c->name . ' ('.$c->slug.')'); ?></span>
              </label>
            <?php endforeach; else: ?>
              <p><?php esc_html_e('No categories found. Create a WooCommerce category per client first.', 'bw'); ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="grid-column:1/-1;padding:16px;">
          <h2><?php esc_html_e('3) Title Pattern & Options','bw'); ?></h2>
          <table class="form-table" role="presentation">
            <tbody>
              <tr>
                <th scope="row"><label for="bw_tpl_title_pattern"><?php esc_html_e('Title Pattern','bw'); ?></label></th>
                <td>
                  <input type="text" id="bw_tpl_title_pattern" name="title_pattern" class="regular-text" style="width:420px"
                    placeholder="<?php esc_attr_e('{template} — {client}','bw'); ?>" value="{template} — {client}">
                  <p class="description"><?php esc_html_e('Use {template} for the original template name, {client} for the category name, and {slug} for the category slug.', 'bw'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="bw_tpl_status"><?php esc_html_e('Post Status','bw'); ?></label></th>
                <td>
                  <select id="bw_tpl_status" name="post_status" class="regular-text">
                    <option value="draft"><?php esc_html_e('Draft','bw'); ?></option>
                    <option value="publish"><?php esc_html_e('Publish','bw'); ?></option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          <?php submit_button(__('Create Products','bw')); ?>
        </div>
      </form>
    </div>
    <?php
  }

  public function handle_generate(){
    if (!current_user_can('manage_woocommerce')) wp_die(__('Insufficient permissions','bw'));
    if (!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) wp_die(__('Bad request','bw'));

    $template_ids = array_filter(array_map('intval', (array)($_POST['template_ids'] ?? [])));
    $cat_ids      = array_filter(array_map('intval', (array)($_POST['cat_ids'] ?? [])));
    $pattern      = trim($_POST['title_pattern'] ?? '{template} — {client}');
    $status       = in_array($_POST['post_status'] ?? 'draft', ['draft','publish'], true) ? $_POST['post_status'] : 'draft';

    if (empty($template_ids) || empty($cat_ids) || $pattern===''){
      wp_redirect(add_query_arg(['page'=>'bw-template-generator','bwmsg'=>'missing'], admin_url('edit.php?post_type=product')));
      exit;
    }

    $created = 0;
    foreach ($template_ids as $tpl_id){
      $tpl_post = get_post($tpl_id);
      if (!$tpl_post || $tpl_post->post_type!=='product') continue;

      foreach ($cat_ids as $cat_id){
        $cat = get_term($cat_id, 'product_cat');
        if (!$cat || is_wp_error($cat)) continue;

        $new_title = $this->apply_pattern($pattern, $tpl_post->post_title, $cat->name, $cat->slug);
        $new_id = $this->duplicate_product($tpl_id);

        if ($new_id){
          // Update title & status
          wp_update_post(['ID'=>$new_id, 'post_title'=>$new_title, 'post_status'=>$status]);
          // Assign to this client category (append)
          wp_set_object_terms($new_id, [$cat_id], 'product_cat', true);
          // Ensure clone is not flagged as template
          delete_post_meta($new_id, self::META_IS_TEMPLATE);
          $created++;
        }
      }
    }

    if ($created > 0){
      wp_redirect(add_query_arg(['page'=>'bw-template-generator','bwmsg'=>'ok','created'=>$created], admin_url('edit.php?post_type=product')));
      exit;
    } else {
      wp_redirect(add_query_arg(['page'=>'bw-template-generator','bwmsg'=>'fail'], admin_url('edit.php?post_type=product')));
      exit;
    }
  }

  private function apply_pattern($pattern, $template_name, $client_name, $client_slug){
    $repl = [
      '{template}' => $template_name,
      '{client}'   => $client_name,
      '{slug}'     => $client_slug,
    ];
    $out = strtr($pattern, $repl);
    // trim fancy dashes/spaces if variables were empty
    return trim(preg_replace('/\s+—\s+$/u', '', preg_replace('/\s{2,}/', ' ', $out)));
  }

  private function duplicate_product($template_id){
    $new_id = 0;
    if (class_exists('WC_Admin_Duplicate_Product')){
      $product = wc_get_product($template_id);
      if ($product){
        $new_id = \WC_Admin_Duplicate_Product::product_duplicate($product);
      }
    }
    if ($new_id) return $new_id;

    // Fallback manual duplicate
    $post = get_post($template_id);
    if (!$post || $post->post_type!=='product') return 0;

    $new_post = [
      'post_author'  => get_current_user_id(),
      'post_content' => $post->post_content,
      'post_excerpt' => $post->post_excerpt,
      'post_status'  => 'draft',
      'post_title'   => $post->post_title,
      'post_type'    => 'product',
    ];
    $new_id = wp_insert_post($new_post);
    if (!$new_id) return 0;

    // copy meta (except template flag)
    $all_meta = get_post_meta($template_id);
    foreach ($all_meta as $key=>$values){
      if ($key === self::META_IS_TEMPLATE) continue;
      foreach ($values as $v){
        add_post_meta($new_id, $key, maybe_unserialize($v));
      }
    }
    // copy thumbnail
    $thumb_id = get_post_thumbnail_id($template_id);
    if ($thumb_id) set_post_thumbnail($new_id, $thumb_id);
    // copy terms (cats/tags)
    $taxes = get_object_taxonomies('product');
    foreach ($taxes as $tax){
      $terms = wp_get_object_terms($template_id, $tax, ['fields'=>'ids']);
      if (!is_wp_error($terms)) wp_set_object_terms($new_id, $terms, $tax);
    }
    return $new_id;
  }
}

new BW_Product_Templates();

require_once __DIR__ . '/includes/class-bw-store-builder.php';
require_once __DIR__ . '/includes/class-bw-template-options.php';
require_once __DIR__ . '/includes/class-bw-wix-import.php';
