<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api, { errorsOf } from '../api';
import Avatar from '../components/Avatar.vue';

const route = useRoute();
const { t } = useI18n();
const m = ref(null);
const errors = ref([]);

async function load() {
  m.value = null;
  errors.value = [];
  try {
    m.value = (await api.get(`/members/${route.params.id}`)).data;
  } catch (e) {
    errors.value = e.status === 404 ? [t('members.notFound')] : errorsOf(e, t);
  }
}
watch(() => route.params.id, load, { immediate: true });

const SOCIAL = { discord: 'Discord', asvz: 'ASVZ', facebook: 'Facebook', instagram: 'Instagram', telegram: 'Telegram', whatsapp: 'WhatsApp' };
</script>

<template>
  <div class="space-y-4">
    <RouterLink to="/members" class="text-sm text-primary hover:underline">← {{ $t('common.back') }}</RouterLink>
    <p v-for="e in errors" :key="e" class="text-red-400">{{ e }}</p>
    <template v-if="m">
      <div class="card flex items-center gap-4">
        <Avatar :src="m.avatar_url" :name="`${m.first_name} ${m.last_name}`" :size="80" />
        <div class="min-w-0">
          <h1 class="truncate text-2xl font-bold">{{ m.first_name }} {{ m.last_name }}</h1>
          <div v-if="m.nickname" class="opacity-80">„{{ m.nickname }}“</div>
          <div class="text-sm opacity-70">{{ m.region }}<template v-if="m.squad"> · {{ m.squad }}</template></div>
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <section class="card space-y-2">
          <h2 class="text-lg font-semibold">{{ $t('profile.playProfile') }}</h2>
          <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="opacity-70">{{ $t('profile.combatRole') }}</dt><dd>{{ m.combat_role ? $t('profile.roles.' + m.combat_role) : '–' }}</dd>
            <dt class="opacity-70">{{ $t('profile.playStyle') }}</dt><dd>{{ m.play_style ? $t('profile.styles.' + m.play_style) : '–' }}</dd>
            <dt class="opacity-70">{{ $t('members.number') }}</dt><dd>{{ m.member_number ?? '–' }}</dd>
          </dl>
        </section>
        <section class="card space-y-2">
          <h2 class="text-lg font-semibold">{{ $t('members.contact') }}</h2>
          <dl class="grid grid-cols-2 gap-2 text-sm">
            <template v-if="m.phone"><dt class="opacity-70">{{ $t('profile.phone') }}</dt><dd><a :href="`tel:${m.phone}`" class="text-primary">{{ m.phone }}</a></dd></template>
            <template v-if="m.email"><dt class="opacity-70">{{ $t('common.email') }}</dt><dd class="truncate"><a :href="`mailto:${m.email}`" class="text-primary">{{ m.email }}</a></dd></template>
            <template v-for="(v, k) in m.social" :key="k"><dt class="opacity-70">{{ SOCIAL[k] ?? k }}</dt><dd class="truncate">{{ v }}</dd></template>
          </dl>
          <p v-if="!m.phone && !m.email && !Object.keys(m.social || {}).length" class="text-sm opacity-70">{{ $t('members.noContact') }}</p>
        </section>
      </div>
    </template>
  </div>
</template>
