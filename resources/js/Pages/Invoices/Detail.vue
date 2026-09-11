<template>
  <div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex items-center justify-between pb-4 border-b border-gray-200">
      <div class="flex items-center space-x-3">
        <router-link to="/invoices" class="text-sm font-medium text-gray-500 hover:text-black">
          ← Kembali ke Daftar Invoice
        </router-link>
        <span class="text-gray-300">|</span>
        <h1 class="text-xl font-bold tracking-tight text-gray-900">Invoice {{ invoice.invoice_number }}</h1>
        <span class="text-sm font-medium text-gray-700">
          ({{ invoice.invoice_type }})
        </span>
      </div>

      <div class="flex items-center space-x-2">
        <button
          @click="showEmailModal = true"
          class="h-9 px-3.5 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md transition inline-flex items-center gap-1.5"
        >
          <MailIcon class="h-4 w-4" />
          Kirim Email
        </button>
        <a
          :href="`/invoices/${invoice.id}/preview`"
          target="_blank"
          class="h-9 px-3.5 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md transition inline-flex items-center"
        >
          Preview Invoice
        </a>
        <a
          :href="`/invoices/${invoice.id}/pdf`"
          class="h-9 px-4 bg-black text-white text-sm font-medium rounded-md hover:bg-gray-800 transition inline-flex items-center"
        >
          Download PDF
        </a>
      </div>
    </div>

    <!-- Send Email Modal -->
    <SendEmailModal
      v-model="showEmailModal"
      :invoice-id="invoice.id"
      :invoice-number="invoice.invoice_number"
    />

    <div v-if="loading" class="py-8 text-center text-sm text-gray-500">
      Memuat rincian invoice...
    </div>

    <div v-else class="space-y-6">
      <!-- Grid Informasi (3 Columns) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Dealer -->
        <div class="rounded-xl border border-gray-200 bg-white p-4">
          <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-3">
            Dealer & Penerima
          </h2>
          <table class="w-full text-sm">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-32">Dealer Code</td>
                <td class="py-1 font-medium text-gray-900">{{ invoice.dealer_code || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Dealer Name</td>
                <td class="py-1 text-gray-900">{{ invoice.dealer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Customer</td>
                <td class="py-1 text-gray-900">{{ invoice.customer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Alamat</td>
                <td class="py-1 text-gray-700">{{ invoice.address || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tax -->
        <div class="rounded-xl border border-gray-200 bg-white p-4">
          <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-3">
            Data Pajak & Dokumen
          </h2>
          <table class="w-full text-sm">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-32">NPWP</td>
                <td class="py-1 text-gray-900">{{ invoice.npwp || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama NPWP</td>
                <td class="py-1 text-gray-900">{{ invoice.npwp_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis NPWP</td>
                <td class="py-1 text-gray-900">{{ invoice.npwp_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis PPh</td>
                <td class="py-1 text-gray-900">{{ invoice.pph_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Tanggal Inv</td>
                <td class="py-1 text-gray-900">{{ invoice.invoice_date || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Program -->
        <div class="rounded-xl border border-gray-200 bg-white p-4">
          <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2 mb-3">
            Program & Item
          </h2>
          <table class="w-full text-sm">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-32">Nama Program</td>
                <td class="py-1 text-gray-900">{{ invoice.program_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Periode Program</td>
                <td class="py-1 text-gray-900">{{ invoice.program_period || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">No CN</td>
                <td class="py-1 text-gray-900">{{ invoice.cn_number || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Kode Item</td>
                <td class="py-1 text-gray-900">{{ invoice.item_code || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama Item</td>
                <td class="py-1 text-gray-900">{{ invoice.item_name || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Financial Calculation Card -->
      <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50">
          <h3 class="text-sm font-medium text-gray-900">
            Rincian Perhitungan Keuangan Invoice
          </h3>
        </div>

        <Table>
          <TableBody>
            <TableRow>
              <TableCell class="w-1/3 text-gray-600">Support Amount</TableCell>
              <TableCell class="text-right font-medium">
                {{ formatCurrency(invoice.support_amount) }}
              </TableCell>
            </TableRow>
            <TableRow>
              <TableCell class="text-gray-600">Dasar Pengenaan Pajak (DPP)</TableCell>
              <TableCell class="text-right">
                {{ formatCurrency(invoice.dpp) }}
              </TableCell>
            </TableRow>
            <TableRow>
              <TableCell class="text-gray-600">DPP Lain</TableCell>
              <TableCell class="text-right">
                {{ formatCurrency(invoice.dpp_lain) }}
              </TableCell>
            </TableRow>
            <TableRow>
              <TableCell class="text-gray-600">PPN (12%)</TableCell>
              <TableCell class="text-right">
                {{ formatCurrency(invoice.ppn) }}
              </TableCell>
            </TableRow>
            <TableRow>
              <TableCell class="text-gray-600">PPh ({{ invoice.pph_type }})</TableCell>
              <TableCell class="text-right text-red-600">
                ({{ formatCurrency(invoice.pph) }})
              </TableCell>
            </TableRow>
            <TableRow class="bg-gray-50/50 font-semibold">
              <TableCell class="text-gray-900 font-medium">TOTAL NETPAY</TableCell>
              <TableCell class="text-right font-semibold text-gray-900 text-base">
                {{ formatCurrency(invoice.netpay) }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { MailIcon } from '@lucide/vue';
import SendEmailModal from '@/components/SendEmailModal.vue';
import {
  Table,
  TableBody,
  TableRow,
  TableCell,
} from '@/components/ui/table';

const props = defineProps({
  id: [String, Number],
});

const invoice = ref({});
const loading = ref(true);
const showEmailModal = ref(false);

const fetchInvoice = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`/api/invoices/${props.id}`);
    invoice.value = res.data;
  } catch (err) {
    console.error('Error fetching invoice', err);
  } finally {
    loading.value = false;
  }
};

const formatCurrency = (val) => {
  const num = Math.round(Number(val) || 0);
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
};

onMounted(() => {
  fetchInvoice();
});
</script>
