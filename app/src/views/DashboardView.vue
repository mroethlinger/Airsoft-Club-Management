<script setup>
import { state } from '../store';
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">{{ $t('dashboard.welcome', { name: state.user.nickname || state.user.first_name }) }}</h1>

    <!-- 1. Events (phase 4) -->
    <div class="grid gap-4 lg:grid-cols-2">
      <section class="card space-y-3">
        <h2 class="text-lg font-semibold">{{ $t('dashboard.myEvents') }}</h2>
        <p class="text-sm opacity-70">{{ $t('dashboard.noMyEvents') }}</p>
      </section>
      <section class="card space-y-3">
        <h2 class="text-lg font-semibold">{{ $t('dashboard.newestEvents') }}</h2>
        <p class="text-sm opacity-70">{{ $t('dashboard.noEvents') }}</p>
        <RouterLink to="/events" class="text-sm text-primary hover:underline">{{ $t('dashboard.allEvents') }} →</RouterLink>
      </section>
    </div>

    <!-- 2. Tasks for leads (phase 8) -->
    <section v-if="state.user.is_lead" class="card space-y-3">
      <h2 class="text-lg font-semibold">{{ $t('dashboard.tasks') }}</h2>
      <p class="text-sm opacity-70">🎉 {{ $t('dashboard.noTasks') }}</p>
    </section>

    <!-- 3. Profile facts (least important: at the bottom) -->
    <div class="grid gap-4 sm:grid-cols-3">
      <div class="card">
        <div class="text-sm opacity-70">{{ $t('dashboard.region') }}</div>
        <div class="text-lg font-semibold">{{ state.user.region?.name ?? '–' }}</div>
      </div>
      <div v-if="state.user.region?.squads_enabled !== false" class="card">
        <div class="text-sm opacity-70">{{ $t('dashboard.squad') }}</div>
        <div class="text-lg font-semibold">{{ state.user.squad?.name ?? $t('dashboard.noSquad') }}</div>
      </div>
      <div class="card">
        <div class="text-sm opacity-70">{{ $t('dashboard.roles') }}</div>
        <ul class="text-lg font-semibold">
          <li v-if="!state.user.roles.length">{{ $t('dashboard.noRoles') }}</li>
          <li v-for="(r, i) in state.user.roles" :key="i">
            {{ $t('roles.' + r.role) }}<template v-if="r.scope_name"> · {{ r.scope_name }}</template>
            <span v-if="r.is_deputy" class="text-sm font-normal opacity-70">({{ $t('dashboard.deputy') }})</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
