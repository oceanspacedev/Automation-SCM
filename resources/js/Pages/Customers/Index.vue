<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master Customer</span>
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Master Customer</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar entitas badan usaha/hukum, NPWP, dan alamat resmi Bill-To faktur.
        </p>
      </div>

      <!-- Tombol aksi; di layar kecil dua tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
        <button
          type="button"
          @click="downloadExport('/api/customers/export', { search: searchQuery, is_active: filterStatus })"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
          title="Export customer sesuai pencarian & filter"
        >
          <span class="sm:hidden">Export</span><span class="hidden sm:inline">Export Excel</span>
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Tambah</span><span class="hidden sm:inline">Tambah Customer</span>
        </button>
      </div>
    </div>

    <!-- Table Card (Matching Form Program Plain Ordinary Table Style) -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ totalRows }} data</span>
        </div>

        <!-- Filter & pencarian; di layar kecil cari di atas, status di bawahnya -->
        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:flex-nowrap">
          <!-- Status Filter -->
          <div class="relative min-w-[140px] col-span-2">
            <select
              v-model="filterStatus"
              @change="fetchCustomers(1)"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="all">Semua Status</option>
              <option value="true">Aktif</option>
              <option value="false">Nonaktif</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <!-- Search Input -->
          <div class="relative w-full sm:w-64 col-span-2 order-first sm:order-none">
            <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
            <input
              type="text"
              v-model="searchQuery"
              @input="handleSearchInput"
              placeholder="Cari kode, nama, NPWP..."
              class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-7 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''; fetchCustomers(1)"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per customer -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="customers.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">Tidak ada data customer yang ditemukan.</p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="c in customers" :key="c.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <p class="min-w-0 text-sm font-medium text-gray-900 break-words">{{ c.code || '-' }} · {{ c.name || '-' }}</p>
              <button
                type="button"
                @click="toggleCustomerStatus(c)"
                class="text-xs hover:underline cursor-pointer shrink-0"
                :class="c.is_active ? 'text-gray-700' : 'text-gray-400'"
              >
                {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
              </button>
            </div>
            <div class="flex items-center justify-between gap-3">
              <p class="min-w-0 text-gray-500 break-words">NPWP {{ c.npwp && c.npwp !== '-' ? c.npwp : '-' }}</p>
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="openEditModal(c)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                >
                  Edit
                </button>
                <button
                  type="button"
                  @click="confirmDelete(c)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-red-600 font-medium cursor-pointer"
                >
                  Hapus
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Plain Ordinary Table (Uniform Font & Style) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-12 border-r border-gray-200">No</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Kode</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama Customer / PT</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">NPWP</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Alamat Bill-To</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading">
              <td colspan="7" class="py-8 text-center text-gray-500">
                Memuat data...
              </td>
            </tr>
            <tr v-else-if="customers.length === 0">
              <td colspan="7" class="py-8 text-center text-gray-500">
                Tidak ada data customer yang ditemukan.
              </td>
            </tr>
            <tr
              v-else
              v-for="(c, idx) in customers"
              :key="c.id"
              class="hover:bg-gray-50/70 text-gray-700 font-normal"
            >
              <!-- 1. No -->
              <!-- 1. No -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                {{ (currentPage - 1) * perPage + idx + 1 }}
              </td>

              <!-- 2. Kode -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ c.code || '-' }}
              </td>

              <!-- 3. Nama Customer -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal min-w-[180px] max-w-[240px] break-words leading-snug">
                {{ c.name || '-' }}
              </td>

              <!-- 4. NPWP -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ c.npwp && c.npwp !== '-' ? c.npwp : '-' }}
              </td>

              <!-- 5. Alamat -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal min-w-[200px] max-w-[280px] break-words leading-snug" :title="c.address">
                {{ c.address || '-' }}
              </td>

              <!-- 6. Status -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleCustomerStatus(c)"
                  class="text-xs hover:underline cursor-pointer"
                  :class="c.is_active ? 'text-gray-700' : 'text-gray-400'"
                >
                  {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>

              <!-- 7. Aksi -->
              <td class="py-2.5 px-3 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1">
                  <button
                    type="button"
                    @click="openEditModal(c)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition cursor-pointer"
                    title="Edit Customer"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="confirmDelete(c)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                    title="Hapus Customer"
                  >
                    <Trash2Icon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination (Matching Form Program exact typography and buttons) -->
      <div
        v-if="totalRows > 0"
        class="p-3 sm:px-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600"
      >
        <!-- Layar kecil: label "Menampilkan" disembunyikan agar tidak melebar -->
        <div class="flex items-center gap-2">
          <span><span class="hidden sm:inline">Menampilkan </span>{{ fromRow }} - {{ toRow }} dari {{ totalRows }} data</span>
          <select
            v-model="perPage"
            @change="fetchCustomers(1)"
            class="h-7 px-2 border border-gray-300 rounded bg-white text-xs cursor-pointer focus:outline-none"
          >
            <option :value="15">15 / hal</option>
            <option :value="20">20 / hal</option>
            <option :value="50">50 / hal</option>
            <option :value="100">100 / hal</option>
          </select>
        </div>

        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="fetchCustomers(currentPage - 1)"
            :disabled="currentPage <= 1 || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <ChevronLeftIcon class="w-3.5 h-3.5" />
            <span>Sebelumnya</span>
          </button>
          <button
            type="button"
            @click="fetchCustomers(currentPage + 1)"
            :disabled="currentPage >= totalPages || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <span>Selanjutnya</span>
            <ChevronRightIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL TAMBAH / EDIT ================= -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-lg w-full p-5 shadow-xl border border-gray-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-gray-200 pb-2.5">
          <h3 class="text-sm font-bold text-gray-950">
            {{ isEditMode ? 'Edit Master Customer' : 'Tambah Master Customer' }}
          </h3>
          <button
            type="button"
            @click="isModalOpen = false"
            class="text-gray-400 hover:text-gray-600 cursor-pointer"
          >
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Kode Customer -->
            <div>
              <label class="block font-medium text-gray-900 mb-1">
                Kode Customer <span class="text-rose-500">*</span>
              </label>
              <input
                type="text"
                v-model="formData.code"
                required
                placeholder="cth: CV TOP / PT RISM"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>

            <!-- Nama Customer -->
            <div>
              <label class="block font-medium text-gray-900 mb-1">
                Nama Badan Usaha/PT <span class="text-rose-500">*</span>
              </label>
              <input
                type="text"
                v-model="formData.name"
                required
                placeholder="cth: CV TOP SELULAR"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- NPWP -->
            <div>
              <label class="block font-medium text-gray-900 mb-1">Nomor NPWP</label>
              <input
                type="text"
                v-model="formData.npwp"
                placeholder="cth: 31.352.339.1-426.000"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>

            <!-- Nama di NPWP -->
            <div>
              <label class="block font-medium text-gray-900 mb-1">Nama di NPWP</label>
              <input
                type="text"
                v-model="formData.npwp_name"
                placeholder="Sama dengan nama di sertifikat"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
          </div>

          <!-- Alamat Bill-To -->
          <div>
            <label class="block font-medium text-gray-900 mb-1">Alamat Resmi Bill To / Faktur Pajak</label>
            <textarea
              v-model="formData.address"
              rows="3"
              placeholder="Alamat lengkap legal entity..."
              class="w-full p-2.5 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            ></textarea>
          </div>

          <!-- Status Aktif Checkbox -->
          <div class="flex items-center gap-2 pt-1">
            <input
              type="checkbox"
              id="is_active_cust"
              v-model="formData.is_active"
              class="w-4 h-4 rounded text-gray-900 focus:ring-gray-900 border-gray-300 cursor-pointer"
            />
            <label for="is_active_cust" class="text-xs text-gray-700 cursor-pointer select-none">
              Status Aktif
            </label>
          </div>

          <!-- Buttons -->
          <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
            <button
              type="button"
              @click="isModalOpen = false"
              class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer disabled:opacity-50"
            >
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Customer' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL HAPUS ================= -->
    <div
      v-if="customerToDelete"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Master Customer</h3>
        <p class="text-xs text-gray-600">
          Hapus customer <strong class="text-gray-900">{{ customerToDelete.name }}</strong> ({{ customerToDelete.code }})?
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="customerToDelete = null"
            class="h-8 px-3 rounded-lg border border-gray-300 hover:bg-gray-50 text-xs font-medium text-gray-700 cursor-pointer"
          >
            Batal
          </button>
          <button
            type="button"
            @click="executeDelete"
            :disabled="isDeleting"
            class="h-8 px-3 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium cursor-pointer disabled:opacity-50"
          >
            <span>{{ isDeleting ? 'Menghapus...' : 'Hapus' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { downloadExport } from '@/lib/download';
import {
  ChevronRightIcon,
  ChevronLeftIcon,
  ChevronDownIcon,
  SearchIcon,
  XIcon,
  PencilIcon,
  Trash2Icon,
} from 'lucide-vue-next';

const customers = ref([]);
const totalRows = ref(0);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(20);
const isLoading = ref(false);

const fromRow = computed(() => (totalRows.value === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1));
const toRow = computed(() => Math.min(currentPage.value * perPage.value, totalRows.value));

const searchQuery = ref('');
const filterStatus = ref('all');
let searchTimeout = null;

const isModalOpen = ref(false);
const isEditMode = ref(false);
const isSaving = ref(false);
const customerToDelete = ref(null);
const isDeleting = ref(false);

const formData = reactive({
  id: null,
  code: '',
  name: '',
  npwp: '',
  npwp_name: '',
  address: '',
  is_active: true,
});

async function fetchCustomers(page = 1) {
  isLoading.value = true;
  currentPage.value = page;

  try {
    const params = {
      page,
      per_page: perPage.value,
      search: searchQuery.value.trim() || undefined,
      is_active: filterStatus.value !== 'all' ? filterStatus.value : undefined,
    };
    const res = await axios.get('/api/customers', { params });
    if (res.data?.success) {
      customers.value = res.data.data.data || [];
      totalRows.value = res.data.data.total || 0;
      totalPages.value = res.data.data.last_page || 1;
      perPage.value = res.data.data.per_page || 20;
    }
  } catch (e) {
    alert('Gagal memuat data customer: ' + (e.response?.data?.message || e.message));
  } finally {
    isLoading.value = false;
  }
}

function handleSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchCustomers(1);
  }, 300);
}

