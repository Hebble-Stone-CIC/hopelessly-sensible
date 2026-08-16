# Gotchas

Things about WordPress core that this plugin has to work around, and that are
not obvious from reading the code. Written as they were found, so that the
source can stay lean without the reasoning being lost.

Each entry names the core file and line where the behaviour lives. Line numbers
drift between releases; the surrounding code does not.

---

## Settings

### `register_setting()` breaks the usual "does this option exist?" test

`option.php:3074` hooks `filter_default_option`, which returns the **registered**
default whenever the fallback passed to `get_option()` is itself falsy. Both
`false` and `array()` are falsy. So this, the normal idiom, silently becomes
"always yes" once the setting is registered:

```php
if ( false !== get_option( HOPSEN_OPTION, false ) ) { ... }
```

Activation would skip detection on every site. We pass a truthy sentinel string
instead, which gets past the filter and gives a straight answer whether or not
the setting has been registered yet.

### The activation hook does not fire on plugin updates

`plugin.php:696`: *"If a plugin is silently activated (such as during an update),
this hook does not fire."* Schema migration therefore cannot live on activation.
It runs on `init` instead, guarded by a version comparison.

### The sanitise callback runs for `add_option()`, not only for form posts

`option.php` sends both `add_option()` and `update_option()` through
`sanitize_option()`, which is where `register_setting()` hangs the callback. So
any write to the option, from anywhere, is filtered as though it were a posted
form, and anything the callback carries across from storage will overwrite what
the write was actually trying to say.

This cost a good deal of care under schema 1, where activation wrote a block of
findings that the callback would then helpfully replace with the stored copy.
Schema 2 stores no findings, so the trap is smaller but not gone: the `live`
block is still carried across, and any future write that means to change it has
to go through a path that reads the row again rather than trusting a cached
copy. `Settings::write_live()` is that path.

### Three ways a settings form loses data, all of which look like success

1. The sanitise callback only receives what the form posted, so anything stored
   but not on the form must be carried across by hand or the first save wipes
   it.
2. Unchecked checkboxes post nothing at all. Build the result by walking the
   registry, not the posted array, so absence means false.
3. A disabled checkbox posts nothing either. Identical silence, opposite
   meaning. Its stored value must be preserved rather than read as false, or a
   save made while WooCommerce happens to be off resets the product reviews
   setting on its way past.

---

## Namespacing

### Functions fall back to the global namespace. Classes do not.

Inside `namespace HopelesslySensible\Features`, `add_filter()` and `__()` resolve
to the global versions automatically. `new WP_Error()` resolves to
`HopelesslySensible\Features\WP_Error` and fatals. `phpcs` cannot see this, which
is why `composer lint` has a second step that greps for it.

### Static methods stay removable from hooks

`plugin.php:1000` and `1015`: `_wp_filter_build_unique_id()` returns
`"Class::method"` for both `array( Class::class, 'method' )` and the string
`'Class::method'`. A third party can `remove_filter()` our hooks with either
form, exactly as they could a plain function. This is a stronger guarantee than
the common instance-plus-singleton pattern, where the remover must first get hold
of the object.

---

## REST

### `rest_pre_dispatch` covers three ways in, `rest_endpoints` covers one

`class-wp-rest-server.php:1079` runs the filter before route matching at 1096,
and again at **1840** inside the `/batch/v1` handler. Embeds go through
`dispatch()` too (line 820), so `?_embed` on a posts request fetches the author
through the same filter. Hooking `rest_endpoints` instead leaves the batch route
and embeds open.

---

## Authentication

### Core already has a generic failure code and string

`pluggable.php:713` uses `authentication_failed` with *"Invalid username, email
address or incorrect password."* Reusing both means the refusal is
indistinguishable from core's own and arrives translated everywhere WordPress is.

### Replacing the error does not break login limiters

`pluggable.php:717` ignores only `empty_username` and `empty_password` when
deciding whether to fire `wp_login_failed`. `authentication_failed` is not in
that list, so failed attempts are still counted.

### The lost-password form is excluded for free

`user.php:3264` uses `invalid_email`, but from `retrieve_password()`, which is not
on the `authenticate` filter. Hooking `authenticate` therefore cannot reach it,
with no special-casing needed.

---

## Author archives

### `?author=N` leaks the username in a redirect, before any 404 can happen

`canonical.php:319` looks the user up and 301s to `get_author_posts_url()`,
putting `user_nicename` in a `Location` header. A plugin that only makes
`/author/slug/` return Not Found still hands the name over here. `redirect_canonical`
also calls `redirect_guess_404_permalink()` on 404s (line 214), so marking the
query Not Found is not enough on its own. It has to come off the hook.

### `set_404()` deliberately keeps the feed flag

