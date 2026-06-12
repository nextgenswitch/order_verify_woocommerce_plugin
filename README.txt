=== Order Verify Call ===
Contributors: infosoftbd
Tags: woocommerce, order verification, ivr, phone call, fraud prevention
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verify WooCommerce orders automatically with interactive NextGenSwitch voice calls.

== Description ==

Order Verify Call helps WooCommerce stores confirm orders by calling the customer's billing phone number through NextGenSwitch.

When an order enters one of your selected statuses, the plugin schedules a call and asks the customer to choose an action from their phone keypad:

* Press 1 to confirm the order.
* Press 2 to cancel the order.
* Press 0 to speak with support, when a support number is configured.

The result is saved on the WooCommerce order and added to its order notes. Customer cancellations automatically move the order to the Cancelled status. Confirmations are recorded without changing the current WooCommerce order status.

= Features =

* Automatically starts verification calls for selected WooCommerce order statuses.
* Uses Action Scheduler when available, with WP-Cron as a fallback.
* Supports custom MP3 or WAV prompts from the WordPress Media Library or a public URL.
* Uses text-to-speech messages when custom audio is not provided.
* Records queued, initiated, ringing, answered, confirmed, cancelled, transferred, no-input, no-answer, and failed results.
* Adds call results and failures to WooCommerce order notes.
* Displays the verification result and provider call ID on the order screen.
* Provides a WooCommerce order action for sending or retrying a call manually.
* Protects callback URLs with a unique, hashed token for each call.
* Supports WooCommerce High-Performance Order Storage (HPOS).
* Includes translation support through the `order-verify-call` text domain.

= Requirements =

* A working WordPress installation.
* WooCommerce installed and active.
* A NextGenSwitch account, API URL, API key, API secret, and caller ID/DID.
* A publicly reachable WordPress REST API.
* HTTPS is strongly recommended for the store, callback URLs, and audio files.
* Working Action Scheduler or WP-Cron processing for automatic calls.

== Installation ==

1. Upload the `order-verify-call` directory to `/wp-content/plugins/`, or install the plugin ZIP from Plugins > Add New > Upload Plugin.
2. Activate Order Verify Call from the WordPress Plugins screen.
3. Make sure WooCommerce is installed and active.
4. Open WooCommerce > Order Verify Call.
5. Enter the NextGenSwitch API details and caller ID/DID.
6. Select the WooCommerce statuses that should trigger a verification call.
7. Optionally configure a support number and custom voice audio.
8. Enable automatic calls and save the settings.

== Configuration ==

= NextGenSwitch connection =

Configure these fields under WooCommerce > Order Verify Call:

* NextGenSwitch API URL: The base URL of your NextGenSwitch installation. Do not include `/api/v1/call`.
* API key: Sent to NextGenSwitch in the `X-Authorization` header.
* API secret: Sent in the `X-Authorization-Secret` header. Leave the field blank after saving to keep the existing secret.
* Caller ID / DID: The number used as the caller ID for outgoing verification calls.
* Support number: An optional phone number or extension dialed when the customer presses 0.

= Trigger statuses =

Choose one or more WooCommerce order statuses. A call is queued the first time an order enters a selected status.

By default, the plugin preselects Processing and On hold when no trigger settings have been saved. Automatic calling remains disabled until you enable it.

= Voice audio =

Each call response can use a custom MP3 or WAV file:

* Order prompt
* Confirmed
* Cancelled
* Transfer
* Invalid key
* No input

Use Choose audio to select a file from the WordPress Media Library, or paste a public audio URL. When a field is blank, NextGenSwitch uses the plugin's text-to-speech message.

The audio URLs must be publicly accessible to NextGenSwitch. Localhost URLs, private network addresses, protected media, and URLs blocked by a firewall will not work.

== How It Works ==

1. A WooCommerce order enters a configured trigger status.
2. The plugin queues an asynchronous call request.
3. NextGenSwitch calls the order's billing phone number.
4. The customer hears the order prompt and presses 1, 2, or 0.
5. NextGenSwitch sends the keypad result and call status to the plugin's REST endpoints.
6. The plugin updates the verification result and adds an order note.

The callback base URL is shown on the settings page and follows this format:

