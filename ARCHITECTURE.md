============================================================
MAX COLLECTION
ARCHITECTURE
Version 0.1
============================================================

Author: Cheski Baum
Company: VSS Technology

Purpose

This document defines the technical architecture for Version 0.1.

The architecture intentionally favors simplicity over flexibility.

Whenever there is a choice between adding an abstraction or writing clear code, choose the clear code.

============================================================
1. ARCHITECTURAL PRINCIPLES
============================================================

The application shall be implemented as a standard Laravel application.

Do not split the system into microservices.

Do not introduce unnecessary abstraction layers.

Do not implement features for possible future requirements.

The architecture should remain understandable by a single developer.

============================================================
2. HIGH LEVEL ARCHITECTURE
============================================================

Browser

↓

Vue 3 + Inertia

↓

Laravel Controllers

↓

Application Services

↓

Database

↓

Laravel Queue

↓

OpenAI Responses API

Only one backend application exists.

No external services other than OpenAI are required.

============================================================
3. FRONTEND
============================================================

Framework

Vue 3

Language

TypeScript

Communication

Inertia.js

Styling

Tailwind CSS

The frontend should remain mobile-first.

Desktop users should receive the same interface centered on the page.

Avoid large components.

Each page should generally consist of one primary component.

============================================================
4. BACKEND
============================================================

Framework

Laravel 12

Language

PHP 8.4

Controllers should remain thin.

Controllers should validate requests.

Controllers should call services.

Business logic belongs inside service classes.

Database access should primarily use Eloquent models.

Repositories are unnecessary for Version 0.1.

============================================================
5. APPLICATION LAYERS
============================================================

Presentation Layer

Vue Pages

Laravel Controllers

Application Layer

Services

Queue Jobs

Domain Layer

Business Rules

Models

Persistence Layer

MySQL

Filesystem

============================================================
6. DATABASE
============================================================

Tables

users

items

images

metadata

metadata_history

processing_jobs

processing_logs

settings

Relationships

User

↓

Items

↓

Images

↓

Metadata

↓

Metadata History

One item may contain many images.

One item has one active metadata record.

One item has many metadata history records.

============================================================
7. FILE STORAGE
============================================================

Store uploaded images using Laravel Storage.

Directory layout

storage/app/

original/

processed/

thumbnails/

Original images shall never be modified.

Derived images are disposable.

============================================================
8. AI PROCESSING
============================================================

AI processing occurs entirely in background queue jobs.

Workflow

Capture

↓

Queue Job

↓

OpenAI

↓

JSON Response

↓

Metadata

↓

Needs Review (if required)

↓

Completed

Only one OpenAI model shall be used during Version 0.1.

Do not abstract multiple AI providers.

============================================================
9. PROCESSING PIPELINE
============================================================

Upload Images

↓

Create Item

↓

Queue Item

↓

Process Images

↓

Identify Category

↓

Extract Metadata

↓

Calculate Confidence

↓

Save Metadata

↓

Finished

============================================================
10. NAVIGATION
============================================================

Login

↓

Home

↓

Capture Item

↓

Home

↓

Process Items

↓

Processed Items

↓

Item Detail

↓

Edit

↓

Home

Needs Review follows the same workflow.

============================================================
11. ERROR HANDLING
============================================================

Errors should never crash the application.

Failures should be logged.

Queue failures should affect only the current item.

Database transactions should be used where appropriate.

============================================================
12. SECURITY
============================================================

Use Laravel authentication.

Hash passwords.

Validate all input.

Protect against CSRF.

Use environment variables for secrets.

Never hardcode API keys.

============================================================
13. CODING STANDARDS
============================================================

Use Laravel conventions whenever possible.

Avoid clever code.

Prefer readable code.

Prefer explicit code.

Comment WHY.

Avoid commenting WHAT.

Small methods.

Small classes.

Meaningful names.

============================================================
14. FUTURE ARCHITECTURE
============================================================

The following concepts are intentionally postponed.

Multiple AI providers

Knowledge Graph

Estimated Market Value

Duplicate Detection

Marketplace Integration

Warehouse Management

Organization Support

Role Based Security

These features shall not influence Version 0.1 architecture.

============================================================
15. FINAL RULE
============================================================

Whenever the architecture seems to require additional abstraction, ask one question:

"Does this solve a current MVP requirement?"

If the answer is No,

do not implement it.

============================================================
16. IMAGE AUTHORITY PRINCIPLE (owner directive, 2026-07-29)
============================================================

The original uploaded image is the authoritative source. It is never
modified, replaced, compressed, cropped, rotated, resized, or deleted
by any automated process. Only an explicit user action (delete photo,
delete item, delete batch) removes an original.

Everything else is a derived image, regenerable from the original at
any time and therefore disposable:

Original       -> exactly what the user uploaded. Read-only.
Thumbnail      -> rotation + trim + 400px, cached on disk for lists,
                  invalidated when adjustments change.
Display image  -> rotation + trim at full size, rendered per request,
                  never stored.
AI image       -> downscaled copy (or the untouched original when the
                  user chooses "Original photos"), sent to the model
                  and discarded, never stored.

All derived forms are produced by the single shared render pipeline
(ImageRenderService), so they always agree with each other.

============================================================
END OF ARCHITECTURE