<?php
/**
 * Plugin Name: BW Mega Menu PRO (Creators + Groups)
 * Description: CPT “Creators” (logo + URL) + taxonomy “Groups” (Browse All URL). Shortcode [bw_mega_menu label="APPAREL STORES" all_link="/all-stores"] outputs full-width mega panel with vertical groups and horizontally scrollable logos.
 * Version: 3.2.0
 * License: GPL-2.0+
 */
if (!defined('ABSPATH')) exit;

class BW_Mega_Menu_Pro {
  const TAX_GROUP   = 'bw_group';
  const CPT_CREATOR = 'bw_creator';
  const TM_BROWSE   = '_bw_group_browse_url';
  const TM_ORDER    = '_bw_group_order';
  const PM_LINK     = '_bw_creator_link';
  const PM_SHOW     = '_bw_creator_show';
  const PM_ORDER    = '_bw_creator_order';

  // settings
  const OPT_SECTION   = 'bwmm_settings';
  const OPT_GRAYSCALE = 'bwmm_grayscale'; // 'on' | 'off'

  public function __construct(){
    add_action('init', [$this,'register_types']);
    add_action('admin_init', [$this,'maybe_seed_terms']);
    add_filter('use_block_editor_for_post_type', [$this, 'maybe_disable_block_editor'], 10, 2);

    // term meta (Groups)
    add_action(self::TAX_GROUP.'_add_form_fields',  [$this,'group_add_fields']);
    add_action(self::TAX_GROUP.'_edit_form_fields', [$this,'group_edit_fields']);
    add_action('created_'.self::TAX_GROUP,          [$this,'save_group_meta'], 10, 2);
    add_action('edited_'.self::TAX_GROUP,           [$this,'save_group_meta'], 10, 2);

    // creators meta
    add_action('add_meta_boxes', [$this,'add_creator_meta_box']);
    add_action('save_post_'.self::CPT_CREATOR, [$this,'save_creator_meta']);

    // admin table niceties
    add_filter('manage_'.self::CPT_CREATOR.'_posts_columns',        [$this,'creator_columns']);
    add_action('manage_'.self::CPT_CREATOR.'_posts_custom_column',  [$this,'creator_column_content'], 10, 2);
    add_filter('manage_edit-'.self::CPT_CREATOR.'_sortable_columns',[$this,'creator_sortable']);
    add_action('pre_get_posts',                                      [$this,'creator_admin_sorting']);

    // assets + shortcode
    add_action('wp_enqueue_scripts', [$this,'register_assets']);
    add_shortcode('bw_mega_menu',   [$this,'shortcode']);

    // settings page
    add_action('admin_menu',  [$this,'add_settings_page']);
    add_action('admin_init',  [$this,'register_settings']);
  }

  /* ---------- Types ---------- */
  public function register_types(){
    register_taxonomy(self::TAX_GROUP, [self::CPT_CREATOR], [
      'label'=>'Groups','labels'=>['singular_name'=>'Group'],
      'public'=>true,'hierarchical'=>false,'show_admin_column'=>true,'rewrite'=>['slug'=>'group'],'show_in_rest'=>true,
    ]);
    register_post_type(self::CPT_CREATOR, [
      'label'=>'Creators','labels'=>['singular_name'=>'Creator','add_new_item'=>'Add New Creator'],
      'public'=>true,'show_ui'=>true,'menu_icon'=>'dashicons-groups',
      'supports'=>['title','editor','thumbnail','page-attributes'],'has_archive'=>false,'rewrite'=>['slug'=>'creator'],'show_in_rest'=>true,
    ]);
    add_image_size('bw_logo', 220, 120, false);
  }

  public function maybe_disable_block_editor($use_block_editor, $post_type) {
    if ($post_type === self::CPT_CREATOR) {
      return false;
    }
    return $use_block_editor;
  }
  public function maybe_seed_terms(){
    $defaults = ['Barebones Apparel','Athletes','Businesses','Music','Schools'];
    foreach ($defaults as $i => $name){
      if (!term_exists($name, self::TAX_GROUP)) {
        $t = wp_insert_term($name, self::TAX_GROUP);
        if (!is_wp_error($t)) update_term_meta($t['term_id'], self::TM_ORDER, $i+1);
      }
    }
  }

