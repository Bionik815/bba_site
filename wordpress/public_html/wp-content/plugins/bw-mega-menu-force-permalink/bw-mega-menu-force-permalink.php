<?php
/**
 * Plugin Name: BW Mega Menu – Force Creator Permalink
 * Description: Makes creator tiles use the post permalink instead of the stored Store URL.
 */
if (!defined('ABSPATH')) exit;

add_filter('bwmm_creator_link', function($url, $creator_id){
  $perma = get_permalink($creator_id);
  return $perma ?: $url;
}, 10, 2);

/* Fallback: if your mega menu doesn’t expose bwmm_creator_link, force reads */
add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single){
  if ($meta_key !== '_bw_creator_link') return $value;
  $perma = get_permalink($object_id);
  if (!$perma) return $value;
  return $single ? $perma : [$perma];
}, 9, 4);
