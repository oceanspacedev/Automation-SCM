<template>
  <div class="dashboard-viz space-y-4 font-sans">
    <!-- Header & filter area (berlaku untuk semua kartu dan grafik di bawahnya) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Dashboard</h1>
        <p class="text-xs text-gray-500 mt-0.5">Data Program, Form Program, dan status potong per region.</p>
      </div>

      <div class="flex items-center gap-2">
        <div class="inline-flex flex-1 sm:flex-none rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs">
          <button
            v-for="option in AREA_OPTIONS"
            :key="option.value"
            type="button"
            @click="selectArea(option.value)"
            :class="[
              'h-8 flex-1 sm:flex-none px-3 rounded-md font-medium transition cursor-pointer whitespace-nowrap',
              selectedArea === option.value ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800',
            ]"
          >
            {{ option.label }}
          </button>
        </div>
        <button
          @click="fetchMetrics()"
          :disabled="loading"
          class="h-9 w-9 shrink-0 border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 rounded-lg transition cursor-pointer shadow-2xs flex items-center justify-center disabled:opacity-50"
          title="Muat ulang"
        >
          <RefreshCwIcon :class="['w-3.5 h-3.5', loading && 'animate-spin']" />
        </button>
      </div>
    </div>

    <div v-if="loading && !metrics.program" class="py-8 text-center text-xs text-gray-500">Memuat data...</div>

    <!-- Saat memuat ulang, tampilan lama tetap terlihat (redup) supaya tidak berkedip -->
    <div v-else class="viz space-y-4 transition-opacity" :class="loading && 'opacity-60'">
      <!-- Kartu ringkasan -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div
          v-for="(card, index) in cards"
          :key="card.label"
          :class="['rounded-xl border border-gray-200 bg-white shadow-xs p-4', index === 0 && 'col-span-2 lg:col-span-1']"
        >
          <div class="flex items-center gap-1.5 text-xs text-gray-500">
            <span v-if="card.color" class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: card.color }"></span>
            <span>{{ card.label }}</span>
          </div>
          <p class="mt-1.5 text-2xl font-semibold tracking-tight text-gray-950">{{ formatNumber(card.value) }}</p>
          <p v-if="card.share !== undefined" class="mt-0.5 text-xs text-gray-400">{{ card.share }}% dari Data Program</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <!-- Grafik status potong per region -->
        <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white shadow-xs">
          <div class="px-4 pt-4 flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <div>
              <h2 class="text-sm font-semibold text-gray-950">Status potong per region</h2>
              <p class="text-xs text-gray-500 mt-0.5">Jumlah baris Data Program.</p>
            </div>
            <div class="flex items-center gap-3">
              <ul class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
                <li v-for="status in STATUSES" :key="status.key" class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: status.color }"></span>
                  {{ status.label }}
                </li>
              </ul>
              <button
                type="button"
                @click="showTable = !showTable"
                class="h-7 px-2.5 rounded-md border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 cursor-pointer"
              >
                {{ showTable ? 'Grafik' : 'Tabel' }}
              </button>
            </div>
          </div>

          <Transition name="chart-swap" mode="out-in">
            <p v-if="rows.length === 0" key="empty" class="px-4 py-10 text-center text-xs text-gray-500">Belum ada data.</p>

            <!-- Tabel (pengganti grafik untuk membaca semua angka) -->
            <div v-else-if="showTable" key="table" class="mt-3 overflow-x-auto border-t border-gray-100">
              <table class="w-full text-left text-xs text-gray-900">
                <thead>
                  <tr class="bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
                    <th class="py-2 px-4">Region</th>
                    <th class="py-2 px-3 text-right">Data Program</th>
                    <th class="py-2 px-3 text-right">Form Program</th>
                    <th v-for="status in STATUSES" :key="status.key" class="py-2 px-3 text-right">{{ status.label }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                  <tr v-for="row in rows" :key="row.key">
                    <td class="py-2 px-4 whitespace-nowrap font-medium text-gray-950">
                      {{ row.region }}<span v-if="row.areaShort" class="ml-1.5 font-normal text-gray-400">{{ row.areaShort }}</span>
                    </td>
                    <td class="py-2 px-3 text-right">{{ row.data_program }}</td>
                    <td class="py-2 px-3 text-right">{{ row.form_program }}</td>
                    <td v-for="status in STATUSES" :key="status.key" class="py-2 px-3 text-right">{{ row[status.key] }}</td>
                  </tr>
                  <tr class="bg-gray-50/70 font-semibold text-gray-950">
                    <td class="py-2 px-4">Total</td>
                    <td class="py-2 px-3 text-right">{{ totals.data_program }}</td>
                    <td class="py-2 px-3 text-right">{{ totals.form_program }}</td>
                    <td v-for="status in STATUSES" :key="status.key" class="py-2 px-3 text-right">{{ totals[status.key] }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Bar bertumpuk: satu baris per region, panjang = jumlah Data Program -->
            <TransitionGroup v-else key="chart" tag="ul" name="bar-row" class="bar-chart relative px-4 pt-4 pb-4 space-y-2.5">
              <li v-for="(row, rowIndex) in rows" :key="row.key" class="flex items-center gap-3" :style="{ '--bar-delay': barDelay(rowIndex) }">
                <span class="w-36 sm:w-44 shrink-0 truncate text-xs text-gray-600" :title="row.region">
                  {{ row.region }}<span v-if="row.areaShort" class="ml-1 text-gray-400">{{ row.areaShort }}</span>
                </span>
                <div class="flex-1 min-w-0 flex items-center gap-2">
                  <div class="bar flex h-3 gap-[2px]" :style="{ width: `${barWidth(row.data_program, maxDataProgram)}%` }">
                    <span
                      v-for="(segment, index) in segmentsOf(row)"
                      :key="segment.key"
                      tabindex="0"
                      class="bar-mark h-full outline-none focus-visible:ring-2 focus-visible:ring-gray-900/30 cursor-default"
                      :class="index === segmentsOf(row).length - 1 && 'rounded-r'"
                      :style="{ flex: `${segment.value} 1 0`, background: segment.color }"
                      @mouseenter="showTip($event, row, segment)"
                      @mousemove="moveTip($event)"
                      @mouseleave="hideTip"
                      @focus="showTip($event, row, segment)"
                      @blur="hideTip"
                    ></span>
                  </div>
                  <span class="bar-value shrink-0 text-xs text-gray-700 tabular-nums">{{ row.data_program }}</span>
                </div>
              </li>
            </TransitionGroup>
          </Transition>
        </div>

        <!-- Grafik Form Program per region (urutan region sama dengan grafik di sebelahnya) -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-xs">
          <div class="px-4 pt-4">
            <h2 class="text-sm font-semibold text-gray-950">Form Program per region</h2>
            <p class="text-xs text-gray-500 mt-0.5">Jumlah pengajuan yang masuk.</p>
          </div>

          <Transition name="chart-swap" mode="out-in">
            <p v-if="rows.length === 0 || totals.form_program === 0" key="empty" class="px-4 py-10 text-center text-xs text-gray-500">Belum ada Form Program.</p>
            <TransitionGroup v-else key="chart" tag="ul" name="bar-row" class="bar-chart relative px-4 pt-4 pb-4 space-y-2.5">
              <li v-for="(row, rowIndex) in rows" :key="row.key" class="flex items-center gap-3" :style="{ '--bar-delay': barDelay(rowIndex) }">
                <span class="w-28 sm:w-32 shrink-0 truncate text-xs text-gray-600" :title="row.region">{{ row.region }}</span>
                <div class="flex-1 min-w-0 flex items-center gap-2">
                  <span
                    v-if="row.form_program > 0"
                    tabindex="0"
                    class="bar bar-mark h-3 rounded-r outline-none focus-visible:ring-2 focus-visible:ring-gray-900/30 cursor-default"
                    :style="{ width: `${barWidth(row.form_program, maxFormProgram)}%`, background: FORM_PROGRAM_COLOR }"
                    @mouseenter="showTip($event, row, { label: 'Form Program', value: row.form_program, color: FORM_PROGRAM_COLOR })"
                    @mousemove="moveTip($event)"
                    @mouseleave="hideTip"
                    @focus="showTip($event, row, { label: 'Form Program', value: row.form_program, color: FORM_PROGRAM_COLOR })"
                    @blur="hideTip"
                  ></span>
                  <span class="bar-value shrink-0 text-xs text-gray-700 tabular-nums">{{ row.form_program }}</span>
                </div>
              </li>
            </TransitionGroup>
          </Transition>
        </div>
      </div>

      <!-- Draft & Invoice -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="item in invoiceItems" :key="item.label" class="rounded-xl border border-gray-200 bg-white shadow-xs p-4">
          <p class="text-xs text-gray-500">{{ item.label }}</p>
          <p class="mt-1 text-base font-semibold text-gray-950">{{ item.value }}</p>
        </div>
      </div>
    </div>

    <!-- Tooltip grafik: angka dulu, lalu keterangannya -->
    <Transition name="tip">
      <div
        v-if="tip"
        class="fixed z-50 pointer-events-none rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-lg text-xs"
        :style="{ left: `${tip.x + 12}px`, top: `${tip.y + 12}px` }"
      >
        <p class="text-gray-500">{{ tip.region }}</p>
        <p class="mt-1 flex items-center gap-2">
          <span class="w-3 h-0.5 rounded-full" :style="{ background: tip.color }"></span>
          <span class="font-semibold text-gray-950">{{ tip.value }}</span>
          <span class="text-gray-600">{{ tip.label }}</span>
        </p>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { RefreshCw as RefreshCwIcon } from 'lucide-vue-next';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { AREAS } from '@/composables/useArea';

const AREA_OPTIONS = [
  { value: '', label: 'Semua area' },
  { value: 'jabar', label: 'Jawa Barat' },
  { value: 'jateng', label: 'Jawa Tengah' },
];

// Tahap potong berurutan, jadi satu warna biru: makin maju makin kontras dengan latar (warna di <style>)
const STATUSES = [
  { key: 'sudah_potong', label: 'Sudah dipotong', color: 'var(--stage-sudah)' },
  { key: 'bisa_potong', label: 'Bisa dipotong', color: 'var(--stage-bisa)' },
  { key: 'belum_potong', label: 'Belum bisa dipotong', color: 'var(--stage-belum)' },
];
const FORM_PROGRAM_COLOR = 'var(--series-1)';

const metrics = ref({});
const loading = ref(true);
const selectedArea = ref('');
const showTable = ref(false);
const tip = ref(null);

const totals = computed(() => metrics.value.program?.totals || {});

// Region dengan Data Program terbanyak di atas; area hanya disebut saat semua area ditampilkan
const rows = computed(() => (metrics.value.program?.regions || [])
  .map((row) => ({
    ...row,
    key: `${row.area}-${row.region}`,
    areaShort: selectedArea.value ? '' : (AREAS.find((area) => area.label === row.area)?.short || ''),
  }))
  .sort((a, b) => b.data_program - a.data_program || b.form_program - a.form_program || a.region.localeCompare(b.region)));

const maxDataProgram = computed(() => Math.max(1, ...rows.value.map((row) => row.data_program)));
const maxFormProgram = computed(() => Math.max(1, ...rows.value.map((row) => row.form_program)));

const barWidth = (value, max) => (value > 0 ? Math.max(2, (value / max) * 100) : 0);
// Bar muncul berurutan dari atas; jeda dibatasi supaya baris bawah tidak menunggu lama
const barDelay = (rowIndex) => `${Math.min(rowIndex, 10) * 40}ms`;
const segmentsOf = (row) => STATUSES.filter((status) => row[status.key] > 0).map((status) => ({ ...status, value: row[status.key] }));

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
const share = (value) => (totals.value.data_program ? Math.round((value / totals.value.data_program) * 100) : 0);

const cards = computed(() => [
  { label: 'Data Program', value: totals.value.data_program },
  { label: 'Form Program', value: totals.value.form_program },
  ...STATUSES.map((status) => ({ label: status.label, value: totals.value[status.key], color: status.color, share: share(totals.value[status.key]) })),
]);

const invoiceItems = computed(() => [
  { label: 'Draft', value: formatNumber(metrics.value.total_draft) },
  { label: 'Invoice terbit', value: formatNumber(metrics.value.total_invoice) },
  { label: 'Invoice DSA · NPS FL · REGULAR', value: [metrics.value.invoice_dsa, metrics.value.invoice_nps_fl, metrics.value.invoice_regular].map(formatNumber).join(' · ') },
  { label: 'Total Net Pay', value: `Rp ${formatNumber(metrics.value.total_netpay)}` },
]);

const showTip = (event, row, segment) => {
  const rect = event.target.getBoundingClientRect();
  tip.value = {
    region: row.areaShort ? `${row.region} · ${row.areaShort}` : row.region,
    label: segment.label,
    value: formatNumber(segment.value),
    color: segment.color,
    x: event.clientX || rect.right,
    y: event.clientY || rect.top,
  };
};

const moveTip = (event) => {
  if (tip.value) tip.value = { ...tip.value, x: event.clientX, y: event.clientY };
};

const hideTip = () => {
  tip.value = null;
};

const fetchMetrics = async ({ silent = false } = {}) => {
  if (!silent) loading.value = true;
  try {
    const res = await axios.get('/api/dashboard', { params: selectedArea.value ? { area: selectedArea.value } : {} });
    metrics.value = res.data;
  } catch (err) {
    console.error('Error fetching dashboard metrics', err);
  } finally {
    if (!silent) loading.value = false;
  }
};

const selectArea = (value) => {
  if (selectedArea.value === value) return;
  selectedArea.value = value;
  fetchMetrics();
};

useAutoRefresh(() => fetchMetrics({ silent: true }), {
  isPaused: () => loading.value || tip.value !== null,
});

onMounted(() => {
  fetchMetrics();
});
</script>

<style scoped>
/* Biru bertingkat untuk tahap potong, sudah dicek kontrasnya terhadap kartu terang (#ffffff) dan gelap (#131D2F) */
.dashboard-viz {
  --stage-sudah: #104281;
  --stage-bisa: #2a78d6;
  --stage-belum: #86b6ef;
  --series-1: #2a78d6;
  --ease-smooth: cubic-bezier(0.22, 1, 0.36, 1);
}

/* Mode gelap: tahap yang lebih maju lebih terang supaya tetap paling menonjol */
.dashboard-viz:is(.dark *) {
  --stage-sudah: #9ec5f4;
  --stage-bisa: #3987e5;
  --stage-belum: #184f95;
  --series-1: #3987e5;
}

/* Bar tumbuh dari kiri saat muncul (angkanya ikut di ujung bar), lalu bergeser halus saat datanya berubah */
.bar {
  animation: bar-grow 800ms var(--ease-smooth) var(--bar-delay, 0ms) backwards;
}

.bar,
.bar-mark {
  transition: width 600ms var(--ease-smooth), flex-grow 600ms var(--ease-smooth), opacity 200ms ease;
}

.bar-value {
  animation: bar-value-in 500ms ease var(--bar-delay, 0ms) backwards;
}

/* Arahkan kursor ke satu bagian bar: bagian lain meredup */
.bar-chart:has(.bar-mark:hover, .bar-mark:focus-visible) .bar-mark:not(:hover, :focus-visible) {
  opacity: 0.35;
}

@keyframes bar-grow {
  from {
    width: 0;
  }
}

@keyframes bar-value-in {
  from {
    opacity: 0;
  }
}

/* Urutan region berubah (ganti area / data baru): baris bergeser ke posisi barunya */
.bar-row-move {
  transition: transform 500ms var(--ease-smooth);
}

.bar-row-enter-active {
  transition: opacity 300ms ease;
}

.bar-row-enter-from,
.bar-row-leave-to {
  opacity: 0;
}

/* Baris yang hilang keluar dari alur supaya baris lain langsung bergeser ke tempatnya */
.bar-row-leave-active {
  position: absolute;
  inset-inline: 1rem;
  transition: opacity 200ms ease;
}

.chart-swap-enter-active,
.chart-swap-leave-active {
  transition: opacity 150ms ease;
}

.chart-swap-enter-from,
.chart-swap-leave-to {
  opacity: 0;
}

.tip-enter-active,
.tip-leave-active {
  transition: opacity 120ms ease, transform 120ms ease;
}

.tip-enter-from,
.tip-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (prefers-reduced-motion: reduce) {
  .bar,
  .bar-mark,
  .bar-value,
  .bar-row-move,
  .bar-row-enter-active,
  .bar-row-leave-active,
  .chart-swap-enter-active,
  .chart-swap-leave-active,
  .tip-enter-active,
  .tip-leave-active {
    animation: none;
    transition: none;
  }
}
</style>
