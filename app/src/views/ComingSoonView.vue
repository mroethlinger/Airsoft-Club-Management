<script setup>
defineProps({ feature: { type: String, required: true } });
</script>

<template>
  <div class="card space-y-2">
    <h1 class="text-2xl font-bold">{{ $t('soon.' + feature) }}</h1>
    <p class="opacity-70">{{ $t('soon.text') }}</p>
  </div>
</template>
