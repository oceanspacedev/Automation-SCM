<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master Program</span>
    </div>

    <BrandTabs />

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Master Program {{ brand.name }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar kode program, nama program, dan periode resmi {{ brand.name }}. Kode program otomatis terisi saat memilih program di draft.
        </p>
      </div>

      <!-- Tombol aksi; di layar kecil tiga tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-3 sm:flex sm:items-center gap-2">
        <input
          ref="importInput"
          type="file"
          accept=".xlsx,.xls,.csv"
          class="hidden"
          @change="handleImportFile"
        />
        <button
          type="button"
          @click="importInput?.click()"
          :disabled="isImporting"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer disabled:opacity-50 whitespace-nowrap"
          title="Import Excel dengan kolom PROGRAM, NAMA PROGRAM, PERIODE"
        >
          <span class="sm:hidden">{{ isImporting ? 'Mengimpor...' : 'Import' }}</span>
          <span class="hidden sm:inline">{{ isImporting ? 'Mengimpor...' : 'Import Excel' }}</span>
        </button>
        <button
          type="button"
          @click="downloadExport('/api/programs/export', { search: searchQuery, is_active: filterStatus })"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
          title="Export program sesuai pencarian & filter (format sama dengan file import)"
        >
          <span class="sm:hidden">Export</span><span class="hidden sm:inline">Export Excel</span>
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Tambah</span><span class="hidden sm:inline">Tambah Program</span>
        </button>
      </div>
    </div>

    <!-- Hasil simpan / import (mis. kode program ikut terisi ke Data Program) -->
    <div
      v-if="notice"
      role="status"
      class="rounded-xl border px-4 py-3 text-xs flex items-start justify-between gap-3"
      :class="notice.error ? 'border-red-200 bg-red-50/60' : 'border-emerald-200 bg-emerald-50/60'"
    >
      <p :class="['min-w-0 break-words', notice.error ? 'text-red-700' : 'text-emerald-800']">{{ notice.text }}</p>
      <button type="button" @click="notice = null" class="text-gray-400 hover:text-gray-600 cursor-pointer shrink-0" title="Tutup">
        <XIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Table Card (Matching Clean Plain Table Style) -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ totalRows }} program</span>
        </div>

        <!-- Filter & pencarian; di layar kecil cari di atas, status di bawahnya -->
        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:flex-nowrap">
          <!-- Status Filter -->
          <div class="relative min-w-[120px] col-span-2">
            <select
              v-model="filterStatus"
              @change="fetchPrograms(1)"
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
              placeholder="Cari program, nama program, periode..."
              class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-7 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''; fetchPrograms(1)"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per program -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="programs.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">Tidak ada data master program yang ditemukan.</p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="p in programs" :key="p.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <p class="min-w-0 text-sm font-medium text-gray-900 break-words">{{ p.code || '-' }} · {{ p.name }}</p>
              <button
                type="button"
                @click="toggleStatus(p)"
                :disabled="p._isToggling"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer shrink-0"
                :class="p.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="p.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                <span>{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </button>
            </div>
            <div class="flex items-center justify-between gap-3">
              <p class="min-w-0 text-gray-500 break-words">{{ p.period || '-' }}</p>
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="openEditModal(p)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                >
                  Edit
                </button>
                <button
                  type="button"
                  @click="confirmDelete(p)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-red-600 font-medium cursor-pointer"
                >
                  Hapus
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Plain Table (Uniform Font & Style) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 w-32 border-r border-gray-200">PROGRAM</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">NAMA PROGRAM</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">PERIODE</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading">
              <td colspan="5" class="py-8 text-center text-gray-500">
                Memuat data...
              </td>
            </tr>
            <tr v-else-if="programs.length === 0">
              <td colspan="5" class="py-8 text-center text-gray-500">
                Tidak ada data master program yang ditemukan.
              </td>
            </tr>
            <tr
              v-else
              v-for="p in programs"
              :key="p.id"
              class="hover:bg-gray-50/80 transition"
            >
              <td class="py-2 px-3 font-medium text-gray-900 border-r border-gray-200 whitespace-nowrap">
                <span v-if="p.code">{{ p.code }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="py-2 px-3 text-gray-900 border-r border-gray-200">
                {{ p.name }}
              </td>
              <td class="py-2 px-3 text-gray-700 italic border-r border-gray-200 whitespace-nowrap">
                {{ p.period || '-' }}
              </td>
              <td class="py-2 px-3 text-center border-r border-gray-200 whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleStatus(p)"
                  :disabled="p._isToggling"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer"
                  :class="p.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="p.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                  <span>{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
              </td>
              <td class="py-2 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <button
                    type="button"
                    @click="openEditModal(p)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-gray-100 text-gray-600 transition cursor-pointer"
                    title="Edit Program"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="confirmDelete(p)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-red-50 text-red-600 hover:border-red-200 transition cursor-pointer"
                    title="Hapus Program"
                  >
                    <Trash2Icon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-3 sm:px-4 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
        <div>
          <span>Menampilkan {{ fromRow }} - {{ toRow }} dari {{ totalRows }} data</span>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-xs text-gray-500">Tampilkan:</label>
          <select
            v-model="perPage"
            @change="fetchPrograms(1)"
            class="h-7 px-2 border border-gray-300 rounded bg-white text-xs cursor-pointer focus:outline-none"
          >
            <option :value="15">15 / hal</option>
            <option :value="25">25 / hal</option>
            <option :value="50">50 / hal</option>
            <option :value="100">100 / hal</option>
          </select>
        </div>

        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="fetchPrograms(currentPage - 1)"
            :disabled="currentPage <= 1 || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <ChevronLeftIcon class="w-3.5 h-3.5" />
            <span>Sebelumnya</span>
          </button>
          <button
            type="button"
            @click="fetchPrograms(currentPage + 1)"
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
            {{ isEditMode ? 'Edit Master Program' : 'Tambah Master Program' }}
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
          <!-- Program (Kode) -->
          <div>
            <label class="block font-medium text-gray-900 mb-1">
              Program <span class="text-rose-500">*</span>
            </label>
            <input
              type="text"
              v-model="formData.code"
              required
              placeholder="cth: PROGRAM 345"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 uppercase"
            />
            <p v-if="!isEditMode" class="text-[11px] text-gray-500 mt-1">Otomatis diisi nomor program berikutnya.</p>
          </div>

          <!-- Nama Program -->
          <div>
            <label class="block font-medium text-gray-900 mb-1">
              Nama Program <span class="text-rose-500">*</span>
            </label>
            <input
              type="text"
              v-model="formData.name"
              required
              placeholder="cth: PROGRAM DSA AGUSTUS 2026"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 uppercase"
            />
          </div>

          <!-- Periode -->
          <div>
            <label class="block font-medium text-gray-900 mb-1">Periode</label>
            <input
              type="text"
              v-model="formData.period"
              placeholder="cth: Periode 1-31 Agustus 2026"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
          </div>

          <!-- Status Aktif -->
          <div class="flex items-center gap-2 pt-1">
            <input
              type="checkbox"
              id="is_active_checkbox"
              v-model="formData.is_active"
              class="w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-0 cursor-pointer"
            />
            <label for="is_active_checkbox" class="text-xs text-gray-800 cursor-pointer select-none">
              Status Aktif (Tampil di formulir dealer dan pilihan draft)
            </label>
          </div>

          <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>

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
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Program' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL HAPUS ================= -->
    <div
      v-if="programToDelete"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Master Program</h3>
        <p class="text-xs text-gray-600">
          Hapus program <strong class="text-gray-900">{{ programToDelete.name }}</strong>?
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="programToDelete = null"
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
import { notify } from '@/composables/useNotify';
import { downloadExport } from '@/lib/download';
import BrandTabs from '@/components/BrandTabs.vue';
import { useBrands } from '@/composables/useBrands';
import { useAutoRefresh, createRequestTracker } from '@/composables/useAutoRefresh';
import {
  ChevronRightIcon,
  ChevronLeftIcon,
  ChevronDownIcon,
  SearchIcon,
  XIcon,
  PencilIcon,
  Trash2Icon,
} from 'lucide-vue-next';

const { brand } = useBrands();

const programs = ref([]);
const totalRows = ref(0);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(25);
const isLoading = ref(false);
const nextCode = ref('');
const importInput = ref(null);
const isImporting = ref(false);
// Hasil simpan / import yang perlu diketahui (mis. kode program ikut terisi ke Data Program), tampil di atas tabel
const notice = ref(null);
// Gagal simpan (mis. nama atau kode sudah dipakai), tampil di dalam form
const formError = ref('');

const fromRow = computed(() => (totalRows.value === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1));
const toRow = computed(() => Math.min(currentPage.value * perPage.value, totalRows.value));

const searchQuery = ref('');
const filterStatus = ref('all');
let searchTimeout = null;

const isModalOpen = ref(false);
const isEditMode = ref(false);
const isSaving = ref(false);
const programToDelete = ref(null);
const isDeleting = ref(false);

const formData = reactive({
  id: null,
  name: '',
  code: '',
  category: '',
  period: '',
  description: '',
  is_active: true,
});

onMounted(() => {
  fetchPrograms();
});

const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchPrograms(1);
  }, 300);
};

