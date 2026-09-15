<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Invoices</h1>
        <p class="text-sm text-gray-500">Daftar invoice resmi yang telah diterbitkan.</p>
      </div>
      <div class="flex items-center gap-2.5">
        <!-- Multi-select Send Button -->
        <button
          v-if="selectedIds.length > 0"
          @click="askSendBatch"
          :disabled="sendingBatch"
          class="h-10 px-4 bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition cursor-pointer flex items-center gap-2 shadow-xs"
          :title="`Kirim ${selectedIds.length} invoice terpilih`"
        >
          <svg v-if="sendingBatch" class="animate-spin -ml-0.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <SendIcon v-else class="w-4 h-4 text-white" />
          <span>{{ sendingBatch ? 'Mengirim...' : `Kirim (${selectedIds.length}) Notifikasi Terpilih` }}</span>
        </button>

        <!-- Send All Button (when nothing specifically selected) -->
        <button
          v-else
          @click="askSendAll"
          :disabled="sendingAll"
          class="h-10 px-4 bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition cursor-pointer flex items-center gap-2 shadow-xs"
          title="Kirim semua invoice yang belum terkirim via Email & WhatsApp"
        >
          <svg v-if="sendingAll" class="animate-spin -ml-0.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <SendIcon v-else class="w-4 h-4 text-white" />
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
          class="h-9 w-64 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
        />
        <select
          v-model="filters.invoice_type"
          @change="fetchInvoices(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black"
        >
          <option value="">Semua Tipe</option>
          <option value="DSA">DSA</option>
          <option value="NPS FL">NPS FL</option>
          <option value="REGULAR">REGULAR</option>
        </select>
        <select
          v-model="filters.status"
          @change="fetchInvoices(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black"
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
                'h-9 px-3 inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-black transition',
                !filters.date && 'text-gray-400'
              ]"
            >
              <CalendarIcon class="h-4 w-4" />
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
          class="h-9 px-3 text-sm text-gray-500 hover:text-black cursor-pointer"
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
            <TableHead class="w-[160px]">Invoice</TableHead>
            <TableHead class="w-[110px]">Status</TableHead>
            <TableHead class="w-[90px]">Method</TableHead>
            <TableHead>Dealer</TableHead>
            <TableHead>Customer</TableHead>
            <TableHead>Email</TableHead>
            <TableHead>WhatsApp</TableHead>
            <TableHead>Program</TableHead>
            <TableHead class="w-[110px]">Tanggal</TableHead>
            <TableHead class="text-right w-[140px]">Amount</TableHead>
            <TableHead class="text-right w-[270px]">Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableEmpty v-if="loading" :colspan="12">
            Memuat data invoice...
          </TableEmpty>
          <TableEmpty v-else-if="invoices.length === 0" :colspan="12">
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
            <TableCell>
              <router-link :to="`/invoices/${inv.id}`" class="hover:underline font-medium">
                {{ inv.invoice_number }}
              </router-link>
            </TableCell>
            <!-- Status Badge -->
            <TableCell>
              <!-- Fully Sent: Email & WhatsApp -->
              <span
                v-if="isFullySent(inv)"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs whitespace-nowrap"
                title="Email & WhatsApp sudah terkirim"
              >
                <CheckCircleIcon class="h-3 w-3 text-emerald-600 shrink-0" />
                <span>Email & WA</span>
              </span>

              <!-- Email Sent Only -->
              <span
                v-else-if="inv.email_sent_at && !inv.whatsapp_sent_at"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs whitespace-nowrap"
                title="Email sudah terkirim"
              >
                <CheckCircleIcon class="h-3 w-3 text-emerald-600 shrink-0" />
                <span>Email Sent</span>
              </span>

              <!-- WhatsApp Sent Only -->
              <span
                v-else-if="!inv.email_sent_at && inv.whatsapp_sent_at"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs whitespace-nowrap"
                title="WhatsApp sudah terkirim"
              >
                <CheckCircleIcon class="h-3 w-3 text-emerald-600 shrink-0" />
                <span>WA Sent</span>
              </span>

              <!-- Sent / Terkirim Generic -->
              <span
                v-else-if="inv.status === 'sent'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs whitespace-nowrap"
              >
                <CheckCircleIcon class="h-3 w-3 text-emerald-600 shrink-0" />
                <span>Terkirim</span>
              </span>

              <!-- Generated -->
              <span
                v-else-if="inv.status === 'generated'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 shadow-2xs whitespace-nowrap"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500 shrink-0"></span>
                <span>Generated</span>
              </span>

              <!-- Paid -->
              <span
                v-else-if="inv.status === 'paid'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200 shadow-2xs whitespace-nowrap"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-purple-500 shrink-0"></span>
                <span>Paid</span>
              </span>

              <!-- Failed / Gagal -->
              <span
                v-else-if="inv.status === 'failed'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200 shadow-2xs whitespace-nowrap"
              >
                <AlertCircleIcon class="h-3 w-3 text-rose-500 shrink-0" />
                <span>Gagal</span>
              </span>

              <!-- Fallback -->
              <span
                v-else
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200 shadow-2xs capitalize whitespace-nowrap"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-gray-400 shrink-0"></span>
                <span>{{ inv.status }}</span>
              </span>
            </TableCell>
            <TableCell>
              {{ inv.invoice_type }}
            </TableCell>
            <TableCell class="max-w-[180px] truncate" :title="`${inv.dealer_code} - ${inv.dealer_name}`">
              {{ inv.dealer_code }} - {{ inv.dealer_name }}
            </TableCell>
            <TableCell class="max-w-[160px] truncate" :title="inv.customer_name">
              {{ inv.customer_name || '-' }}
            </TableCell>
            <!-- Email -->
            <TableCell class="max-w-[150px] truncate" :title="inv.email || inv.draft?.email">
              {{ inv.email || inv.draft?.email || '-' }}
            </TableCell>
            <!-- WhatsApp -->
            <TableCell class="max-w-[140px] truncate" :title="inv.whatsapp || inv.draft?.whatsapp">
              {{ inv.whatsapp || inv.draft?.whatsapp || '-' }}
            </TableCell>
            <TableCell class="max-w-[150px] truncate" :title="inv.program_name">
              {{ inv.program_name || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap">
              {{ inv.invoice_date || '-' }}
            </TableCell>
            <TableCell class="text-right font-medium text-gray-900 whitespace-nowrap">
              {{ formatCurrency(inv.netpay) }}
            </TableCell>

            <!-- Modern Action Buttons With Pixel-Perfect Alignment -->
            <TableCell class="text-right w-[270px] whitespace-nowrap">
              <div class="flex items-center justify-end gap-2">
                <!-- 1. View / Detail Icon -->
                <router-link
                  :to="`/invoices/${inv.id}`"
                  class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer shadow-2xs"
                  title="Lihat Detail Invoice"
                >
                  <EyeIcon class="w-4 h-4 text-gray-600" />
                </router-link>

                <!-- 2. Print / Preview Icon -->
                <a
                  :href="`/invoices/${inv.id}/preview`"
                  target="_blank"
                  class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer shadow-2xs"
                  title="Cetak / Preview Invoice"
                >
                  <PrinterIcon class="w-4 h-4 text-gray-600" />
                </a>

                <!-- 3. PDF Download Button (Fixed Width) -->
                <a
                  :href="`/invoices/${inv.id}/pdf`"
                  class="h-9 w-[74px] shrink-0 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 transition cursor-pointer shadow-2xs"
                  title="Unduh PDF Invoice"
                >
                  <svg class="w-4 h-4 text-red-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 2.5h7l5 5v13.5a1 1 0 01-1 1H7a1 1 0 01-1-1V3.5a1 1 0 011-1z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2.5v5h5" />
                    <text x="7" y="16.5" font-size="6.5" font-family="sans-serif" font-weight="bold" fill="currentColor" stroke="none">PDF</text>
                  </svg>
                  <span class="text-xs font-bold text-red-500 tracking-wide">PDF</span>
                </a>

                <!-- 4. Email/WA Action Button (Fixed Width 94px for 100% straight vertical column) -->
                <!-- Already Sent to both or completed -->
                <span
                  v-if="isAlreadySent(inv)"
                  class="h-9 w-[94px] shrink-0 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-lg inline-flex items-center justify-center gap-1.5 cursor-default select-none shadow-2xs"
                  title="Invoice ini sudah terkirim"
                >
                  <CheckCircleIcon class="w-4 h-4 text-emerald-600 shrink-0" />
                  <span>Terkirim</span>
                </span>

                <!-- Ready to send (has email or whatsapp): Clean Blue Kirim button -->
                <button
                  v-else-if="hasDestination(inv)"
                  @click="askSendSingle(inv)"
                  :disabled="sendingId === inv.id"
                  class="h-9 w-[94px] shrink-0 bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition inline-flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs disabled:opacity-50"
                  :title="`Kirim notifikasi ke ${inv.email || inv.draft?.email || ''} ${inv.whatsapp || inv.draft?.whatsapp ? '(' + (inv.whatsapp || inv.draft?.whatsapp) + ')' : ''}`"
                >
                  <span v-if="sendingId === inv.id" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  <SendIcon v-else class="w-4 h-4 text-white shrink-0" />
                  <span>Kirim</span>
                </button>

                <!-- No email/whatsapp: Manual Input Button -->
                <button
                  v-else
                  @click="openEmailModal(inv)"
                  class="h-9 w-[94px] shrink-0 border border-gray-200 bg-white text-gray-700 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50/50 text-xs font-medium rounded-lg transition inline-flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                  title="Input email / WhatsApp manual & kirim"
                >
                  <SendIcon class="w-4 h-4 text-gray-500 shrink-0" />
                  <span>Manual</span>
                </button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
        <TableFooter v-if="invoices.length > 0">
          <TableRow>
            <TableCell :colspan="10">
              Total
            </TableCell>
            <TableCell class="text-right">
              {{ formatCurrency(totalNetpay) }}
            </TableCell>
            <TableCell></TableCell>
          </TableRow>
        </TableFooter>
      </Table>
    </div>

    <!-- Pagination (Shadcn style) -->
    <div class="flex items-center justify-between py-2 text-sm text-gray-500">
      <div>
        Menampilkan {{ pagination.total > 0 ? (pagination.current_page - 1) * pagination.per_page + 1 : 0 }} sampai {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} dari {{ pagination.total }} invoice.
      </div>
      <div v-if="pagination.last_page > 1" class="flex items-center space-x-2">
        <button
          @click="fetchInvoices(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="h-8 px-3 rounded-md border border-gray-200 text-sm font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Previous
        </button>
        <span class="text-sm font-medium text-gray-700">
          Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
        </span>
        <button
          @click="fetchInvoices(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="h-8 px-3 rounded-md border border-gray-200 text-sm font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Send Email Modal -->
    <SendEmailModal
      v-model="showEmailModal"
      :invoice-id="selectedInvoice?.id"
      :invoice-number="selectedInvoice?.invoice_number"
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
    />
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
  Printer as PrinterIcon,
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

const confirmState = reactive({
  show: false,
  title: '',
  message: '',
  confirmText: 'Ya, Kirim Sekarang',
  loading: false,
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
    const res = await axios.post('/api/invoices/quick-send-all');
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
  fetchInvoices(1);
});
</script>
