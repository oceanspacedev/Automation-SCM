<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Riwayat</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">Riwayat Approval</span>
    </div>

    <AreaTabs />

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Riwayat Approval</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Alur potong klaim tiap baris Data Program: WA ke telemarketing, persetujuan dealer, WA ke AR, lalu dipotong AR.
        </p>
      </div>
      <button
        type="button"
        @click="fetchRows(pagination.current_page)"
        :disabled="loading"
        class="h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer disabled:opacity-50 whitespace-nowrap self-start sm:self-auto"
      >
        <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', loading && 'animate-spin text-gray-900']" />
        <span>{{ loading ? 'Memuat...' : 'Refresh' }}</span>
      </button>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs text-gray-500">{{ formatCount(pagination.total) }} baris</p>

        <!-- Filter tahap & pencarian; di layar kecil cari di atas, tahap di bawahnya -->
        <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2">
          <div class="relative sm:w-60">
            <select
              v-model="stage"
              @change="fetchRows(1)"
              aria-label="Tahap approval"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option v-for="option in stageOptions" :key="option.value" :value="option.value">
                {{ option.label }} ({{ formatCount(counts[option.value]) }})
              </option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <div class="relative w-full sm:w-64">
            <SearchIcon class="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="search"
              @input="debounceFetch"
              type="text"
              placeholder="Cari dealer, kode BT, program"
              class="h-9 w-full pl-8 pr-3 text-xs text-gray-900 bg-white border border-gray-300 rounded-lg placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 transition shadow-2xs"
            />
          </div>
        </div>
      </div>

      <p v-if="errorMessage" class="px-5 py-3 text-xs text-rose-700 border-b border-gray-100">{{ errorMessage }}</p>
      <p v-if="loading && rows.length === 0" class="py-12 text-center text-xs text-gray-500">Memuat riwayat approval...</p>
      <p v-else-if="rows.length === 0" class="py-12 px-4 text-center text-xs text-gray-500">
        Belum ada baris di tahap ini. Baris masuk ke alur setelah statusnya <span class="font-medium text-gray-700">BISA DI POTONG</span>.
      </p>

      <ul v-else class="divide-y divide-gray-100">
        <li v-for="row in rows" :key="row.id" class="px-4 sm:px-5 py-3.5">
          <!-- Layar lebar: toko di kiri, alur dengan lebar terbatas, lalu Detail tepat di sebelahnya -->
          <div class="flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-6">
            <div class="flex items-start justify-between gap-3 lg:w-60 xl:w-72 lg:shrink-0">
              <!-- Teks tampil utuh: program boleh turun baris, nominal di sebelah status -->
              <div class="min-w-0">
                <p class="text-xs font-semibold text-gray-900 break-words">{{ row.dealer_name || '-' }}</p>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 break-words">{{ rowProgram(row) }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span :class="['inline-flex items-center h-[18px] px-1.5 rounded-full text-[10px] font-medium ring-1 ring-inset', stageBadgeClass(row.stage)]">
                    {{ row.stage_label }}
                  </span>
                  <span class="text-[11px] font-medium text-gray-700 tabular-nums whitespace-nowrap">{{ formatRupiah(row.net_pay) }}</span>
                </div>
              </div>
              <button type="button" @click="openDetail(row)" :class="[detailButtonClass, 'lg:hidden']">Detail</button>
            </div>

            <!-- Alur 5 langkah (layar sedang & lebar) -->
            <ol class="hidden md:grid grid-cols-5 w-full max-w-3xl xl:max-w-4xl lg:flex-1">
              <li v-for="(step, index) in row.steps" :key="step.key" class="relative flex flex-col items-center text-center px-1.5 min-w-0">
                <!-- Garis dari langkah sebelumnya: hijau penuh bila sudah lewat, hijau berjalan menuju langkah sekarang -->
                <span v-if="index > 0" :class="['absolute top-2 right-1/2 w-full h-0.5 rounded-full overflow-hidden', connectorClass(step.state)]">
                  <span v-if="step.state === 'current'" class="flow-runner"></span>
                </span>
                <StepDot :state="step.state" class="relative z-10 ring-4 ring-white" />
                <p :class="['mt-1.5 text-[10px] font-medium leading-[14px]', stepLabelClass(step.state)]">{{ step.label }}</p>
                <!-- Tinggi baris tetap walau kosong, supaya kelima langkah sejajar; nama panjang turun baris, tidak dipotong -->
                <p :class="['w-full min-h-[14px] text-[10px] leading-[14px] break-words', step.state === 'waiting' ? 'text-gray-400' : 'text-gray-600']">
                  {{ displayName(step.person) }}
                </p>
                <p class="min-h-[14px] text-[10px] leading-[14px] text-gray-400 tabular-nums">{{ stepTime(step) }}</p>
              </li>
            </ol>

            <!-- Alur 5 langkah (layar kecil) -->
            <ol class="md:hidden">
              <li v-for="(step, index) in row.steps" :key="step.key" class="relative flex items-start gap-2 pb-2.5 last:pb-0">
                <span
                  v-if="index < row.steps.length - 1"
                  :class="['absolute left-[7px] top-4 bottom-0 w-0.5 rounded-full overflow-hidden', connectorClass(row.steps[index + 1].state)]"
                >
                  <span v-if="row.steps[index + 1].state === 'current'" class="flow-runner-y"></span>
                </span>
                <StepDot :state="step.state" small class="relative z-10 shrink-0" />
                <div class="min-w-0 flex-1 flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p :class="['text-[11px] font-medium leading-4', stepLabelClass(step.state)]">{{ step.label }}</p>
                    <p v-if="step.person" class="text-[10px] leading-[14px] text-gray-500 break-words">{{ displayName(step.person) }}</p>
                  </div>
                  <span class="text-[10px] leading-4 text-gray-400 tabular-nums whitespace-nowrap">{{ stepTime(step) }}</span>
                </div>
              </li>
            </ol>

            <!-- Tepat setelah alur, tidak didorong ke ujung kanan -->
            <button type="button" @click="openDetail(row)" :class="[detailButtonClass, 'hidden lg:inline-flex']">Detail</button>
          </div>
        </li>
      </ul>

      <FilamentPagination
        :total="pagination.total"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        @page-change="fetchRows"
        @per-page-change="(p) => { pagination.per_page = p; fetchRows(1); }"
      />
    </div>

    <!-- Detail: langkah lengkap dan semua catatan -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" @click.self="detail = null">
      <div class="bg-white rounded-xl max-w-md w-full shadow-xl border border-gray-200 max-h-[90vh] flex flex-col text-[11px]">
        <div class="px-4 py-3.5 border-b border-gray-100 flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <h3 class="text-xs font-semibold text-gray-950">{{ detail.dealer_name || '-' }}</h3>
              <span :class="['inline-flex items-center h-[18px] px-1.5 rounded-full text-[10px] font-medium ring-1 ring-inset', stageBadgeClass(detail.stage)]">
                {{ detail.stage_label }}
              </span>
            </div>
            <p class="text-gray-500 mt-0.5">{{ rowMeta(detail) }}</p>
            <p v-if="detail.periode" class="text-gray-500">{{ detail.periode }}</p>
          </div>
          <button type="button" @click="detail = null" class="p-1 -m-1 text-gray-400 hover:text-gray-600 cursor-pointer" title="Tutup">
            <XIcon class="w-3.5 h-3.5" />
          </button>
        </div>

        <div class="px-4 py-3.5 space-y-4 overflow-y-auto">
          <!-- Alur vertikal -->
          <ol>
            <li v-for="(step, index) in detail.steps" :key="step.key" class="relative flex gap-2.5 pb-3.5 last:pb-0">
              <span
                v-if="index < detail.steps.length - 1"
                :class="['absolute left-2 top-5 bottom-0 w-0.5 rounded-full overflow-hidden', connectorClass(detail.steps[index + 1].state)]"
              >
                <span v-if="detail.steps[index + 1].state === 'current'" class="flow-runner-y"></span>
              </span>
              <StepDot :state="step.state" class="relative z-10 shrink-0" />
              <div class="min-w-0 pt-px space-y-px">
                <p class="flex flex-wrap items-center gap-x-2">
                  <span :class="['font-medium', stepLabelClass(step.state)]">{{ step.label }}</span>
                  <span class="text-gray-400">{{ stepStateLabel(step) }}</span>
                </p>
                <p v-if="step.person" class="text-gray-700">
                  <span v-if="step.role" class="text-gray-400">{{ step.role }} · </span>{{ displayName(step.person) }}
                </p>
                <p v-if="step.at || step.date" class="text-gray-500 tabular-nums">{{ stepTime(step, true) }}</p>
                <p v-if="step.actor" class="text-gray-500">Oleh {{ step.actor }}<template v-if="step.recipient"> · ke {{ step.recipient }}</template></p>
                <p v-if="step.note && step.key !== 'ready'" class="text-gray-500 break-words">{{ step.note }}</p>
              </div>
            </li>
          </ol>

          <div class="space-y-2 border-t border-gray-100">
            <p class="pt-3 font-medium text-gray-900">Semua catatan</p>
            <p v-if="detailLoading" class="text-gray-500">Memuat...</p>
            <p v-else-if="!detail.timeline?.length" class="text-gray-500">
              Belum ada catatan. Langkah sebelum Riwayat Approval dipasang tidak tercatat waktunya; statusnya diambil dari Data Program.
            </p>
            <ul v-else class="rounded-lg border border-gray-200 divide-y divide-gray-100">
              <li v-for="item in detail.timeline" :key="item.id" class="px-3 py-2 space-y-px">
                <div class="flex items-start justify-between gap-3">
                  <span :class="['font-medium', item.success ? 'text-gray-900' : 'text-rose-700']">{{ item.label }}{{ item.success ? '' : ' · gagal' }}</span>
                  <span class="text-[10px] text-gray-400 tabular-nums whitespace-nowrap">{{ formatDateTime(item.at) }}</span>
                </div>
                <p v-if="item.actor || item.recipient" class="text-gray-500 break-words">
                  <template v-if="item.actor">Oleh {{ item.actor }}</template><template v-if="item.actor && item.recipient"> · </template><template v-if="item.recipient">ke {{ item.recipient }}</template>
                </p>
                <p v-if="item.note" class="text-gray-500 break-words">{{ item.note }}</p>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h } from 'vue';