`class-wp-query.php:1834`: every query flag is reset except `is_feed`, which is
saved and restored. `template-loader.php:57` answers `is_feed()` before it ever
checks `is_404()`, and `$wp_query->posts` is still populated. So
`/author/slug/feed/` sends a 404 header and then serves the writer's entire feed
underneath it. Clear the flag by hand.

### The feed content type is already sent by the time you refuse the feed

`WP::send_headers()` runs on `send_headers`, well before `template_redirect`,
and sends `application/rss+xml` while the request is still a feed. Clearing the
flag afterwards leaves a Not Found page going out labelled as RSS. Nothing
breaks, since the status is what a reader acts on, but both places that refuse a
feed set the content type back by hand.

### `redirect_canonical` turns a refused feed into a redirect to nowhere

Removing it matters on comment feeds for a second reason beyond the guessed
permalink. Given a post's comment feed with the flag cleared but the query still
saying feed, it rebuilds the address and 301s to `/hello-world/feed/feed/`.
Observed on a live site: a Not Found delivered as a redirect to a URL that has
never existed.

### Dropping a sitemap provider

`class-wp-sitemaps-registry.php:51`: returning anything that is not a
`WP_Sitemaps_Provider` from `wp_sitemaps_add_provider` prevents registration, so
returning `false` removes the users sitemap and its index entry.

---

## Comments

### `comments_array` does not reach comment feeds

`comment-template.php:1589` applies it from `comments_template()`, which is the
theme displaying comments. Comment feeds are built by `WP_Query` through a
separate set of clauses starting at `class-wp-query.php:2815`, with their own
`comment_feed_join` and `comment_feed_where` filters. Hiding comments from the
page therefore leaves `/comments/feed/` serving all of them. Same shape as the
author feed problem: a page that looks closed on top of a feed that is open.

### A review is a comment, an order note is a comment

`comment_type` of `'review'` is a WooCommerce product review; `'order_note'` is
an order's audit trail. Anything that empties `comments_array` wholesale, or
filters by post rather than by comment type, destroys both. Every decision must
be made on the comment's own type.

### WooCommerce only names a review as one when the form posted it

`class-wc-comments.php:644`, `update_comment_type()`, is hooked to
`preprocess_comment` and begins `! is_admin() && isset( $_POST['comment_post_ID'] )`.
The type is therefore set from the request, not from the comment. Confirmed
against WooCommerce 11.0.1 on a live shop:

| How the review was created | Stored `comment_type` |
| --- | --- |
| The front-end review form | `review` |
| `wp_new_comment()` on a product, no `$_POST` | `comment` |
| REST, `wc/v3/products/reviews` | `review`, set explicitly at controller line 394 |
| Order note, `$order->add_order_note()` | `order_note` |

So the common paths are safe and the uncommon ones are not: an importer, a
migration from another platform, or a shop old enough to predate the type all
leave rows that are reviews in everything but the column. This settles the
question the old entry here left open. Reviews are hidden by type **and** by
placement for that reason: an ordinary comment sitting on a product is treated
as a review, which is also how the form and the count already behaved.

### `get_post_type( 0 )` does not mean "no post"

`post.php`: `get_post()` starts `if ( empty( $post ) && isset( $GLOBALS['post'] ) )`
and takes the global. Zero is empty, so asking about post zero quietly answers
about whatever is being rendered.

The site-wide comment feed has no queried object, so `get_queried_object_id()`
returns zero, and on a shop whose newest comment was a review the global post at
that moment was a product. `/comments/feed/` was therefore judged to be a
product's feed and served in full, every comment in it, while every page on the
site was hiding them. Found on a live shop; invisible from the pages themselves.

### Comment feeds have no array filter, only SQL clauses

`class-wp-query.php:2900`: the feed's comment ids go straight into
`$this->comments` through `array_map( 'get_comment', ... )`. There is no
equivalent of `the_comments` on that path, so a feed can only be filtered by
appending SQL to `comment_feed_where`. That is why the site-wide feed is refused
whole rather than filtered: this plugin has no raw SQL in it, and a mixed
document cannot be partly closed without some.

### `comments_array` is the theme's list, not the site's

`WP_Comment_Query::get_comments()` applies **`the_comments`** to every comment
query, and the display paths that matter go through it: core's Recent Comments
widget, the Latest Comments block, WooCommerce's reviews widget, any theme
calling `get_comments()`. None of them touch `comments_template()`, so none of
them see `comments_array`. A site that had just closed comments everywhere
carried on listing them in its sidebar, naming the people who left them.

Two things to know before filtering it. A query asking for ids or a single
column gets an array of scalars, with no type to read, so those are handed back
untouched. And `count => true` returns before the filter, which is what keeps
detection counting truthfully while everything else is hiding. That is not an
oversight to work around: it is the only reason the row can say "three comments
are approved here and hidden from your visitors" on a site where the same query
run any other way would come back empty.

