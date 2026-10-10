# What Client Work Hub needs from a server

Written against **0.3.10**.

This is the whole list, in one place: what must be there before the
application will run at all, what only some features need, and exactly what
you lose when one of the optional pieces is missing.

**If you only want the answer for YOUR server, the application tells you.**
Three places show this same table, each with a ready-written message you can
send your hosting company naming only what is actually missing:

- **The installer**, under *What this server can do* — before you commit to
  anything.
- **Admin → Update → Health check**, once installed — the *PHP extensions*
  and *Optional extensions* rows, with *Send this to your hosting company*
  underneath.
- **`tools/requirements-check.php`**, for a server where the application will
  not start at all. Upload it into the folder the app lives in, open it, read
  it, then **delete it**.

All three read one list, in `lib/requirements.php`. They cannot disagree, and
`tests/lib_standalone.php` fails the build if any of them grows its own copy.

---

## 1 · The four hard requirements

Without any one of these, nothing runs. The installer checks all four before
it will accept the form, and says plainly which is missing rather than dying
as a blank page.

| | Need | Why |
|---|---|---|
| **PHP** | **8.1 or newer** | The code uses PHP 8.1 language features throughout. Tested clean under `E_ALL` up to 8.5. |
| **Extension** | `pdo_mysql` | The only way the application talks to the database. Real prepared statements, not PDO's escaper. |
| **Extension** | `mbstring` | Text handling for names, notes and emails — anything with an accent or a symbol in it. |
| **Extension** | `openssl` | AES-256-GCM encryption of stored client credentials, with the key kept outside the database. Also HTTPS from PHP. |

Plus a handful that are compiled into essentially every PHP ever shipped and
are listed only for completeness: `session`, `json`, `filter`, `ctype`,
`pcre`, `hash`. If one of those is genuinely absent you have a broken PHP,
not a configuration problem.

**On cPanel, `nd_pdo_mysql` counts as `pdo_mysql`.** It is the same driver
under the host's own name.

**The PHP version is set per domain, in *MultiPHP Manager*** — not in *PHP
Selector*, which only sets the account default. A domain still on PHP 7 shows
the installer as a blank page, not as a message.

### And a database

