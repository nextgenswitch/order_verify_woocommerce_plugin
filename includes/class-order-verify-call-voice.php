<?php

/** NextGenSwitch call handling and WooCommerce integration. */
class Order_Verify_Call_Voice {
	const OPTION_NAME = 'order_verify_call_settings';
	const ASYNC_HOOK = 'order_verify_call_place_call';

	public function maybe_schedule_call( $order_id, $from, $to ) {
		$settings = self::get_settings();
		$statuses = isset( $settings['trigger_statuses'] ) ? (array) $settings['trigger_statuses'] : array( 'processing', 'on-hold' );
		if ( empty( $settings['enabled'] ) || ! in_array( $to, $statuses, true ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_ovc_call_requested' ) ) {
			return;
		}
		$order->update_meta_data( '_ovc_call_requested', current_time( 'mysql', true ) );
		$order->update_meta_data( '_ovc_verification_status', 'queued' );
		$order->save();
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( self::ASYNC_HOOK, array( $order_id ), 'order-verify-call' );

			if ( $action_id && class_exists( 'ActionScheduler' ) && class_exists( 'ActionScheduler_AsyncRequest_QueueRunner' ) ) {
				$runner = new ActionScheduler_AsyncRequest_QueueRunner( ActionScheduler::store() );
				$runner->maybe_dispatch();
			}
		} else {
			wp_schedule_single_event( time() + 1, self::ASYNC_HOOK, array( $order_id ) );
		}
	}

