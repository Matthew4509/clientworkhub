# Third-party components

Client Work Hub itself is under the PolyForm Shield License 1.0.0 — see
`LICENSE`. Three things inside it are other people's work under their own
licences, and **this project's licence does not cover them**. If you fork this, these travel with it on
their own terms.

There is no package manager here. Everything below is vendored: the actual
bytes are in this repository, unmodified, which is why it installs by upload
and runs with no build step.

| Component | Where | Licence |
|---|---|---|
| **jsPDF** 2.x | `js/jspdf.umd.min.js` | MIT — © 2010-2021 James Hall and contributors, <https://github.com/parallax/jsPDF> |
| **Inter** (variable + static weights) | `css/fonts/inter-*.woff2` | SIL Open Font License 1.1 — © The Inter Project Authors, <https://github.com/rsms/inter> |
| **IBM Plex Mono** 400/500/600 | `css/fonts/ibmplexmono-*.woff2` | SIL Open Font License 1.1 — © IBM Corp., <https://github.com/IBM/plex> |

**The BIP-39 English wordlist** in `lib/words.php` — the 2,048 words a sealed
backup's key is drawn from — is from BIP-0039, which is in the public domain
(the BIP itself is CC0 / public domain). It is reproduced unaltered and must
stay unaltered: a list with one word changed cannot open a backup made with
the original.

## Why the fonts are here at all

They used to be served from Google Fonts, which meant every
visitor's IP address reached a third party on every page — **including the
public ones, where the visitor is a stranger who never agreed to anything.**
Self-hosting closed that. Latin and latin-ext only: 214 KB across eight
files, which is less than one of the tracking scripts this application does
not have either.

The Open Font License permits this — bundling and redistributing the font
files with software is exactly what it is for. It asks two things, and both
are met here: the fonts are not sold on their own, and they keep their
names because they are unmodified.