  /* ---------- Group meta ---------- */
  public function group_add_fields(){ ?>
    <div class="form-field"><label for="bw_group_order">Order</label>
      <input type="number" name="bw_group_order" id="bw_group_order" value="10" min="0" step="1">
      <p class="description">Lower = earlier.</p></div>
    <div class="form-field"><label for="bw_group_browse">Browse All URL</label>
      <input type="url" name="bw_group_browse" id="bw_group_browse" value="" placeholder="https://example.com/group/slug/">
      <p class="description">Optional; if blank, term archive is used.</p></div><?php
  }
  public function group_edit_fields($term){
    $order = get_term_meta($term->term_id, self::TM_ORDER, true);
    $url   = get_term_meta($term->term_id, self::TM_BROWSE, true); ?>
    <tr class="form-field"><th><label for="bw_group_order">Order</label></th>
      <td><input type="number" name="bw_group_order" id="bw_group_order" value="<?php echo esc_attr($order ?: 10); ?>" min="0" step="1"></td></tr>
    <tr class="form-field"><th><label for="bw_group_browse">Browse All URL</label></th>
      <td><input type="url" name="bw_group_browse" id="bw_group_browse" value="<?php echo esc_url($url); ?>" class="regular-text"></td></tr><?php
  }
  public function save_group_meta($term_id){
    if (isset($_POST['bw_group_order']))  update_term_meta($term_id, self::TM_ORDER, (int) $_POST['bw_group_order']);
    if (isset($_POST['bw_group_browse'])) update_term_meta($term_id, self::TM_BROWSE, esc_url_raw($_POST['bw_group_browse']));
  }

  /* ---------- Creator meta ---------- */
  public function add_creator_meta_box(){
    add_meta_box('bw_creator_meta', 'Creator Settings', [$this,'render_creator_meta'], self::CPT_CREATOR, 'side');
  }
  public function render_creator_meta($post){
    $link  = get_post_meta($post->ID, self::PM_LINK, true);
    $show  = get_post_meta($post->ID, self::PM_SHOW, true) === '1';
    $order = get_post_meta($post->ID, self::PM_ORDER, true) ?: 10;
    wp_nonce_field('bw_creator_save','bw_creator_nonce'); ?>
    <p><label for="bw_creator_link"><strong>Store URL</strong></label>
      <input type="url" id="bw_creator_link" name="bw_creator_link" value="<?php echo esc_attr($link); ?>" class="widefat" placeholder="https://subdomain.domain.com/"></p>
    <p><label><input type="checkbox" name="bw_creator_show" value="1" <?php checked($show,true); ?>> Show in Mega Menu</label></p>
    <p><label for="bw_creator_order"><strong>Order</strong></label>
      <input type="number" id="bw_creator_order" name="bw_creator_order" value="<?php echo esc_attr($order); ?>" class="small-text" min="0" step="1"> <span class="description">Lower = earlier.</span></p>
    <p><strong>Logo</strong><br>Use the Featured Image.</p><?php
  }
  public function save_creator_meta($post_id){
    if (!isset($_POST['bw_creator_nonce']) || !wp_verify_nonce($_POST['bw_creator_nonce'],'bw_creator_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post',$post_id)) return;
    update_post_meta($post_id, self::PM_LINK,  esc_url_raw($_POST['bw_creator_link'] ?? ''));
    update_post_meta($post_id, self::PM_SHOW,  isset($_POST['bw_creator_show']) ? '1' : '0');
    update_post_meta($post_id, self::PM_ORDER, (int) ($_POST['bw_creator_order'] ?? 10));
  }

  /* ---------- Admin table helpers ---------- */
  public function creator_columns($cols){
    return ['cb'=>$cols['cb'],'title'=>'Creator','thumbnail'=>'Logo','bw_group'=>'Group','bw_link'=>'Store URL','menu'=>'In Menu?','order'=>'Order','date'=>$cols['date']];
  }
  public function creator_column_content($col,$post_id){
    switch($col){
      case 'thumbnail': echo get_the_post_thumbnail($post_id,[80,50]) ?: '—'; break;
      case 'bw_group':  $t=get_the_terms($post_id,self::TAX_GROUP); echo $t && !is_wp_error($t) ? esc_html(implode(', ',wp_list_pluck($t,'name'))) : '—'; break;
      case 'bw_link':   $u=get_post_meta($post_id,self::PM_LINK,true); echo $u?'<a href="'.esc_url($u).'" target="_blank">'.esc_html($u).'</a>':'—'; break;
      case 'menu':      echo get_post_meta($post_id,self::PM_SHOW,true)==='1'?'✅':'—'; break;
      case 'order':     echo (int) get_post_meta($post_id,self::PM_ORDER,true); break;
    }
  }
  public function creator_sortable($cols){ $cols['order']='order'; return $cols; }
  public function creator_admin_sorting($q){
    if (!is_admin() || !$q->is_main_query() || $q->get('post_type')!==self::CPT_CREATOR) return;
    if ($q->get('orderby')==='order'){ $q->set('meta_key', self::PM_ORDER); $q->set('orderby','meta_value_num'); }
  }

