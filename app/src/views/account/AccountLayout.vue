<script setup>
import Avatar from '../../components/Avatar.vue';
import { state } from '../../store';

const items = [
  { to: '/account/profile', key: 'profile', icon: '👤' },
  { to: '/account/contact', key: 'contact', icon: '✉️' },
  { to: '/account/mobility', key: 'mobility', icon: '🚗' },
  { to: '/account/security', key: 'security', icon: '🔒' },
];
</script>

<template>
  <div class="grid gap-6 md:grid-cols-[16rem_1fr]">
    <aside class="space-y-4">
      <div class="card flex items-center gap-3 p-4">
        <Avatar :src="state.user.avatar_url" :name="state.user.name" :size="48" />
        <div class="min-w-0">
          <div class="truncate font-semibold">{{ state.user.name }}</div>
          <div class="truncate text-xs opacity-70">{{ state.user.email }}</div>
        </div>
      </div>
      <nav class="card flex gap-1 overflow-x-auto p-2 md:flex-col">
        <RouterLink v-for="i in items" :key="i.key" :to="i.to" class="flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm hover:bg-white/10" active-class="bg-primary/15 font-semibold text-primary">
          <span aria-hidden="true">{{ i.icon }}</span>{{ $t('account.' + i.key) }}
        </RouterLink>
      </nav>
    </aside>
    <section class="min-w-0">
      <p v-if="!state.user.has_member_record" class="card text-sm opacity-80">{{ $t('account.noRecord') }}</p>
      <RouterView v-else />
    </section>
  </div>
</template>