### Ratings are drawn from the stored average, and ask nothing

`wc-template-functions.php:4344`, `wc_get_rating_html()`, checks only that the
average is above zero. WooCommerce's own templates ask `wc_review_ratings_enabled()`
before calling it, so filtering the two options is enough for those, but a theme
calling it directly gets stars regardless: Storefront's sticky add-to-cart bar
was still showing "Rated 4.50 out of 5" on a product whose reviews had just been
hidden. Every caller passes through the `woocommerce_product_get_rating_html`
filter, which is the one place to answer.

### WooCommerce already keeps its own comments out of the front end

`class-wc-comments.php:121` excludes `order_note` from `comments_clauses`, and
line 61 excludes product reviews from general comment queries. Useful to know
when a test says a widget shows nothing: on a shop it may be WooCommerce's doing
rather than this plugin's.

### WooCommerce version boundaries that matter here

- **6.7** (July 2022) moved review moderation to its own screen under Products.
  Before that it lived on `edit-comments.php`, which this plugin removes when
  comments are closed. On an older shop that leaves no way to approve a review.
  Documented in `readme.txt` rather than worked around.
- Reviews are stored as `comment_type = 'review'` on a current shop, but only
  when the front-end form created them, per the entry above. Whatever the
  version at which that type arrived, the rows it did not cover are still there
  on shops that have been running for years, which is why placement decides as
  well as type. Check any shop with:
  `SELECT comment_type, COUNT(*) FROM wp_comments GROUP BY comment_type;`
- Products have always been a custom post type. What moved out of the posts
  table was **orders**, via HPOS, default for new installs from 8.2. Order notes
  stayed in the comments table either way.

---

## XML-RPC

### `xmlrpc_enabled` does not disable the endpoint

Core's own docblock recommends it (`class-wp-xmlrpc-server.php:212`), but the flag
is read at line 221 **inside the server** and consulted at 299 only for methods
requiring a login. The server still starts, still parses the posted XML, and
still answers `pingback.ping`. Refusing the request is what closes the pingback
vector.

### "Is Jetpack installed" is the wrong question, and unanswerable by name

Jetpack stopped being one plugin years ago. It is a set of Composer packages,
and other products embed the parts they want, so a site can depend heavily on
the Jetpack connection with nothing named Jetpack anywhere on it.

Measured on a live shop, WooCommerce 11.0.1:

- **WooCommerce Payments** authenticates to WordPress.com over the Jetpack
  connection, and is called neither Jetpack nor VaultPress. Watched making 58 to
  108 calls to `jetpack.wordpress.com/jetpack.register/1/` in a single admin
  request. WooCommerce Shipping and Tax uses the same package.
- **WooCommerce itself** ships ten Jetpack packages in
  `woocommerce/vendor/automattic/`, `jetpack-connection` among them. So
  `class_exists( 'Automattic\Jetpack\Connection\Manager' )` is true on every
  WooCommerce site on earth, connected or not, and testing for it would spare
  XML-RPC on all of them.

Name matching fails one way, library detection fails the other. The signal that
tracks neither presence nor branding is core's own `xmlrpc_methods` filter:
having hooked it is what extending XML-RPC means.
`class-manager.php:143` registers it inside `if ( $manager->is_connected() )`,
so the connection announces itself exactly when blocking it would hurt. It also
gives the right answer for a site still carrying the options-table wreckage of a
shop removed years ago, because abandoned rows hook nothing.

### Jetpack has a second XML-RPC endpoint that is not xmlrpc.php

`class-manager.php:244`: a request carrying `?jetpack=comms` defines
`XMLRPC_REQUEST` itself, during `plugins_loaded`, and serves XML-RPC from
`template_redirect`. Our refusal runs at `plugins_loaded` priority 0, before
that constant exists, so this path is never seen. It softens the blast radius of
a wrong detection without removing it, since registration itself still goes
through `/xmlrpc.php`.

### Refusing early is worth roughly 7,400 lines

`xmlrpc.php` defines `XMLRPC_REQUEST` at line 13, before loading WordPress at line
29, and only loads `class-wp-xmlrpc-server.php` (7,227 lines),
`wp-admin/includes/admin.php` (plus 31 further requires) and `class-IXR.php` after
that. Refusing on `plugins_loaded` also skips the theme's `functions.php` and
every other plugin's `init` callbacks.

---

## Queries

### `has_published_posts` is not `count_users()`

`class-wp-user-query.php:358` turns it into a subquery on distinct `post_author`
over published posts, so the users table is only consulted for people who have
written something. On a shop, `count_users()` would count thousands of customers.
It also excludes posts orphaned by deleted accounts, which have no author archive
to hide anyway.

