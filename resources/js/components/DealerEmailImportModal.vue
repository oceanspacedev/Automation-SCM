<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" @click.self="close">
    <div class="bg-white rounded-xl max-w-2xl w-full shadow-xl border border-gray-200 max-h-[90vh] flex flex-col">
      <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-gray-950">Import Email Dealer</h3>
          <p class="text-xs text-gray-500 mt-0.5">Dicocokkan dari Kode BT atau nama toko, di semua area.</p>
        </div>
        <button type="button" @click="close" :disabled="uploading" class="text-gray-400 hover:text-gray-600 cursor-pointer disabled:opacity-50">
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- 1. Pilih file -->
      <div v-if="step === 'pick'" class="p-5 space-y-4 text-xs overflow-y-auto">
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

        <p class="text-gray-500">Kolom: Kode BT (atau ID GS / Nama Dealer) dan Email. File Export Excel Master Dealer juga bisa.</p>

        <p v-if="errorMessage" class="text-rose-600">{{ errorMessage }}</p>
      </div>

      <!-- 2. Preview & 3. Hasil -->
      <div v-else class="p-5 space-y-4 text-xs overflow-y-auto">
        <p v-if="step === 'done'" class="flex items-center gap-2 text-emerald-700 font-medium">
          <CheckCircleIcon class="w-4 h-4" />
          {{ changedCount }} email toko tersimpan.
        </p>

        <!-- Kolom yang terbaca dari file: Kode BT / ID GS dipakai lebih dulu daripada nama -->
        <p v-if="columnSummary" class="text-gray-500">Kolom terbaca: <span class="text-gray-800">{{ columnSummary }}</span></p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <div v-for="stat in stats" :key="stat.label" class="p-3 rounded-lg border border-gray-200">
            <p class="text-gray-500">{{ stat.label }}</p>
            <p :class="['mt-0.5 text-base font-semibold tabular-nums', stat.class]">{{ stat.value }}</p>
          </div>
        </div>

        <!-- Nama yang belum pasti: mirip (sudah terpilih), cocok ke beberapa toko, atau hanya saran -->
        <div v-if="step === 'preview' && result.review.length">
          <p class="font-medium text-gray-900 mb-2">Perlu dicek ({{ result.review.length }})</p>
          <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500 sticky top-0">
                <tr>
                  <th class="px-3 py-2 font-medium w-14">Baris</th>
                  <th class="px-3 py-2 font-medium">Nama di file</th>
                  <th class="px-3 py-2 font-medium w-[55%]">Toko di Master Dealer</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-800">
                <tr v-for="row in result.review" :key="row.row">
                  <td class="px-3 py-2 tabular-nums align-top">{{ row.row }}</td>
                  <td class="px-3 py-2 align-top">
                    {{ row.name }}
                    <span class="block text-gray-400">{{ REVIEW_LABELS[row.kind] }}</span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <select
                      :value="choiceFor(row)"
                      @change="choose(row, $event.target.value)"
                      class="h-8 w-full rounded-lg border border-gray-300 bg-white px-2 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer"
                    >
                      <option value="0">{{ row.kind === 'similar' ? 'Lewati' : 'Pilih toko (atau lewati)' }}</option>
                      <option v-for="dealer in row.candidates" :key="dealer.id" :value="String(dealer.id)">{{ dealerLabel(dealer) }}</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="changedRows.length">
          <p class="font-medium text-gray-900 mb-2">{{ step === 'preview' ? 'Akan disimpan' : 'Disimpan' }}</p>
          <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500 sticky top-0">
                <tr>
                  <th class="px-3 py-2 font-medium w-24">Kode BT</th>
                  <th class="px-3 py-2 font-medium">Dealer</th>
                  <th class="px-3 py-2 font-medium">Email</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-800">
                <tr v-for="row in changedRows" :key="row.row">
                  <td class="px-3 py-2 whitespace-nowrap">{{ row.kode_bt || '-' }}</td>
                  <td class="px-3 py-2">{{ row.dealer_name || '-' }}</td>
                  <td class="px-3 py-2 break-all">
                    {{ row.new_email }}
                    <span v-if="row.old_email" class="block text-gray-400 line-through">{{ row.old_email }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="skippedRows.length">
          <p class="font-medium text-gray-900 mb-2">Dilewati</p>
          <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500 sticky top-0">
                <tr>
                  <th class="px-3 py-2 font-medium w-14">Baris</th>
                  <th class="px-3 py-2 font-medium w-32">Toko</th>
                  <th class="px-3 py-2 font-medium">Alasan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-800">
                <tr v-for="row in skippedRows" :key="`${row.row}-${row.reason}`">
                  <td class="px-3 py-2 tabular-nums">{{ row.row }}</td>
                  <td class="px-3 py-2">{{ row.key || '-' }}</td>
                  <td class="px-3 py-2 text-rose-700">{{ row.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="errorMessage" class="text-rose-600">{{ errorMessage }}</p>
      </div>

      <div class="px-5 py-3.5 border-t border-gray-100 flex items-center justify-end gap-2">
        <template v-if="step === 'pick'">
          <button type="button" @click="close" :disabled="uploading" :class="secondaryButtonClass">Batal</button>
          <button type="button" @click="send(true)" :disabled="!selectedFile || uploading" :class="primaryButtonClass">
            <span v-if="uploading" :class="spinnerClass"></span>
            <span>{{ uploading ? 'Membaca file...' : 'Lihat Preview' }}</span>
          </button>
        </template>
        <template v-else-if="step === 'preview'">
          <button type="button" @click="step = 'pick'" :disabled="uploading" :class="secondaryButtonClass">Kembali</button>
          <button type="button" @click="send(false)" :disabled="changedCount === 0 || uploading" :class="primaryButtonClass">
            <span v-if="uploading" :class="spinnerClass"></span>
            <span>{{ uploading ? 'Menyimpan...' : `Simpan ${changedCount} Email` }}</span>
          </button>
        </template>
        <template v-else>
          <button type="button" @click="reset" :class="secondaryButtonClass">Import file lain</button>
          <button type="button" @click="close" :class="primaryButtonClass">Selesai</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { notify } from '@/composables/useNotify';
import {
  X as XIcon,
  UploadCloud as UploadCloudIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  CheckCircle as CheckCircleIcon,
} from 'lucide-vue-next';

defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'imported']);

const secondaryButtonClass = 'h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium cursor-pointer disabled:opacity-50';
const primaryButtonClass = 'h-8 px-4 inline-flex items-center gap-1.5 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer disabled:opacity-50';
const spinnerClass = 'w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin';

const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const uploading = ref(false);
const errorMessage = ref('');
// 'pick' → 'preview' → 'done'
const step = ref('pick');
const result = ref(null);
// Toko yang dipilih di preview untuk nama yang belum pasti: baris file → id toko (0 = lewati)
const choices = ref({});
let refreshTimer = null;

const REVIEW_LABELS = {
  similar: 'Nama mirip',
  choose: 'Lebih dari satu toko',
  suggest: 'Saran nama mirip',
};

const dealerLabel = (dealer) => [
  dealer.kode_bt,
  dealer.dealer_name + (dealer.alias ? ` (${dealer.alias})` : ''),
  dealer.region,
  dealer.area,
].filter(Boolean).join(' · ');

const COLUMN_LABELS = { kode_bt: 'Kode BT', id_realme: 'ID GS', dealer_name: 'Nama', email: 'Email' };
const columnSummary = computed(() => Object.entries(COLUMN_LABELS)
  .filter(([field]) => result.value?.columns?.[field])
  .map(([field, label]) => `${label} (kolom ${result.value.columns[field].column})`)
  .join(' · '));

const choiceFor = (row) => String(choices.value[row.row] ?? row.selected ?? 0);

// Pilihan baru: preview dihitung ulang supaya jumlah dan daftar yang akan disimpan ikut berubah
const choose = (row, value) => {
  choices.value = { ...choices.value, [row.row]: Number(value) };
  clearTimeout(refreshTimer);
  refreshTimer = setTimeout(() => send(true), 250);
};

const changedCount = computed(() => (result.value ? result.value.filled + result.value.updated : 0));
const changedRows = computed(() => (result.value?.rows || []).filter((row) => row.status !== 'same'));
const skippedRows = computed(() => [
  ...(result.value?.not_found || []),
  ...(result.value?.invalid || []).map((row) => ({ ...row, reason: `Email tidak valid: ${row.email}` })),
].sort((a, b) => a.row - b.row));

const stats = computed(() => [
  { label: 'Email baru', value: result.value.filled, class: 'text-emerald-700' },
  { label: 'Email diganti', value: result.value.updated, class: 'text-gray-950' },
  { label: 'Sudah sama', value: result.value.same, class: 'text-gray-950' },
  { label: 'Dilewati', value: skippedRows.value.length, class: skippedRows.value.length ? 'text-rose-700' : 'text-gray-950' },
]);

const pickFile = (files) => {
  if (files?.length) {
    selectedFile.value = files[0];
    choices.value = {};
    errorMessage.value = '';
  }
};

const clearFile = () => {
  selectedFile.value = null;
  choices.value = {};
  if (fileInput.value) fileInput.value.value = '';
};

const send = async (previewOnly) => {
  if (!selectedFile.value) return;

  uploading.value = true;
  errorMessage.value = '';

  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('choices', JSON.stringify(choices.value));
  if (previewOnly) formData.append('preview', '1');

  try {
    const res = await axios.post('/api/dealers/import-emails', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = res.data.data;
    step.value = previewOnly ? 'preview' : 'done';
    if (!previewOnly) {
      notify.success(res.data.message);
      emit('imported');
    }
  } catch (err) {
    errorMessage.value =
      err.response?.data?.errors?.file?.[0] ||
      err.response?.data?.message ||
      (err.response?.status === 413 ? 'Ukuran file terlalu besar untuk server.' : null) ||
      'Gagal mengimport file.';
  } finally {
    uploading.value = false;
  }
};

const reset = () => {
  clearTimeout(refreshTimer);
  result.value = null;
  choices.value = {};
  step.value = 'pick';
  errorMessage.value = '';
  clearFile();
};

const close = () => {
  if (uploading.value) return;
  reset();
  emit('close');
};
</script>
