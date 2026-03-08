<?php
/**
 * Plugin Name: BW Creator Link Helper
 * Description: Allows entering relative paths for Creator “Store URL” and saves them as absolute to current site.
 */
if (!defined('ABSPATH')) exit;

add_action('save_post_bw_creator', function($post_id){
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $val = get_post_meta($post_id, '_bw_creator_link', true);
  if (!$val || !is_string($val)) return;

  $val = trim($val);
  // If relative like "/creator/axe-n-dagger/", convert to absolute
  if ($val !== '' && $val[0] === '/') {
    $abs = home_url($val);
    update_post_meta($post_id, '_bw_creator_link', esc_url_raw($abs));
  }
}, 20);

// Safety: normalize on read too (plays nice with domain moves)
add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single){
  if ($meta_key !== '_bw_creator_link') return $value;
  $stored = get_metadata_raw($object_id, $meta_key, true);
  if (!$stored) return $value;
  // If it’s relative, prefix; if it’s absolute with old host, swap to current.
  $home = home_url('/');
  if ($stored[0] === '/') return $single ? home_url($stored) : [home_url($stored)];
  $h = wp_parse_url($home); $s = wp_parse_url($stored);
  if ($s && $h && !empty($s['host']) && !empty($h['host']) && strtolower($s['host']) !== strtolower($h['host'])) {
    $rebuilt = ($h['scheme'] ?? 'https').'://'.$h['host'].($s['path'] ?? '/').(!empty($s['query'])?'?'.$s['query']:'').(!empty($s['fragment'])?'#'.$s['fragment']:'');
    return $single ? $rebuilt : [$rebuilt];
  }
  return $value;
}, 10, 4);
