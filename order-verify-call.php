<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://infosoftbd.com
 * @since             1.0.0
 * @package           Order_Verify_Call
 *
 * @wordpress-plugin
 * Plugin Name:       Order Verify Call
 * Plugin URI:        https://infosoftbd.com/plugins/order-verify-call
 * Description:       Automatically verify WooCommerce orders through IVR phone calls, reducing fake orders and improving order confirmation rates.
 * Version:           1.0.0
 * Author:            Infosoftbd Solutions
 * Author URI:        https://infosoftbd.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       order-verify-call
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ORDER_VERIFY_CALL_VERSION', '1.0.0' );

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-order-verify-call-activator.php
 */
function activate_order_verify_call() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-order-verify-call-activator.php';
	Order_Verify_Call_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-order-verify-call-deactivator.php
 */
function deactivate_order_verify_call() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-order-verify-call-deactivator.php';
	Order_Verify_Call_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_order_verify_call' );
register_deactivation_hook( __FILE__, 'deactivate_order_verify_call' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-order-verify-call.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_order_verify_call() {

	$plugin = new Order_Verify_Call();
	$plugin->run();

}
run_order_verify_call();
