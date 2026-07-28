# Milestones — Phase 1 (MVP) Verification

All thirteen Phase 1 milestones were built, tested, and approved by the
owner. This records each milestone's status as of 2026-07-29, including
where today's app deliberately differs from the original milestone text
because of later owner decisions (logged in DECISIONS.md / WISHLIST.md).

Regression coverage: the full test suite (141 tests, 898 assertions)
runs on every push via GitHub Actions.

| # | Milestone | Status | Notes on evolution since |
|---|-----------|--------|--------------------------|
| 1 | Project Initialization | ✅ | Laravel 12, Vue 3 + TypeScript + Inertia, Tailwind, MySQL, Git. Live in production on Railway. |
| 2 | Authentication | ✅ | Login/logout; both administrators seeded; no registration, no reset. Unchanged. |
| 3 | Home Screen | ✅ | All four statistics live — renamed per owner (Items Uploaded / Photos Uploaded) and made clickable in the Inventory Overview panel. Settings is no longer "Coming Soon" (see M13 note). |
| 4 | Capture Items | ✅ | Upload, mobile camera (now a scanner-style live viewfinder), multiple images, first-image-creates-item, delete cascades. "I'm Done" evolved into the wizard finish + autograph question. |
| 5 | Database | ✅ | All eight specced tables with verified relationships (DatabaseSchemaTest), plus later owner-requested tables: batches, collections, card_sets. |
| 6 | Background Processing | ✅ | Process button, Laravel queue, one job per item, statuses, logging. Stub replaced by real AI in M7; stalled jobs now self-rescue to Needs Review. |
| 7 | AI Integration | ✅ | Four categories; returns category, metadata, confidence, raw JSON (stored verbatim). Below-threshold → Needs Review; the 75 default is now editable in Settings. |
| 8 | Processed Items | ✅ | Full uncropped display, title, category, confidence, date, View. Evolved per owner: overview-first drill-down, SQL pagination, filters, value display. |
| 9 | Item Detail | ✅ | Full-size images (Original/Cleaned pairs), pulled-fields-only metadata, confidence, processing info incl. model. Edit / Reprocess (tier + photo-source choices) / Back. |
| 10 | Editing | ✅ | Every field editable with owner-specified dropdowns; append-only history; edits clear review status. Owner change: Save stays on Edit with a confirmation instead of returning to detail. |
| 11 | Needs Review | ✅ | All four reasons implemented (low confidence, unsupported, AI failure incl. stall rescue, missing metadata — checklists exempt per owner). View on each row; Edit/Reprocess on the item page. |
| 12 | Search | ✅ | Keyword search across the eight specced fields. Owner change 2026-07-28: Set Name retired from the app, leaving seven live search fields. |
| 13 | MVP Completion | ✅ | Full checklist verified by tests and by production use with real cards (16-card 1987 Topps batch and others). Settings has since graduated from placeholder to a functional page. |

Phase 1 / Level 1: **complete**. Everything built afterwards (batches,
collections, values, drill-downs, live camera, landing page, duplicate
awareness, market pricing) is Phase 2+ work layered on this base.
