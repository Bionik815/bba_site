<?php
/**
 * Plugin Name: BW Normalize Creator Links
 * Description: Normalizes _bw_creator_link to the current site domain at runtime; supports absolute, relative, or old-host URLs.
 */

if (!defined('ABSPATH')) exit;

/**
 * Normalize a stored URL so it always points to the current site domain.
 * - If value starts with '/', prefix home_url()
 * - If absolute URL with different host, swap scheme+host to current site's
 */
function bw_normalize_creator_link_value($raw){
  if (!is_string($raw) || $raw === '') return $raw;

  // Relative path? -> make absolute to current site
  if ($raw[0] === '/') {
    return home_url($raw);
  }

  $site = wp_parse_url(home_url('/'));
  $val  = wp_parse_url($raw);
  if (!$val) return $raw;

  // If it already matches current host, leave as-is
  if (!empty($val['host']) && !empty($site['host']) && strtolower($val['host']) !== strtolower($site['host'])) {
    // Rebuild with current host/scheme, keep path/query/fragment
    $scheme = !empty($site['scheme']) ? $site['scheme'] : 'https';
    $host   = $site['host'];
    $path   = isset($val['path']) ? $val['path'] : '/';
    $query  = isset($val['query']) ? ('?'.$val['query']) : '';
    $frag   = isset($val['fragment']) ? ('#'.$val['fragment']) : '';
    return $scheme . '://' . $host . $path . $query . $frag;
  }

  return $raw;
}

/**
 * Intercept meta reads for _bw_creator_link and normalize them.
 * This makes ANY consumer (mega menu, templates, etc.) get the corrected URL.
 */
add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single){
  if ($meta_key !== '_bw_creator_link') return $value; // only intercept our field
  $stored = get_metadata_raw($object_id, $meta_key, true); // avoid recursion
  if (!$stored) return $value;

  $norm = bw_normalize_creator_link_value($stored);
  // Return in the shape WP expects for get_post_meta
  if ($single) return $norm;
  return [$norm];
}, 10, 4);

/**
 * (Nice-to-have) If your Mega Menu exposes a link filter, normalize there too.
 * Safe to keep even if the filter doesn't exist.
 */
add_filter('bwmm_creator_link', function($url){
  return bw_normalize_creator_link_value($url);
}, 10);
