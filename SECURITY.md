# Client Work Hub — security review

> **Viewing a stored client credential is deliberately not gated behind a
> second sign-in.** The plaintext never reaches the page except one request
> at a time (`admin/credential.php`), the `credential.viewed` audit row is
> written before the value is decrypted, and the rate limit is 60 reads per
> 15 minutes. `cm_reveal_check()` is the guard on CHANGING a stored password.

This review covers every control listed below. This application is distributed FREE and self-hosted by strangers — in a
domain root, a folder, or a subdomain, on cPanel accounts and local machines
we will never see. Every control below is written for that: nothing assumes
a particular host, path, or a competent server administrator.

**Format**: each item states the control and WHERE it lives, so a future
change can check itself against this list. A claim without a file name is
not a claim.

## Authentication

- **Admin passwords are HASHED** — `password_hash()`/`password_verify()`
  (PASSWORD_DEFAULT, rehash-on-login), never encrypted, never reversible.
  `lib/password.php`. The installer runs the same rules (`install.php`
  defers to `cm_password_problem`).
- **One answer for every sign-in failure** — no account / wrong password /
  closed account are indistinguishable (`account/login.php`), and password
  reset always says "if that address exists" (`account/forgot.php`).
  A Google-less port kept the timing guard: a missing hash burns a dummy
  `password_verify` (`cm_password_verify_account`).
- **Rate limits on the caller, not the account** for sign-in: refused per
  (address + email) at 8/15min and per address at 30/15min, IPv6 counted per
  /64, with a Retry-After header; the count per email alone only slows tries
  down, so nobody can lock the account holder out by knowing the address (0.3.17+).
  Reset requests the same way (4/hour per address + email, 15 per address,
  a high ceiling of 20 per email). Also 2FA attempts (keyed on the ACCOUNT — the attacker chooses
  the IP, not the account) and credential reveals. One writer, one reader,
  one row shape (`rate_hit`); the smoke suite proves the counter counts what
  the recorder records.
- **Two-step sign-in**: TOTP (RFC 6238, QR drawn LOCALLY — the secret never
  leaves the server; `lib/qr.php`) or emailed codes, ten single-use hashed
  backup codes. The gate lives INSIDE `cm_login()`, so no route — password,
  reset link, dev sign-in — can forget it. TOTP replay is refused
  (`tfa_last_step`); enrolment stores nothing until a typed code matches.
  New backup codes need the password and a current code; a second method
  cannot be enrolled over the first; the turn-off and new-codes boxes share a
  try limit (0.3.17+).
- **Remember-me**: split selector/validator token, validator stored as
  SHA-256 and compared constant-time, single-use with rotation, never
  self-extending, revoked wholesale on password change/reset, all tokens
  burned on a validator mismatch (the stolen-cookie signature).
  `lib/remember.php`. The sign-in form says what the trade costs.
- **Idle timeout**: 12 hours (`CM_IDLE_SECONDS`, `lib/auth.php`) — a vault
  must not stay open on an abandoned browser. A remembered device signs
  straight back in, so the timeout costs nothing it should not.
- **Session**: regenerated id on every privilege change (login, 2FA
  completion), `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, one cookie
  (`cm_session`). A password change or reset, or turning two-step on or off,
  ends every OTHER open session on its next request (`cm_credential_mark()`,
  0.3.17+); the browser that made the change stays signed in.
- **Dev sign-in** is triple-gated: config flag AND localhost AND env=dev
  (`cm_dev_login_allowed`) — a config file copied to a live host with the
  flag still on opens nothing.

## Authorisation

- Every admin screen calls `cm_require_admin()`; strangers are redirected to
  sign-in, and `admin/update.php`, `admin/backup.php` and `admin/restore.php`
  keep the stricter 404-or-recovery-key guard (`lib/recovery.php` — key ≥32
  chars, file-only, `hash_equals`, opens exactly those three screens, every use
  audited by path, never with the key itself).
- `admin_emails` in config.php is the permanent root of admin access; the
  `account.role` column is reserved for future revocable grants.
- Object access is scoped server-side: contacts by `(id, client_id)`, job
  assets validated against the JOB'S client (`cm_job_assets_set`),
  occurrences checked against their job before any write.

## Stored client credentials — the crown jewels

- **AES-256-GCM** (authenticated — tampered ciphertext decrypts to nothing,
  never to garbage), key derived from config.php's `secret_key` or
  `storage/appkey.php`, **never stored in the database**. A stolen dump
  decrypts to nothing; the smoke suite dumps the whole credential table and
  greps for the plaintext to prove it. `lib/settings.php`.
- The key file is a `.php` that RETURNS the key: requested directly it
  executes and prints nothing, so it cannot leak even where `.htaccess` is
  ignored.
- **Reveal** is a POST-only JSON endpoint (`admin/credential.php`, 405 to
  anything else), admin plus the `X-CM-CSRF` header, rate-limited at 60
  reads per 15 minutes per admin, writes `credential.viewed` to the audit
  log BEFORE the value is decrypted, answers `no-store`, and refusals are
  audited too. There is no re-authentication (see the note at the
  top); the audit row carries the weight.
- Credentials appear in **no search** (`admin/search.php` never queries the
  table), no email, no summary (`admin/summary.php`'s generator has no path
  to them), no list, no audit note.

## Injection and output

- **SQL**: PDO with real prepares (`EMULATE_PREPARES` off), parameterised
  everywhere; the only interpolations are integer-cast LIMIT/OFFSET and
  whitelist-validated column/sort names.
- **XSS**: every dynamic output goes through `e()` (ENT_QUOTES). Checked by
  search: every `<?=` without `e()` is a static ternary or an integer cast.
- **CSRF**: one session token; every state-changing POST checks
  `cm_require_csrf()` (grep-audited — no handler without it). The installer,
  which has no session, uses a claim token double-submitted via cookie AND
  disk file.
- **Mail header injection**: recipient and header values are stripped of
  CR/LF/NUL (`cm_mail_header_safe`); non-ASCII names/subjects are RFC-2047
  encoded.
- **Open redirects**: `cm_safe_return()` accepts only plain same-site paths
  (rejects `//`, `/\`, schemes, header-splitting characters).

