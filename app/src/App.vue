<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Avatar from './components/Avatar.vue';
import NotificationBell from './components/NotificationBell.vue';
import { state, logout } from './store';

const route = useRoute();
const homeUrl = window.acmApp?.homeUrl ?? '/';
const u = computed(() => state.user);

// Everything a member needs every day: always visible (top bar on desktop, tab bar on phones).
const primary = computed(() => [
  { to: '/', label: 'nav.dashboard', icon: '🏠', exact: true },
  { to: '/events', label: 'nav.events', icon: '📅' },
  { to: '/members', label: u.value?.is_lead ? 'nav.members' : 'nav.myRegion', icon: '👥' },
  { to: '/my-rides', label: 'nav.myRides', icon: '🚗' },
]);

// Personal area (avatar menu on desktop, "Menü" sheet on phones).
const personal = [
  { to: '/account', label: 'nav.account', icon: '⚙️' },
  { to: '/feedback', label: 'nav.feedback', icon: '📬' },
];

// "Verwaltung": entries the user may open. Phase 3 only links to wp-admin; lead tools follow with their phases.
const admin = computed(() => {
  const x = u.value;
  if (!x) return [];
  return [{ href: x.admin_url, label: 'nav.wpAdmin', icon: '🛠️', show: !!x.admin_url }].filter((l) => l.show);
});
const isActive = (l) => (l.exact ? route.path === l.to : route.path.startsWith(l.to));

