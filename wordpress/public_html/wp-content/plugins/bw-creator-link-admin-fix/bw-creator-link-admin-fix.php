<?php
/**
 * Plugin Name: BW Creator Link — Admin Fix
 * Description: Lets you enter relative paths for the Creator “Store URL” and saves/normalizes to the current site domain.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function($hook){
  // Only load on the Creator edit screen
  $screen = get_current_screen();
  if (!$screen || $screen->id !== 'bw_creator') return;
  $js = <<<JS
  (function(){
    // try to find the input by its name attribute
    var el = document.querySelector('input[name="bw_creator_link"]');
    if(!el) return;
    try{
      el.type = 'text'; // remove URL validation
      el.placeholder = '/creator/your-client/';
      // small hint below the field
      var hint = document.createElement('div');
      hint.style.fontSize = '11px';
      hint.style.opacity = '0.8';
      hint.style.marginTop = '4px';
      hint.textContent = 'Tip: you can enter a relative path like /creator/axe-n-dagger/; it will be saved as a full URL for this site.';
      el.parentNode.appendChild(hint);
    }catch(e){}
  })();
JS;
  wp_add_inline_script('jquery-core', $js); // jQuery is loaded in admin; we piggyback to inject our JS
});

/** Normalize on save: relative -> absolute; old host -> current host */
add_action('save_post_bw_creator', function($post_id){
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $val = get_post_meta($post_id, '_bw_creator_link', true);
  if (!$val || !is_string($val)) return;

  $val = trim($val);
  if ($val === '') return;

  // If relative, prefix home_url()
  if ($val[0] === '/') {
    $abs = home_url($val);
    update_post_meta($post_id, '_bw_creator_link', esc_url_raw($abs));
    return;
  }

  // If absolute with different host, swap host/scheme to current
  $home = wp_parse_url(home_url('/'));
  $s    = wp_parse_url($val);
  if ($s && $home && !empty($s['host']) && !empty($home['host']) && strtolower($s['host']) !== strtolower($home['host'])) {
    $rebuilt = ($home['scheme'] ?? 'https') . '://' . $home['host'] . ($s['path'] ?? '/')
             . (!empty($s['query']) ? '?'.$s['query'] : '')
             . (!empty($s['fragment']) ? '#'.$s['fragment'] : '');
    update_post_meta($post_id, '_bw_creator_link', esc_url_raw($rebuilt));
  }
}, 20);
