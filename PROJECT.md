PROJECT.txt

============================================================
MAX COLLECTION
Product Requirements Document
Version 0.1
============================================================

1. Executive Summary

2. Product Vision

3. Project Objectives

4. Guiding Principles

5. Users

6. Supported Collectibles

7. Application Navigation

8. Screen Specifications
   8.1 Login
   8.2 Home
   8.3 Capture Item
   8.4 Processing Queue
   8.5 Processed Items
   8.6 Item Detail
   8.7 Needs Review
   8.8 Settings

9. Complete User Workflow

10. AI Processing Requirements

11. Metadata Requirements

12. Database Requirements

13. Image Storage Requirements

14. Search Requirements

15. Editing Requirements

16. Audit History

17. Error Handling

18. Business Rules

19. Performance Requirements

20. Security Requirements

21. Out of Scope

22. MVP Success Criteria


============================================================
MAX COLLECTION
PRODUCT REQUIREMENTS DOCUMENT
Version 0.1 (MVP)
============================================================

Author: Cheski Baum
Company: VSS Technology

Document Purpose

This document defines the complete functional requirements for Version 0.1 of Max Collection.

It is the authoritative source for all business requirements, workflows, and application behavior.

If implementation differs from this document, this document takes precedence unless explicitly approved by the project owner.

============================================================
1. EXECUTIVE SUMMARY
============================================================

Max Collection is an AI-assisted collectible management application.

The application's purpose is to transform photographs of collectibles into structured, searchable collection records using artificial intelligence.

Unlike traditional inventory applications, users should not be expected to manually type most information.

Instead, the application should:

• Capture photographs.

• Process those photographs using AI.

• Identify the collectible.

• Extract useful metadata.

• Present extracted information.

• Allow user corrections.

• Save the completed record.

The application is intended to minimize manual data entry while maintaining user control over the final information.

Version 0.1 is not intended to be feature complete.

Its purpose is to validate that the AI-assisted workflow functions reliably.

============================================================
2. PRODUCT VISION
============================================================

The long-term vision is to create one of the most intelligent collectible management platforms available.

However, Version 0.1 deliberately ignores long-term complexity.

The MVP exists to answer one question:

"Can AI reliably identify collectibles from photographs and produce editable structured information?"

If the answer is yes, the MVP has succeeded.

Future features such as pricing, duplicate detection, grading, inventory management, warehouse tracking, AI learning, marketplace integration, and analytics will only be considered after this question has been answered.

============================================================
3. PROJECT OBJECTIVES
============================================================

The MVP has six objectives.

Objective 1

Provide an extremely simple workflow for capturing collectible images.

Objective 2

Allow AI to identify supported collectible categories.

Objective 3

Extract structured metadata appropriate for each category.

Objective 4

Allow the user to review and correct AI results.

Objective 5

Store corrected information permanently.

Objective 6

Provide a fast searchable collection.

No feature should be added unless it directly contributes toward one of these objectives.

============================================================
4. GUIDING PRINCIPLES
============================================================

The following principles govern every design and development decision.

4.1 Simplicity

The simplest working solution is preferred.

Avoid unnecessary abstraction.

Avoid unnecessary architecture.

Avoid speculative features.

4.2 User Control

AI assists the user.

AI never replaces the user.

Users always make the final decision regarding stored information.

4.3 Preserve Original Data

Original uploaded photographs must never be modified.

Every processed image must be derived from the original.

4.4 Human Review

AI mistakes are expected.

Every field must remain editable.

No metadata should be considered permanently correct.

4.5 Incremental Development

The application will be built in small phases.

Each completed phase should produce a working application.

Future phases must build upon previous phases rather than replacing them.

============================================================
5. USERS
============================================================

Version 0.1 supports Administrator accounts only.

There are no standard users.

There are no organizations.

There are no permission levels.

Every authenticated user has full administrative access.

The initial administrator accounts are:

cheskib@gmail.com

srulymax007@gmail.com

Both accounts are seeded during installation.

Both accounts use the initial password:

collection321$$

Password changes may be implemented later.

No registration screen is required.

No password reset functionality is required.

============================================================
END OF PART 1


============================================================
6. SUPPORTED COLLECTIBLES
============================================================

Version 0.1 supports only the following collectible categories.

Sports Cards

Comic Books

Coins

Stamps

The AI is responsible for determining which category an item belongs to.

If an uploaded item cannot be confidently classified into one of these categories, the item shall be marked as:

Unsupported

Unsupported items are still saved but are automatically placed into the Needs Review queue.

No additional processing is performed.

Future versions will introduce additional categories.

============================================================
7. APPLICATION NAVIGATION
============================================================

The MVP intentionally contains a very small number of screens.

Login

↓

Home

↓

Capture Item

↓

Return Home

↓

Process Items

↓

Processed Items

↓

Item Detail

↓

Edit Item

or

↓

Needs Review

↓

Item Detail

↓

Edit Item

↓

Return Home

There should never be more than three clicks required to reach any major function.

Navigation should remain simple and intuitive.

