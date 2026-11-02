<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" @click.self="close">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-xl border border-gray-200 max-h-[90vh] flex flex-col">
      <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-gray-950">Import No PO/SJ & No Transaksi</h3>
          <p class="text-xs text-gray-500 mt-0.5">Baris Data Program dicari dari Kode BT dan Nama Program, di semua area.</p>
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

        <ul class="space-y-1 text-gray-500 list-disc pl-4">
          <li>Kolom yang dibaca: <strong class="text-gray-800">Kode BT</strong>, <strong class="text-gray-800">Nama Program</strong>, <strong class="text-gray-800">No PO/SJ</strong>, dan <strong class="text-gray-800">No. Trs</strong>.</li>
          <li>Kode BT "BTO…" dan "BT0…" dianggap sama. Spasi dan tanda baca di nama program diabaikan.</li>
          <li>Sel kosong tidak menghapus nomor yang sudah ada. Nomor yang berbeda diganti isi file.</li>
          <li>Hasilnya langsung dikirim juga ke Google Spreadsheet area masing-masing, kalau areanya sudah terhubung.</li>
        </ul>

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

        <p v-if="result.replaced" class="text-gray-600">
          {{ result.replaced }} baris sebelumnya sudah berisi nomor lain dan diganti isi file.
        </p>

        <ul v-if="result.sheet_push.length" class="space-y-1">
          <li v-for="(push, index) in result.sheet_push" :key="index" class="flex items-start gap-2">
            <CheckCircleIcon v-if="push.status === 'sent'" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-emerald-600" />
            <AlertCircleIcon v-else-if="push.status === 'failed'" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-rose-600" />
            <InfoIcon v-else class="w-3.5 h-3.5 mt-0.5 shrink-0 text-gray-400" />
            <span :class="push.status === 'failed' ? 'text-rose-700' : 'text-gray-700'">Spreadsheet {{ push.area }}: {{ push.message }}</span>
          </li>
        </ul>

        <div v-if="result.not_found.length">
          <p class="font-medium text-gray-900">Tidak ditemukan di Data Program</p>
          <p class="mt-0.5 mb-2 text-gray-500">Cek lagi Kode BT dan Nama Program baris ini di file.</p>
          <div class="max-h-56 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500">
                <tr>
                  <th class="px-3 py-2 font-medium w-14">Baris</th>
                  <th class="px-3 py-2 font-medium w-28">Kode BT</th>
                  <th class="px-3 py-2 font-medium">Nama Program</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-800">
                <tr v-for="row in result.not_found" :key="row.row">
                  <td class="px-3 py-2 tabular-nums">{{ row.row }}</td>
                  <td class="px-3 py-2">{{ row.kode_bt || '-' }}</td>
                  <td class="px-3 py-2">{{ row.program || '-' }}</td>
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
            <span>{{ uploading ? 'Mengimport...' : 'Mulai import' }}</span>
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
import {
  X as XIcon,
  UploadCloud as UploadCloudIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  Info as InfoIcon,
} from 'lucide-vue-next';

defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'imported']);

const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const uploading = ref(false);
const errorMessage = ref('');
const result = ref(null);

const stats = computed(() => [
  { label: 'Baris di file', value: result.value.total_rows, class: 'text-gray-950' },
  { label: 'Diperbarui', value: result.value.updated, class: 'text-emerald-700' },
  { label: 'Sudah sama', value: result.value.unchanged, class: 'text-gray-950' },
  { label: 'Tidak ditemukan', value: result.value.not_found.length, class: result.value.not_found.length ? 'text-rose-700' : 'text-gray-950' },
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
  if (!selectedFile.value) return;

  uploading.value = true;
  errorMessage.value = '';

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const res = await axios.post('/api/data-program/import-po', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = res.data.data;
    notify.success(res.data.message);
    emit('imported');
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
  result.value = null;
  errorMessage.value = '';
  clearFile();
};

const close = () => {
  if (uploading.value) return;
  reset();
  emit('close');
};
</script>
