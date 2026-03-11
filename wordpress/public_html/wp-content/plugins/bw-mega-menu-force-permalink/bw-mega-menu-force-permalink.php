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
