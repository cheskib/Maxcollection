# Decisions Log

A record of approved architectural and product decisions made during development.
Requirements documents (README, PROJECT, ARCHITECTURE, CLAUDE, PHASE_1) are never modified; intentional decisions and deviations are recorded here instead.

---

## 2026-07-24 — Replace initial prototype

The repository previously contained an unrelated Node.js prototype built before the project documentation existed. It was deleted entirely and replaced with the Laravel 12 application defined by the documentation. Nothing was preserved.

## 2026-07-24 — OpenAI model selection

Version 0.1 uses `gpt-4.1` as the single OpenAI model. No multi-model support, no provider abstraction. All AI interaction goes through one service class. The API key is provided at Milestone 7 and lives only in `.env`.

## 2026-07-24 — Milestone 4/5 table split

Milestone 4 (Capture Item) requires the `items` and `images` tables, which PHASE_1 originally assigned to Milestone 5. Approved change: Milestone 4 creates `items` and `images` plus image capture; Milestone 5 creates the remaining tables (`metadata`, `metadata_history`, `processing_jobs`, `processing_logs`, `settings`) and completes relationships and migrations. This is the only approved deviation from the milestone sequence.

## 2026-07-24 — Settings screen deferred

No Settings page is built during Phase 1. The Home screen's Settings button is disabled or leads to a simple "Coming Soon" page. No settings functionality is required for the MVP.

## 2026-07-24 — Raw AI responses stored verbatim

Every OpenAI response is stored exactly as returned, before any parsing or modification, to support future debugging and prompt improvement.

## 2026-07-24 — Queue infrastructure kept minimal

Laravel Queue only (database driver for local development). No Horizon, Redis, Supervisor configuration, or queue monitoring dashboards during the MVP.

## 2026-07-24 — Metadata stored as explicit columns

Category metadata lives in explicit nullable columns on the `metadata` table (one row per item), not a JSON blob. Fields shared between categories (year, country, denomination, condition notes) are stored once. This keeps the schema self-documenting and makes keyword search plain SQL. Proposed in the Milestone 5 report and approved with the continuation of Milestones 6–13.

## 2026-07-24 — Accepted upload formats

Uploads accept JPG, PNG, and WebP up to 20 MB per photo. The PRD requires "only supported image formats" without listing them; this set was flagged in the Milestone 4 report and carried forward. HEIC can be added later if iPhone captures require it.

## 2026-07-24 — "Missing required metadata" review rule

PROJECT.md section 17 lists "Missing required metadata" as a Needs Review reason without defining required fields. Implemented as: an item needs review when every primary identifying field for its category is blank (sports card: player name; comic book: title; coin: country/denomination; stamp: country/issue name).

## 2026-07-26 — Edit form input refinements (owner-directed)

From first production use: the Edit screen shows the item's photographs; Sport is a fixed dropdown; Rookie Card and Autograph are Yes/No dropdowns (owner narrowed Rookie Card from the PRD's Yes/No/Unknown — the empty option covers "undetermined"); Year is a dropdown (current year back to 1900) for sports cards only; Team and Manufacturer suggest known values while allowing free typing. Franchise-founding-based year ranges are deferred until a verified data source is added.

## 2026-07-26 — Queue worker runs inside the web container

Railway volumes attach to a single service, so a separate worker service cannot read uploaded photographs (diagnosed in production: the AI received text-only requests). The entrypoint runs `queue:work` in the background of the web container instead.

## 2026-07-26 — Bulk capture added (owner-requested)

The owner requested two capture approaches: one item at a time (unchanged) and bulk. Bulk Capture creates one item per group of photos, with a session toggle for 1 or 2 photos per item (default 2, front/back) — grouping happens client-side so the server stays simple. Photos can come from the camera in rhythm or from a multi-select file picker. An odd leftover photo can be finished as a single-photo item. PHP upload limits raised to 25M per file for phone photographs.

## 2026-07-26 — Scanner PDF import (owner-requested)

The owner scans cards one at a time, producing PDFs whose pages alternate front, back, front, back. Bulk Capture accepts a PDF upload: pages are rendered to JPEG (poppler, 200 dpi) in the background queue and grouped by the same 1-or-2 photos-per-item setting into ordinary captured items. The uploaded PDF and working files are deleted after import.

## 2026-07-28 — Capture Station approved; physical storage foundation

The Capture Station architecture (CAPTURE-STATION.md) was approved with all
assumptions resolved and ten additional owner requirements. Milestone 1
implements the Laravel side: a pre-registered barcode registry (`barcodes`,
the source of truth — all objects reference it by id), printable Code 128
label sheets, the barcode-driven packing workflow (box → bags → divider,
one open box per user), undo with double-read protection, a storage audit
trail (`storage_events`), and batch finalization by bag barcode
(`finalized_at` distinct from the future Dropbox `archived_at`). Physical
location lives only in storage tables; metadata never changes because a
card moves.

