<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900">Invoices</h1>
          <!-- Google Connection Status Badge -->
          <span
            v-if="googleStatus.is_connected"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-normal bg-gray-50 text-gray-600 border border-gray-200"
            :title="`Terhubung dengan akun Google: ${googleStatus.account_email}`"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
            <span>Gmail: {{ googleStatus.account_email }}</span>
          </span>
          <a
            v-else
            href="/auth/google/redirect"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-normal bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 transition cursor-pointer"
            title="Klik untuk menghubungkan akun Google Workspace (Gmail API)"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
            <span>Hubungkan Google Workspace</span>
          </a>
        </div>
        <p class="text-sm text-gray-500">Daftar invoice resmi yang telah diterbitkan.</p>
      </div>
      <div class="flex items-center gap-2.5">
        <!-- Multi-select Send Button -->
        <button
          v-if="selectedIds.length > 0"
          @click="askSendBatch"
          :disabled="sendingBatch"
          class="h-9 px-4 bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium rounded-lg disabled:opacity-50 transition cursor-pointer flex items-center gap-2 shadow-2xs"
          :title="`Kirim ${selectedIds.length} invoice terpilih`"
        >
          <svg v-if="sendingBatch" class="animate-spin -ml-0.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <SendIcon v-else class="w-3.5 h-3.5 text-white" />
          <span>{{ sendingBatch ? 'Mengirim...' : `Kirim (${selectedIds.length}) Notifikasi Terpilih` }}</span>
        </button>

        <!-- Send All Button (when nothing specifically selected) -->
        <button
          v-else
          @click="askSendAll"
          :disabled="sendingAll"
          class="h-9 px-4 bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium rounded-lg disabled:opacity-50 transition cursor-pointer flex items-center gap-2 shadow-2xs"
          title="Kirim semua invoice yang belum terkirim via Email & WhatsApp"
        >
          <svg v-if="sendingAll" class="animate-spin -ml-0.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <SendIcon v-else class="w-3.5 h-3.5 text-white" />
          <span>{{ sendingAll ? 'Mengirim Semua...' : 'Kirim Semua Notifikasi' }}</span>
        </button>

        <router-link
          to="/drafts"
          class="h-10 px-4 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-lg transition inline-flex items-center gap-2 shadow-xs"
        >
          <ArrowLeftIcon class="w-4 h-4 text-gray-600" />
          <span>Kembali ke Draft</span>
        </router-link>
      </div>
    </div>

    <!-- Alert Notification -->
    <Alert v-if="alertMessage" :variant="alertSuccess ? 'default' : 'destructive'">
      <CheckCircleIcon v-if="alertSuccess" class="h-4 w-4" />
      <AlertCircleIcon v-else class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ alertMessage }}</span>
        <button @click="alertMessage = null" class="ml-4 text-sm opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
      </AlertDescription>
    </Alert>

    <!-- Toolbar Filters (Shadcn style) -->
    <div class="flex flex-wrap items-center justify-between gap-3 py-1">
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="filters.search"
          @input="debounceFetch"
          type="text"
          placeholder="Filter invoice, dealer, customer, email..."
          class="h-9 w-64 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
        />
        <select
          v-model="filters.invoice_type"
          @change="fetchInvoices(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Tipe</option>
          <option value="DSA">DSA</option>
          <option value="NPS FL">NPS FL</option>
          <option value="REGULAR">REGULAR</option>
        </select>
        <select
          v-model="filters.status"
          @change="fetchInvoices(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Status</option>
          <option value="generated">Generated</option>
          <option value="sent">Sent (Terkirim)</option>
          <option value="paid">Paid</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <Popover>
          <PopoverTrigger as-child>
            <button
              :class="[
                'h-9 px-3 inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white text-xs text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-black transition cursor-pointer',
                !filters.date && 'text-gray-400'
              ]"
            >
              <CalendarIcon class="h-3.5 w-3.5" />
              {{ filters.date ? formatDateDisplay(filters.date) : 'Pilih tanggal' }}
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto p-0">
            <Calendar
              :model-value="selectedCalendarDate"
              :initial-focus="true"
              @update:model-value="onDateSelect"
            />
          </PopoverContent>
        </Popover>
        <button
          v-if="filters.search || filters.invoice_type || filters.status || filters.date"
          @click="resetFilters"
          class="h-9 px-3 text-xs text-gray-500 hover:text-black cursor-pointer"
        >
          Reset
        </button>
      </div>
    </div>

    <!-- Official Shadcn Table Card (All uniform font) -->
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead class="w-[40px] text-center">
              <input
                type="checkbox"
                :checked="isAllSelected"
                :indeterminate="isIndeterminate"
                @change="toggleSelectAll"
                :disabled="selectableInvoices.length === 0"
                class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                title="Pilih semua yang belum dikirim di halaman ini"
              />
            </TableHead>
            <TableHead class="w-[150px] whitespace-nowrap text-xs">Invoice</TableHead>
            <TableHead class="min-w-[140px] text-xs">Dealer</TableHead>
            <TableHead class="min-w-[130px] text-xs">Customer</TableHead>
            <TableHead class="min-w-[130px] text-xs">Email</TableHead>
            <TableHead class="w-[110px] whitespace-nowrap text-xs">WhatsApp</TableHead>
            <TableHead class="w-[95px] whitespace-nowrap text-xs">Tanggal</TableHead>
            <TableHead class="text-right w-[120px] whitespace-nowrap text-xs">Amount</TableHead>
            <TableHead class="text-right w-[225px] whitespace-nowrap text-xs">Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableEmpty v-if="loading" :colspan="9">
            Memuat data invoice...
          </TableEmpty>
          <TableEmpty v-else-if="invoices.length === 0" :colspan="9">
            Belum ada invoice yang dibuat. Silakan generate invoice dari menu <strong>Draft</strong>.
          </TableEmpty>
          <TableRow v-for="inv in invoices" :key="inv.id">
            <!-- Row Checkbox -->
            <TableCell class="w-[40px] text-center">
              <input
                type="checkbox"
                :value="inv.id"
                v-model="selectedIds"
                :disabled="isAlreadySent(inv) || !hasDestination(inv)"
                class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                :title="isAlreadySent(inv) ? 'Sudah dikirim' : (!hasDestination(inv) ? 'Tidak ada email atau nomor WhatsApp' : 'Pilih invoice ini')"
              />
            </TableCell>
            <TableCell class="text-xs">
              <router-link :to="`/invoices/${inv.id}`" class="hover:underline text-xs text-gray-900">
                {{ inv.invoice_number }}
              </router-link>
            </TableCell>
            <TableCell class="max-w-[170px] truncate text-xs" :title="`${inv.dealer_code} - ${inv.dealer_name}`">
              {{ inv.dealer_code }} - {{ inv.dealer_name }}
            </TableCell>
            <TableCell class="max-w-[150px] truncate text-xs" :title="inv.customer_name">
              {{ inv.customer_name || '-' }}
            </TableCell>
            <!-- Email -->
            <TableCell class="max-w-[140px] truncate text-xs" :title="inv.email || inv.draft?.email">
              {{ inv.email || inv.draft?.email || '-' }}
            </TableCell>
            <!-- WhatsApp -->
            <TableCell class="whitespace-nowrap text-xs" :title="inv.whatsapp || inv.draft?.whatsapp">
              {{ inv.whatsapp || inv.draft?.whatsapp || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap text-xs">
              {{ inv.invoice_date || '-' }}
            </TableCell>
            <TableCell class="text-right text-gray-800 whitespace-nowrap text-xs">
              {{ formatCurrency(inv.netpay) }}
            </TableCell>

            <!-- Modern Action Buttons With Pixel-Perfect Vertical Alignment -->
            <TableCell class="text-right w-[225px] whitespace-nowrap text-xs">
              <div class="flex items-center justify-end gap-1.5">
                <!-- 1. View / Detail Icon -->
                <router-link
                  :to="`/invoices/${inv.id}`"
                  class="w-8 h-8 shrink-0 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer shadow-2xs"
                  title="Lihat Detail Invoice"
                >
                  <EyeIcon class="w-4 h-4 text-gray-600" />
                </router-link>

                <!-- 2. PDF Download Button (Clean Monochrome Outline) -->
                <a
                  :href="`/invoices/${inv.id}/pdf`"
                  class="h-8 w-[68px] shrink-0 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer shadow-2xs"
                  title="Unduh PDF Invoice"
                >
                  <FileTextIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                  <span class="text-xs font-medium text-gray-700">PDF</span>
                </a>

                <!-- 3. Email/WA Action Button (Calm Neutral / Primary) -->
                <!-- Already Sent: Subtle calm gray/emerald badge -->
                <span
                  v-if="isAlreadySent(inv)"
                  class="h-8 w-[88px] shrink-0 bg-gray-50 text-gray-600 border border-gray-200 text-xs font-medium rounded-lg inline-flex items-center justify-center gap-1.5 cursor-default select-none shadow-2xs"
                  title="Invoice ini sudah terkirim"
                >
                  <CheckCircleIcon class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                  <span>Terkirim</span>
                </span>

                <!-- Ready to send: Blue Kirim button (opens Send modal with sender choice) -->
                <button
                  v-else-if="hasDestination(inv)"
                  @click="openEmailModal(inv)"
                  :disabled="sendingId === inv.id"
                  class="h-8 w-[88px] shrink-0 bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition inline-flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs disabled:opacity-50"
                  :title="`Kirim invoice ke ${inv.email || inv.draft?.email || ''} ${inv.whatsapp || inv.draft?.whatsapp ? '(' + (inv.whatsapp || inv.draft?.whatsapp) + ')' : ''}`"
                >
                  <SendIcon class="w-3.5 h-3.5 text-white shrink-0" />
                  <span>Kirim</span>
                </button>

                <!-- No email/whatsapp: Manual Input Button -->
                <button
                  v-else
                  @click="openEmailModal(inv)"
                  class="h-8 w-[88px] shrink-0 border border-gray-200 bg-white text-gray-700 hover:text-gray-900 hover:bg-gray-50 text-xs font-medium rounded-lg transition inline-flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                  title="Input email / WhatsApp manual & kirim"
                >
                  <SendIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                  <span>Manual</span>
                </button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
        <TableFooter v-if="invoices.length > 0">
          <TableRow>
            <TableCell :colspan="7" class="text-xs text-gray-700">
              Total
            </TableCell>
            <TableCell class="text-right whitespace-nowrap text-xs text-gray-700">
              {{ formatCurrency(totalNetpay) }}
            </TableCell>
            <TableCell></TableCell>
          </TableRow>
        </TableFooter>
      </Table>
    </div>

    <!-- Pagination (Shadcn style) -->
    <div class="flex items-center justify-between py-2 text-xs text-gray-500">
      <div>
        Menampilkan {{ pagination.total > 0 ? (pagination.current_page - 1) * pagination.per_page + 1 : 0 }} sampai {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} dari {{ pagination.total }} invoice.
      </div>
      <div v-if="pagination.last_page > 1" class="flex items-center space-x-2">
        <button
          @click="fetchInvoices(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="h-8 px-3 rounded-md border border-gray-200 text-xs font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Previous
        </button>
        <span class="text-xs font-medium text-gray-700">
          Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
        </span>
        <button
          @click="fetchInvoices(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="h-8 px-3 rounded-md border border-gray-200 text-xs font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Send Email Modal with Sender Selector -->
    <SendEmailModal
      v-model="showEmailModal"
      :invoice-id="selectedInvoice?.id"
      :invoice-number="selectedInvoice?.invoice_number"
      :dealer-name="selectedInvoice?.dealer_name"
      :customer-name="selectedInvoice?.customer_name"
      :default-email="selectedInvoice?.email || selectedInvoice?.draft?.email"
      :default-whatsapp="selectedInvoice?.whatsapp || selectedInvoice?.draft?.whatsapp"
      @sent="onEmailSent"
    />

    <!-- Modern Confirmation Modal (Replacing native browser popup) -->
    <ConfirmModal
      v-model="confirmState.show"
      :title="confirmState.title"
      :message="confirmState.message"
      :confirm-text="confirmState.confirmText"
      :loading="confirmState.loading"
      @confirm="onConfirmAction"
    >
      <div v-if="confirmState.showSenderSelect" class="mt-3 space-y-1.5 text-left">
        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700">
          Kirim dari
        </label>
        <select
          v-model="batchSenderId"
          class="w-full h-10 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option v-for="acc in emailAccounts" :key="acc.id" :value="acc.id">
            {{ acc.name }} &lt;{{ acc.email }}&gt; {{ acc.is_default ? '(Default)' : '' }}
          </option>
        </select>
        <p v-if="selectedBatchSender" class="text-[11px] text-gray-500">
          Pengirim: <span class="font-medium text-gray-800">{{ selectedBatchSender.name }}</span> ({{ selectedBatchSender.email }})
        </p>
      </div>
    </ConfirmModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { CalendarDate, parseDate } from '@internationalized/date';
import {
  CalendarIcon,
  MailIcon,
  Send as SendIcon,
  Eye as EyeIcon,
  FileText as FileTextIcon,
  MailCheck as MailCheckIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  ArrowLeft as ArrowLeftIcon,
} from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import SendEmailModal from '@/components/SendEmailModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
} from '@/components/ui/table';

const invoices = ref([]);
const loading = ref(false);
const showEmailModal = ref(false);
const selectedInvoice = ref(null);
const sendingId = ref(null);
const sendingAll = ref(false);
const sendingBatch = ref(false);
const selectedIds = ref([]);
const alertMessage = ref(null);
const alertSuccess = ref(true);

const emailAccounts = ref([]);
const batchSenderId = ref(null);

const selectedBatchSender = computed(() => {
  return emailAccounts.value.find(acc => acc.id === batchSenderId.value) || null;
});

const fetchEmailAccounts = async () => {
  try {
    const res = await axios.get('/api/email-accounts');
    emailAccounts.value = res.data.data || [];
    if (!batchSenderId.value && emailAccounts.value.length > 0) {
      const def = emailAccounts.value.find(a => a.is_default);
      batchSenderId.value = def ? def.id : emailAccounts.value[0].id;
    }
  } catch (err) {
    console.error('Failed to fetch email accounts', err);
  }
};

const googleStatus = reactive({
  is_configured: false,
  is_connected: false,
  account_email: null,
});

const fetchGoogleStatus = async () => {
  try {
    const res = await axios.get('/api/google/status');
    Object.assign(googleStatus, res.data);
  } catch (err) {
    console.error('Failed to fetch Google status', err);
  }
};

const confirmState = reactive({
  show: false,
  title: '',
  message: '',
  confirmText: 'Ya, Kirim Sekarang',
  loading: false,
  showSenderSelect: false,
  action: null,
});

const onConfirmAction = async () => {
  if (confirmState.action) {
    await confirmState.action();
  }
};

const isFullySent = (inv) => {
  return !!(inv.email_sent_at && inv.whatsapp_sent_at);
};

const isAlreadySent = (inv) => {
  return inv.status === 'sent' || (!!inv.email_sent_at && !!inv.whatsapp_sent_at);
};

const hasDestination = (inv) => {
  return !!(inv.email || inv.draft?.email || inv.whatsapp || inv.draft?.whatsapp);
};

const selectableInvoices = computed(() => {
  return invoices.value.filter(inv => hasDestination(inv) && !isAlreadySent(inv));
});

const isAllSelected = computed(() => {
  return selectableInvoices.value.length > 0 &&
    selectableInvoices.value.every(inv => selectedIds.value.includes(inv.id));
});

const isIndeterminate = computed(() => {
  const selectedCount = selectableInvoices.value.filter(inv => selectedIds.value.includes(inv.id)).length;
  return selectedCount > 0 && selectedCount < selectableInvoices.value.length;
});

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    const newIds = new Set([...selectedIds.value, ...selectableInvoices.value.map(i => i.id)]);
    selectedIds.value = Array.from(newIds);
  } else {
    const selectableIdSet = new Set(selectableInvoices.value.map(i => i.id));
    selectedIds.value = selectedIds.value.filter(id => !selectableIdSet.has(id));
  }
};

