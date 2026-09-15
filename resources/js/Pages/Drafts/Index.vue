<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Draft Invoice</h1>
        <p class="text-sm text-gray-500">Kelola dan terbitkan draft invoice yang diimpor dari Excel.</p>
      </div>
      <div class="flex items-center space-x-2">
        <!-- Multi-select Generate Button -->
        <template v-if="selectedIds.length > 0">
          <button
            @click="askGenerateSelected"
            :disabled="generatingBatch"
            class="h-9 px-3.5 bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium rounded-md disabled:opacity-50 transition cursor-pointer flex items-center space-x-1.5 shadow-xs"
            :title="`Terbitkan ${selectedIds.length} draft terpilih menjadi invoice`"
          >
            <svg v-if="generatingBatch" class="animate-spin -ml-0.5 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <CheckSquareIcon v-else class="w-4 h-4 text-white" />
            <span>{{ generatingBatch ? 'Menerbitkan...' : `Generate (${selectedIds.length}) Invoice Terpilih` }}</span>
          </button>
          <button
            @click="selectedIds = []"
            class="h-9 px-3 border border-gray-200 bg-white hover:bg-gray-100 text-gray-700 text-sm font-medium rounded-md transition cursor-pointer flex items-center gap-1 shadow-xs"
            title="Batalkan pilihan"
          >
            <span>Batal Pilih</span>
          </button>
        </template>

        <!-- Generate All Invoices (when none selected) -->
        <button
          v-else
          @click="askGenerateAll"
          :disabled="generatingAll"
          class="h-9 px-3.5 bg-[#1D70F5] text-white text-sm font-medium rounded-md hover:bg-blue-600 disabled:opacity-50 transition cursor-pointer flex items-center space-x-1.5 shadow-xs"
          title="Terbitkan invoice untuk semua draft yang berstatus Ready"
        >
          <svg v-if="generatingAll" class="animate-spin -ml-0.5 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>{{ generatingAll ? 'Menerbitkan Semua...' : 'Generate Semua Invoice' }}</span>
        </button>

        <button
          @click="showImportModal = true"
          class="h-9 px-3.5 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-lg transition cursor-pointer flex items-center gap-2 shadow-xs"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-4 h-4 shrink-0">
            <path fill="#166e40" d="M37 6H17a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
            <path fill="#23a455" d="M37 6H24v36h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
            <path fill="#2ecc71" opacity=".35" d="M24 13h15v4H24zm0 7h15v4H24zm0 7h15v4H24zm0 7h15v4H24z"/>
            <path fill="#107c41" d="M22 13H8a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V15a2 2 0 0 0-2-2z"/>
            <path fill="#ffffff" d="M12.4 28.5l2.4-4.8 2.4 4.8h2.3l-3.5-6.5 3.3-6.5h-2.3l-2.2 4.7-2.2-4.7h-2.3l3.3 6.5-3.5 6.5h2.3z"/>
          </svg>
          <span>Import Excel</span>
        </button>
      </div>
    </div>

    <!-- Toolbar Filters (Shadcn style) -->
    <div class="flex flex-wrap items-center justify-between gap-3 py-1">
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="filters.search"
          @input="debounceFetch"
          type="text"
          placeholder="Filter dealer, customer, CN..."
          class="h-9 w-64 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
        />
        <select
          v-model="filters.invoice_type"
          @change="fetchDrafts(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black"
        >
          <option value="">Semua Tipe</option>
          <option value="DSA">DSA</option>
          <option value="NPS FL">NPS FL</option>
          <option value="REGULAR">REGULAR</option>
        </select>
        <select
          v-model="filters.status"
          @change="fetchDrafts(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black"
        >
          <option value="">Semua Status</option>
          <option value="ready">Ready</option>
          <option value="error">Error</option>
          <option value="invoiced">Invoiced</option>
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

    <!-- Alert -->
    <Alert v-if="alertMessage" :variant="alertSuccess ? 'default' : 'destructive'">
      <CheckCircleIcon v-if="alertSuccess" class="h-4 w-4" />
      <AlertCircleIcon v-else class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ alertMessage }}</span>
        <button @click="alertMessage = null" class="ml-4 text-sm opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
      </AlertDescription>
    </Alert>

    <!-- Official Shadcn-Vue Table Card (All uniform font) -->
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
                :disabled="selectableDrafts.length === 0"
                class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                title="Pilih semua draft Ready di halaman ini"
              />
            </TableHead>
            <TableHead class="w-[95px] whitespace-nowrap">Dealer Code</TableHead>
            <TableHead class="min-w-[140px]">Dealer Name</TableHead>
            <TableHead class="min-w-[130px]">Customer</TableHead>
            <TableHead class="min-w-[130px]">Email</TableHead>
            <TableHead class="w-[105px] whitespace-nowrap">WhatsApp</TableHead>
            <TableHead class="w-[95px] whitespace-nowrap">Tanggal</TableHead>
            <TableHead class="w-[85px] whitespace-nowrap">Status</TableHead>
            <TableHead class="text-right w-[110px] whitespace-nowrap">Support</TableHead>
            <TableHead class="text-right w-[110px] whitespace-nowrap">Netpay</TableHead>
            <TableHead class="text-right w-[115px] whitespace-nowrap">Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableEmpty v-if="loading" :colspan="11">
            Memuat data draft...
          </TableEmpty>
          <TableEmpty v-else-if="drafts.length === 0" :colspan="11">
            Belum ada data draft. Silakan klik tombol <strong>+ Import Excel</strong>.
          </TableEmpty>
          <TableRow
            v-for="draft in drafts"
            :key="draft.id"
            :class="selectedIds.includes(draft.id) ? 'bg-gray-50 hover:bg-gray-100/70' : ''"
          >
            <!-- Checkbox Selection -->
            <TableCell class="w-[40px] text-center">
              <input
                type="checkbox"
                :value="draft.id"
                v-model="selectedIds"
                :disabled="draft.status !== 'ready'"
                class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                :title="draft.status === 'ready' ? 'Pilih draft ini untuk digenerate' : (draft.status === 'invoiced' ? 'Invoice sudah diterbitkan' : 'Draft error tidak dapat digenerate')"
              />
            </TableCell>
            <TableCell class="whitespace-nowrap text-xs font-medium text-gray-900">
              {{ draft.dealer_code || '-' }}
            </TableCell>
            <TableCell class="max-w-[170px] truncate text-xs" :title="draft.dealer_name">
              {{ draft.dealer_name || '-' }}
            </TableCell>
            <TableCell class="max-w-[150px] truncate text-xs" :title="draft.customer_name">
              {{ draft.customer_name || '-' }}
            </TableCell>
            <TableCell class="max-w-[140px] truncate text-xs" :title="draft.email">
              {{ draft.email || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap text-xs">
              {{ draft.whatsapp || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap text-xs">
              {{ draft.invoice_date || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap">
              <!-- Ready (Clean Neutral) -->
              <span
                v-if="draft.status === 'ready'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200 shadow-2xs whitespace-nowrap"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-gray-400 shrink-0"></span>
                <span>Ready</span>
              </span>

              <!-- Invoiced -->
              <span
                v-else-if="draft.status === 'invoiced'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs whitespace-nowrap"
              >
                <CheckCircleIcon class="h-3 w-3 text-emerald-600 shrink-0" />
                <span>Invoiced</span>
              </span>

              <!-- Error -->
              <span
                v-else-if="draft.status === 'error'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 shadow-2xs whitespace-nowrap"
              >
                <AlertCircleIcon class="h-3 w-3 text-amber-600 shrink-0" />
                <span>Error</span>
              </span>

              <!-- Fallback -->
              <span
                v-else
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200 shadow-2xs capitalize whitespace-nowrap"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-gray-400 shrink-0"></span>
                <span>{{ draft.status }}</span>
              </span>
            </TableCell>
            <TableCell class="text-right whitespace-nowrap text-xs">
              {{ formatCurrency(draft.support_amount) }}
            </TableCell>
            <TableCell class="text-right whitespace-nowrap text-xs">
              {{ formatCurrency(draft.netpay) }}
            </TableCell>
            <TableCell class="text-right">
              <div class="flex items-center justify-end space-x-1.5">
                <!-- View Icon Button -->
                <router-link
                  :to="`/drafts/${draft.id}`"
                  class="w-7 h-7 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:text-black hover:bg-gray-100 transition cursor-pointer"
                  title="Lihat Detail Draft"
                >
                  <EyeIcon class="w-3.5 h-3.5" />
                </router-link>

                <!-- Generate Button (No Icon, Brand Blue) -->
                <button
                  v-if="draft.status === 'ready'"
                  @click="askGenerateSingle(draft)"
                  :disabled="generatingId === draft.id"
                  class="h-7 px-3 bg-[#1D70F5] text-white text-xs font-medium rounded-md hover:bg-blue-600 disabled:opacity-50 transition cursor-pointer shadow-xs"
                  title="Generate Invoice"
                >
                  <span v-if="generatingId === draft.id" class="inline-block w-3 h-3 mr-1 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  <span>{{ generatingId === draft.id ? 'Membuat...' : 'Generate' }}</span>
                </button>

                <!-- Validate Icon Button -->
                <button
                  v-else-if="draft.status === 'error'"
                  @click="validateDraft(draft.id)"
                  class="h-7 px-2.5 border border-amber-300 bg-amber-50 text-amber-800 text-xs font-medium rounded-md hover:bg-amber-100 transition inline-flex items-center gap-1 cursor-pointer"
                  title="Validasi Ulang Rumus Draft"
                >
                  <CheckCircleIcon class="w-3.5 h-3.5 text-amber-600" />
                  <span>Validasi</span>
                </button>

                <!-- Invoiced Status Link -->
                <router-link
                  v-else-if="draft.status === 'invoiced' && draft.invoice"
                  :to="`/invoices/${draft.invoice.id}`"
                  class="h-7 px-2 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-medium rounded-md inline-flex items-center gap-1 hover:bg-emerald-100 transition"
                  title="Lihat Invoice yang Sudah Dibuat"
                >
                  <CheckCircleIcon class="w-3 h-3 text-emerald-600" />
                  <span>Invoice</span>
                </router-link>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
        <TableFooter v-if="drafts.length > 0">
          <TableRow>
            <TableCell :colspan="8" class="font-medium text-xs">
              Total
            </TableCell>
            <TableCell class="text-right whitespace-nowrap font-medium text-xs">
              {{ formatCurrency(totalSupport) }}
            </TableCell>
            <TableCell class="text-right whitespace-nowrap font-medium text-xs">
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
        Menampilkan {{ pagination.total > 0 ? (pagination.current_page - 1) * pagination.per_page + 1 : 0 }} sampai {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} dari {{ pagination.total }} draft.
      </div>
      <div v-if="pagination.last_page > 1" class="flex items-center space-x-2">
        <button
          @click="fetchDrafts(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="h-8 px-3 rounded-md border border-gray-200 text-sm font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Previous
        </button>
        <span class="text-sm font-medium text-gray-700">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>
        <button
          @click="fetchDrafts(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="h-8 px-3 rounded-md border border-gray-200 text-sm font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Import Modal -->
    <ImportModal
      :is-open="showImportModal"
      @close="showImportModal = false"
      @imported="fetchDrafts(1)"
    />

    <!-- Confirmation Modal -->
    <ConfirmModal
      v-model="confirmState.show"
      :title="confirmState.title"
      :message="confirmState.message"
      :confirm-text="confirmState.confirmText"
      :loading="confirmState.loading"
      icon="warning"
      @confirm="onConfirmAction"
    />

    <!-- Generate Invoice Modal with Bill To Selection -->
    <GenerateInvoiceModal
      v-model="generateModalState.show"
      :title="generateModalState.title"
      :subtitle="generateModalState.subtitle"
      :target-info="generateModalState.targetInfo"
      :confirm-text="generateModalState.confirmText"
      :loading="generateModalState.loading"
      @confirm="onGenerateConfirm"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { parseDate } from '@internationalized/date';
import {
  CalendarIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  Eye as EyeIcon,
  CheckSquare as CheckSquareIcon,
} from '@lucide/vue';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Alert, AlertDescription } from '@/components/ui/alert';
import ImportModal from '@/components/ImportModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import GenerateInvoiceModal from '@/components/GenerateInvoiceModal.vue';
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

const drafts = ref([]);
const loading = ref(false);
const showImportModal = ref(false);
const selectedIds = ref([]);
const generatingId = ref(null);
const generatingBatch = ref(false);
const generatingAll = ref(false);
const alertMessage = ref(null);
const alertSuccess = ref(true);

const confirmState = reactive({
  show: false,
  title: '',
  message: '',
  confirmText: '',
  loading: false,
  action: null,
});

const onConfirmAction = async () => {
  if (confirmState.action) {
    await confirmState.action();
  }
};

const generateModalState = reactive({
  show: false,
  title: 'Terbitkan Invoice',
  subtitle: '',
  targetInfo: '',
  confirmText: 'Ya, Terbitkan Invoice',
  loading: false,
  action: null,
});

const onGenerateConfirm = async (billTo) => {
  if (generateModalState.action) {
    await generateModalState.action(billTo);
  }
};

const selectableDrafts = computed(() => {
  return drafts.value.filter(d => d.status === 'ready');
});

const isAllSelected = computed(() => {
  return selectableDrafts.value.length > 0 &&
    selectableDrafts.value.every(d => selectedIds.value.includes(d.id));
});

const isIndeterminate = computed(() => {
  const selectedCount = selectableDrafts.value.filter(d => selectedIds.value.includes(d.id)).length;
  return selectedCount > 0 && selectedCount < selectableDrafts.value.length;
});

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    const newIds = new Set([...selectedIds.value, ...selectableDrafts.value.map(d => d.id)]);
    selectedIds.value = Array.from(newIds);
  } else {
    const selectableIdSet = new Set(selectableDrafts.value.map(d => d.id));
    selectedIds.value = selectedIds.value.filter(id => !selectableIdSet.has(id));
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
  if (!val) { filters.date = ''; fetchDrafts(1); return; }
  filters.date = val.toString();
  fetchDrafts(1);
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

const totalSupport = computed(() => {
  return drafts.value.reduce((acc, d) => acc + (Number(d.support_amount) || 0), 0);
});

const totalNetpay = computed(() => {
  return drafts.value.reduce((acc, d) => acc + (Number(d.netpay) || 0), 0);
});

let debounceTimeout = null;
const debounceFetch = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchDrafts(1);
  }, 300);
};

const fetchDrafts = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      ...filters,
    };
    const res = await axios.get('/api/drafts', { params });
    drafts.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.per_page = res.data.per_page;
    pagination.total = res.data.total;
  } catch (err) {
    console.error('Error fetching drafts', err);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.invoice_type = '';
  filters.status = '';
  filters.date = '';
  fetchDrafts(1);
};

