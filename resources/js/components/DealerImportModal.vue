<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" @click.self="close">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-xl border border-gray-200 max-h-[90vh] flex flex-col">
      <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-gray-950">Import Master Dealer</h3>
          <p class="text-xs text-gray-500 mt-0.5">
            File <strong class="text-gray-700">Master Customer CV TOP</strong> untuk data toko, atau file
            <strong class="text-gray-700">Draft Program</strong> (sheet RAW JABAR) untuk agreement toko. Jenis file dikenali otomatis.
          </p>
        </div>
        <button type="button" @click="close" :disabled="uploading" class="text-gray-400 hover:text-gray-600 cursor-pointer disabled:opacity-50">
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <div class="p-5 space-y-4 text-xs overflow-y-auto">
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
          <p class="mt-0.5 text-gray-400">.xlsx, .xls, atau .csv (maks. 50 MB)</p>
        </div>

        <div v-else class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200">
          <div class="flex items-center gap-2.5 min-w-0">
            <FileSpreadsheetIcon class="w-5 h-5 text-emerald-600 shrink-0" />
            <div class="min-w-0">
              <p class="font-medium text-gray-900 truncate">{{ selectedFile.name }}</p>
              <p class="text-[11px] text-gray-400">{{ formatFileSize(selectedFile.size) }}</p>
            </div>
          </div>
          <button type="button" @click="clearFile" :disabled="uploading" class="p-1 text-gray-400 hover:text-rose-600 rounded-md cursor-pointer" title="Ganti file">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <ul class="space-y-1 text-gray-500 list-disc pl-4">
          <li>
            <strong class="text-gray-800">CV TOP:</strong> wajib ada kolom ID Customer (kode BT), Nama Customer, dan Big Area atau Reffnote.
            Area tiap toko diambil dari Big Area / Reffnote, jadi satu file berisi semua area.
          </li>
          <li>
            <strong class="text-gray-800">Draft Program:</strong> kolom Kode BT dan AGREMENT. Isi tiap agreement dicek AI, jadi bisa butuh
            beberapa menit. Import data toko CV TOP dulu bila Master Dealer masih kosong.
          </li>
        </ul>

        <fieldset class="space-y-2">
          <legend class="font-medium text-gray-900 mb-1.5">Dealer yang sudah ada <span class="font-normal text-gray-500">(file CV TOP)</span></legend>
          <label class="flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer" :class="!replaceExisting ? 'border-gray-900' : 'border-gray-200'">
            <input type="radio" :value="false" v-model="replaceExisting" class="mt-0.5 cursor-pointer" />
            <span>
              <span class="block font-medium text-gray-900">Tambah & perbarui</span>
              <span class="block text-gray-500 mt-0.5">Toko di file ditambahkan atau diperbarui berdasarkan kode BT. Dealer lain tetap ada.</span>
            </span>
          </label>
          <label class="flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer" :class="replaceExisting ? 'border-rose-500' : 'border-gray-200'">
            <input type="radio" :value="true" v-model="replaceExisting" class="mt-0.5 cursor-pointer" />
            <span>
              <span class="block font-medium text-gray-900">Ganti semua</span>
              <span class="block text-gray-500 mt-0.5">Semua dealer lama di Jawa Barat dan Jawa Tengah dihapus, lalu diganti isi file ini.</span>
            </span>
          </label>
        </fieldset>

        <p class="text-gray-500">Import berjalan di background. Jendela ini langsung tertutup dan hasilnya muncul di halaman Master Dealer.</p>
        <p v-if="errorMessage" class="text-[11px] text-red-600">{{ errorMessage }}</p>
      </div>

      <div class="px-5 py-3.5 border-t border-gray-100 flex items-center justify-end gap-2">
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
          class="h-8 px-4 inline-flex items-center gap-1.5 rounded-lg text-white text-xs font-medium cursor-pointer disabled:opacity-50"
          :class="replaceExisting ? 'bg-rose-600 hover:bg-rose-700' : 'bg-gray-900 hover:bg-gray-800'"
        >
          <span v-if="uploading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span>{{ uploading ? 'Mengunggah...' : 'Mulai import' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import {
  X as XIcon,
  UploadCloud as UploadCloudIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
} from 'lucide-vue-next';

defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'started']);

const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const replaceExisting = ref(false);
const uploading = ref(false);
const errorMessage = ref('');

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${parseFloat((bytes / 1024 ** i).toFixed(1))} ${units[i]}`;
};

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
  if (replaceExisting.value && !confirm('Bila file ini file CV TOP, semua dealer lama di Jawa Barat dan Jawa Tengah akan dihapus dan diganti isi file ini. Lanjutkan?')) return;

  uploading.value = true;
  errorMessage.value = '';

  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('replace_existing', replaceExisting.value ? '1' : '0');

  try {
    const res = await axios.post('/api/dealers/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    emit('started', res.data.status);
    replaceExisting.value = false;
    clearFile();
    emit('close');
  } catch (err) {
    errorMessage.value =
      err.response?.data?.errors?.file?.[0] ||
      err.response?.data?.message ||
      (err.response?.status === 413 ? 'Ukuran file terlalu besar untuk server.' : null) ||
      'Gagal mengunggah file.';
  } finally {
    uploading.value = false;
  }
};

const close = () => {
  if (uploading.value) return;
  emit('close');
};
</script>
