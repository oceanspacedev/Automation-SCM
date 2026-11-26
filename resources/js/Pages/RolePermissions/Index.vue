<template>
  <div class="mx-auto w-full max-w-[1040px] space-y-5">
    <div class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><span>Pengaturan</span><ChevronRightIcon class="size-3.5" /><span class="text-gray-800">Role Permission</span></div>
    <div><h1 class="text-xl font-semibold tracking-tight text-gray-950">Role Permission</h1><p class="mt-1 text-xs text-gray-500">Pilih role, lalu tentukan akses modulnya.</p></div>
    <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ loadError }} <button type="button" class="ml-2 underline" @click="loadPermissions">Coba lagi</button></div>
    <div v-else-if="loading" class="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">Memuat hak akses...</div>
    <div v-else>
      <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-gray-200">
        <nav aria-label="Pilih role" class="flex gap-5 sm:gap-7 overflow-x-auto">
          <button v-for="role in roles" :key="role.key" type="button" :disabled="saving" :aria-pressed="selectedRole === role.key" @click="selectedRole = role.key" :class="selectedRole === role.key ? 'border-gray-900 text-gray-950' : 'border-transparent text-gray-500 hover:text-gray-900'" class="whitespace-nowrap border-b-2 px-0.5 py-3 text-xs font-medium transition disabled:opacity-50">{{ role.label }}</button>
        </nav>
        <div class="mb-2 flex items-center gap-3">
          <span v-if="isDirty" class="text-[11px] text-amber-700">Belum disimpan</span>
          <button type="button" :disabled="saving || !isDirty" @click="savePermissions" class="h-8 rounded-md border border-gray-900 bg-gray-900 px-3 text-xs font-medium text-white hover:bg-gray-800 disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">{{ saving ? 'Menyimpan...' : 'Simpan perubahan' }}</button>
        </div>
      </div>
      <section class="min-w-0 bg-white" :aria-label="'Hak akses ' + selectedRoleLabel">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[540px] table-fixed text-xs">
            <thead class="border-b border-gray-200 bg-gray-50 text-gray-500"><tr><th class="w-[40%] px-3 py-3 text-left font-medium">Modul</th><th class="px-1 sm:px-3 py-3 font-medium">Lihat</th><th class="px-1 sm:px-3 py-3 font-medium whitespace-nowrap">Tambah</th><th class="px-1 sm:px-3 py-3 font-medium">Edit</th><th class="px-1 sm:px-3 py-3 font-medium">Hapus</th></tr></thead>
            <tbody>
              <template v-for="group in groups" :key="group.label">
                <tr class="border-b border-gray-100 bg-gray-50/50"><th colspan="5" class="px-3 py-2 text-left text-[10px] font-medium uppercase tracking-wider text-gray-400">{{ group.label }}</th></tr>
                <tr v-for="module in group.modules" :key="module.key" class="border-b border-gray-100 last:border-0 hover:bg-gray-50/50">
                  <td class="px-3 py-2.5 font-normal text-gray-700">{{ module.label }}</td>
                  <td v-for="action in actions" :key="action.key" class="px-1 sm:px-3 py-2.5 text-center">
                    <input v-if="action.key === 'view' || module.transaction || module.key.startsWith('master_')" type="checkbox" :checked="hasPermission(module.key, action.key)" :disabled="saving || (action.key !== 'view' && !hasPermission(module.key, 'view')) || (selectedRole === 'admin' && module.key === 'master_user' && action.key === 'view')" @change="setPermission(module.key, action.key, $event.target.checked)" :aria-label="action.label + ' ' + module.label" class="size-3.5 rounded border-gray-300 accent-gray-800 focus:ring-gray-400 cursor-pointer disabled:cursor-not-allowed disabled:opacity-40" />
                    <span v-else class="text-gray-300" aria-label="Tidak tersedia">—</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <p class="border-t border-gray-200 px-3 py-3 text-[11px] text-gray-400">Berlaku untuk semua pengguna dengan role ini. Aktifkan Lihat untuk mengizinkan tambah, edit atau hapus. Riwayat hanya menyediakan akses lihat. Import dan sinkronisasi memerlukan Tambah dan Edit.</p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { ChevronRight as ChevronRightIcon } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import { notify } from '@/composables/useNotify';

const { checkAuth } = useAuth();
const roles = [{ key: 'admin', label: 'Admin' }, { key: 'scm', label: 'SCM' }, { key: 'ar', label: 'AR' }, { key: 'telemarketing', label: 'Telemarketing' }, { key: 'pajak', label: 'Pajak' }];
const actions = [{ key: 'view', label: 'Lihat' }, { key: 'create', label: 'Tambah' }, { key: 'edit', label: 'Edit' }, { key: 'delete', label: 'Hapus' }];
const groups = [
  { label: 'Transaksi', modules: [{ key: 'drafts', label: 'Draft', transaction: true }, { key: 'invoices', label: 'Invoice', transaction: true }, { key: 'data_program', label: 'Data Program', transaction: true }, { key: 'form_program', label: 'Form Program', transaction: true }] },
  { label: 'Riwayat', modules: [{ key: 'riwayat_program', label: 'Riwayat Program & Approval' }, { key: 'email_logs', label: 'Riwayat Email' }] },
  { label: 'Master Data', modules: [{ key: 'master_program', label: 'Master Program' }, { key: 'master_dealer', label: 'Master Dealer' }, { key: 'master_customer', label: 'Master Badan Usaha' }, { key: 'master_user', label: 'Master User' }] },
];
const selectedRole = ref('scm');
const permissions = ref({});
const savedPermissions = ref({});
const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const selectedRoleLabel = computed(() => roles.find(role => role.key === selectedRole.value)?.label);
const selectedPermissions = computed(() => permissions.value[selectedRole.value] || []);
const isDirty = computed(() => JSON.stringify([...selectedPermissions.value].sort()) !== JSON.stringify([...(savedPermissions.value[selectedRole.value] || [])].sort()));
const permissionKey = (module, action) => action === 'view' ? module : module + '_' + action;
const hasPermission = (module, action) => selectedPermissions.value.includes(permissionKey(module, action));
const setPermission = (module, action, enabled) => {
  const next = new Set(selectedPermissions.value);
  const key = permissionKey(module, action);
  if (enabled) next.add(key);
  else {
    next.delete(key);
    if (action === 'view') {
      next.delete(module + '_create');
      next.delete(module + '_edit');
      next.delete(module + '_delete');
    }
  }
  permissions.value[selectedRole.value] = [...next];
};
const loadPermissions = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get('/api/users');
    permissions.value = structuredClone(data.default_permissions);
    savedPermissions.value = structuredClone(data.default_permissions);
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Gagal memuat hak akses role.';
  } finally {
    loading.value = false;
  }
};
const savePermissions = async () => {
  if (saving.value || !isDirty.value) return;
  saving.value = true;
  const role = selectedRole.value;
  const values = [...selectedPermissions.value];
  try {
    await axios.put('/api/role-permissions', { role, permissions: values });
    savedPermissions.value[role] = values;
    await checkAuth();
    notify.success('Hak akses ' + selectedRoleLabel.value + ' berhasil disimpan.');
  } catch (error) {
    notify.error(error.response?.data?.message || 'Gagal menyimpan hak akses role.');
  } finally {
    saving.value = false;
  }
};
onMounted(loadPermissions);
</script>
