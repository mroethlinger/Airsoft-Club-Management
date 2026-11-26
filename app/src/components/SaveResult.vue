<script setup>
defineProps({ result: { type: Object, default: null } });
</script>

<template>
  <div v-if="result" :class="result.ok ? 'text-primary' : 'text-red-400'" class="text-sm" role="status">
    <template v-if="result.ok">{{ $t('profile.saved') }}</template>
    <p v-for="m in result.errors" :key="m">{{ m }}</p>
  </div>
</template>
