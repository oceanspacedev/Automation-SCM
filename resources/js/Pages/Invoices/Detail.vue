<template>
  <div class="space-y-4 font-sans">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-200">
      <div class="flex items-center space-x-2.5">
        <router-link to="/invoices" class="text-xs font-medium text-gray-500 hover:text-gray-900 transition">
          ← Kembali ke Daftar Invoice
        </router-link>
        <span class="text-gray-300">|</span>
        <h1 class="text-lg font-bold tracking-tight text-gray-950">Invoice {{ invoice.invoice_number }}</h1>
        <span class="text-xs font-medium text-gray-500">
          ({{ invoice.invoice_type }})
        </span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <!-- Status Badges -->
        <span
          v-if="invoice.status === 'sent' && invoice.email_sent_at && invoice.whatsapp_sent_at"
          class="h-8 px-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
          title="Invoice ini sudah dikirim ke Email dan WhatsApp"
        >
          <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-600" />
          <span>Email & WA Terkirim</span>
        </span>

        <span
          v-else-if="invoice.email_sent_at && !invoice.whatsapp_sent_at"
          class="h-8 px-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
          title="Email sudah terkirim"
        >
          <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-600" />
          <span>Email Terkirim</span>
        </span>

        <span
          v-else-if="!invoice.email_sent_at && invoice.whatsapp_sent_at"
          class="h-8 px-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
          title="WhatsApp sudah terkirim"
        >
          <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-600" />
          <span>WA Terkirim</span>
        </span>

        <span
          v-else-if="invoice.status === 'sent'"
          class="h-8 px-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
          title="Invoice sudah terkirim"
        >
          <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-600" />
          <span>Terkirim</span>
        </span>

        <span
          v-else-if="invoice.status === 'paid'"
          class="h-8 px-2.5 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
          <span>Paid</span>
        </span>

        <span
          v-else-if="invoice.status === 'failed'"
          class="h-8 px-2.5 bg-rose-50 text-rose-700 border border-rose-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
        >
          <AlertCircleIcon class="h-3.5 w-3.5 text-rose-600" />
          <span>Gagal Terkirim</span>
        </span>

        <span
          v-else-if="invoice.status === 'generated'"
          class="h-8 px-2.5 bg-gray-50 text-gray-700 border border-gray-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none"
          title="Invoice telah digenerate, siap dikirim"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
          <span>Generated</span>
        </span>

        <span
          v-else-if="invoice.status"
          class="h-8 px-2.5 bg-gray-50 text-gray-700 border border-gray-200 text-xs font-medium rounded-lg inline-flex items-center gap-1.5 select-none capitalize"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
          <span>{{ invoice.status }}</span>
        </span>

        <!-- Kirim Button -->
        <button
          v-if="!(invoice.email_sent_at && invoice.whatsapp_sent_at)"
          @click="showEmailModal = true"
          :disabled="quickSending"
          class="h-8 px-3 bg-[#1D70F5] text-white hover:bg-blue-600 text-xs font-medium rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-2xs"
          title="Pilih pengirim dan kirim invoice"
        >
          <SendIcon class="w-3.5 h-3.5" />
          <span>Kirim Notifikasi</span>
        </button>

        <a
          :href="`/invoices/${invoice.id}/preview`"
          target="_blank"
          class="h-8 px-3 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition inline-flex items-center shadow-2xs"
        >
          Preview Invoice
        </a>
        <a
          :href="`/invoices/${invoice.id}/pdf`"
          class="h-8 px-3 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition inline-flex items-center shadow-2xs"
        >
          Download PDF
        </a>
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

    <!-- Send Email / WA Modal -->
    <SendEmailModal
      v-model="showEmailModal"
      :invoice-id="invoice.id"
      :invoice-number="invoice.invoice_number"
      :dealer-name="invoice.dealer_name"
      :customer-name="invoice.customer_name"
      :default-email="invoice.email || invoice.draft?.email"
      :default-whatsapp="invoice.whatsapp || invoice.draft?.whatsapp"
      @sent="onEmailSent"
    />

    <div v-if="loading" class="py-8 text-center text-xs text-gray-500">
      Memuat rincian invoice...
    </div>

    <div v-else class="space-y-4">
      <!-- Grid Informasi (3 Columns) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Dealer -->
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
          <h2 class="text-xs font-semibold text-gray-950 border-b border-gray-100 pb-2 mb-2.5">
            Dealer & Penerima
          </h2>
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-28">Dealer Code</td>
                <td class="py-1 font-medium text-gray-900">{{ invoice.dealer_code || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Dealer Name</td>
                <td class="py-1 text-gray-800">{{ invoice.dealer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Customer</td>
                <td class="py-1 text-gray-800">{{ invoice.customer_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500 align-top">Alamat</td>
                <td class="py-1 text-gray-700 leading-relaxed">{{ invoice.address || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Email</td>
                <td class="py-1 text-gray-900 font-medium break-all">{{ invoice.email || invoice.draft?.email || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">WhatsApp</td>
                <td class="py-1 text-gray-900 font-medium">{{ invoice.whatsapp || invoice.draft?.whatsapp || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tax -->
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
          <h2 class="text-xs font-semibold text-gray-950 border-b border-gray-100 pb-2 mb-2.5">
            Data Pajak & Dokumen
          </h2>
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-28">NPWP</td>
                <td class="py-1 text-gray-800 font-mono">{{ invoice.npwp || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama NPWP</td>
                <td class="py-1 text-gray-800">{{ invoice.npwp_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis NPWP</td>
                <td class="py-1 text-gray-800">{{ invoice.npwp_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Jenis PPh</td>
                <td class="py-1 text-gray-800">{{ invoice.pph_type || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Tanggal Inv</td>
                <td class="py-1 text-gray-800">{{ invoice.invoice_date || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Status</td>
                <td class="py-1 text-gray-900 font-medium capitalize">{{ invoice.status || '-' }}</td>
              </tr>
              <tr v-if="invoice.email_sent_at">
                <td class="py-1 text-gray-500">Email Dikirim</td>
                <td class="py-1 text-gray-700 text-[11px]">{{ formatDateTime(invoice.email_sent_at) }}</td>
              </tr>
              <tr v-if="invoice.whatsapp_sent_at">
                <td class="py-1 text-gray-500">WA Dikirim</td>
                <td class="py-1 text-gray-700 text-[11px]">{{ formatDateTime(invoice.whatsapp_sent_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Program -->
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
          <h2 class="text-xs font-semibold text-gray-950 border-b border-gray-100 pb-2 mb-2.5">
            Program & Item
          </h2>
          <table class="w-full text-xs">
            <tbody>
              <tr>
                <td class="py-1 text-gray-500 w-28">Nama Program</td>
                <td class="py-1 text-gray-800">{{ invoice.program_name || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Periode Program</td>
                <td class="py-1 text-gray-800">{{ invoice.program_period || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">No CN</td>
                <td class="py-1 text-gray-800 font-mono">{{ invoice.cn_number || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Kode Item</td>
                <td class="py-1 text-gray-800 font-mono">{{ invoice.item_code || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1 text-gray-500">Nama Item</td>
                <td class="py-1 text-gray-800">{{ invoice.item_name || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Financial Calculation Card -->
      <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-2xs">
        <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50/50">
          <h3 class="text-xs font-semibold text-gray-950">
            Rincian Perhitungan Keuangan Invoice
          </h3>
        </div>

        <Table>
          <TableBody>
            <TableRow class="hover:bg-transparent">
              <TableCell class="w-1/3 text-gray-600 text-xs py-2">Support Amount</TableCell>
              <TableCell class="text-right font-medium text-xs text-gray-900 py-2">
                {{ formatCurrency(invoice.support_amount) }}
              </TableCell>
            </TableRow>
            <TableRow class="hover:bg-transparent">
              <TableCell class="text-gray-600 text-xs py-2">Dasar Pengenaan Pajak (DPP)</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">
                {{ formatCurrency(invoice.dpp) }}
              </TableCell>
            </TableRow>
            <TableRow class="hover:bg-transparent">
              <TableCell class="text-gray-600 text-xs py-2">DPP Lain</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">
                {{ formatCurrency(invoice.dpp_lain) }}
              </TableCell>
            </TableRow>
            <TableRow class="hover:bg-transparent">
              <TableCell class="text-gray-600 text-xs py-2">PPN (12%)</TableCell>
              <TableCell class="text-right text-xs text-gray-800 py-2">
                {{ formatCurrency(invoice.ppn) }}
              </TableCell>
            </TableRow>
            <TableRow class="hover:bg-transparent">
              <TableCell class="text-gray-600 text-xs py-2">PPh ({{ invoice.pph_type }})</TableCell>
              <TableCell class="text-right text-rose-600 text-xs py-2 font-medium">
                ({{ formatCurrency(invoice.pph) }})
              </TableCell>
            </TableRow>
            <TableRow class="bg-gray-50/70 hover:bg-gray-50/70">
              <TableCell class="text-gray-950 font-bold text-xs py-2.5">TOTAL NETPAY</TableCell>
              <TableCell class="text-right font-bold text-gray-950 text-sm py-2.5">
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

const formatDateTime = (val) => {
  if (!val) return '-';
  const d = new Date(val);
  return isNaN(d.getTime()) ? val : d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

onMounted(() => {
  fetchInvoice();
});
</script>
