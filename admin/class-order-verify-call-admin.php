<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://infosoftbd.com
 * @since      1.0.0
 *
 * @package    Order_Verify_Call
 * @subpackage Order_Verify_Call/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Order_Verify_Call
 * @subpackage Order_Verify_Call/admin
 * @author     Infosoftbd Solutions <info@infosoftbd.com>
 */
class Order_Verify_Call_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Order_Verify_Call_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Order_Verify_Call_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if ( isset( $_GET['page'] ) && 'order-verify-call' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/order-verify-call-admin.css', array(), $this->version, 'all' );
		}

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Order_Verify_Call_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Order_Verify_Call_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if ( isset( $_GET['page'] ) && 'order-verify-call' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			wp_enqueue_media();
			$script_path = plugin_dir_path( __FILE__ ) . 'js/order-verify-call-admin.js';
			$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : $this->version;

			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/order-verify-call-admin.js',
				array( 'jquery', 'media-editor' ),
				$version,
				true
			);
		}

	}

	public function add_settings_page() {
		add_submenu_page( 'woocommerce', __( 'Order Verify Call', 'order-verify-call' ), __( 'Order Verify Call', 'order-verify-call' ), 'manage_woocommerce', 'order-verify-call', array( $this, 'render_settings_page' ) );
	}

	public function register_settings() {
		register_setting( 'order_verify_call_group', Order_Verify_Call_Voice::OPTION_NAME, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$current = Order_Verify_Call_Voice::get_settings();
		$output  = array();
		$output['enabled'] = empty( $input['enabled'] ) ? 0 : 1;
		$output['api_url'] = isset( $input['api_url'] ) ? untrailingslashit( esc_url_raw( $input['api_url'] ) ) : '';
		$output['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$output['api_secret'] = ! empty( $input['api_secret'] ) ? sanitize_text_field( $input['api_secret'] ) : ( isset( $current['api_secret'] ) ? $current['api_secret'] : '' );
		$output['from_number'] = isset( $input['from_number'] ) ? sanitize_text_field( $input['from_number'] ) : '';
		$output['support_number'] = isset( $input['support_number'] ) ? sanitize_text_field( $input['support_number'] ) : '';
		$output['trigger_statuses'] = isset( $input['trigger_statuses'] ) ? array_map( 'sanitize_key', (array) $input['trigger_statuses'] ) : array();
		foreach ( array( 'audio_prompt', 'audio_confirmed', 'audio_cancelled', 'audio_transferred', 'audio_invalid', 'audio_no_input' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( $input[ $key ] ) : '';
		}
		return $output;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = Order_Verify_Call_Voice::get_settings();
		include plugin_dir_path( __FILE__ ) . 'partials/order-verify-call-admin-display.php';
	}

}
