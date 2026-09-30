<template>
  <div class="space-y-4">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Invoices</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">List</span>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-950">Invoices</h1>
          <!-- Google Connection Status Badge -->
          <span
            v-if="googleStatus.is_connected"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-normal bg-gray-50 text-gray-600 border border-gray-200"
            :title="`Terhubung dengan akun Google: ${googleStatus.account_email}`"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span>Gmail: {{ googleStatus.account_email }}</span>
          </span>
          <a
            v-else
            href="/auth/google/redirect"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-normal bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 transition cursor-pointer"
            title="Klik untuk menghubungkan akun Google Workspace (Gmail API)"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
            <span>Hubungkan Google Workspace</span>
          </a>
        </div>
        <p class="text-xs text-gray-500 mt-0.5">Daftar invoice resmi yang telah diterbitkan.</p>
      </div>

      <!-- Filament Page Actions -->
      <div class="flex items-center gap-2">
        <!-- Multi-select Send Button -->
        <button
          v-if="selectedIds.length > 0"
          @click="askSendBatch"
          :disabled="sendingBatch"
          class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs disabled:opacity-50 transition cursor-pointer flex items-center gap-2"
          :title="`Kirim ${selectedIds.length} invoice terpilih`"
        >
          <svg v-if="sendingBatch" class="animate-spin -ml-0.5 h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <SendIcon v-else class="w-3.5 h-3.5 text-gray-500" />
          <span>{{ sendingBatch ? 'Mengirim...' : `Kirim (${selectedIds.length}) Terpilih` }}</span>
        </button>

        <!-- Send All Button (when nothing specifically selected) -->
        <button
          v-else
          @click="askSendAll"
          :disabled="sendingAll"
          class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs disabled:opacity-50 transition cursor-pointer flex items-center gap-2"
          title="Kirim semua invoice yang belum terkirim via Email & WhatsApp"
        >
          <svg v-if="sendingAll" class="animate-spin -ml-0.5 h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <SendIcon v-else class="w-3.5 h-3.5 text-gray-500" />
          <span>{{ sendingAll ? 'Mengirim Semua...' : 'Kirim Semua Notifikasi' }}</span>
        </button>

        <router-link
          to="/drafts"
          class="h-9 px-3 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition inline-flex items-center gap-1.5 shadow-2xs"
        >
          <ArrowLeftIcon class="w-3.5 h-3.5 text-gray-500" />
          <span>Draft Invoice</span>
        </router-link>
      </div>
    </div>

    <!-- Alert Notification -->
    <Alert v-if="alertMessage" :variant="alertSuccess ? 'default' : 'destructive'">
      <CheckCircleIcon v-if="alertSuccess" class="h-4 w-4" />
      <AlertCircleIcon v-else class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ alertMessage }}</span>
        <button @click="alertMessage = null" class="ml-4 text-xs opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
      </AlertDescription>
    </Alert>

    <!-- Filament Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Toolbar (Search & Filter like Filament) -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Left: Quick Selection Info -->
        <div class="flex items-center gap-2 text-xs">
          <span v-if="selectedIds.length > 0" class="font-medium text-gray-900 bg-gray-100 px-2.5 py-1 rounded-md">
            {{ selectedIds.length }} invoice terpilih
          </span>
          <span v-else class="text-gray-400 text-xs">
            Daftar invoice resmi diterbitkan
          </span>
        </div>

        <!-- Right: Search Box & Filter Popover -->
        <div class="flex items-center gap-2 self-end sm:self-auto w-full sm:w-auto">
          <!-- Search box with Magnifying glass -->
          <div class="relative flex-1 sm:w-64">
            <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="filters.search"
              @input="debounceFetch"
              type="text"
              placeholder="Search"
              class="h-9 w-full pl-9 pr-3 text-xs text-gray-900 bg-white border border-gray-300 rounded-lg placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 transition"
            />
          </div>

          <!-- Filter Popover with Active Badge Count (like photo: funnel with badge) -->
          <Popover v-model:open="isFilterOpen">
            <PopoverTrigger as-child>
              <button
                type="button"
                :class="[
                  'h-9 px-2.5 rounded-lg border text-xs font-medium inline-flex items-center gap-1.5 transition shadow-2xs cursor-pointer',
                  activeFilterCount > 0
                    ? 'border-gray-900 bg-gray-50 text-gray-950'
                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                ]"
                title="Buka Filter"
              >
                <FilterIcon class="w-4 h-4 text-gray-600" />
                <span
                  :class="[
                    'w-4 h-4 rounded-full text-[10px] font-semibold flex items-center justify-center',
                    activeFilterCount > 0 ? 'bg-gray-950 text-white' : 'bg-gray-100 text-gray-600'
                  ]"
                >
                  {{ activeFilterCount }}
                </span>
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-72 p-3 space-y-3" align="end">
              <div class="text-xs font-semibold text-gray-900 border-b border-gray-100 pb-2 flex items-center justify-between">
                <span>Filter Invoices</span>
                <button
                  v-if="activeFilterCount > 0"
                  @click="resetFilters"
                  class="text-[11px] font-normal text-rose-600 hover:underline cursor-pointer"
                >
                  Reset
                </button>
              </div>

              <!-- Tipe Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tipe Invoice</label>
                <select
                  v-model="filters.invoice_type"
                  @change="fetchInvoices(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Tipe</option>
                  <option value="DSA">DSA</option>
                  <option value="NPS FL">NPS FL</option>
                  <option value="REGULAR">REGULAR</option>
                </select>
              </div>

              <!-- Status Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Status</label>
                <select
                  v-model="filters.status"
                  @change="fetchInvoices(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Status</option>
                  <option value="generated">Generated</option>
                  <option value="sent">Sent (Terkirim)</option>
                  <option value="paid">Paid</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>

              <!-- Date Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tanggal</label>
                <input
                  v-model="filters.date"
                  @change="fetchInvoices(1)"
                  type="date"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                />
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <!-- Filament Table -->
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead class="w-[44px] text-center">
              <input
                type="checkbox"
                :checked="isAllSelected"
                :indeterminate="isIndeterminate"
                @change="toggleSelectAll"
                :disabled="selectableInvoices.length === 0"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                title="Pilih semua yang belum dikirim di halaman ini"
              />
            </TableHead>
            <TableHead class="min-w-[150px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Nomor Invoice
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[170px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Dealer Tujuan
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[140px]">
              <span>Customer</span>
            </TableHead>
            <TableHead class="min-w-[100px]">
              <span>Tipe</span>
            </TableHead>
            <TableHead class="min-w-[110px]">
              <span>Status</span>
            </TableHead>
            <TableHead class="min-w-[120px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Tanggal
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="text-right min-w-[130px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer justify-end w-full">
                Total Netpay
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="text-right w-[210px] min-w-[210px]">
              <span>Action</span>
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <TableEmpty v-if="loading" :colspan="9">
            <div class="py-8 flex flex-col items-center justify-center gap-2 text-gray-500">
              <span class="inline-block w-5 h-5 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
              <span class="text-xs">Memuat data invoice...</span>
            </div>
          </TableEmpty>

          <TableEmpty v-else-if="invoices.length === 0" :colspan="9">
            <div class="py-8 text-center text-gray-400 text-xs">
              Belum ada invoice yang dibuat. Silakan generate invoice dari menu <strong>Draft</strong>.
            </div>
          </TableEmpty>

          <TableRow
            v-for="inv in invoices"
            :key="inv.id"
            :class="selectedIds.includes(inv.id) ? 'bg-gray-50' : ''"
          >
            <!-- Checkbox Selection -->
            <TableCell class="w-[44px] text-center">
              <input
                type="checkbox"
                :value="inv.id"
                v-model="selectedIds"
                :disabled="isAlreadySent(inv) || !hasDestination(inv)"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                :title="isAlreadySent(inv) ? 'Sudah dikirim' : (!hasDestination(inv) ? 'Tidak ada email atau nomor WhatsApp' : 'Pilih invoice ini')"
              />
            </TableCell>

            <!-- Nomor Invoice (Bold like SJ-xxxx) -->
            <TableCell>
              <router-link
                :to="`/invoices/${inv.id}`"
                class="text-gray-950 text-xs hover:underline"
              >
                {{ inv.invoice_number }}
              </router-link>
            </TableCell>

            <!-- Dealer Tujuan -->
            <TableCell class="text-xs text-gray-800" :title="`${inv.dealer_code} - ${inv.dealer_name}`">
              {{ inv.dealer_code }} - {{ inv.dealer_name }}
            </TableCell>

            <!-- Customer -->
            <TableCell class="text-xs text-gray-600" :title="inv.customer_name">
              {{ inv.customer_name || '-' }}
            </TableCell>

            <!-- Tipe -->
            <TableCell class="text-xs text-gray-600">
              {{ inv.invoice_type || '-' }}
            </TableCell>

            <!-- Status (Filament Pill Badge) -->
            <TableCell>
              <FilamentBadge
                :color="inv.status === 'sent' ? 'success' : (inv.status === 'paid' ? 'success' : 'info')"
              >
                {{ inv.status === 'sent' ? 'DELIVERED' : (inv.status || 'GENERATED') }}
              </FilamentBadge>
            </TableCell>

            <!-- Tanggal -->
            <TableCell class="text-xs text-gray-600 whitespace-nowrap">
              {{ inv.invoice_date || '-' }}
            </TableCell>

            <!-- Netpay -->
            <TableCell class="text-right text-xs text-gray-950 whitespace-nowrap">
              {{ formatCurrency(inv.netpay) }}
            </TableCell>

            <!-- Filament Action Buttons (View, PDF, Kirim/Sent) -->
            <TableCell class="text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-1.5">
                <!-- 1. View Button -->
                <router-link
                  :to="`/invoices/${inv.id}`"
                  class="h-7 px-2 inline-flex items-center justify-center gap-1 rounded-md border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium transition shadow-2xs cursor-pointer"
                  title="Lihat Detail Invoice"
                >
                  <EyeIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                  <span>View</span>
                </router-link>

                <!-- 2. PDF Link Button -->
                <a
                  :href="`/invoices/${inv.id}/pdf`"
                  target="_blank"
                  class="h-7 px-2 inline-flex items-center justify-center gap-1 rounded-md border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium transition shadow-2xs cursor-pointer"
                  title="Unduh PDF"
                >
                  <FileTextIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                  <span>PDF</span>
                </a>

                <!-- 3. Kirim / Sent Action -->
                <span
                  v-if="isAlreadySent(inv)"
                  class="h-7 w-[72px] inline-flex items-center justify-center gap-1 rounded-md border border-emerald-200/80 bg-emerald-50/70 text-emerald-700 text-xs font-medium transition shadow-2xs select-none"
                  title="Invoice ini sudah terkirim"
                >
                  <CheckCircleIcon class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                  <span>Sent</span>
                </span>
                <button
                  v-else
                  @click="openEmailModal(inv)"
                  class="h-7 w-[72px] inline-flex items-center justify-center gap-1 rounded-md border border-blue-200/80 bg-blue-50/70 hover:bg-blue-100 text-blue-700 text-xs font-medium transition shadow-2xs cursor-pointer"
                  title="Kirim Invoice via Email/WhatsApp"
                >
                  <SendIcon class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                  <span>Kirim</span>
                </button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>

        <TableFooter v-if="invoices.length > 0">
          <TableRow>
            <TableCell :colspan="7" class="text-xs font-semibold text-gray-950">
              Total Netpay
            </TableCell>
            <TableCell class="text-right whitespace-nowrap text-xs font-bold text-gray-950">
              {{ formatCurrency(totalNetpay) }}
            </TableCell>
            <TableCell></TableCell>
          </TableRow>
        </TableFooter>
      </Table>

      <!-- Filament Pagination Footer -->
      <FilamentPagination
        :total="pagination.total"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        @page-change="fetchInvoices"
        @per-page-change="(p) => { pagination.per_page = p; fetchInvoices(1); }"
      />
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
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  Search as SearchIcon,
  Filter as FilterIcon,
} from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import SendEmailModal from '@/components/SendEmailModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import FilamentBadge from '@/components/ui/FilamentBadge.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
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
const isFilterOpen = ref(false);

