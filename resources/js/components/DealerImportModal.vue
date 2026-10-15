<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" @click.self="close">
    <div class="bg-white rounded-xl max-w-lg w-full shadow-xl border border-gray-200 max-h-[90vh] flex flex-col">
      <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-gray-950">Import Master Dealer</h3>
          <p class="text-xs text-gray-500 mt-0.5">
            File Master Customer CV TOP. Satu file berisi semua area; area tiap toko diambil dari kolom Big Area atau Reffnote.
          </p>
        </div>
        <button type="button" @click="close" :disabled="uploading" class="text-gray-400 hover:text-gray-600 cursor-pointer disabled:opacity-50">
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Form upload -->
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
          <p class="mt-0.5 text-gray-400">.xlsx, .xls, atau .csv (maks. 20 MB)</p>
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

        <p class="text-gray-500">
          Kolom wajib: <strong class="text-gray-800">ID Customer</strong> (kode BT), <strong class="text-gray-800">Nama Customer</strong>,
          dan <strong class="text-gray-800">Big Area</strong> atau <strong class="text-gray-800">Reffnote</strong>. Kolom lain seperti Alias, ID GS,
          Nama GS, Jenis NPWP, Telemarketing, No. WA Telemarketing, dan Sales Area ikut diimport bila ada.
        </p>

        <fieldset class="space-y-2">
          <legend class="font-medium text-gray-900 mb-1.5">Dealer yang sudah ada</legend>
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

        <p v-if="errorMessage" class="text-[11px] text-red-600">{{ errorMessage }}</p>
      </div>

      <!-- Hasil import -->
      <div v-else class="p-5 space-y-4 text-xs overflow-y-auto">
        <div class="flex items-start gap-2.5">
          <CheckCircle2Icon class="w-5 h-5 text-emerald-600 shrink-0" />
          <div>
            <p class="text-sm font-semibold text-gray-950">{{ result.imported }} dealer berhasil diimport</p>
            <p class="text-gray-500 mt-0.5">
              <span v-for="(count, areaLabel, index) in result.per_area" :key="areaLabel">{{ index ? ' · ' : '' }}{{ areaLabel }}: {{ count }}</span>
            </p>
          </div>
        </div>

        <ul class="space-y-1.5 text-gray-600 list-disc pl-4">
          <li v-if="result.regions_created.length">Region baru di Master Region: {{ result.regions_created.join(', ') }}</li>
          <li v-if="result.normalized_kode_bt">{{ result.normalized_kode_bt }} kode BT "BTO…" ditulis jadi "BT0…".</li>
          <li v-for="note in result.kept_original_kode_bt" :key="note">Kode BT tetap ditulis asli: {{ note }}</li>
          <li v-if="duplicateCount">{{ duplicateCount }} kode BT muncul lebih dari sekali di file; baris terakhir yang dipakai.</li>
        </ul>

        <div v-if="result.skipped.length" class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <p class="font-medium text-amber-700">{{ result.skipped.length }} baris dilewati</p>
            <a href="/api/dealers/import/skipped" class="inline-flex items-center gap-1 text-gray-700 hover:text-gray-950 underline">
              <DownloadIcon class="w-3.5 h-3.5" />
              Unduh daftar baris
            </a>
          </div>
          <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">
            <table class="w-full text-left text-[11px]">
              <thead class="bg-gray-50 text-gray-600 sticky top-0">
                <tr>
                  <th class="py-1.5 px-2.5 font-semibold">Baris</th>
                  <th class="py-1.5 px-2.5 font-semibold">Kode BT</th>
                  <th class="py-1.5 px-2.5 font-semibold">Nama</th>
                  <th class="py-1.5 px-2.5 font-semibold">Alasan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-700">
                <tr v-for="row in result.skipped" :key="row.row">
                  <td class="py-1.5 px-2.5">{{ row.row }}</td>
                  <td class="py-1.5 px-2.5">{{ row.kode_bt || '-' }}</td>
                  <td class="py-1.5 px-2.5">{{ row.dealer_name || '-' }}</td>
                  <td class="py-1.5 px-2.5 text-amber-700">{{ row.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-gray-500">Lengkapi kolom Big Area / Reffnote di file yang diunduh, lalu import lagi dengan pilihan "Tambah & perbarui".</p>
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
            class="h-8 px-4 inline-flex items-center gap-1.5 rounded-lg text-white text-xs font-medium cursor-pointer disabled:opacity-50"
            :class="replaceExisting ? 'bg-rose-600 hover:bg-rose-700' : 'bg-gray-900 hover:bg-gray-800'"
          >
            <span v-if="uploading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ uploading ? 'Mengimport...' : (replaceExisting ? 'Ganti Semua & Import' : 'Import') }}</span>
          </button>
        </template>
        <button
          v-else
          type="button"
          @click="finish"
          class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer"
        >
          Selesai
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import {
  X as XIcon,
  UploadCloud as UploadCloudIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  CheckCircle2 as CheckCircle2Icon,
  Download as DownloadIcon,
} from 'lucide-vue-next';

defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'imported']);

const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const replaceExisting = ref(false);
const uploading = ref(false);
const errorMessage = ref('');
const result = ref(null);

const duplicateCount = computed(() => Object.keys(result.value?.duplicates || {}).length);

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
  if (replaceExisting.value && !confirm('Semua dealer lama di Jawa Barat dan Jawa Tengah akan dihapus dan diganti isi file ini. Lanjutkan?')) return;

  uploading.value = true;
  errorMessage.value = '';

  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('replace_existing', replaceExisting.value ? '1' : '0');

  try {
    const res = await axios.post('/api/dealers/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = res.data.data;
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

const close = () => {
  if (uploading.value) return;
  emit('close');
};

const finish = () => {
  result.value = null;
  replaceExisting.value = false;
  clearFile();
  emit('close');
};
</script>
