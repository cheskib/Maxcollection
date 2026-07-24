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

## 2026-07-24 — Documentation stored in repository

The five specification documents are committed to the repository root as README.md, PROJECT.md, ARCHITECTURE.md, CLAUDE.md, and PHASE_1.md, alongside this decisions log.
