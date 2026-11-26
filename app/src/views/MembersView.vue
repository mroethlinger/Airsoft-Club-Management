<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api, { errorsOf } from '../api';
import Avatar from '../components/Avatar.vue';
import { state } from '../store';

const { t } = useI18n();
const members = ref([]);
const loaded = ref(false);
const errors = ref([]);
const search = ref('');

onMounted(async () => {
  try {
    members.value = (await api.get('/members')).data;
  } catch (e) {
    errors.value = errorsOf(e, t);
  }
  loaded.value = true;
});

const name = (m) => `${m.first_name} ${m.last_name}`.trim();
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return members.value;
  return members.value.filter((m) => [name(m), m.nickname, m.squad, m.region].filter(Boolean).join(' ').toLowerCase().includes(q));
});
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-bold">{{ state.user.is_lead ? $t('nav.members') : $t('nav.myRegion') }}</h1>
    <input v-model="search" type="search" class="field max-w-md" :placeholder="$t('members.search')" :aria-label="$t('members.search')" />
    <p v-for="m in errors" :key="m" class="text-red-400">{{ m }}</p>
    <ul v-if="filtered.length" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="m in filtered" :key="m.id">
        <RouterLink :to="`/members/${m.id}`" class="flex items-center gap-3 rounded-xl bg-surface p-3 hover:bg-white/10">
          <Avatar :src="m.avatar_url" :name="name(m)" :size="44" />
          <div class="min-w-0">
            <div class="truncate font-semibold">{{ name(m) }}<span v-if="m.nickname && m.nickname !== m.first_name" class="font-normal opacity-70"> „{{ m.nickname }}“</span></div>
            <div class="truncate text-xs opacity-70">{{ m.region }}<template v-if="m.squad"> · {{ m.squad }}</template></div>
          </div>
        </RouterLink>
      </li>
    </ul>
    <p v-else-if="loaded" class="opacity-70">{{ $t('members.none') }}</p>
  </div>
</template>
