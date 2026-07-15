<?php
/**
 * Plugin Name: BW Gang Sheet Builder
 * Description: Customer-facing DTF gang sheet builder and submission workflow with WooCommerce-ready foundations.
 * Version: 0.4.0
 * Author: Barebones Apparel
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BW_GSB_VERSION', '0.7.2');
define('BW_GSB_FILE', __FILE__);
define('BW_GSB_PATH', plugin_dir_path(__FILE__));
define('BW_GSB_URL', plugin_dir_url(__FILE__));

require_once BW_GSB_PATH . 'includes/class-bw-gsb-plugin.php';

BW_GSB_Plugin::get_instance();
