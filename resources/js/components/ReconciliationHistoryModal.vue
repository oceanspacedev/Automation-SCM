<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/40 backdrop-blur-xs font-sans"
        @click.self="close"
      >
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-4xl flex flex-col max-h-[90vh]" role="dialog" aria-modal="true">
          <!-- Header -->
          <div class="px-5 sm:px-6 pt-5 pb-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
            <div class="min-w-0">
              <h3 class="text-base font-semibold text-gray-950">Riwayat Pencocokan</h3>
              <p class="mt-0.5 text-xs text-gray-500">
                <template v-if="filters.data_program_id">
                  {{ filteredRowName || 'Baris ini' }} ·
                  <button type="button" @click="clearRowIdFilter" class="text-gray-700 underline hover:text-gray-950 cursor-pointer">Tampilkan semua</button>
                </template>
                <template v-else>Hasil pencocokan Data Program dengan pengajuan Form Program.</template>
              </p>
            </div>
            <button type="button" @click="close" class="p-1 -m-1 text-gray-400 hover:text-gray-700 cursor-pointer shrink-0" title="Tutup">
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Tab status & pencarian -->
          <div class="px-5 sm:px-6 py-3 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 shrink-0">
            <div class="inline-flex self-start items-center gap-1 p-1 rounded-lg border border-gray-200 bg-gray-50 text-xs">
              <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                @click="setTab(tab.key)"
                :class="[
                  'h-7 px-3 inline-flex items-center gap-1.5 rounded-md font-medium transition cursor-pointer',
                  activeTab === tab.key ? 'bg-white text-gray-900 shadow-2xs border border-gray-200' : 'text-gray-500 hover:text-gray-900'
                ]"
              >
                {{ tab.label }}
                <span class="text-gray-400 font-normal tabular-nums">{{ stats[tab.stat] || 0 }}</span>
              </button>
            </div>

            <div class="relative w-full sm:w-64">
              <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
              <input
                id="history-search"
                v-model="filters.search"
                @input="debounceFetch"
                type="text"
                placeholder="Cari dealer atau program"
                class="h-9 w-full pl-9 pr-3 text-xs bg-white border border-gray-300 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900"
              />
            </div>
          </div>

          <!-- Daftar riwayat -->
          <div class="flex-1 overflow-y-auto min-h-[240px] [scrollbar-width:thin]">
            <p v-if="loading" class="py-16 text-center text-xs text-gray-500">Memuat riwayat...</p>

            <div v-else-if="logs.length === 0" class="py-16 px-6 text-center">
              <p class="text-sm text-gray-700">Belum ada riwayat</p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ activeTab === 'matched' ? 'Belum ada data yang cocok.' : (activeTab === 'failed' ? 'Tidak ada data yang belum cocok.' : 'Riwayat muncul setelah Cocokkan Test Program dijalankan.') }}
              </p>
            </div>

            <template v-else>
              <!-- Judul kolom (layar lebar) -->
              <div :class="[rowGridClass, 'hidden md:grid py-2.5 bg-gray-50 border-b border-gray-100 text-[11px] font-medium text-gray-500 sticky top-0 z-10']">
                <span>Dealer & program</span>
                <span>Status</span>
                <span>Keterangan</span>
                <span class="text-right">Waktu</span>
              </div>

              <ul class="divide-y divide-gray-100">
                <li v-for="log in logs" :key="log.id" :class="[rowGridClass, 'py-3 text-xs']">
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ log.dealer_name || '-' }}</p>
                    <p class="mt-0.5 text-gray-500 truncate">
                      {{ log.program_name || '-' }}<template v-if="log.kode_bt"> · {{ log.kode_bt }}</template>
                    </p>
                  </div>

                  <p class="inline-flex items-center gap-1.5 font-medium" :class="log.status === 'MATCHED' ? 'text-emerald-700' : 'text-rose-700'">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="log.status === 'MATCHED' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    {{ log.status === 'MATCHED' ? 'Sesuai' : getFailLabel(log.status) }}
                  </p>

                  <div class="min-w-0 text-gray-700">
                    <p>{{ getCleanDescription(log) }}</p>
                    <p v-if="documentLinks(log).length" class="mt-1 flex items-center gap-2.5 text-gray-500">
                      <a
                        v-for="doc in documentLinks(log)"
                        :key="doc.label"
                        :href="doc.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-gray-700 underline hover:text-gray-950"
                      >{{ doc.label }}</a>
                    </p>
                  </div>

                  <p class="text-gray-500 md:text-right whitespace-nowrap tabular-nums">
                    <span class="text-gray-900">{{ formatDateDisplay(log.created_at) }}</span>
                    <span class="md:block md:mt-0.5"> {{ formatTimeDisplay(log.created_at) }}</span>
                  </p>
                </li>
              </ul>
            </template>
          </div>

          <!-- Halaman -->
          <div class="px-5 sm:px-6 py-3 border-t border-gray-100 flex items-center justify-between gap-3 text-xs shrink-0">
            <span class="text-gray-500">{{ pagination.total || 0 }} riwayat</span>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1 || loading"
                :class="pageButtonClass"
              >
                Sebelumnya
              </button>
              <span class="text-gray-600 tabular-nums">{{ pagination.current_page }} / {{ pagination.last_page || 1 }}</span>
              <button
                type="button"
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.last_page || loading"
                :class="pageButtonClass"
              >
                Selanjutnya
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import axios from 'axios';
import { Search as SearchIcon, X as XIcon } from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  initialDataProgramId: {
    type: [Number, String, null],
    default: null,
  },
  initialBatchId: {
    type: [String, null],
    default: null,
  },
  filteredRowName: {
    type: [String, null],
    default: null,
  },
});