const validateDraft = async (id) => {
  try {
    const res = await axios.post(`/api/drafts/${id}/validate`);
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal validasi draft.';
    alertSuccess.value = false;
  }
};

const askGenerateSingle = (draft) => {
  if (generatingId.value === draft.id) return;
  generateModalState.title = 'Terbitkan Invoice';
  generateModalState.subtitle = 'Pilih pihak Bill To untuk dicantumkan pada lembar invoice:';
  generateModalState.targetInfo = `${draft.dealer_name || draft.dealer_code} (${draft.invoice_type || 'Invoice'})`;
  generateModalState.confirmText = 'Ya, Terbitkan Invoice';
  generateModalState.action = async (billTo) => {
    generateModalState.loading = true;
    try {
      await executeGenerateSingle(draft.id, billTo);
      generateModalState.show = false;
    } finally {
      generateModalState.loading = false;
    }
  };
  generateModalState.show = true;
};

const executeGenerateSingle = async (draftId, billTo) => {
  generatingId.value = draftId;
  alertMessage.value = null;

  try {
    const res = await axios.post(`/api/invoices/generate/${draftId}`, {
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
    selectedIds.value = selectedIds.value.filter(id => id !== draftId);
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal generate invoice.';
    alertSuccess.value = false;
  } finally {
    generatingId.value = null;
  }
};

const askGenerateSelected = () => {
  if (selectedIds.value.length === 0 || generatingBatch.value) return;
  generateModalState.title = 'Terbitkan Invoice Terpilih';
  generateModalState.subtitle = `Pilih pihak Bill To untuk ${selectedIds.value.length} invoice terpilih:`;
  generateModalState.targetInfo = `${selectedIds.value.length} Draft Terpilih`;
  generateModalState.confirmText = `Ya, Terbitkan (${selectedIds.value.length}) Invoice`;
  generateModalState.action = async (billTo) => {
    generateModalState.loading = true;
    try {
      await executeGenerateSelected(billTo);
      generateModalState.show = false;
    } finally {
      generateModalState.loading = false;
    }
  };
  generateModalState.show = true;
};

const executeGenerateSelected = async (billTo) => {
  generatingBatch.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/generate-all', {
      ids: selectedIds.value,
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = res.data.success;
    selectedIds.value = [];
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal menerbitkan invoice terpilih.';
    alertSuccess.value = false;
  } finally {
    generatingBatch.value = false;
  }
};

const askGenerateAll = () => {
  if (generatingAll.value) return;
  generateModalState.title = 'Terbitkan Semua Invoice';
  generateModalState.subtitle = 'Pilih pihak Bill To untuk SEMUA draft dengan status Ready:';
  generateModalState.targetInfo = 'Semua Draft (Status Ready)';
  generateModalState.confirmText = 'Ya, Terbitkan Semua';
  generateModalState.action = async (billTo) => {
    generateModalState.loading = true;
    try {
      await executeGenerateAll(billTo);
      generateModalState.show = false;
    } finally {
      generateModalState.loading = false;
    }
  };
  generateModalState.show = true;
};

const executeGenerateAll = async (billTo) => {
  generatingAll.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/generate-all', {
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = res.data.success;
    selectedIds.value = [];
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal menerbitkan semua invoice.';
    alertSuccess.value = false;
  } finally {
    generatingAll.value = false;
  }
};

const formatCurrency = (val) => {
  const num = Math.round(Number(val) || 0);
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
};

onMounted(() => {
  fetchDrafts(1);
});
</script>