## 2026-07-28 — Terminology: divider cards, not category cards

Owner correction after Milestone 1: the physical section marker is a
"divider card" — there is no such thing as a "category card". The barcode
prefix is `DIV-` (was `CAT-`), the registry type is `divider`, all screens
say "Divider", and `storage_sections.category_barcode_id` was renamed to
`divider_barcode_id`. Renamed before any labels were printed, so no
physical labels are affected. Follow-up ruling: divider labels are
generated by quantity like bags and boxes; names are optional decoration —
a divider only marks a location inside a box (each box holds ~3,000 cards)
and its ID is fully independent of any card category.

## 2026-07-28 — Comic books: front cover only, bagged like cards

Owner ruling: comic books are photographed **front cover only** — one photo
per comic, via Bulk Capture with the photos-per-item toggle set to 1. Comic
backs are usually advertisements and carry no identifying information; the
front cover holds everything the AI needs (title, issue number, publisher,
cover date). Comic batches then flow through the identical bag → box →
divider storage workflow as cards; the storage system is agnostic to what
a bag contains. No code change — the 1-photo bulk mode already exists.

## 2026-07-29 — Removals, roles, and daily reports (owner-directed)

Cards leave bags for documented reasons only, and only administrators can
do it. Principles set by the owner: the digital record is never deleted
(removal is a disposition change with a full audit trail); boxes are NOT
sealed — contents adjust as cards sell, and every screen shows what a
container holds NOW (history available on request); reinstatement reuses
the original record — the card can go into any box without rescanning.
Dispositions: null (in its bag), `relocated` (still owned — moved to
safer storage / grading / damaged; still counts in collection value),
`gone` (sold / gift / lost; out of totals, sale details recorded).
Accounts have roles: `admin` (everything) and `scanner` (digitize and
pack only — no removals, reports, settings, exports, or deletions).
Built-in admin Reports page: live pipeline (capturing → review →
processed → bagged → boxed) plus a 14-day daily ledger of all inbound
and outbound activity, computed live so the numbers always reflect the
current state.

## 2026-07-29 — Two capture production systems (owner-directed)

Exactly two production systems, both for raw items only (never bagged,
never graded): comics go through the panel picture station (six bare
comics per ~$7 panel, front cover only, fixed overhead camera; every
frame contains the comics, the bag barcode, and the panel number, so
the photograph is its own manifest — software crops and pre-binds the
batch to the bag); cards go through the fi-8170 scanner (front + back,
duplex). Panels are not used for cards. Full spec in
CAPTURE-STATION.md "The Comic Imaging Line".

## 2026-07-29 — Card line separator stickers (owner-directed)

Card batches are prepped in circulating open-front bins (50–70 raw
cards + one unpeeled bag sticker on a stiff carrier ticket). The
ticket feeds through the fi-8170 FIRST: the system recognizes the
BAG barcode in the scanned image, creates no item from it, and opens
a batch pre-bound to that bag — so stickers slice a continuous feed
into batches with zero screen interaction. Bag binding therefore
happens at the START of a card batch; bagging is purely physical
(peel sticker onto bag, cards in). Full spec in CAPTURE-STATION.md.

## 2026-07-29 — Production kiosks; hardware-per-line final ruling

Each capture line gets a thin touchscreen kiosk (prompts + big
buttons only) talking over WebSocket/API to a Windows controller PC
that owns all hardware, spooling, and uploads; AI stays server-side.
Final hardware ruling: the fi-8170 document scanner is for cards
only; comics are camera only (the panel line) — no flatbed. The
kiosk controls the imaging stage only, and the same architecture
later extends to coins, stamps, documents, games, and antiques. Full
spec in CAPTURE-STATION.md "Production Kiosks".

## 2026-07-29 — Card capture validation: flag, never stop

Owner ruling: after cards are captured, the system verifies the bag
number is readable and registered and that every card has a front and
a back. Failures FLAG the batch for admin attention — the line never
stops, the bin proceeds to bagging (the physical sticker keeps the
physical bag correct), and the scanning operator is never notified.
Admins resolve flags from their own queue. Supersedes the earlier
stop-the-line header validation. Follow-up ruling: the flag is caught
physically at the boxing station — scanning a flagged bag plays the
problem tone, refuses the bag, and locks further scanning until the
packer confirms the bag was set aside (logged). Only capture-health
flags intercept; needs-review bags pass freely. Rescan cycle: the
set-aside bag's number returns to the scan queue and is the one bag
number permitted to scan again as a batch header; both captures are
kept on one page (the superseded one marked, never deleted). Sounds
are load-bearing: distinct good/problem tones, verified speakers at
every station, sound check at session start.