  /* ---------- Settings page ---------- */
  public function add_settings_page(){
    add_options_page('BW Mega Menu', 'BW Mega Menu', 'manage_options', 'bwmm-settings', [$this,'render_settings_page']);
  }
  public function register_settings(){
    register_setting(self::OPT_SECTION, self::OPT_GRAYSCALE, [
      'type'=>'string','sanitize_callback'=>function($v){ return $v==='on'?'on':'off'; },'default'=>'off'
    ]);
    add_settings_section('bwmm_main', 'Display Options', '__return_false', self::OPT_SECTION);
    add_settings_field(self::OPT_GRAYSCALE, 'Logo Style', function(){
      $val = get_option(self::OPT_GRAYSCALE,'off');
      ?>
      <label><input type="radio" name="<?php echo esc_attr(self::OPT_GRAYSCALE); ?>" value="off" <?php checked($val,'off'); ?>> Color</label>
      &nbsp;&nbsp;
      <label><input type="radio" name="<?php echo esc_attr(self::OPT_GRAYSCALE); ?>" value="on"  <?php checked($val,'on');  ?>> Grayscale (hover to color)</label>
      <?php
    }, self::OPT_SECTION, 'bwmm_main');
  }
  public function render_settings_page(){ ?>
    <div class="wrap">
      <h1>BW Mega Menu</h1>
      <form method="post" action="options.php">
        <?php
          settings_fields(self::OPT_SECTION);
          do_settings_sections(self::OPT_SECTION);
          submit_button();
        ?>
      </form>
    </div>
  <?php }

  /* ---------- Assets + Shortcode ---------- */
  public function register_assets(){
    wp_register_style('bw-mega-menu-pro', plugins_url('bw-mega-menu-pro.css', __FILE__), [], '3.3.7');
    // Astra safety
    $astra = ".ast-primary-header-bar, .main-header-bar { overflow:visible!important } .site-header, .ast-primary-header-bar{position:relative;z-index:30}";
    wp_add_inline_style('bw-mega-menu-pro',$astra);
    wp_register_script('bw-mega-menu-pro', plugins_url('bw-mega-menu-pro.js', __FILE__), [], '3.3.0', true);
  }

