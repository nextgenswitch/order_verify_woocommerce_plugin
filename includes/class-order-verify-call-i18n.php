<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://infosoftbd.com
 * @since      1.0.0
 *
 * @package    Order_Verify_Call
 * @subpackage Order_Verify_Call/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Order_Verify_Call
 * @subpackage Order_Verify_Call/includes
 * @author     Infosoftbd Solutions <info@infosoftbd.com>
 */
class Order_Verify_Call_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'order-verify-call',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
