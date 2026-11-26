import { createApp } from 'vue';
import { createI18n } from 'vue-i18n';
import { createRouter, createWebHistory } from 'vue-router';
import './app.css';
import App from './App.vue';
import de from './locales/de.json';
import { applyBranding, loadUser, state } from './store';
import DashboardView from './views/DashboardView.vue';
import MembersView from './views/MembersView.vue';
import MemberProfileView from './views/MemberProfileView.vue';
import ComingSoonView from './views/ComingSoonView.vue';
import AccountLayout from './views/account/AccountLayout.vue';
import AccountProfile from './views/account/AccountProfile.vue';
import AccountContact from './views/account/AccountContact.vue';
import AccountMobility from './views/account/AccountMobility.vue';
import AccountSecurity from './views/account/AccountSecurity.vue';

const cfg = window.acmApp ?? { base: '/mitglieder/' };

// New languages: add src/locales/<code>.json and register it here.
const i18n = createI18n({ legacy: false, locale: 'de', fallbackLocale: 'de', messages: { de } });

const router = createRouter({
  history: createWebHistory(cfg.base),
  routes: [
    { path: '/', name: 'dashboard', component: DashboardView },
    { path: '/events', name: 'events', component: ComingSoonView, props: { feature: 'events' } },
    { path: '/members', name: 'members', component: MembersView },
    { path: '/members/:id(\\d+)', name: 'member', component: MemberProfileView },
    { path: '/my-rides', name: 'my-rides', component: ComingSoonView, props: { feature: 'rides' } },
    { path: '/feedback', name: 'feedback', component: ComingSoonView, props: { feature: 'feedback' } },
    {
      path: '/account',
      component: AccountLayout,
      redirect: '/account/profile',
      children: [
        { path: 'profile', name: 'account-profile', component: AccountProfile },
        { path: 'contact', name: 'account-contact', component: AccountContact },
        { path: 'mobility', name: 'account-mobility', component: AccountMobility },
        { path: 'security', name: 'account-security', component: AccountSecurity },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async () => {
  if (!state.loaded) await loadUser();
});

applyBranding(cfg.branding);
createApp(App).use(i18n).use(router).mount('#acm-app');