- **MySQL 5.7+ or MariaDB 10.3+**, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`.
  That is a conservative floor rather than a hard one: the schema uses no
  JSON columns, no generated columns and no CHECK constraints, and every
  indexed text column is 190 characters or narrower — deliberately inside
  the old 767-byte InnoDB index limit, so it would in fact build on 5.6.
  Nothing here pins you to a recent server; ask your host for the newest
  they offer anyway.
- A user with **ALL PRIVILEGES** on it. The updater runs `ALTER TABLE`, so
  read/write alone is not enough.

### And HTTPS

Not enforced by code, but the application holds real client passwords and
sends password-reset links. Run it on https and nothing else.

---

## 2 · The optional extensions, and what each one costs you

Every one of these is behind a `function_exists()` check. A missing one
produces a plain refusal in words at the point of use — never a fatal, never
a half-done job.

### `zip` — updates, both routes

Reading a release zip. Updating by upload needs it (and so would the online
route, which is off unless `config.php` names a feed).

**Without it:** no updates at all through Admin → Update. You replace files
by hand in File Manager, then run the database step.

### `curl` — card payments (and the online update route, if switched on)

Every call to Stripe and PayPal. The online update check is off by default,
so updates do not need curl.

**Without it:** card and PayPal payments cannot work. Updating by upload is
unaffected.

Note that curl here always verifies the certificate (`SSL_VERIFYPEER`), so
the host also needs a **CA bundle**. Real hosting has one; a bare local PHP
build often does not, and shows up as a payment provider that "could not be reached".

### `sodium` — the release signature

Verifying that an uploaded release really was signed with the Client Work
Hub release key. This is the one that catches people, because sodium is
bundled with PHP 8 but is a **separately tickable extension on cPanel**, and
plenty of hosts leave it off.

**Without it:** an uploaded release is accepted on its checksums alone,
and the screen says plainly that its signature could not be checked. Upload
only a release you downloaded from the project's Releases page yourself.
(The online route, if it is ever switched on in `config.php`, refuses
outright without sodium: it will not install a release whose authorship it
cannot prove.)

### `zlib` — compressed backups

Gzipping the database backup on the way out; roughly a tenth the size, and
phpMyAdmin reads `.sql.gz` natively.

**Without it:** backups still download, as a plain `.sql`.

### `gd` — re-encoding an uploaded logo

Defence in depth on logo upload: the image is decoded and written out again,
which destroys anything hidden in the file alongside the picture.

**Without it:** logo upload still works. The file is still validated by its
own header bytes, but is kept exactly as you uploaded it.

---

## 3 · Server settings worth checking

| Setting | Wants | Why |
|---|---|---|
| `upload_max_filesize` | **≥ 8M** | The release zip is ~1.4 MB today and grows every version. The Update screen prints your host's limit next to the upload box. |
| `post_max_size` | ≥ `upload_max_filesize` | If it is smaller, a large upload is discarded before PHP ever sees it. The site then answers that the file is larger than the server accepts and names the limit, so raise this one along with `upload_max_filesize`. |
| `display_errors` | **Off** | With it on, PHP prints its own warning above the site's message when an upload is too large (that warning is written before the site's code runs, so only php.ini can stop it). |
| `memory_limit` | 64M is comfortable | |
| `max_execution_time` | 30s is fine | The updater and the backup ask for longer themselves where the host permits it. |
| `date.timezone` | anything | The application sets its own from your settings. |

---

## 4 · What it does NOT need

Worth stating, because these are the usual reasons an application is hard to
host — and every one of them was designed out:

- **No Composer, and no `vendor/` step.** Nothing to build before uploading.
- **No `mod_rewrite`.** Every URL is a real `.php` file. The `.htaccess` uses
  only `Options -Indexes` and deny rules, each wrapped in `<IfModule>`.
- **No shell access.** No `exec`, no `shell_exec`, no `proc_open` anywhere.
  The database backup is written in PHP precisely so it does not need
  `mysqldump` — and so a database password never appears on a command line
  where every other user on a shared host can read it.
- **No cron.** Follow-up emails go out when you open the dashboard. A cron is
  supported (`php tools/cron.php`) if you want messages to leave on the day
  they are due whether or not you sign in, but nothing requires one.
- **No SMTP library or mail extension.** Sending uses PHP's own `mail()`,
  which on cPanel is the host's mail server already.
- **No PDF extension.** Invoice and receipt PDFs are drawn in the browser.
- **No `intl`, no `bcmath`, no `gmp`, no `fileinfo`, no `imagick`.**
- **No third-party JavaScript, and no CDN.** The CSP is `script-src 'self'`,
  and even the 2FA QR code is generated on your own server rather than
  handing the shared secret to an image service.

---

## 5 · Turning an extension on, on cPanel

**cPanel → Select PHP Version → Extensions**, tick it. It takes effect at
once — no restart, no support ticket.

The one trap: make sure the PHP version shown on that screen is the version
the domain actually runs. A host with several PHPs installed will happily let
you tick `sodium` on 8.2 while the domain runs 8.4, and nothing will change.
`tools/requirements-check.php` prints the version it is running under, which
is the version that matters.

---

## 6 · Where the code enforces this

So the list can be checked rather than believed:

- `install.php` — the installer's gate, `cm_requirements_missing(true)`:
  PHP 8.1, `pdo_mysql`, `mbstring`, `openssl`. Computed above the POST
  handler, because the handler would otherwise call `mb_strlen()` on a PHP
  with no mbstring, which is a fatal instead of a message.
- `lib/health.php` — `cm_health_checks()`: the same four, re-checked on
  Admin → Health.
- `lib/updater.php` — `cm_update_ready()`: ZipArchive.
- `lib/updater.php` — `cm_update_online_ready()`: curl **and** sodium.
- `lib/payments.php` — `cm_pay_http()`: curl, for Stripe and PayPal.
- `lib/backup.php` — `cm_backup_can_gzip()`: zlib.
- `lib/branding.php` — `cm_logo_png_decodes()`, `cm_logo_jpeg_truncated()`
  and `cm_logo_reencode()`: gd.

- `lib/requirements.php` — **the one declaration.** Everything above comes
  from the array in `cm_requirements()`, and
  `cm_requirements_host_message()` turns whatever is missing into the note
  you send your host. Adding an extension there makes it appear on all three
  screens at once.

`lib/requirements.php` runs on a server that cannot run the application —
that is the point of it, since the installer loads it to explain why it will
not proceed. So it uses no `mb_*`, no `openssl`, no database and no config.
Keep it that way.

---

## 7 · Terms

The installer shows the terms in `lib/terms.php` and will not proceed until
the confirmation box is ticked — the tick is compared on the server, not just
required by the browser. What was agreed, by whom and when is written to the
audit log and to settings, because `install.php` deletes itself moments later
and terms nobody can re-read afterwards are not terms.

`CM_TERMS_VERSION` stamps the wording. Change a sentence, bump the stamp.