const startProgramsRequest = createRequestTracker();

const fetchPrograms = async (page = currentPage.value, { silent = false } = {}) => {
  const isLatest = startProgramsRequest();
  if (!silent) isLoading.value = true;
  currentPage.value = page;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      search: searchQuery.value,
      is_active: filterStatus.value,
    };

    const res = await axios.get('/api/programs', { params });
    if (!isLatest()) return;
    if (res.data?.success) {
      programs.value = res.data.data.data;
      totalRows.value = res.data.data.total;
      totalPages.value = res.data.data.last_page;
      currentPage.value = res.data.data.current_page;
      nextCode.value = res.data.next_code || '';
    }
  } catch (err) {
    console.error('Failed to fetch master programs:', err);
  } finally {
    if (!silent && isLatest()) isLoading.value = false;
  }
};

useAutoRefresh(() => fetchPrograms(currentPage.value, { silent: true }), {
  isPaused: () => isLoading.value || isModalOpen.value || programToDelete.value !== null || isImporting.value || isSaving.value || isDeleting.value,
});

const openCreateModal = () => {
  isEditMode.value = false;
  formData.id = null;
  formData.name = '';
  formData.code = nextCode.value;
  formData.category = '';
  formData.period = '';
  formData.description = '';
  formData.is_active = true;
  formError.value = '';
  isModalOpen.value = true;
};

