<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://infosoftbd.com
 * @since      1.0.0
 *
 * @package    Order_Verify_Call
 * @subpackage Order_Verify_Call/admin/partials
 */
?>

<div class="wrap ovc-settings">
	<h1><?php esc_html_e( 'Order Verify Call', 'order-verify-call' ); ?></h1>
	<p><?php esc_html_e( 'Call customers through NextGenSwitch and let them press 1 to confirm, 2 to cancel, or 0 for support.', 'order-verify-call' ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'order_verify_call_group' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Enable automatic calls', 'order-verify-call' ); ?></th><td><label><input type="checkbox" name="order_verify_call_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Call orders when they enter a selected status', 'order-verify-call' ); ?></label></td></tr>
			<tr><th scope="row"><label for="ovc-api-url"><?php esc_html_e( 'NextGenSwitch API URL', 'order-verify-call' ); ?></label></th><td><input class="regular-text" type="url" id="ovc-api-url" name="order_verify_call_settings[api_url]" value="<?php echo esc_attr( isset( $settings['api_url'] ) ? $settings['api_url'] : '' ); ?>" placeholder="https://example.nextgenswitch.com" required></td></tr>
			<tr><th scope="row"><label for="ovc-api-key"><?php esc_html_e( 'API key', 'order-verify-call' ); ?></label></th><td><input class="regular-text" type="text" id="ovc-api-key" name="order_verify_call_settings[api_key]" value="<?php echo esc_attr( isset( $settings['api_key'] ) ? $settings['api_key'] : '' ); ?>" autocomplete="off" required><p class="description">X-Authorization</p></td></tr>
			<tr><th scope="row"><label for="ovc-api-secret"><?php esc_html_e( 'API secret', 'order-verify-call' ); ?></label></th><td><input class="regular-text" type="password" id="ovc-api-secret" name="order_verify_call_settings[api_secret]" value="" autocomplete="new-password" placeholder="<?php echo empty( $settings['api_secret'] ) ? esc_attr__( 'Enter secret', 'order-verify-call' ) : esc_attr__( 'Saved - leave blank to keep it', 'order-verify-call' ); ?>"><p class="description">X-Authorization-Secret</p></td></tr>
			<tr><th scope="row"><label for="ovc-from"><?php esc_html_e( 'Caller ID / DID', 'order-verify-call' ); ?></label></th><td><input class="regular-text" type="text" id="ovc-from" name="order_verify_call_settings[from_number]" value="<?php echo esc_attr( isset( $settings['from_number'] ) ? $settings['from_number'] : '' ); ?>" required></td></tr>
			<tr><th scope="row"><label for="ovc-support"><?php esc_html_e( 'Support number', 'order-verify-call' ); ?></label></th><td><input class="regular-text" type="text" id="ovc-support" name="order_verify_call_settings[support_number]" value="<?php echo esc_attr( isset( $settings['support_number'] ) ? $settings['support_number'] : '' ); ?>"><p class="description"><?php esc_html_e( 'Optional extension or phone number used when the customer presses 0.', 'order-verify-call' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Trigger statuses', 'order-verify-call' ); ?></th><td>
				<?php foreach ( wc_get_order_statuses() as $status_key => $label ) : $status = str_replace( 'wc-', '', $status_key ); ?>
					<label class="ovc-status"><input type="checkbox" name="order_verify_call_settings[trigger_statuses][]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, (array) $settings['trigger_statuses'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</td></tr>
		</table>

		<h2><?php esc_html_e( 'Voice audio', 'order-verify-call' ); ?></h2>
		<p><?php esc_html_e( 'Paste a publicly reachable MP3/WAV URL or choose a file from the WordPress Media Library. Blank fields use text-to-speech.', 'order-verify-call' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			$audio_fields = array(
				'audio_prompt'      => __( 'Order prompt', 'order-verify-call' ),
				'audio_confirmed'   => __( 'Confirmed', 'order-verify-call' ),
				'audio_cancelled'   => __( 'Cancelled', 'order-verify-call' ),
				'audio_transferred' => __( 'Transfer', 'order-verify-call' ),
				'audio_invalid'     => __( 'Invalid key', 'order-verify-call' ),
				'audio_no_input'    => __( 'No input', 'order-verify-call' ),
			);
			foreach ( $audio_fields as $key => $label ) :
			?>
			<tr><th scope="row"><label for="ovc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text ovc-audio-url" type="url" id="ovc-<?php echo esc_attr( $key ); ?>" name="order_verify_call_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( isset( $settings[ $key ] ) ? $settings[ $key ] : '' ); ?>"> <button type="button" class="button ovc-select-audio"><?php esc_html_e( 'Choose audio', 'order-verify-call' ); ?></button></td></tr>
			<?php endforeach; ?>
		</table>
		<?php submit_button(); ?>
	</form>
	<hr>
	<p><strong><?php esc_html_e( 'Webhook base:', 'order-verify-call' ); ?></strong> <code><?php echo esc_html( rest_url( 'order-verify-call/v1/' ) ); ?></code></p>
	<p class="description"><?php esc_html_e( 'This URL and all selected audio files must be publicly reachable by NextGenSwitch.', 'order-verify-call' ); ?></p>
</div>
