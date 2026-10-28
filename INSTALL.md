# Installing Client Work Hub

Client Work Hub is free, self-hosted software. You put the files on hosting
YOU control, and everything it stores — your clients, their sign-in details,
your schedule — stays on your server and nobody else's.

It runs on ordinary shared hosting (cPanel) or on your own computer. You do
not need to be technical: one form does the whole install.

---

## What you need

- **PHP 8.1 or newer** with the `pdo_mysql`, `mbstring` and `openssl`
  extensions (almost every host has all three; the installer checks and
  tells you plainly if one is missing). On cPanel, `nd_pdo_mysql` counts as
  `pdo_mysql`.

  **On cPanel the PHP version is set per domain, in *MultiPHP Manager*** —
  not in *PHP Selector*, which only sets the account's default. A domain
  still on PHP 7 shows the installer as a blank page, not as a message.
  Check what the domain really runs before uploading: open any page and look
  for the `X-Powered-By` header, or make a one-line `<?php phpinfo();`
  file and open it, then delete it.
- **A MySQL or MariaDB database** and a user with ALL PRIVILEGES on it.
- Five minutes.

**For the full list — including the optional extensions and what each one is
for — see [REQUIREMENTS.md](REQUIREMENTS.md).** You do not have to read it
first: the installer shows the same table on screen, marks what your server
is missing, and writes out a message you can send your hosting company asking
for it. Admin → Update shows it again after installing.

## Installing on cPanel hosting

1. **Secure the hosting first.** This application will hold real passwords,
   so the account around it matters: use a strong, unique password on your
   cPanel/hosting login, and turn on your host's two-factor authentication
   if they offer it. A perfect application inside a weak hosting account is
   still weak.

2. **Create the database.** cPanel → *MySQL® Databases*: create a database,
   create a user, add the user to the database and tick **ALL PRIVILEGES**.
   Note all three names — cPanel puts your account name in front of them
   (like `myaccount_clients`).

3. **Upload the files.** cPanel → *File Manager*. Upload the release zip
   and Extract it where you want the application to live:
   - the whole site: `public_html/`
   - a folder: `public_html/manager/` → yoursite.com/manager
   - a subdomain: create it in cPanel first, then extract into its folder.

   All three work identically — nothing assumes it lives at the root.

   **If the domain already has a website on it — WordPress, Squarespace
   export, anything — use a folder or a subdomain, not `public_html/`.**
   This is not a limitation to work around; it is how the application is
   meant to be installed beside something else. Everything it serves lives
   under the folder you choose, so `public_html/hub/` gives you
   `yoursite.com/hub/portal/` for clients and `yoursite.com/hub/admin/` for
   you, and touches nothing your existing site owns. Extracting over the top
   of `public_html/` on a live site would put this application's
   `index.php` where the other site's front page is.

   **Do not name the folder `admin`.** The application has its own `admin/`
   inside it, and `yoursite.com/admin/admin/` is a confusing address to
   live with. `hub` reads well.

   **If you password-protect a folder with cPanel's *Directory Privacy*,
   protect the INNER `admin/` folder only** — `public_html/hub/admin/` —
   never the folder the application lives in. Everything under
   `portal/` (booking, receipts, the unsubscribe link in every email) has
   to stay reachable by your clients; a password on the whole install puts
   a browser sign-in box in front of every one of those links.

4. **Run the installer — straight away.** Open `/install.php` at the
   address you chose — for a folder install that is
   `yoursite.com/hub/install.php`, not `yoursite.com/install.php`.
   **Do this immediately after uploading**: until the installer has run, the
   site belongs to whoever runs it first. The form asks for the database
   details from step 2 and the administrator account you want, and does
   everything else — including generating the encryption key. It deletes
   itself when it finishes.

   The form's **Web address** is pre-filled with the folder the installer is
   running in — `https://yoursite.com/hub` for a folder install. Leave it:
   every link in every email is built from it, and a wrong one sends your
   clients to a page that does not exist. No trailing slash.

5. **Turn on two-step sign-in.** The success page links straight to it.
   This is the single most valuable minute you will spend: with it on,
   knowing your password is not enough to reach your clients' credentials.
   Save the ten backup codes somewhere that is not the phone generating the
   codes.

6. **Put your business name in** — Admin → Settings → Invoicing → *Your
   business* (legal or trading name), and the From name under Settings →
   Email. Until you do, the application signs everything it sends, and
   heads the PDF summaries, as **ClientWorkHub**.

7. **Check the health screen** — Admin → Settings → Update. It answers,
   from inside the site, every question a technician would answer from a
   command line: PHP version, encryption working, files intact, database up
   to date.

## On one Windows PC: the home edition

The simplest way to use it on your own Windows 10 or 11 PC, with nothing to
host and nothing else to install:

1. From the Releases page, download **`clientworkhub-home-x.y.z.zip`**.
2. Right-click it, **Extract All**, open the folder it makes, then the
   **Client Work Hub x.y.z** folder inside it.
3. Double-click **Install Client Work Hub.cmd**. If Windows warns about it,
   choose *Run* (on a blue "Windows protected your PC" box, *More info* first,
   then *Run anyway*). Setup installs for your Windows user only (no
   administrator), offers a desktop shortcut, and lists Client Work Hub in
   Settings > Apps. If the PC lacks Microsoft's Visual C++ Runtime, setup
   offers to download it from Microsoft first (Windows asks permission).
4. Open **Client Work Hub** from the Start menu. The first time, it asks for
   your name, an email address to sign in with (nothing is ever sent to it),
   a password and your time zone.

