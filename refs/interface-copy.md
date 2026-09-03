# Hopelessly Sensible: interface copy

Final text for the registry. The build must use this verbatim and must not
paraphrase it. If something here is wrong, fix it here first.

**Rules this copy follows.** Labels are active imperatives, never passive, and
never contain the word "off". No fear-selling: no "hackers", no counts of
attacks blocked. Technical terms appear once, inside the description, so people
who were told to disable something can still find the row. Warnings name a
person doing a thing rather than an abstraction.

**Everything here is present tense.** It describes the site as it is on the
request that renders it, never as it was when the plugin was activated. Numbers
in `{braces}` are filled at render time from a query run on that request.

**A state line describes the site, not the switch.** The toggle already says
whether it is on, so repeating that underneath is noise. A state line appears
only where the site itself has something worth knowing: a reason this might be
the wrong setting for you, something being hidden that you cannot otherwise
see, or a blocker stopping the switch from being turned on at all. A switch that
is on and has nothing to report carries no state line.

**No row is ever hidden.** A switch that cannot be turned on renders in place,
off and disabled, with a line saying what is stopping it. A hidden row would
make this screen a partial account of the plugin, and a screenshot of it a lie.

**A blocked row shows no warning.** "What might this break?" is a question about
a decision, and there is no decision to make under a switch that cannot be
moved. The blocker line is the whole of what it says.

---

## Set up for you

### Keep the user list private

WordPress will hand a list of everyone who writes on your site, usernames
included, to anyone who asks for it. Switching this on limits that list to
people logged in with permission to manage users.

**What might this break?** (collapsed)

> Likely nothing at all. If you have a "meet the team" page that builds itself
> from your user list rather than from a page you wrote, worth a quick check
> that it still looks right.

**State line:** none.

---

### Keep login errors vague

When someone gets a login wrong, WordPress tells them whether the username
exists or the password was wrong. That quietly confirms real usernames to
anyone guessing at them. Switching this on gives the same short message either
way.

**What might this break?** (collapsed)

> If you mistype your own username, the login screen will no longer tell you
> which part you got wrong, and the "Lost your password?" link that usually
> appears in that message will be gone. The link underneath the login form
> still works as normal.

**State line:** none.

---

### Hide author pages

Everyone who writes on your site gets their own page listing their posts, at an
address that also gives away their username. Switching this on makes those
pages show Not Found, and keeps writers out of your sitemap and out of the
previews other sites generate when they link to you.

**What might this break?** (collapsed)

> If your site has several writers and you link to "posts by Jane" anywhere,
> those links will stop working. Look at a post: if the author's name underneath
> the title is a link, switching this on will break it.

**State line, off, one writer:** One person publishes posts here, so these pages
have nothing your visitors need.

**State line, off, several writers:** {authors} people publish posts here, and
sites with several writers often link to author pages from their bylines.

**State line, off, nothing published:** Nothing is published here yet, so there
are no author pages to hide. Worth switching on before you publish.

---

### Close comments everywhere

Closes comments on every post and page, hides any that are already there, and
takes the comment screens out of your dashboard. Comment forms attract a great
deal of automated spam, most of it posted to push links to other sites.

**What might this break?** (open by default)

> Your existing comments are hidden from visitors, not deleted, so switching
> this back on brings them straight back. While it is on you will not be able to
> read or moderate them either, because the Comments screen goes as well. And if
> people are still talking on your site, this ends that conversation without
> telling them why. WooCommerce product reviews are left alone: they have their
> own option below.

**State line, on, one comment hidden:** One comment is approved here and hidden
from your visitors. If you want it gone for good, switch this off, delete it
under Comments, then switch it back on.

**State line, on, comments hidden:** {comments} comments are approved here and
hidden from your visitors. If you want them gone for good, switch this off,
delete them under Comments, then switch it back on.

**State line, on, only the sample comment:** The only comment here is the sample
one WordPress adds when a site is installed, so nothing of yours is hidden.

**State line, off, one comment in use:** One comment has been approved here in
the past year, so somebody is reading it.

**State line, off, comments in use:** {comments} comments have been approved
here in the past year, so somebody is reading them.

**State line, off, no comments:** No comments have been approved here in the
past year, so switching this on would hide nothing of yours.

Every count on this row has a singular of its own. "1 comments have been
approved" was on the screen of a real site before anybody noticed, because the
number that reaches it most often is one: the comment WordPress installs with.

---

## Worth a think first

Shown under the heading, as the only copy on this screen that belongs to a
section rather than to a row. Every warning in this section starts open.

> Nothing in this section is ever switched on for you. These three take
> something away from you rather than from a visitor, so the decision is yours.

### Block remote publishing

XML-RPC is an old way for apps to post to your site remotely. Most sites no
longer use it, and it is a favourite of automated password-guessing tools
because one request can try many passwords at once.

**What might this break?** (open by default)

> If anyone on your team writes posts from their phone using the WordPress app,
> switching this on will stop them. Jetpack needs remote publishing too, as do
> several plugins that connect a shop to WordPress.com. We can see anything on
> your site that asks WordPress for remote publishing, but there is no way for
> us to see the mobile app, so ask around before you switch this on.

**State line, off, nothing using it:** Nothing on your site is asking for
remote publishing at the moment.

**State line, blocked:** Something on your site is using remote publishing right
now, so this cannot be switched on. If you know what it is and no longer need
it, turn it off there first and this switch will free up.

---

### Close product reviews

