<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-40 flex items-center justify-center p-4">
    <div class="bg-white border border-gray-300 w-full max-w-2xl rounded shadow-lg p-6">
      <div class="flex justify-between items-center border-b border-gray-200 pb-3 mb-4">
        <h2 class="text-base font-bold text-gray-900">Import Draft Excel</h2>
        <button @click="close" class="text-gray-400 hover:text-gray-600 text-lg font-bold">
          &times;
        </button>
      </div>

      <!-- Upload Form -->
      <div v-if="!result" class="space-y-4">
        <p class="text-xs text-gray-600">
          Pilih file Excel (.xlsx atau .xls) berisi data draft invoice. Kolom akan divalidasi dan dihitung otomatis oleh sistem.
        </p>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Pilih File Excel:</label>
          <input
            type="file"
            ref="fileInput"
            accept=".xlsx, .xls, .csv"
            @change="handleFileChange"
            class="block w-full text-xs text-gray-700 border border-gray-300 rounded p-2 focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">
            Tipe Invoice Default (jika kolom di Excel kosong):
          </label>
          <select
            v-model="selectedInvoiceType"
            class="block w-full text-xs text-gray-700 border border-gray-300 rounded p-2 bg-white focus:outline-none"
          >
            <option value="NPS FL">NPS FL (National Program Scheme - Front Line)</option>
            <option value="DSA">DSA (Direct Sales Agent)</option>
          </select>
        </div>

        <div v-if="uploadError" class="p-3 bg-red-50 border border-red-200 text-xs text-red-700 rounded">
          {{ uploadError }}
        </div>

        <div class="flex justify-end space-x-2 pt-3 border-t border-gray-200">
          <button
            type="button"
            @click="close"
            :disabled="uploading"
            class="px-4 py-1.5 border border-gray-300 text-xs rounded hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="uploadFile"
            :disabled="!selectedFile || uploading"
            class="px-4 py-1.5 bg-black text-white text-xs font-medium rounded hover:bg-gray-800 disabled:opacity-50"
          >
            {{ uploading ? 'Memproses Import...' : 'Upload & Import' }}
          </button>
        </div>
      </div>

      <!-- Result View -->
      <div v-else class="space-y-4">
        <div class="p-3 bg-gray-50 border border-gray-200 rounded text-xs space-y-1">
          <div class="font-bold text-gray-900 mb-2">Hasil Import:</div>
          <div class="grid grid-cols-4 gap-2">
            <div>Total Baris: <strong class="text-gray-900">{{ result.total_rows }}</strong></div>
            <div>Berhasil (Ready): <strong class="text-green-700">{{ result.success }}</strong></div>
            <div>Gagal (Error): <strong class="text-red-700">{{ result.failed }}</strong></div>
            <div>Peringatan Rumus: <strong class="text-amber-700">{{ result.warning }}</strong></div>
          </div>
        </div>

        <!-- Error table if any -->
        <div v-if="result.errors && result.errors.length > 0">
          <div class="text-xs font-bold text-red-700 mb-2">Detail Kesalahan Baris:</div>
          <div class="max-h-60 overflow-y-auto border border-gray-200 rounded">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="w-16">Row</TableHead>
                  <TableHead class="w-32">Field</TableHead>
                  <TableHead>Value</TableHead>
                  <TableHead>Error</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(err, idx) in result.errors" :key="idx">
                  <TableCell class="font-mono font-bold">{{ err.row }}</TableCell>
                  <TableCell class="font-mono text-gray-700">{{ err.field }}</TableCell>
                  <TableCell class="text-gray-600 truncate max-w-xs">{{ err.value || '-' }}</TableCell>
                  <TableCell class="text-red-600">{{ err.error }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </div>

        <div class="flex justify-end space-x-2 pt-3 border-t border-gray-200">
          <button
            type="button"
            @click="resetAndClose"
            class="px-4 py-1.5 bg-black text-white text-xs font-medium rounded hover:bg-gray-800"
          >
            Selesai & Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from '@/components/ui/table';

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'imported']);

const fileInput = ref(null);
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref(null);
const result = ref(null);
const selectedInvoiceType = ref('NPS FL');

const handleFileChange = (e) => {
  const files = e.target.files;
  if (files && files.length > 0) {
    selectedFile.value = files[0];
    uploadError.value = null;
  }
};

const uploadFile = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  uploadError.value = null;

  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('invoice_type', selectedInvoiceType.value);

  try {
    const res = await axios.post('/api/drafts/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = res.data.data;
    emit('imported');
  } catch (err) {
    console.error('Import upload error:', err);
    uploadError.value =
      err.response?.data?.errors?.file?.[0] ||
      err.response?.data?.message ||
      (err.response?.status === 413 ? 'Ukuran file terlalu besar (Payload Too Large).' : null) ||
      (err.response?.status === 419 ? 'Sesi kedaluwarsa. Silakan refresh browser.' : null) ||
      err.message ||
      'Gagal mengunggah file.';
  } finally {
    uploading.value = false;
  }
};

const close = () => {
  emit('close');
};

const resetAndClose = () => {
  result.value = null;
  selectedFile.value = null;
  if (fileInput.value) fileInput.value.value = '';
  emit('close');
};
</script>