const openEditModal = (p) => {
  isEditMode.value = true;
  formData.id = p.id;
  formData.name = p.name;
  formData.code = p.code || '';
  formData.category = p.category || '';
  formData.period = p.period || '';
  formData.description = p.description || '';
  formData.is_active = p.is_active !== false;
  formError.value = '';
  isModalOpen.value = true;
};

const submitForm = async () => {
  if (!formData.name.trim()) return;

  isSaving.value = true;
  notice.value = null;
  formError.value = '';
  try {
    const res = isEditMode.value
      ? await axios.put(`/api/programs/${formData.id}`, formData)
      : await axios.post('/api/programs', formData);
    isModalOpen.value = false;
    fetchPrograms(currentPage.value);
    // Kode program ikut diisi ke baris Data Program bernama program sama yang kolom Program-nya masih kosong
    if (res.data?.data_program_filled > 0) notice.value = { error: false, text: res.data.message };
  } catch (err) {
    formError.value = err.response?.data?.message || 'Gagal menyimpan program.';
  } finally {
    isSaving.value = false;
  }
};

const handleImportFile = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;

  isImporting.value = true;
  notice.value = null;
  try {
    const payload = new FormData();
    payload.append('file', file);
    const res = await axios.post('/api/programs/import', payload);
    notice.value = { error: false, text: res.data?.message || 'Import master program selesai.' };
    fetchPrograms(1);
  } catch (err) {
    notice.value = { error: true, text: err.response?.data?.message || 'Gagal mengimpor file master program.' };
  } finally {
    isImporting.value = false;
  }
};

const toggleStatus = async (p) => {
  p._isToggling = true;
  try {
    const res = await axios.patch(`/api/programs/${p.id}/toggle-status`);
    if (res.data?.success) {
      p.is_active = res.data.data.is_active;
    }
  } catch (err) {
    notify.error('Gagal mengubah status program.');
  } finally {
    p._isToggling = false;
  }
};

const confirmDelete = (p) => {
  programToDelete.value = p;
};

const executeDelete = async () => {
  if (!programToDelete.value) return;

  isDeleting.value = true;
  try {
    const res = await axios.delete(`/api/programs/${programToDelete.value.id}`);
    if (res.data?.success) {
      programToDelete.value = null;
      fetchPrograms(currentPage.value);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Gagal menghapus program.';
    notify.error(msg);
  } finally {
    isDeleting.value = false;
  }
};
</script>
