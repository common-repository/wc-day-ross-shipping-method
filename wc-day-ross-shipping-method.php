<?php
/*
 * Plugin Name:       Shipping Method for Day & Ross on Woocommerce
 * Plugin URI:        https://rapidplugin.com/plugins/Day&Ross-Shipping-Plugin/
 * Description:       This plugin adds a new shipping method in Woocommerce for Day&Ross Services.
 * Version:           1.2.0
 * Author:            RapidPlugin
 * Author URI:        https://rapidplugin.com/
 * WP tested up to:   6.0.3
 * Requires PHP:      7.2
 * License:           GPLv3
 */
defined( 'ABSPATH' ) || exit;
define( 'RapidPlugin_DayAndRoss_BaseName', plugin_basename( __FILE__ ) );
define( 'RapidPlugin_DayAndRoss_Path', plugin_dir_path( __FILE__ ) );
require_once 'includes/main-class.php';
add_action( 'plugins_loaded', [ 'RapidPlugin_DayAndRoss', 'run' ], 0 );