const defaultFallbackAccounts = [
  { id: 1, name: 'Rebate. MSI', email: 'ade@mediaselulerindonesia.com', is_default: true },
  { id: 2, name: 'Program CS', email: 'admin.scm@completeselular.com', is_default: false },
  { id: 3, name: 'Program MSI', email: 'admin.scm@mediaselulerindonesia.com', is_default: false },
  { id: 4, name: 'Program SMI', email: 'admin.scm@satumediaindonesia.com', is_default: false },
  { id: 5, name: 'Program Top', email: 'admin.scm@topselular.com', is_default: false },
];

const emailAccounts = ref([...defaultFallbackAccounts]);
const batchSenderId = ref(1);

const selectedBatchSender = computed(() => {
  return emailAccounts.value.find(acc => acc.id === batchSenderId.value) || emailAccounts.value[0] || null;
});

const fetchEmailAccounts = async () => {
  try {
    const res = await axios.get('/api/email-accounts');
    if (Array.isArray(res.data.data) && res.data.data.length > 0) {
      emailAccounts.value = res.data.data;
      if (!batchSenderId.value || !emailAccounts.value.some(a => a.id === batchSenderId.value)) {
        const def = emailAccounts.value.find(a => a.is_default);
        batchSenderId.value = def ? def.id : emailAccounts.value[0].id;
      }
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

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.invoice_type) count++;
  if (filters.status) count++;
  if (filters.date) count++;
  return count;
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
  per_page: 10,
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
      per_page: pagination.per_page,
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
  isFilterOpen.value = false;
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
