<template>
  <div class="space-y-4 font-sans">
    <!-- Top Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-gray-200">
      <!-- Layar kecil: tautan kembali di baris sendiri, judul di bawahnya -->
      <div class="flex flex-wrap sm:flex-nowrap items-center gap-x-2.5 gap-y-1 min-w-0">
        <router-link :to="`/drafts/${draft.brand || 'realme'}/${draft.area || 'jabar'}`" class="basis-full sm:basis-auto text-xs font-medium text-gray-500 hover:text-gray-900 transition">
          ← Kembali ke Draft
        </router-link>
        <span class="hidden sm:inline text-gray-300">|</span>
        <h1 class="text-lg font-bold tracking-tight text-gray-950">Detail Draft #{{ id }}</h1>
      </div>

      <!-- Layar kecil: status selebar layar, tombol aksi berbagi satu baris sama lebar -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Status Badges -->
        <span
          v-if="draft.status === 'ready'"
          class="h-8 px-2.5 bg-gray-50 text-gray-700 border border-gray-200 text-xs font-medium rounded-lg inline-flex items-center justify-center gap-1.5 select-none basis-full sm:basis-auto"
          title="Draft siap untuk digenerate menjadi invoice"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
          <span>Ready</span>
        </span>

        <span
          v-else-if="draft.status === 'invoiced'"
          class="h-8 px-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg inline-flex items-center justify-center gap-1.5 select-none basis-full sm:basis-auto"
          title="Invoice telah diterbitkan"
        >
          <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-600" />
          <span>Invoiced</span>
        </span>

        <span
          v-else-if="draft.status === 'error'"
          class="h-8 px-2.5 bg-rose-50 text-rose-700 border border-rose-200 text-xs font-medium rounded-lg inline-flex items-center justify-center gap-1.5 select-none basis-full sm:basis-auto"
          title="Draft memiliki ketidaksesuaian rumus"
        >
          <AlertCircleIcon class="h-3.5 w-3.5 text-rose-600" />
          <span>Error</span>
        </span>

        <span
          v-else-if="draft.status"
          class="h-8 px-2.5 bg-gray-50 text-gray-700 border border-gray-200 text-xs font-medium rounded-lg inline-flex items-center justify-center gap-1.5 select-none basis-full sm:basis-auto capitalize"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
          <span>{{ draft.status }}</span>
        </span>
        <button
          v-if="draft.status !== 'invoiced'"
          @click="validateDraft"
          :disabled="validating"
          class="h-8 px-2 sm:px-3 flex-1 sm:flex-initial border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition cursor-pointer shadow-2xs whitespace-nowrap"
        >
          <span class="sm:hidden">{{ validating ? 'Memvalidasi...' : 'Validasi' }}</span>
          <span class="hidden sm:inline">{{ validating ? 'Memvalidasi...' : 'Validasi Ulang Rumus' }}</span>
        </button>

        <button
          v-if="draft.status === 'ready'"
          @click="showGenerateModal = true"
          :disabled="generating"
          class="h-8 px-2 sm:px-3.5 flex-1 sm:flex-initial bg-[#1D70F5] text-white text-xs font-medium rounded-lg hover:bg-blue-600 disabled:opacity-50 transition cursor-pointer shadow-2xs whitespace-nowrap"
        >
          <span class="sm:hidden">{{ generating ? 'Proses...' : 'Generate' }}</span>
          <span class="hidden sm:inline">{{ generating ? 'Membuat Invoice...' : 'Generate Invoice' }}</span>
        </button>

        <router-link
          v-if="draft.invoice"
          :to="`/invoices/${draft.invoice.id}`"
          class="h-8 px-2 sm:px-3.5 flex-1 sm:flex-initial bg-[#1D70F5] text-white text-xs font-medium rounded-lg hover:bg-blue-600 transition inline-flex items-center justify-center shadow-2xs whitespace-nowrap"
        >
          <span class="sm:hidden">Lihat Invoice →</span>
          <span class="hidden sm:inline">Lihat Invoice ({{ draft.invoice.invoice_number }}) →</span>
        </router-link>
      </div>
    </div>

    <!-- Alert -->
    <Alert v-if="alertMessage" :variant="alertSuccess ? 'default' : 'destructive'" class="text-xs py-2.5">
      <CheckCircleIcon v-if="alertSuccess" class="h-4 w-4" />
      <AlertCircleIcon v-else class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between text-xs">
        <span>{{ alertMessage }}</span>
        <button @click="alertMessage = null" class="ml-4 text-xs opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
      </AlertDescription>
    </Alert>

    <div v-if="loading" class="py-8 text-center text-xs text-gray-500">
      Memuat data detail draft...
    </div>

    <div v-else class="space-y-4">
      <!-- Info Grid (3 Columns) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- 1. Dealer Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
          <h2 class="text-xs font-semibold text-gray-950 border-b border-gray-100 pb-2 mb-2.5">
            Dealer Information
          </h2>
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-28">Dealer Code</td>
                <td class="py-1 font-medium text-gray-900">{{ draft.dealer_code || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Dealer Name</td>
                <td class="py-1 text-gray-800">{{ draft.dealer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Customer</td>
                <td class="py-1 text-gray-800">{{ draft.customer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Region / RSM</td>
                <td class="py-1 text-gray-800">{{ draft.region || '-' }} / {{ draft.rsm || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500 align-top">Alamat</td>
                <td class="py-1 text-gray-700 leading-relaxed">{{ draft.address || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Email</td>
                <td class="py-1 text-gray-900 font-medium break-all">{{ draft.email || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">WhatsApp</td>
                <td class="py-1 text-gray-900 font-medium">{{ draft.whatsapp || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 2. Tax Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
          <h2 class="text-xs font-semibold text-gray-950 border-b border-gray-100 pb-2 mb-2.5">
            Tax Information
          </h2>
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-28">NPWP</td>
                <td class="py-1 text-gray-800">{{ draft.npwp || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama NPWP</td>
                <td class="py-1 text-gray-800">{{ draft.npwp_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis NPWP</td>
                <td class="py-1 text-gray-800">{{ draft.npwp_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis PPh</td>
                <td class="py-1 text-gray-800">{{ draft.pph_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Tarif PPh Sistem</td>
                <td class="py-1 text-gray-800">
                  {{ (comparison?.calculated?.pph_rate ? comparison.calculated.pph_rate * 100 : 2.5) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 3. Program & Item Information -->
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
          <h2 class="text-xs font-semibold text-gray-950 border-b border-gray-100 pb-2 mb-2.5">
            Program & Invoice Type
          </h2>
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-28">Invoice Type</td>
                <td class="py-1 text-gray-800 font-medium">
                  {{ draft.invoice_type || 'N/A' }}
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Status</td>
                <td class="py-1 text-gray-900 font-medium capitalize">
                  {{ draft.status || '-' }}
                </td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama Program</td>
                <td class="py-1 text-gray-800">{{ draft.program_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Periode Program</td>
                <td class="py-1 text-gray-800">{{ draft.program_period || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">No CN / Ref</td>
                <td class="py-1 text-gray-800">{{ draft.cn_number || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Item (Qty)</td>
                <td class="py-1 text-gray-800">{{ draft.item_name || draft.item_code || '-' }} ({{ draft.real_qty || 1 }})</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Calculation Comparison Table -->
      <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-2xs">
        <div class="px-4 py-2.5 border-b border-gray-100 flex justify-between items-center gap-3 bg-gray-50/50">
          <h3 class="text-xs font-semibold text-gray-950">
            Perbandingan Nilai Excel VS Hasil Perhitungan Sistem
          </h3>
          <div class="shrink-0">
            <span
              v-if="comparison?.is_matched"
              class="text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200"
            >
              Semua Rumus Match
            </span>
            <span
              v-else
              class="text-[11px] font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200"
            >
              Terdapat Perbedaan
            </span>
          </div>
        </div>

        <!-- Layar kecil: satu blok per komponen perhitungan dengan baris label–nilai -->
        <ul class="md:hidden divide-y divide-gray-100">
          <li class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm font-medium text-gray-900 break-words">Support Amount</p>
              <span class="text-emerald-700 font-medium shrink-0">MATCH</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-500">Nilai Excel</span>
              <span class="text-gray-800 text-right">{{ formatCurrency(draft.support_amount) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-500">Hasil Sistem Laravel</span>
              <span class="text-gray-800 text-right">{{ formatCurrency(comparison?.calculated?.support_amount ?? draft.support_amount) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-500">Selisih</span>
              <span class="text-gray-500 text-right">Rp 0</span>
            </div>
          </li>

          <li v-for="(field, key) in comparisonFields" :key="key" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm font-medium text-gray-900 capitalize break-words">{{ key.replace('_', ' ') }}</p>
              <span class="font-medium shrink-0" :class="field.status === 'MATCH' ? 'text-emerald-700' : 'text-rose-700'">
                {{ field.status }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-500">Nilai Excel</span>
              <span class="text-gray-800 text-right">{{ formatCurrency(field.excel) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-500">Hasil Sistem Laravel</span>
              <span class="text-gray-800 text-right">{{ formatCurrency(field.system) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-gray-500">Selisih</span>
              <span class="text-right" :class="field.diff > 0 ? 'text-amber-700 font-medium' : 'text-gray-500'">{{ formatCurrency(field.diff) }}</span>
            </div>
          </li>
        </ul>

        <Table container-class="hidden md:block">
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
            <TableRow class="hover:bg-transparent">
              <TableCell class="font-medium text-xs text-gray-900 py-2">Support Amount</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">{{ formatCurrency(draft.support_amount) }}</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">{{ formatCurrency(comparison?.calculated?.support_amount ?? draft.support_amount) }}</TableCell>
              <TableCell class="text-right text-xs text-gray-500 py-2">Rp 0</TableCell>
              <TableCell class="text-center text-emerald-700 font-medium text-xs py-2">MATCH</TableCell>
            </TableRow>

            <TableRow v-for="(field, key) in comparisonFields" :key="key" class="hover:bg-transparent">
              <TableCell class="font-medium text-xs text-gray-900 py-2 capitalize">{{ key.replace('_', ' ') }}</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">{{ formatCurrency(field.excel) }}</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">{{ formatCurrency(field.system) }}</TableCell>
              <TableCell class="text-right text-xs py-2" :class="field.diff > 0 ? 'text-amber-700 font-medium' : 'text-gray-500'">
                {{ formatCurrency(field.diff) }}
              </TableCell>
              <TableCell class="text-center text-xs py-2">
                <span :class="field.status === 'MATCH' ? 'text-emerald-700 font-medium' : 'text-rose-700 font-medium'">
                  {{ field.status }}
                </span>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <!-- Generate Invoice Modal with Bill To Selection -->
    <GenerateInvoiceModal
      v-model="showGenerateModal"
      title="Terbitkan Invoice"
      subtitle="Pilih pihak Bill To untuk dicantumkan pada invoice draft ini:"
      :target-info="`${draft.dealer_name || draft.dealer_code} (${draft.invoice_type || 'Invoice'})`"
      confirm-text="Ya, Terbitkan Invoice"
      :loading="generating"
      @confirm="handleGenerateConfirm"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { CheckCircle as CheckCircleIcon, AlertCircle as AlertCircleIcon } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import GenerateInvoiceModal from '@/components/GenerateInvoiceModal.vue';
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
const showGenerateModal = ref(false);
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

const handleGenerateConfirm = async (billTo) => {
  generating.value = true;
  alertMessage.value = null;
  try {
    const res = await axios.post(`/api/invoices/generate/${props.id}`, {
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
    showGenerateModal.value = false;
    router.push(`/invoices/${res.data.invoice.id}`);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal generate invoice.';
    alertSuccess.value = false;
  } finally {
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
