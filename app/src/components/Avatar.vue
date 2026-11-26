<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  src: { type: String, default: null },
  name: { type: String, default: '' },
  size: { type: Number, default: 40 },
});

// Fall back to initials if there is no photo or it fails to load.
const failed = ref(false);
watch(() => props.src, () => (failed.value = false));

const initials = computed(() =>
  (props.name || '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')
);
</script>

<template>
  <img v-if="src && !failed" :src="src" :alt="name" :style="{ width: size + 'px', height: size + 'px' }" class="shrink-0 rounded-full object-cover" @error="failed = true" />
  <span v-else :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.4 + 'px' }" class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/20 font-semibold text-primary" aria-hidden="true">{{ initials }}</span>
</template>
