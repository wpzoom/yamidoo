=== Yamidoo ===
Contributors: yamidoo
Tags: chat, live chat, support, ai chatbot, customer support
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your website to Yamidoo and add an AI support chat widget that answers visitors from your own content and hands off to a human when needed.

== Description ==

Yamidoo is an AI-first customer support chat. This plugin connects your WordPress site to your Yamidoo account and adds the chat widget to your site in seconds — no code required.

The widget answers your visitors instantly from your content and can hand off to a human on your team when needed. All appearance and behavior (colors, welcome message, launcher icon, suggested questions, lead capture, human handoff, and more) are configured in your **Yamidoo dashboard**, so changes go live automatically without updating anything in WordPress.

**What this plugin does**

* Adds the Yamidoo chat widget to the front end of your site with a single Site ID.
* Optionally identifies logged-in WordPress users to Yamidoo (name, email, username, user ID) so your support team knows who they are talking to.
* Plays nicely with caching and optimization plugins — WP Rocket, LiteSpeed Cache, Autoptimize, Perfmatters, SiteGround Optimizer and Cloudflare Rocket Loader are all told to leave the widget alone.

**Requires a Yamidoo account.** Create one and get your Site ID at [yamidoo.ai](https://yamidoo.ai/).

== External services ==

This plugin connects to Yamidoo, a hosted third-party service operated by Yamidoo, to provide the AI support chat. It is required for the plugin to function.

* **What it is:** Yamidoo loads the chat widget script (`widget.js`) from the Yamidoo app at `https://app.yamidoo.ai` and communicates with the Yamidoo API on the same host to fetch your widget's configuration and to power live conversations.
* **What data is sent, and when:**
  * When a visitor interacts with the widget: their chat messages, an anonymous session identifier, the page URL/referrer and basic browser/device metadata, and any files a visitor chooses to upload in the chat.
  * If "identify logged-in users" is enabled and a user is logged in: that user's WordPress display name, email address, username and user ID.
  * When loading the widget: your Site ID (a public identifier used to select your widget configuration).
* **Service links:** [Website](https://yamidoo.ai/) · [Terms of Service](https://yamidoo.ai/terms) · [Privacy Policy](https://yamidoo.ai/privacy)

By installing this plugin and adding your Site ID, you agree to Yamidoo's Terms of Service and Privacy Policy.

== Installation ==

1. Install and activate the plugin.
2. In your [Yamidoo dashboard](https://yamidoo.ai/), open your site and copy its **Site ID** from **Integrations → WordPress**.
3. In WordPress, go to **Settings → Yamidoo** and paste the Site ID.
4. Make sure **Show the Yamidoo chat widget** is enabled, then save. The widget appears on your site immediately.

== Frequently Asked Questions ==

= Do I need a Yamidoo account? =

Yes. Yamidoo is a hosted service; the plugin connects your site to it. Sign up at [yamidoo.ai](https://yamidoo.ai/).

= Where do I customize the widget's colors, text and behavior? =

In your Yamidoo dashboard. Because the widget reads its configuration from Yamidoo, your changes apply automatically — there is nothing to update in WordPress.

= Is there a paid or "Pro" version of this plugin? =

No. The plugin is free. Any plan limits or premium widget features are handled by your Yamidoo subscription on the service side.

= Does it work with caching and optimization plugins? =

Yes. The widget's script tags carry the opt-out attributes these plugins honour, and the plugin registers on the exclusion filters of WP Rocket, LiteSpeed Cache, Autoptimize, Perfmatters and SiteGround Optimizer, so the widget is not delayed, deferred or combined. If you use another optimizer with a manual exclusion list, exclude `widget.js` and `yamidoo`.

== Screenshots ==

1. The Yamidoo settings screen in the WordPress admin: paste your Site ID to connect, toggle the chat widget on the front end, and choose whether to identify logged-in users.

== Changelog ==

= 1.0.0 =
* Initial release
