<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const open = ref(false);
const items = ref([]);
const unread = ref(0);
let timer;

async function load() {
  try {
    const data = await api.get('/notifications');
    items.value = data.data;
    unread.value = data.unread;
  } catch {
    /* offline or signed out: keep what we have */
  }
}

const root = ref(null);
function closeOnOutside(e) {
  if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}

onMounted(() => {
  load();
  timer = setInterval(load, 60000);
  window.addEventListener('focus', load);
  document.addEventListener('click', closeOnOutside);
});
onBeforeUnmount(() => {
  clearInterval(timer);
  window.removeEventListener('focus', load);
  document.removeEventListener('click', closeOnOutside);
});

async function go(n) {
  open.value = false;
  if (!n.read) {
    n.read = true;
    unread.value = Math.max(unread.value - 1, 0);
    await api.post(`/notifications/${n.id}/read`).catch(() => {});
  }
  if (n.url) router.push(n.url);
}

async function readAll() {
  await api.post('/notifications/read-all').catch(() => {});
  items.value.forEach((n) => (n.read = true));
  unread.value = 0;
}

const ago = (iso) => {
  const min = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  if (min < 1) return 'gerade eben';
  if (min < 60) return `vor ${min} Min.`;
  if (min < 1440) return `vor ${Math.round(min / 60)} Std.`;
  return new Date(iso).toLocaleDateString('de-DE');
};

function toggle() {
  open.value = !open.value;
  if (open.value) load();
}
</script>

<template>
  <div ref="root" class="relative">
    <button class="relative rounded-md p-2 hover:bg-white/10" :aria-label="$t('notifications.title')" :aria-expanded="open" @click="toggle">
      <span aria-hidden="true">🔔</span>
      <span v-if="unread" class="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] font-bold leading-4 text-primary-contrast">{{ unread > 9 ? '9+' : unread }}</span>
    </button>

    <div v-if="open" class="absolute right-0 z-40 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl border border-white/10 bg-surface shadow-2xl">
      <div class="flex items-center border-b border-white/10 px-4 py-2">
        <span class="font-semibold">{{ $t('notifications.title') }}</span>
        <button v-if="unread" class="ml-auto text-xs text-primary hover:underline" @click="readAll">{{ $t('notifications.readAll') }}</button>
      </div>
      <ul class="max-h-96 overflow-y-auto">
        <li v-for="n in items" :key="n.id">
          <button class="block w-full px-4 py-3 text-left hover:bg-white/5" :class="!n.read && 'bg-primary/10'" @click="go(n)">
            <div class="text-sm" :class="!n.read && 'font-semibold'">{{ n.title }}</div>
            <div v-if="n.body" class="text-xs opacity-80">{{ n.body }}</div>
            <div class="mt-0.5 text-[11px] opacity-50">{{ ago(n.created_at) }}</div>
          </button>
        </li>
        <li v-if="!items.length" class="px-4 py-6 text-center text-sm opacity-60">{{ $t('notifications.none') }}</li>
      </ul>
    </div>
  </div>
</template>
