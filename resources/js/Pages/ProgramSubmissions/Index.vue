<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900">Form Program REALME Jabar</h1>
          <span
            v-if="autoRefresh"
            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
            title="Pembaruan data otomatis aktif"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            Realtime
          </span>
        </div>
        <p class="text-sm text-gray-500 mt-0.5">
          Respon formulir program REALME dan dokumen bukti transaksi yang terhubung ke Google Sheets.
        </p>
      </div>

      <!-- Action Buttons (Tanpa warna & tanpa icon) -->
      <div class="flex items-center gap-2">
        <a
          :href="googleSheetUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="h-9 px-3.5 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white text-xs text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs cursor-pointer"
          title="Buka file Google Spreadsheet"
        >
          Buka Spreadsheet
        </a>

        <button
          type="button"
          @click="showSetupModal = true"
          class="h-9 px-3.5 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white text-xs text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs cursor-pointer"
          title="Pengaturan Google Script & Webhook"
        >
          Pengaturan & Script
        </button>

        <button
          type="button"
          @click="triggerSync"
          :disabled="isSyncing"
          class="h-9 px-3.5 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white text-xs text-gray-700 hover:bg-gray-50 hover:text-gray-900 disabled:opacity-50 transition shadow-2xs cursor-pointer"
        >
          {{ isSyncing ? 'Menyinkronkan...' : 'Sinkronkan Sekarang' }}
        </button>
      </div>
    </div>

    <!-- Alert Status Sinkronisasi -->
    <div
      v-if="syncMessage"
      :class="[
        'p-3 rounded-lg border text-xs flex items-center justify-between transition',
        syncError
          ? 'bg-rose-50 border-rose-200 text-rose-700'
          : 'bg-emerald-50 border-emerald-200 text-emerald-800'
      ]"
    >
      <div class="flex items-center gap-2">
        <AlertCircleIcon v-if="syncError" class="w-4 h-4 shrink-0" />
        <CheckCircleIcon v-else class="w-4 h-4 shrink-0" />
        <span>{{ syncMessage }}</span>
      </div>
      <button @click="syncMessage = ''" class="text-xs font-medium hover:underline ml-4 cursor-pointer">
        Tutup
      </button>
    </div>

    <!-- Toolbar Filters -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 py-1">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
          <SearchIcon class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" />
          <input
            v-model="filters.search"
            @input="debounceFetch"
            type="text"
            placeholder="Cari dealer, ID Real, program, sales..."
            class="h-9 w-72 pl-9 pr-3 rounded-md border border-gray-200 bg-white text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
          />
        </div>

        <select
          v-model="filters.region"
          @change="fetchSubmissions(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Region</option>
          <option v-for="reg in regionOptions" :key="reg" :value="reg">
            {{ reg }}
          </option>
        </select>

        <button
          v-if="filters.search || filters.region"
          type="button"
          @click="resetFilters"
          class="h-9 px-2.5 text-xs text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-md transition cursor-pointer"
        >
          Reset Filter
        </button>
      </div>

      <!-- Realtime Auto-Refresh Toggle -->
      <div class="flex items-center gap-3">
        <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
          <input
            type="checkbox"
            v-model="autoRefresh"
            class="rounded border-gray-300 text-black focus:ring-black cursor-pointer"
          />
          <span>Auto-Refresh (15d)</span>
        </label>

        <span v-if="lastUpdatedText" class="text-xs text-gray-400">
          Update: {{ lastUpdatedText }}
        </span>
      </div>
    </div>

    <!-- Official Shadcn Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-2xs">
      <Table>
        <TableHeader>
          <TableRow class="bg-gray-50/80 border-b border-gray-200 uppercase tracking-wider text-[11px] font-semibold hover:bg-gray-50/80">
            <TableHead class="w-[44px] text-center font-semibold text-gray-600">No</TableHead>
            <TableHead class="whitespace-nowrap font-semibold text-gray-600">Waktu</TableHead>
            <TableHead class="whitespace-nowrap font-semibold text-gray-600">Region</TableHead>
            <TableHead class="whitespace-nowrap font-semibold text-gray-600">ID Real</TableHead>
            <TableHead class="min-w-[170px] font-semibold text-gray-600">Nama Dealer</TableHead>
            <TableHead class="min-w-[220px] font-semibold text-gray-600">Nama Program</TableHead>
            <TableHead class="whitespace-nowrap font-semibold text-gray-600">Nama Sales</TableHead>
            <TableHead class="w-[60px] text-center font-semibold text-gray-600">CN</TableHead>
            <TableHead class="w-[60px] text-center font-semibold text-gray-600">Agr</TableHead>
            <TableHead class="w-[70px] text-center font-semibold text-gray-600">Faktur</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <!-- Loading State -->
          <TableEmpty v-if="loading && submissions.length === 0" :colspan="10">
            <div class="inline-flex items-center gap-2 text-gray-500 py-6">
              <RefreshCwIcon class="w-4 h-4 animate-spin text-gray-400" />
              <span>Memuat data form program...</span>
            </div>
          </TableEmpty>

          <!-- Empty State -->
          <TableEmpty v-else-if="submissions.length === 0" :colspan="10">
            <div class="max-w-md mx-auto py-6 space-y-1.5 text-gray-500">
              <p class="font-medium text-gray-800">Belum ada data form program yang tersimpan.</p>
              <p class="text-xs text-gray-500">
                Klik tombol <strong>"Sinkronkan Sekarang"</strong> di atas untuk memuat data dari spreadsheet Anda.
              </p>
            </div>
          </TableEmpty>

          <!-- Data Rows -->
          <TableRow
            v-else
            v-for="(row, idx) in submissions"
            :key="row.id"
            class="hover:bg-gray-50/80 transition"
          >
            <TableCell class="text-center text-gray-500 text-xs py-2.5">
              {{ (pagination.current_page - 1) * pagination.per_page + idx + 1 }}
            </TableCell>
            <TableCell class="whitespace-nowrap text-gray-700 text-xs py-2.5 leading-tight">
              <div>{{ formatTimestamp(row.submission_timestamp).date }}</div>
              <div class="text-[11px] text-gray-400">{{ formatTimestamp(row.submission_timestamp).time }}</div>
            </TableCell>
            <TableCell class="whitespace-nowrap text-gray-700 text-xs py-2.5">
              {{ row.region || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap text-gray-700 text-xs py-2.5">
              {{ row.id_real || '-' }}
            </TableCell>
            <TableCell class="text-gray-700 text-xs py-2.5 leading-snug">
              <div class="line-clamp-2" :title="row.dealer_name">{{ row.dealer_name || '-' }}</div>
            </TableCell>
            <TableCell class="text-gray-700 text-xs py-2.5 leading-snug">
              <div class="line-clamp-2" :title="row.program_name">{{ row.program_name || '-' }}</div>
            </TableCell>
            <TableCell class="whitespace-nowrap text-gray-700 text-xs py-2.5">
              {{ row.sales_name || '-' }}
            </TableCell>

            <!-- Dokumen Credit Note -->
            <TableCell class="text-center py-2.5 whitespace-nowrap">
              <a
                v-if="isValidUrl(row.credit_note_url)"
                :href="row.credit_note_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs transition cursor-pointer"
                title="Buka Dokumen Credit Note di Google Drive"
              >
                <FileTextIcon class="w-3 h-3 text-blue-600 shrink-0" />
                <span>CN</span>
              </a>
              <span v-else class="text-gray-300 text-xs">-</span>
            </TableCell>

            <!-- Agreement -->
            <TableCell class="text-center py-2.5 whitespace-nowrap">
              <a
                v-if="isValidUrl(row.agreement_url)"
                :href="row.agreement_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs transition cursor-pointer"
                title="Buka Dokumen Agreement di Google Drive"
              >
                <FileTextIcon class="w-3 h-3 text-indigo-600 shrink-0" />
                <span>Agr</span>
              </a>
              <span v-else class="text-gray-300 text-xs">-</span>
            </TableCell>

            <!-- Faktur Pajak -->
            <TableCell class="text-center py-2.5 whitespace-nowrap">
              <a
                v-if="isValidUrl(row.tax_invoice_url)"
                :href="row.tax_invoice_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs transition cursor-pointer"
                title="Buka Faktur Pajak di Google Drive"
              >
                <FileTextIcon class="w-3 h-3 text-emerald-600 shrink-0" />
                <span>Faktur</span>
              </a>
              <span v-else class="text-gray-300 text-xs">-</span>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <!-- Pagination Footer -->
      <div
        v-if="pagination.total > 0"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-200 bg-gray-50/50 text-xs text-gray-500"
      >
        <div>
          Menampilkan <span class="font-semibold text-gray-900">{{ submissions.length }}</span> dari
          <span class="font-semibold text-gray-900">{{ pagination.total }}</span> total respon
        </div>
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="pagination.current_page <= 1 || loading"
            @click="fetchSubmissions(pagination.current_page - 1)"
            class="px-2.5 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
          >
            Sebelumnya
          </button>
          <span class="px-2 py-1 font-medium text-gray-700">
            Halaman {{ pagination.current_page }} dari {{ pagination.last_page || 1 }}
          </span>
          <button
            type="button"
            :disabled="pagination.current_page >= pagination.last_page || loading"
            @click="fetchSubmissions(pagination.current_page + 1)"
            class="px-2.5 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Panduan & Pengaturan Google Apps Script -->
    <div
      v-if="showSetupModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl border border-gray-200 shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
          <div>
            <h2 class="text-base font-bold text-gray-900">Pengaturan Google Apps Script & Webhook</h2>
            <p class="text-xs text-gray-500">
              Menghubungkan data spreadsheet yang dibatasi (restricted) agar dapat terbaca di web aplikasi.
            </p>
          </div>
          <button
            @click="showSetupModal = false"
            class="w-7 h-7 rounded-lg hover:bg-gray-200 flex items-center justify-center text-gray-400 hover:text-gray-700 transition cursor-pointer"
          >
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto space-y-4 text-xs text-gray-700">
          <!-- Step 1: Input Web App URL -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-2.5">
            <label class="block font-semibold text-gray-900">
              1. URL Google Apps Script Web App:
            </label>
            <div class="flex gap-2">
              <input
                v-model="configUrlInput"
                type="url"
                placeholder="https://script.google.com/macros/s/.../exec"
                class="h-9 flex-1 px-3 rounded-md border border-gray-300 bg-white text-xs font-mono focus:outline-none focus:ring-1 focus:ring-black"
              />
              <button
                type="button"
                @click="saveConfigAndSync"
                :disabled="isSavingConfig || !configUrlInput"
                class="px-4 h-9 rounded-md bg-black text-white text-xs font-medium hover:bg-neutral-800 disabled:opacity-50 transition cursor-pointer"
              >
                {{ isSavingConfig ? 'Menyimpan...' : 'Simpan & Sinkronkan' }}
              </button>
            </div>
            <p class="text-[11px] text-gray-500">
              URL ini dihasilkan setelah Anda menerapkan (Deploy) script di bawah sebagai Web App di akun Google Anda.
            </p>
          </div>

          <!-- Step 2: Panduan Script -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-gray-900">2. Kode Google Apps Script (Siap Salin):</span>
              <button
                type="button"
                @click="copyScriptCode"
                class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-700 hover:text-blue-900 cursor-pointer"
              >
                <CheckIcon v-if="copied" class="w-3.5 h-3.5 text-emerald-600" />
                <CopyIcon v-else class="w-3.5 h-3.5" />
                <span>{{ copied ? 'Tersalin ke Clipboard!' : 'Salin Kode Script' }}</span>
              </button>
            </div>

            <pre class="p-3 rounded-lg bg-gray-900 text-gray-100 font-mono text-[11px] overflow-x-auto leading-relaxed border border-gray-800 max-h-56"><code>{{ scriptCode }}</code></pre>
          </div>

          <!-- Step 3: Langkah Penerapan -->
          <div class="space-y-1.5 text-gray-600">
            <div class="font-semibold text-gray-900">3. Cara Pasang di Google Spreadsheet:</div>
            <ol class="list-decimal list-inside space-y-1 pl-1">
              <li>Buka spreadsheet Google Form Anda.</li>
              <li>Klik menu <strong>Ekstensi (Extensions)</strong> > <strong>Apps Script</strong>.</li>
              <li>Hapus teks di editor, lalu tempel kode script di atas.</li>
              <li>Klik tombol biru <strong>Deploy (Terapkan)</strong> > <strong>New deployment (Deployment baru)</strong>.</li>
              <li>Pilih jenis <strong>Web app (Aplikasi Web)</strong>:
                <ul class="list-disc list-inside pl-4 mt-0.5 text-gray-500">
                  <li><em>Execute as</em>: <strong>Me (Saya)</strong></li>
                  <li><em>Who has access</em>: <strong>Anyone (Siapa saja)</strong></li>
                </ul>
              </li>
              <li>Klik <strong>Deploy</strong>, lalu salin URL Web App yang muncul ke kotak input di atas.</li>
            </ol>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50 flex justify-end">
          <button
            type="button"
            @click="showSetupModal = false"
            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import {
  Search as SearchIcon,
  RefreshCw as RefreshCwIcon,
  ExternalLink as ExternalLinkIcon,
  Settings as SettingsIcon,
  AlertCircle as AlertCircleIcon,
  CheckCircle as CheckCircleIcon,
  FileText as FileTextIcon,
  Copy as CopyIcon,
  Check as CheckIcon,
  X as XIcon,
} from 'lucide-vue-next';
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
  TableEmpty,
} from '@/components/ui/table';

const googleSheetUrl = 'https://docs.google.com/spreadsheets/d/1jf_i5r4Nn3q0RE6n_gIyCFn1XPlAWjYdqQOvWXewfXs/edit?resourcekey=&gid=2012509458#gid=2012509458';

const submissions = ref([]);
const regionOptions = ref([]);
const loading = ref(false);
const isSyncing = ref(false);
const syncMessage = ref('');
const syncError = ref(false);
const autoRefresh = ref(true);
const lastUpdatedText = ref('');
const showSetupModal = ref(false);
const configUrlInput = ref('');
const isSavingConfig = ref(false);
const copied = ref(false);

const filters = reactive({
  search: '',
  region: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

let debounceTimer = null;
let pollTimer = null;

const scriptCode = `function doGet(e) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName("Form Responses 1") || ss.getActiveSheet();
  var lastRow = sheet.getLastRow();
  var lastCol = sheet.getLastColumn();
  
  if (lastRow < 2) {
    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      total: 0,
      rows: []
    })).setMimeType(ContentService.MimeType.JSON);
  }
  
  var header = sheet.getRange(1, 1, 1, lastCol).getValues()[0];
  var limit = e && e.parameter && e.parameter.limit ? parseInt(e.parameter.limit) : 0;
  
  var dataRows;
  // Jika diminta data terbaru (limit), ambil hanya baris paling akhir agar respon sangat cepat (< 1 detik)
  if (limit > 0 && lastRow > 1) {
    var startRow = Math.max(2, lastRow - limit + 1);
    var numRows = lastRow - startRow + 1;
    dataRows = sheet.getRange(startRow, 1, numRows, lastCol).getValues();
  } else {
    dataRows = sheet.getRange(2, 1, lastRow - 1, lastCol).getValues();
  }
  
  var allRows = [header].concat(dataRows);
  
  return ContentService.createTextOutput(JSON.stringify({
    success: true,
    total: lastRow - 1,
    rows: allRows
  })).setMimeType(ContentService.MimeType.JSON);
}

function onFormSubmit(e) {
  // Masukkan domain web publik / tunnel Anda di sini untuk push instan
  var webhookUrl = "${window.location.origin}/api/webhooks/form-program";
  if (!webhookUrl || webhookUrl.indexOf("http") !== 0 || webhookUrl.indexOf("localhost") !== -1) return;
  
  var payload = {
    values: e ? e.values : null,
    namedValues: e ? e.namedValues : null
  };
  
  UrlFetchApp.fetch(webhookUrl, {
    method: "post",
    contentType: "application/json",
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });
}`;

const isValidUrl = (url) => {
  if (!url) return false;
  return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'));
};

const formatTimestamp = (ts) => {
  if (!ts) return { date: '-', time: '' };
  try {
    const d = new Date(ts);
    if (!isNaN(d.getTime())) {
      const day = String(d.getDate()).padStart(2, '0');
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const year = d.getFullYear();
      const hours = String(d.getHours()).padStart(2, '0');
      const minutes = String(d.getMinutes()).padStart(2, '0');
      return {
        date: `${day}/${month}/${year}`,
        time: `${hours}:${minutes}`,
      };
    }
  } catch (e) {
    // fallback
  }

  if (typeof ts === 'string') {
    if (ts.includes('T')) {
      const parts = ts.split('T');
      const timePart = parts[1] ? parts[1].slice(0, 5) : '';
      return { date: parts[0], time: timePart };
    }
    if (ts.includes(' ')) {
      const parts = ts.split(' ');
      const timePart = parts[1] ? parts[1].slice(0, 5) : '';
      return { date: parts[0], time: timePart };
    }
  }

  return { date: String(ts), time: '' };
};

const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchSubmissions(1);
  }, 300);
};

