<template>
  <div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex items-center justify-between pb-4 border-b border-gray-200">
      <div class="flex items-center space-x-3">
        <router-link to="/drafts" class="text-sm font-medium text-gray-500 hover:text-black">
          ← Kembali ke Draft
        </router-link>
        <span class="text-gray-300">|</span>
        <h1 class="text-xl font-bold tracking-tight text-gray-900">Detail Draft #{{ id }}</h1>
        <span class="text-sm font-medium capitalize text-gray-700">
          ({{ draft.status }})
        </span>
      </div>

      <div class="flex items-center space-x-2">
        <button
          v-if="draft.status !== 'invoiced'"
          @click="validateDraft"
          :disabled="validating"
          class="h-9 px-3.5 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md transition cursor-pointer"
        >
          {{ validating ? 'Memvalidasi...' : 'Validasi Ulang Rumus' }}
        </button>

        <button
          v-if="draft.status === 'ready'"
          @click="generateInvoice"
          :disabled="generating"
          class="h-9 px-4 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 transition cursor-pointer shadow-xs"
        >
          {{ generating ? 'Membuat Invoice...' : 'Generate Invoice' }}
        </button>

        <router-link
          v-if="draft.invoice"
          :to="`/invoices/${draft.invoice.id}`"
          class="h-9 px-4 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition inline-flex items-center shadow-xs"
        >
          Lihat Invoice ({{ draft.invoice.invoice_number }}) →
        </router-link>
      </div>
    </div>

    <!-- Alert -->
    <Alert v-if="alertMessage" :variant="alertSuccess ? 'default' : 'destructive'">
      <CheckCircleIcon v-if="alertSuccess" class="h-4 w-4" />
      <AlertCircleIcon v-else class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ alertMessage }}</span>
        <button @click="alertMessage = null" class="ml-4 text-sm opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
      </AlertDescription>
    </Alert>

    <div v-if="loading" class="py-8 text-center text-sm text-gray-500">
      Memuat data detail draft...
    </div>

    <div v-else class="space-y-6">
      <!-- Info Grid (3 Columns) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- 1. Dealer Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-4">
          <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-3">
            Dealer Information
          </h2>
          <table class="w-full text-sm">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-32">Dealer Code</td>
                <td class="py-1 font-medium text-gray-900">{{ draft.dealer_code || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Dealer Name</td>
                <td class="py-1 text-gray-900">{{ draft.dealer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Customer</td>
                <td class="py-1 text-gray-900">{{ draft.customer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Region / RSM</td>
                <td class="py-1 text-gray-900">{{ draft.region || '-' }} / {{ draft.rsm || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Alamat</td>
                <td class="py-1 text-gray-700">{{ draft.address || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Email</td>
                <td class="py-1 text-gray-900 font-medium">{{ draft.email || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 2. Tax Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-4">
          <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-3">
            Tax Information
          </h2>
          <table class="w-full text-sm">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-32">NPWP</td>
                <td class="py-1 text-gray-900">{{ draft.npwp || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama NPWP</td>
                <td class="py-1 text-gray-900">{{ draft.npwp_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis NPWP</td>
                <td class="py-1 text-gray-900">{{ draft.npwp_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis PPh</td>
                <td class="py-1 text-gray-900">{{ draft.pph_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Tarif PPh Sistem</td>
                <td class="py-1 text-gray-900">
                  {{ (comparison?.calculated?.pph_rate ? comparison.calculated.pph_rate * 100 : 2.5) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 3. Program & Item Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-4">
          <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-3">
            Program & Invoice Type
          </h2>
          <table class="w-full text-sm">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-32">Invoice Type</td>
                <td class="py-1 text-gray-900">
                  {{ draft.invoice_type || 'N/A' }}
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama Program</td>
                <td class="py-1 text-gray-900">{{ draft.program_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Periode Program</td>
                <td class="py-1 text-gray-900">{{ draft.program_period || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">No CN / Ref</td>
                <td class="py-1 text-gray-900">{{ draft.cn_number || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Item (Qty)</td>
                <td class="py-1 text-gray-900">{{ draft.item_name || draft.item_code || '-' }} ({{ draft.real_qty || 1 }})</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Calculation Comparison Table -->
      <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center bg-gray-50/50">
          <h3 class="text-sm font-medium text-gray-900">
            Perbandingan Nilai Excel VS Hasil Perhitungan Sistem
          </h3>
          <div>
            <span
              v-if="comparison?.is_matched"
              class="text-xs font-medium text-green-700 bg-green-50 px-2 py-0.5 rounded border border-green-200"
            >
              Semua Rumus Match
            </span>
            <span
              v-else
              class="text-xs font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200"
            >
              Terdapat Perbedaan
            </span>
          </div>
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Komponen Perhitungan</TableHead>
              <TableHead class="text-right">Nilai Excel</TableHead>
              <TableHead class="text-right">Hasil Sistem Laravel</TableHead>
              <TableHead class="text-right">Selisih</TableHead>
              <TableHead class="text-center w-[120px]">Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow>
              <TableCell class="font-medium">Support Amount</TableCell>
              <TableCell class="text-right">{{ formatCurrency(draft.support_amount) }}</TableCell>
              <TableCell class="text-right">{{ formatCurrency(comparison?.calculated?.support_amount ?? draft.support_amount) }}</TableCell>
              <TableCell class="text-right">Rp 0</TableCell>
              <TableCell class="text-center text-green-700 font-medium">MATCH</TableCell>
            </TableRow>

            <TableRow v-for="(field, key) in comparisonFields" :key="key">
              <TableCell class="font-medium capitalize">{{ key.replace('_', ' ') }}</TableCell>
              <TableCell class="text-right">{{ formatCurrency(field.excel) }}</TableCell>
              <TableCell class="text-right">{{ formatCurrency(field.system) }}</TableCell>
              <TableCell class="text-right" :class="field.diff > 0 ? 'text-amber-700 font-medium' : ''">
                {{ formatCurrency(field.diff) }}
              </TableCell>
              <TableCell class="text-center">
                <span :class="field.status === 'MATCH' ? 'text-green-700 font-medium' : 'text-red-700 font-medium'">
                  {{ field.status }}
                </span>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { CheckCircle as CheckCircleIcon, AlertCircle as AlertCircleIcon } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from '@/components/ui/table';

const props = defineProps({
  id: [String, Number],
});

const router = useRouter();
const draft = ref({});
const comparison = ref(null);
const loading = ref(true);
const validating = ref(false);
const generating = ref(false);
const alertMessage = ref(null);
const alertSuccess = ref(true);

const comparisonFields = computed(() => {
  return comparison.value?.fields || {};
});

const fetchDetail = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`/api/drafts/${props.id}`);
    draft.value = res.data.draft;
    comparison.value = res.data.comparison;
  } catch (err) {
    alertMessage.value = 'Gagal memuat detail draft.';
    alertSuccess.value = false;
  } finally {
    loading.value = false;
  }
};

const validateDraft = async () => {
  validating.value = true;
  alertMessage.value = null;
  try {
    const res = await axios.post(`/api/drafts/${props.id}/validate`);
    draft.value = res.data.draft;
    comparison.value = res.data.comparison;
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal validasi draft.';
    alertSuccess.value = false;
  } finally {
    validating.value = false;
  }
};

const generateInvoice = async () => {
  if (!confirm('Terbitkan Invoice dari Draft ini?')) return;

  generating.value = true;
  alertMessage.value = null;
  try {
    const res = await axios.post(`/api/invoices/generate/${props.id}`);
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
    router.push(`/invoices/${res.data.invoice.id}`);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal generate invoice.';
    alertSuccess.value = false;
    generating.value = false;
  }
};

const formatCurrency = (val) => {
  const num = Math.round(Number(val) || 0);
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
};

onMounted(() => {
  fetchDetail();
});
</script>