  public function shortcode($atts){
    // inherit default grayscale from option, but allow per-shortcode override
    $default_gray = get_option(self::OPT_GRAYSCALE, 'off'); // 'on'|'off'

    $atts = shortcode_atts([
      'label'=>'INFLUENCERS',
      'limit'=>-1,
      'order'=>'ASC',
      'class'=>'',
      'all_link'=>'',
      'show_all_tx'=>'Show All',
      'grayscale'=>$default_gray, // 'on'|'off'|'inherit' (inherit unused now)
    ], $atts,'bw_mega_menu');

    wp_enqueue_style('bw-mega-menu-pro');
    wp_enqueue_script('bw-mega-menu-pro');

    $groups = get_terms([
      'taxonomy'=>self::TAX_GROUP,'hide_empty'=>false,'meta_key'=>self::TM_ORDER,'orderby'=>'meta_value_num','order'=>'ASC',
    ]);
    if (is_wp_error($groups) || empty($groups)) return '<!-- BW: no groups -->';

    $gray_class = ($atts['grayscale']==='on') ? ' is-gray' : '';

    $instance_id = wp_unique_id('bw-mega-');

    ob_start(); ?>
    <div class="bw-mega<?php echo esc_attr($gray_class.' '.$atts['class']); ?>">
      <button class="bw-mega__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($instance_id . '-panel'); ?>">
        <?php echo esc_html($atts['label']); ?><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
      </button>
      <div id="<?php echo esc_attr($instance_id . '-panel'); ?>" class="bw-mega__panel" hidden>
  <div class="bw-mega__inner">
    <div class="bw-panel-head">
      <h2 style="margin:0"><?php echo esc_html($atts['label']); ?></h2>
      <button type="button" class="bw-close" aria-label="Close">&times;</button>
    </div>
          <div class="bw-tabs" role="tablist" aria-label="<?php echo esc_attr($atts['label']); ?> groups">
            <?php foreach($groups as $i => $g):
              $tab_id = $instance_id . '-tab-' . $g->term_id;
              $panel_id = $instance_id . '-group-' . $g->term_id;
              ?>
              <button
                type="button"
                class="bw-tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
                id="<?php echo esc_attr($tab_id); ?>"
                role="tab"
                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                aria-controls="<?php echo esc_attr($panel_id); ?>"
                tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>">
                <?php echo esc_html($g->name); ?>
              </button>
            <?php endforeach; ?>
          </div>

          <?php foreach($groups as $i => $g):
            $browse = get_term_meta($g->term_id,self::TM_BROWSE,true) ?: get_term_link($g);
            $panel_id = $instance_id . '-group-' . $g->term_id;
            $tab_id = $instance_id . '-tab-' . $g->term_id;
            $creators = get_posts([
              'post_type'=>self::CPT_CREATOR,'posts_per_page'=>(int)$atts['limit'],'order'=>$atts['order'],
              'orderby'=>'meta_value_num','meta_key'=>self::PM_ORDER,
              'tax_query'=>[['taxonomy'=>self::TAX_GROUP,'field'=>'term_id','terms'=>[$g->term_id]]],
              'meta_query'=>[['key'=>self::PM_SHOW,'value'=>'1']]
            ]);
            ?>
            <section class="bw-group-panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
              id="<?php echo esc_attr($panel_id); ?>"
              role="tabpanel"
              aria-labelledby="<?php echo esc_attr($tab_id); ?>"
              <?php echo $i === 0 ? '' : 'hidden'; ?>>
              <div class="bw-group__head">
                <h3 class="bw-group__title"><?php echo esc_html($g->name); ?></h3>
                <a class="bw-group__all" href="<?php echo esc_url($browse); ?>"><?php echo esc_html($atts['show_all_tx']); ?> <span class="arrow">›</span></a>
              </div>
              <ul class="bw-logos-grid">
                <?php if ($creators): foreach($creators as $c):
                  $u = $this->resolve_creator_url((int)$c->ID);
                  $name = get_the_title($c->ID);
                  $thumb_html = get_the_post_thumbnail($c->ID,'bw_logo',['alt'=>$name, 'class'=>'bwmm-img']);

                  if ($thumb_html) {
                    echo '<li class="bw-logo">
                            <a class="bwmm-card" href="'.esc_url($u).'" aria-label="'.esc_attr($name).'">
                              <div class="bwmm-tile">'.$thumb_html.'</div>
                              <div class="bwmm-caption">'.esc_html($name).'</div>
                            </a>
                          </li>';
                  } else {
                    echo '<li class="bw-logo">
                            <a class="bwmm-card" href="'.esc_url($u).'" aria-label="'.esc_attr($name).'">
                              <div class="bwmm-tile"><span class="bw-logo-text">'.esc_html($name).'</span></div>
                              <div class="bwmm-caption">'.esc_html($name).'</div>
                            </a>
                          </li>';
                  }
                endforeach; else: ?>
                  <li class="bw-logo bw-logo--empty"><em>No items yet.</em></li>
                <?php endif; ?>
              </ul>
            </section>
          <?php endforeach; ?>
          <?php if (!empty($atts['all_link'])): ?>
            <div class="bw-all"><a class="bw-all__link" href="<?php echo esc_url($atts['all_link']); ?>">All Stores</a></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
  }

  private function resolve_creator_url(int $creator_id): string {
    $stored = (string) get_post_meta($creator_id, self::PM_LINK, true);
    $perma  = (string) get_permalink($creator_id);

    if ($stored === '' || str_contains($stored, '?post_type=' . self::CPT_CREATOR . '&p=')) {
      $stored = $perma;
    }

    $resolved = apply_filters('bwmm_creator_link', $stored, $creator_id);
    if (!is_string($resolved) || $resolved === '') {
      $resolved = $perma;
    }

    return $resolved !== '' ? $resolved : '#';
  }
}
new BW_Mega_Menu_Pro();
