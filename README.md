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

**Current version: 0.3.19.** `FEATURES.md` is what it does, `SECURITY.md` is
how it is defended and what is deliberately left open, `INSTALL.md` and
`SETUP.md` are how to put it on a server and switch it on.

## Running it locally

Any PHP 8.1+ with `pdo_mysql`, `openssl` and `mbstring`, and any
MySQL 5.7+ / MariaDB 10.4+. There is no build step, no composer and no npm.

1. Create an empty database and a user with rights on it.
2. Serve the folder. PHP's own server is enough, but **route it through
   `tools/devserver.php`** —

   ```
   php -S localhost:8080 tools/devserver.php
   ```

   The built-in server does not read `.htaccess` and never will, so without
   that router `storage/`, `lib/` and the spooled email under
   `storage/mail/` are all readable over HTTP locally while being correctly
   denied on any real host. Developing against the more permissive of the two
   is how a production-only fault never gets reproduced; the router is a
   line-by-line mirror of `.htaccess` and `tests/smoke.php` fetches every one
   of those paths and demands a refusal.
3. Open it. With no `config.php` you land on the installer.

Run the suites with `php tests/smoke.php --base=http://localhost:8080`.
Without `--base` the HTTP half is skipped and says so.

## Deploying to cPanel

Upload everything, open `/install.php`, fill in one form. It writes
`config.php` (with a generated encryption key), builds the database, creates
your administrator account and deletes itself. Then turn on two-step sign-in.

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
