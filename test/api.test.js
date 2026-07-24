'use strict';

const { test, before, after } = require('node:test');
const assert = require('node:assert');
const { openDb } = require('../src/db');
const { createApp } = require('../src/app');

let server;
let base;

before(async () => {
  const db = openDb(':memory:');
  server = createApp(db);
  await new Promise((resolve) => server.listen(0, resolve));
  base = `http://localhost:${server.address().port}`;
});

after(() => server.close());

async function api(method, path, body) {
  const res = await fetch(base + path, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, body: await res.json() };
}

test('member lifecycle: create, charge, pay, balance, delete', async () => {
  const created = await api('POST', '/api/members', { name: 'Test Member', email: 'test@example.com' });
  assert.strictEqual(created.status, 201);
  assert.strictEqual(created.body.name, 'Test Member');
  const id = created.body.id;

  const charge = await api('POST', '/api/charges', { member_id: id, description: 'Annual dues', amount: '180.00' });
  assert.strictEqual(charge.status, 201);
  assert.strictEqual(charge.body.amount_cents, 18000);

  const payment = await api('POST', '/api/payments', { member_id: id, amount: 100, method: 'check', reference: '1042' });
  assert.strictEqual(payment.status, 201);
  assert.strictEqual(payment.body.amount_cents, 10000);
  assert.strictEqual(payment.body.method, 'check');

  const detail = await api('GET', `/api/members/${id}`);
  assert.strictEqual(detail.status, 200);
  assert.strictEqual(detail.body.charged_cents, 18000);
  assert.strictEqual(detail.body.paid_cents, 10000);
  assert.strictEqual(detail.body.charges.length, 1);
  assert.strictEqual(detail.body.payments.length, 1);

  const summary = await api('GET', '/api/summary');
  assert.strictEqual(summary.status, 200);
  assert.strictEqual(summary.body.outstanding_cents, 8000);

  const deleted = await api('DELETE', `/api/members/${id}`);
  assert.strictEqual(deleted.status, 200);
  const afterDelete = await api('GET', `/api/members/${id}`);
  assert.strictEqual(afterDelete.status, 404);
});

test('validation: rejects bad input', async () => {
  const noName = await api('POST', '/api/members', { email: 'x@example.com' });
  assert.strictEqual(noName.status, 400);

  const member = await api('POST', '/api/members', { name: 'Validator' });
  const id = member.body.id;

  const badAmount = await api('POST', '/api/charges', { member_id: id, description: 'x', amount: -5 });
  assert.strictEqual(badAmount.status, 400);

  const zeroAmount = await api('POST', '/api/payments', { member_id: id, amount: 0 });
  assert.strictEqual(zeroAmount.status, 400);

  const badMember = await api('POST', '/api/charges', { member_id: 999999, description: 'x', amount: 10 });
  assert.strictEqual(badMember.status, 400);

  const unknownMethod = await api('POST', '/api/payments', { member_id: id, amount: 5, method: 'barter' });
  assert.strictEqual(unknownMethod.status, 201);
  assert.strictEqual(unknownMethod.body.method, 'other');
});

test('unknown API route returns 404 JSON', async () => {
  const res = await api('GET', '/api/nope');
  assert.strictEqual(res.status, 404);
  assert.ok(res.body.error);
});
