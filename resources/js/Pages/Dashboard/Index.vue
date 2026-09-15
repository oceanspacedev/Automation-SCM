<template>
  <div>
    <div class="mb-6 flex justify-between items-center">
      <h1 class="text-xl font-bold tracking-tight">Dashboard Ringkasan</h1>
      <button
        @click="fetchMetrics"
        class="px-3 py-1.5 border border-gray-300 text-xs font-medium hover:bg-gray-50 rounded"
      >
        Refresh Data
      </button>
    </div>

    <div v-if="loading" class="py-8 text-center text-sm text-gray-500">
      Memuat data dashboard...
    </div>

    <div v-else class="space-y-6">
      <!-- Tabel Ringkasan Metrik Sederhana -->
      <div class="border border-gray-200 rounded overflow-hidden">
        <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200 text-xs font-semibold text-gray-700 uppercase tracking-wider">
          Metrik Draft & Invoice
        </div>
        <table class="min-w-full text-sm divide-y divide-gray-200">
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600 w-1/3">Total Draft Excel</td>
              <td class="px-4 py-3 font-bold text-gray-900">{{ metrics.total_draft ?? 0 }} baris</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600">Draft Ready (Siap Dibuat Invoice)</td>
              <td class="px-4 py-3 text-green-700 font-semibold">{{ metrics.draft_ready ?? 0 }}</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600">Draft Error (Perlu Perbaikan)</td>
              <td class="px-4 py-3 text-red-700 font-semibold">{{ metrics.draft_error ?? 0 }}</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600">Total Invoice Terbit</td>
              <td class="px-4 py-3 font-bold text-gray-900">{{ metrics.total_invoice ?? 0 }}</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600">Invoice Tipe DSA</td>
              <td class="px-4 py-3 text-gray-800">{{ metrics.invoice_dsa ?? 0 }}</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600">Invoice Tipe NPS FL</td>
              <td class="px-4 py-3 text-gray-800">{{ metrics.invoice_nps_fl ?? 0 }}</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-medium text-gray-600">Invoice Tipe REGULAR</td>
              <td class="px-4 py-3 text-gray-800">{{ metrics.invoice_regular ?? 0 }}</td>
            </tr>
            <tr class="bg-gray-50 font-semibold">
              <td class="px-4 py-3 text-gray-900">Total Netpay Invoice</td>
              <td class="px-4 py-3 text-black text-base">
                Rp {{ formatCurrency(metrics.total_netpay ?? 0) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Navigasi Cepat -->
      <div class="flex space-x-3 pt-2">
        <router-link
          to="/drafts"
          class="px-4 py-2 border border-gray-300 text-sm font-medium hover:bg-gray-50 rounded"
        >
          Lihat Daftar Draft & Import →
        </router-link>
        <router-link
          to="/invoices"
          class="px-4 py-2 border border-gray-300 text-sm font-medium hover:bg-gray-50 rounded"
        >
          Lihat Daftar Invoice →
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

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
