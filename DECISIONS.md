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

## 2026-07-24 — Documentation stored in repository

The five specification documents are committed to the repository root as README.md, PROJECT.md, ARCHITECTURE.md, CLAUDE.md, and PHASE_1.md, alongside this decisions log.