============================================================
8. SCREEN SPECIFICATIONS
============================================================

------------------------------------------------------------
8.1 LOGIN
------------------------------------------------------------

Purpose

Authenticate an administrator.

Fields

Email Address

Password

Buttons

Login

Behavior

Successful authentication redirects the user to the Home screen.

Invalid credentials display a simple error message.

No registration.

No password recovery.

No remember-me option.

------------------------------------------------------------
8.2 HOME
------------------------------------------------------------

Purpose

Provide access to all primary application functions.

Display four statistics.

Items Captured

Items Processed

Needs Review

Pictures Uploaded

Buttons

Capture Item

Process Items

Processed Items

Needs Review

Settings

Logout

The Home screen should be clean and uncluttered.

------------------------------------------------------------
8.3 CAPTURE ITEM
------------------------------------------------------------

Purpose

Allow users to capture one or more photographs for a single collectible.

Buttons

Take Picture

Upload Picture

Take Another Picture

Delete Picture

I'm Done

Workflow

The first successful uploaded photograph creates the item record.

A unique internal Item ID is assigned.

Additional photographs are attached to the same item.

Deleting a photograph removes only that image.

If the final remaining photograph is deleted, the item itself is automatically deleted.

Selecting "I'm Done" returns the user to the Home screen.

No AI processing occurs during capture.

============================================================
9. CAPTURE WORKFLOW
============================================================

Step 1

User selects Capture Item.

Step 2

User uploads or photographs the collectible.

Step 3

System stores original photographs.

Step 4

User may upload additional photographs.

Step 5

User selects "I'm Done."

Step 6

User returns to Home.

No processing occurs during capture.

This separation is intentional.

The user should be able to capture many items before beginning processing.

============================================================
10. PROCESSING QUEUE
============================================================

Processing is initiated only when the user selects:

Process Items

The application places every unprocessed item into a Laravel queue.

The queue processes each item independently.

The user does not wait for completion.

Processing continues in the background.

Failures affecting one item must not interrupt processing of other items.

Queue progress should be visible from the Home screen in a future version.

Version 0.1 only requires background processing.

============================================================
11. AI PROCESSING
============================================================

The AI processing pipeline performs the following tasks.

Step 1

Determine collectible category.

Step 2

Extract structured metadata.

Step 3

Generate a confidence score.

Step 4

Return structured JSON.

If confidence is below the configured threshold (75% for MVP), the item is automatically placed into the Needs Review queue.

The original uploaded photographs must never be modified.

Any resized, cropped, or optimized images are stored separately from the originals.

============================================================
END OF PART 2

============================================================
12. METADATA REQUIREMENTS
============================================================

The AI shall return structured metadata appropriate for the identified collectible category.

Metadata shall be stored independently from AI responses so that users may edit values without modifying the original AI result.

------------------------------------------------------------
12.1 SPORTS CARDS
------------------------------------------------------------

The following fields shall be extracted whenever possible.

Player Name

Team

Sport

Year

Manufacturer

Set Name

Card Number

Rookie Card (Yes/No/Unknown)

Parallel

Serial Number

Autograph

Condition Notes

Confidence Score

If AI cannot determine a value, the field shall remain blank.

Never invent values.

------------------------------------------------------------
12.2 COMIC BOOKS
------------------------------------------------------------

Title

Issue Number

Publisher

Year

Variant

Condition Notes

Confidence Score

------------------------------------------------------------
12.3 COINS
------------------------------------------------------------

Country

Denomination

Year

Mint Mark

Composition

Condition Notes

Confidence Score

------------------------------------------------------------
12.4 STAMPS
------------------------------------------------------------

Country

Issue Name

Year

Color

Denomination

Condition Notes

Confidence Score

============================================================
13. PROCESSED ITEMS
============================================================

Purpose

Display every successfully processed collectible.

Display

Thumbnail

Primary Title

Category

Confidence

Processing Date

Buttons

View

Search

Sort

Filters may be added in future versions.

Version 0.1 requires only a search box.

============================================================
14. ITEM DETAIL
============================================================

Purpose

Display complete information for a single collectible.

Sections

Photographs

Metadata

AI Confidence

Processing Information

Buttons

Edit

Back

Reprocess (Administrator)

All uploaded photographs shall remain available.

Original photographs shall always be viewable.

============================================================
15. EDITING
============================================================

Every metadata field shall be editable.

The application shall never permanently lock any field.

When the user saves changes:

Update the metadata.

Record the modification in history.

Return to the Item Detail screen.

Edits become the official values displayed throughout the application.

Original AI output shall remain preserved for auditing.

============================================================
16. EDIT HISTORY
============================================================

Every modification shall create a history record.

History records contain:

Field Name

Previous Value

New Value

User

Date

Time

History is append-only.

History records are never modified.

History records are never deleted.

============================================================
17. NEEDS REVIEW
============================================================

Items requiring manual attention appear in the Needs Review screen.

Reasons include:

Low AI confidence

Unsupported category

Unreadable photographs

AI processing failure

Missing required metadata

