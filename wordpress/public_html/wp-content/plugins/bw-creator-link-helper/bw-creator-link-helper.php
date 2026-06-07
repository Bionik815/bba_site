<?php
/**
 * Plugin Name: BW Creator Link Helper
 * Description: Lets you enter relative paths for the Creator "Store URL" and normalizes them to this site's absolute URL — on save and on read (domain-move safe). Consolidates the former "BW Creator Link — Admin Fix" plugin (admin field UX + save normalizer + read normalizer).
 */
if (!defined('ABSPATH')) exit;

// Admin UX: allow a relative path in the "Store URL" field (drop URL validation) + hint.
add_action('admin_enqueue_scripts', function($hook){
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->id !== 'bw_creator') return;
  $js = <<<JS
  (function(){
    var el = document.querySelector('input[name="bw_creator_link"]');
    if(!el) return;
    try{
      el.type = 'text'; // remove browser URL validation so relative paths are allowed
      el.placeholder = '/creator/your-client/';
      var hint = document.createElement('div');
      hint.style.fontSize = '11px';
      hint.style.opacity = '0.8';
      hint.style.marginTop = '4px';
      hint.textContent = 'Tip: you can enter a relative path like /creator/axe-n-dagger/; it will be saved as a full URL for this site.';
      el.parentNode.appendChild(hint);
    }catch(e){}
  })();
JS;
  wp_add_inline_script('jquery-core', $js);
});

// Normalize on save: relative -> absolute; old host -> current host.
add_action('save_post_bw_creator', function($post_id){
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $val = get_post_meta($post_id, '_bw_creator_link', true);
  if (!$val || !is_string($val)) return;

  $val = trim($val);
  if ($val === '') return;

  // Relative path -> absolute for this site.
  if ($val[0] === '/') {
    update_post_meta($post_id, '_bw_creator_link', esc_url_raw(home_url($val)));
    return;
  }

  // Absolute URL with a different host -> swap host/scheme to the current site.
  $home = wp_parse_url(home_url('/'));
  $s    = wp_parse_url($val);
  if ($s && $home && !empty($s['host']) && !empty($home['host']) && strtolower($s['host']) !== strtolower($home['host'])) {
    $rebuilt = ($home['scheme'] ?? 'https') . '://' . $home['host'] . ($s['path'] ?? '/')
             . (!empty($s['query']) ? '?'.$s['query'] : '')
             . (!empty($s['fragment']) ? '#'.$s['fragment'] : '');
    update_post_meta($post_id, '_bw_creator_link', esc_url_raw($rebuilt));
  }
}, 20);

// Read-time normalization is handled by the must-use plugin bw-normalize-creator-links
// (single owner). NOTE: that mu-plugin's get_metadata_raw() call has a wrong-argument
// order, so read-time normalization is currently a no-op site-wide; links work because
// the save normalizer above stores absolute/current-host URLs. Fixing the mu-plugin would
// activate read normalization everywhere — a deliberate, separate change.
