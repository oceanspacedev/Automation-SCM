<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master Region</span>
    </div>

    <AreaTabs />

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Master Region {{ area.label }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar region {{ area.label }} yang muncul di form pengajuan program dan Master Dealer.
        </p>
      </div>

      <!-- Tombol aksi; di layar kecil dua tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
        <button
          type="button"
          @click="downloadExport('/api/regions/export', { search: searchQuery })"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Export</span><span class="hidden sm:inline">Export Excel</span>
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Tambah</span><span class="hidden sm:inline">Tambah Region</span>
        </button>
      </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ regions.length }} region</span>
        </div>

        <div class="relative w-full sm:w-64">
          <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Cari region..."
            class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
          />
        </div>
      </div>

      <!-- Layar kecil: satu kartu per region -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="filteredRegions.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">
          Belum ada region untuk {{ area.label }}. Klik "Tambah Region" untuk menambahkan.
        </p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="r in filteredRegions" :key="r.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-center justify-between gap-3">
              <p class="min-w-0 text-sm font-medium text-gray-900 break-words">{{ r.name }}</p>
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="toggleStatus(r)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer"
                  :class="r.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="r.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                  <span>{{ r.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
                <button
                  type="button"
                  @click="openEditModal(r)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                >
                  Edit
                </button>
                <button
                  type="button"
                  @click="regionToDelete = r"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-red-600 font-medium cursor-pointer"
                >
                  Hapus
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-12 border-r border-gray-200">No</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">REGION</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading">
              <td colspan="4" class="py-8 text-center text-gray-500">Memuat data...</td>
            </tr>
            <tr v-else-if="filteredRegions.length === 0">
              <td colspan="4" class="py-8 text-center text-gray-500">
                Belum ada region untuk {{ area.label }}. Klik "Tambah Region" untuk menambahkan.
              </td>
            </tr>
            <tr v-else v-for="(r, index) in filteredRegions" :key="r.id" class="hover:bg-gray-50/80 transition">
              <td class="py-2 px-3 text-center text-gray-500 border-r border-gray-200">{{ index + 1 }}</td>
              <td class="py-2 px-3 font-medium text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ r.name }}</td>
              <td class="py-2 px-3 text-center border-r border-gray-200 whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleStatus(r)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer"
                  :class="r.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="r.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                  <span>{{ r.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
              </td>
              <td class="py-2 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <button
                    type="button"
                    @click="openEditModal(r)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-gray-100 text-gray-600 transition cursor-pointer"
                    title="Edit Region"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="regionToDelete = r"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-red-50 text-red-600 hover:border-red-200 transition cursor-pointer"
                    title="Hapus Region"
                  >
                    <Trash2Icon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Tambah / Edit -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-xl border border-gray-200 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-200 pb-2.5">
          <h3 class="text-sm font-bold text-gray-950">
            {{ formData.id ? 'Edit Region' : 'Tambah Region' }} {{ area.label }}
          </h3>
          <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-medium text-gray-900 mb-1">Nama Region <span class="text-rose-500">*</span></label>
            <input
              type="text"
              v-model="formData.name"
              required
              placeholder="cth: BIG SEMARANG"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 uppercase"
            />
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="region_is_active"
              v-model="formData.is_active"
              class="w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-0 cursor-pointer"
            />
            <label for="region_is_active" class="text-xs text-gray-800 cursor-pointer select-none">
              Aktif (tampil di form pengajuan program)
            </label>
          </div>

          <p v-if="errorMessage" class="text-[11px] text-red-600">{{ errorMessage }}</p>

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
              {{ isSaving ? 'Menyimpan...' : 'Simpan Region' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Hapus -->
    <div v-if="regionToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Region</h3>
        <p class="text-xs text-gray-600">
          Hapus region <strong class="text-gray-900">{{ regionToDelete.name }}</strong> dari {{ area.label }}?
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="regionToDelete = null"
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
            {{ isDeleting ? 'Menghapus...' : 'Hapus' }}
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
import AreaTabs from '@/components/AreaTabs.vue';
import { useArea } from '@/composables/useArea';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { downloadExport } from '@/lib/download';
import {
  ChevronRight as ChevronRightIcon,
  Search as SearchIcon,
  X as XIcon,
  Pencil as PencilIcon,
  Trash2 as Trash2Icon,
} from 'lucide-vue-next';

const { area } = useArea();

const regions = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');

const isModalOpen = ref(false);
const isSaving = ref(false);
const errorMessage = ref('');
const regionToDelete = ref(null);
const isDeleting = ref(false);

const formData = reactive({
  id: null,
  name: '',
  is_active: true,
});

const filteredRegions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return q ? regions.value.filter(r => r.name.toLowerCase().includes(q)) : regions.value;
});

const fetchRegions = async ({ silent = false } = {}) => {
  if (!silent) isLoading.value = true;
  try {
    const res = await axios.get('/api/regions');
    regions.value = res.data?.data || [];
  } catch (err) {
    console.error('Failed to fetch regions:', err);
  } finally {
    if (!silent) isLoading.value = false;
  }
};

useAutoRefresh(() => fetchRegions({ silent: true }), {
  isPaused: () => isLoading.value || isModalOpen.value || regionToDelete.value !== null || isSaving.value || isDeleting.value,
});

const openCreateModal = () => {
  formData.id = null;
  formData.name = '';
  formData.is_active = true;
  errorMessage.value = '';
  isModalOpen.value = true;
};

const openEditModal = (r) => {
  formData.id = r.id;
  formData.name = r.name;
  formData.is_active = r.is_active !== false;
  errorMessage.value = '';
  isModalOpen.value = true;
};

const submitForm = async () => {
  isSaving.value = true;
  errorMessage.value = '';
  try {
    const payload = { name: formData.name, is_active: formData.is_active };
    if (formData.id) {
      await axios.put(`/api/regions/${formData.id}`, payload);
    } else {
      await axios.post('/api/regions', payload);
    }
    isModalOpen.value = false;
    fetchRegions();
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal menyimpan region.';
  } finally {
    isSaving.value = false;
  }
};

const toggleStatus = async (r) => {
  try {
    const res = await axios.patch(`/api/regions/${r.id}/toggle-status`);
    r.is_active = res.data?.data?.is_active ?? !r.is_active;
  } catch (err) {
    notify.error('Gagal mengubah status region.');
  }
};

const executeDelete = async () => {
  isDeleting.value = true;
  try {
    await axios.delete(`/api/regions/${regionToDelete.value.id}`);
    regionToDelete.value = null;
    fetchRegions();
  } catch (err) {
    notify.error(err.response?.data?.message || 'Gagal menghapus region.');
  } finally {
    isDeleting.value = false;
  }
};

onMounted(fetchRegions);
</script>