const openEmailModal = (inv) => {
  if (isAlreadySent(inv)) return;
  selectedInvoice.value = inv;
  showEmailModal.value = true;
};

const onEmailSent = (payload) => {
  alertSuccess.value = true;
  alertMessage.value = `Notifikasi invoice berhasil diproses dan dikirim.`;
  const inv = invoices.value.find(i => i.id === payload.invoiceId);
  if (inv) {
    inv.status = 'sent';
    if (payload.email) inv.email_sent_at = new Date().toISOString();
    if (payload.whatsapp) inv.whatsapp_sent_at = new Date().toISOString();
  }
  selectedIds.value = selectedIds.value.filter(id => id !== payload.invoiceId);
};

const askSendSingle = (inv) => {
  if (isAlreadySent(inv)) return;
  const targetEmail = inv.email || inv.draft?.email;
  const targetWa = inv.whatsapp || inv.draft?.whatsapp;
  const destinations = [];
  if (targetEmail) destinations.push(`Email (${targetEmail})`);
  if (targetWa) destinations.push(`WhatsApp (${targetWa})`);

  confirmState.title = 'Kirim Notifikasi Invoice';
  confirmState.message = `Apakah Anda yakin ingin mengirim invoice ${inv.invoice_number} ke ${destinations.join(' & ')}?`;
  confirmState.confirmText = 'Ya, Kirim Sekarang';
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await quickSendInvoice(inv);
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const quickSendInvoice = async (inv) => {
  if (sendingId.value || isAlreadySent(inv)) return;
  sendingId.value = inv.id;
  alertMessage.value = null;

  try {
    const res = await axios.post(`/api/invoices/${inv.id}/quick-send-email`);
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    inv.status = 'sent';
    if (inv.email || inv.draft?.email) inv.email_sent_at = new Date().toISOString();
    if (inv.whatsapp || inv.draft?.whatsapp) inv.whatsapp_sent_at = new Date().toISOString();
    selectedIds.value = selectedIds.value.filter(id => id !== inv.id);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal mengirim notifikasi invoice';
  } finally {
    sendingId.value = null;
  }
};

const askSendBatch = () => {
  if (selectedIds.value.length === 0 || sendingBatch.value) return;
  confirmState.title = 'Kirim Batch Notifikasi (Email & WA)';
  confirmState.message = `Apakah Anda yakin ingin mengirim ${selectedIds.value.length} invoice terpilih ke alamat Email dan WhatsApp tujuan masing-masing?`;
  confirmState.confirmText = `Ya, Kirim (${selectedIds.value.length}) Invoice`;
  confirmState.showSenderSelect = true;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await executeSendBatch();
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const executeSendBatch = async () => {
  sendingBatch.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/send-batch', {
      ids: selectedIds.value,
      sender_id: batchSenderId.value,
    });
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    selectedIds.value = [];
    fetchInvoices(pagination.current_page);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal memproses pengiriman batch.';
  } finally {
    sendingBatch.value = false;
  }
};

