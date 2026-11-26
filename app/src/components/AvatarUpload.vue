<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api, { errorsOf } from '../api';
import Avatar from './Avatar.vue';

const props = defineProps({
  memberId: { type: Number, required: true },
  url: { type: String, default: null },
  name: { type: String, default: '' },
});
const emit = defineEmits(['changed']);
const { t } = useI18n();

const errors = ref([]);
const busy = ref(false);

async function upload(e) {
  const file = e.target.files[0];
  e.target.value = '';
  if (!file) return;
  busy.value = true;
  errors.value = [];
  try {
    const fd = new FormData();
    fd.append('file', file);
    await api.post(`/members/${props.memberId}/avatar`, fd);
    emit('changed');
  } catch (err) {
    errors.value = errorsOf(err, t);
  } finally {
    busy.value = false;
  }
}

async function remove() {
  busy.value = true;
  try {
    await api.del(`/members/${props.memberId}/avatar`);
    emit('changed');
  } catch (err) {
    errors.value = errorsOf(err, t);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="flex items-center gap-4">
    <Avatar :src="url" :name="name" :size="96" />
    <div class="space-y-2 text-sm">
      <label class="btn-ghost inline-block cursor-pointer">
        {{ $t('avatar.choose') }}
        <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" :disabled="busy" @change="upload" />
      </label>
      <button v-if="url" type="button" class="btn-ghost ml-2" :disabled="busy" @click="remove">{{ $t('avatar.remove') }}</button>
      <p class="text-xs opacity-70">{{ $t('avatar.hint') }}</p>
      <p v-for="m in errors" :key="m" class="text-red-400">{{ m }}</p>
    </div>
  </div>
</template>
