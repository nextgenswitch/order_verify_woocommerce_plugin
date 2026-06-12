# Order Verify Call

Automatically verify WooCommerce orders with interactive voice calls through NextGenSwitch.

When an order enters a configured status, the plugin calls the customer's billing phone number and asks them to:

- Press `1` to confirm the order.
- Press `2` to cancel the order.
- Press `0` to speak with support, when a support number is configured.

The result is stored on the WooCommerce order and added to its order notes. A cancellation moves the order to the **Cancelled** status, while confirmation is recorded without changing the current WooCommerce order status.

## Features

- Automatically starts verification calls for selected WooCommerce order statuses.
- Uses Action Scheduler when available, with WP-Cron as a fallback.
- Supports custom MP3 or WAV prompts from the WordPress Media Library or a public URL.
- Uses text-to-speech messages when custom audio is not provided.
- Records queued, initiated, ringing, answered, confirmed, cancelled, transferred, no-input, no-answer, and failed results.
- Adds call results and failures to WooCommerce order notes.
- Displays the verification result and provider call ID on the order screen.
- Provides a WooCommerce order action for manually sending or retrying calls.
- Protects callback URLs with a unique, hashed token for each call.
- Supports WooCommerce High-Performance Order Storage (HPOS).
- Supports translations through the `order-verify-call` text domain.

## Requirements

- WordPress
- WooCommerce
- A NextGenSwitch account
- A NextGenSwitch API URL, API key, API secret, and caller ID/DID
- A publicly reachable WordPress REST API
- Working Action Scheduler or WP-Cron processing
- HTTPS for the store, callback URLs, and audio files is strongly recommended

## Installation

1. Upload the `order-verify-call` directory to `/wp-content/plugins/`.
2. Alternatively, upload the plugin ZIP from **Plugins > Add New > Upload Plugin**.
3. Activate **Order Verify Call** from the WordPress Plugins screen.
4. Make sure WooCommerce is installed and active.
5. Open **WooCommerce > Order Verify Call**.
6. Configure the NextGenSwitch connection and call settings.

## Configuration

### NextGenSwitch connection

Configure the following fields under **WooCommerce > Order Verify Call**:

| Setting | Description |
| --- | --- |
| NextGenSwitch API URL | Base URL of the NextGenSwitch installation. Do not include `/api/v1/call`. |
| API key | Sent to NextGenSwitch in the `X-Authorization` header. |
| API secret | Sent in the `X-Authorization-Secret` header. Leave it blank after saving to retain the existing secret. |
| Caller ID / DID | Number used as the caller ID for outgoing verification calls. |
| Support number | Optional phone number or extension dialed when the customer presses `0`. |

### Trigger statuses

Select one or more WooCommerce statuses that should trigger a verification call.

When no trigger settings have been saved, the plugin defaults to **Processing** and **On hold**. Automatic calling remains disabled until **Enable automatic calls** is selected.

An automatic call is normally queued only the first time an order enters a selected status.

### Voice audio

Custom MP3 or WAV audio can be configured for:

- Order prompt
- Confirmed response
- Cancelled response
- Support transfer
- Invalid key
- No input

Use **Choose audio** to select a file from the WordPress Media Library, or enter a public audio URL. Blank fields use the built-in text-to-speech messages.

Audio URLs must be publicly accessible to NextGenSwitch. Localhost URLs, private network addresses, protected media, and URLs blocked by a firewall will not work.

## Call Workflow

1. A WooCommerce order enters a configured trigger status.
2. The plugin queues an asynchronous call request.
3. NextGenSwitch calls the order's billing phone number.
4. The customer hears the order prompt and presses `1`, `2`, or `0`.
5. NextGenSwitch sends the keypad selection and call status to the plugin's REST endpoints.
6. The plugin updates the verification result and adds an order note.

The callback base URL is displayed on the settings page and follows this format:

```text
https://example.com/wp-json/order-verify-call/v1/
```

Callback URLs normally do not need to be registered manually because the plugin includes them in each NextGenSwitch call request.

## Customer Actions

| Key | Result | WooCommerce behavior |
| --- | --- | --- |
| `1` | Confirmed | Records confirmation and keeps the current order status. |
| `2` | Cancelled | Records cancellation and moves the order to the Cancelled status. |
| `0` | Transferred | Transfers the call when a support number is configured. |
| No input | No input | Records that no keypad selection was received. |
| Other key | Invalid | Repeats the prompt and asks for another selection. |