const emit = defineEmits(['update:modelValue']);

const tabs = [
  { key: 'all', label: 'Semua', stat: 'total' },
  { key: 'matched', label: 'Sesuai', stat: 'matched' },
  { key: 'failed', label: 'Belum cocok', stat: 'failed' },
];

// Satu baris riwayat: bertumpuk di HP, empat kolom di layar lebar
const rowGridClass = 'px-5 sm:px-6 grid gap-1.5 md:grid-cols-[minmax(0,1.2fr)_130px_minmax(0,1.6fr)_96px] md:gap-4 md:items-start';
const pageButtonClass = 'h-8 px-3 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer';

const loading = ref(false);
const logs = ref([]);
const activeTab = ref('all'); // 'all' | 'matched' | 'failed'

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
});

const stats = reactive({
  total: 0,
  matched: 0,
  failed: 0,
});

const filters = reactive({
  search: '',
  data_program_id: null,
  batch_id: null,
});

let debounceTimer = null;
const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    pagination.current_page = 1;
    fetchLogs();
  }, 300);
};

const setTab = (tab) => {
  activeTab.value = tab;
  pagination.current_page = 1;
  fetchLogs();
};

const clearRowIdFilter = () => {
  filters.data_program_id = null;
  pagination.current_page = 1;
  fetchLogs();
};

const fetchLogs = async () => {
  loading.value = true;
  try {
    const params = {
      page: pagination.current_page,
      per_page: pagination.per_page,
      tab: activeTab.value,
    };

    if (filters.search) params.search = filters.search;
    if (filters.data_program_id) params.data_program_id = filters.data_program_id;
    if (filters.batch_id) params.batch_id = filters.batch_id;

    const res = await axios.get('/api/data-program/reconciliation-logs', { params });
    if (res.data.success) {
      logs.value = res.data.data || [];
      if (res.data.pagination) {
        pagination.current_page = res.data.pagination.current_page;
        pagination.last_page = res.data.pagination.last_page;
        pagination.total = res.data.pagination.total;
      }
      if (res.data.stats) {
        Object.assign(stats, res.data.stats);
      }
    }
  } catch (e) {
    console.error('Gagal mengambil riwayat rekonsiliasi:', e);
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page < 1 || page > pagination.last_page) return;
  pagination.current_page = page;
  fetchLogs();
};

const close = () => {
  emit('update:modelValue', false);
};

// Formatter Helpers
const formatRupiah = (val) => {
  if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
};

const formatDateDisplay = (val) => {
  if (!val) return '-';
  const d = new Date(val);
  return d.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const formatTimeDisplay = (val) => {
  if (!val) return '';
  const d = new Date(val);
  return d.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getFailLabel = (status) => {
  switch (status) {
    case 'DOC_INCOMPLETE':
      return 'Dokumen Kurang';
    case 'NOMINAL_MISMATCH':
      return 'Selisih Nominal';
    case 'NO_MATCH':
      return 'Belum ada form';
    case 'ERROR':
      return 'Error';
    default:
      return 'Gagal';
  }
};

const getCleanDescription = (log) => {
  if (log.status === 'MATCHED') {
    const nominal = log.dp_amount ? formatRupiah(log.dp_amount) : '';
    return nominal ? `Nominal ${nominal} sesuai, dokumen lengkap.` : 'Nominal dan dokumen sesuai.';
  }
  if (log.status === 'NOMINAL_MISMATCH') {
    return `Selisih ${formatRupiah(log.selisih)}: Data Program ${formatRupiah(log.dp_amount)}, Form Program ${formatRupiah(log.submission_amount)}.`;
  }
  if (log.status === 'DOC_INCOMPLETE') {
    const missing = log.missing_docs && log.missing_docs.length > 0 ? log.missing_docs.join(', ') : 'Dokumen';
    return `Nominal cocok, ${missing} belum diunggah.`;
  }
  if (log.status === 'NO_MATCH') {
    return 'Belum ada pengajuan Form Program yang cocok.';
  }
  return log.notes || 'Pencocokan belum berhasil';
};

// Link dokumen dari pengajuan yang dicocokkan (CN, Agreement, Faktur)
const documentLinks = (log) => {
  const sub = log.program_submission;
  if (!sub) return [];
  return [
    { label: 'CN', url: sub.credit_note_url },
    { label: 'Agreement', url: sub.agreement_url },
    { label: 'Faktur', url: sub.tax_invoice_url },
  ].filter((doc) => doc.url);
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      filters.data_program_id = props.initialDataProgramId || null;
      filters.batch_id = props.initialBatchId || null;
      filters.search = '';
      activeTab.value = 'all';
      pagination.current_page = 1;
      fetchLogs();
    }
  }
);
</script>
