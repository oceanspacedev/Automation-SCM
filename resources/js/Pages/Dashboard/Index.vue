<template>
  <div class="space-y-4 font-sans">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500">
      <span>Dashboard</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800 font-medium">Overview</span>
    </div>

    <!-- Header Page (Filament style) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Dashboard Ringkasan</h1>
        <p class="text-xs text-gray-500 mt-0.5">Ringkasan status metrik operasional draft dan invoice.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchMetrics"
          :disabled="loading"
          class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition cursor-pointer shadow-2xs flex items-center gap-1.5 disabled:opacity-50"
        >
          <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', loading && 'animate-spin']" />
          <span>Refresh Data</span>
        </button>
      </div>
    </div>

    <div v-if="loading" class="py-8 text-center text-xs text-gray-500">
      Memuat data dashboard...
    </div>

    <div v-else class="space-y-6">
      <!-- Filament Metric Card Table -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-white">
          <span class="text-xs font-semibold text-gray-950">Metrik Draft & Invoice</span>
          <span class="text-[11px] text-gray-400">Ringkasan Otomatis</span>
        </div>
        <table class="min-w-full text-xs divide-y divide-gray-200">
          <tbody class="divide-y divide-gray-100 bg-white">
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600 w-1/2 sm:w-1/3">Total Draft Excel</td>
              <td class="px-4 py-3 font-semibold text-gray-950">{{ metrics.total_draft ?? 0 }} baris</td>
            </tr>
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600">Draft Ready (Siap Dibuat Invoice)</td>
              <td class="px-4 py-3">
                <FilamentBadge status="ready">{{ metrics.draft_ready ?? 0 }} Ready</FilamentBadge>
              </td>
            </tr>
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600">Draft Error (Perlu Perbaikan)</td>
              <td class="px-4 py-3">
                <FilamentBadge status="error">{{ metrics.draft_error ?? 0 }} Error</FilamentBadge>
              </td>
            </tr>
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600">Total Invoice Terbit</td>
              <td class="px-4 py-3 font-semibold text-gray-950">{{ metrics.total_invoice ?? 0 }} invoice</td>
            </tr>
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600">Invoice Tipe DSA</td>
              <td class="px-4 py-3 text-gray-800">{{ metrics.invoice_dsa ?? 0 }}</td>
            </tr>
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600">Invoice Tipe NPS FL</td>
              <td class="px-4 py-3 text-gray-800">{{ metrics.invoice_nps_fl ?? 0 }}</td>
            </tr>
            <tr class="hover:bg-gray-50/60 transition">
              <td class="px-4 py-3 font-normal text-gray-600">Invoice Tipe REGULAR</td>
              <td class="px-4 py-3 text-gray-800">{{ metrics.invoice_regular ?? 0 }}</td>
            </tr>
            <tr class="bg-gray-50/70 font-semibold border-t border-gray-200">
              <td class="px-4 py-3.5 text-gray-950">Total Netpay Invoice</td>
              <td class="px-4 py-3.5 text-gray-950 text-sm font-bold">
                Rp {{ formatCurrency(metrics.total_netpay ?? 0) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Navigasi Cepat (Filament style) -->
      <div class="flex flex-wrap items-center gap-3 pt-1">
        <router-link
          to="/drafts"
          class="h-9 px-4 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition inline-flex items-center gap-1.5 shadow-2xs"
        >
          <span>Lihat Daftar Draft & Import</span>
          <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
        </router-link>
        <router-link
          to="/invoices"
          class="h-9 px-4 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition inline-flex items-center gap-1.5 shadow-2xs"
        >
          <span>Lihat Daftar Invoice</span>
          <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { ChevronRight as ChevronRightIcon, RefreshCw as RefreshCwIcon } from 'lucide-vue-next';
import FilamentBadge from '@/components/ui/FilamentBadge.vue';

const metrics = ref({});
const loading = ref(true);

const fetchMetrics = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/dashboard');
    metrics.value = res.data;
  } catch (err) {
    console.error('Error fetching dashboard metrics', err);
  } finally {
    loading.value = false;
  }
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID').format(val || 0);
};

onMounted(() => {
  fetchMetrics();
});
</script>
