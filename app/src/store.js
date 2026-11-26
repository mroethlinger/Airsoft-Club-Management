import { reactive } from 'vue';
import api from './api';

export const state = reactive({
  user: null,
  loaded: false,
  branding: window.acmApp?.branding ?? null,
  offline: false,
});

export function applyBranding(b) {
  if (!b) return;
  state.branding = b;
  const root = document.documentElement.style;
  root.setProperty('--c-primary', b.color_primary);
  root.setProperty('--c-primary-contrast', b.color_primary_contrast);
  root.setProperty('--c-background', b.color_background);
  root.setProperty('--c-surface', b.color_surface);
  root.setProperty('--c-text', b.color_text);
  document.title = b.org_name;
}

/** Loads the signed-in user and the branding. A 401 means the WordPress session ended. */
export async function loadUser() {
  try {
    const res = await api.get('/session');
    state.user = res.data;
    applyBranding(res.branding);
    state.offline = false;
  } catch (e) {
    if (e.status === 401 || e.status === 403) {
      state.user = null;
      signIn();
      return;
    }
    if (e.status === 0) state.offline = true;
    else throw e;
  } finally {
    state.loaded = true;
  }
}

/** Sends the browser to the WordPress login and back to the current page. */
export function signIn() {
  const back = encodeURIComponent(location.href);
  location.assign(`${window.acmApp?.loginUrl ?? '/wp-login.php'}?redirect_to=${back}`);
}

/** WordPress logout (the URL carries its own nonce). */
export function logout() {
  state.user = null;
  location.assign(window.acmApp?.logoutUrl ?? '/wp-login.php?action=logout');
}