import axios from 'axios';
import AreaTabs from '@/components/AreaTabs.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import {
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  RefreshCw as RefreshCwIcon,
  Search as SearchIcon,
  Check as CheckIcon,
  X as XIcon,
  Pause as PauseIcon,
} from 'lucide-vue-next';

// Bulatan langkah: hijau selesai, kuning posisi sekarang, merah WA gagal, oranye ditunda, abu-abu belum
const StepDot = (props) => {
  const size = props.small ? 'w-4 h-4' : 'w-[18px] h-[18px]';
  const icon = props.small ? 'w-2 h-2' : 'w-2.5 h-2.5';
  const styles = {
    done: 'bg-emerald-500 text-white',
    failed: 'bg-rose-500 text-white',
    postponed: 'bg-amber-500 text-white',
    current: 'bg-white border-2 border-amber-400',
    waiting: 'bg-white border border-gray-300',
  };
  const inner = {
    done: () => h(CheckIcon, { class: icon, 'stroke-width': 3 }),
    failed: () => h(XIcon, { class: icon, 'stroke-width': 3 }),
    postponed: () => h(PauseIcon, { class: icon, 'stroke-width': 3 }),
    current: () => h('span', { class: 'w-1 h-1 rounded-full bg-amber-500' }),
  }[props.state];
  // Posisi sekarang berdenyut pelan, seperti titik pengantaran yang sedang berjalan
  const pulse = props.state === 'current' ? [h('span', { class: 'absolute inset-0 rounded-full bg-amber-400/40 animate-ping motion-reduce:hidden' })] : [];

  return h(
    'span',
    { class: ['relative rounded-full flex items-center justify-center', size, styles[props.state] || styles.waiting] },
    [...pulse, ...(inner ? [inner()] : [])]
  );
};
StepDot.props = { state: { type: String, default: 'waiting' }, small: Boolean };

