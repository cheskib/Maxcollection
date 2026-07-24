'use strict';

const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');

const PUBLIC_DIR = path.join(__dirname, '..', 'public');
const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.svg': 'image/svg+xml',
};
const PAYMENT_METHODS = ['cash', 'check', 'card', 'transfer', 'other'];

function sendJson(res, status, body) {
  const data = JSON.stringify(body);
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
  res.end(data);
}

function readJsonBody(req) {
  return new Promise((resolve, reject) => {
    let raw = '';
    req.on('data', (chunk) => {
      raw += chunk;
      if (raw.length > 1e6) {
        reject(new Error('Request body too large'));
        req.destroy();
      }
    });
    req.on('end', () => {
      if (!raw) return resolve({});
      try {
        resolve(JSON.parse(raw));
      } catch {
        reject(new Error('Invalid JSON body'));
      }
    });
    req.on('error', reject);
  });
}

// Amounts are accepted as decimal strings/numbers (e.g. "18.50") and stored as integer cents.
function toCents(value) {
  const num = typeof value === 'string' ? Number(value) : value;
  if (typeof num !== 'number' || !Number.isFinite(num) || num <= 0) return null;
  const cents = Math.round(num * 100);
  return cents > 0 ? cents : null;
}

function memberRow(db, id) {
  return db
    .prepare(
      `SELECT m.*,
        COALESCE((SELECT SUM(amount_cents) FROM charges WHERE member_id = m.id), 0) AS charged_cents,
        COALESCE((SELECT SUM(amount_cents) FROM payments WHERE member_id = m.id), 0) AS paid_cents
      FROM members m WHERE m.id = ?`
    )
    .get(id);
}

function createApp(db) {
  return http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const route = `${req.method} ${url.pathname}`;

    try {
      // --- Members ---
      if (route === 'GET /api/members') {
        const rows = db
          .prepare(
            `SELECT m.*,
              COALESCE((SELECT SUM(amount_cents) FROM charges WHERE member_id = m.id), 0) AS charged_cents,
              COALESCE((SELECT SUM(amount_cents) FROM payments WHERE member_id = m.id), 0) AS paid_cents
            FROM members m ORDER BY m.name COLLATE NOCASE`
          )
          .all();
        return sendJson(res, 200, rows);
      }

      if (route === 'POST /api/members') {
        const body = await readJsonBody(req);
        const name = typeof body.name === 'string' ? body.name.trim() : '';
        if (!name) return sendJson(res, 400, { error: 'name is required' });
        const result = db
          .prepare('INSERT INTO members (name, email, phone, notes) VALUES (?, ?, ?, ?)')
          .run(name, body.email || null, body.phone || null, body.notes || null);
        return sendJson(res, 201, memberRow(db, result.lastInsertRowid));
      }

      const memberMatch = url.pathname.match(/^\/api\/members\/(\d+)$/);
      if (memberMatch) {
        const id = Number(memberMatch[1]);
        const existing = memberRow(db, id);
        if (!existing) return sendJson(res, 404, { error: 'member not found' });

        if (req.method === 'GET') {
          const charges = db.prepare('SELECT * FROM charges WHERE member_id = ? ORDER BY created_at DESC, id DESC').all(id);
          const payments = db.prepare('SELECT * FROM payments WHERE member_id = ? ORDER BY paid_at DESC, id DESC').all(id);
          return sendJson(res, 200, { ...existing, charges, payments });
        }
        if (req.method === 'DELETE') {
          db.prepare('DELETE FROM members WHERE id = ?').run(id);
          return sendJson(res, 200, { deleted: id });
        }
        return sendJson(res, 405, { error: 'method not allowed' });
      }

      // --- Charges ---
      if (route === 'POST /api/charges') {
        const body = await readJsonBody(req);
        const memberId = Number(body.member_id);
        const cents = toCents(body.amount);
        const description = typeof body.description === 'string' ? body.description.trim() : '';
        if (!Number.isInteger(memberId) || !db.prepare('SELECT id FROM members WHERE id = ?').get(memberId)) {
          return sendJson(res, 400, { error: 'valid member_id is required' });
        }
        if (!description) return sendJson(res, 400, { error: 'description is required' });
        if (cents === null) return sendJson(res, 400, { error: 'amount must be a positive number' });
        const result = db
          .prepare('INSERT INTO charges (member_id, description, amount_cents, due_date) VALUES (?, ?, ?, ?)')
          .run(memberId, description, cents, body.due_date || null);
        const row = db.prepare('SELECT * FROM charges WHERE id = ?').get(result.lastInsertRowid);
        return sendJson(res, 201, row);
      }

      // --- Payments ---
      if (route === 'POST /api/payments') {
        const body = await readJsonBody(req);
        const memberId = Number(body.member_id);
        const cents = toCents(body.amount);
        if (!Number.isInteger(memberId) || !db.prepare('SELECT id FROM members WHERE id = ?').get(memberId)) {
          return sendJson(res, 400, { error: 'valid member_id is required' });
        }
        if (cents === null) return sendJson(res, 400, { error: 'amount must be a positive number' });
        const method = PAYMENT_METHODS.includes(body.method) ? body.method : 'other';
        const result = db
          .prepare('INSERT INTO payments (member_id, amount_cents, method, reference) VALUES (?, ?, ?, ?)')
          .run(memberId, cents, method, body.reference || null);
        const row = db.prepare('SELECT * FROM payments WHERE id = ?').get(result.lastInsertRowid);
        return sendJson(res, 201, row);
      }

      // --- Summary ---
      if (route === 'GET /api/summary') {
        const totals = db
          .prepare(
            `SELECT
              (SELECT COUNT(*) FROM members) AS members,
              COALESCE((SELECT SUM(amount_cents) FROM charges), 0) AS charged_cents,
              COALESCE((SELECT SUM(amount_cents) FROM payments), 0) AS paid_cents`
          )
          .get();
        return sendJson(res, 200, {
          members: totals.members,
          charged_cents: totals.charged_cents,
          paid_cents: totals.paid_cents,
          outstanding_cents: totals.charged_cents - totals.paid_cents,
        });
      }

      if (url.pathname.startsWith('/api/')) {
        return sendJson(res, 404, { error: 'not found' });
      }

      // --- Static frontend ---
      const rel = url.pathname === '/' ? 'index.html' : url.pathname.slice(1);
      const filePath = path.join(PUBLIC_DIR, path.normalize(rel));
      if (!filePath.startsWith(PUBLIC_DIR) || !fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        return res.end('Not found');
      }
      res.writeHead(200, { 'Content-Type': MIME[path.extname(filePath)] || 'application/octet-stream' });
      return res.end(fs.readFileSync(filePath));
    } catch (err) {
      return sendJson(res, err.message === 'Invalid JSON body' ? 400 : 500, { error: err.message });
    }
  });
}

module.exports = { createApp };