It runs only while its window is open and answers only on that PC. Your data
is in `%APPDATA%\Client Work Hub`; uninstalling leaves it there, and
installing again carries on from it. Forgotten your password? Start menu →
Client Work Hub → **Reset my password**. Updates: Settings → Update →
**Check for updates**. That replaces the application but not the PHP and
MariaDB beside it; when a release carries a newer PHP (0.3.21 does), download
the new home zip and run **Install Client Work Hub.cmd** again, which keeps
your data. Settings → Update warns while your PHP is older than the newest
download's. Back up from the same screen: the file goes to your Downloads
folder, so copy it to a USB stick or a cloud folder.

The home edition has no client portal, no email, no online payments, no work
screens and no Piano property type: everything that would reach a client or
the internet is switched off.

## Running it on your own computer (web edition)

Any local PHP + MySQL stack works — XAMPP and Laragon are the common ones
on Windows, MAMP on Mac.

1. Install XAMPP (or similar) and start **Apache** and **MySQL**.
2. Extract the release zip into the web folder — for XAMPP:
   `C:\xampp\htdocs\clientworkhub\`.
3. Create a database: open http://localhost/phpmyadmin → New → name it
   (e.g. `clientworkhub`). The default XAMPP database user is `root` with
   an empty password — fine for a machine only you use, never for hosting.
4. Open http://localhost/clientworkhub/install.php and fill in the form
   (database host `localhost`, port `3306`).

A local install is private to your machine. If the computer is shared,
treat it like hosting: real database password, two-step sign-in on.

## If your host runs nginx, not Apache

**Skip this on cPanel** — cPanel is Apache or LiteSpeed, and both read the
`.htaccess` that ships with the application.

**nginx ignores `.htaccess` completely.** Nothing in it applies, and the
files it protects are then served to anybody who asks: the database schema
in `migrations/*.sql`, every `.md` including `SECURITY.md`, `MANIFEST.txt`,
and the `tests/` and `tools/` folders. None of those contains a password —
`config.php` will not execute as text and `storage/` holds the key file
behind its own guard — but between them they hand a stranger a map of the
application and its defences.

Add this to your server block, inside `server { … }`:

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
location ^~ /naming/                       { deny all; }
```

Then `nginx -t` to check the syntax and reload.

**Two of those are belt and braces, not the belt.** Every file in `lib/`
refuses a direct request on its own (`if (!defined('CM_ROOT'))`), and
`storage/` keeps its key in a `.php` file that returns rather than prints.
`migrations/*.sql` has no such second guard — a `.sql` file cannot refuse to
be read — which is why the first two lines matter most.

To check it worked, ask for one and expect to be refused:

```bash
curl -o /dev/null -w '%{http_code}
' https://yoursite.example/migrations/001_init.sql
```

`403` or `404` is right. `200` means the block is not being applied — the
usual cause is that it sits in the wrong `server { }` for the hostname.

## Updating to a new release (web edition)

The home edition updates itself from Settings → Update → **Check for
updates** (see the home edition section above). On your own hosting, from
0.3.7 the whole update happens on one screen — Admin → Settings →
Update, top to bottom:

1. **Take a backup first** — *Download a backup now*. Neither update button
   works without one, on purpose. There is also *Download an encrypted backup
   instead*, which seals the same file with a four-word key shown once and
   stored nowhere — the copy to keep off-site. Keep a plain one too: it is the
   only copy that can be restored without this software running.

   To put one back, Admin → Settings → Update → **Restore a backup**. A file
   too big for your host's upload limit goes in `storage/restore/` by FTP or
   File Manager and appears in the list there.
2. **Get the release** — press **Check for updates** (from 0.3.22): the site
   asks GitHub Releases for the newest version and offers to download it.
   It asks only when you press the button. If your host blocks outgoing
   connections, download the newest `clientworkhub-x.y.z.zip` from
   <https://github.com/Matthew4509/clientworkhub/releases> instead and
   install it with **Or install a release zip you already have**, exactly
   as it downloaded. Either way the release is verified
   file-by-file before anything is touched, and where PHP has the `sodium`
   extension it must also carry Client Work Hub's signature — a tampered
   file is refused. Without sodium the screen says the signature could not
   be checked; upload only a zip you downloaded yourself.
3. **Apply it** — tick the disclaimer, press Apply. Your `config.php`,
   `storage/` and any files you added yourself are never written or
   removed.
4. **Update the database** — if the release brought database changes, the
   section below the files lists them and applies them with one button.

The old way still works and is the fallback if your host's PHP is missing
the `zip` extension: extract the release zip over the top of the old files
in File Manager (config.php and storage/ are not in the zip, so nothing of
yours is touched), then run step 4.

## If something goes wrong (web edition)

At home, a forgotten password is fixed from the Start menu: Client Work Hub →
**Reset my password**.

- **A too-large upload shows a PHP "Warning" line above the message** — your
  host has `display_errors` switched on in php.ini. PHP prints that line
  before the site's own code runs, so only the host setting can hide it:
  set `display_errors` to Off (cPanel → MultiPHP INI Editor). The upload was
  refused either way and nothing was changed.

- **The site shows "not set up yet"** — config.php is missing or the
  installer never ran. Open `/install.php`.
- **You cannot sign in after an update** — the recovery route in
  `config.php` (`update_key`) reaches the update, backup and restore screens
  with no sign-in, which makes the key as powerful as an admin password:
  remove it when you are done. SECURITY.md and the update screen itself
  explain it.
- **Lost the admin password** — *Forgot your password* on the sign-in page
  (needs email switched on), or edit `admin_emails` in config.php through
  File Manager to add an address you control, then use the reset flow.
