<script setup>
import { reactive } from 'vue';
import AvatarUpload from '../../components/AvatarUpload.vue';
import SaveResult from '../../components/SaveResult.vue';
import { state, loadUser } from '../../store';
import { useSave } from './useSave';

const u = state.user;
const form = reactive({
  first_name: u.first_name ?? '',
  last_name: u.last_name ?? '',
  nickname: u.nickname ?? '',
  name_display: u.name_display ?? 'full',
  street: u.street ?? '',
  postal_code: u.postal_code ?? '',
  city: u.city ?? '',
  country: u.country ?? 'DE',
  combat_role: u.combat_role ?? '',
  play_style: u.play_style ?? '',
});
// Preview of how other members see the name.
const preview = (mode) => {
  const abbr = `${form.first_name} ${form.last_name.charAt(0)}.`.trim();
  if (mode === 'nickname') return form.nickname || abbr;
  return mode === 'abbreviated' ? abbr : `${form.first_name} ${form.last_name}`.trim();
};
const { result, busy, save } = useSave();
const submit = () => save({ ...form, country: form.country.toUpperCase() });
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">{{ $t('account.profile') }}</h1>

    <div class="card space-y-3">
      <h2 class="text-lg font-semibold">{{ $t('avatar.title') }}</h2>
      <AvatarUpload :member-id="state.user.id" :url="state.user.avatar_url" :name="state.user.name" @changed="loadUser" />
    </div>

    <form class="card space-y-4" @submit.prevent="submit">
      <h2 class="text-lg font-semibold">{{ $t('profile.personal') }}</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm">{{ $t('profile.firstName') }}<input v-model="form.first_name" class="field mt-1" required maxlength="100" autocomplete="given-name" /></label>
        <label class="text-sm">{{ $t('profile.lastName') }}<input v-model="form.last_name" class="field mt-1" required maxlength="100" autocomplete="family-name" /></label>
        <label class="text-sm">{{ $t('profile.nickname') }}<input v-model="form.nickname" class="field mt-1" maxlength="50" /></label>
        <fieldset class="space-y-2 rounded-lg bg-white/5 p-3 text-sm sm:col-span-2">
          <legend class="px-1 font-semibold">{{ $t('profile.nameDisplay.title') }}</legend>
          <p class="text-xs opacity-70">{{ $t('profile.nameDisplay.info') }}</p>
          <label v-for="n in ['full', 'abbreviated', 'nickname']" :key="n" class="flex items-center gap-2">
            <input v-model="form.name_display" type="radio" :value="n" class="accent-[var(--c-primary)]" />
            {{ $t('profile.nameDisplay.' + n) }}
            <span class="opacity-60">→ {{ preview(n) }}</span>
          </label>
          <p v-if="form.name_display === 'nickname' && !form.nickname" class="text-xs text-yellow-300">⚠️ {{ $t('profile.nameDisplay.needNickname') }}</p>
        </fieldset>
      </div>

      <h2 class="pt-2 text-lg font-semibold">{{ $t('profile.address') }}</h2>
      <div class="grid gap-4 sm:grid-cols-6">
        <label class="text-sm sm:col-span-6">{{ $t('profile.street') }}<input v-model="form.street" class="field mt-1" maxlength="150" autocomplete="street-address" /></label>
        <label class="text-sm sm:col-span-2">{{ $t('profile.postalCode') }}<input v-model="form.postal_code" class="field mt-1" maxlength="16" autocomplete="postal-code" /></label>
        <label class="text-sm sm:col-span-3">{{ $t('profile.city') }}<input v-model="form.city" class="field mt-1" maxlength="100" autocomplete="address-level2" /></label>
        <label class="text-sm sm:col-span-1">{{ $t('profile.country') }}<input v-model="form.country" class="field mt-1 uppercase" maxlength="2" minlength="2" required autocomplete="country" /></label>
      </div>

      <h2 class="pt-2 text-lg font-semibold">{{ $t('profile.playProfile') }}</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm">{{ $t('profile.combatRole') }}
          <select v-model="form.combat_role" class="field mt-1">
            <option value="">{{ $t('profile.notSpecified') }}</option>
            <option v-for="r in ['shooter', 'driver', 'sniper', 'lone_fighter']" :key="r" :value="r">{{ $t('profile.roles.' + r) }}</option>
          </select>
        </label>
        <label class="text-sm">{{ $t('profile.playStyle') }}
          <select v-model="form.play_style" class="field mt-1">
            <option value="">{{ $t('profile.notSpecified') }}</option>
            <option v-for="s in ['reenactment', 'militia', 'independent']" :key="s" :value="s">{{ $t('profile.styles.' + s) }}</option>
          </select>
        </label>
      </div>

      <dl class="grid gap-2 text-sm sm:grid-cols-3">
        <div><dt class="opacity-70">{{ $t('dashboard.region') }}</dt><dd>{{ state.user.region?.name ?? '–' }}</dd></div>
        <div><dt class="opacity-70">{{ $t('dashboard.squad') }}</dt><dd>{{ state.user.squad?.name ?? $t('dashboard.noSquad') }}</dd></div>
        <div v-if="state.user.birth_date"><dt class="opacity-70">{{ $t('profile.birthDate') }}</dt><dd>{{ state.user.birth_date }} <span class="text-xs opacity-70">({{ $t('profile.leadOnly') }})</span></dd></div>
      </dl>

      <SaveResult :result="result" />
      <button class="btn" :disabled="busy">{{ $t('common.save') }}</button>
    </form>
  </div>
</template>
