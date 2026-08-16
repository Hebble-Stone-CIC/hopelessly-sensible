=== Hopelessly Sensible: Simple Security Hardening ===
Contributors: mattbedford, hebblestone
Tags: security, hardening, xmlrpc, comments, privacy
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security hardening for people who have better things to do. Seven options, plain English, and an honest warning on every one.

== Description ==

Most security plugins are built for people who enjoy security. This one is built for everyone else: the person who looks after a small charity's website on a Tuesday evening and would like to stop worrying about it.

It does seven things. Each one has a switch, a plain-English explanation of what it does, and an honest note about what it might break. No notification badges, no upgrade prompts, no counting of attacks repelled, and nothing anywhere in your dashboard trying to sell you something.

**It describes your site as it is today.**

Everything on the settings screen is about your site now, not about what it looked like the day you installed this. If four people publish posts here, it says four. If three comments are approved and hidden from your visitors, it says so, and tells you how to get rid of them for good if that is what you want.

**It sets up what is safe, and leaves the rest to you.**

When you activate it, the plugin looks at your site and switches on what is safe here. If you have one writer, it hides author pages. If nobody has approved a comment in a year, it closes comments. Three of the seven are never switched on for you, because they take something away from you rather than from a visitor, and that decision is yours to make.

**If something changes, it stands down and tells you.**

If a setting stops being safe to leave on, this plugin switches it off by itself. Install something that needs remote publishing and blocking remote publishing goes off, rather than sitting there reading as on while quietly breaking your new plugin. When that happens you get one notice, once, saying what changed and why, and you can dismiss it for good. It is the only thing this plugin will ever show you outside its own settings screen, and it only ever appears because something has already happened.

A switch that is off is never turned on behind your back. That direction is always yours.

**What it does**

* Keeps your list of users and their usernames away from anonymous visitors
* Gives the same short message whether a login failed on the username or the password
* Hides author pages, and keeps writers out of your sitemap and link previews
* Blocks XML-RPC, an old remote publishing interface popular with password-guessing tools
* Closes comments everywhere, and leaves WooCommerce reviews alone unless you say otherwise
* Closes WooCommerce product reviews, if you want that
* Locks the theme and plugin file editors in the dashboard
* Warns you if you have a user called "admin", and does nothing else about it

**What it does not do**

* It does not write to your .htaccess file, your wp-config.php, or anything outside its own single settings row. Deactivate it and your site is exactly as it was, immediately.
* It adds no JavaScript to your dashboard.
* It does not scan, does not phone home, does not collect anything, and has no paid version.
* It does not give you homework. There is no checklist, no score, and no red badge waiting for you.
* It does not hide options from you. Anything this plugin cannot do on your site is still on the screen, switched off, saying what is stopping it.

Free and open source, GPL, written by Hebble & Stone, a community interest company that builds websites for charities.

== Frequently Asked Questions ==

= Will this break my site? =

Any of these settings can change how something works, which is why each one carries a warning saying what. The two that most often surprise people are closing comments, which hides discussion your visitors may be part of and takes the Comments screen out of your dashboard while it is on, and locking the file editor, which stops you editing theme files from the dashboard. Both are explained on the settings screen before you touch them. Nothing is deleted either way: switch comments back on and everything is where you left it.

= What happens if I deactivate it? =

Everything goes back to how it was, straight away. The plugin makes no permanent changes to your site, so there is nothing to undo. Uninstalling removes its single row from your options table.

= Does it work with WooCommerce? =

Yes, and carefully. WooCommerce reviews are stored as comments, so closing comments would ordinarily take your product reviews and star ratings with them. This plugin never does that. Reviews have their own separate option, off by default, which sits on the screen switched off and explained if WooCommerce is not installed. Order notes, which are also stored as comments, are never touched by anything here.

One thing to know if your shop is running an old WooCommerce. Closing comments takes the Comments screen out of your dashboard, and WooCommerce moved review moderation to its own screen under Products in version 6.7. On WooCommerce 6.7 or later this is fine, and you carry on approving reviews as normal. On anything older, reviews are still moderated on the Comments screen, so this plugin leaves that screen reachable on those shops rather than taking review moderation away from you.

= Does it work on multisite? =

Yes, but activate it on each site rather than across the whole network. Activating for the network means it cannot look at your sites one by one, so every option starts where it starts rather than where the plugin would have put it, and it says so on the settings screen. Activated site by site, it looks at each one properly. Either way the settings are per site, so what you choose on one site does not affect another.

= Will it stop someone hacking my site? =

No plugin can promise that, and you should be wary of any that implies it. This one closes off a set of well-known ways that automated tools gather information and guess passwords. That is worth doing, and it is not the same thing as being safe. Strong passwords, two-factor authentication, and keeping WordPress and its plugins updated matter more than anything here.

= Why is there no scanning, firewall, or login limiting? =

Because those need attention, and this plugin is built to be set once and forgotten. Features that generate alerts generate work, and work gets ignored, and ignored alerts are worse than no alerts.

= I have a user called "admin" and it is warning me. What do I do? =

WordPress does not let you rename a user. The usual route is to create a second administrator account with a different username, log in as that one, delete the "admin" account, and hand its posts over to the new account when WordPress asks.

== Screenshots ==

1. The settings screen. Every option carries a plain explanation and a note on what it might break.
2. An option this site cannot use, still on the screen, saying what is stopping it.

== Changelog ==

= 1.0.0 =
* First release.
