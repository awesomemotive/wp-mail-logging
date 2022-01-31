=== WP Mail Logging ===
Contributors: Wysija, MailPoet, No3x, tripflex
Tags: mail, email, log, logging, email log, debug, smtp, spam, deliverability
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 5.0
Tested up to: 5.9
Stable tag: 1.10.4

Log every single email sent by WordPress. Zero configuration. Entirely free.

== Description ==

WP Mail Logging is the most popular plugin to log emails sent by WordPress or WooCommerce. Simply activate it and it will work immediately, no configuration necessary.

### Why log emails sent from WordPress or WooCommerce?

Because it's useful to know which emails your WordPress sends for debugging purposes or out of curiosity.

By default, WordPress and your web host do not log, store or keep track of emails sent from your website.

### What email information is logged?

The default emails WordPress sends are logged:

* password reset emails
* new account email notification emails
* new comment email notification
* WordPress version updates notification

These emails will be logged like this:

* Complete list of sent mails, and searchable
* Contents of the email
* Headers of the email
* Error statuses
* IP address of originating server
* Feature: decide which emails you want to keep
* Developer: filters are provided to extend the columns

For most websites, this represents just a couple of emails per week. If your website is high traffic, is a membership website or is a WooCommerce store, the number of emails will increase and will be critical.

### Why are my logged emails still not delivered to the inbox?

When WordPress sends emails, there's no guarantee it will be delivered.

This is the lifecycle of a WordPress email:
1. WordPress or plugin creates an email
2. WordPress passes the email to the host and email gets logged by this plugin
3. The host takes the email and sends it (SMTP or Mail Transfer Agent)
4. Recipient receives or blocks the email
5. If the email is accepted, the spam filter decides which inbox it goes into
6. Recipients see the email and might open it.

This plugin does not track delivery after step 2.

### How do I know if my WordPress' emails are delivered to an inbox then?

You need to send an email from your WordPress to an inbox that has no spam filter:

1. Install the plugin <a href="https://wordpress.org/plugins/check-email/">Check Email</a>
2. In a new tab, go to <a href="https://www.mail-tester.com">mail-tester.com</a>. Copy the email address there.
3. In your WordPress admin, go to Tools > Check Email
4. Send an email to the address you copied from mail-tester
5. Return to the mail-tester, and click on "Check your score". Wait until mail-tester gets the email.

If mail-tester never receives your email, there's a problem with your host's sending method. Contact your host to let them know.

### What to do if the emails are not delivered to my personal inbox?

If mail-tester receives your emails (see point above), but your own address doesn't receive it:

* Check your inbox spam folder
* If the email is not there, your email provider is blocking the email before it even reaches the spambox. This is common.

We recommend in this case to send your WordPress email with a service provider, like MailPoet or Sendgrid. <a href="https://www.mailpoet.com/blog/top-three-smtp-plugins-for-wordpress/">See our guide on free plugins and options to send with an SMTP plugin.</a>

### Which emails does WooCommerce send?

* Cancelled order
* Failed order
* Order on-hold
* Processing order
* Completed order
* Refunded order
* Customer invoice / Order details
* Customer note
* Reset password
* New account

### Which popular plugins send emails?

* The Event Calendar
* Gravity Forms
* Ninja Forms
* Contact Form 7
* Wordfence
* ... and many more.

### Credits

The plugin was created and launched in 2014 by <a href="https://no3x.de/">Christian Zöller</a>.

== Frequently Asked Questions ==
= I need help! =
We answer in the forums, but only occasionally.
= Where can I report a bug? =
You can do so in the support forums. We'll be happy to review them.
= Can I submit changes to the plugin? =
Yes, directly on <a href="https://github.com/kgjerstad/wp-mail-logging/" rel="nofollow">GitHub</a>.

== Screenshots ==
1. The List
2. The Detail View
3. The Settings

== Changelog ==
= 1.10.4 - 2022-01-31 =
- Improved: reduced zip archive size.

= 1.10.3 - 2022-01-31 =
- Removed: Redux Framework template lib and banner loading. Thanks @kprovance!

= 1.10.2 - 2021-11-24 =
- Updated: Redux framework version to 4.3.4.
- Fixed: "disable_demo" PHP error. Thanks @Mike00mike!
- Fixed: changelog date typos. Thanks @Spreeuw!
- Removed: the Redux framework Gutenberg Library blocks. Thanks @Helenel!

= 1.10.1 - 2021-11-24 =
- Removed: Redux Framework connection notice. Thanks Jesse!
- Fixed: is_theme PHP error. Thanks @max3322!

= 1.10.0 - 2021-11-23 =
- Updated: Redux framework to 4.3.3.

= 1.9.9 - 2021-09-12 =
- Updated: support for WordPress 5.8.

= 1.9.8 - 2021-06-18 =
- Changed ownership!

= 1.9.7 - 2020-09-02 =
- Added: wpml_banner_display filter to hide MailPoet banner;
- Updated: support for WordPress 5.5.

= 1.9.6 - 2020-05-05 =
- Removed: contextual help tab.

= 1.9.5 - 2019-11-07 =
- Updated: plugin author.

= 1.9.4 - 2019-11-07 =
- Fixed: assets files.

= 1.9.3 - 2019-11-07 =
- Fixed: typo in readme.

= 1.9.2 - 2019-11-07 =
- Added: MailPoet banner;
- Updated: assets;
- Improved: menu position is now under wp-admin > tools.

= 1.9.1 - 2019-08-20 =
- MailPoet has claimed ownership. We're grateful to Christian for all the work committed to this project over the years.

= 1.9.1, 2019-04-18 =
- Fix: log-view resources loaded on each page (performance issue)
- Fix: attachment icon is not displayed (e.g. if mime-type is unsupported)

= 1.0, 2014 =
- Hello matrix