function openCreateModal() {
  isEditMode.value = false;
  Object.assign(formData, {
    id: null,
    code: '',
    name: '',
    npwp: '',
    npwp_name: '',
    address: '',
    is_active: true,
  });
  isModalOpen.value = true;
}

function openEditModal(c) {
  isEditMode.value = true;
  Object.assign(formData, {
    id: c.id,
    code: c.code,
    name: c.name,
    npwp: c.npwp || '',
    npwp_name: c.npwp_name || '',
    address: c.address || '',
    is_active: Boolean(c.is_active),
  });
  isModalOpen.value = true;
}

async function submitForm() {
  isSaving.value = true;
  try {
    if (isEditMode.value) {
      await axios.put(`/api/customers/${formData.id}`, formData);
    } else {
      await axios.post('/api/customers', formData);
    }
    isModalOpen.value = false;
    await fetchCustomers(currentPage.value);
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan data customer.');
  } finally {
    isSaving.value = false;
  }
}

async function toggleCustomerStatus(c) {
  try {
    const res = await axios.patch(`/api/customers/${c.id}/toggle-status`);
    if (res.data?.success) {
      c.is_active = res.data.data.is_active;
    }
  } catch (e) {
    alert('Gagal mengubah status: ' + (e.response?.data?.message || e.message));
  }
}

function confirmDelete(c) {
  customerToDelete.value = c;
}

async function executeDelete() {
  if (!customerToDelete.value) return;
  isDeleting.value = true;
  try {
    const res = await axios.delete(`/api/customers/${customerToDelete.value.id}`);
    if (res.data?.success) {
      customerToDelete.value = null;
      await fetchCustomers(currentPage.value);
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus customer.');
  } finally {
    isDeleting.value = false;
  }
}

onMounted(() => {
  fetchCustomers(1);
});
</script>
