# What Client Work Hub does

Three sections, easiest first. **The core is what you will use every day** —
everything after it is there when you want it and switched off until then.

- [The core — add a client, add a job, add a property](#the-core)
- [Intermediate — your time, and how work is scheduled](#intermediate--your-time-and-how-work-is-scheduled)
- [Advanced — money, email, and your clients' own page](#advanced--money-email-and-your-clients-own-page)
- [The full list](#the-full-list)

---

# The core

**Ninety per cent of using this application is: add a client, add a job, add a
property, do the work, know where the day went.** Everything is built around
making those fast, and nothing in the later sections is required to do them.

## Clients

The person or business you work for.

- **Add one from anywhere** — "+ New" is in the header on every screen, and it
  opens **a window over the page you are on**. Cancel and you are exactly
  where you were, with nothing lost.
- **Five short steps, one of them required.** Who they are · where they stand ·
  how to reach them · where they are · anything worth remembering. A
  half-filled client is a real client; come back to the rest whenever.
- **Standing instructions.** The permanent rules for this client —
  *prefers 500-word articles, not 300*; *never post before 9am*. They follow
  their work around and appear wherever it is being done, and **you can add to
  them from inside a task without leaving the task**, which is the moment you
  actually learn them. They only ever get added to, never replaced, so a year
  of accumulated rules cannot be wiped by one careless save.
- **Contacts** — more than one person at a business.
- **A conversation record.** Emails the application sent and calls you logged
  yourself, merged into one history per client.
- **VIP.** A flag that can push all of a client's work up the queue.

## Jobs

A piece of work for a client: a value, a start, a length, and the daily tasks
it generates.

- **Three steps**: who it is for · what the work is · when it runs.
- **It will not fight you.** Leave the name empty and the job names itself.
  Leave the date empty and it starts today. Nothing is refused over something
  the application could work out — and if it does have to stop and ask, **every
  word you typed is still there** when it does.
- **The brief.** Paste what the client sent — keywords, targets, links, the
  lot — at the moment it arrives. It stays on the job and shows wherever the
  work is done, so you are never hunting through email for it. You can add to
  it later from inside the task.
- **A live target completion date** while you are still filling the form, so
  you can promise a date before you have committed to anything.
- **Daily, weekly or monthly actions.** A job generates the tasks that appear
  on your dashboard on the right days.
- **Status that means something**: active, waiting on the client, paused,
  completed, cancelled — with a window that asks *why* and *until when* rather
  than a button that silently changes a word.
- **The jobs list leads with the property, not the service.** Five services
  across a hundred jobs would otherwise give you a list whose boldest column is
  one of five strings — a category printed a hundred times, not a name. Search
  matches property names too, because that is the word that will be in your
  head.

## Properties — and the password vault

A **property** is a thing of theirs you work *on*: a website, a Facebook page,
a domain registrar, a Google Business listing, a hosting account.

- **Add one in a window**, from the client's page or from inside the job
  creation flow — where **+ New property** creates it and ticks it onto the job
  in a single save.
- **Each one holds its address, what it is, and its sign-in.**
- **Stored passwords are encrypted** with a key that lives in a file on your
  server and **never in the database**, so a stolen database dump decrypts to
  nothing.
- **They are shown where you are working** — on the job, on the task, and on
  the dashboard's work card: the address, a link that opens it, the username
  with a Copy button, and the password one deliberate click away.
- **Every reveal is written to the audit log** before it happens, is rate
  limited, and never appears in any summary, any export or any email.
- Full detail is in `SECURITY.md`, and it is worth reading once before you put
  a real client password in.

## Your day

- **The dashboard is the day.** What is running, what is next, and one door
  into it. It is deliberately not a second place to do the work.
- **A red banner when a timer is running**, from anywhere in the application,
  with one click back to it.
- **The task screen** is where work actually happens: who it is for, what you
  already did, the schedule, the sign-ins, and somewhere to write down what you
  learn.
- **Time tracking** — start, pause and finish, with pause never throwing you
  off the page you are on.
- **Workflow** — everything that needs doing, in one list, when you want the
  whole picture instead of today's slice.
- **Snippets** — text you send often, kept and inserted in a click.
- **Search**, across clients, jobs and properties.
- **A Help button in the corner of every screen** — a short walkthrough of the
  screen you are on, not a manual. It never opens itself, and Settings → Help
  turns it off.

---

# Intermediate — your time, and how work is scheduled

All optional. With none of it set, tasks land on every day and the application
never has an opinion about your capacity.

## Your workday

- **Named working days with any number of breaks.** Not one start and one end
  — a real week: *7:00–8:45, school run, flexible lunch, school pickup, family
  from 5, an hour of wrap-up at 7*. A Saturday that is eight hours with a
  30-minute lunch, and a Sunday that is two hours twice.
- **Days you do not cover have no stated hours**, which is exactly what an
  install that has set nothing has.
- With days set, **daily tasks land only on days you work**. With times set,
  the dashboard can tell you what fits before your next appointment or the end
  of the day.

## Appointments

Recurring commitments that are not client work — the school run, a standing
call. They occupy the day so the plan does not pretend those hours are free.

## What you do first

**You number the factors and the queue obeys.** Whatever is 1 is offered
first:

| | |
|---|---|
| **Overdue** | Past its date. Late work stays late until it is done. |
| **VIP clients** | Everything for a client marked VIP, ahead of everyone else. |
| **Finished, not handed back** | The tasks are done and the client has not been told. Nothing else chases you for this, and they are waiting. |
| **Job priority** | The critical / high / normal / low set on the job itself. |
| **Due soonest** | Then simply what is due first — always last, because something has to break the tie. |

**Nothing is ever refused because of this order.** A short Saturday does the
important things first; it does not turn work away.

## Workload — the only setting that can refuse work

Off by default, and that is the right setting for most people: refusing paid
work because an *estimate* says a day is full costs real money to protect a
guess.

| | |
|---|---|
| **Take everything** | No limits. Every date offered is the nominal one and nothing is ever refused. **The default, and it suits most people.** |
| **Take everything, but quote honest dates** | Still never refuses. When the days are full the start slides later or the delivery stretches — a seven-day package might be quoted as fourteen, and the client is told before they buy. |
| **Stop taking work when full** | Refuses bookings that will not fit. **The one setting here that can cost you a sale.** |

## Overtime

- **An allowance of extra minutes a day.** A 9-to-5 that looks full can still
  fit eight half-hour jobs, and that is a lot of money to decline over
  arithmetic.
- **"Overtime makes the day longer"** turns the allowance into real hours: a
  9-to-5 with four hours allowed reads 9am to 9pm, work is planned into those
  hours **before any date is pushed back**, and it is marked as overtime where
  it lands there.

## Reminders and follow-ups

- **Set a reminder** about anything, from anywhere.
- **Repeat daily, weekly, monthly or yearly**, and snooze.
- **Client follow-ups** are scheduled emails — the chase after a job is
  finished. See the next section for what goes in them.

---

# Advanced — money, email, and your clients' own page

## Email to your clients

**Off until you turn it on** (Settings → Email). While it is off, everything
the application would send is written to a folder you can read instead, so
nothing escapes while you are finding your way around.

- **Four messages you can reword**: the summary at the end of a job, the
  follow-up, the password reset, and the sign-in code. The last two are locked
  down for the obvious reason.
- **Merge codes** — the client's name, their first name, the job, your business
  name, the summary, and the link to their own page.
- **A record of everything sent**, per client and in one list, with the body as
  it went out.
- **Unsubscribe is absolute.** One click stops **everything** — follow-ups,
  summaries, invoices, receipts. There is no re-subscribe link and there never
  will be: anything reachable by a click is reachable by a mail client that
  prefetches links, and a mail scanner must not be able to switch somebody's
  email back on. Only a phone call and an admin can undo it, and while it is on
  every tab of that client's file says so in red.
- **The unsubscribe page confirms rather than acting on arrival**, for the same
  reason — otherwise a corporate link scanner sweeping one email could stop the
  mail for a whole client list in a single pass.
- **Sending is capped at twenty messages a run**, oldest first, which is what
  keeps you under a shared host's outbound mail limit. See `SETUP.md` §9.

## The rebooking hook

**This is a core feature, and it is the one that brings work back without you
asking for it.**

Every follow-up and every end-of-job summary you send can carry **one link that
belongs to that client**. They click it and they are on their own page, with no
password, no account, and nothing to remember.

### What they can do there

1. **Tell you how it went.** The feedback box is first on purpose. An unhappy
   client who has told you *why* is the most answerable problem in the
   business — *"too low quality"* is the opening of a conversation that ends in
   a premium quote, and burying it under three buy buttons is how you never
   hear it. **Nothing they write is ever published.** There is no rating, no
   stars, and nowhere for it to go but to you.
2. **Book the same thing again, in one click.**
3. **Book something else** from their own history, each line with a button on
   it.

You see the visit on your side, so **a returning client shows up as a returning
client** rather than as a stranger.

### How the link works, and why it is safe to email

The address looks like this:

```
https://yoursite.com/clientworkhub/my.php?k=9f2c…64 characters…a1
```

**That key is 64 characters of pure randomness — 32 random bytes — and it is
not made out of anything about the client.** It is not their id encoded, not
their email scrambled, not a number with a hash on the end. There is nothing
in it to decode, because nothing about them was put in.

**Anyone holding that URL is treated as that client.** That is a deliberate
trade — it is what makes rebooking one click instead of a signup — and it is
only acceptable because of what the page is allowed to show:

| Shown | Not shown |
|---|---|
| Their **first name** (or their company, or "there") | Surname · address · phone · email |
| The **services they bought from you** — names, prices, dates | Notes, standing instructions, contacts |
| Buttons to book again | **Anything at all from the password vault** |

**One function in the code decides that list**, so the answer cannot drift from
one page to another as things get added. Treat the URL as public: a forwarded
email, a shared screen or a link in a browser history hands over a first name
and a list of what they already bought — which they already knew — and nothing
else.

**And in your database it is stored twice, on purpose:** once as a one-way hash
that cannot be turned back into a link, which is what a visit is checked
against; and once encrypted, so *you* can show the link again when a client
asks. A stolen database dump therefore gives an attacker **neither a working
link nor a readable one** — the hash cannot be reversed, and the key that would
decrypt the other copy is not in the database at all.

**You can revoke a link at any time.** A revoked or invented key says exactly
the same thing as a wrong one — *that link has expired, get in touch* — so
nobody can learn anything by guessing.

### What it needs to be switched on

**The rebooking link only works while the billing portal is on** (Settings →
"Your basic billing portal"). With the portal off there is no public surface
for the link to live on, and the merge code in your emails comes out empty
rather than broken.

If rebooking is what you want and a shop window is not, turn the portal on and
set **"When somebody arrives without a service link"** to **Show nothing at
all**. Rebooking links keep working; the portal's front page shows nobody
anything.

## Invoicing and receipts

- **Your business identity** — name, address, contact — as it appears on a
  financial document. Often not the name on your shop window; both exist and
  both are used.
- **One configurable tax**, named and rated by you — GST, VAT, sales tax,
  whatever your invoices should say — **applied on top of your prices or
  already included in them**. Nothing infers a mode from a country or a
  currency, because plenty of people want the other one.
- **Draft, then issue.** The invoice number is allocated when you issue it, so
  an abandoned draft leaves no gap in your numbering. Voiding keeps its number.
- **An invoice and a receipt are the same document read at two moments** —
  there is no separate receipt to generate or lose.
- **Record payments** against an invoice, in full or in part.
- **A printable document**, and **the client's own copy** at a link of its own —
  rendered by the same code, so the two cannot drift apart.
- **An issued invoice carries its own tax snapshot**, so changing your rate next
  year does not silently rewrite last year's paperwork.
- **Bank details** for the footer, with the settings screen telling you plainly
  when a half-filled set would print nothing.

## The billing portal — your public page

Off by default, and most installs leave it off. Switched on it is the billing
and signup side of a business whose real website is somewhere else.

- **Your published services**, with a featured row.
- **A direct link per service**, with a Copy button — the URL you put behind a
  Buy button on your real site.
- **A booking and enquiry form** that never creates a client or a job on its
  own. Enquiries land on the **Bookings** screen, where **you** turn one into a
  client and a job.
- **Your logo and business name.** PNG or JPEG; SVG is refused because an SVG
  can carry a script and it would be served from your own domain.
- **You choose what a stranger with no service link sees**: your service list,
  a redirect to your real website, or nothing at all. **A direct service link
  works regardless**, which is the entire point.
- **There is deliberately no sign-in link on it.**
- Setting it up: `SETUP.md` §9.

## Onboarding

**What you ask a client for before you can start.** A form of your own design,
per service, sent as a link — it collects their answers, takes their properties
and seals any passwords straight into the vault on arrival.

## Reports

Where the money actually is: **clients by effective rate, lowest first** — what
you are really earning per hour once tracked time is set against what you
charged. It is usually not the client you expected.

## Running it safely

- **Two-step sign-in**, with ten backup codes.
- **An append-only audit log** of every security-sensitive action, newest
  first. No screen edits or deletes it, ever.
- **Rate limiting** on sign-in and on every credential reveal.
- **A health screen** that answers, from inside the site, every question a
  technician would answer from a command line: PHP version, whether encryption
  works, whether every file arrived intact, whether the database is current.
- **One-click database backup**, streamed, and **one-click updates** that
  refuse to run until you have taken one. **Your clients' stored passwords
  stay out of it unless you tick the box** — the saved password, the secret
  note and the recovery code on every login, so a backup you email to
  yourself or leave in Downloads is not the vault. The file says which kind
  it is, and the restore screen warns you before putting a redacted one back.
- **Encrypted backups.** The same backup, sealed with AES-256 under a key of
  four ordinary English words the site shows you once and never stores. The
  file is unreadable to anyone without those words - including us, and
  including you, so write them down. Unlike a plain backup it also carries your
  stored PayPal, Stripe, Google and mail passwords, so a restore brings them
  back working instead of blank.
- **Restore.** Put a backup back from inside the site: upload it (or drop a
  large one in `storage/restore/`), read what is in it - when it was taken, from
  which site, how many tables - and then replace the database with it. It reads
  the whole file before it changes anything, so an incomplete download is
  refused rather than half-restored, and it runs any database updates the
  backup was missing straight afterwards.
- **Demo mode** — fifty invented clients to look around with, read-only, and a
  wipe that can only ever delete what it created.

---

# The full list

| | |
|---|---|
| **Clients** | Add in a window from any screen · five steps, one required · standing instructions that only ever grow · contacts · merged email and call history · VIP · search |
| **Jobs** | Three steps · names and dates itself · the brief · live target completion date · daily/weekly/monthly actions · priority · active, waiting, paused, completed, cancelled · lists led by the property · one-click flow from new client → new job → its properties |
| **Properties** | Websites, social accounts, registrars, hosting · address, type, sign-in · encrypted passwords, key never in the database · shown where you work · Copy and reveal, every reveal audited and rate limited |
| **Your day** | Dashboard · running-timer banner from anywhere · task screen · start/pause/finish · pause never leaves the page · workflow list · snippets |
| **Help** | A button in the corner of every screen · a walkthrough of the screen you are on for the dashboard, a client, a job and a task, and a general tour everywhere else · never opens itself · one off switch in Settings |
| **Scheduling** | Named workdays with any number of breaks · recurring appointments · a priority order you number yourself · four workload modes · overtime allowance that can lengthen the day · reminders that repeat and snooze |
| **Services** | Sell the same work twice — price, duration, tasks, publishing, onboarding · stamp a job from one in two clicks |
| **Email** | Four rewordable messages · merge codes · a record of everything sent · absolute unsubscribe enforced in one place · a confirm-first unsubscribe page · a cap of twenty a run · optional cron |
| **Rebooking hook** | One link per client in your emails · feedback, re-order, history · 64 random characters carrying nothing about them · first name and past purchases only · hashed for lookup and encrypted for display · revocable · visits counted |
| **Money** | Business identity · one configurable tax, inclusive or exclusive · drafts · numbers allocated at issue · void keeps its number · payments · printable · the client's own copy · per-invoice tax snapshot · bank details |
| **Billing portal** | Published and featured services · direct link per service · booking and enquiry form · bookings screen · logo and business name · three answers for arriving with no link · no sign-in link |
| **Onboarding** | Per-service forms · collects properties · seals passwords into the vault on arrival |
| **Reports** | Clients by effective rate, lowest first |
| **Safety** | Two-step sign-in with backup codes · append-only audit log · rate limiting · health screen · streamed backups, plain or encrypted · restore from either · updates that require a backup first · demo mode |
| **Hosting** | Plain PHP and MySQL · no composer, no build step · installs at a domain root, in a subfolder, or on a subdomain · one-form installer that deletes itself |

---

**Setting any of it up:** `SETUP.md`.
**How the vault is protected:** `SECURITY.md`.