## 2026-07-29 — Bagging is scan-in / scan-out, enforced

Owner spec: the bagger scans the ticket first and the system renders
its verdict before any cards move. Good → peel, fill, seal → scan
again to finish (both scans timed: bag-and-sticker duration per bag
and bagger). Flagged → error tone, confirm set-aside (timed), then
the next bin. The flow is enforced — no new bin until the current one
is closed out. Three flagged bags in a row raise an alarm on the KPI
dashboard and notify the admin. Bagging becomes the primary flag
catch; the boxing intercept remains as backstop.

## 2026-07-29 — Validation immediate, AI deferred

Owner ruling: the moment a batch's scanning completes, the mechanical
checks run immediately (bag readable/registered, front + back for
every card) — milliseconds, no AI — so the verdict exists before the
bin reaches bagging, the only window where a missing side is cheap to
fix. AI recognition is deliberately not urgent: it queues in the
background at low priority; the images already contain everything and
AI mistakes are fixed later by editing metadata. Validation follows
the cards; AI follows the images.

## 2026-07-30 — Scan-only floor: set-aside cards, badge identity,
default collection, shelf barcodes

Owner rulings completing the floor design: set-aside is confirmed by
scanning a laminated SET-ASIDE card (bagging needs gun + monitor +
speakers, no touchscreen); employee identity is a badge scan (OP-
cards in the registry; sign in/out by scan at every station, badge
feeds through the fi-8170 at the scan desk; per-operator KPIs
restored); all scanned batches land in one admin-managed default
collection (currently "Sruli's"); shelves get one SHF- barcode each
as soon as the physical shelves exist, completing the card → bag →
box → divider → shelf chain. All station PCs/monitors come from
existing stock.

## 2026-07-30 — Admin controls: scan-line collection and AI hold;
files renamed to bag number in the cloud

Owner-directed: (1) admins choose the collection all scans land in —
a Settings picker, forward-only from the moment it's saved (currently
"Sruli's"); (2) admins can HOLD all AI processing — scanning and
validation always continue, queued items wait, releasing re-dispatches
them, and the stall rescue leaves held items alone; (3) archived
files in Dropbox are renamed to the bag number itself
(BAG-000123-01-front.jpg) so a file separated from its folder still
announces its bag; (4) the fi-8170 line profile outputs one JPEG per
side, not PDF — files appear as they scan, validation stays instant,
and the image file itself is the sacred original (the PDF import
remains as the fallback path).

## 2026-07-30 — Comic book categories: Age, Format, Genre;
Publisher → Age drill-down

Owner-approved comics organization. Three new axes: **Age**, derived
automatically from the cover-date year (Golden 1938–1955, Silver
1956–1969, Bronze 1970–1984, Copper 1985–1991, Modern 1992+) — never
stored, so correcting a year corrects the age; **Format** (Regular
Issue, Annual, Special, One-Shot, Giant-Size, Graphic Novel, Trade
Paperback, Magazine); and **Genre** (Superhero, Horror, Sci-Fi,
Western, Romance, War, Humor, Crime) — both AI-extracted from the
cover, editable as dropdowns, filterable on the items list. The comics
browse drill is **Publisher → Age** (owner's chosen order): pick the
publisher, then the era, then the filtered list. Comics with a year
before 1938 or no year fall outside the age buckets and remain
reachable through "View all". The comics **key-issues watchlist**
(flag known key books like Amazing Fantasy 15 on sight — the comics
counterpart of the card key-names list) is approved in principle and
parked on the wishlist for a future milestone.

## 2026-07-30 — Grid photo capture: the panel flow without panels
(owner-directed)

Testing ramp toward the panel line, for comics AND cards: the owner
draws equal boxes (1, 2, 4, or 6), lays items in them, and shoots one
overhead photo. Bulk Capture's new Grid mode slices the photo into
equal cells — one item per cell, reading order — and each cover runs
through the AI like any capture. No computer vision: fixed equal
cells, matching the drawn grid and the future panels' fixed geometry.
The row × column split is chosen so cells come closest to the portrait
shape of a comic/card (landscape six = 2×3, portrait six = 3×2), and
phone EXIF orientation is applied before slicing. Cards use two
photos: shoot fronts, flip every card in its own box, shoot backs —
cells pair by position. Same background-job pattern as the PDF
import (batch shows "Converting…", duplicate photos refused by
content hash). The manufactured panels and their in-frame bag
barcode / panel number remain a later milestone; grid batches bind
to bags the ordinary way (scan the bag on the batch page).

## 2026-07-24 — Documentation stored in repository

The five specification documents are committed to the repository root as README.md, PROJECT.md, ARCHITECTURE.md, CLAUDE.md, and PHASE_1.md, alongside this decisions log.
