<template>
  <div v-if="isOpen && canEdit" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" @click.self="close">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-xl border border-gray-200 max-h-[90vh] flex flex-col">
      <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-gray-950">Import Pengguna</h3>
          <p class="text-xs text-gray-500 mt-0.5">Satu akun per nomor WhatsApp.</p>
        </div>
        <button type="button" @click="close" :disabled="uploading" class="text-gray-400 hover:text-gray-600 cursor-pointer disabled:opacity-50">
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Pilih file -->
      <div v-if="!result" class="p-5 space-y-4 text-xs overflow-y-auto">
        <input type="file" ref="fileInput" accept=".xlsx,.xls,.csv" class="hidden" @change="pickFile($event.target.files)" />

        <div
          v-if="!selectedFile"
          @click="fileInput?.click()"
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="isDragging = false; pickFile($event.dataTransfer.files)"
          class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition"
          :class="isDragging ? 'border-gray-900 bg-gray-50' : 'border-gray-200 hover:border-gray-400 bg-gray-50/50'"
        >
          <UploadCloudIcon class="w-6 h-6 mx-auto text-gray-400" />
          <p class="mt-2 font-medium text-gray-800">Klik untuk memilih file atau tarik file ke sini</p>
          <p class="mt-0.5 text-gray-400">.xlsx, .xls, atau .csv</p>
        </div>

        <div v-else class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200">
          <div class="flex items-center gap-2.5 min-w-0">
            <FileSpreadsheetIcon class="w-5 h-5 text-emerald-600 shrink-0" />
            <p class="font-medium text-gray-900 truncate">{{ selectedFile.name }}</p>
          </div>
          <button type="button" @click="clearFile" :disabled="uploading" class="p-1 text-gray-400 hover:text-rose-600 rounded-md cursor-pointer" title="Ganti file">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <div>
          <label for="import-role" class="block font-medium text-gray-900 mb-1">Peran</label>
          <select
            id="import-role"
            v-model="role"
            class="w-full h-9 px-3 rounded-lg border border-gray-300 bg-white text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400 cursor-pointer"
          >
            <option v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p class="mt-1 text-gray-500">Untuk baris tanpa kolom Peran.</p>
        </div>

        <div>
          <label for="import-password" class="block font-medium text-gray-900 mb-1">Password awal <span class="font-normal text-gray-500">(opsional)</span></label>
          <input
            id="import-password"
            v-model="password"
            type="password"
            autocomplete="new-password"
            placeholder="Min. 8 karakter, huruf &amp; angka"
            class="w-full h-9 px-3 rounded-lg border border-gray-300 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400"
          />
          <p class="mt-1 text-gray-500">Kosongkan untuk login lewat WhatsApp saja.</p>
        </div>

        <p class="text-gray-500">Kolom: Nama, No WA, Email, Peran. File CV TOP juga bisa.</p>

        <p v-if="errorMessage" class="text-rose-600">{{ errorMessage }}</p>
      </div>

      <!-- Hasil import -->
      <div v-else class="p-5 space-y-4 text-xs overflow-y-auto">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div v-for="stat in stats" :key="stat.label" class="p-3 rounded-lg border border-gray-200">
            <p class="text-gray-500">{{ stat.label }}</p>
            <p :class="['mt-0.5 text-base font-semibold', stat.class]">{{ stat.value }}</p>
          </div>
        </div>

        <div v-if="result.created.length">
          <p class="font-medium text-gray-900 mb-2">Akun dibuat</p>
          <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500">
                <tr>
                  <th class="px-3 py-2 font-medium">Nama</th>
                  <th class="px-3 py-2 font-medium w-36">WhatsApp</th>
                  <th class="px-3 py-2 font-medium w-28">Peran</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-800">
                <tr v-for="account in result.created" :key="account.whatsapp">
                  <td class="px-3 py-2">{{ account.name }}</td>
                  <td class="px-3 py-2 tabular-nums">{{ account.whatsapp }}</td>
                  <td class="px-3 py-2">{{ roleLabel(account.role) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="result.existing.length">
          <p class="font-medium text-gray-900">Sudah punya akun</p>
          <p class="mt-0.5 text-gray-600">
            <span v-for="(account, index) in result.existing" :key="account.whatsapp">{{ index ? ', ' : '' }}{{ account.account }} ({{ account.whatsapp }})</span>
          </p>
        </div>

        <div v-if="result.skipped.length">
          <p class="font-medium text-gray-900 mb-2">Dilewati</p>
          <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500">
                <tr>
                  <th class="px-3 py-2 font-medium w-14">Baris</th>
                  <th class="px-3 py-2 font-medium">Nama</th>
                  <th class="px-3 py-2 font-medium">Alasan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-800">
                <tr v-for="row in result.skipped" :key="`${row.row}-${row.name}`">
                  <td class="px-3 py-2 tabular-nums">{{ row.row }}</td>
                  <td class="px-3 py-2">{{ row.name || '-' }}<span v-if="row.whatsapp" class="block text-gray-500 tabular-nums">{{ row.whatsapp }}</span></td>
                  <td class="px-3 py-2 text-rose-700">{{ row.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="px-5 py-3.5 border-t border-gray-100 flex items-center justify-end gap-2">
        <template v-if="!result">
          <button
            type="button"
            @click="close"
            :disabled="uploading"
            class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium cursor-pointer disabled:opacity-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="upload"
            :disabled="!selectedFile || uploading"
            class="h-8 px-4 inline-flex items-center gap-1.5 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer disabled:opacity-50"
          >
            <span v-if="uploading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ uploading ? 'Membuat akun...' : 'Buat akun' }}</span>
          </button>
        </template>
        <template v-else>
          <button
            type="button"
            @click="reset"
            class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium cursor-pointer"
          >
            Import file lain
          </button>
          <button
            type="button"
            @click="close"
            class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer"
          >
            Selesai
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { notify } from '@/composables/useNotify';
import { useAuth } from '@/composables/useAuth';
import {
  X as XIcon,
  UploadCloud as UploadCloudIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
} from 'lucide-vue-next';

const props = defineProps({
  isOpen: Boolean,
  // Hanya Admin yang boleh membuat akun Admin
  canCreateAdmin: Boolean,
});

const emit = defineEmits(['close', 'imported']);

const { canEdit } = useAuth();

const ROLE_LABELS = { telemarketing: 'Telemarketing', ar: 'AR', scm: 'SCM', admin: 'Admin' };

const roleOptions = computed(() =>
  Object.entries(ROLE_LABELS)
    .filter(([value]) => value !== 'admin' || props.canCreateAdmin)
    .map(([value, label]) => ({ value, label })),
);

const roleLabel = (value) => ROLE_LABELS[value] || value;

const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const role = ref('telemarketing');
const password = ref('');
const uploading = ref(false);
const errorMessage = ref('');
const result = ref(null);

const stats = computed(() => [
  { label: 'Nomor di file', value: result.value.total_numbers, class: 'text-gray-950' },
  { label: 'Akun dibuat', value: result.value.created.length, class: 'text-emerald-700' },
  { label: 'Sudah punya akun', value: result.value.existing.length, class: 'text-gray-950' },
  { label: 'Dilewati', value: result.value.skipped.length, class: result.value.skipped.length ? 'text-rose-700' : 'text-gray-950' },
]);

const pickFile = (files) => {
  if (files?.length) {
    selectedFile.value = files[0];
    errorMessage.value = '';
  }
};

const clearFile = () => {
  selectedFile.value = null;
  if (fileInput.value) fileInput.value.value = '';
};

const upload = async () => {
  if (!canEdit.value || !selectedFile.value) return;

  uploading.value = true;
  errorMessage.value = '';

  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('role', role.value);
  if (password.value) formData.append('password', password.value);

  try {
    const res = await axios.post('/api/users/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = res.data.data;
    notify.success(res.data.message);
    emit('imported');
  } catch (err) {
    errorMessage.value =
      err.response?.data?.errors?.file?.[0] ||
      err.response?.data?.errors?.password?.[0] ||
      err.response?.data?.message ||
      (err.response?.status === 413 ? 'Ukuran file terlalu besar untuk server.' : null) ||
      'Gagal mengimport file.';
  } finally {
    uploading.value = false;
  }
};

const reset = () => {
  result.value = null;
  errorMessage.value = '';
  password.value = '';
  clearFile();
};

const close = () => {
  if (uploading.value) return;
  reset();
  emit('close');
};
</script>