WooCommerce reviews are comments wearing a different hat, so closing comments
does not touch them. Switching this on closes the review form and hides the
reviews you already have.

**What might this break?** (open by default)

> This hides existing reviews and the star ratings that come with them, not just
> the form for writing new ones. On a shop, those ratings are often the thing
> that persuades someone to buy. Switching it back on brings them back.

**State line, off:** none.

**State line, blocked:** WooCommerce is not active on this site, so there are no
product reviews to close.

---

### Lock the file editor

WordPress lets administrators edit theme and plugin files straight from the
dashboard. It is a quick way to take a site down by accident, and if someone
gets into an administrator account it is a quick way for them to run code of
their own.

**What might this break?** (open by default)

> This takes the theme and plugin file editors out of the dashboard for
> everyone, including you. If you or whoever looks after your site makes small
> fixes that way, they will need to edit files over SFTP or through your hosting
> control panel instead. Switching this back on brings the editors back on the
> next page load. Some code snippets plugins stop running their code when the
> file editor is locked, so if you use one, check it still works.

**State line, off:** none.

**State line, blocked, editor already locked:** Your `wp-config.php` already
locks the file editor, so this switch has nothing left to do.

**State line, blocked, editor forced open:** Your `wp-config.php` sets
`DISALLOW_FILE_EDIT` to false, and that beats anything we do. The file editor
stays available until somebody changes or removes that line.

**State line, blocked, one GeneratePress element runs PHP:** One GeneratePress
element here runs PHP, and GeneratePress will not do that while the file editor
is locked. If that code no longer needs to live in an element, moving it to your
child theme frees this switch up.

**State line, blocked, several GeneratePress elements run PHP:** `{elements}`
GeneratePress elements here run PHP, and GeneratePress will not do that while
the file editor is locked. If that code no longer needs to live in an element,
moving it to your child theme frees this switch up.

The second sentence is conditional and stays conditional. A blocker is a fact
about the site and frequently something the owner did on purpose, so it is
reported and never set as homework.

> **Why this is a blocker and not a warning.** A warning is something a person
> reads and accepts. There is no informed way to accept this one: GeneratePress
> does not fail loudly when it stops running an element, it echoes the element's
> PHP source into the page instead, on every page that element runs on. And
> opening that element and saving it while the editor is locked deletes the
> "Execute PHP" setting for good, because GeneratePress hides the checkbox and
> reads its absence as off. Neither is a consequence somebody chose. See
> `refs/gotchas.md`, "GeneratePress".
>
> Only elements that are published, of type hook, and set to execute PHP count.
> A site that has hooked `generate_hooks_execute_php` has taken the decision away
> from the constant and is never blocked.

---

## When we switch something off

Appears once, across the dashboard, to every administrator, and can be
dismissed. It reports a change we have already made, so it is not asking anyone
to do anything.

**These sentences are about the event, not about the site**, and that is the one
place on this screen where the past tense is right. The banner outlives the
situation that caused it: it stays until the setting is switched back on or the
notice is dismissed, because somebody whose setting was changed needs telling
whether or not the reason has since gone away. So nothing here may describe how
the site is now, or it will be describing it wrongly a day later.

### Hopelessly Sensible has changed a setting

One sentence per setting, then the closing line, once, however many there are.

**Remote publishing:** Something on your site started using remote publishing,
and "Block remote publishing" would have stopped it working, so we switched that
setting off.

**Product reviews:** WooCommerce was not active, so "Close product reviews" had
nothing left to act on and we switched it off.

**File editor:** Your `wp-config.php` set `DISALLOW_FILE_EDIT` to false, which
beats anything we do, so we switched "Lock the file editor" off.

### A GeneratePress element started running PHP

> A GeneratePress element on your site started running PHP, and "Lock the file
> editor" would have stopped GeneratePress running it, so we switched that
> setting off.

Plural, where more than one element was found: GeneratePress elements on your
site started running PHP, and "Lock the file editor" would have stopped
GeneratePress running them, so we switched that setting off.

### When the cause was not recorded

> Something on your site meant the file editor could not be locked, so we
> switched "Lock the file editor" off.

**A feature that can be switched off for more than one reason keeps the reason.**
The banner reports an event, so it may not go and ask the site what is wrong
now: the answer would be a different sentence a day later, or no sentence at
all. The blocker variant is recorded at the moment the switch moves, and the
sentence is chosen from it. The line above is what stands for a record written
by a version that did not keep causes, and it is worded to be true whichever
cause it turns out to have been.

**Closing line:** You can switch these back on under Settings, Hopelessly
Sensible, and that screen will tell you if anything is still in the way.

---

## The admin username warning

No toggle. Appears on this page only, never elsewhere in the dashboard.

### You have a user called "admin"

> "admin" is the first username automated guessing tools try, so that account
> attracts far more attempts than any other on your site.
>
> We have not changed anything, and this plugin will not: WordPress does not let
> you rename a user once it exists. If you want to deal with it, the usual route
> is to create a second administrator account with a different username, log in
> as that one, then delete the "admin" account and hand its posts over to the
> new one when WordPress asks.

---

## The network activation notice

No toggle. Appears on this page only, and only on a site where the plugin was
activated across a whole network.

### This plugin was activated for the whole network

> Activating across a network means the plugin could not look at your sites one
> by one, so none of the options below were chosen for you: they are all sitting
> where they start. What you set here applies to this site only, and each of
> your other sites is set separately.
>
> If you would rather it looked at each site and made a start for you,
> deactivate it for the network and activate it on each site instead.
