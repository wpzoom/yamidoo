# Yamidoo – AI Support Chat

Connect your WordPress site to [Yamidoo](https://yamidoo.ai/) and add the AI support chat widget in seconds. The widget answers visitors from your own content and hands off to a human on your team when needed.

Yamidoo is a hosted service — this plugin is a lightweight connector. It **installs the widget** and optionally **tells Yamidoo who your logged-in users are**. Everything about how the widget looks and behaves (colors, welcome message, launcher icon, suggested questions, lead capture, human handoff, …) is configured in your **Yamidoo dashboard**, so changes go live automatically with nothing to update in WordPress.

> **Requires a Yamidoo account.** Create one and grab your Site ID at [yamidoo.ai](https://yamidoo.ai/).

---

## Features

- One-field setup — paste your **Site ID** and the widget appears.
- Loads the official hosted `widget.js`, so it always matches your dashboard configuration and stays up to date.
- Optionally identifies logged-in WordPress users to Yamidoo (name, email, username, user ID) so your support team knows who they're talking to — **User Switching–safe**.
- **Customer data connector** for Easy Digital Downloads (customer, licenses, orders, subscriptions) and WooCommerce (orders, subscriptions): shown in the Yamidoo inbox next to the conversation, and used by the AI to answer logged-in customers' account questions. Fetched on demand over a signed request, never stored. Extend with the `yamidoo_customer_sections` filter.
- Plays nicely with caching/optimization tools (WP Rocket, Cloudflare Rocket Loader).
- No "Pro" version, no upsells. Any plan limits or premium widget features are handled by your Yamidoo subscription on the service side.

## Requirements

- WordPress **6.4+**
- PHP **7.4+**
- A Yamidoo account and a Site ID

## Installation

1. Copy the plugin folder to `wp-content/plugins/yamidoo/` and activate **Yamidoo** in **Plugins**.
2. In your [Yamidoo dashboard](https://app.yamidoo.ai/), open your site and copy its **Site ID** from **Integrations → WordPress**.
3. In WordPress, go to **Settings â Yamidoo** and paste the Site ID.
4. Make sure **Show the Yamidoo chat widget** is enabled, then **Save Changes**. The widget appears on your site immediately.

## Settings

Found under **Settings â Yamidoo**. All settings are stored in a single option, `yamidoo_settings`.

| Setting | Key | Default | Description |
| --- | --- | --- | --- |
| Site ID | `site_id` | `''` | Your Yamidoo site UUID. The only credential the widget needs. |
| Show the widget | `enabled` | `on` | Output the widget on the front end. |
| Identify logged-in users | `identify_logged_in` | `on` | Send the logged-in user's name/email/username/ID to Yamidoo via the widget's JS API. |
| Customer lookup | `share_customer_data` | `off` | Serve `POST /wp-json/yamidoo/v1/customer` (EDD / WooCommerce card) to Yamidoo, and sign logged-in identities. |
| Lookup secret | `lookup_secret` | `''` | Generated in the dashboard under **Integrations → Customer data**; signs every lookup (`sha256=HMAC(secret, "ts.email")`) and the identify signature (`HMAC(secret, lowercase email)`). |

Widget appearance and behavior are **not** configured here — use the **Customize your widget → Open the Yamidoo dashboard** link on the settings screen.

## Printing the widget yourself

If your theme already outputs `widget.js`, turn **Show the widget** off and keep customer lookup on. The customer endpoint keeps working, and the inbox shows the customer's record. For the AI to use it, sign your own `identify()` call:

```php
<?php $user = wp_get_current_user(); ?>
<script>
yamidoo('identify', {
  name: <?php echo wp_json_encode( $user->display_name ); ?>,
  email: <?php echo wp_json_encode( $user->user_email ); ?>,
  signature: <?php echo wp_json_encode( yamidoo_identity_signature( $user->user_email ) ); ?>
});
</script>
```

`yamidoo_identity_signature()` returns `''` while sharing is off, so the call is safe to leave in place. Guard it with `function_exists()` if the plugin may be deactivated.

## License

GPL-2.0-or-later. See <https://www.gnu.org/licenses/gpl-2.0.html>.