## Files and hosting — the self-hosted reality

- **Subfolder-safe guards**: `.htaccess` patterns match anywhere in the
  path (`(^|/)lib(/|$)` …), because `^/lib` silently never matches when the
  app lives at `/manager/`. The installer redirect in
  bootstrap derives the app's own root from the request instead of assuming
  the domain root.
- **Belt and braces**: every `lib/` file refuses inclusion-less requests
  (`CM_ROOT` guard); `bootstrap.php` refuses being the requested script
  itself; `tests/` and `migrations/` are CLI-only in code; `storage/`
  carries its own deny file, written by the installer AND recreated on
  demand. None of this depends on `.htaccess` being honoured.
- **`config.php` / MANIFEST / docs / .sql / .bat denied over HTTP** by
  FilesMatch (filename-based, path-independent).
- **The installer** self-deletes on success, answers 404 on an installed
  site (checked FIRST, before anything runs or writes), warns loudly when
  the delete fails, and the health check + nav badge + Cleanup button hunt
  down a leftover copy. The install claim makes a race visible and gives
  the file-manager owner the winning move.
- **The encrypted backup** (`admin/seal-key.php` → `admin/backup.php`) is
  AES-256-GCM under a key of four words drawn from a 2,048-word list — 2^44
  keys — stretched through 600,000 rounds of PBKDF2-SHA256. The key is
  generated by the server, shown once, and **stored nowhere**: not in the
  database, not in the audit log, not even as a hash, because a stored hash of
  a key is an offline oracle. It is held in the session only between the screen
  that shows it and the download, and forgotten before the first byte is sent,
  so one key seals exactly one file.

  The payload is framed — 1 MiB per frame, each its own GCM message — so the
  dump never has to fit in memory, and each frame's additional data binds the
  header, the frame's index and whether it is the last. A truncated or
  reordered file therefore refuses to open rather than restoring part of a
  database. There is **no recovery**: lose the four words and the file is noise.

  Because the file is opaque, it is also the only backup that can safely carry
  the settings encryption key, and it does. A restore without it brings back
  every stored PayPal, Stripe, Google and mail credential as an empty string,
  silently.

- **The restore** (`admin/restore.php`) is full-admin, POST + CSRF, needs the
  word RESTORE typed, and refuses until a backup has been taken in that sitting.
  It reads the whole file through before executing one statement: a file that
  does not end with the marker every finished backup ends with is rejected as
  incomplete, and a backup from a newer schema than the running files is
  rejected outright because migrations do not run backwards.

- **The backup** is POST-only + CSRF (never a GET a proxy logs or a browser
  prefetches), streamed (no copy ever written server-side), audited, and its
  "taken" flag is set only after the last byte — a truncated download
  unlocks nothing.
- **A backup leaves the clients' stored passwords behind unless you ask for
  them** (0.3.16+). `password_enc`, `notes_enc` and `recovery_enc` on
  `client_credential` are written as NULL unless *Include my clients' stored
  passwords in the download* is ticked — all three, because a recovery code
  opens the same account a password does and the secret note is where the PIN
  gets written. The header line `-- passwords: not included` travels in the
  file, the restore screen reads it back, and restoring such a file onto the
  site keeps the passwords the site already holds (same login, same client;
  0.3.17+). The audit row records which kind was taken. The default is the safe one in code
  as well as on screen: `cm_backup_stream()`'s third argument defaults to
  false, so a caller that forgets produces the redacted file.
  `cm_backup_secret_columns()` is the one list, and `tests/backup_restore.php`
  fails if a new `_enc` column on that table is not on it.
