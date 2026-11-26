<script setup>
import { reactive } from 'vue';
import SaveResult from '../../components/SaveResult.vue';
import { state } from '../../store';
import { useSave } from './useSave';

const u = state.user;
const SOCIAL = [
  { key: 'discord', label: 'Discord', hint: 'Benutzername' },
  { key: 'asvz', label: 'ASVZ-Name', hint: 'Name im ASVZ' },
  { key: 'facebook', label: 'Facebook', hint: 'Profilname oder -link' },
  { key: 'instagram', label: 'Instagram', hint: '@benutzername' },
  { key: 'telegram', label: 'Telegram', hint: '@benutzername' },
  { key: 'whatsapp', label: 'WhatsApp', hint: 'Nummer' },
];
const form = reactive({
  phone: u.phone ?? '',
  share_phone: !!u.share_phone,
  share_email: !!u.share_email,
  social: Object.fromEntries(SOCIAL.map((s) => [s.key, u.social?.[s.key] ?? ''])),
});
const { result, busy, save } = useSave();
const profileUrl = u.profile_url;
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">{{ $t('account.contact') }}</h1>

    <div class="card space-y-2 text-sm">
      <h2 class="text-lg font-semibold">{{ $t('common.email') }}</h2>
      <p>{{ u.email }}</p>
      <p class="opacity-70">{{ $t('account.emailHint') }} <a :href="profileUrl" class="text-primary hover:underline">{{ $t('account.wpProfile') }}</a></p>
    </div>

    <form class="card space-y-4" @submit.prevent="save(form)">
      <h2 class="text-lg font-semibold">{{ $t('account.phoneAndSocial') }}</h2>
      <p class="text-sm opacity-70">{{ $t('account.socialPrivacy') }}</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm">{{ $t('profile.phone') }}<input v-model="form.phone" type="tel" class="field mt-1" maxlength="40" autocomplete="tel" /></label>
        <label v-for="s in SOCIAL" :key="s.key" class="text-sm">{{ s.label }}
          <input v-model="form.social[s.key]" class="field mt-1" maxlength="100" :placeholder="s.hint" />
        </label>
      </div>
      <div class="space-y-2 rounded-lg bg-white/5 p-3 text-sm">
        <p class="font-semibold">{{ $t('account.shareTitle') }}</p>
        <label class="flex items-center gap-2"><input v-model="form.share_phone" type="checkbox" class="accent-[var(--c-primary)]" /> {{ $t('account.sharePhone') }}</label>
        <label class="flex items-center gap-2"><input v-model="form.share_email" type="checkbox" class="accent-[var(--c-primary)]" /> {{ $t('account.shareEmail') }}</label>
      </div>
      <SaveResult :result="result" />
      <button class="btn" :disabled="busy">{{ $t('common.save') }}</button>
    </form>
  </div>
</template>