`class-wp-user-query.php:1089` salts the cache key with the **posts**
last-changed marker when `has_published_posts` is used, so publishing a post
correctly invalidates the count. Hand-rolled SQL would need that written and
maintained.

### `type => 'comment'` is core's own name for the rule this plugin follows

`class-wp-comment-query.php:794` maps it to `comment_type IN ( '', 'comment' )`,
which is exactly the pair this plugin claims and no more. Taking it from core
rather than writing the pair out
means reviews and order notes stay excluded even if core changes what an ordinary
comment is.

---

## The admin screen

### Core prints "Settings saved" for you, once

`admin-header.php:323`: any screen whose `$parent_file` is `options-general.php`
gets `options-head.php`, which calls `settings_errors()`. A submenu added with
`add_options_page()` qualifies, so calling `settings_errors()` on the page shows
the message twice. Verified both ways on a real save.

### An attribute selector is not an element selector

Core styles the checkbox as `input[type="checkbox"]`, which scores 0,1,1.
A class on its own scores 0,1,0 and loses, so the styled switch arrived as a
16 pixel square with core's dashicon tick in it. Repeating the attribute
alongside the class, `input[type="checkbox"].hopsen-toggle`, wins at 0,2,1
without `!important`, which would otherwise put the rules out of reach of
anyone theming their own dashboard.

### The Activity widget shows comments, and has no filter for them

`wp_dashboard_site_activity()` calls `wp_dashboard_recent_comments()`, which
builds its own query with no filter of its own: `dashboard_recent_posts_query_args`
exists at `dashboard.php:1004`, and there is no comment equivalent. So removing
the Comments menu, the legacy Recent Comments box, the admin bar node and the
front end still leaves the Activity panel listing recent comments with working
moderation links, on the first screen an administrator sees after logging in.

This was left alone at first, on the reasoning that closing comments to visitors
should not take moderation away from the person who closed them. That was wrong,
and in a way that only shows up on a site with more than one administrator: the
person who closed comments and the person who clicks Approve a year later are not
the same person, and the second one has no way of knowing the comment they just
approved is visible to nobody.

The lever is the query, not the widget. Dropping the `is_admin()` exemption from
the `the_comments` filter empties the panel of comments while leaving the panel
itself, so Recently Published and Publishing Soon survive. Reviews and order
notes pass through untouched because the decision is made on `comment_type`
first.

### Removing a menu page does not remove the page

`remove_menu_page( 'edit-comments.php' )` takes the item out of the sidebar and
nothing else. Both `edit-comments.php` and `comment.php` still load if you type
the address, and `comment.php` is the one that gets forgotten: it is the single
comment edit and reply screen, and every moderation link in the dashboard points
at it.

Redirecting them is safe except on WooCommerce below 6.7, which moderates product
reviews on the comments screen itself. See the WooCommerce section.

### Every new site ships with an approved comment

`wp_install_defaults()` in `wp-admin/includes/upgrade.php` writes a "Hello
world!" post and one comment on it, `comment_approved = 1`, dated the moment of
the install. So any question of the form "has anybody approved a comment here
lately?" answers yes on a site nobody has ever visited.

Match it on `comment_author_email`, fixed at `wapuu@wordpress.example`. The
author name next to it goes through `__()`, so "A WordPress Commenter" finds
nothing on a site in any other language. Both are overridable through the
arguments to `wp_install_defaults()`, which is how a multisite network changes
them, so treat the match as a strong heuristic rather than a fact.

Found on a fresh WordPress 6.5.5 install, where the settings screen read "1
comments have been approved here in the past year, so somebody is reading them"
on a site with no readers and no comments. Two bugs in one sentence, since it
also had no singular.

## Tooling

### The test suite loads the Local site's mu-plugins

`tests/wp-tests-config.php` borrows the Local site's WordPress codebase as
`ABSPATH`, and mu-plugins load unconditionally, so anything dropped into that
site's `wp-content/mu-plugins/` runs inside every test in the suite. Ordinary
plugins are safe, since the test bootstrap activates this plugin and nothing
else, but mu-plugins are not.

Cost an entirely misleading run: a one-line fixture hooking `xmlrpc_methods`, put
there to make the settings screen show a blocked row in the browser, turned seven
unrelated tests red across three files, including tests that had nothing to do
with XML-RPC. If a run fails in places the change could not possibly have
touched, look in that directory before reading any more code.

### `phpcs` reports worker batches, not files

With `parallel=8` in the ruleset, the progress line reads `5 / 5` for nine files.
Use `--parallel=1` or the JSON report to see the real list. Nothing is being
skipped.

### Plugin Check tolerates the `default` text domain

`I18n_Usage_Check.php:104`: *"Downgrade errors about usage of the 'default' text
domain from WordPress Core to warnings."* Reusing core's strings is an accepted
practice, not a violation.
