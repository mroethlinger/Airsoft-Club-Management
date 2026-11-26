<script setup>
import { state } from '../../store';
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">{{ $t('account.security') }}</h1>
    <div v-if="state.user.needs_2fa" class="card border border-yellow-500/40 text-sm text-yellow-200">🔒 {{ $t('security.required') }}</div>
    <div class="card space-y-3 text-sm">
      <p>{{ $t('security.info') }}</p>
      <a :href="state.user.profile_url" class="btn inline-block">{{ $t('security.open') }}</a>
    </div>
  </div>
</template>
