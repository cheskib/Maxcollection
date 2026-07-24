'use strict';

const fmt = (cents) => (cents / 100).toLocaleString(undefined, { style: 'currency', currency: 'USD' });

async function api(path, options) {
  const res = await fetch(path, options);
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.error || `Request failed (${res.status})`);
  return body;
}

async function refresh() {
  const [summary, members] = await Promise.all([api('/api/summary'), api('/api/members')]);

  document.getElementById('stat-members').textContent = summary.members;
  document.getElementById('stat-charged').textContent = fmt(summary.charged_cents);
  document.getElementById('stat-paid').textContent = fmt(summary.paid_cents);
  document.getElementById('stat-outstanding').textContent = fmt(summary.outstanding_cents);

  for (const select of document.querySelectorAll('[data-member-select]')) {
    const current = select.value;
    select.innerHTML = '<option value="" disabled selected>Select member *</option>' +
      members.map((m) => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');
    if (current) select.value = current;
  }

  const tbody = document.querySelector('#members-table tbody');
  tbody.innerHTML = members.map((m) => {
    const balance = m.charged_cents - m.paid_cents;
    const contact = [m.email, m.phone].filter(Boolean).map(escapeHtml).join(' · ');
    return `<tr>
      <td>${escapeHtml(m.name)}</td>
      <td>${contact}</td>
      <td class="num">${fmt(m.charged_cents)}</td>
      <td class="num">${fmt(m.paid_cents)}</td>
      <td class="num ${balance > 0 ? 'owed' : ''}">${fmt(balance)}</td>
      <td><button class="danger" data-delete="${m.id}">Delete</button></td>
    </tr>`;
  }).join('') || '<tr><td colspan="6" class="empty">No members yet — add one above.</td></tr>';
}

function escapeHtml(text) {
  return String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function bindForm(id, endpoint) {
  document.getElementById(id).addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const payload = Object.fromEntries(new FormData(form).entries());
    try {
      await api(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      form.reset();
      await refresh();
    } catch (err) {
      alert(err.message);
    }
  });
}

document.querySelector('#members-table tbody').closest('table').addEventListener('click', async (event) => {
  const id = event.target.dataset.delete;
  if (!id) return;
  if (!confirm('Delete this member and all their charges/payments?')) return;
  try {
    await api(`/api/members/${id}`, { method: 'DELETE' });
    await refresh();
  } catch (err) {
    alert(err.message);
  }
});

bindForm('member-form', '/api/members');
bindForm('charge-form', '/api/charges');
bindForm('payment-form', '/api/payments');
refresh().catch((err) => alert(err.message));
