<template>
  <Teleport to="body">
    <Transition name="modal-scale">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close"
      >
        <!-- Backdrop with soft blur -->
        <div
          class="absolute inset-0 bg-black/40 backdrop-blur-xs transition-opacity"
          @click="close"
        />

        <!-- Modal Dialog Box -->
        <div
          :class="[
            'relative z-10 w-full bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden transform transition-all font-sans max-h-[90vh] flex flex-col',
            preview && !result ? 'max-w-5xl' : 'max-w-xl',
          ]"
        >
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <!-- Authentic Microsoft Excel Logo -->
              <div class="w-10 h-10 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-10 h-10 drop-shadow-xs">
                  <path fill="#166e40" d="M37 6H17a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                  <path fill="#23a455" d="M37 6H24v36h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                  <path fill="#2ecc71" opacity=".35" d="M24 13h15v4H24zm0 7h15v4H24zm0 7h15v4H24zm0 7h15v4H24z"/>
                  <path fill="#107c41" d="M22 13H8a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V15a2 2 0 0 0-2-2z"/>
                  <path fill="#ffffff" d="M12.4 28.5l2.4-4.8 2.4 4.8h2.3l-3.5-6.5 3.3-6.5h-2.3l-2.2 4.7-2.2-4.7h-2.3l3.3 6.5-3.5 6.5h2.3z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-base font-bold text-gray-900 leading-tight">
                  Import Draft Excel
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                  {{ preview && !result
                    ? 'Cek dulu baris yang akan masuk ke draft sebelum diimport.'
                    : 'Unggah berkas Excel DSA atau NPS FL untuk diproses ke draft invoice.' }}
                </p>
              </div>
            </div>
            <button
              type="button"
              @click="close"
              :disabled="uploading"
              class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body: Upload Form -->
          <div v-if="!preview && !result" class="p-6 space-y-4 overflow-y-auto min-h-0">
            <!-- Hidden native file input -->
            <input
              type="file"
              ref="fileInput"
              accept=".xlsx, .xls, .csv"
              @change="handleFileChange"
              class="hidden"
            />

            <!-- Drag & Drop / File Picker Area -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                Pilih Berkas Excel:
              </label>

              <!-- Not selected state -->
              <div
                v-if="!selectedFile"
                @click="triggerFileInput"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDrop"
                :class="[
                  'border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all',
                  isDragging
                    ? 'border-emerald-500 bg-emerald-50/50'
                    : 'border-gray-200 hover:border-emerald-500 bg-gray-50/50 hover:bg-emerald-50/20'
                ]"
              >
                <!-- Authentic Microsoft Excel Logo -->
                <div class="w-12 h-12 mx-auto mb-2.5 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-12 h-12 drop-shadow-xs">
                    <path fill="#166e40" d="M37 6H17a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                    <path fill="#23a455" d="M37 6H24v36h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                    <path fill="#2ecc71" opacity=".35" d="M24 13h15v4H24zm0 7h15v4H24zm0 7h15v4H24zm0 7h15v4H24z"/>
                    <path fill="#107c41" d="M22 13H8a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V15a2 2 0 0 0-2-2z"/>
                    <path fill="#ffffff" d="M12.4 28.5l2.4-4.8 2.4 4.8h2.3l-3.5-6.5 3.3-6.5h-2.3l-2.2 4.7-2.2-4.7h-2.3l3.3 6.5-3.5 6.5h2.3z"/>
                  </svg>
                </div>
                <div class="text-sm font-medium text-gray-800">
                  Klik untuk memilih berkas Excel atau drag & drop ke sini
                </div>
                <div class="text-xs text-gray-400 mt-1">
                  Format berkas: .xlsx, .xls, atau .csv (Maks. 10MB)
                </div>
              </div>

              <!-- Selected file preview card -->
              <div
                v-else
                class="border border-emerald-200 bg-emerald-50/50 rounded-xl p-4 flex items-center justify-between"
              >
                <div class="flex items-center gap-3 truncate pr-2">
                  <!-- Authentic Microsoft Excel Logo -->
                  <div class="w-10 h-10 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-10 h-10 drop-shadow-xs">
                      <path fill="#166e40" d="M37 6H17a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                      <path fill="#23a455" d="M37 6H24v36h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                      <path fill="#2ecc71" opacity=".35" d="M24 13h15v4H24zm0 7h15v4H24zm0 7h15v4H24zm0 7h15v4H24z"/>
                      <path fill="#107c41" d="M22 13H8a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V15a2 2 0 0 0-2-2z"/>
                      <path fill="#ffffff" d="M12.4 28.5l2.4-4.8 2.4 4.8h2.3l-3.5-6.5 3.3-6.5h-2.3l-2.2 4.7-2.2-4.7h-2.3l3.3 6.5-3.5 6.5h2.3z"/>
                    </svg>
                  </div>
                  <div class="truncate">
                    <div class="text-sm font-semibold text-gray-900 truncate">
                      {{ selectedFile.name }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatFileSize(selectedFile.size) }} &bull; Siap diunggah
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  @click="removeSelectedFile"
                  :disabled="uploading"
                  class="p-2 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-white transition cursor-pointer shrink-0"
                  title="Hapus / Ganti Berkas"
                >
                  <Trash2Icon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Tipe Invoice Selector -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                Tipe Invoice Default (jika kolom di Excel kosong):
              </label>
              <select
                v-model="selectedInvoiceType"
                class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition cursor-pointer"
              >
                <option value="NPS FL">NPS FL (National Program Scheme - Front Line)</option>
                <option value="DSA">DSA (Direct Sales Agent)</option>
                <option value="REGULAR">REGULAR</option>
              </select>
            </div>

            <!-- Error Alert -->
            <div
              v-if="uploadError"
              class="p-3.5 bg-rose-50 border border-rose-200 text-xs text-rose-700 rounded-xl flex items-start gap-2.5"
            >
              <AlertCircleIcon class="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
              <span>{{ uploadError }}</span>
            </div>
          </div>

          <!-- Body: Preview (sebelum disimpan) -->
          <div v-else-if="preview && !result" class="p-6 space-y-4 overflow-y-auto min-h-0">
            <p class="text-xs text-gray-600">
              <strong class="text-gray-900">{{ selectedFile?.name }}</strong> · tipe invoice default {{ selectedInvoiceType }}. Belum ada yang disimpan.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
              <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-slate-500">Total Baris</div>
                <div class="text-lg font-bold text-slate-900 mt-0.5">{{ preview.total_rows }}</div>
              </div>
              <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-emerald-600">Akan Masuk</div>
                <div class="text-lg font-bold text-emerald-700 mt-0.5">{{ preview.success }}</div>
              </div>
              <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-rose-600">Tidak Masuk (Error)</div>
                <div class="text-lg font-bold text-rose-700 mt-0.5">{{ preview.failed }}</div>
              </div>
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-amber-600">Peringatan Rumus</div>
                <div class="text-lg font-bold text-amber-700 mt-0.5">{{ preview.warning }}</div>
              </div>
            </div>

            <p
              v-for="(count, areaLabel) in (preview.moved_to_area || {})"
              :key="areaLabel"
              class="text-xs text-gray-600"
            >
              {{ count }} draft masuk ke tab <strong class="text-gray-900">{{ areaLabel }}</strong> (sesuai Master Dealer).
            </p>

            <!-- Filter baris -->
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs">
              <button
                v-for="option in previewFilters"
                :key="option.value"
                type="button"
                @click="previewFilter = option.value"
                :class="[
                  'h-7 px-3 rounded-md font-medium transition cursor-pointer',
                  previewFilter === option.value ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800',
                ]"
              >
                {{ option.label }} ({{ option.count }})
              </button>
            </div>

            <div class="max-h-[45vh] overflow-auto border border-gray-200 rounded-xl">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-14">Baris</TableHead>
                    <TableHead>Dealer</TableHead>
                    <TableHead>No CN</TableHead>
                    <TableHead>Program</TableHead>
                    <TableHead>Tipe</TableHead>
                    <TableHead class="text-right">Net Pay</TableHead>
                    <TableHead class="min-w-[200px]">Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="row in visiblePreviewRows" :key="row.row" :class="row.status === 'error' && 'bg-rose-50/40'">
                    <TableCell class="text-xs tabular-nums text-gray-500">{{ row.row }}</TableCell>
                    <TableCell class="text-xs">
                      <span class="block font-medium text-gray-900">{{ row.dealer_name || '-' }}</span>
                      <span v-if="row.dealer_code" class="block text-gray-500">{{ row.dealer_code }}</span>
                    </TableCell>
                    <TableCell class="text-xs tabular-nums text-gray-800">{{ row.cn_number || '-' }}</TableCell>
                    <TableCell class="text-xs text-gray-800">{{ row.program_name || '-' }}</TableCell>
                    <TableCell class="text-xs text-gray-800 whitespace-nowrap">{{ row.invoice_type || '-' }}</TableCell>
                    <TableCell class="text-xs text-right tabular-nums text-gray-900 whitespace-nowrap">{{ rupiah(row.netpay) }}</TableCell>
                    <TableCell class="text-xs">
                      <template v-if="row.status === 'ready'">
                        <span class="inline-flex items-center gap-1.5 text-emerald-700 font-medium">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Masuk ke draft
                        </span>
                        <span v-if="row.area && movedAreas.includes(row.area)" class="block text-gray-500">Tab {{ row.area }}</span>
                        <span v-if="row.formula_warning" class="block text-amber-700">Angka pajak beda dengan sistem</span>
                      </template>
                      <template v-else>
                        <span class="inline-flex items-center gap-1.5 text-rose-700 font-medium">
                          <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Tidak masuk
                        </span>
                        <span v-for="message in row.errors" :key="message" class="block text-rose-700">{{ message }}</span>
                      </template>
                    </TableCell>
                  </TableRow>
                  <TableRow v-if="visiblePreviewRows.length === 0">
                    <TableCell colspan="7" class="text-xs text-center text-gray-500 py-6">Tidak ada baris.</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            <!-- Error Alert -->
            <div
              v-if="uploadError"
              class="p-3.5 bg-rose-50 border border-rose-200 text-xs text-rose-700 rounded-xl flex items-start gap-2.5"
            >
              <AlertCircleIcon class="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
              <span>{{ uploadError }}</span>
            </div>
          </div>

          <!-- Body: Result View (Setelah berhasil/gagal import) -->
          <div v-else class="p-6 space-y-4 overflow-y-auto min-h-0">
            <!-- Summary Stats Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
              <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-slate-500">Total Baris</div>
                <div class="text-lg font-bold text-slate-900 mt-0.5">{{ result.total_rows }}</div>
              </div>
              <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-emerald-600">Masuk ke Draft</div>
                <div class="text-lg font-bold text-emerald-700 mt-0.5">{{ result.success }}</div>
              </div>
              <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-rose-600">Tidak Masuk (Error)</div>
                <div class="text-lg font-bold text-rose-700 mt-0.5">{{ result.failed }}</div>
              </div>
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-center">
                <div class="text-[11px] font-medium text-amber-600">Peringatan Rumus</div>
                <div class="text-lg font-bold text-amber-700 mt-0.5">{{ result.warning }}</div>
              </div>
            </div>

            <!-- Draft yang masuk ke area lain karena tokonya milik area itu di Master Dealer -->
            <p
              v-for="(count, areaLabel) in (result.moved_to_area || {})"
              :key="areaLabel"
              class="text-xs text-gray-600"
            >
              {{ count }} draft masuk ke tab <strong class="text-gray-900">{{ areaLabel }}</strong> karena tokonya terdaftar di area itu pada Master Dealer.
            </p>

            <!-- Error Details Table if any -->
            <div v-if="result.errors && result.errors.length > 0">
              <div class="text-xs font-semibold text-rose-700 flex items-center gap-1.5">
                <AlertCircleIcon class="w-3.5 h-3.5" />
                <span>Baris yang tidak masuk ke draft</span>
              </div>
              <p class="mt-1 mb-2 text-xs text-gray-600">Perbaiki baris ini di Excel, lalu import ulang. Baris yang sudah masuk tidak akan dobel.</p>
              <div class="max-h-56 overflow-y-auto border border-gray-200 rounded-xl">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead class="w-16">Baris</TableHead>
                      <TableHead class="w-28">Kolom</TableHead>
                      <TableHead>Nilai</TableHead>
                      <TableHead>Pesan Kesalahan</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="(err, idx) in result.errors" :key="idx">
                      <TableCell class="font-mono font-bold">{{ err.row }}</TableCell>
                      <TableCell class="font-mono text-gray-700 text-xs">{{ err.field }}</TableCell>
                      <TableCell class="text-gray-600 truncate max-w-[120px] text-xs">{{ err.value || '-' }}</TableCell>
                      <TableCell class="text-rose-600 text-xs">{{ err.error }}</TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="px-6 py-4 bg-gray-50/80 border-t border-gray-100 flex items-center justify-end gap-2.5">
            <template v-if="!preview && !result">
              <button
                type="button"
                @click="close"
                :disabled="uploading"
                class="h-10 px-4 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-100 transition cursor-pointer disabled:opacity-50"
              >
                Batal
              </button>
              <button
                type="button"
                @click="loadPreview"
                :disabled="!selectedFile || uploading"
                class="h-10 px-5 rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition cursor-pointer shadow-xs disabled:opacity-50 flex items-center gap-2"
              >
                <span
                  v-if="uploading"
                  class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                ></span>
                <EyeIcon v-else class="w-4 h-4 text-white" />
                <span>{{ uploading ? 'Membaca file...' : 'Lihat Preview' }}</span>
              </button>
            </template>

            <template v-else-if="preview && !result">
              <button
                type="button"
                @click="backToFile"
                :disabled="uploading"
                class="h-10 px-4 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-100 transition cursor-pointer disabled:opacity-50"
              >
                Kembali
              </button>
              <button
                type="button"
                @click="uploadFile"
                :disabled="!preview.success || uploading"
                class="h-10 px-5 rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition cursor-pointer shadow-xs disabled:opacity-50 flex items-center gap-2"
              >
                <span
                  v-if="uploading"
                  class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                ></span>
                <UploadCloudIcon v-else class="w-4 h-4 text-white" />
                <span>{{ uploading ? 'Mengimport...' : `Import ${preview.success} Baris` }}</span>
              </button>
            </template>

            <template v-else>
              <button
                type="button"
                @click="resetAndClose"
                class="h-10 px-5 rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition cursor-pointer shadow-xs"
              >
                Selesai & Tutup
              </button>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import {
  UploadCloud as UploadCloudIcon,
  X as XIcon,
  Trash2 as Trash2Icon,
  AlertCircle as AlertCircleIcon,
  Eye as EyeIcon,
} from 'lucide-vue-next';
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
const isDragging = ref(false);