`https://example.com/wp-json/order-verify-call/v1/`

You normally do not need to register callback URLs manually because they are included in each NextGenSwitch call request.

== Manual Calls and Retries ==

To send or retry a verification call:

1. Open the order in WooCommerce.
2. Find the Order actions panel.
3. Select Send/retry verification call.
4. Apply the action.

A manual retry forces a new call request even if the order was called previously. Review the order notes before retrying to avoid contacting the customer unnecessarily.

== Frequently Asked Questions ==

= Does confirmation change the WooCommerce order status? =

No. Pressing 1 records the verification result as Confirmed and adds an order note, but keeps the current WooCommerce order status unchanged.

= What happens when the customer presses 2? =

The verification result is recorded as Cancelled and the WooCommerce order is moved to the Cancelled status.

= What happens when the customer presses 0? =

If a support number is configured, the call is transferred to that number and the verification result is recorded as Transferred. Without a support number, 0 is treated as an invalid selection.

= Can I use the plugin without custom audio files? =

Yes. Leave the audio fields blank to use the built-in text-to-speech messages.

= Why was an automatic call not sent? =

Check the following:

* Automatic calls are enabled.
* The order entered a selected trigger status after the plugin was configured.
* The order has a valid billing phone number.
* The API URL, key, secret, and caller ID/DID are complete.
* Action Scheduler or WP-Cron is processing queued jobs.
* The order was not already marked as called.

You can use the manual order action to send or retry the call.

= Why are callbacks or keypad selections failing? =

Confirm that the WordPress REST API is publicly reachable and that security plugins, a web application firewall, basic authentication, or maintenance mode are not blocking `/wp-json/order-verify-call/v1/`.

= Where can I see call errors? =

Open the WooCommerce order. Failed requests are stored as order notes, and the current verification result appears below the billing details.

== Troubleshooting ==

= Test the setup =

1. Save the NextGenSwitch settings with automatic calls disabled.
2. Create a test order with a phone number you control.
3. Open the order and run Send/retry verification call from Order actions.
4. Check the order notes and the Voice verification field.
5. Test all configured keypad actions before enabling automatic calls for live orders.

= Scheduled actions =

WooCommerce normally processes calls through Action Scheduler. You can inspect jobs under WooCommerce > Status > Scheduled Actions and search for the `order_verify_call_place_call` hook.

If Action Scheduler is unavailable, the plugin uses WP-Cron. A low-traffic site may need a real server cron job to run WordPress scheduled events reliably.

== Privacy and External Services ==

This plugin sends data to the configured NextGenSwitch service to place verification calls. The request includes:

* The customer's WooCommerce billing phone number.
* The configured caller ID/DID.
* A callback URL containing the order ID and a single-call security token.
* XML instructions containing the voice prompt or public audio URLs.

Call status and keypad responses are sent back to the site's WordPress REST API and stored as WooCommerce order metadata and order notes.

Store owners are responsible for configuring NextGenSwitch, reviewing its privacy terms, informing customers where required, and complying with applicable consent, telemarketing, privacy, and call-recording laws.

== Developer Notes ==

= REST endpoints =

The plugin registers two authenticated callback endpoints:

* `POST /wp-json/order-verify-call/v1/dtmf/{order_id}`
* `POST /wp-json/order-verify-call/v1/status/{order_id}`

Each callback must include the unique `token` generated for that call. Only a password hash of the token is stored with the order.

= Request timeout filter =

The NextGenSwitch API request timeout defaults to 45 seconds and can be changed with this filter:

`order_verify_call_request_timeout`

Example:

`add_filter( 'order_verify_call_request_timeout', function () { return 60; } );`

The plugin enforces a minimum timeout of 15 seconds.

== Uninstallation ==

Deleting the plugin from the WordPress Plugins screen removes the `order_verify_call_settings` option. Existing verification metadata and notes attached to WooCommerce orders are retained.

== Changelog ==

= 1.0.0 =

* Initial release.
* Added automatic WooCommerce order verification calls through NextGenSwitch.
* Added keypad confirmation, cancellation, and support transfer actions.
* Added custom audio prompts with text-to-speech fallbacks.
* Added call status tracking, order notes, manual retries, and HPOS compatibility.