	public function place_call( $order_id, $force = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'ovc_order_missing', __( 'Order not found.', 'order-verify-call' ) );
		}
		$settings = self::get_settings();
		foreach ( array( 'api_url', 'api_key', 'api_secret', 'from_number' ) as $key ) {
			if ( empty( $settings[ $key ] ) ) {
				return $this->record_error( $order, __( 'NextGenSwitch settings are incomplete.', 'order-verify-call' ) );
			}
		}
		if ( ! $force && in_array( $order->get_meta( '_ovc_verification_status' ), array( 'initiated', 'ringing', 'answered', 'confirmed' ), true ) ) {
			return true;
		}
		$phone = preg_replace( '/[^0-9+]/', '', (string) $order->get_billing_phone() );
		if ( '' === $phone ) {
			return $this->record_error( $order, __( 'The order has no valid billing phone number.', 'order-verify-call' ) );
		}

		$token        = wp_generate_password( 32, false, false );
		$dtmf_url     = add_query_arg( 'token', $token, rest_url( 'order-verify-call/v1/dtmf/' . $order_id ) );
		$status_url   = add_query_arg( 'token', $token, rest_url( 'order-verify-call/v1/status/' . $order_id ) );
		$response_xml = $this->build_initial_xml( $dtmf_url, $settings );
		$endpoint     = untrailingslashit( $settings['api_url'] ) . '/api/v1/call';
		$order->update_meta_data( '_ovc_webhook_token', wp_hash_password( $token ) );
		$order->update_meta_data( '_ovc_verification_status', 'initiating' );
		$order->delete_meta_data( '_ovc_last_error' );
		$order->save();

		$request_timeout = max( 15, (int) apply_filters( 'order_verify_call_request_timeout', 45, $order_id, $endpoint ) );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $request_timeout,
				'headers' => array(
					'Content-Type'           => 'application/x-www-form-urlencoded',
					'X-Authorization'        => $settings['api_key'],
					'X-Authorization-Secret' => $settings['api_secret'],
				),
				'body' => array(
					'to'             => $phone,
					'from'           => $settings['from_number'],
					'statusCallback' => $status_url,
					'responseXml'    => $response_xml,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->record_error( $order, $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			if ( ! empty( $data['message'] ) ) {
				$message = $data['message'];
			} elseif ( ! empty( $data['errors'][0]['message'] ) ) {
				$message = $data['errors'][0]['message'];
			} else {
				$message = sprintf( __( 'NextGenSwitch returned HTTP %d.', 'order-verify-call' ), $code );
			}
			return $this->record_error( $order, sanitize_text_field( $message ) );
		}
		$order->update_meta_data( '_ovc_verification_status', 'initiated' );
		$call_id = ! empty( $data['call_id'] ) ? $data['call_id'] : ( isset( $data['id'] ) ? $data['id'] : '' );
		if ( '' !== $call_id ) {
			$order->update_meta_data( '_ovc_call_id', sanitize_text_field( $call_id ) );
		}
		$order->add_order_note( __( 'NextGenSwitch order verification call initiated.', 'order-verify-call' ) );
		$order->save();
		return true;
	}

	public function register_routes() {
		foreach ( array( 'dtmf' => 'handle_dtmf', 'status' => 'handle_status' ) as $route => $callback ) {
			register_rest_route(
				'order-verify-call/v1',
				'/' . $route . '/(?P<order_id>\d+)',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, $callback ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	public function handle_dtmf( WP_REST_Request $request ) {
		$order = $this->authenticated_order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$settings    = self::get_settings();
		$digit_param = $request->get_param( 'digits' );
		if ( null === $digit_param ) {
			$digit_param = $request->get_param( 'Digits' );
		}
		$digit = trim( (string) $digit_param );
		if ( '1' === $digit ) {
			$this->update_verification( $order, 'confirmed', __( 'Customer confirmed the order by pressing 1.', 'order-verify-call' ) );
			$xml = $this->response_xml( $settings, 'audio_confirmed', __( 'Thank you. Your order is confirmed.', 'order-verify-call' ) );
		} elseif ( '2' === $digit ) {
			$this->update_verification( $order, 'cancelled', __( 'Customer cancelled the order by pressing 2.', 'order-verify-call' ) );
			if ( ! $order->has_status( 'cancelled' ) ) {
				$order->update_status( 'cancelled', __( 'Cancelled by the customer during the verification call.', 'order-verify-call' ) );
			}
			$xml = $this->response_xml( $settings, 'audio_cancelled', __( 'Your order has been cancelled.', 'order-verify-call' ) );
		} elseif ( '0' === $digit && ! empty( $settings['support_number'] ) ) {
			$this->update_verification( $order, 'transferred', __( 'Customer requested a support transfer by pressing 0.', 'order-verify-call' ) );
			$xml = $this->xml_document( $this->voice_node( $settings, 'audio_transferred', __( 'Please hold while we transfer your call.', 'order-verify-call' ) ) . '<Dial>' . esc_xml( $settings['support_number'] ) . '</Dial>' );
		} elseif ( '' === $digit ) {
			$order->update_meta_data( '_ovc_verification_status', 'no_input' );
			$order->add_order_note( __( 'Verification call ended because no keypad selection was received.', 'order-verify-call' ) );
			$order->save();
			$xml = $this->response_xml( $settings, 'audio_no_input', __( 'We did not receive a selection. Goodbye.', 'order-verify-call' ) );
		} else {
			$action = add_query_arg( 'token', (string) $request->get_param( 'token' ), rest_url( 'order-verify-call/v1/dtmf/' . $order->get_id() ) );
			$xml    = $this->xml_document( '<Gather action="' . esc_url( $action ) . '" method="POST" numDigits="1" timeout="15" actionOnEmptyResult="true">' . $this->voice_node( $settings, 'audio_invalid', __( 'Invalid selection. Press 1 to confirm, 2 to cancel, or 0 for support.', 'order-verify-call' ) ) . '</Gather>' );
		}
		/*
		// Log the response XML for debugging purposes without exposing the webhook token.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_xml = preg_replace( '/([?&amp;]token=)[^&quot;&<]+/', '$1[redacted]', $xml );
			$debug_message = sprintf( 'Order %d DTMF digit "%s" response XML: %s', $order->get_id(), $digit, $debug_xml );
			error_log( 'Order Verify Call: ' . $debug_message );
			$order->add_order_note( esc_html( $debug_message ) );
			$order->save();
		}
		*/

		return new WP_REST_Response( $xml, 200, array( 'Content-Type' => 'text/xml; charset=UTF-8' ) );
	}
	public function serve_xml_response( $served, $result, $request, $server ) {
		if ( $served || ! $result instanceof WP_REST_Response || 0 !== strpos( $request->get_route(), '/order-verify-call/v1/' ) ) {
			return $served;
		}

		$headers      = $result->get_headers();
		$content_type = isset( $headers['Content-Type' ] ) ? $headers['Content-Type' ] : '';
		if ( false === stripos( $content_type, 'xml' ) ) {
			return $served;
		}

		status_header( $result->get_status() );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo (string) $result->get_data();
		return true;
	}



	public function handle_status( WP_REST_Request $request ) {
		$order = $this->authenticated_order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$raw = strtolower( sanitize_text_field( (string) ( $request->get_param( 'status' ) ?: $request->get_param( 'CallStatus' ) ?: $request->get_param( 'call_status' ) ) ) );
		if ( '' !== $raw ) {
			$order->update_meta_data( '_ovc_call_status', $raw );
			$current = $order->get_meta( '_ovc_verification_status' );
			if ( ! in_array( $current, array( 'confirmed', 'cancelled', 'transferred' ), true ) ) {
				if ( 'ringing' === $raw ) {
					$order->update_meta_data( '_ovc_verification_status', 'ringing' );
				} elseif ( in_array( $raw, array( 'established', 'answered', 'active' ), true ) ) {
					$order->update_meta_data( '_ovc_verification_status', 'answered' );
				} elseif ( in_array( $raw, array( 'no-answer', 'no_answer', 'busy', 'disconnected' ), true ) ) {
					$order->update_meta_data( '_ovc_verification_status', 'no_answer' );
				} elseif ( 'failed' === $raw ) {
					$order->update_meta_data( '_ovc_verification_status', 'failed' );
				}
			}
			$order->save();
		}
		return new WP_REST_Response( 'OK', 200 );
	}

	public function add_order_action( $actions ) {
		$actions['ovc_retry_verification'] = __( 'Send/retry verification call', 'order-verify-call' );
		return $actions;
	}

	public function run_order_action( $order ) {
		$this->place_call( $order->get_id(), true );
	}

	public function handle_manual_call_request() {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$redirect = remove_query_arg( 'ovc_manual_call', wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );

		if ( ! $order_id || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'ovc_manual_call_' . $order_id ) ) {
			wp_safe_redirect( add_query_arg( 'ovc_manual_call', 'invalid', $redirect ) );
			exit;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_safe_redirect( add_query_arg( 'ovc_manual_call', 'missing', $redirect ) );
			exit;
		}

		if ( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_safe_redirect( add_query_arg( 'ovc_manual_call', 'denied', $redirect ) );
			exit;
		}

		$result = $this->place_call( $order_id, true );
		$notice = is_wp_error( $result ) ? 'failed' : 'sent';

		wp_safe_redirect( add_query_arg( 'ovc_manual_call', $notice, $redirect ) );
		exit;
	}

	public function display_manual_call_notice() {
		if ( empty( $_GET['ovc_manual_call'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['ovc_manual_call'] ) );
		if ( 'sent' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Verification call request sent.', 'order-verify-call' ) . '</p></div>';
		} elseif ( 'failed' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Verification call request failed. Check the order notes for details.', 'order-verify-call' ) . '</p></div>';
		} elseif ( 'denied' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'You do not have permission to send verification calls for this order.', 'order-verify-call' ) . '</p></div>';
		} elseif ( in_array( $notice, array( 'invalid', 'missing' ), true ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The verification call request could not be processed.', 'order-verify-call' ) . '</p></div>';
		}
	}

	public function display_order_details( $order ) {
		$status = $order->get_meta( '_ovc_verification_status' );
		$label  = $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'Not sent', 'order-verify-call' );

		echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Voice verification:', 'order-verify-call' ) . '</strong> ' . esc_html( $label );
		$call_id = $order->get_meta( '_ovc_call_id' );
		if ( $call_id ) {
			echo '<br><small>' . esc_html__( 'Call ID:', 'order-verify-call' ) . ' ' . esc_html( $call_id ) . '</small>';
		}
		$last_error = $order->get_meta( '_ovc_last_error' );
		if ( $last_error ) {
			echo '<br><small>' . esc_html__( 'Last error:', 'order-verify-call' ) . ' ' . esc_html( $last_error ) . '</small>';
		}
		if ( current_user_can( 'edit_shop_order', $order->get_id() ) || current_user_can( 'manage_woocommerce' ) ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => 'ovc_manual_verification_call',
						'order_id' => $order->get_id(),
					),
					admin_url( 'admin-post.php' )
				),
				'ovc_manual_call_' . $order->get_id()
			);
			echo '<br><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Send verification call', 'order-verify-call' ) . '</a>';
		}
		echo '</p>';
	}

	private function authenticated_order( WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['order_id'] ) );
		if ( ! $order ) {
			return new WP_Error( 'ovc_order_missing', __( 'Order not found.', 'order-verify-call' ), array( 'status' => 404 ) );
		}
		$hash = (string) $order->get_meta( '_ovc_webhook_token' );
		if ( ! $hash || ! wp_check_password( (string) $request->get_param( 'token' ), $hash ) ) {
			return new WP_Error( 'ovc_invalid_token', __( 'Invalid webhook token.', 'order-verify-call' ), array( 'status' => 403 ) );
		}
		return $order;
	}

	private function build_initial_xml( $dtmf_url, $settings ) {
		$prompt = __( 'Press 1 to confirm your order, press 2 to cancel, or press 0 to speak with support.', 'order-verify-call' );
		return $this->xml_document( '<Gather action="' . esc_url( $dtmf_url ) . '" method="POST" numDigits="1" timeout="15" actionOnEmptyResult="true">' . $this->voice_node( $settings, 'audio_prompt', $prompt ) . '</Gather>' );
	}

	private function response_xml( $settings, $audio_key, $fallback ) {
		return $this->xml_document( $this->voice_node( $settings, $audio_key, $fallback ) );
	}
	private function voice_node( $settings, $audio_key, $fallback ) {
		return ! empty( $settings[ $audio_key ] ) ? '<Play>' . esc_xml( $settings[ $audio_key ] ) . '</Play>' : '<Say>' . esc_xml( $fallback ) . '</Say>';
	}

	private function xml_document( $content ) {
		return '<?xml version="1.0" encoding="UTF-8"?><Response>' . $content . '</Response>';
	}

	private function update_verification( $order, $status, $note ) {
		$order->update_meta_data( '_ovc_verification_status', $status );
		$order->add_order_note( $note );
		$order->save();
	}

	private function record_error( $order, $message ) {
		$order->update_meta_data( '_ovc_verification_status', 'failed' );
		$order->update_meta_data( '_ovc_last_error', sanitize_text_field( $message ) );
		$order->add_order_note( sprintf( __( 'Verification call failed: %s', 'order-verify-call' ), sanitize_text_field( $message ) ) );
		$order->save();
		return new WP_Error( 'ovc_call_failed', $message );
	}

	public static function get_settings() {
		return wp_parse_args( (array) get_option( self::OPTION_NAME, array() ), array( 'enabled' => 0, 'trigger_statuses' => array( 'processing', 'on-hold' ) ) );
	}
}
