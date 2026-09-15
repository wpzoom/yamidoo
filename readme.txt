=== Yamidoo – AI Support Chat ===
Contributors: yamidoo, wpzoom
Tags: chat, live chat, support, ai chatbot, woocommerce
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your website to Yamidoo in one click and add an AI support chat widget that answers visitors from your own content and hands off to a human when needed.

== Description ==

Yamidoo is an AI-first customer support chat. This plugin connects your WordPress site to your Yamidoo account and adds the chat widget to your site in seconds — no code required.

The widget answers your visitors instantly from your content and can hand off to a human on your team when needed. All appearance and behavior (colors, welcome message, launcher icon, suggested questions, lead capture, human handoff, and more) are configured in your **Yamidoo dashboard**, so changes go live automatically without updating anything in WordPress.

**What this plugin does**

* **One-click connect.** Click *Connect to Yamidoo*, sign in or create a free account, and you are sent back with the site linked and indexing started. Prefer to do it by hand? Paste a Site ID instead.
* Adds the Yamidoo chat widget to the front end of your site.
* Optionally identifies logged-in WordPress users to Yamidoo (name, email, username, user ID) so your support team knows who they are talking to.
* **Customer data for Easy Digital Downloads and WooCommerce.** Your team sees a customer's orders, licenses and subscriptions next to their conversation in your own Yamidoo inbox. If you choose to, your AI assistant can also answer logged-in customers' questions about their own account ("when does my license expire?") — that is a separate switch in the dashboard, off by default. Nothing is uploaded or shared with anyone else: your workspace fetches one customer's record from your site when it is needed, over a request signed with your secret, and does not store it. Developers can add data from any plugin with the `yamidoo_customer_sections` filter.
* Plays nicely with caching and optimization plugins — WP Rocket, LiteSpeed Cache, Autoptimize, Perfmatters, SiteGround Optimizer and Cloudflare Rocket Loader are all told to leave the widget alone.

**Requires a Yamidoo account.** Create one and get your Site ID at [yamidoo.ai](https://yamidoo.ai/).

== External services ==

This plugin connects to Yamidoo, a hosted third-party service operated by Yamidoo, to provide the AI support chat. It is required for the plugin to function.

* **What it is:** Yamidoo loads the chat widget script (`widget.js`) from the Yamidoo app at `https://app.yamidoo.ai` and communicates with the Yamidoo API on the same host to fetch your widget's configuration and to power live conversations.
* **What data is sent, and when:**
  * When a visitor interacts with the widget: their chat messages, an anonymous session identifier, the page URL/referrer and basic browser/device metadata, and any files a visitor chooses to upload in the chat.
  * If "identify logged-in users" is enabled and a user is logged in: that user's WordPress display name, email address, username and user ID.
  * If customer lookup is enabled: when your team opens a conversation, or the AI answers a logged-in customer, your Yamidoo workspace requests that one email's record from this site (`/wp-json/yamidoo/v1/customer`) — Easy Digital Downloads customer, licenses, orders and subscriptions, or WooCommerce orders and subscriptions. Every request is signed with the secret you paste into the settings; the record is shown to your team and used for that answer, then discarded.
  * When loading the widget: your Site ID (a public identifier used to select your widget configuration).
  * When you click *Connect to Yamidoo*: your site's address, the address of the settings screen to return to, and your WordPress email and name to prefill the sign-in form. Nothing is sent until you click the button.
* **Service links:** [Website](https://yamidoo.ai/) · [Terms of Service](https://yamidoo.ai/terms) · [Privacy Policy](https://yamidoo.ai/privacy)

By installing this plugin and adding your Site ID, you agree to Yamidoo's Terms of Service and Privacy Policy.

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings → Yamidoo** and click **Connect to Yamidoo**. Sign in or create a free account and confirm — you are sent straight back, and the widget appears on your site. On Easy Digital Downloads and WooCommerce stores, customer data is switched on for you too.

Prefer to do it by hand?

1. In your [Yamidoo dashboard](https://yamidoo.ai/), open your site and copy its **Site ID** from **Integrations → WordPress**.
2. In **Settings → Yamidoo**, paste the Site ID and save.
3. Optional, for Easy Digital Downloads or WooCommerce stores: in the dashboard open **Integrations → Customer data**, paste the Lookup URL shown in **Settings → Yamidoo** (`https://your-site.com/wp-json/yamidoo/v1/customer`), click **Generate secret**, paste the secret into WordPress and tick **Show customer data in my Yamidoo inbox**.

== Frequently Asked Questions ==

= Do I need a Yamidoo account? =

Yes. Yamidoo is a hosted service; the plugin connects your site to it. Sign up at [yamidoo.ai](https://yamidoo.ai/).

= Where do I customize the widget's colors, text and behavior? =

In your Yamidoo dashboard. Because the widget reads its configuration from Yamidoo, your changes apply automatically — there is nothing to update in WordPress.

= Is there a paid or "Pro" version of this plugin? =

No. The plugin is free. Any plan limits or premium widget features are handled by your Yamidoo subscription on the service side.

= Does it work with caching and optimization plugins? =

Yes. The widget's script tags carry the opt-out attributes these plugins honour, and the plugin registers on the exclusion filters of WP Rocket, LiteSpeed Cache, Autoptimize, Perfmatters and SiteGround Optimizer, so the widget is not delayed, deferred or combined. If you use another optimizer with a manual exclusion list, exclude `widget.js` and `yamidoo`.

= What customer data is shared, and with whom? =

Only what you enable, and only with your own Yamidoo workspace — not with Yamidoo the company or anyone else. With customer lookup on, your workspace can ask this site for one email address's record at a time: the customer name and totals, and their orders, licenses and subscriptions from Easy Digital Downloads or WooCommerce (the most recent 8 of each). Your support team sees it in the inbox next to that person's conversation. The AI does not see it unless you turn on **Let the AI answer account questions** in the dashboard, and even then only when the person chatting is logged in to your WordPress site — the plugin signs their identity, so a visitor cannot type someone else's email and ask about their orders. Nothing is copied into Yamidoo; each request is signed with your secret and answered live from your site.

= My theme prints the widget itself. Can I still use customer data? =

Yes. Leave **Show the Yamidoo chat widget** off so the plugin does not add a second copy, and keep customer lookup on: the endpoint works regardless. For the AI to answer account questions, add a `signature` to your own `identify()` call using `yamidoo_identity_signature( $user->user_email )`, computed in PHP.

= Can I add data from other plugins? =

Yes. Hook the `yamidoo_customer_sections` filter and return extra sections (a title and a list of items with a value, optional label, badge, tone, meta and url). Memberships, Freemius, a CRM — anything you can look up by email.

== Screenshots ==

1. The Yamidoo settings screen in the WordPress admin: paste your Site ID to connect, toggle the chat widget on the front end, and choose whether to identify logged-in users.

== Changelog ==

= 1.1.1 =
* NEW: One-click connect — sign in or create an account from Settings → Yamidoo and everything is filled in for you

= 1.1.0 =
* NEW: Customer data for Easy Digital Downloads and WooCommerce — orders, licenses and subscriptions shown next to the conversation, and account questions answered for logged-in customers
* NEW: `yamidoo_customer_sections` filter for other plugins
* Logged-in identities are signed so the AI only shares account data with the real account holder
* The connect token and lookup secret are encrypted in the database, keyed to your site's auth salt
* Suggested privacy policy text under Settings → Privacy → Policy Guide

= 1.0.0 =
* Initial release
