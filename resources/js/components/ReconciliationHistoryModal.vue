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
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/40 backdrop-blur-xs font-sans overflow-y-auto"
        @click.self="close"
      >
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-4xl overflow-hidden flex flex-col max-h-[85vh] my-auto">
          <!-- Header Simple -->
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between shrink-0 bg-white">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center border border-gray-200 shrink-0">
                <HistoryIcon class="w-4 h-4" />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-bold text-gray-900">Riwayat Pencocokan Form Program</h3>
                  <button
                    v-if="filters.data_program_id"
                    type="button"
                    @click="clearRowIdFilter"
                    class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200 flex items-center gap-1 cursor-pointer transition"
                    title="Hapus filter baris ini"
                  >
                    <span>Filter: {{ filteredRowName || 'Baris Ini' }}</span>
                    <XIcon class="w-3 h-3 text-gray-500" />
                  </button>
                </div>
                <p class="text-[11px] text-gray-500 mt-0.5">
                  Daftar hasil pencocokan data program dengan form pengajuan dealer.
                </p>
              </div>
            </div>

            <button
              type="button"
              @click="close"
              class="w-7 h-7 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              title="Tutup"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Controls: Segmented Tabs (Semua / Sesuai / Gagal) & Search -->
          <div class="px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-gray-50/50 shrink-0">
            <!-- Simple Tab Pills -->
            <div class="inline-flex p-1 rounded-lg bg-gray-200/70 text-xs">
              <button
                type="button"
                @click="setTab('all')"
                :class="[
                  'px-3 py-1 rounded-md text-xs font-medium transition cursor-pointer',
                  activeTab === 'all'
                    ? 'bg-white text-gray-900 shadow-2xs font-semibold'
                    : 'text-gray-600 hover:text-gray-900'
                ]"
              >
                Semua
                <span class="ml-1 text-[11px] text-gray-400 font-normal">({{ stats.total || 0 }})</span>
              </button>

              <button
                type="button"
                @click="setTab('matched')"
                :class="[
                  'px-3 py-1 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5',
                  activeTab === 'matched'
                    ? 'bg-white text-emerald-800 shadow-2xs font-semibold'
                    : 'text-gray-600 hover:text-emerald-700'
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Sesuai</span>
                <span class="text-[11px] text-gray-400 font-normal">({{ stats.matched || 0 }})</span>
              </button>

              <button
                type="button"
                @click="setTab('failed')"
                :class="[
                  'px-3 py-1 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5',
                  activeTab === 'failed'
                    ? 'bg-white text-rose-800 shadow-2xs font-semibold'
                    : 'text-gray-600 hover:text-rose-700'
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                <span>Gagal / Belum Cocok</span>
                <span class="text-[11px] text-gray-400 font-normal">({{ stats.failed || 0 }})</span>
              </button>
            </div>

            <!-- Single Clean Search -->
            <div class="relative w-full sm:w-64">
              <SearchIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
              <input
                v-model="filters.search"
                @input="debounceFetch"
                type="text"
                placeholder="Cari dealer, program..."
                class="h-8 w-full pl-8 pr-3 text-xs bg-white border border-gray-200 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
              />
            </div>
          </div>

          <!-- Flat Clean List / Table -->
          <div class="flex-1 overflow-y-auto min-h-[250px]">
            <!-- Loading -->
            <div v-if="loading" class="py-16 text-center text-xs text-gray-400">
              <RefreshCwIcon class="w-5 h-5 animate-spin mx-auto text-gray-400 mb-2" />
              Memuat riwayat...
            </div>

            <!-- Empty -->
            <div v-else-if="logs.length === 0" class="py-16 text-center text-xs text-gray-500">
              <p class="font-medium text-gray-700">Tidak ada riwayat pencocokan</p>
              <p class="text-[11px] text-gray-400 mt-0.5">
                {{ activeTab === 'matched' ? 'Belum ada data yang berhasil cocok.' : (activeTab === 'failed' ? 'Tidak ada data yang gagal.' : 'Belum ada riwayat tercatat.') }}
              </p>
            </div>

            <!-- Clean Flat Table -->
            <table v-else class="w-full text-left text-xs border-collapse">
              <thead class="bg-gray-50/80 text-[11px] font-semibold text-gray-500 border-b border-gray-100 sticky top-0 z-10">
                <tr>
                  <th class="py-2.5 px-4">Dealer & Program</th>
                  <th class="py-2.5 px-3 w-[150px]">Status</th>
                  <th class="py-2.5 px-3">Keterangan</th>
                  <th class="py-2.5 px-4 w-[130px] text-right">Waktu</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr
                  v-for="log in logs"
                  :key="log.id"
                  class="hover:bg-gray-50/60 transition"
                >
                  <!-- Dealer & Program -->
                  <td class="py-3 px-4 align-top">
                    <div class="font-semibold text-gray-900 text-xs">
                      {{ log.dealer_name || '-' }}
                    </div>
                    <div class="text-[11px] text-gray-500 mt-0.5 flex items-center gap-1.5 flex-wrap">
                      <span>{{ log.program_name || '-' }}</span>
                      <span v-if="log.kode_bt" class="font-mono text-gray-400">&bull; {{ log.kode_bt }}</span>
                    </div>
                  </td>

                  <!-- Status -->
                  <td class="py-3 px-3 align-top whitespace-nowrap">
                    <span
                      :class="[
                        'inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-medium border',
                        log.status === 'MATCHED'
                          ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                          : 'bg-rose-50 text-rose-800 border-rose-200'
                      ]"
                    >
                      <span
                        class="w-1.5 h-1.5 rounded-full"
                        :class="log.status === 'MATCHED' ? 'bg-emerald-500' : 'bg-rose-500'"
                      ></span>
                      {{ log.status === 'MATCHED' ? 'Sesuai' : getFailLabel(log.status) }}
                    </span>
                  </td>

                  <!-- Keterangan & Dokumen -->
                  <td class="py-3 px-3 align-top text-xs text-gray-700">
                    <div>{{ getCleanDescription(log) }}</div>
                    <!-- Link Dokumen kecil jika ada -->
                    <div
                      v-if="log.program_submission && (log.program_submission.credit_note_url || log.program_submission.agreement_url || log.program_submission.tax_invoice_url)"
                      class="flex items-center gap-2 mt-1 text-[11px]"
                    >
                      <span class="text-gray-400 text-[10px]">Dokumen:</span>
                      <a
                        v-if="log.program_submission.credit_note_url"
                        :href="log.program_submission.credit_note_url"
                        target="_blank"
                        class="text-indigo-600 hover:underline flex items-center gap-0.5"
                      >
                        <ExternalLinkIcon class="w-2.5 h-2.5" /> CN
                      </a>
                      <a
                        v-if="log.program_submission.agreement_url"
                        :href="log.program_submission.agreement_url"
                        target="_blank"
                        class="text-indigo-600 hover:underline flex items-center gap-0.5"
                      >
                        <ExternalLinkIcon class="w-2.5 h-2.5" /> Agr
                      </a>
                      <a
                        v-if="log.program_submission.tax_invoice_url"
                        :href="log.program_submission.tax_invoice_url"
                        target="_blank"
                        class="text-indigo-600 hover:underline flex items-center gap-0.5"
                      >
                        <ExternalLinkIcon class="w-2.5 h-2.5" /> Faktur
                      </a>
                    </div>
                  </td>

                  <!-- Waktu -->
                  <td class="py-3 px-4 align-top text-right whitespace-nowrap text-[11px] text-gray-500">
                    <div class="text-gray-800 font-medium">{{ formatDateDisplay(log.created_at) }}</div>
                    <div class="text-gray-400 text-[10px] mt-0.5">{{ formatTimeDisplay(log.created_at) }}</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Simple Pagination Footer -->
          <div class="px-5 py-2.5 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between text-xs shrink-0">
            <span class="text-gray-500 text-[11px]">
              Total {{ pagination.total || 0 }} data
            </span>

            <div class="flex items-center gap-1">
              <button
                type="button"
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1 || loading"
                class="h-7 px-2.5 rounded border border-gray-200 bg-white text-[11px] text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
              >
                Sebelumnya
              </button>
              <span class="text-[11px] px-2 text-gray-600 font-medium">
                {{ pagination.current_page }} / {{ pagination.last_page || 1 }}
              </span>
              <button
                type="button"
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.last_page || loading"
                class="h-7 px-2.5 rounded border border-gray-200 bg-white text-[11px] text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
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
import {
  History as HistoryIcon,
  Search as SearchIcon,
  RefreshCw as RefreshCwIcon,
  X as XIcon,
  ExternalLink as ExternalLinkIcon,
} from 'lucide-vue-next';

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
      return 'Form Belum Ada';
    case 'ERROR':
      return 'Error';
    default:
      return 'Gagal';
  }
};

const getCleanDescription = (log) => {
  if (log.status === 'MATCHED') {
    const nominal = log.dp_amount ? formatRupiah(log.dp_amount) : '';
    return nominal ? `Nominal ${nominal} sesuai • Dokumen lengkap (Siap Potong)` : 'Finansial & Dokumen sesuai';
  }
  if (log.status === 'NOMINAL_MISMATCH') {
    return `Selisih: ${formatRupiah(log.selisih)} (Data: ${formatRupiah(log.dp_amount)} vs Form: ${formatRupiah(log.submission_amount)})`;
  }
  if (log.status === 'DOC_INCOMPLETE') {
    const missing = log.missing_docs && log.missing_docs.length > 0 ? log.missing_docs.join(', ') : 'Dokumen';
    return `Finansial cocok, tetapi ${missing} belum diupload`;
  }
  if (log.status === 'NO_MATCH') {
    return 'Belum ada form pengajuan dari dealer yang cocok';
  }
  return log.notes || 'Pencocokan belum berhasil';
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