// Open menus: 'admin' and 'profile' on desktop, 'sheet' on phones.
const open = ref(null);
const toggle = (name) => (open.value = open.value === name ? null : name);
watch(() => route.path, () => (open.value = null));
const closeOutside = () => (open.value = null);
const closeOnEscape = (e) => e.key === 'Escape' && (open.value = null);
onMounted(() => {
  document.addEventListener('click', closeOutside);
  document.addEventListener('keydown', closeOnEscape);
});
onBeforeUnmount(() => {
  document.removeEventListener('click', closeOutside);
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <div class="min-h-screen">
    <header v-if="u" class="relative z-30 border-b border-white/10 bg-surface">
      <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2.5 md:gap-5 md:py-3">
        <RouterLink to="/" class="flex min-w-0 items-center gap-3" :title="$t('nav.dashboard')">
          <img v-if="state.branding?.logo_url" :src="state.branding.logo_url" alt="" class="h-9 w-9 shrink-0 rounded object-contain" />
          <span class="truncate font-bold text-primary">{{ state.branding?.org_short_name || state.branding?.org_name }}</span>
        </RouterLink>

        <nav class="hidden items-center gap-1 text-sm md:flex" :aria-label="$t('nav.main')">
          <RouterLink v-for="l in primary" :key="l.to" :to="l.to" class="rounded-lg px-3 py-2 hover:bg-white/10" :class="isActive(l) && 'bg-white/10 text-primary'">
            {{ $t(l.label) }}
          </RouterLink>
          <div v-if="admin.length" class="relative">
            <button type="button" class="flex items-center gap-1 rounded-lg px-3 py-2 hover:bg-white/10" aria-haspopup="true" :aria-expanded="open === 'admin'" @click.stop="toggle('admin')">
              {{ $t('nav.admin') }} <span class="text-xs" aria-hidden="true">▾</span>
            </button>
            <div v-if="open === 'admin'" class="absolute left-0 top-full mt-2 min-w-52 rounded-lg border border-white/10 bg-surface py-1 shadow-xl">
              <a v-for="l in admin" :key="l.href" :href="l.href" class="flex items-center gap-2 px-4 py-2 hover:bg-white/10 hover:text-primary">
                <span aria-hidden="true">{{ l.icon }}</span>{{ $t(l.label) }}
              </a>
            </div>
          </div>
        </nav>

        <div class="ml-auto flex items-center gap-2">
          <NotificationBell />
          <div class="relative hidden md:block">
            <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-white/10" aria-haspopup="true" :aria-expanded="open === 'profile'" @click.stop="toggle('profile')">
              <Avatar :src="u.avatar_url" :name="u.name" :size="32" />
              <span class="max-w-32 truncate">{{ u.nickname || u.first_name }}</span> <span class="text-xs" aria-hidden="true">▾</span>
            </button>
            <div v-if="open === 'profile'" class="absolute right-0 top-full mt-2 min-w-52 rounded-lg border border-white/10 bg-surface py-1 shadow-xl">
              <RouterLink v-for="l in personal" :key="l.to" :to="l.to" class="flex items-center gap-2 px-4 py-2 hover:bg-white/10 hover:text-primary"><span aria-hidden="true">{{ l.icon }}</span>{{ $t(l.label) }}</RouterLink>
              <button class="flex w-full items-center gap-2 border-t border-white/10 px-4 py-2 text-left hover:bg-white/10" @click="logout"><span aria-hidden="true">🚪</span>{{ $t('common.logout') }}</button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <RouterLink v-if="u?.needs_2fa" to="/account/security" class="block bg-yellow-500/20 px-4 py-2 text-center text-sm text-yellow-200 hover:bg-yellow-500/30">🔒 {{ $t('security.required') }}</RouterLink>
    <div v-if="state.offline" class="bg-yellow-500/20 px-4 py-2 text-center text-sm text-yellow-300">{{ $t('errors.offline') }}</div>

    <main class="mx-auto max-w-6xl px-4 py-6 md:py-8" :class="u && 'pb-28 md:pb-8'">
      <RouterView v-if="u" />
      <p v-else-if="state.loaded" class="opacity-70">{{ $t('errors.signedOut') }}</p>
      <p v-else class="opacity-70">{{ $t('common.loading') }}</p>
    </main>
    <footer class="mx-auto max-w-6xl px-4 pb-8 pt-4 text-center text-xs opacity-70" :class="u && 'pb-28 md:pb-8'">
      <a :href="homeUrl"class="hover:text-primary hover:underline">{{ $t('nav.website') }}</a>
      <span v-if="state.branding"> · © {{ new Date().getFullYear() }} {{ state.branding.org_name }}</span>
    </footer>

    <!-- Phones: tab bar at the thumb, everything else in the "Menü" sheet -->
    <template v-if="u">
      <div v-if="open === 'sheet'" class="fixed inset-0 z-40 bg-black/60 md:hidden" aria-hidden="true" @click="open = null" />
      <section v-if="open === 'sheet'" class="fixed inset-x-0 bottom-16 z-50 max-h-[75vh] overflow-y-auto rounded-t-2xl border-t border-white/10 bg-surface p-4 pb-6 shadow-2xl md:hidden" :aria-label="$t('nav.menu')" @click.stop>
        <div class="mb-3 flex items-center gap-3">
          <Avatar :src="u.avatar_url" :name="u.name" :size="44" />
          <div class="min-w-0">
            <div class="truncate font-semibold">{{ u.nickname || u.name }}</div>
            <div class="truncate text-xs opacity-70">{{ u.region?.name }}<template v-if="u.squad"> · {{ u.squad.name }}</template></div>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
          <RouterLink v-for="l in personal" :key="l.to" :to="l.to" class="rounded-xl bg-white/5 px-2 py-3 active:bg-white/10">
            <div class="text-xl" aria-hidden="true">{{ l.icon }}</div>{{ $t(l.label) }}
          </RouterLink>
        </div>
        <template v-if="admin.length">
          <h2 class="mb-2 mt-4 text-xs font-semibold uppercase opacity-60">{{ $t('nav.admin') }}</h2>
          <div class="grid grid-cols-3 gap-2 text-center text-xs">
            <a v-for="l in admin" :key="l.href" :href="l.href" class="rounded-xl bg-white/5 px-2 py-3 active:bg-white/10">
              <div class="text-xl" aria-hidden="true">{{ l.icon }}</div>{{ $t(l.label) }}
            </a>
          </div>
        </template>
        <button class="btn-ghost mt-4 w-full" @click="logout">🚪 {{ $t('common.logout') }}</button>
      </section>

      <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-surface pb-[env(safe-area-inset-bottom)] md:hidden" :aria-label="$t('nav.main')">
        <ul class="grid grid-cols-5 text-center text-[11px]">
          <li v-for="l in primary" :key="l.to">
            <RouterLink :to="l.to" class="flex flex-col items-center gap-0.5 py-2" :class="isActive(l) ? 'text-primary' : 'opacity-80'">
              <span class="text-xl leading-none" aria-hidden="true">{{ l.icon }}</span>{{ $t(l.label) }}
            </RouterLink>
          </li>
          <li>
            <button type="button" class="flex w-full flex-col items-center gap-0.5 py-2" :class="open === 'sheet' ? 'text-primary' : 'opacity-80'" :aria-expanded="open === 'sheet'" @click.stop="toggle('sheet')">
              <span class="text-xl leading-none" aria-hidden="true">☰</span>{{ $t('nav.menu') }}
            </button>
          </li>
        </ul>
      </nav>
    </template>
  </div>
</template>
