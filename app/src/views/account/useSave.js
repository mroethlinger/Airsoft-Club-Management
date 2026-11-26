import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api, { errorsOf } from '../../api';
import { loadUser } from '../../store';

/** Shared "save profile fields" logic for the account pages (PATCH /me). */
export function useSave() {
  const { t } = useI18n();
  const result = ref(null);
  const busy = ref(false);

  async function save(fields) {
    busy.value = true;
    result.value = null;
    try {
      await api.patch('/me', fields);
      result.value = { ok: true, errors: [] };
      await loadUser();
    } catch (e) {
      result.value = { ok: false, errors: errorsOf(e, t) };
    } finally {
      busy.value = false;
    }
  }

  return { result, busy, save };
}