const askSendAll = () => {
  if (sendingAll.value) return;
  confirmState.title = 'Kirim Semua Notifikasi Invoice';
  confirmState.message = 'Apakah Anda yakin ingin mengirim semua invoice yang belum terkirim via Email dan WhatsApp?';
  confirmState.confirmText = 'Ya, Kirim Semua';
  confirmState.showSenderSelect = true;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await executeSendAll();
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const executeSendAll = async () => {
  sendingAll.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/quick-send-all', {
      sender_id: batchSenderId.value,
    });
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    selectedIds.value = [];
    fetchInvoices(pagination.current_page);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal memproses pengiriman massal';
  } finally {
    sendingAll.value = false;
  }
};

const filters = reactive({
  search: '',
  invoice_type: '',
  status: '',
  date: '',
});

const selectedCalendarDate = computed(() => {
  if (!filters.date) return undefined;
  try { return parseDate(filters.date); } catch { return undefined; }
});

const onDateSelect = (val) => {
  if (!val) { filters.date = ''; fetchInvoices(1); return; }
  filters.date = val.toString();
  fetchInvoices(1);
};

const formatDateDisplay = (dateStr) => {
  if (!dateStr) return '';
  try {
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateStr));
  } catch { return dateStr; }
};

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

const totalNetpay = computed(() => {
  return invoices.value.reduce((acc, i) => acc + (Number(i.netpay) || 0), 0);
});

let debounceTimeout = null;
const debounceFetch = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchInvoices(1);
  }, 300);
};

const fetchInvoices = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      ...filters,
    };
    const res = await axios.get('/api/invoices', { params });
    invoices.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.per_page = res.data.per_page;
    pagination.total = res.data.total;
  } catch (err) {
    console.error('Error fetching invoices', err);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.invoice_type = '';
  filters.status = '';
  filters.date = '';
  fetchInvoices(1);
};

const formatCurrency = (val) => {
  const num = Math.round(Number(val) || 0);
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
};

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('google_connected')) {
    alertSuccess.value = true;
    alertMessage.value = `Akun Google Workspace (${urlParams.get('account') || ''}) berhasil terhubung!`;
    window.history.replaceState({}, document.title, window.location.pathname);
  } else if (urlParams.get('google_error')) {
    alertSuccess.value = false;
    alertMessage.value = `Gagal menghubungkan akun Google: ${urlParams.get('google_error')}`;
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  fetchInvoices(1);
  fetchGoogleStatus();
  fetchEmailAccounts();
});
</script>
