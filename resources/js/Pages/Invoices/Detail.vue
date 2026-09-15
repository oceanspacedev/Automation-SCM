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
        <!-- Already Sent Status Badge -->
        <span
          v-if="invoice.status === 'sent' && invoice.email_sent_at && invoice.whatsapp_sent_at"
          class="h-9 px-3.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-medium rounded-md inline-flex items-center gap-1.5 select-none"
          title="Invoice ini sudah dikirim ke Email dan WhatsApp"
        >
          <CheckCircleIcon class="h-4 w-4 text-emerald-600" />
          <span>Email & WA Terkirim</span>
        </span>

        <span
          v-else-if="invoice.email_sent_at && !invoice.whatsapp_sent_at"
          class="h-9 px-3.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-medium rounded-md inline-flex items-center gap-1.5 select-none"
          title="Email sudah terkirim"
        >
          <CheckCircleIcon class="h-4 w-4 text-emerald-600" />
          <span>Email Terkirim</span>
        </span>

        <span
          v-else-if="!invoice.email_sent_at && invoice.whatsapp_sent_at"
          class="h-9 px-3.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-medium rounded-md inline-flex items-center gap-1.5 select-none"
          title="WhatsApp sudah terkirim"
        >
          <CheckCircleIcon class="h-4 w-4 text-emerald-600" />
          <span>WA Terkirim</span>
        </span>

        <!-- 1-Click Send if destination exists and not completely sent -->
        <button
          v-if="!(invoice.email_sent_at && invoice.whatsapp_sent_at) && (invoice.email || invoice.draft?.email || invoice.whatsapp || invoice.draft?.whatsapp)"
          @click="quickSend"
          :disabled="quickSending"
          class="h-9 px-3.5 bg-[#1D70F5] text-white hover:bg-blue-600 text-sm font-medium rounded-md transition inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-xs"
          title="Kirim notifikasi langsung ke Email & WhatsApp yang tersedia"
        >
          <span v-if="quickSending" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <SendIcon v-else class="w-4 h-4" />
          <span>{{ quickSending ? 'Mengirim...' : 'Kirim Notifikasi' }}</span>
        </button>

        <!-- Manual or Customize Destination -->
        <button
          v-if="!(invoice.email_sent_at && invoice.whatsapp_sent_at)"
          @click="showEmailModal = true"
          class="h-9 px-3 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md transition inline-flex items-center gap-1.5 cursor-pointer"
          title="Sesuaikan Email / Nomor WhatsApp dan kirim"
        >
          <SendIcon class="w-4 h-4" />
          <span>{{ (invoice.email || invoice.draft?.email || invoice.whatsapp || invoice.draft?.whatsapp) ? 'Kirim ke Tujuan Lain' : 'Kirim Notifikasi' }}</span>
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
          class="h-9 px-4 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md transition inline-flex items-center"
        >
          Download PDF
        </a>
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

    <!-- Send Email / WA Modal -->
    <SendEmailModal
      v-model="showEmailModal"
      :invoice-id="invoice.id"
      :invoice-number="invoice.invoice_number"
      :default-email="invoice.email || invoice.draft?.email"
      :default-whatsapp="invoice.whatsapp || invoice.draft?.whatsapp"
      @sent="onEmailSent"
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
              <tr>
                <td class="py-1 text-gray-500">Email</td>
                <td class="py-1 text-gray-900 font-medium">{{ invoice.email || invoice.draft?.email || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">WhatsApp</td>
                <td class="py-1 text-gray-900 font-medium">{{ invoice.whatsapp || invoice.draft?.whatsapp || '-' }}</td>
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
import { Send as SendIcon, CheckCircle as CheckCircleIcon, AlertCircle as AlertCircleIcon } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
const quickSending = ref(false);
const alertMessage = ref(null);
const alertSuccess = ref(true);

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

const onEmailSent = (payload) => {
  alertSuccess.value = true;
  alertMessage.value = 'Notifikasi invoice berhasil dikirim!';
  invoice.value.status = 'sent';
  if (payload.email) invoice.value.email_sent_at = new Date().toISOString();
  if (payload.whatsapp) invoice.value.whatsapp_sent_at = new Date().toISOString();
};

const quickSend = async () => {
  if (quickSending.value || (invoice.value.email_sent_at && invoice.value.whatsapp_sent_at)) return;
  quickSending.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post(`/api/invoices/${props.id}/quick-send-email`);
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    invoice.value.status = 'sent';
    if (invoice.value.email || invoice.value.draft?.email) invoice.value.email_sent_at = new Date().toISOString();
    if (invoice.value.whatsapp || invoice.value.draft?.whatsapp) invoice.value.whatsapp_sent_at = new Date().toISOString();
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal mengirim notifikasi invoice';
  } finally {
    quickSending.value = false;
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
