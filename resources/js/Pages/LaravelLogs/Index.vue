<template>
  <div class="space-y-4 font-sans">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Pengaturan</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800 dark:text-slate-200">Laravel Logs</span>
    </div>

    <!-- Header & Page Actions -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
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
          <span>{{ isLoading ? 'Memuat...' : 'Refresh' }}</span>
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

    <!-- Main Card -->
    <div v-else class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="px-4 sm:px-5 py-3 border-b border-gray-100 dark:border-slate-800 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Segmented Level Filter (gaya sama seperti Schedules) -->
        <div class="inline-flex flex-wrap items-center gap-1 p-1 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/60 self-start">
          <button
            v-for="tab in levelTabs"
            :key="tab.value"
            type="button"
            @click="selectLevel(tab.value)"
            :class="[
              'h-7 px-2.5 rounded-md text-xs transition inline-flex items-center gap-1.5 cursor-pointer whitespace-nowrap',
              filters.level === tab.value
                ? 'bg-white dark:bg-slate-900 text-gray-950 dark:text-white font-semibold shadow-2xs border border-gray-200 dark:border-slate-700'
                : 'border border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200 font-medium'
            ]"
          >
            <span v-if="tab.dot" :class="['w-1.5 h-1.5 rounded-full shrink-0', tab.dot]"></span>
            <span>{{ tab.label }}</span>
            <span
              class="text-[11px] tabular-nums"
              :class="filters.level === tab.value ? 'text-gray-500 dark:text-slate-400' : 'text-gray-400 dark:text-slate-500'"
            >
              {{ formatNumber(summary[tab.countKey] ?? 0) }}
            </span>
          </button>
        </div>

        <!-- File selector & Search -->
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
          <div v-if="files.length > 0" class="relative sm:w-52">
            <select
              v-model="filters.file"
              @change="onFileChange"
              aria-label="Pilih file log"
              class="h-9 w-full pl-3 pr-8 text-xs text-gray-800 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-gray-950 appearance-none cursor-pointer transition shadow-2xs"
            >
              <option v-for="file in files" :key="file.name" :value="file.name">
                {{ file.name }} ({{ formatBytes(file.size) }})
              </option>
            </select>
            <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <div class="relative flex-1 sm:w-64">
            <SearchIcon class="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="searchInput"
              type="text"
              placeholder="Cari pesan, file, stack trace..."
              class="h-9 w-full pl-8 pr-7 text-xs text-gray-900 bg-white border border-gray-300 rounded-lg placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 transition shadow-2xs"
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

      <!-- Info bar tipis saat error atau file dipotong -->
      <div
        v-if="loadError"
        role="alert"
        class="px-4 sm:px-5 py-2.5 border-b border-gray-100 dark:border-slate-800 bg-rose-50/60 text-xs text-rose-700"
      >
        {{ loadError }}
      </div>
      <div
        v-else-if="truncated"
        class="px-4 sm:px-5 py-2 border-b border-gray-100 dark:border-slate-800 bg-gray-50/60 dark:bg-slate-800/40 text-[11px] text-gray-500 dark:text-slate-400 flex items-center justify-between gap-2"
      >
        <span>Menampilkan {{ formatBytes(readBytes) }} terbaru dari {{ formatBytes(fileSize) }}.</span>
        <span v-if="currentFileInfo" class="hidden sm:inline text-gray-400 dark:text-slate-500 tabular-nums shrink-0">
          Diperbarui {{ formatDateTime(currentFileInfo.modified_at) }}
        </span>
      </div>

      <!-- States -->
      <div v-if="isLoading && entries.length === 0" class="py-12 text-center text-xs text-gray-500">
        Memuat catatan log...
      </div>
      <div v-else-if="entries.length === 0" class="py-12 px-4 text-center text-xs text-gray-500">
        {{ emptyStateText }}
      </div>

      <!-- Clean Structured Log List (konsisten dengan Riwayat Approval) -->
      <ul v-else class="divide-y divide-gray-100 dark:divide-slate-800">
        <li
          v-for="parsed in parsedEntries"
          :key="parsed.raw.id"
          @click="openDetail(parsed.raw)"
          class="px-4 sm:px-5 py-3 hover:bg-gray-50/70 dark:hover:bg-slate-800/40 transition cursor-pointer"
        >
          <div class="flex flex-col md:flex-row md:items-start gap-2 md:gap-5">
            <!-- Kiri: Level + Waktu + Env -->
            <div class="flex md:flex-col items-center md:items-start justify-between gap-2 md:gap-1 md:w-44 md:shrink-0 pt-0.5">
              <div class="flex items-center gap-2 min-w-0">
                <span :class="['inline-flex items-center gap-1.5 text-[11px] font-semibold', levelTextClass(parsed.raw.category)]">
                  <span :class="['w-1.5 h-1.5 rounded-full shrink-0', levelDotClass(parsed.raw.category)]"></span>
                  <span>{{ levelLabel(parsed.raw.level) }}</span>
                </span>
                <span
                  v-if="parsed.raw.env"
                  class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400"
                >
                  {{ parsed.raw.env }}
                </span>
              </div>

              <span class="text-[11px] text-gray-500 dark:text-slate-400 tabular-nums whitespace-nowrap">
                {{ formatLogTime(parsed.raw.timestamp) }}
              </span>
            </div>

            <!-- Tengah: Pesan utama yang sudah dibersihkan & ringkasan exception/detail -->
            <div class="min-w-0 flex-1 space-y-1">
              <p class="text-xs text-gray-900 dark:text-slate-100 leading-relaxed line-clamp-2 break-words">
                {{ parsed.headline }}
              </p>

              <div
                v-if="parsed.exceptionShort || parsed.locationShort || parsed.frames.length || parsed.jsonPretty"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-gray-500 dark:text-slate-400"
              >
                <span
                  v-if="parsed.exceptionShort"
                  class="font-medium text-rose-700 dark:text-rose-400"
                >
                  {{ parsed.exceptionShort }}
                </span>
                <span v-if="parsed.exceptionShort && parsed.locationShort" class="text-gray-300 dark:text-slate-700">·</span>
                <span v-if="parsed.locationShort" class="truncate max-w-sm">
                  {{ parsed.locationShort }}
                </span>
                <span
                  v-if="(parsed.exceptionShort || parsed.locationShort) && (parsed.frames.length || parsed.jsonPretty)"
                  class="text-gray-300 dark:text-slate-700"
                >·</span>
                <span v-if="parsed.frames.length" class="text-gray-400 dark:text-slate-500">
                  Stack trace ({{ parsed.frames.length }} frame)
                </span>
                <span v-else-if="parsed.jsonPretty" class="text-gray-400 dark:text-slate-500">
                  Data JSON
                </span>
              </div>
            </div>

            <!-- Kanan: Aksi ringkas (desktop), mobile cukup tap baris -->
            <div class="hidden md:flex items-center gap-1.5 shrink-0 pt-0.5" @click.stop>
              <button
                type="button"
                @click="copyEntry(parsed.raw)"
                class="h-7 px-2.5 inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 text-[11px] font-medium text-gray-600 dark:text-slate-300 transition cursor-pointer"
                title="Salin isi log"
              >
                <CheckIcon v-if="copiedId === parsed.raw.id" class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                <CopyIcon v-else class="w-3 h-3 text-gray-400 dark:text-slate-400" />
                <span>{{ copiedId === parsed.raw.id ? 'Tersalin' : 'Salin' }}</span>
              </button>
              <button
                type="button"
                @click="openDetail(parsed.raw)"
                class="h-7 px-2.5 inline-flex items-center rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 text-[11px] font-medium text-gray-700 dark:text-slate-200 transition cursor-pointer"
              >
                Detail
              </button>
            </div>
          </div>
        </li>
      </ul>

      <!-- Pagination -->
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
      <div class="bg-white dark:bg-slate-900 rounded-xl max-w-3xl w-full shadow-2xl border border-gray-200 dark:border-slate-800 max-h-[88vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-800 flex items-start justify-between gap-3 shrink-0">
          <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <span :class="['inline-flex items-center gap-1.5 text-xs font-semibold', levelTextClass(selectedEntry.category)]">
                <span :class="['w-2 h-2 rounded-full', levelDotClass(selectedEntry.category)]"></span>
                <span>{{ levelLabel(selectedEntry.level) }}</span>
              </span>
              <span
                v-if="selectedEntry.env"
                class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300"
              >
                {{ selectedEntry.env }}
              </span>
              <span v-if="selectedEntry.timestamp" class="text-gray-300 dark:text-slate-700">·</span>
              <span v-if="selectedEntry.timestamp" class="text-xs text-gray-500 dark:text-slate-400 tabular-nums">
                {{ formatLogTime(selectedEntry.timestamp) }}
              </span>
            </div>
            <p class="text-[11px] text-gray-400 dark:text-slate-500">{{ currentFile }}</p>
          </div>

          <div class="flex items-center gap-1.5 shrink-0">
            <button
              type="button"
              @click="copyEntry(selectedEntry)"
              class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition cursor-pointer"
            >
              <CheckIcon v-if="copiedId === selectedEntry.id" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
              <CopyIcon v-else class="w-3.5 h-3.5 text-gray-500 dark:text-slate-400" />
              <span>{{ copiedId === selectedEntry.id ? 'Tersalin' : 'Salin' }}</span>
            </button>
            <button
              type="button"
              @click="selectedEntry = null"
              class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition cursor-pointer"
              title="Tutup"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <div v-if="selectedParsed" class="p-5 overflow-y-auto flex-1 space-y-4">
          <!-- Pesan Utama -->
          <div class="space-y-1.5">
            <p class="text-xs font-semibold text-gray-700 dark:text-slate-300">Pesan</p>
            <div class="p-3.5 rounded-lg border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/60 text-xs text-gray-900 dark:text-slate-100 leading-relaxed break-words whitespace-pre-wrap">
              {{ selectedParsed.headline }}
            </div>
          </div>

          <!-- Ringkasan Exception (jika ada) -->
          <div
            v-if="selectedParsed.exceptionClass || selectedParsed.exceptionFile"
            class="rounded-lg border border-gray-200 dark:border-slate-800 divide-y divide-gray-100 dark:divide-slate-800 text-xs"
          >
            <div v-if="selectedParsed.exceptionClass" class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
              <span class="text-gray-500 dark:text-slate-400 shrink-0">Exception</span>
              <span class="font-medium text-gray-900 dark:text-slate-100 break-all">
                {{ selectedParsed.exceptionClass }}
                <span v-if="selectedParsed.exceptionCode" class="text-gray-400 dark:text-slate-500 font-normal">(code {{ selectedParsed.exceptionCode }})</span>
              </span>
            </div>
            <div v-if="selectedParsed.exceptionFile" class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
              <span class="text-gray-500 dark:text-slate-400 shrink-0">Sumber</span>
              <span class="font-mono text-[11px] text-gray-800 dark:text-slate-200 break-all">{{ selectedParsed.exceptionFile }}</span>
            </div>
            <div v-if="selectedParsed.firstAppLocation" class="px-3.5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
              <span class="text-gray-500 dark:text-slate-400 shrink-0">Lokasi Kode</span>
              <span class="font-mono text-[11px] font-medium text-gray-900 dark:text-white break-all">{{ selectedParsed.firstAppLocation }}</span>
            </div>
          </div>

          <!-- Payload JSON terformat (jika pesan mengandung JSON) -->
          <div v-if="selectedParsed.jsonPretty" class="space-y-1.5">
            <p class="text-xs font-semibold text-gray-700 dark:text-slate-300">Data</p>
            <pre class="p-3.5 rounded-lg border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/60 text-[11px] font-mono text-gray-800 dark:text-slate-200 overflow-x-auto max-h-60 leading-relaxed whitespace-pre-wrap break-words">{{ selectedParsed.jsonPretty }}</pre>
          </div>

          <!-- Stack Trace / Detail Tambahan -->
          <div v-if="selectedParsed.frames.length || selectedEntry.context" class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <p class="text-xs font-semibold text-gray-700 dark:text-slate-300">
                {{ selectedParsed.frames.length ? 'Stack Trace' : 'Detail Tambahan' }}
              </p>

              <!-- Switcher: Ringkas App / Semua / Mentah -->
              <div
                v-if="selectedParsed.frames.length"
                class="inline-flex items-center gap-1 p-0.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/60 text-[11px]"
              >
                <button
                  v-if="selectedParsed.appFrames.length"
                  type="button"
                  @click="traceMode = 'app'"
                  :class="[
                    'h-6 px-2 rounded-md transition cursor-pointer',
                    traceMode === 'app'
                      ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white font-semibold shadow-2xs border border-gray-200 dark:border-slate-700'
                      : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200'
                  ]"
                >
                  Aplikasi ({{ selectedParsed.appFrames.length }})
                </button>
                <button
                  type="button"
                  @click="traceMode = 'all'"
                  :class="[
                    'h-6 px-2 rounded-md transition cursor-pointer',
                    traceMode === 'all'
                      ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white font-semibold shadow-2xs border border-gray-200 dark:border-slate-700'
                      : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200'
                  ]"
                >
                  Semua ({{ selectedParsed.frames.length }})
                </button>
                <button
                  type="button"
                  @click="traceMode = 'raw'"
                  :class="[
                    'h-6 px-2 rounded-md transition cursor-pointer',
                    traceMode === 'raw'
                      ? 'bg-white dark:bg-slate-900 text-gray-900 dark:text-white font-semibold shadow-2xs border border-gray-200 dark:border-slate-700'
                      : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200'
                  ]"
                >
                  Mentah
                </button>
              </div>
            </div>

            <!-- Daftar Frame Terstruktur -->
            <div
              v-if="selectedParsed.frames.length && traceMode !== 'raw'"
              class="rounded-lg border border-gray-200 dark:border-slate-800 divide-y divide-gray-100 dark:divide-slate-800 max-h-80 overflow-y-auto"
            >
              <div
                v-for="frame in visibleFrames"
                :key="frame.index"
                :class="[
                  'px-3.5 py-2 text-[11px] flex items-start gap-3',
                  frame.isApp
                    ? 'bg-white dark:bg-slate-900'
                    : 'bg-gray-50/50 dark:bg-slate-950/30 text-gray-500 dark:text-slate-500'
                ]"
              >
                <span class="font-mono text-[10px] text-gray-400 dark:text-slate-600 tabular-nums w-6 shrink-0 pt-0.5">
                  #{{ frame.index }}
                </span>
                <div class="min-w-0 flex-1 space-y-0.5">
                  <p :class="['font-mono break-all', frame.isApp ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-600 dark:text-slate-400']">
                    {{ frame.location }}
                  </p>
                  <p v-if="frame.call" class="font-mono text-[10px] text-gray-500 dark:text-slate-500 break-all">
                    {{ frame.call }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Teks Mentah -->
            <pre
              v-else
              class="p-3.5 rounded-lg border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/60 text-[11px] font-mono text-gray-800 dark:text-slate-200 overflow-x-auto max-h-80 leading-relaxed whitespace-pre-wrap break-words"
            >{{ selectedParsed.cleanContext || selectedEntry.context }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import { confirmAction, notify } from '@/composables/useNotify';
import {
  Check as CheckIcon,
  ChevronDown as ChevronDownIcon,
  ChevronRight as ChevronRightIcon,
  Copy as CopyIcon,
  Download as DownloadIcon,
  RefreshCw as RefreshCwIcon,
  Search as SearchIcon,
  Trash2 as Trash2Icon,
  X as XIcon,
} from 'lucide-vue-next';

const levelTabs = [
  { value: 'all', label: 'Semua', countKey: 'total', dot: '' },
  { value: 'error', label: 'Error', countKey: 'error', dot: 'bg-rose-500' },
  { value: 'warning', label: 'Warning', countKey: 'warning', dot: 'bg-amber-500' },
  { value: 'info', label: 'Info', countKey: 'info', dot: 'bg-sky-500' },
  { value: 'debug', label: 'Debug', countKey: 'debug', dot: 'bg-gray-400' },
];

const files = ref([]);
const currentFile = ref('');
const fileSize = ref(0);
const readBytes = ref(0);
const truncated = ref(false);
const entries = ref([]);
const selectedEntry = ref(null);
const copiedId = ref(null);
const traceMode = ref('all');
const summary = reactive({ total: 0, error: 0, warning: 0, info: 0, debug: 0 });
const meta = reactive({ page: 1, per_page: 25, total: 0, last_page: 1 });
const filters = reactive({ file: '', level: 'all', search: '' });
const searchInput = ref('');

const isLoading = ref(false);
const isClearing = ref(false);
const isForbidden = ref(false);
const loadError = ref('');
let searchDebounceTimer = null;
let copiedTimer = null;

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

const levelDotClass = (category) => ({
  error: 'bg-rose-500',
  warning: 'bg-amber-500',
  info: 'bg-sky-500',
}[category] || 'bg-gray-400');

const levelTextClass = (category) => ({
  error: 'text-rose-700 dark:text-rose-400',
  warning: 'text-amber-700 dark:text-amber-400',
  info: 'text-sky-700 dark:text-sky-400',
}[category] || 'text-gray-600 dark:text-slate-400');

const levelLabel = (level) => {
  const value = String(level || 'INFO').toUpperCase();
  return value.charAt(0) + value.slice(1).toLowerCase();
};

const normalizePath = (value) => String(value || '')
  .replace(/\\\//g, '/')
  .replace(/\\\\/g, '/')
  .replace(/^.*?\/(?:Invoice|Automation-SCM)\//i, '')
  .replace(/^\/var\/www\/(?:html\/)?/i, '');

const shortLocation = (path) => {
  if (!path) return '';
  const clean = normalizePath(path);
  const parts = clean.split('/');
  return parts.length > 2 ? parts.slice(-2).join('/') : clean;
};

const parseFrames = (context) => {
  if (!context) return [];
  const cleanText = String(context)
    .replace(/\\\//g, '/')
    .replace(/\\\\/g, '/')
    .replace(/"?\s*\}?\s*$/, '');
  const frames = [];

  for (const rawLine of cleanText.split(/\r\n|\r|\n/)) {
    const line = rawLine.trim();
    if (!line || line === '[stacktrace]') continue;

    const match = line.match(/^#(\d+)\s+(.+?)\((\d+)\):\s*(.*)$/);
    if (match) {
      const file = normalizePath(match[2]);
      const location = `${file}:${match[3]}`;
      const isApp = !file.includes('vendor/') && !file.startsWith('artisan');
      frames.push({ index: Number(match[1]), location, call: match[4], isApp, raw: line });
      continue;
    }

    const mainMatch = line.match(/^#(\d+)\s+\{main\}$/);
    if (mainMatch) {
      frames.push({ index: Number(mainMatch[1]), location: '{main}', call: '', isApp: false, raw: line });
    }
  }

  return frames;
};

const parseEntry = (entry) => {
  const rawMessage = String(entry.message || '').trim();
  let headline = rawMessage;
  let exceptionClass = '';
  let exceptionCode = '';
  let exceptionFile = '';
  let jsonPretty = '';

  const exIdx = rawMessage.indexOf('{"exception":"[object]');
  if (exIdx > 0) {
    headline = rawMessage.slice(0, exIdx).trim();
    const exBody = rawMessage.slice(exIdx).replace(/\\\//g, '/').replace(/\\\\/g, '\\');
    const exMatch = exBody.match(/\(([A-Za-z0-9_\\]+?)(?:\(code:\s*([^)]+)\))?:\s*[\s\S]*?\s+at\s+([^"\s)]+:\d+)/);
    if (exMatch) {
      exceptionClass = exMatch[1];
      exceptionCode = exMatch[2] || '';
      exceptionFile = normalizePath(exMatch[3]);
    }
  } else {
    const jsonMatch = rawMessage.match(/^(.*?)(\{[\s\S]*\}|\[[\s\S]*\])$/);
    if (jsonMatch && jsonMatch[1].trim()) {
      try {
        const parsedJson = JSON.parse(jsonMatch[2]);
        headline = jsonMatch[1].replace(/[:\s]+$/, '').trim();
        jsonPretty = JSON.stringify(parsedJson, null, 2);
      } catch {
        // Biarkan pesan asli bila bukan JSON valid
      }
    }
  }

  const frames = parseFrames(entry.context);
  const appFrames = frames.filter((f) => f.isApp);
  const firstAppLocation = appFrames[0]?.location || '';
  const exceptionShort = exceptionClass ? exceptionClass.split('\\').pop() : '';
  const locationShort = shortLocation(firstAppLocation || exceptionFile);
  const cleanContext = entry.context ? String(entry.context).replace(/\\\//g, '/') : '';

  return {
    raw: entry,
    headline: headline || rawMessage || '-',
    exceptionClass,
    exceptionShort,
    exceptionCode,
    exceptionFile,
    firstAppLocation,
    locationShort,
    jsonPretty,
    frames,
    appFrames,
    cleanContext,
  };
};

const parsedEntries = computed(() => entries.value.map(parseEntry));
const selectedParsed = computed(() => (selectedEntry.value ? parseEntry(selectedEntry.value) : null));
const visibleFrames = computed(() => {
  if (!selectedParsed.value) return [];
  return traceMode.value === 'app' && selectedParsed.value.appFrames.length
    ? selectedParsed.value.appFrames
    : selectedParsed.value.frames;
});

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
  fetchLogs(1);
}

function openDetail(entry) {
  selectedEntry.value = entry;
  const parsed = parseEntry(entry);
  traceMode.value = parsed.appFrames.length ? 'app' : 'all';
}

async function copyEntry(entry) {
  const header = entry.timestamp ? `[${entry.timestamp}] ${entry.env}.${entry.level}: ` : '';
  const text = entry.context ? `${header}${entry.message}\n${entry.context}` : `${header}${entry.message}`;
  try {
    await navigator.clipboard.writeText(text);
    copiedId.value = entry.id;
    if (copiedTimer) clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
      copiedId.value = null;
    }, 1800);
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
  if (copiedTimer) clearTimeout(copiedTimer);
});
</script>