const resetFilters = () => {
  filters.search = '';
  filters.region = '';
  fetchSubmissions(1);
};

const fetchSubmissions = async (page = 1, silent = false) => {
  if (!silent) loading.value = true;
  try {
    const res = await axios.get('/api/program-submissions', {
      params: {
        page,
        search: filters.search,
        region: filters.region,
      },
    });

    const data = res.data;
    submissions.value = data.submissions.data || [];
    pagination.current_page = data.submissions.current_page;
    pagination.last_page = data.submissions.last_page;
    pagination.per_page = data.submissions.per_page;
    pagination.total = data.submissions.total;
    regionOptions.value = data.regions || [];

    if (data.configured_webapp_url && !configUrlInput.value) {
      configUrlInput.value = data.configured_webapp_url;
    }

    const now = new Date();
    lastUpdatedText.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch (err) {
    console.error('Gagal mengambil data submissions', err);
  } finally {
    if (!silent) loading.value = false;
  }
};

const triggerSync = async () => {
  isSyncing.value = true;
  syncMessage.value = '';
  syncError.value = false;
  try {
    const res = await axios.post('/api/program-submissions/sync', {
      url: configUrlInput.value,
    });
    syncMessage.value = res.data.message || 'Sinkronisasi berhasil.';
    syncError.value = false;
    await fetchSubmissions(pagination.current_page, true);
  } catch (err) {
    syncError.value = true;
    syncMessage.value = err.response?.data?.message || 'Gagal sinkronisasi data dari Google Apps Script.';
  } finally {
    isSyncing.value = false;
  }
};

const saveConfigAndSync = async () => {
  if (!configUrlInput.value) return;
  isSavingConfig.value = true;
  try {
    await axios.post('/api/program-submissions/config', {
      url: configUrlInput.value,
    });
    await triggerSync();
    showSetupModal.value = false;
  } catch (err) {
    syncError.value = true;
    syncMessage.value = err.response?.data?.message || 'Gagal menyimpan URL Google Apps Script.';
  } finally {
    isSavingConfig.value = false;
  }
};

const copyScriptCode = () => {
  navigator.clipboard.writeText(scriptCode);
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2500);
};