- **Updates** (0.3.7+): a release applied from Admin → Update is verified
  three times (inside the zip, again from the staged copy on disk, again at
  the moment of apply); `config.php`, `storage/`, `install.php` and any
  file you added are never written or removed — deletions come only from
  the difference between the old release's manifest and the new one's. An update **uploaded** by hand is refused unless
  its `MANIFEST.sig` (inside the zip, since 0.3.16) verifies against the
  Ed25519 public key baked into this code (`CM_UPDATE_PUBKEY` in
  `lib/updater.php`); the signing key never leaves the developer's machine — with two stated exceptions: a host whose PHP has no sodium
  cannot check signatures at all, and says so on screen when it accepts an
  upload on its checksums alone; and `'update.allow_unsigned_upload' =>
  true` in `config.php` turns the check off for someone running their own
  build. **The site never checks for updates on its own** (from 0.3.19):
  the old feed channel is off unless `config.php` names a feed
  (`'update.feed'`). From 0.3.22 an admin can press **Check for updates**,
  which makes one GET to GitHub's API for the latest release of
  `Matthew4509/clientworkhub` (the address is fixed in code; redirects are
  followed only while they stay on GitHub's hosts) and downloads the
  `clientworkhub-x.y.z.zip` it names into the update folder, where it goes
  through exactly the checks an upload does. A feed, if one is configured, must carry
  releases signed with the same key, and the version inside the signed zip
  must be the version the feed named.

**The Windows home edition** (0.3.20, `'edition' => 'home'`): it answers only
on 127.0.0.1 (the launcher binds there, and the application refuses any other
peer) and, from 0.3.21, only to a `Host` of `127.0.0.1`, `localhost` or `[::1]`,
so a web page whose name has been re-pointed at this PC (DNS rebinding) is
refused; its router matches the decoded path, so an encoded or trailing-dot
spelling of a hidden file is hidden too. Its `php.ini` disables running other
programs and opening URLs as files. An in-app update replaces the application,
never the bundled PHP and MariaDB: a release with a newer runtime is installed
by running the new home download's installer again (data is kept), and the
health check warns while the PHP is older than `CM_HOME_MIN_PHP`. Everything
that reaches a client or the internet answers 404 on the
server, and email is refused rather than spooled. Its **Check for updates**
asks the GitHub Releases API only when pressed, follows redirects only on
GitHub's own hosts, and treats the download exactly as an upload: checksums,
the release signature, and the version inside the zip must match the one in
its name. The previous release is kept so it can be put back. Its data folder
(`%APPDATA%\Client Work Hub`) holds `config.php` and a random database root
password; they sit in that Windows user's profile, which other standard users
on the PC cannot read (administrators can, as with any file on the PC).

## Headers

Sent by PHP on every page (`cm_send_security_headers`), so the protection
travels with the application, not the server config: CSP (`default-src
'self'`, no inline script, `frame-ancestors 'none'`, `object-src 'none'`,
`base-uri 'none'`; **no third party at all** — Inter and IBM Plex Mono
are self-hosted in `css/fonts/`, so `style-src` and `font-src` are plain
`'self'`), nosniff,
Referrer-Policy, X-Frame-Options, HSTS on HTTPS, Permissions-Policy denying
geolocation/camera/microphone/payment. Every page is `noindex`. Pages that
can carry a revealed secret are `no-store`.

## Audit log

Append-only by construction — no screen edits or deletes rows. Records
logins-by-2FA, reveals and refusals, credential changes (slot names, never
values), manual time adjustments (before → after), completions, waivers,
status changes, settings saves, recovery-key use. Never a password, secret,
or code.

## Known limits — stated, not hidden

1. **The window between upload and install is unclaimed** — whoever runs
   the installer first owns the site. INSTALL.md says in bold: install
   immediately after uploading. No single PHP file can close this.
2. **`trust_proxy` defaults to false**; behind Cloudflare the operator must
   set it or rate limits key on the proxy's address.
3. **Email is unauthenticated plain `mail()`** — deliverability depends on
   the host's SPF; the settings screen says so.
4. **A plain backup is not encrypted** — the download itself is the operator's
   to protect; credential fields inside it are encrypted with a key kept
   outside the database, and since 0.3.16 they are left out of it altogether
   unless the operator ticks the box. The *encrypted* backup (above) closes
   this, and the plain one stays because it is the only copy that can be
   restored without this software running.
5. **`secret_key` is trusted at whatever length it is given.** The installer
   writes 64 hex characters; a value typed into `config.php` by hand is
   accepted however short, because refusing it would send the application off
   to make a new key and silently blank every secret already encrypted with
   the old one. Settings → Update reports anything under 32 characters as a
   warning, with the order the change has to be made in.
6. **Single admin by design today** — no per-user permissions until the
   role system is built on the reserved column.
