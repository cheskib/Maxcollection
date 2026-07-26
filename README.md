# Max Collection
### README.md

Version: 0.1 (MVP)
Status: Planning Complete – Ready for Development

---

# Overview

Max Collection is an AI-assisted collectible management system developed by VSS Technology.

Its purpose is to allow a collector to photograph collectibles, have AI identify them, extract useful metadata, allow the user to review and correct the results, and build a searchable digital collection.

The MVP is intentionally limited in scope. It exists to prove that AI can reliably convert photographs of collectibles into useful structured information.

The application is not intended to be production-ready during Phase 1.

The primary objective is validating the concept.

---

# MVP Objectives

The system must allow an administrator to:

• Capture one or more photographs of a collectible.

• Process those photographs using AI.

• Identify the collectible category.

• Extract metadata.

• Present extracted information to the user.

• Allow edits and corrections.

• Save the item.

• Search previously processed items.

If these steps work reliably, the MVP is considered successful.

---

# Supported Categories

Version 0.1 supports only:

- Sports Cards
- Comic Books
- Coins
- Stamps

Everything else should be classified as Unsupported.

---

# Technology Stack

Backend

- Laravel 12
- PHP 8.4
- MySQL

Frontend

- Vue 3
- TypeScript
- Inertia
- Tailwind CSS

Processing

- Laravel Queue
- Laravel Scheduler

Artificial Intelligence

- OpenAI Responses API (Vision)

Storage

- Local filesystem during MVP

---

# Authentication

The MVP contains Administrator accounts only.

There are no standard users.

There is no registration.

There are no permissions beyond Administrator access.

Two administrator accounts are seeded into the database.

---

# Guiding Philosophy

Keep everything simple.

Every feature must directly support proving the core concept.

Do not add features because they may be useful later.

Do not over-engineer.

Do not optimize prematurely.

Build the smallest application that successfully demonstrates the workflow.

---

# Core Workflow

Login

↓

Capture Item

↓

Upload Pictures

↓

Process Queue

↓

AI Identification

↓

Metadata Extraction

↓

Needs Review (if required)

↓

User Corrections

↓

Save

↓

Search

↓

Repeat

---

# Out of Scope

The following features are intentionally excluded from Version 0.1:

- Estimated Market Value (EMV)
- Duplicate Detection
- Knowledge Graphs
- AI Learning
- Multiple AI Providers
- Barcode Support
- QR Code Tracking
- Warehouse Locations
- User Roles
- Organizations
- APIs
- Mobile Applications
- Offline Mode
- Inventory Management
- Reports
- Dashboards
- Authentication Providers
- Notifications
- Email
- Cloud Storage

These features may be introduced in future versions but must not be implemented during the MVP.

---

# Repository Organization

The project documentation is provided in five documents.

README.md

Project overview.

PROJECT.md

Functional requirements and business rules.

ARCHITECTURE.md

Technical design and system architecture.

CLAUDE.md

Development instructions specifically for Claude.

PHASE_1.md

Implementation milestones and development order.

Each document builds upon the previous one.

Claude should read every document before beginning implementation.

---

# Development Rules

Implementation must proceed in phases.

After completing each milestone:

• Explain what was built.

• Explain any assumptions.

• Explain any deviations from the specification.

• Stop and wait for approval before continuing.

---

# Success Criteria

The MVP is complete when the following workflow succeeds reliably:

1. Login

2. Capture photographs

3. Process photographs using AI

4. Extract metadata

5. Review results

6. Edit incorrect fields

7. Save item

8. Search for saved items

Nothing else is required for Version 0.1.

---

# Important

This project intentionally favors clarity over complexity.

Every line of code should contribute directly toward proving that AI can transform photographs of collectibles into accurate, editable, searchable collection records.

Anything that does not directly support that objective should be postponed until a later phase.

End of README.md