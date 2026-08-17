# Hopelessly Sensible

**Security hardening for people who have better things to do.**

A minimal WordPress security plugin. Free, GPL, no pro tier, no dashboard bloat, no upsell. You're welcome.

It has seven switches, each with a plain-English explanation of what it does and a clear note about what 
might go wrong so you can make the decision yourself, without needing to be a security expert.

---

## Why install?

Most security plugins give you thousands of options, many of which are of questionable value. 
Most website security problems, though, aren't sophisticated attacks by criminals trying to steal your secrets. They're 
bots and spambots trying the same doors over and over until they find one which has been left unlocked. 
The bots are looking for the most common, easiest ways in. That might be posting spam comments, guessing common usernames on your login screen (which, by default, WordPress helpfully tells them if they're right or wrong), or other hidden doors that WordPress leaves open by default. 
This plugin doesn't try to do everything. It just helps lock some of those most 'at-risk' doors, making your site too much hassle for bots to bother with.


### It sets up what is safe, and leaves the rest to you

On activation it looks at your site and switches on the features it is safe to. 

One
writer? It hides author pages.

Nobody has approved a comment in a year? It closes comments. 

Three of the seven features are never switched on automatically, because they take something away from you that you might 
not want to give up. That's your call and the screen helps you make the choice that's right for your site.

### When it stands down, it tells you

If something on your site changes that leaves a setting unable to work, or that might break your
site, the switch goes off by itself and every administrator is told once,
visibly, what changed and why. 

Install a plugin that needs remote publishing?
"Block remote publishing" switches itself off. 


### It never hides your options

A feature that cannot be switched on here is still shown, just disabled, and it will say plainly what is stopping it. 
Maybe WooCommerce is not active, so there are no product reviews to close. Your `wp-config.php` already locks the
file editor, so that switch has nothing left to do. We keep ourselves very, very honest about what we do.


### It leaves nothing behind

The plugin writes to exactly one row in your database options table and nowhere else.
Every feature is a filter or an action evaluated at request time. Deactivate it and your site is exactly as it was before. 
Delete it and the single option goes with it.

---

## The seven features

| Feature                        | What it does                                                                                           |
|--------------------------------|--------------------------------------------------------------------------------------------------------|
| **Keep the user list private** | Stops the REST API handing your usernames to anyone who asks.                                          |
| **Keep login errors vague**    | The same message whether the username or the password was wrong, so guessing cannot confirm a real account. |
| **Hide author pages**          | Author archives answer Not Found, and writers are kept out of your sitemap and out of link previews.   |
| **Close comments everywhere**  | Closes and hides comments across the site, and takes the comment screens out of your dashboard.        |
| **Block remote publishing**    | Refuses XML-RPC outright, which also closes the incoming pingback route. [off by default]              |
| **Close product reviews**      | Closes the WooCommerce review form and hides existing reviews and their star ratings. [off by default] |
| **Lock the file editor**       | Takes the theme and plugin file editors out of the dashboard. [off by default]                         |

> The first four are set up for you. The last three are your call.

### Other nice stuff
- No JavaScript, anywhere. Not in the admin screen, not on the front end.
- No runtime dependencies and no autoloader.
- WooCommerce handled properly [particularly the comments/star-ratings]

---

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later


## Installing

From WordPress: search for "Hopelessly Sensible" under Plugins, Add New.

From a release: download the zip and upload it under Plugins, Add New, Upload
Plugin.

From this repository: clone it into `wp-content/plugins/` and activate. The
repository is the plugin, so nothing needs building to run it.

Settings live under **Admin > Settings > Hopelessly Sensible**.

---

## Working on it

Composer is used for development tooling only. Nothing in `vendor/` is ever
shipped, and the plugin has no runtime dependencies at all.

```bash
composer install
```

### The test suite

The tests run on the real WordPress core test suite through `wp-phpunit`, so
they need two things: a WordPress codebase to load, and a MySQL server to
install a test database on.

```bash
cp tests/wp-tests-config-local.php.example tests/wp-tests-config-local.php
# edit that copy: wp_root, and either db_socket or db_host
composer test
```


> **The test database is emptied before every run.** That is how the core suite
> works. Give it a name no real site would ever have. `tests/bootstrap.php`
> refuses to start if the name looks like a real site, but do not rely on that
> to save you.

Heads up. Running the suite through the Playground CLI, which would need neither a
database nor a WordPress installation, does not work.

### Coding standards

Full WordPress Coding Standards, enforced and non-negotiable.

```bash
composer lint     # standards, plus a namespace check phpcs cannot see
composer test     # the core test suite
composer check    # both, and what "done" means here
composer build    # refuses a dirty tree, runs check, then zips from HEAD
```

House rules on top of WPCS, all of which have a reason:

- **Long array syntax**, `array( ... )`, never `[ ... ]`.
- **Named functions for every hook callback. Never closures**, which cannot be
  removed from a hook later.
- Tabs, Yoda conditions, everything escaped, everything sanitised.

### How it is put together

```
hopelessly-sensible.php     Bootstrap only: header, constants, requires, activation.
uninstall.php               Deletes one option, per site on a network.
inc/class-registry.php      Source of truth. Every feature defined exactly once.
inc/class-settings.php      Option shape, accessor, sanitise, registration.
inc/class-detection.php     The questions asked about a site, and never stored.
inc/class-plugin.php        Activation, and booting features each request.
inc/admin/                  The settings screen. Entirely replaceable.
inc/features/               One file per feature.
assets/admin.css            The only asset.
refs/gotchas.md             Core behaviour found the hard way. Start here.
refs/interface-copy.md      All user-facing text. The verbatim source.
tests/                      One case file per feature, plus settings and detection.
```

`inc/class-registry.php` defines every feature exactly once, and everything else
derives from it: the defaults, the sanitise loop, the settings screen, the REST
schema. **Adding a feature means one registry entry and one file in
`inc/features/`.** If a change requires editing the settings screen, the registry
is wrong.

Feature code must not know the admin screen exists. No file in `inc/features/`
may reference the settings page, its markup or its assets. The rendering layer is
the only replaceable part of this plugin, and that is deliberate.

### Two documents worth reading before you change anything

**`refs/gotchas.md`** holds forty-odd things about WordPress core that this
plugin has to work around and that are not obvious from reading the code. Why
filtering `rest_pre_dispatch` covers three ways in where `rest_endpoints` covers
one. Why `set_404()` deliberately keeps the feed flag. Why every new WordPress
site ships with an approved comment that must not be counted. Each entry names
the core file where the behaviour lives.

**`refs/interface-copy.md`** is the fixed source for every word a user sees. The
copy is the product here, so it must be used verbatim and must not be
paraphrased or adjusted to fit the markup. If something in it is wrong, raise it
rather than rewriting it.

---

## Contributing

Issues and pull requests are welcome at
[Hebble-Stone-CIC/hopelessly-sensible](https://github.com/Hebble-Stone-CIC/hopelessly-sensible).

Before opening a pull request:

1. `composer check` must pass clean. Lint failures are not warnings here.
2. New behaviour needs a test. Coverage is not the target; the target is that
   anything load-bearing has a test that would fail if it broke.
3. User-facing text comes from `refs/interface-copy.md`, verbatim.
4. Anything learned about core the hard way goes in `refs/gotchas.md`.

Some things are deliberately out of scope, and were considered and declined
rather than overlooked:

- Proof-of-work or challenge-response on the login form. Fail open and it stops
  nothing; fail closed and it can lock people out of their own website.
- Hiding version information. The generator tag is one fingerprint among many
  while asset query strings and `readme.html` remain, so a toggle for it implies
  protection it does not provide.
- Disabling pingbacks as a separate option. Already covered by blocking XML-RPC.
- Flagging stale users or weak passwords, prompting for two-factor, or anything
  else that hands the site owner a task list.
- Any scanning, firewall, alerting, or score.

That last group is the point of the plugin rather than a gap in it. It must not
give people homework. A non-technical user should be able to activate this, read one
screen and never have to think about it again.

---

## Licence

GPL v2 or later. See [LICENSE](LICENSE).

Copyright (C) 2026 Hebble & Stone.