let isAutoSyncing = false;
let autoSyncTimer = null;

const runAutoSync = async () => {
  if (isAutoSyncing || isSyncing.value || !autoRefresh.value) return;
  isAutoSyncing = true;
  try {
    const res = await axios.post('/api/program-submissions/sync', {
      limit: 50,
    });
    if (res.data?.data?.new_count > 0) {
      syncMessage.value = `Otomatis menarik ${res.data.data.new_count} respon baru dari spreadsheet.`;
      syncError.value = false;
      await fetchSubmissions(pagination.current_page, true);
    }
  } catch (err) {
    // Silent fail in background so user experience is smooth
  } finally {
    isAutoSyncing = false;
  }
};

onMounted(() => {
  fetchSubmissions(1);
  // Auto-sync di background saat halaman pertama kali dibuka
  runAutoSync();

  // Refresh tampilan data setiap 15 detik
  pollTimer = setInterval(() => {
    if (autoRefresh.value) {
      fetchSubmissions(pagination.current_page, true);
    }
  }, 15000);

  // Auto-sync berkala dari Google Sheets setiap 30 detik
  autoSyncTimer = setInterval(() => {
    if (autoRefresh.value) {
      runAutoSync();
    }
  }, 30000);
});

onUnmounted(() => {
  clearInterval(pollTimer);
  clearInterval(autoSyncTimer);
  clearTimeout(debounceTimer);
});
</script>
