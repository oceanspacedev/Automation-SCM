<template>
  <div class="space-y-4 font-sans">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800 font-medium">Laravel Logs</span>
    </div>

    <!-- Header & Page Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Laravel Logs</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Catatan error, warning, dan aktivitas scheduler aplikasi.
          <template v-if="currentFileInfo">
            · {{ currentFileInfo.name }} ({{ formatBytes(fileSize) }})
          </template>
        </p>
      </div>

      <div v-if="!isForbidden" class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          @click="fetchLogs(meta.page)"
          :disabled="isLoading"
          class="h-9 px-3 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs transition inline-flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap disabled:opacity-50"
        >
          <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', isLoading && 'animate-spin text-gray-900']" />
          <span>Refresh</span>
        </button>

        <button
          type="button"
          @click="downloadLog"
          :disabled="!currentFile || isLoading"
          class="h-9 px-3 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs transition inline-flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <DownloadIcon class="w-3.5 h-3.5 text-gray-500" />
          <span>Download</span>
        </button>

        <button
          type="button"
          @click="clearLog"
          :disabled="!currentFile || isClearing || fileSize === 0"
          class="h-9 px-3 border border-gray-300 bg-white text-rose-600 hover:bg-rose-50 hover:border-rose-200 text-xs font-medium rounded-lg shadow-2xs transition inline-flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Trash2Icon class="w-3.5 h-3.5" />
          <span>{{ isClearing ? 'Mengosongkan...' : 'Kosongkan' }}</span>
        </button>
      </div>
    </div>

    <p v-if="isForbidden" class="max-w-3xl rounded-xl border border-gray-200 bg-white px-5 py-4 text-xs text-gray-600">
      Hanya Admin yang bisa mengakses Laravel Logs.
    </p>

    <!-- Table Container Card (Filament style, sama seperti Invoices & Riwayat Email) -->
    <div v-else class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Card Toolbar -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Left: Segmented level tabs -->
        <div class="flex flex-wrap items-center gap-1.5">
          <button
            v-for="tab in levelTabs"
            :key="tab.value"
            type="button"
            @click="selectLevel(tab.value)"
            :class="[
              'h-8 px-3 rounded-lg text-xs transition inline-flex items-center gap-1.5 cursor-pointer whitespace-nowrap',
              filters.level === tab.value
                ? 'border border-gray-900 bg-white text-gray-950 font-semibold shadow-2xs'
                : 'border border-transparent text-gray-500 hover:text-gray-900 hover:bg-gray-50 font-medium'
            ]"
          >
            <span>{{ tab.label }}</span>
            <span class="text-[11px] tabular-nums" :class="filters.level === tab.value ? 'text-gray-600' : 'text-gray-400'">
              {{ formatNumber(summary[tab.countKey] ?? 0) }}
            </span>
          </button>
        </div>

        <!-- Right: File selector & Search -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
          <div v-if="files.length > 0" class="relative sm:w-48">
            <select
              v-model="filters.file"
              @change="onFileChange"
              aria-label="Pilih file log"
              class="h-9 w-full pl-3 pr-8 text-xs text-gray-800 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 appearance-none cursor-pointer transition"
            >
              <option v-for="file in files" :key="file.name" :value="file.name">
                {{ file.name }} ({{ formatBytes(file.size) }})
              </option>
            </select>
            <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <div class="relative flex-1 sm:w-64">
            <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="searchInput"
              type="text"
              placeholder="Search"
              class="h-9 w-full pl-9 pr-7 text-xs text-gray-900 bg-white border border-gray-300 rounded-lg placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 transition"
            />
            <button
              v-if="searchInput"
              type="button"
              @click="searchInput = ''"
              class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 text-gray-400 hover:text-gray-700 cursor-pointer"
              title="Hapus pencarian"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Info bar tipis hanya saat ada error muat atau file dipotong -->
      <div
        v-if="loadError"
        role="alert"
        class="px-4 py-2.5 border-b border-gray-100 bg-rose-50/60 text-xs text-rose-700"
      >
        {{ loadError }}
      </div>
      <div
        v-else-if="truncated"
        class="px-4 py-2 border-b border-gray-100 bg-gray-50/70 text-[11px] text-gray-500 flex items-center justify-between gap-2"
      >
        <span>Menampilkan {{ formatBytes(readBytes) }} terbaru dari {{ formatBytes(fileSize) }} agar tetap ringan. Gunakan tombol Download untuk file lengkap.</span>
        <span v-if="currentFileInfo" class="hidden sm:inline text-gray-400 tabular-nums shrink-0">Diperbarui {{ formatDateTime(currentFileInfo.modified_at) }}</span>
      </div>

      <!-- Layar kecil: daftar kartu ringkas -->
      <div class="md:hidden">
        <div v-if="isLoading && entries.length === 0" class="py-10 text-center text-xs text-gray-500">
          Memuat catatan log...
        </div>
        <div v-else-if="entries.length === 0" class="py-10 px-4 text-center text-xs text-gray-400">
          {{ emptyStateText }}
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li
            v-for="entry in entries"
            :key="entry.id"
            @click="openDetail(entry)"
            class="p-4 space-y-2 text-xs text-gray-700 hover:bg-gray-50/70 cursor-pointer transition"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5">
                <FilamentBadge :label="entry.level" :color="badgeColor(entry.category)" />
                <span v-if="entry.env" class="text-[11px] text-gray-400">{{ entry.env }}</span>
              </div>
              <span class="text-[11px] text-gray-500 tabular-nums">{{ formatLogTime(entry.timestamp) }}</span>
            </div>
            <p class="text-xs text-gray-900 line-clamp-3 break-words leading-relaxed">
              {{ entry.message }}
            </p>
          </li>
        </ul>
      </div>

      <!-- Layar lebar: tabel bersih bergaya spreadsheet/Filament -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 w-44 border-r border-gray-200">Waktu</th>
              <th scope="col" class="py-2.5 px-3 w-28 text-center border-r border-gray-200">Level</th>
              <th scope="col" class="py-2.5 px-3 w-24 text-center border-r border-gray-200">Env</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Pesan</th>
              <th scope="col" class="py-2.5 px-3 w-24 text-center">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading && entries.length === 0">
              <td colspan="5" class="py-10 text-center text-gray-500">
                <div class="inline-flex items-center gap-2">
                  <span class="inline-block w-4 h-4 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
                  <span>Memuat catatan log...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="entries.length === 0">
              <td colspan="5" class="py-10 text-center text-gray-400 text-xs">
                {{ emptyStateText }}
              </td>
            </tr>

            <tr
              v-else
              v-for="entry in entries"
              :key="entry.id"
              @click="openDetail(entry)"
              class="hover:bg-gray-50/70 text-gray-700 font-normal transition cursor-pointer"
            >
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap tabular-nums text-gray-600">
                {{ formatLogTime(entry.timestamp) }}
              </td>

              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <FilamentBadge :label="entry.level" :color="badgeColor(entry.category)" />
              </td>

              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-500">
                {{ entry.env || '-' }}
              </td>

              <td class="py-2.5 px-3 border-r border-gray-100 max-w-xl">
                <p class="text-xs text-gray-900 line-clamp-2 break-words leading-relaxed">
                  {{ entry.message }}
                </p>
              </td>

              <td class="py-2.5 px-3 text-center whitespace-nowrap" @click.stop>
                <div class="inline-flex items-center gap-1">
                  <button
                    type="button"
                    @click="openDetail(entry)"
                    class="w-7 h-7 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition cursor-pointer"
                    title="Lihat detail"
                  >
                    <EyeIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="copyEntry(entry)"
                    class="w-7 h-7 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition cursor-pointer"
                    title="Salin log"
                  >
                    <CopyIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination (Komponen standar aplikasi) -->
      <FilamentPagination
        :total="meta.total"
        :current-page="meta.page"
        :last-page="meta.last_page"
        :per-page="meta.per_page"
        :per-page-options="[25, 50, 100]"
        @page-change="goToPage"
        @per-page-change="onPerPageChange"
      />
    </div>

    <!-- Modal Detail Log -->
    <div
      v-if="selectedEntry"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
      @click.self="selectedEntry = null"
    >
      <div class="bg-white rounded-xl max-w-3xl w-full shadow-2xl border border-gray-200 max-h-[86vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
          <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <FilamentBadge :label="selectedEntry.level" :color="badgeColor(selectedEntry.category)" />
              <span v-if="selectedEntry.env" class="text-xs text-gray-500">{{ selectedEntry.env }}</span>
              <span v-if="selectedEntry.timestamp" class="text-xs text-gray-400">·</span>
              <span v-if="selectedEntry.timestamp" class="text-xs text-gray-600 tabular-nums">{{ formatLogTime(selectedEntry.timestamp) }}</span>
            </div>
            <p class="text-xs text-gray-500">{{ currentFile }}</p>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <button
              type="button"
              @click="copyEntry(selectedEntry)"
              class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition cursor-pointer"
            >
              <CopyIcon class="w-3.5 h-3.5 text-gray-500" />
              <span>Salin</span>
            </button>
            <button
              type="button"
              @click="selectedEntry = null"
              class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
              title="Tutup"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <div class="p-5 overflow-y-auto flex-1 space-y-4">
          <div class="space-y-1.5">
            <p class="text-xs font-semibold text-gray-700">Pesan</p>
            <div class="p-3.5 rounded-lg border border-gray-200 bg-gray-50 text-xs text-gray-900 leading-relaxed break-words whitespace-pre-wrap">
              {{ selectedEntry.message }}
            </div>
          </div>

          <div v-if="selectedEntry.context" class="space-y-1.5">
            <p class="text-xs font-semibold text-gray-700">Stack Trace / Detail</p>
            <pre class="p-3.5 rounded-lg border border-gray-200 bg-gray-50 text-[11px] font-mono text-gray-800 overflow-x-auto max-h-96 leading-relaxed whitespace-pre-wrap break-words">{{ selectedEntry.context }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import FilamentBadge from '@/components/ui/FilamentBadge.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import { confirmAction, notify } from '@/composables/useNotify';
import {
  ChevronDown as ChevronDownIcon,
  ChevronRight as ChevronRightIcon,
  Copy as CopyIcon,
  Download as DownloadIcon,
  Eye as EyeIcon,
  RefreshCw as RefreshCwIcon,
  Search as SearchIcon,
  Trash2 as Trash2Icon,
  X as XIcon,
} from 'lucide-vue-next';

const levelTabs = [
  { value: 'all', label: 'Semua', countKey: 'total' },
  { value: 'error', label: 'Error', countKey: 'error' },
  { value: 'warning', label: 'Warning', countKey: 'warning' },
  { value: 'info', label: 'Info', countKey: 'info' },
  { value: 'debug', label: 'Debug', countKey: 'debug' },
];

const files = ref([]);
const currentFile = ref('');
const fileSize = ref(0);
const readBytes = ref(0);
const truncated = ref(false);
const entries = ref([]);
const selectedEntry = ref(null);
const summary = reactive({ total: 0, error: 0, warning: 0, info: 0, debug: 0 });
const meta = reactive({ page: 1, per_page: 25, total: 0, last_page: 1 });
const filters = reactive({ file: '', level: 'all', search: '' });
const searchInput = ref('');

const isLoading = ref(false);
const isClearing = ref(false);
const isForbidden = ref(false);
const loadError = ref('');
let searchDebounceTimer = null;

const currentFileInfo = computed(() => files.value.find((f) => f.name === currentFile.value) || null);

const emptyStateText = computed(() => {
  if (files.value.length === 0) return 'Belum ada file log di storage/logs.';
  if (fileSize.value === 0) return 'File log masih kosong.';
  return 'Tidak ada catatan log yang cocok dengan filter.';
});

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

const formatBytes = (bytes) => {
  const num = Number(bytes || 0);
  if (num < 1024) return `${num} B`;
  if (num < 1024 * 1024) return `${(num / 1024).toFixed(1)} KB`;
  return `${(num / (1024 * 1024)).toFixed(2)} MB`;
};

const formatDateTime = (value) => {
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? String(value)
    : `${date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}, ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
};

const formatLogTime = (value) => {
  if (!value) return '-';
  const normalized = String(value).replace(' ', 'T');
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return value;
  return `${date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}, ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
};

const badgeColor = (category) => {
  if (category === 'error') return 'danger';
  if (category === 'warning') return 'warning';
  if (category === 'info') return 'info';
  return 'gray';
};

async function fetchLogs(page = 1) {
  isLoading.value = true;
  loadError.value = '';
  try {
    const res = await axios.get('/api/laravel-logs', {
      params: {
        file: filters.file || undefined,
        level: filters.level,
        search: filters.search || undefined,
        page,
        per_page: meta.per_page,
      },
    });
    const data = res.data;
    isForbidden.value = false;
    files.value = data.files || [];
    currentFile.value = data.current_file || '';
    if (!filters.file && currentFile.value) {
      filters.file = currentFile.value;
    }
    fileSize.value = data.file_size || 0;
    readBytes.value = data.read_bytes || 0;
    truncated.value = !!data.truncated;
    entries.value = data.entries || [];
    Object.assign(summary, data.summary || { total: 0, error: 0, warning: 0, info: 0, debug: 0 });
    Object.assign(meta, data.meta || { page: 1, per_page: meta.per_page, total: 0, last_page: 1 });
  } catch (err) {
    if (err.response?.status === 403) {
      isForbidden.value = true;
    } else {
      loadError.value = err.response?.data?.message || 'Gagal memuat Laravel Logs.';
    }
  } finally {
    isLoading.value = false;
  }
}

function selectLevel(level) {
  if (filters.level === level) return;
  filters.level = level;
  fetchLogs(1);
}

function onFileChange() {
  selectedEntry.value = null;
  fetchLogs(1);
}

function goToPage(page) {
  fetchLogs(page);
}

function onPerPageChange(perPage) {
  meta.per_page = perPage;
}

function openDetail(entry) {
  selectedEntry.value = entry;
}

async function copyEntry(entry) {
  const header = entry.timestamp ? `[${entry.timestamp}] ${entry.env}.${entry.level}: ` : '';
  const text = entry.context ? `${header}${entry.message}\n${entry.context}` : `${header}${entry.message}`;
  try {
    await navigator.clipboard.writeText(text);
    notify.success('Catatan log disalin ke clipboard.');
  } catch {
    notify.error('Gagal menyalin catatan log.');
  }
}

function downloadLog() {
  if (!currentFile.value) return;
  window.open(`/api/laravel-logs/download?file=${encodeURIComponent(currentFile.value)}`, '_blank');
}

async function clearLog() {
  if (!currentFile.value || isClearing.value) return;
  const confirmed = await confirmAction({
    title: 'Kosongkan file log?',
    message: `Seluruh isi ${currentFile.value} (${formatBytes(fileSize.value)}) akan dikosongkan. Tindakan ini tidak bisa dibatalkan.`,
    confirmText: 'Ya, Kosongkan',
  });
  if (!confirmed) return;

  isClearing.value = true;
  try {
    const res = await axios.delete('/api/laravel-logs', { params: { file: currentFile.value } });
    notify.success(res.data?.message || 'File log dikosongkan.');
    selectedEntry.value = null;
    await fetchLogs(1);
  } catch (err) {
    notify.error(err.response?.data?.message || 'Gagal mengosongkan file log.');
  } finally {
    isClearing.value = false;
  }
}

watch(searchInput, (value) => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    filters.search = value.trim();
    fetchLogs(1);
  }, 300);
});

onMounted(() => {
  fetchLogs(1);
});

onUnmounted(() => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});
</script>