The screen displays:

Thumbnail

Reason

Confidence

Date Processed

Buttons

View

Edit

Reprocess

Once corrected, an item automatically leaves the Needs Review queue.

============================================================
18. SEARCH
============================================================

Version 0.1 supports a simple keyword search.

Search shall include:

Player

Title

Manufacturer

Set

Year

Card Number

Issue Number

Country

Results should appear immediately after search submission.

Advanced filtering is outside MVP scope.

============================================================
END OF PART 3


============================================================
19. DATABASE REQUIREMENTS
============================================================

The MVP database should remain intentionally simple.

The following tables are required.

Users

Stores administrator accounts.

Items

Represents a single collectible.

Images

Stores references to one or more photographs belonging to an item.

Metadata

Stores the current editable metadata for each collectible.

Metadata History

Stores all metadata modifications.

Processing Jobs

Tracks AI processing requests.

Processing Logs

Stores processing events and errors.

Settings

Stores application configuration.

Additional tables should not be introduced unless they are required to implement MVP functionality.

============================================================
20. IMAGE STORAGE
============================================================

Uploaded photographs shall be stored using the following principles.

Original images are never modified.

Derived images may be generated for thumbnails or AI optimization.

Recommended storage structure.

storage/

    original/

    processed/

    thumbnails/

The original upload remains the authoritative source.

Deleting an item deletes all associated derived images.

Deletion of original images should only occur when the item itself is deleted.

============================================================
21. BUSINESS RULES
============================================================

The following business rules apply throughout the application.

Rule 1

One collectible may contain multiple photographs.

Rule 2

The first uploaded photograph creates the collectible.

Rule 3

Deleting the final remaining photograph deletes the collectible.

Rule 4

AI never permanently owns any metadata.

Users always have final authority.

Rule 5

Every metadata modification is recorded.

Rule 6

Original photographs are immutable.

Rule 7

AI processing always occurs asynchronously using Laravel Queues.

Rule 8

A processing failure affecting one item must never stop processing of other items.

Rule 9

Unsupported collectibles remain stored but require manual review.

Rule 10

The application should never discard user-entered information without explicit confirmation.

============================================================
22. ERROR HANDLING
============================================================

The application should gracefully recover from failures whenever possible.

Typical failure scenarios include:

Invalid image upload

Corrupted image

Unsupported file format

AI timeout

AI API failure

Database write failure

Queue failure

For each failure:

Log the error.

Display a meaningful message to the user.

Continue processing remaining items whenever possible.

System errors should never expose stack traces or sensitive implementation details to end users.

============================================================
23. PERFORMANCE REQUIREMENTS
============================================================

Performance is important but simplicity takes priority during the MVP.

Target expectations:

Login should complete within 2 seconds.

Image upload should begin immediately.

The UI should remain responsive while background processing occurs.

Search results should return within 1 second for collections up to 10,000 items.

Long-running AI requests must never block the user interface.

============================================================
24. SECURITY REQUIREMENTS
============================================================

Administrator authentication is required for all application access.

Passwords shall be securely hashed using Laravel defaults.

CSRF protection shall remain enabled.

User input shall always be validated.

Uploaded files shall be validated before storage.

Only supported image formats may be uploaded.

Application secrets and API keys shall never be hard-coded.

Environment variables shall be used for all credentials.

============================================================
25. OUT OF SCOPE
============================================================

The following features are intentionally excluded from Version 0.1.

Estimated Market Value (EMV)

Marketplace Integration

Auction Data

Duplicate Detection

AI Learning

Knowledge Graphs

Barcode Recognition

QR Code Tracking

Warehouse Management

Inventory Locations

Mobile Applications

Offline Support

Multi-user Organizations

Role-based Security

Reporting

Analytics

Email Notifications

Cloud Storage

Public APIs

Bulk Imports

Exports

These features may be introduced in future releases but shall not be implemented during Phase 1.

============================================================
26. MVP ACCEPTANCE CRITERIA
============================================================

Version 0.1 shall be considered complete when all of the following scenarios succeed.

Scenario 1

Administrator logs into the application.

PASS

Scenario 2

Administrator captures one or more photographs of a collectible.

PASS

Scenario 3

Administrator initiates processing.

PASS

Scenario 4

AI identifies the collectible category.

PASS

Scenario 5

AI extracts metadata.

PASS

Scenario 6

Metadata is presented to the administrator.

PASS

Scenario 7

Administrator edits incorrect values.

PASS

Scenario 8

Changes are saved.

PASS

Scenario 9

Item is searchable.

PASS

Scenario 10

Administrator can review processing history.

PASS

If every scenario above succeeds reliably, Version 0.1 is complete.

No additional functionality is required before beginning Version 0.2.

IMPLEMENTATION NOTE

If any requirement in this document is ambiguous, do not invent behavior.

Instead:

1. Record the assumption.
2. Explain why the assumption is necessary.
3. Wait for approval before implementing the ambiguous functionality.

The objective is correctness, not speed.

============================================================
END OF PROJECT.txt
============================================================


