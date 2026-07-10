<?php
/**
 * Plugin Name: BW Mega Menu PRO (Creators + Groups)
 * Description: CPT “Creators” (logo + URL) + taxonomy “Groups” (Browse All URL). Shortcode [bw_mega_menu label="APPAREL STORES" all_link="/all-stores"] outputs a Bunker-style header tab bar: one tab per group, each opening a full-width panel of client logo cards.
 * Version: 4.3.0
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
  const PM_PINNED   = '_bw_creator_pinned';

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
    wp_register_style('bw-mega-menu-pro', plugins_url('bw-mega-menu-pro.css', __FILE__), [], '4.3.0');
    // Astra safety: the panel is positioned against the header bar row that
    // hosts the shortcode, so those bars must allow overflow and anchor it.
    $astra = ".ast-primary-header-bar, .main-header-bar, .ast-below-header-bar { overflow:visible!important } .site-header, .ast-primary-header-bar, .ast-below-header-bar { position:relative; z-index:30 } .ast-builder-html-element { min-width:0; max-width:100% }";
    wp_add_inline_style('bw-mega-menu-pro',$astra);
    wp_register_script('bw-mega-menu-pro', plugins_url('bw-mega-menu-pro.js', __FILE__), [], '4.3.0', true);
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
      'show_all_tx'=>'Shop All',
      'grayscale'=>$default_gray, // 'on'|'off'
    ], $atts,'bw_mega_menu');

    wp_enqueue_style('bw-mega-menu-pro');
    wp_enqueue_script('bw-mega-menu-pro');

    // Group tabs alphabetical.
    $groups = get_terms([
      'taxonomy'=>self::TAX_GROUP,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC',
    ]);
    if (is_wp_error($groups) || empty($groups)) return '<!-- BW: no groups -->';

    $gray_class = ($atts['grayscale']==='on') ? ' is-gray' : '';
    $instance_id = wp_unique_id('bw-mm-');

    // Bunker-style: a single header entry (e.g. CLIENT STORES) opens a
    // full-width panel; the client-group categories are tabs inside the
    // panel, and hovering/selecting one reveals that group's store cards.
    ob_start(); ?>
    <nav class="bw-mm<?php echo esc_attr($gray_class.($atts['class'] ? ' '.$atts['class'] : '')); ?>" data-bw-mm aria-label="<?php echo esc_attr($atts['label']); ?>">
      <button
        type="button"
        class="bw-mm__trigger"
        data-bw-mm-trigger
        aria-expanded="false"
        aria-controls="<?php echo esc_attr($instance_id . '-panel'); ?>">
        <?php echo esc_html($atts['label']); ?>
        <svg class="bw-mm__caret" aria-hidden="true" width="12" height="12" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z" fill="currentColor"/></svg>
      </button>

      <div class="bw-mm__panel" id="<?php echo esc_attr($instance_id . '-panel'); ?>" data-bw-mm-panel hidden>
        <div class="bw-mm__cats-bar">
          <ul class="bw-mm__cats">
            <?php foreach($groups as $g):
              $panel_id = $instance_id . '-group-' . $g->term_id; ?>
              <li class="bw-mm__cat-item">
                <button
                  type="button"
                  class="bw-mm__cat"
                  data-bw-mm-tab="<?php echo esc_attr((string)$g->term_id); ?>"
                  aria-expanded="false"
                  aria-controls="<?php echo esc_attr($panel_id); ?>">
                  <?php echo esc_html($g->name); ?>
                </button>
              </li>
            <?php endforeach; ?>
            <?php if (!empty($atts['all_link'])): ?>
              <li class="bw-mm__cat-item bw-mm__cat-item--all">
                <a class="bw-mm__cat bw-mm__cat--link" href="<?php echo esc_url($atts['all_link']); ?>"><?php esc_html_e('All Stores'); ?></a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="bw-mm__panel-inner">
          <?php foreach($groups as $g):
            $browse = get_term_meta($g->term_id,self::TM_BROWSE,true) ?: get_term_link($g);
            $panel_id = $instance_id . '-group-' . $g->term_id;
            // Alphabetical, then float pinned creators to the top (PHP 8 usort
            // is stable, so alpha order holds within pinned and unpinned).
            $creators = get_posts([
              'post_type'=>self::CPT_CREATOR,'posts_per_page'=>(int)$atts['limit'],
              'orderby'=>'title','order'=>'ASC',
              'tax_query'=>[['taxonomy'=>self::TAX_GROUP,'field'=>'term_id','terms'=>[$g->term_id]]],
              'meta_query'=>[['key'=>self::PM_SHOW,'value'=>'1']]
            ]);
            usort($creators, function($a,$b){
              $pa = get_post_meta($a->ID, self::PM_PINNED, true) === '1' ? 1 : 0;
              $pb = get_post_meta($b->ID, self::PM_PINNED, true) === '1' ? 1 : 0;
              return $pb <=> $pa;
            });
            ?>
            <section class="bw-mm__group"
              id="<?php echo esc_attr($panel_id); ?>"
              data-bw-mm-group="<?php echo esc_attr((string)$g->term_id); ?>"
              aria-label="<?php echo esc_attr($g->name); ?>"
              hidden>
              <div class="bw-mm__group-head">
                <h3 class="bw-mm__group-title"><?php echo esc_html($g->name); ?></h3>
                <a class="bw-mm__group-all" href="<?php echo esc_url($browse); ?>">
                  <?php echo esc_html($atts['show_all_tx'] . ' ' . $g->name); ?> <span class="arrow" aria-hidden="true">›</span>
                </a>
              </div>
              <ul class="bw-mm__grid">
                <?php if ($creators): foreach($creators as $c):
                  $u = $this->resolve_creator_url((int)$c->ID);
                  $name = get_the_title($c->ID);
                  $thumb_html = get_the_post_thumbnail($c->ID,'medium',['alt'=>$name, 'class'=>'bwmm-img', 'loading'=>'lazy']);
                  ?>
                  <li class="bw-mm__card-item">
                    <a class="bwmm-card" href="<?php echo esc_url($u); ?>" aria-label="<?php echo esc_attr($name); ?>" title="<?php echo esc_attr($name); ?>">
                      <div class="bwmm-tile">
                        <?php echo $thumb_html ?: '<span class="bw-logo-text">'.esc_html($name).'</span>'; ?>
                      </div>
                    </a>
                  </li>
                <?php endforeach; else: ?>
                  <li class="bw-mm__card-item bw-mm__card-item--empty"><em><?php esc_html_e('Stores coming soon.'); ?></em></li>
                <?php endif; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </div>
      </div>
    </nav>
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