// Preview: isi file dicek dulu tanpa disimpan, lalu diimport bila sudah sesuai
const preview = ref(null);
const previewFilter = ref('all');

const previewFilters = computed(() => {
  const rows = preview.value?.rows || [];
  return [
    { value: 'all', label: 'Semua', count: rows.length },
    { value: 'ready', label: 'Akan masuk', count: rows.filter((row) => row.status === 'ready').length },
    { value: 'error', label: 'Error', count: rows.filter((row) => row.status === 'error').length },
  ];
});

const visiblePreviewRows = computed(() =>
  (preview.value?.rows || []).filter((row) => previewFilter.value === 'all' || row.status === previewFilter.value),
);

// Area tujuan hanya disebut untuk draft yang pindah ke tab area lain
const movedAreas = computed(() => Object.keys(preview.value?.moved_to_area || {}));

const rupiah = (value) => `Rp ${new Intl.NumberFormat('id-ID').format(Math.round(Number(value) || 0))}`;

const triggerFileInput = () => {
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const handleFileChange = (e) => {
  const files = e.target.files;
  if (files && files.length > 0) {
    selectedFile.value = files[0];
    uploadError.value = null;
  }
};

const handleDrop = (e) => {
  isDragging.value = false;
  const files = e.dataTransfer.files;
  if (files && files.length > 0) {
    selectedFile.value = files[0];
    uploadError.value = null;
  }
};

const removeSelectedFile = () => {
  selectedFile.value = null;
  preview.value = null;
  if (fileInput.value) fileInput.value.value = '';
};

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

/**
 * Kirim file ke server: preview hanya membaca & memvalidasi, tanpa menyimpan draft.
 */
const sendFile = async ({ previewOnly }) => {
  const formData = new FormData();
  formData.append('file', selectedFile.value);
  formData.append('invoice_type', selectedInvoiceType.value);
  if (previewOnly) formData.append('preview', '1');

  const res = await axios.post('/api/drafts/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

  return res.data.data;
};

const uploadErrorMessage = (err) =>
  err.response?.data?.errors?.file?.[0] ||
  err.response?.data?.message ||
  (err.response?.status === 413 ? 'Ukuran file terlalu besar (Payload Too Large).' : null) ||
  (err.response?.status === 419 ? 'Sesi kedaluwarsa. Silakan refresh browser.' : null) ||
  err.message ||
  'Gagal mengunggah file.';

const loadPreview = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  uploadError.value = null;

  try {
    preview.value = await sendFile({ previewOnly: true });
    previewFilter.value = 'all';
  } catch (err) {
    console.error('Import preview error:', err);
    uploadError.value = uploadErrorMessage(err);
  } finally {
    uploading.value = false;
  }
};

const uploadFile = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  uploadError.value = null;

  try {
    result.value = await sendFile({ previewOnly: false });
    emit('imported');
  } catch (err) {
    console.error('Import upload error:', err);
    uploadError.value = uploadErrorMessage(err);
  } finally {
    uploading.value = false;
  }
};

const backToFile = () => {
  preview.value = null;
  uploadError.value = null;
};

const close = () => {
  if (uploading.value) return;
  preview.value = null;
  emit('close');
};

const resetAndClose = () => {
  result.value = null;
  preview.value = null;
  selectedFile.value = null;
  if (fileInput.value) fileInput.value.value = '';
  emit('close');
};
</script>

<style scoped>
.modal-scale-enter-active,
.modal-scale-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-scale-enter-from,
.modal-scale-leave-to {
  opacity: 0;
  transform: scale(0.96);
}
</style>
