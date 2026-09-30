<template>
  <div class="space-y-4 font-sans">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500">
      <span>Riwayat</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800 font-medium">Riwayat Email</span>
    </div>

    <!-- Header & Page Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Riwayat Email</h1>
        <p class="text-xs text-gray-500 mt-0.5">Histori pengiriman invoice via email dan status pengiriman.</p>
      </div>

      <!-- Action Button (Filament dark button style) -->
      <div class="flex items-center gap-2">
        <router-link
          to="/invoices"
          class="h-9 px-3.5 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium rounded-lg shadow-2xs transition inline-flex items-center gap-2 cursor-pointer"
        >
          <MailIcon class="w-4 h-4 text-gray-500" />
          <span>Kirim Invoice Baru</span>
        </router-link>
      </div>
    </div>

    <!-- Filament Table Container Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Card Toolbar (Search & Filter like Filament) -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Left: Quick count / Selection Info -->
        <div class="flex items-center gap-2 text-xs text-gray-500">
          <span v-if="selectedIds.length > 0" class="font-medium text-gray-900 bg-gray-100 px-2.5 py-0.5 rounded-md">
            {{ selectedIds.length }} terpilih
          </span>
          <span v-else class="text-gray-400 text-xs">
            Daftar pengiriman email invoice
          </span>
        </div>

        <!-- Right: Search Input & Filter Popover -->
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
                <span>Filter Log Email</span>
                <button
                  v-if="activeFilterCount > 0"
                  @click="resetFilters"
                  class="text-xs font-normal text-rose-600 hover:underline cursor-pointer"
                >
                  Reset
                </button>
              </div>

              <!-- Status Filter -->
              <div class="space-y-1">
                <label class="text-xs font-medium text-gray-700">Status</label>
                <select
                  v-model="filters.status"
                  @change="fetchLogs(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Status</option>
                  <option value="sent">DELIVERED (Sent)</option>
                  <option value="failed">FAILED</option>
                </select>
              </div>

              <!-- Date Filter -->
              <div class="space-y-1">
                <label class="text-xs font-medium text-gray-700">Tanggal Pengiriman</label>
                <input
                  v-model="filters.date"
                  @change="fetchLogs(1)"
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
                :disabled="logs.length === 0"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
              />
            </TableHead>
            <TableHead class="min-w-[140px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Nomor Invoice
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[160px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Dealer Tujuan
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[150px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Pengirim
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[110px]">
              <span>Status</span>
            </TableHead>
            <TableHead class="min-w-[160px]">
              <span>Penerima</span>
            </TableHead>
            <TableHead class="min-w-[150px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Waktu Terkirim
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="text-right w-[140px]">
              <span>Action</span>
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <TableEmpty v-if="loading" :colspan="8">
            <div class="py-8 flex flex-col items-center justify-center gap-2 text-gray-500">
              <span class="inline-block w-5 h-5 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
              <span class="text-xs">Memuat data email logs...</span>
            </div>
          </TableEmpty>

          <TableEmpty v-else-if="logs.length === 0" :colspan="8">
            <div class="py-8 text-center text-gray-400 text-xs">
              Belum ada riwayat pengiriman email.
            </div>
          </TableEmpty>

          <TableRow
            v-for="log in logs"
            :key="log.id"
            :class="selectedIds.includes(log.id) ? 'bg-gray-50' : ''"
          >
            <!-- Checkbox -->
            <TableCell class="w-[44px] text-center">
              <input
                type="checkbox"
                :value="log.id"
                v-model="selectedIds"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer"
              />
            </TableCell>

            <!-- Nomor Invoice (Bold Primary Key like SJ-xxxx in photo) -->
            <TableCell>
              <router-link
                :to="`/invoices/${log.invoice_id}`"
                class="font-semibold text-gray-950 text-xs hover:underline inline-flex items-center gap-1"
              >
                {{ log.invoice_number || '-' }}
              </router-link>
            </TableCell>

            <!-- Dealer Tujuan -->
            <TableCell class="text-xs text-gray-800" :title="log.invoice?.dealer_name">
              {{ log.invoice?.dealer_name || '-' }}
            </TableCell>

            <!-- Pengirim -->
            <TableCell class="text-xs">
              <div class="text-gray-900 font-medium">{{ log.sender_name || 'Rebate MSI' }}</div>
              <div class="text-xs text-gray-400">{{ log.sender_email || '-' }}</div>
            </TableCell>

            <!-- Status (Filament pill badge) -->
            <TableCell>
              <FilamentBadge :color="log.status === 'sent' ? 'success' : 'danger'">
                {{ log.status === 'sent' ? 'DELIVERED' : 'FAILED' }}
              </FilamentBadge>
            </TableCell>

            <!-- Penerima -->
            <TableCell class="text-xs text-gray-700">
              {{ log.recipient_email || '-' }}
            </TableCell>

            <!-- Waktu Terkirim (Formatted like '29 Sep 2026, 03:10') -->
            <TableCell class="text-xs text-gray-700 whitespace-nowrap">
              {{ formatDateTimeFilament(log.created_at) }}
            </TableCell>

            <!-- Action column (View, PDF with Filament icons) -->
            <TableCell class="text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-3">
                <router-link
                  :to="`/invoices/${log.invoice_id}`"
                  class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 hover:text-gray-950 hover:underline cursor-pointer"
                  title="Lihat Detail Invoice"
                >
                  <EyeIcon class="w-4 h-4 text-gray-500" />
                  <span>View</span>
                </router-link>

                <a
                  :href="`/invoices/${log.invoice_id}/pdf`"
                  target="_blank"
                  class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 hover:text-gray-950 hover:underline cursor-pointer"
                  title="Unduh Dokumen PDF"
                >
                  <FileTextIcon class="w-4 h-4 text-gray-500" />
                  <span>PDF</span>
                </a>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <!-- Filament Pagination Footer -->
      <FilamentPagination
        :total="pagination.total"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        @page-change="fetchLogs"
        @per-page-change="onPerPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  ChevronRightIcon,
  ChevronDownIcon,
  SearchIcon,
  FilterIcon,
  EyeIcon,
  FileTextIcon,
  MailIcon,
} from 'lucide-vue-next';
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
} from '@/components/ui/table';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import FilamentBadge from '@/components/ui/FilamentBadge.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';

