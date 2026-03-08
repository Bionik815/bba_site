<?php
/**
 * Plugin Name: BW Client Menu Picker
 * Description: Per-page dropdown to choose a client-specific WP Menu + shortcode [client_menu].
 * Version: 1.0.0
 * License: GPL-2.0+
 */
if (!defined('ABSPATH')) exit;

class BW_Client_Menu {
  const META_KEY = '_bw_client_menu_id';
  public function __construct(){
    add_action('add_meta_boxes', [$this,'add_metabox']);
    add_action('save_post_page', [$this,'save_meta']);
    add_shortcode('client_menu', [$this,'shortcode']);
  }
  public function add_metabox(){
    add_meta_box('bw_client_menu_box', __('Client Menu','bw'), [$this,'render_metabox'],'page','side');
  }
  public function render_metabox($post){
    wp_nonce_field('bw_client_menu_save','bw_client_menu_nonce');
    $menus = wp_get_nav_menus(); $current = get_post_meta($post->ID, self::META_KEY, true);
    echo '<p><label for="bw_client_menu_select">'.esc_html__('Select a menu for this page','bw').'</label></p>';
    echo '<select id="bw_client_menu_select" name="bw_client_menu_select" class="widefat"><option value="0">— None —</option>';
    foreach ($menus as $m){ printf('<option value="%d"%s>%s</option>', (int)$m->term_id, selected((int)$current,(int)$m->term_id,false), esc_html($m->name)); }
    echo '</select>';
  }
  public function save_meta($post_id){
    if (!isset($_POST['bw_client_menu_nonce']) || !wp_verify_nonce($_POST['bw_client_menu_nonce'],'bw_client_menu_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_page',$post_id)) return;
    $val = isset($_POST['bw_client_menu_select']) ? (int)$_POST['bw_client_menu_select'] : 0;
    if ($val > 0) update_post_meta($post_id, self::META_KEY, $val); else delete_post_meta($post_id, self::META_KEY);
  }
  public function shortcode($atts){
    $atts = shortcode_atts(['class'=>'bw-client-menu','fallback'=>''], $atts,'client_menu');
    $menu_id = 0;
    if (is_singular('page')) $menu_id = (int) get_post_meta(get_the_ID(), self::META_KEY, true);
    if ($menu_id <= 0 && $atts['fallback']!==''){ $fb = wp_get_nav_menu_object($atts['fallback']); if ($fb) $menu_id = (int)$fb->term_id; }
    if ($menu_id <= 0) return '';
    return wp_nav_menu(['menu'=>$menu_id,'container'=>'nav','container_class'=>esc_attr($atts['class']),'menu_class'=>'menu','echo'=>false,'depth'=>2,'fallback_cb'=>false]) ?: '';
  }
}
new BW_Client_Menu();
