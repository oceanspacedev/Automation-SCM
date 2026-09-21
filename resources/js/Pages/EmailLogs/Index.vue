<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Riwayat Email</h1>
        <p class="text-sm text-gray-500">Histori pengiriman invoice via email, diurutkan per hari.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2 py-1">
      <input
        v-model="filters.search"
        @input="debounceFetch"
        type="text"
        placeholder="Cari invoice, email..."
        class="h-9 w-60 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
      />
      <select
        v-model="filters.status"
        @change="fetchLogs(1)"
        class="h-9 rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-black"
      >
        <option value="">Semua Status</option>
        <option value="sent">Sent</option>
        <option value="failed">Failed</option>
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
        v-if="filters.search || filters.status || filters.date"
        @click="resetFilters"
        class="h-9 px-3 text-sm text-gray-500 hover:text-black cursor-pointer"
      >
        Reset
      </button>
    </div>

    <!-- Grouped by date -->
    <div v-if="loading" class="py-10 text-center text-sm text-gray-400">
      Memuat riwayat email...
    </div>

    <div v-else-if="groupedLogs.length === 0" class="py-10 text-center text-sm text-gray-400">
      Belum ada riwayat pengiriman email.
    </div>

    <div v-else class="space-y-6">
      <div v-for="group in groupedLogs" :key="group.date">
        <!-- Date label -->
        <div class="flex items-center gap-3 mb-2">
          <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
            {{ group.label }}
          </span>
          <span class="text-xs text-gray-400">{{ group.logs.length }} email dikirim</span>
          <div class="flex-1 border-t border-gray-100"></div>
        </div>

        <!-- Table per group -->
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="w-[170px]">Invoice</TableHead>
                <TableHead class="w-[90px]">Type</TableHead>
                <TableHead>Dealer</TableHead>
                <TableHead>Pengirim</TableHead>
                <TableHead>Penerima</TableHead>
                <TableHead class="w-[90px]">Status</TableHead>
                <TableHead class="w-[120px]">Waktu</TableHead>
                <TableHead class="w-[80px]">Action</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="log in group.logs" :key="log.id">
                <TableCell class="font-medium">
                  <router-link
                    :to="`/invoices/${log.invoice_id}`"
                    class="hover:underline text-gray-900"
                  >
                    {{ log.invoice_number }}
                  </router-link>
                </TableCell>
                <TableCell class="text-gray-600">
                  {{ log.invoice?.invoice_type || '-' }}
                </TableCell>
                <TableCell class="max-w-[150px] truncate text-gray-700 text-xs" :title="log.invoice?.dealer_name">
                  {{ log.invoice?.dealer_name || '-' }}
                </TableCell>
                <TableCell class="text-xs">
                  <div class="font-medium text-gray-900">{{ log.sender_name || 'Rebate. MSI' }}</div>
                  <div class="text-[11px] text-gray-400">{{ log.sender_email || 'ade@mediaselularindonesia.com' }}</div>
                </TableCell>
                <TableCell class="text-gray-700 text-xs">
                  {{ log.recipient_email }}
                </TableCell>
                <TableCell>
                  <span
                    :class="log.status === 'sent'
                      ? 'bg-green-50 text-green-700 border-green-200'
                      : 'bg-red-50 text-red-700 border-red-200'"
                    class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded border"
                  >
                    <span v-if="log.status === 'sent'">✓</span>
                    <span v-else>✗</span>
                    {{ log.status === 'sent' ? 'Sent' : 'Failed' }}
                  </span>
                </TableCell>
                <TableCell class="text-gray-500 text-xs whitespace-nowrap">
                  {{ formatTime(log.created_at) }}
                </TableCell>
                <TableCell>
                  <a
                    :href="`/invoices/${log.invoice_id}/pdf`"
                    class="text-xs text-gray-500 hover:text-black hover:underline"
                  >
                    PDF
                  </a>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!loading && pagination.last_page > 1" class="flex items-center justify-between py-2 text-sm text-gray-500">
      <div>
        Menampilkan {{ pagination.total > 0 ? (pagination.current_page - 1) * pagination.per_page + 1 : 0 }}
        sampai {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
        dari {{ pagination.total }} log.
      </div>
      <div class="flex items-center space-x-2">
        <button
          @click="fetchLogs(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="h-8 px-3 rounded-md border border-gray-200 text-sm font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Previous
        </button>
        <span class="text-sm font-medium text-gray-700">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>
        <button
          @click="fetchLogs(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="h-8 px-3 rounded-md border border-gray-200 text-sm font-medium hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { parseDate } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from '@/components/ui/table';

const logs = ref([]);
const loading = ref(false);

const filters = reactive({
  search: '',
  status: '',
  date: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
});

const selectedCalendarDate = computed(() => {
  if (!filters.date) return undefined;
  try { return parseDate(filters.date); } catch { return undefined; }
});

const onDateSelect = (val) => {
  if (!val) { filters.date = ''; fetchLogs(1); return; }
  filters.date = val.toString();
  fetchLogs(1);
};

// Group logs by date
const groupedLogs = computed(() => {
  const groups = {};
  for (const log of logs.value) {
    const date = log.created_at.substring(0, 10); // YYYY-MM-DD
    if (!groups[date]) groups[date] = [];
    groups[date].push(log);
  }
  return Object.entries(groups)
    .sort(([a], [b]) => b.localeCompare(a))
    .map(([date, items]) => ({
      date,
      label: formatGroupDate(date),
      logs: items,
    }));
});

const formatGroupDate = (dateStr) => {
  try {
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    if (d.toDateString() === today.toDateString()) return 'Hari ini — ' + new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(d);
    if (d.toDateString() === yesterday.toDateString()) return 'Kemarin — ' + new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(d);
    return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).format(d);
  } catch { return dateStr; }
};

const formatDateDisplay = (dateStr) => {
  try {
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateStr));
  } catch { return dateStr; }
};

const formatTime = (dateTimeStr) => {
  try {
    return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(dateTimeStr));
  } catch { return '-'; }
};

let debounceTimeout = null;
const debounceFetch = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => fetchLogs(1), 300);
};

const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const res = await axios.get('/api/email-logs', { params: { page, ...filters } });
    logs.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.per_page = res.data.per_page;
    pagination.total = res.data.total;
  } catch (err) {
    console.error('Error fetching email logs', err);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.status = '';
  filters.date = '';
  fetchLogs(1);
};

onMounted(() => fetchLogs(1));
</script>
