============================================================
MAX COLLECTION
CLAUDE DEVELOPMENT INSTRUCTIONS
Version 0.1
============================================================

Author: Cheski Baum
Company: VSS Technology

Purpose

This document defines how Claude should approach the implementation of Max Collection.

The PROJECT document defines WHAT to build.

This document defines HOW to build it.

If these instructions conflict with normal development practices, these instructions take precedence unless explicitly overridden by the project owner.

============================================================
1. PRIMARY OBJECTIVE
============================================================

Your objective is not to write the most sophisticated software.

Your objective is to build the simplest application that completely satisfies the PROJECT document.

Optimize for correctness.

Optimize for readability.

Optimize for maintainability.

Do not optimize for hypothetical future requirements.

============================================================
2. GENERAL RULES
============================================================

Never invent features.

Never redesign the product.

Never improve the requirements without approval.

If something is not specified, ask.

Do not guess.

Whenever you are uncertain, stop and ask for clarification.

============================================================
3. DEVELOPMENT APPROACH
============================================================

Build the application in small milestones.

Complete one milestone.

Verify it works.

Explain what was built.

Wait for approval.

Do not continue automatically.

============================================================
4. ASSUMPTIONS
============================================================

Whenever you make an assumption:

Clearly identify it.

Explain why it is necessary.

Wait for approval before implementing behavior based upon that assumption.

Never silently invent behavior.

============================================================
5. SIMPLICITY
============================================================

Always prefer the simplest working solution.

Avoid unnecessary abstraction.

Avoid unnecessary interfaces.

Avoid unnecessary inheritance.

Avoid unnecessary design patterns.

Avoid architecture designed only for future possibilities.

============================================================
6. LARAVEL
============================================================

Follow Laravel conventions whenever possible.

Use Eloquent.

Use Form Requests.

Use Policies only if required.

Use Queue Jobs for background processing.

Use Service classes for business logic.

Keep Controllers thin.

Keep Models focused.

============================================================
7. FRONTEND
============================================================

Use Vue 3.

Use TypeScript.

Use Inertia.

Use Tailwind.

Keep components small.

Avoid deeply nested components.

Favor readability over cleverness.

============================================================
8. CODE QUALITY
============================================================

Write readable code.

Choose meaningful names.

Keep methods short.

Keep classes focused.

Avoid duplication where practical.

Comment WHY.

Do not comment obvious code.

============================================================
9. ERROR HANDLING
============================================================

Fail gracefully.

Display meaningful messages.

Log unexpected errors.

Never expose stack traces to users.

Recover whenever possible.

============================================================
10. DATABASE
============================================================

Keep the schema simple.

Do not create tables that are not required by the PROJECT document.

Do not normalize prematurely.

Do not optimize for millions of records.

Optimize for clarity.

============================================================
11. AI
============================================================

Use one OpenAI provider.

Use one model.

Return structured JSON.

Validate AI responses.

Never assume AI responses are correct.

User corrections always override AI.

============================================================
12. TESTING
============================================================

Test each completed milestone.

Verify expected behavior.

Fix problems before continuing.

Do not allow failing functionality to accumulate.

============================================================
13. COMMUNICATION
============================================================

After every milestone provide:

Summary

Files Created

Files Modified

Database Changes

Routes Added

Remaining Work

Questions

Wait for approval.

============================================================
14. WHEN TO STOP
============================================================

Immediately stop if:

Requirements conflict.

Architecture must change.

An assumption is required.

A major dependency is missing.

The implementation significantly differs from the PROJECT document.

============================================================
15. FINAL RULE
============================================================

Whenever you are tempted to build something more sophisticated, ask yourself:

"Does the PROJECT document require this?"

If the answer is No,

do not build it.

============================================================
END OF CLAUDE DEVELOPMENT INSTRUCTIONS