const logs = ref([]);
const loading = ref(false);
const isFilterOpen = ref(false);
const selectedIds = ref([]);

const filters = reactive({
  search: '',
  status: '',
  date: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.status) count++;
  if (filters.date) count++;
  return count;
});

const isAllSelected = computed(() => {
  return logs.value.length > 0 && selectedIds.value.length === logs.value.length;
});

const isIndeterminate = computed(() => {
  return selectedIds.value.length > 0 && selectedIds.value.length < logs.value.length;
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedIds.value = [];
  } else {
    selectedIds.value = logs.value.map(l => l.id);
  }
};

const formatDateTimeFilament = (dateTimeStr) => {
  if (!dateTimeStr) return '-';
  try {
    const d = new Date(dateTimeStr);
    const datePart = new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    }).format(d);
    const timePart = new Intl.DateTimeFormat('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }).format(d);
    return `${datePart}, ${timePart}`;
  } catch {
    return dateTimeStr;
  }
};

let debounceTimeout = null;
const debounceFetch = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => fetchLogs(1), 300);
};

const onPerPageChange = (newPerPage) => {
  pagination.per_page = newPerPage;
  fetchLogs(1);
};

const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const res = await axios.get('/api/email-logs', {
      params: {
        page,
        per_page: pagination.per_page,
        ...filters
      }
    });
    logs.value = res.data.data || [];
    pagination.current_page = res.data.current_page || 1;
    pagination.last_page = res.data.last_page || 1;
    pagination.per_page = res.data.per_page || 10;
    pagination.total = res.data.total || 0;
    selectedIds.value = [];
  } catch (err) {
    console.error('Error fetching email logs', err);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.status = '';
  filters.date = '';
  filters.search = '';
  fetchLogs(1);
  isFilterOpen.value = false;
};

onMounted(() => fetchLogs(1));
</script>
