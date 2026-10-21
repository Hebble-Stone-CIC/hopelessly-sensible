=== Hopelessly Sensible: Simple Security Hardening ===
Contributors: mattbedford, hebblestone
Tags: security, hardening, xmlrpc, comments, privacy
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security hardening for people who have better things to do. Seven options, plain English and clear information about what changes.

== Description ==

Most WordPress security plugins are heavy and slow. This one is simple, light and reliable.

It does seven things. Each one has a switch, a plain-English explanation of what it does and an honest note about what it might break. No notification badges, no upgrade banners and nothing in your dashboard trying to sell you something.

**It sets up what is safe and leaves you in charge**

When you activate it, Hopelessly Sensible looks at your site and switches on everything that is safe here. Protections that take a feature away from you are left off, so that call stays yours.

**If something changes, it stands down and tells you**

If a setting stops being safe to leave on, this plugin switches it off by itself and gives you one clear, dismissible notice saying what changed and why. It is the only thing this plugin ever shows you outside its own settings screen. A switch that is off is never turned on behind your back.

**What it does**

* Keeps your list of users and their usernames away from anonymous visitors
* Gives the same short message whether a login failed due to the username or the password
* Hides author pages and keeps writers out of your sitemap and link previews
* Blocks XML-RPC, an old remote publishing interface popular with password-guessing tools
* Optionally: closes comments everywhere
* Optionally: closes WooCommerce product reviews
* Optionally: locks the theme and plugin file editors in the dashboard
* Warns you if you have a user called "admin", and explains how to fix it

**What it does not do**

* It does not write to your .htaccess file, your wp-config.php or anything outside its own single settings row. Deactivate it and your site is exactly as it was, immediately.
* It adds no JavaScript to your dashboard.
* It does not scan, does not phone home, does not collect any data and has no paid version.
* It does not give you homework. There is no checklist, no score and no red badge waiting for you.
* It does not hide options from you. Anything this plugin cannot do on your site is still on the screen, switched off, saying what is stopping it.

Free and open source, GPL, written by Hebble & Stone, a community interest company that builds websites for charities, organisations and small businesses.

== Frequently Asked Questions ==

= Will this break my site? =

Very unlikely, but each setting changes how something works and carries a warning saying what. If something surprises you, switch it back off and your site is as it was.

= What happens if I deactivate it? =

Everything goes back to how it was, straight away. The plugin makes no permanent changes to your site, so there is nothing to undo. Uninstalling removes its single row from your options table.

= Does it work with WooCommerce? =

Yes, and carefully. WooCommerce reviews are stored as comments, so closing comments would ordinarily take your product reviews and star ratings with them. This plugin never does that. Reviews have their own option, off by default, and it stays on the screen with an explanation even when WooCommerce is not installed. Order notes, which are also stored as comments, are never touched by anything here.

One thing to know if your shop is running an old WooCommerce. Closing comments takes the Comments screen out of your dashboard, and WooCommerce moved review moderation to its own screen under Products in version 6.7. On WooCommerce 6.7 or later this is fine, and you carry on approving reviews as normal. On anything older, reviews are still moderated on the Comments screen, so this plugin leaves that screen reachable on those shops rather than taking review moderation away from you.

= Does it work on multisite? =

Yes, but activate it on each site rather than across the whole network. Activating for the network means it cannot look at your sites one by one, so every option is off by default and it says so on the settings screen. Activated site by site, it looks at each one properly. Either way the settings are per site, so what you choose on one site does not affect another.

= Will it stop someone hacking my site? =

No plugin can promise that and you should be wary of any that implies it. This one closes off a set of well-known ways that automated tools and bots gather information and guess passwords. That is worth doing, though it is not the same thing as being safe. Strong passwords, two-factor authentication and keeping WordPress and its plugins updated matter more than anything.

= Why is there no scanning, firewall or login limiting? =

Because those need attention and this plugin is set and forget. Features that generate alerts generate work, work gets ignored and ignored alerts are worse than no alerts.

= I am on Bitnami or AWS Lightsail. Why does it mention my wp-config.php? =

Every Bitnami WordPress image, which is what Lightsail runs, ends wp-config.php with a short snippet that switches off pingbacks. It is a sensible default, but it is a small part of what "Block remote publishing" does: it removes one method from remote publishing and leaves the rest answering, including the login methods that password-guessing tools use. This plugin recognises a snippet in wp-config.php as yours rather than as a plugin's, says so under the switch, and still lets you switch it on. Once it is on, the whole of remote publishing is refused, pingbacks included, and the snippet has nothing left to do.

= I have a user called "admin" and it is warning me. What do I do? =

WordPress does not let you rename a user. The usual route is to create a second administrator account with a different username, log in as that one, delete the "admin" account and hand its posts over to the new account when WordPress asks.

== Screenshots ==

1. The settings screen. Every option carries a plain explanation and a note on what it might break.
2. An option this site cannot use, still on the screen, saying what is stopping it.

== Changelog ==

= 1.2.0 =
* Changed: "Hide author pages" is now switched on when the plugin is first activated, unless your theme ships a page design of its own for author pages. It used to be switched on only for sites with a single writer, which was the wrong question: every account has an author address whether or not it has written anything. Sites already running the plugin are left exactly as they are.
* Changed: the note under "Hide author pages" now says what those pages give away, rather than counting writers.
* Changed: a snippet in your wp-config.php that hooks into remote publishing, which every Bitnami and Lightsail install carries, no longer stops "Block remote publishing" being switched on. The note under the switch names the line and explains the difference.
* Changed: when a snippet elsewhere on your site is using remote publishing, the note under the switch now names the file and line, so you know where to look.

= 1.1.1 =
* Changed: three messages borrowed from WordPress itself (the vague login error, the remote publishing refusal and the user list refusal) are now translatable as part of this plugin, as the plugin directory requires, rather than inheriting the translations WordPress ships with.

= 1.1.0 =
* Fixed: on WordPress 7.1, "Block remote publishing" could not be switched on at all, and switched itself off on sites that had it on. WordPress 7.1 began using the same hook this plugin watches to work out whether anything on your site needs remote publishing, and the plugin read that as your site needing it.
* Added: "Lock the file editor" is now blocked, with an explanation, on sites where a published GeneratePress element runs PHP. GeneratePress stops running that code while the editor is locked, and prints the code into the page instead.
* Changed: the warning under "Lock the file editor" now mentions code snippets plugins, some of which stop running their code when the editor is locked.
* Tested against WordPress 7.1.

= 1.0.0 =
* First release.
