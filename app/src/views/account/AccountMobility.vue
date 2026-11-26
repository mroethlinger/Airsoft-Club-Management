<script setup>
import { reactive } from 'vue';
import SaveResult from '../../components/SaveResult.vue';
import { state } from '../../store';
import { useSave } from './useSave';

const u = state.user;
const form = reactive({
  can_drive: !!u.can_drive,
  vehicle_seats: u.vehicle_seats ?? '',
  vehicle_description: u.vehicle_description ?? '',
  needs_ride: !!u.needs_ride,
  mobility_notes: u.mobility_notes ?? '',
});
const { result, busy, save } = useSave();
const submit = () => save({ ...form, vehicle_seats: form.vehicle_seats === '' ? null : Number(form.vehicle_seats) });
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">{{ $t('account.mobility') }}</h1>
    <form class="card space-y-4" @submit.prevent="submit">
      <p class="text-sm opacity-70">{{ $t('mobility.info') }}</p>
      <label class="flex items-center gap-2"><input v-model="form.can_drive" type="checkbox" /> {{ $t('mobility.canDrive') }}</label>
      <div v-if="form.can_drive" class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm">{{ $t('mobility.seats') }}<input v-model="form.vehicle_seats" type="number" min="0" max="8" class="field mt-1" /></label>
        <label class="text-sm">{{ $t('mobility.vehicle') }}<input v-model="form.vehicle_description" class="field mt-1" maxlength="150" :placeholder="$t('mobility.vehicleHint')" /></label>
      </div>
      <label class="flex items-center gap-2"><input v-model="form.needs_ride" type="checkbox" /> {{ $t('mobility.needsRide') }}</label>
      <label class="block text-sm">{{ $t('mobility.notes') }}<input v-model="form.mobility_notes" class="field mt-1" maxlength="255" :placeholder="$t('mobility.notesHint')" /></label>
      <SaveResult :result="result" />
      <button class="btn" :disabled="busy">{{ $t('common.save') }}</button>
    </form>
  </div>
</template>
