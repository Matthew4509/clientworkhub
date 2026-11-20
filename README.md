# Client Work Hub

**https://github.com/Matthew4509/clientworkhub** — releases are published
on its Releases page, and the repository there has an illustrated tour
(`readme.html`, and the same tour as `Client-Work-Hub.pdf`).

This is self-hosted software: it runs on the operator's own server, under
their own domain, and names no other website anywhere a client can see. The
footer of a running install belongs to the person running it.

Client operations software for an agency of one (built to grow): clients,
their websites and social accounts, securely stored credentials, jobs with
daily/weekly/monthly actions, a work queue, time tracking, billing value and
profitability — behind a hardened single-admin sign-in with two-step
authentication.

Plain PHP + MySQL/MariaDB, no framework, no composer — built to run on
ordinary cPanel hosting and to be operated entirely from a browser: one-click
install (`install.php`), one-click database updates (Admin → Settings →
Update), streamed database backups, and a health screen that answers every
question a person with a shell would ask.

**Current version: 0.3.24.** `FEATURES.md` is what it does, `SECURITY.md` is
how it is defended and what is deliberately left open, `INSTALL.md` and
`SETUP.md` are how to put it on a server and switch it on.

## Two editions, one release

- **Web edition** (`clientworkhub-x.y.z.zip`): for your own hosting, as
  described below.
- **Windows home edition** (`clientworkhub-home-x.y.z.zip`): runs on one
  Windows 10 or 11 PC, for one person, with nothing to host. Unzip it and
  double-click **Install Client Work Hub.cmd** in the folder inside it: it
  installs for your Windows user (no administrator, unless the PC lacks
  Microsoft's Visual C++ Runtime: setup offers to install it, and Windows asks
  for permission), adds Client Work Hub to the Start menu, and opens it in its
  own window. It carries its own PHP and MariaDB, answers only on
  that PC, and keeps your data in `%APPDATA%\Client Work Hub`, which
  uninstalling (Settings > Apps) leaves alone. Everything that reaches a
  client or the internet is left out: no client portal or links, no email,
  no Stripe or PayPal, no work screens, no Piano property type. Invoices and
  job summaries are PDFs you save. **Check for updates** (Settings > Update)
  asks GitHub only when you press it, installs a newer release after checking
  its signature, and can go back to the version before.

The two are the same code: one setting in `config.php` (`'edition' =>
'home'`, written only by the Windows home edition when it first starts) turns the web features off on
the server, not just in the menus.

## Download the right zip

Take **`clientworkhub-x.y.z.zip`** from the
[Releases page](https://github.com/Matthew4509/clientworkhub/releases), under
*Assets*. Not the green **Code** button and not the *Source code* zips GitHub
adds to every release: those wrap everything in an extra folder, and they are
not the signed package that Admin → Settings → Update checks.

## Deploying to cPanel

1. cPanel → *MySQL® Databases*: create a database and a user, and add
   the user to it with **ALL PRIVILEGES**.
2. cPanel → *File Manager*: make a folder such as `public_html/hub`,
   upload the release zip into it and **Extract**. The files land directly
   in that folder.
3. Open `yoursite.com/hub/install.php` **straight away** and fill in one
   form. It writes `config.php` (with a generated encryption key), builds the
   database, creates your administrator account and deletes itself.
4. Turn on two-step sign-in.

**[INSTALL.md](INSTALL.md) is the full guide**: choosing the PHP version in
MultiPHP Manager, installing beside an existing website, nginx hosts, and
what to check afterwards.

## Running it locally

Any PHP 8.1+ with `pdo_mysql`, `openssl` and `mbstring`, and any
MySQL 5.7+ / MariaDB 10.3+. There is no build step, no composer and no npm.
XAMPP, Laragon or MAMP work: extract the zip into the web folder and open
`install.php` (see INSTALL.md).

PHP's own server (`php -S localhost:8080`) also runs it, but it does not read
`.htaccess`, so `storage/`, `lib/` and the spooled email are readable over
HTTP while it runs. Use it on your own machine only, never on a network.

## The three security rules that must never be broken

1. **Admin passwords are hashed** (`password_hash`), never encrypted.
2. **Stored client credentials are encrypted** (AES-256-GCM) with a key that
   lives in `config.php` / `storage/appkey.php` — **never in the database**.
   A stolen database dump must decrypt to nothing.
3. **The audit log is append-only.** No screen edits or deletes it, ever.

## Licence

**PolyForm Shield License 1.0.0.** `SPDX-License-Identifier: PolyForm-Shield-1.0.0`
— the full text is in [`LICENSE`](LICENSE), and it is on every source file
this project wrote.

In plain words: it is free. Install it, run your business on it, change it,
share copies of it, all at no cost. What you may not do is sell it or offer a
product that competes with it — repackaging it under another name, selling
copies, or selling it as a hosted service. The `Required Notice:` line at the
top of `LICENSE` has to stay with any copy. It comes with no warranty of any
kind — read the "No Liability" section of `LICENSE`, and mean it: this
software holds passwords that belong to other people.

Releases up to and including 0.3.18 were published under the MIT licence, and
copies of those releases keep the MIT terms they were given under. From
0.3.19 the licence is PolyForm Shield.

Three things inside it are somebody else's work under their own licences —
jsPDF (MIT) and the Inter and IBM Plex Mono font files (SIL Open Font License
1.1). They are listed in [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md),
and this project's licence does not cover them.

Separately from the licence, every release numbered **0.3.x is free for life**
to anyone already running it — no fee, no expiry, no licence key, no client
limit.
