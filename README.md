# Maxcollection

Maxcollection is a standalone collections management system — a new system alongside VSS and Shuls. It tracks **members**, the **charges** they owe (dues, pledges, invoices), and the **payments** they make, and shows outstanding balances at a glance.

## Features

- Member directory with contact details
- Charges (description, amount, optional due date) per member
- Payments (cash / check / card / transfer / other, with reference numbers)
- Per-member balance and an overall dashboard: total charged, total collected, outstanding
- Simple web dashboard, no build step
- SQLite storage via Node's built-in `node:sqlite` — **zero npm dependencies**

## Requirements

- Node.js 22.5+ (uses the built-in `node:sqlite` module)

## Run

```bash
npm start
```

Then open http://localhost:3000. The database is created automatically at `data/maxcollection.db` (override with the `MAXCOLLECTION_DB` env var; `PORT` overrides the port).

## Test

```bash
npm test
```

## API

| Method | Path                | Description                                        |
| ------ | ------------------- | -------------------------------------------------- |
| GET    | `/api/summary`      | Totals: members, charged, collected, outstanding   |
| GET    | `/api/members`      | All members with charged/paid totals               |
| POST   | `/api/members`      | Create member `{ name, email?, phone?, notes? }`   |
| GET    | `/api/members/:id`  | Member detail with charges and payments            |
| DELETE | `/api/members/:id`  | Delete member (cascades charges/payments)          |
| POST   | `/api/charges`      | `{ member_id, description, amount, due_date? }`    |
| POST   | `/api/payments`     | `{ member_id, amount, method?, reference? }`       |

Amounts are sent as decimal values (e.g. `"180.00"`) and stored as integer cents.
