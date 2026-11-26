// Unit tests for the REST client (node --test, no browser needed).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createClient, ApiError } from '../src/api.js';

function fakeFetch(responses) {
  const calls = [];
  const impl = async (url, init) => {
    calls.push({ url, init });
    const r = responses.shift();
    return { ok: r.status < 400, status: r.status, text: async () => JSON.stringify(r.body ?? null), json: async () => r.body };
  };
  return { impl, calls };
}

test('sends nonce and JSON body', async () => {
  const cfg = { api: '/wp-json/acm/v1', nonce: 'n1' };
  const { impl, calls } = fakeFetch([{ status: 200, body: { data: 1 } }]);
  const api = createClient({ fetchImpl: impl, getConfig: () => cfg });
  assert.deepEqual(await api.patch('/me', { city: 'X' }), { data: 1 });
  assert.equal(calls[0].url, '/wp-json/acm/v1/me');
  assert.equal(calls[0].init.headers['X-WP-Nonce'], 'n1');
  assert.equal(calls[0].init.body, '{"city":"X"}');
});

test('refreshes an expired nonce once and retries', async () => {
  const cfg = { api: '/api', nonce: 'old' };
  const { impl, calls } = fakeFetch([
    { status: 403, body: { code: 'rest_cookie_invalid_nonce' } },
    { status: 200, body: { nonce: 'new' } },
    { status: 200, body: { ok: true } },
  ]);
  const api = createClient({ fetchImpl: impl, getConfig: () => cfg });
  assert.deepEqual(await api.get('/session'), { ok: true });
  assert.equal(calls[1].url, '/api/nonce');
  assert.equal(calls[2].init.headers['X-WP-Nonce'], 'new');
});

test('does not loop when the retry fails too', async () => {
  const cfg = { api: '/api', nonce: 'old' };
  const { impl, calls } = fakeFetch([
    { status: 403, body: { code: 'rest_cookie_invalid_nonce' } },
    { status: 200, body: { nonce: 'new' } },
    { status: 403, body: { code: 'rest_cookie_invalid_nonce', message: 'no' } },
  ]);
  const api = createClient({ fetchImpl: impl, getConfig: () => cfg });
  await assert.rejects(api.get('/x'), (e) => e instanceof ApiError && e.status === 403);
  assert.equal(calls.length, 3);
});

test('maps network failure to status 0', async () => {
  const api = createClient({ fetchImpl: async () => { throw new Error('down'); }, getConfig: () => ({ api: '', nonce: '' }) });
  await assert.rejects(api.get('/x'), (e) => e.status === 0 && e.code === 'offline');
});

test('passes validation messages through', async () => {
  const { impl } = fakeFetch([{ status: 422, body: { code: 'acm_invalid', message: 'Ungültig' } }]);
  const api = createClient({ fetchImpl: impl, getConfig: () => ({ api: '', nonce: 'n' }) });
  await assert.rejects(api.patch('/me', {}), (e) => e.status === 422 && e.message === 'Ungültig');
});
