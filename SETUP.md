# Setting up Client Work Hub

From an empty folder on your hosting to your first client, in order.

Client Work Hub is self-hosted. The files go on hosting **you** control, and
everything it stores — your clients, their passwords, your schedule — stays on
your server. Nobody else has a copy, which also means nobody else can fix it
for you. That trade is the whole point, and this document is the part that
makes it a fair one.

**Roughly twenty minutes**, most of it waiting for an upload.

---

## Contents

1. [What you need before you start](#1--what-you-need-before-you-start)
2. [Where it goes — the folder question](#2--where-it-goes--the-folder-question)
3. [READ THIS BEFORE YOU PASSWORD-PROTECT ANYTHING](#3--read-this-before-you-password-protect-anything)
4. [Set up the database](#4--set-up-the-database)
5. [Upload the files](#5--upload-the-files)
6. [Run the installer](#6--run-the-installer)
7. [Lock it down — the five minutes after](#7--lock-it-down--the-five-minutes-after)
8. [Get started: a client, a job, a property](#8--get-started-a-client-a-job-a-property)
9. [Advanced and optional](#9--advanced-and-optional)
   · [The billing portal](#the-billing-portal-your-public-page)
   · [Email](#email--off-until-you-turn-it-on)
   · [Scheduled runs (cron)](#scheduled-runs-cron)
   · [Backups](#backups)
   · [nginx](#if-your-host-runs-nginx)
10. [When something goes wrong](#10--when-something-goes-wrong)

---

## 1 · What you need before you start

| | |
|---|---|
| **PHP 8.1 or newer** | with `pdo_mysql`, `mbstring` and `openssl`. Almost every host has all three, and the installer checks and names any that are missing rather than failing oddly later. |
| **A MySQL or MariaDB database** | and a user with **ALL PRIVILEGES** on it. The application creates and alters its own tables, so a read-only or restricted user will get part-way through the install and stop. |
| **Somewhere to put files** | cPanel File Manager, FTP, or a terminal. Any of them. |

You do not need: composer, node, a build step, SSH, or a framework. It is
plain PHP and SQL, and it is meant to run on ordinary shared hosting.

**If your host runs nginx rather than Apache or LiteSpeed, read
[§9](#if-your-host-runs-nginx) BEFORE you upload.** cPanel is Apache or
LiteSpeed and both read the `.htaccess` that ships with the application. nginx
reads none of it, and the protections in it are then simply absent.

---

## 2 · Where it goes — the folder question

**Short answer: put the whole application in its own folder, and never create
a folder called `admin` yourself. It already has one.**

This is the thing most people get tangled in, so here it is laid out.

### There is no separate "admin install"

Client Work Hub is **one application**. Inside it, `admin/` is already there —
it arrives in the zip. Wherever you put the application, the admin screens are
at `/admin/` **underneath it**, and you do not create, move or rename that
folder.

### If the domain is only ever going to be Client Work Hub

Extract into `public_html/` itself:

```
public_html/
├── index.php          ← the front door
├── admin/             ← comes in the zip. Your screens
├── account/           ← sign-in, password, two-step
├── lib/  css/  js/  migrations/
├── config.php         ← written by the installer
└── storage/           ← created by the installer
```

- You sign in at **`yoursite.com/admin/`**
- With the billing portal off (the default) `yoursite.com/` simply sends you
  to the sign-in page. Nothing public is exposed.

### If the domain already has a website on it

**Yes: `public_html/clientworkhub/`, and the admin is then at
`public_html/clientworkhub/admin/`.**

```
public_html/
├── index.html         ← YOUR EXISTING SITE. Untouched
├── about.html
├── wp-content/        ← or a WordPress. Also untouched
└── clientworkhub/     ← the whole application, in here
    ├── index.php
    ├── admin/         ← yoursite.com/clientworkhub/admin/
    └── …
```

- Your site stays at `yoursite.com/`, working exactly as it does now
- Client Work Hub is at `yoursite.com/clientworkhub/`
- You sign in at **`yoursite.com/clientworkhub/admin/`**

**Nothing about this is a workaround.** The application is built to live in a
subfolder: there is not one link anywhere in it that assumes it owns the root
of a domain, and its `.htaccess` is written so that its protections still
apply from inside a folder. A subfolder install is as supported as a root one.

**The folder name is yours.** `clientworkhub`, `crm`, `manager`, `office` —
whatever you will not mind typing. Shorter is kinder to you.

**One thing you must get right:** on the installer's first screen, the **Web
address** field has to include the folder —
`https://yoursite.com/clientworkhub`, not `https://yoursite.com`. Every link
in every email is built from it. If you get it wrong you can correct
`base_url` in `config.php` afterwards.

### Or a subdomain

Create it in cPanel first (say `manager.yoursite.com`), then extract into the
folder cPanel made for it. Sign-in is `manager.yoursite.com/admin/`. This is
the tidiest option if you own the DNS and do not mind the extra step.

---

## 3 · READ THIS BEFORE YOU PASSWORD-PROTECT ANYTHING

cPanel's **Directory Privacy** (some hosts call it "Password Protect
Directories") puts a browser password box in front of a folder. It is a good
idea in the right place and it will cause you a bad afternoon in the wrong
one.

### Do not password-protect anything until the install is finished

Protect a folder first and you are putting a password box in front of your own
installer — at best confusing, at worst a half-finished install you cannot get
back into to finish. **Upload, install, and then protect.**

### Never password-protect the application's top folder

Protect `public_html/clientworkhub/` itself and you have protected **the whole
application, including every page your clients are meant to reach**:

| What breaks | Why it matters |
|---|---|
| Rebooking links | The link at the bottom of your follow-up emails asks the client for a password they do not have |
| Receipts | A client cannot open the receipt you just sent them |
| **Unsubscribe links** | **The unsubscribe link in every email stops working.** That is the one with legal weight, not just an annoyance |
| The onboarding form | The form new clients fill in to hand over their details |
| Your billing portal | If you have it on, nobody can see it |

None of this announces itself. You will not get an error; your clients will
just quietly hit a password box and give up, and you will not hear about it.

### Do password-protect `admin/`, after the install

This is the one that is worth doing:

```
public_html/clientworkhub/admin/
```

cPanel → **Directory Privacy** → navigate into your application folder →
select **`admin`** → tick *Password protect this directory* → add a user.

**Use a different password from your Client Work Hub sign-in.** Two locks with
one key is one lock.

Why this folder and no other:

- **It is safe.** Your sign-in page lives at `account/login.php`, *outside*
  `admin/`, so signing in still works normally. So does *Forgot your
  password*.
- **Nothing public lives under `admin/`.** Every page a client ever touches is
  in the folder above it, and the stylesheets and scripts are in `css/` and
  `js/`. Protecting `admin/` cannot reach any of them.
- **Nothing is disturbed.** The application ships no `.htaccess` inside
  `admin/`, so cPanel writes a fresh one and there is nothing of ours for it
  to overwrite.

You will be asked for the browser password once per session, and then your own
sign-in. That is the point: an attacker now needs two things before they reach
a screen that could show them a client's stored password.

### And never protect `account/`

It holds *Forgot your password* and the two-step screens. Protect it and the
day you are locked out is the day you cannot get the reset link to work
either.

---

## 4 · Set up the database

cPanel → **MySQL® Databases**.

1. **Create a database.** Call it something you will recognise —
   `clientworkhub`.
2. **Create a user**, with a long random password. Use the *Password
   Generator* and paste the result somewhere before you close the box; you
   need it in five minutes and never again.
3. **Add the user to the database**, and tick **ALL PRIVILEGES**.

**Write down all three: database name, user name, password.** cPanel puts your
account name in front of the first two, so what you actually need is more like
`myaccount_clientworkhub` and `myaccount_cmuser`. The name you typed is not
the name to use.

You do **not** need to create any tables, import any `.sql` file, or open
phpMyAdmin. The installer builds all of it.

**Host and port**, for the installer: on cPanel these are almost always
`localhost` and `3306`. If your host is one of the few that differs, they will
say so in their own documentation.

---

## 5 · Upload the files

cPanel → **File Manager** → navigate to where you decided in
[§2](#2--where-it-goes--the-folder-question) → **Upload** the release zip →
back in File Manager, right-click it → **Extract**.

Then **delete the zip file**. It is a second copy of the whole application
sitting in a public folder.

**Check that it extracted where you meant.** The commonest slip is ending up
with `clientworkhub/clientworkhub/index.php`. You want `index.php` directly
inside your chosen folder, with `admin`, `account` and `lib` beside it.

**Make sure hidden files came across.** The application ships a file called
`.htaccess`, and the leading dot makes some FTP clients hide it and some
extract steps skip it. In File Manager: **Settings** → *Show Hidden Files*. It
matters — it is what stops strangers reading `config.php` and the contents of
`storage/`. There is a way to check in [§7](#7--lock-it-down--the-five-minutes-after).

---

## 6 · Run the installer

**Open `/install.php` at your address, straight away.**

- Root install: `https://yoursite.com/install.php`
- Folder install: `https://yoursite.com/clientworkhub/install.php`

**Do this immediately after uploading, not tomorrow.** Until the installer has
run, the site belongs to whoever runs it first — and that is a public URL.

One form, three sections:

| | |
|---|---|
| **1 · Your site** | The web address, with `https://` and no trailing slash, **including the subfolder if you used one**. Every link in every email is built from it. |
| **2 · Your database** | The three things from [§4](#4--set-up-the-database), plus host and port. |
| **3 · Your administrator account** | Your name, your email, and the password you will sign in with. There is no confirmation email — installing the site is proof enough — so it works immediately. |

Press the button and it will:

- write `config.php`, **including a freshly generated encryption key**
- create `storage/`, and close it to the web
- build every table the application needs
- create your administrator account and sign you in
- **delete itself**

**If the finished page says the installer could not delete itself, delete
`install.php` by hand now.** File Manager → right-click → Delete. Until it is
gone it is the one file on the site that could hand it to somebody else.

### About that encryption key

`config.php` now holds a key that nothing else in the world has. Every client
password you store is encrypted with it, and it is deliberately not in the
database — so a stolen database dump decrypts to nothing.

**It cannot be changed later without re-entering every stored password.** Two
consequences, both of which matter more than they look:

- **Keep a copy of `config.php` with your backups.** A database backup without
  it restores into a vault nobody can open.
- **If you tested on a laptop first, do not carry that `config.php` up to the
  live site.** Let the installer make a new one.

---

## 7 · Lock it down — the five minutes after

Do these now, in this order. They are the difference between "installed" and
"safe to put a client's password into".

**1 · Turn on two-step sign-in.** Admin → **Settings → Two-step sign-in**.
This application holds the passwords to every account you manage for every
client; a password on its own is one reused password away from all of it.
**Save the ten backup codes somewhere that is not the phone generating the
codes.**

**2 · Password-protect `admin/`** — [§3](#3--read-this-before-you-password-protect-anything).
Now, not before.

**3 · Check `.htaccess` is actually being applied.** Paste each of these into
your browser, with your own address. **Every one must come back "Not found" or
"Forbidden":**

```
https://yoursite.com/clientworkhub/config.php
https://yoursite.com/clientworkhub/storage/
https://yoursite.com/clientworkhub/MANIFEST.txt
https://yoursite.com/clientworkhub/lib/bootstrap.php
https://yoursite.com/clientworkhub/migrations/001_init.sql
```

If any of them shows you anything at all, `.htaccess` is not being read.
Either it did not get uploaded (see [§5](#5--upload-the-files)), or your host
has `AllowOverride` switched off — ask them to enable it, or move the rules
into your vhost. **On nginx none of it applies at all**; see
[§9](#if-your-host-runs-nginx).

**4 · Check the health screen.** Admin → Settings → **Update**. It answers,
from inside the site, every question a technician would answer from a command
line: PHP version, whether encryption is working, whether every file arrived
intact, whether the database is up to date.

**5 · Take a backup.** Admin → **Settings → Update → *Download a backup now***.
Put it somewhere that is not the server. A backup you have never taken is not a
backup — and keep a copy of `config.php` beside it.

---

## 8 · Get started: a client, a job, a property

This is what the application is *for*, and it is meant to take under a minute.
Ninety per cent of your time in here will be these three things.

Everything below **opens in a window over the page you are on**. You never
lose your place, and Cancel costs you nothing.

### The three words

| | |
|---|---|
| **Client** | The person or business you work for. |
| **Job** | A piece of work for them, with a value, a start and a length. It generates the daily tasks that show up on your dashboard. |
| **Property** | A thing of theirs that you work *on* — a website, a Facebook page, a domain registrar, a Google Business listing. This is where a sign-in is stored, and it is why the vault exists. |

### Add a client

**"+ New" in the header → Client.** Available from any screen.

Five short steps: who they are · where they stand · how to reach them · where
they are · anything worth remembering. **Only the first has anything you must
answer.** Skip the rest and come back — a half-filled client is a real client.

**"Anything worth remembering" is worth a sentence.** It becomes their
*standing instructions* — the permanent rules, like *prefers 500-word
articles, never 300*. It is shown to you wherever their work is being done,
and you can add to it later from inside a task without leaving the task.

### Add a job

**"+ New" → Job**, or **Add job** on the client's own page.

Three steps: who it is for · what the work is · when it runs.

- **You do not have to name it.** Leave it empty and it names itself.
- **You do not have to set a date.** It starts today.
- **Paste the brief.** Whatever they sent you — keywords, targets, links, the
  lot. It sits on the job and appears wherever the work is done, so you are
  never hunting the email again.
- **A service is optional.** If you have set one up, picking it brings its
  price, its length and its daily tasks with it. If you have not, choose
  *"nothing, I will describe it"*.

**Nothing here refuses you over something it could work out for itself**, and
if it does have to stop, everything you typed is still there when it does.

### Add a property

Straight after you create a job, the application asks which of the client's
properties the job touches, and lets you **add one right there** with
**+ New property** — created and ticked in a single save.

You can also add one any time from the client's page → **Add property**.

A property holds its address, what it is, and — if you want — **the username
and password**. Those are encrypted, and they are shown to you exactly where
you are working: the address, a link that opens it, the username with a Copy
button, and the password one deliberate click away. Every reveal is written to
the audit log.

### Then work

Your **Dashboard** is the day: what is running, what is next, and one door into
it. Open the daily task, start the timer, do the work, close it out. Time
recorded against the job becomes the value and profitability figures in
**Reports**.

### The Help button

**Bottom-right corner of every screen.** It opens a short walkthrough — five
or six cards you step through with Next — and on the dashboard, a client, a
job and a task it explains **that** screen rather than the application in
general. Everywhere else it gives you the general tour.

It never opens itself, and it does not track what you have read. If you would
rather not see it: **Settings → Help → uncheck *Show the Help button***. The
walkthroughs stay in the software and come straight back if you switch it on
again.

### If you want a shortcut for repeat work: Services

**Set up → Services.** A service is a piece of work you sell more than once —
its price, how long it takes, and the tasks it generates. After that, "new job
from that service" is two clicks and everything is filled in.

**You do not need one to start.** Add a client, add a job, get on with it.
Build services when you notice yourself typing the same job twice.

---

## 9 · Advanced and optional

**None of this is needed to run the application.** Everything in
[§8](#8--get-started-a-client-a-job-a-property) works with all of it switched
off. Come back when you want one of these things.

### The billing portal (your public page)

**Admin → Settings → "Your basic billing portal".**

Off by default, and most installs leave it off — if you take payment through
Fiverr or Upwork or an invoice by email, you do not need it and you can skip
this section entirely.

> **Where to find it:** while the portal is off, this group sits at the bottom
> of the Settings page folded shut and marked *not in use* — deliberately, so
> a feature nobody is using does not interrupt the ones they are. Click the
> heading to open it. **The moment you switch it on, it stays open** and the
> *not in use* label goes.

**What it is:** the billing and signup side of a business whose real website is
somewhere else. Your published services, a contact form, and a booking form.
**There is deliberately no sign-in link on it** — a sign-in link on a shop
window is an invitation to try, and you already know where `/admin/` is.

**Turning it on:**

1. **Turn the billing portal on** — the switch at the top of the group.
2. **Business name.** Without one it falls back to your email From name, and
   then to "Client Work Hub", which is the software's name and not yours.
3. **Your logo.** Around 400 × 100 px, under 200 KB, PNG or JPEG.
   **SVG is refused on purpose** — an SVG can carry a script, and it would be
   served from your own domain, the one place a browser trusts. The file is
   stored outside the web root and served through a single PHP file that
   accepts no parameters, so it cannot be turned into a way to read anything
   else.
4. **One line about what you do.** It sits under your name and becomes the
   description a search engine shows.
5. **Contact email and phone.** Both optional; both appear in the footer of
   every portal page.
6. **"When somebody arrives without a service link."** Three answers:
   - **List my services** (the default) — the shop window.
   - **Send them to my main website** — needs the address in the field below
     it. This is usually the right one if your real site is elsewhere. An
     empty address falls back to the list rather than sending anybody nowhere.
   - **Show nothing at all** — the portal only works by direct link.

**A direct link to a service always works, whichever of those three you
choose.** That is the entire point of them. **Set up → Services → open a
service → Direct link** gives you a URL with a Copy button, which is what you
paste behind a Buy button on your real website.

**Publishing:** a service only appears on the portal once you mark it
Published. *Featured* puts it in the row at the top.

**Before you announce it, look at the page as a stranger.** Open your portal
address in a private window and check that what is on it is what you meant to
sell.

### Email — off until you turn it on

**Admin → Settings → Email.**

Out of the box `Send email` is **off**, and everything the application would
have sent is written to a folder instead. That is the right default — nothing
can escape while you are still finding your way around — but it means:

**no invoice emails, no receipts, no onboarding links, no password reset
emails, no unsubscribe confirmations.** A client who asks to reset their
password gets nothing, and nobody is told.

Turn `Send email` on, set the From name and address, **send yourself one thing,
and confirm it arrives.** Do that on day one, not after the first client
complains.

While it is off, you can read exactly what *would* have gone out: the files are
in `storage/mail/`, readable through File Manager. They are not reachable over
the web.

### Scheduled runs (cron)

**You do not need a cron. The application works without one and always has.**

Follow-up emails are sent **when you open the Dashboard**, which for most
people is every working day. That is deliberate: this is self-hosted by people
who will never open a cron screen, and a feature that silently requires one is
a feature that silently does nothing.

**The honest consequence:** a message due on a day you never sign in goes out
the next day you do. **Late, not lost.** For one person working their own
diary, that is usually fine.

**Set up a cron if** you want messages to go out on the day they are due
whether or not you sign in, or you are building up enough scheduled mail that
you would rather it trickled than arrived in one batch.

**Admin → Settings → Email → "Set up a scheduled run…"** has the exact command
for *your* install, ready to copy. It looks like this:

```bash
php /home/youraccount/public_html/clientworkhub/tools/cron.php
```

In cPanel: **Cron Jobs** → *Add New Cron Job* → choose the interval → paste the
command.

**Hourly is the right answer for almost everybody.** Twenty messages go out per
run, oldest first:

| How often | Runs a day | Messages a day |
|---|---|---|
| Once a day | 1 | 20 |
| **Every hour** | 24 | **480** |
| Every 15 minutes | 96 | 1,920 |

That cap is what makes the schedule the throttle. Shared hosts commonly cap
outbound mail at 200–500 an hour, and going over freezes **the whole domain's**
mail, not just one message. 480 a day is far more than a one-person business
will ever schedule and comfortably under every limit worth worrying about. A
run with nothing due does nothing and costs no email, so a frequent schedule is
not wasteful.

**It is command-line only.** There is no URL and no secret key to keep safe —
`tools/` is denied over the web. Add `--dry-run` to the end to see what *would*
go without sending anything.

Once it is running, that Settings block changes to **"A scheduled run is
working"** with the time of the last one — so a cron that quietly dies is
visible rather than silent.

### Backups

**Admin → Settings → Update → *Download a backup now*.** Keep it off the
server, and **keep a copy of `config.php` with it** — the backup does not contain your encryption key, and a
restored database without that key is a vault nobody can open.

### Updating to a new release

1. **Take a backup first.** The update button does not work without one, on
   purpose.
2. Upload the new release zip and extract it over the top of the old files.
   `config.php` and `storage/` are not in the zip, so nothing of yours is
   touched.
3. Admin → Settings → **Update** lists the database changes that arrived with
   the files, verifies every file arrived intact, and applies them with one
   button.

### If your host runs nginx

**Skip this on cPanel** — cPanel is Apache or LiteSpeed and both read
`.htaccess`.

**nginx ignores `.htaccess` completely.** Nothing in it applies, and the files
it protects are then served to anybody who asks: the database schema in
`migrations/*.sql`, every `.md` file, `MANIFEST.txt`, and the contents of
`storage/`. Add this inside your `server { … }` block:

```nginx
# The application's own files, served to nobody.
location ~* \.(sql|md|bat|log)$            { deny all; }
location = /MANIFEST.txt                   { deny all; }
location = /VERSION.txt                    { deny all; }
location = /config.php                     { deny all; }
location ^~ /migrations/                   { deny all; }
location ^~ /lib/                          { deny all; }
location ^~ /tests/                        { deny all; }
location ^~ /tools/                        { deny all; }
location ^~ /storage/                      { deny all; }
```

Then `nginx -t` and reload. Check it with the five URLs in
[§7](#7--lock-it-down--the-five-minutes-after) — every one must refuse.

**Installed in a subfolder?** Prefix each path with the folder:
`location ^~ /clientworkhub/lib/`.

---

## 10 · When something goes wrong

| What you see | What it is |
|---|---|
| **"Client Work Hub is not set up yet"** | `config.php` is missing or the installer never finished. Open `/install.php`. |
| **A completely blank white page** | A PHP error with error display off, which is the correct production setting. Your host's error log has the reason — cPanel → *Errors*. |
| **`install.php` shows "already installed"** | It is doing its job. An account exists, so it refuses. Delete the file. |
| **Sign-in page loops back to itself** | Cookies blocked, or `base_url` in `config.php` does not match the address you are actually using — `http` vs `https` is the usual culprit. |
| **A client says a link does not work** | Check you have not password-protected the application's top folder — [§3](#3--read-this-before-you-password-protect-anything). Then check email is switched on. |
| **You cannot sign in after an update** | `config.php` holds an `update_key` that reaches the update, backup and restore screens with no sign-in. `SECURITY.md` explains it. |
| **Lost your admin password** | *Forgot your password* on the sign-in page — **needs email switched on**. Otherwise, edit `admin_emails` in `config.php` through File Manager to add an address you control, then use the reset. |
| **Emails are not arriving** | Settings → Email: is `Send email` on? Then look in `storage/mail/` — if the files are being written there, it is still off. |

---

## What is in this folder

| | |
|---|---|
| **`SETUP.md`** | This file. |
| **`FEATURES.md`** | What the application can do, from the basics outwards. |
| **`INSTALL.md`** | The short installation reference. |
| **`SECURITY.md`** | How stored passwords are protected, and what you are trusted to do. **Read it before you put a real client password in.** |
| **`README.md`** | What the application is, in a paragraph. |
