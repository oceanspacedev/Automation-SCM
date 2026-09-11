<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Invoices</h1>
        <p class="text-sm text-gray-500">Daftar invoice resmi yang telah diterbitkan.</p>
      </div>
      <div>
        <router-link
          to="/drafts"
          class="h-9 px-3 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md transition inline-flex items-center"
        >
          ← Kembali ke Draft
        </router-link>
      </div>
    </div>

    <!-- Toolbar Filters (Shadcn style) -->
    <div class="flex flex-wrap items-center justify-between gap-3 py-1">
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="filters.search"
          @input="debounceFetch"
          type="text"
          placeholder="Filter invoice, dealer, customer..."
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
        </select>
        <select
          v-model="filters.status"
          @change="fetchInvoices(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black"
        >
          <option value="">Semua Status</option>
          <option value="generated">Generated</option>
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
            <TableHead class="w-[160px]">Invoice</TableHead>
            <TableHead class="w-[100px]">Status</TableHead>
            <TableHead class="w-[90px]">Method</TableHead>
            <TableHead>Dealer</TableHead>
            <TableHead>Customer</TableHead>
            <TableHead>Program</TableHead>
            <TableHead class="w-[110px]">Tanggal</TableHead>
            <TableHead class="text-right w-[140px]">Amount</TableHead>
            <TableHead class="text-right w-[140px]">Action</TableHead>
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
            <TableCell>
              <router-link :to="`/invoices/${inv.id}`" class="hover:underline">
                {{ inv.invoice_number }}
              </router-link>
            </TableCell>
            <TableCell class="capitalize">
              {{ inv.status }}
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
            <TableCell class="max-w-[150px] truncate" :title="inv.program_name">
              {{ inv.program_name || '-' }}
            </TableCell>
            <TableCell class="whitespace-nowrap">
              {{ inv.invoice_date || '-' }}
            </TableCell>
            <TableCell class="text-right">
              {{ formatCurrency(inv.netpay) }}
            </TableCell>
            <TableCell class="text-right">
              <div class="flex items-center justify-end space-x-2">
                <router-link
                  :to="`/invoices/${inv.id}`"
                  class="text-sm text-gray-600 hover:text-black hover:underline"
                >
                  View
                </router-link>
                <a
                  :href="`/invoices/${inv.id}/preview`"
                  target="_blank"
                  class="text-sm text-gray-600 hover:text-black hover:underline"
                >
                  Print
                </a>
                <button
                  @click="openEmailModal(inv)"
                  class="h-7 px-2.5 border border-gray-200 bg-white text-gray-700 text-xs font-medium rounded hover:bg-gray-50 transition inline-flex items-center gap-1"
                >
                  <MailIcon class="h-3.5 w-3.5" />
                  Email
                </button>
                <a
                  :href="`/invoices/${inv.id}/pdf`"
                  class="h-7 px-2.5 bg-black text-white text-xs font-medium rounded hover:bg-gray-800 transition inline-flex items-center"
                >
                  PDF
                </a>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
        <TableFooter v-if="invoices.length > 0">
          <TableRow>
            <TableCell :colspan="7">
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
          {{ pagination.current_page }} / {{ pagination.last_page }}
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
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { CalendarDate, parseDate } from '@internationalized/date';
import { CalendarIcon, MailIcon } from '@lucide/vue';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import SendEmailModal from '@/components/SendEmailModal.vue';
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

const openEmailModal = (inv) => {
  selectedInvoice.value = inv;
  showEmailModal.value = true;
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