const rows = ref([]);
const counts = ref({});
const stages = ref({});
const stage = ref('all');
const search = ref('');
const loading = ref(false);
const errorMessage = ref('');
const detail = ref(null);
const detailLoading = ref(false);
const pagination = reactive({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
let debounceTimer = null;

const stageOptions = computed(() => [
  { value: 'all', label: 'Semua tahap' },
  ...Object.entries(stages.value).map(([value, label]) => ({ value, label })),
]);

const formatCount = (value) => Number(value || 0).toLocaleString('id-ID');
const formatRupiah = (value) => (value === null || value === undefined || value === '' ? '-' : `Rp ${new Intl.NumberFormat('id-ID').format(Number(value))}`);
const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-');

const rowProgram = (row) => [row.kode_bt, row.program_name || row.program].filter(Boolean).join(' · ') || '-';
const rowMeta = (row) => [rowProgram(row), formatRupiah(row.net_pay)].join(' · ');

// Nama dari data tertulis kapital semua; ditampilkan "Akhmad Zulfikar" supaya mudah dibaca
const displayName = (name) => {
  if (!name) return '';
  if (/[a-z]/.test(name)) return name;
  return name.toLowerCase().replace(/(^|[\s.'-])(\p{L})/gu, (match, separator, letter) => separator + letter.toUpperCase());
};

// Waktu langkah: jam tercatat, atau tanggal potong dari Data Program untuk baris lama
const stepTime = (step, long = false) => {
  if (step.at) {
    return long
      ? formatDateTime(step.at)
      : new Date(step.at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  }
  return step.date || '';
};

const STEP_STATE_LABELS = {
  done: 'Selesai',
  current: 'Menunggu',
  waiting: 'Belum',
  failed: 'Gagal terkirim',
  postponed: 'Ditunda',
};
const stepStateLabel = (step) => {
  if (step.state === 'done' && !step.at && !step.date && step.key !== 'ready') return 'Selesai · waktu tidak tercatat';
  return STEP_STATE_LABELS[step.state] || step.state;
};

const detailButtonClass = 'h-7 px-2.5 shrink-0 items-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-[11px] font-medium text-gray-700 cursor-pointer';

// Garis menuju sebuah langkah: hijau penuh bila sudah lewat, jalur hijau muda (dengan kilatan berjalan) menuju posisi sekarang
const connectorClass = (state) => ({
  done: 'bg-emerald-500',
  current: 'bg-emerald-100',
  failed: 'bg-rose-300',
  postponed: 'bg-amber-300',
}[state] || 'bg-gray-200');

const stepLabelClass = (state) => ({
  done: 'text-gray-900',
  current: 'text-amber-700',
  failed: 'text-rose-700',
  postponed: 'text-amber-700',
}[state] || 'text-gray-400');

const stageBadgeClass = (value) => ({
  waiting_telemarketing: 'bg-gray-50 text-gray-600 ring-gray-200',
  waiting_approval: 'bg-amber-50 text-amber-700 ring-amber-200',
  postponed: 'bg-orange-50 text-orange-700 ring-orange-200',
  approved: 'bg-sky-50 text-sky-700 ring-sky-200',
  cut: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
}[value] || 'bg-gray-50 text-gray-600 ring-gray-200');

async function fetchRows(page = 1) {
  loading.value = true;
  errorMessage.value = '';
  try {
    const params = { page, per_page: pagination.per_page, stage: stage.value };
    if (search.value.trim()) params.search = search.value.trim();
    const res = await axios.get('/api/data-program/approval-history', { params });
    rows.value = res.data.data || [];
    counts.value = res.data.counts || {};
    stages.value = res.data.stages || {};
    Object.assign(pagination, res.data.pagination || {});
  } catch (err) {
    errorMessage.value = 'Gagal memuat riwayat approval: ' + (err.response?.data?.message || err.message);
  } finally {
    loading.value = false;
  }
}

function debounceFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchRows(1), 350);
}

async function openDetail(row) {
  detail.value = { ...row, timeline: [] };
  detailLoading.value = true;
  try {
    const res = await axios.get(`/api/data-program/${row.id}/approval-history`);
    detail.value = res.data.data;
  } catch {
    // Detail tetap tampil dari data baris; hanya daftar catatan yang kosong
  } finally {
    detailLoading.value = false;
  }
}

onMounted(() => fetchRows(1));
</script>

<style scoped>
/* Kilatan hijau yang berjalan di garis menuju langkah sekarang, seperti pelacak pengantaran */
.flow-runner,
.flow-runner-y {
  position: absolute;
  border-radius: 9999px;
}

.flow-runner {
  top: 0;
  bottom: 0;
  left: 0;
  width: 45%;
  background: linear-gradient(90deg, rgb(16 185 129 / 0), rgb(16 185 129) 70%);
  animation: flow-run-x 1.4s ease-in-out infinite;
}

.flow-runner-y {
  left: 0;
  right: 0;
  top: 0;
  height: 45%;
  background: linear-gradient(180deg, rgb(16 185 129 / 0), rgb(16 185 129) 70%);
  animation: flow-run-y 1.4s ease-in-out infinite;
}

@keyframes flow-run-x {
  from { transform: translateX(-100%); }
  to { transform: translateX(225%); }
}

@keyframes flow-run-y {
  from { transform: translateY(-100%); }
  to { transform: translateY(225%); }
}

/* Tanpa animasi bila pengguna memintanya: garis diisi diam setengah */
@media (prefers-reduced-motion: reduce) {
  .flow-runner,
  .flow-runner-y {
    animation: none;
  }
}
</style>