Without a configured support number, pressing `0` is treated as an invalid selection.

## Manual Calls and Retries

1. Open an order in WooCommerce.
2. Find the **Order actions** panel.
3. Select **Send/retry verification call**.
4. Apply the action.

A manual retry forces a new call request even if the order was called previously. Review the order notes before retrying to avoid contacting the customer unnecessarily.

## Verification Statuses

The order screen can display the following verification states:

| Status | Meaning |
| --- | --- |
| `queued` | The call has been scheduled. |
| `initiating` | The request is being sent to NextGenSwitch. |
| `initiated` | NextGenSwitch accepted the call request. |
| `ringing` | The customer's phone is ringing. |
| `answered` | The call was answered or established. |
| `confirmed` | The customer pressed `1`. |
| `cancelled` | The customer pressed `2`. |
| `transferred` | The customer requested support. |
| `no_input` | No keypad selection was received. |
| `no_answer` | The call was unanswered, busy, or disconnected. |
| `failed` | The call request or provider callback reported a failure. |

## Troubleshooting

### Test the setup

1. Save the NextGenSwitch settings with automatic calls disabled.
2. Create a test order with a phone number you control.
3. Open the order and run **Send/retry verification call** from **Order actions**.
4. Check the order notes and the **Voice verification** field.
5. Test all configured keypad actions before enabling automatic calls for live orders.

### An automatic call was not sent

Check that:

- Automatic calls are enabled.
- The order entered a selected trigger status after configuration.
- The order has a valid billing phone number.
- The API URL, key, secret, and caller ID/DID are complete.
- Action Scheduler or WP-Cron is processing queued jobs.
- The order was not already marked as called.

Use the manual order action to send or retry the call when needed.

### Inspect scheduled actions

WooCommerce normally processes calls through Action Scheduler. Open **WooCommerce > Status > Scheduled Actions** and search for:

```text
order_verify_call_place_call
```

If Action Scheduler is unavailable, the plugin uses WP-Cron. Low-traffic sites may need a server cron job to process WordPress scheduled events reliably.

### Callbacks or keypad selections are failing

Confirm that the WordPress REST API is publicly reachable. Security plugins, web application firewalls, basic authentication, and maintenance mode must not block:

```text
/wp-json/order-verify-call/v1/
```

### Find call errors

Open the WooCommerce order. Failed requests are stored as order notes, and the current verification result is displayed below the billing details.

## REST API

The plugin registers two callback endpoints:

```text
POST /wp-json/order-verify-call/v1/dtmf/{order_id}
POST /wp-json/order-verify-call/v1/status/{order_id}
```

Each callback must include the unique `token` generated for that call. Only a password hash of the token is stored with the order.

## Developer Hook

The NextGenSwitch API request timeout defaults to 45 seconds. Change it with the `order_verify_call_request_timeout` filter:

```php
add_filter(
    'order_verify_call_request_timeout',
    function () {
        return 60;
    }
);
```

The plugin enforces a minimum timeout of 15 seconds.

## Privacy and External Services

This plugin sends data to the configured NextGenSwitch service to place verification calls. The request includes:

- The customer's WooCommerce billing phone number
- The configured caller ID/DID
- A callback URL containing the order ID and a single-call security token
- XML instructions containing the voice prompt or public audio URLs

Call status and keypad responses are sent back to the site's WordPress REST API and stored as WooCommerce order metadata and order notes.

Store owners are responsible for reviewing NextGenSwitch's privacy terms, informing customers where required, and complying with applicable consent, telemarketing, privacy, and call-recording laws.

## Uninstallation

Deleting the plugin from the WordPress Plugins screen removes the `order_verify_call_settings` option.

Existing verification metadata and notes attached to WooCommerce orders are retained.

## Changelog

### 1.0.0

- Initial release.
- Added automatic WooCommerce order verification calls through NextGenSwitch.
- Added keypad confirmation, cancellation, and support transfer actions.
- Added custom audio prompts with text-to-speech fallbacks.
- Added call status tracking, order notes, manual retries, and HPOS compatibility.

## License

Licensed under the [GNU General Public License v2.0 or later](LICENSE.txt).

## Author

[Infosoftbd Solutions](https://infosoftbd.com/)
