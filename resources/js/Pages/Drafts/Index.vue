<template>
  <div class="space-y-4">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Drafts</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">List</span>
    </div>

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Draft Invoice</h1>
        <p class="text-xs text-gray-500 mt-0.5">Kelola dan terbitkan draft invoice yang diimpor dari Excel.</p>
      </div>

      <!-- Action Buttons (Filament style) -->
      <div class="flex items-center gap-2">
        <!-- Multi-select Generate Button -->
        <template v-if="selectedIds.length > 0">
          <button
            @click="askGenerateSelected"
            :disabled="generatingBatch"
            class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs disabled:opacity-50 transition cursor-pointer flex items-center gap-2"
            :title="`Terbitkan ${selectedIds.length} draft terpilih menjadi invoice`"
          >
            <svg v-if="generatingBatch" class="animate-spin -ml-0.5 h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <CheckSquareIcon v-else class="w-3.5 h-3.5 text-gray-500" />
            <span>{{ generatingBatch ? 'Menerbitkan...' : `Generate (${selectedIds.length}) Terpilih` }}</span>
          </button>
          <button
            @click="selectedIds = []"
            class="h-9 px-3 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg transition cursor-pointer shadow-2xs"
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
          class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs disabled:opacity-50 transition cursor-pointer flex items-center gap-2"
          title="Terbitkan invoice untuk semua draft yang berstatus Ready"
        >
          <svg v-if="generatingAll" class="animate-spin -ml-0.5 h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <CheckSquareIcon v-else class="w-3.5 h-3.5 text-gray-500" />
          <span>{{ generatingAll ? 'Menerbitkan...' : 'Generate Semua Invoice' }}</span>
        </button>

        <button
          @click="showImportModal = true"
          class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg transition cursor-pointer flex items-center gap-2 shadow-2xs"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-4 h-4 shrink-0">
            <path fill="#166e40" d="M37 6H17a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
            <path fill="#23a455" d="M37 6H24v36h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
            <path fill="#2ecc71" opacity=".35" d="M24 13h15v4H24zm0 7h15v4H24zm0 7h15v4H24z"/>
            <path fill="#107c41" d="M22 13H8a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V15a2 2 0 0 0-2-2z"/>
            <path fill="#ffffff" d="M12.4 28.5l2.4-4.8 2.4 4.8h2.3l-3.5-6.5 3.3-6.5h-2.3l-2.2 4.7-2.2-4.7h-2.3l3.3 6.5-3.5 6.5h2.3z"/>
          </svg>
          <span>Import Excel</span>
        </button>
      </div>
    </div>

    <!-- Alert -->
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
            {{ selectedIds.length }} draft terpilih
          </span>
          <span v-else class="text-gray-400 text-xs">
            Daftar draft invoice hasil import Excel
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

          <!-- Filter Popover with Active Badge Count (funnel with badge) -->
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
                <span>Filter Draft</span>
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
                  @change="fetchDrafts(1)"
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
                  @change="fetchDrafts(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Status</option>
                  <option value="ready">Ready</option>
                  <option value="error">Error</option>
                  <option value="invoiced">Invoiced</option>
                </select>
              </div>

              <!-- Date Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tanggal</label>
                <input
                  v-model="filters.date"
                  @change="fetchDrafts(1)"
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
                :disabled="selectableDrafts.length === 0"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                title="Pilih semua draft Ready di halaman ini"
              />
            </TableHead>
            <TableHead class="min-w-[120px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Dealer Code
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[160px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Dealer Name
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
            <TableHead class="text-right min-w-[120px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer justify-end w-full">
                Support
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="text-right min-w-[120px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer justify-end w-full">
                Netpay
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="text-center w-[90px] min-w-[90px]">
              <span>Action</span>
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <TableEmpty v-if="loading" :colspan="10">
            <div class="py-8 flex flex-col items-center justify-center gap-2 text-gray-500">
              <span class="inline-block w-5 h-5 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
              <span class="text-xs">Memuat data draft...</span>
            </div>
          </TableEmpty>

          <TableEmpty v-else-if="drafts.length === 0" :colspan="10">
            <div class="py-8 text-center text-gray-400 text-xs">
              Belum ada data draft. Silakan klik tombol <strong>Import Excel</strong>.
            </div>
          </TableEmpty>

          <TableRow
            v-for="draft in drafts"
            :key="draft.id"
            :class="selectedIds.includes(draft.id) ? 'bg-gray-50' : ''"
          >
            <!-- Checkbox Selection -->
            <TableCell class="w-[44px] text-center">
              <input
                type="checkbox"
                :value="draft.id"
                v-model="selectedIds"
                :disabled="draft.status !== 'ready'"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                :title="draft.status === 'ready' ? 'Pilih draft ini untuk digenerate' : (draft.status === 'invoiced' ? 'Invoice sudah diterbitkan' : 'Draft error tidak dapat digenerate')"
              />
            </TableCell>

            <!-- Dealer Code (Bold primary key like SJ-xxxx) -->
            <TableCell>
              <router-link
                :to="`/drafts/${draft.id}`"
                class="font-semibold text-gray-950 text-xs hover:underline"
              >
                {{ draft.dealer_code || '-' }}
              </router-link>
            </TableCell>

            <!-- Dealer Name -->
            <TableCell class="text-xs text-gray-800" :title="draft.dealer_name">
              {{ draft.dealer_name || '-' }}
            </TableCell>

            <!-- Customer -->
            <TableCell class="text-xs text-gray-600" :title="draft.customer_name">
              {{ draft.customer_name || '-' }}
            </TableCell>

            <!-- Tipe -->
            <TableCell class="text-xs text-gray-600">
              {{ draft.invoice_type || '-' }}
            </TableCell>

            <!-- Status (Plain text netral, seperti form program & invoice) -->
            <TableCell class="text-xs text-gray-700 whitespace-nowrap">
              <span>{{ draft.status === 'ready' ? 'READY' : (draft.status === 'invoiced' ? 'INVOICED' : (draft.status ? draft.status.toUpperCase() : 'ERROR')) }}</span>
            </TableCell>

            <!-- Tanggal -->
            <TableCell class="text-xs text-gray-600 whitespace-nowrap">
              {{ draft.invoice_date || '-' }}
            </TableCell>

            <!-- Support Amount -->
            <TableCell class="text-right text-xs text-gray-600 whitespace-nowrap">
              {{ formatCurrency(draft.support_amount) }}
            </TableCell>

            <!-- Netpay -->
            <TableCell class="text-right text-xs font-semibold text-gray-950 whitespace-nowrap">
              {{ formatCurrency(draft.netpay) }}
            </TableCell>

            <!-- Action Buttons: Toolbar Terpadu (Icon-only persis Form Program) -->
            <TableCell class="text-center py-2 whitespace-nowrap">
              <div class="inline-flex items-center rounded-md border border-gray-200 bg-white shadow-2xs divide-x divide-gray-200 overflow-hidden">
                <!-- 1. View Button -->
                <router-link
                  :to="`/drafts/${draft.id}`"
                  class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                  title="Lihat Detail Draft"
                >
                  <EyeIcon class="w-3.5 h-3.5 text-gray-600" />
                </router-link>

                <!-- 2. Generate Action (Ready) -->
                <button
                  v-if="draft.status === 'ready'"
                  type="button"
                  @click="askGenerateSingle(draft)"
                  :disabled="generatingId === draft.id"
                  class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer disabled:opacity-50"
                  title="Generate Invoice"
                >
                  <span v-if="generatingId === draft.id" class="inline-block w-3.5 h-3.5 border-2 border-gray-400 border-t-gray-900 rounded-full animate-spin shrink-0"></span>
                  <CheckSquareIcon v-else class="w-3.5 h-3.5 text-gray-600" />
                </button>

                <!-- 3. Validasi Action (Error) -->
                <button
                  v-else-if="draft.status === 'error'"
                  type="button"
                  @click="validateDraft(draft.id)"
                  class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                  title="Validasi Ulang Rumus Draft"
                >
                  <RefreshCwIcon class="w-3.5 h-3.5 text-gray-600" />
                </button>

                <!-- 4. Invoiced Action (Invoiced) -->
                <router-link
                  v-else-if="draft.status === 'invoiced' && draft.invoice"
                  :to="`/invoices/${draft.invoice.id}`"
                  class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                  :title="`Lihat Invoice (${draft.invoice.invoice_number})`"
                >
                  <FileTextIcon class="w-3.5 h-3.5 text-gray-600" />
                </router-link>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>

        <TableFooter v-if="drafts.length > 0">
          <TableRow>
            <TableCell :colspan="7" class="text-xs font-semibold text-gray-950">
              Total
            </TableCell>
            <TableCell class="text-right whitespace-nowrap text-xs text-gray-700">
              {{ formatCurrency(totalSupport) }}
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
        @page-change="fetchDrafts"
        @per-page-change="(p) => { pagination.per_page = p; fetchDrafts(1); }"
      />
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
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  Search as SearchIcon,
  Filter as FilterIcon,
  RefreshCw as RefreshCwIcon,
  FileText as FileTextIcon,
} from '@lucide/vue';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Alert, AlertDescription } from '@/components/ui/alert';
import ImportModal from '@/components/ImportModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import GenerateInvoiceModal from '@/components/GenerateInvoiceModal.vue';
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

const drafts = ref([]);
const loading = ref(false);
const showImportModal = ref(false);
const isFilterOpen = ref(false);
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
  per_page: 10,
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
      per_page: pagination.per_page,
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
  isFilterOpen.value = false;
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
