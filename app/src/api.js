// REST client for wp-json/acm/v1 with WordPress cookie authentication.
// Every request sends X-WP-Nonce; when the nonce expired, the client fetches a fresh one once and retries.

const config = () => window.acmApp ?? { api: '/wp-json/acm/v1', nonce: '' };

export class ApiError extends Error {
  constructor(status, code, message, data) {
    super(message || code || `HTTP ${status}`);
    this.status = status;
    this.code = code;
    this.data = data;
  }
}

export function createClient({ fetchImpl = (...a) => fetch(...a), getConfig = config } = {}) {
  async function refreshNonce() {
    const cfg = getConfig();
    const res = await fetchImpl(`${cfg.api}/nonce`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' },
    });
    if (!res.ok) return false;
    const body = await res.json();
    cfg.nonce = body.nonce;
    return true;
  }

  async function request(method, path, body, retried = false) {
    const cfg = getConfig();
    const headers = { Accept: 'application/json', 'X-WP-Nonce': cfg.nonce };
    const init = { method, credentials: 'same-origin', headers };
    if (body instanceof FormData) {
      init.body = body;
    } else if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetchImpl(cfg.api + path, init);
    } catch (e) {
      throw new ApiError(0, 'offline', e.message);
    }
    const text = await res.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      data = null;
    }
    if (res.ok) return data;
    const code = data?.code ?? '';
    if (!retried && (code === 'rest_cookie_invalid_nonce' || (res.status === 403 && code === 'rest_forbidden' && cfg.nonce))) {
      if (await refreshNonce().catch(() => false)) return request(method, path, body, true);
    }
    throw new ApiError(res.status, code, data?.message, data);
  }

  return {
    get: (p) => request('GET', p),
    post: (p, b) => request('POST', p, b ?? {}),
    patch: (p, b) => request('PATCH', p, b),
    del: (p) => request('DELETE', p),
    refreshNonce,
  };
}

const api = createClient();
export default api;

/** Readable messages from an error. */
export function errorsOf(e, t = (k) => k) {
  if (e instanceof ApiError) {
    if (e.status === 0) return [t('errors.offline')];
    if (e.status === 401) return [t('errors.signedOut')];
    if (e.status === 403 && !e.message) return [t('errors.forbidden')];
    return [e.message || t('common.error')];
  }
  return [t('common.error')];
}
