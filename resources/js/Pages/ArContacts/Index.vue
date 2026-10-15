<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master AR</span>
    </div>

    <AreaTabs />

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Master AR {{ area.label }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          AR tiap region. Setelah telemarketing mengonfirmasi dealer setuju dipotong, WhatsApp dikirim ke AR region dealer tersebut.
        </p>
      </div>

      <!-- Tombol aksi; di layar kecil dua tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
        <button
          type="button"
          @click="downloadExport('/api/ar-contacts/export', { search: searchQuery })"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Export</span><span class="hidden sm:inline">Export Excel</span>
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span>Tambah AR</span>
        </button>
      </div>
    </div>

    <p v-if="regionsWithoutAr.length" class="text-xs text-amber-700">
      Belum ada AR untuk {{ regionsWithoutAr.join(', ') }}. WhatsApp dealer region ini masih dikirim ke nomor AR default.
    </p>

    <!-- Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ contacts.length }} AR</span>
        </div>

        <div class="relative w-full sm:w-64">
          <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Cari region, nama, atau nomor..."
            class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
          />
        </div>
      </div>

      <!-- Layar kecil: satu kartu per AR -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="filteredContacts.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">
          Belum ada AR untuk {{ area.label }}. Klik "Tambah AR" untuk menambahkan.
        </p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="c in filteredContacts" :key="c.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <p class="min-w-0 text-sm font-medium text-gray-900 break-words">{{ c.region }}</p>
              <button
                type="button"
                @click="toggleStatus(c)"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer shrink-0"
                :class="c.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="c.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                <span>{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </button>
            </div>
            <div class="flex items-center justify-between gap-3">
              <p class="min-w-0 text-gray-500 break-words">{{ c.name }} · {{ c.whatsapp }}</p>
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
                  @click="contactToDelete = c"
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
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">NAMA AR</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">NO WHATSAPP</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading">
              <td colspan="6" class="py-8 text-center text-gray-500">Memuat data...</td>
            </tr>
            <tr v-else-if="filteredContacts.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-500">
                Belum ada AR untuk {{ area.label }}. Klik "Tambah AR" untuk menambahkan.
              </td>
            </tr>
            <tr v-else v-for="(c, index) in filteredContacts" :key="c.id" class="hover:bg-gray-50/80 transition">
              <td class="py-2 px-3 text-center text-gray-500 border-r border-gray-200">{{ index + 1 }}</td>
              <td class="py-2 px-3 font-medium text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ c.region }}</td>
              <td class="py-2 px-3 text-gray-800 border-r border-gray-200 whitespace-nowrap">{{ c.name }}</td>
              <td class="py-2 px-3 text-gray-700 border-r border-gray-200 whitespace-nowrap">{{ c.whatsapp }}</td>
              <td class="py-2 px-3 text-center border-r border-gray-200 whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleStatus(c)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer"
                  :class="c.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="c.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                  <span>{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
              </td>
              <td class="py-2 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <button
                    type="button"
                    @click="openEditModal(c)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-gray-100 text-gray-600 transition cursor-pointer"
                    title="Edit AR"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="contactToDelete = c"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-red-50 text-red-600 hover:border-red-200 transition cursor-pointer"
                    title="Hapus AR"
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
            {{ formData.id ? 'Edit AR' : 'Tambah AR' }} {{ area.label }}
          </h3>
          <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-medium text-gray-900 mb-1">Region <span class="text-rose-500">*</span></label>
            <select
              v-model="formData.region"
              required
              class="w-full h-9 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer"
            >
              <option value="" disabled>Pilih region</option>
              <option v-for="region in modalRegionOptions" :key="region.name" :value="region.name" :disabled="region.taken">
                {{ region.name }}{{ region.taken ? ' (sudah ada AR)' : '' }}
              </option>
            </select>
            <p class="mt-1 text-[11px] text-gray-500">Region diambil dari Master Region {{ area.label }}.</p>
          </div>

          <div>
            <label class="block font-medium text-gray-900 mb-1">Nama AR <span class="text-rose-500">*</span></label>
            <input
              type="text"
              v-model="formData.name"
              required
              placeholder="cth: RINA"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
          </div>

          <div>
            <label class="block font-medium text-gray-900 mb-1">No WhatsApp <span class="text-rose-500">*</span></label>
            <input
              type="tel"
              v-model="formData.whatsapp"
              required
              placeholder="cth: 081234567890"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="ar_is_active"
              v-model="formData.is_active"
              class="w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-0 cursor-pointer"
            />
            <label for="ar_is_active" class="text-xs text-gray-800 cursor-pointer select-none">
              Aktif (menerima WhatsApp dari telemarketing)
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
              {{ isSaving ? 'Menyimpan...' : 'Simpan AR' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Hapus -->
    <div v-if="contactToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus AR</h3>
        <p class="text-xs text-gray-600">
          Hapus AR <strong class="text-gray-900">{{ contactToDelete.name }}</strong> untuk region
          <strong class="text-gray-900">{{ contactToDelete.region }}</strong>? WhatsApp dealer region ini akan kembali ke nomor AR default.
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="contactToDelete = null"
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
import AreaTabs from '@/components/AreaTabs.vue';
import { useArea } from '@/composables/useArea';
import { downloadExport } from '@/lib/download';
import {
  ChevronRight as ChevronRightIcon,
  Search as SearchIcon,
  X as XIcon,
  Pencil as PencilIcon,
  Trash2 as Trash2Icon,
} from 'lucide-vue-next';

const { area } = useArea();

const contacts = ref([]);
const regionNames = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');

const isModalOpen = ref(false);
const isSaving = ref(false);
const errorMessage = ref('');
const contactToDelete = ref(null);
const isDeleting = ref(false);

const formData = reactive({
  id: null,
  region: '',
  name: '',
  whatsapp: '',
  is_active: true,
});

const filteredContacts = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return contacts.value;
  return contacts.value.filter(c => [c.region, c.name, c.whatsapp].some(v => (v || '').toLowerCase().includes(q)));
});

const regionsWithoutAr = computed(() => {
  const covered = new Set(contacts.value.filter(c => c.is_active).map(c => c.region));
  return regionNames.value.filter(name => !covered.has(name));
});

// Region Master Region area ini; region yang sudah punya AR (selain yang sedang diedit) tidak bisa dipilih lagi
const modalRegionOptions = computed(() => {
  const names = new Set(regionNames.value);
  if (formData.region) names.add(formData.region);
  const taken = new Set(contacts.value.filter(c => c.id !== formData.id).map(c => c.region));
  return [...names].sort().map(name => ({ name, taken: taken.has(name) }));
});

const fetchData = async () => {
  isLoading.value = true;
  try {
    const [contactRes, regionRes] = await Promise.all([
      axios.get('/api/ar-contacts'),
      axios.get('/api/regions/options'),
    ]);
    contacts.value = contactRes.data?.data || [];
    regionNames.value = regionRes.data?.data || [];
  } catch (err) {
    console.error('Failed to fetch AR contacts:', err);
  } finally {
    isLoading.value = false;
  }
};

const openCreateModal = () => {
  formData.id = null;
  formData.region = '';
  formData.name = '';
  formData.whatsapp = '';
  formData.is_active = true;
  errorMessage.value = '';
  isModalOpen.value = true;
};

const openEditModal = (c) => {
  formData.id = c.id;
  formData.region = c.region;
  formData.name = c.name;
  formData.whatsapp = c.whatsapp;
  formData.is_active = c.is_active !== false;
  errorMessage.value = '';
  isModalOpen.value = true;
};

const submitForm = async () => {
  isSaving.value = true;
  errorMessage.value = '';
  try {
    const payload = {
      region: formData.region,
      name: formData.name,
      whatsapp: formData.whatsapp,
      is_active: formData.is_active,
    };
    if (formData.id) {
      await axios.put(`/api/ar-contacts/${formData.id}`, payload);
    } else {
      await axios.post('/api/ar-contacts', payload);
    }
    isModalOpen.value = false;
    fetchData();
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal menyimpan AR.';
  } finally {
    isSaving.value = false;
  }
};

const toggleStatus = async (c) => {
  try {
    const res = await axios.patch(`/api/ar-contacts/${c.id}/toggle-status`);
    c.is_active = res.data?.data?.is_active ?? !c.is_active;
  } catch (err) {
    alert('Gagal mengubah status AR');
  }
};

const executeDelete = async () => {
  isDeleting.value = true;
  try {
    await axios.delete(`/api/ar-contacts/${contactToDelete.value.id}`);
    contactToDelete.value = null;
    fetchData();
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus AR.');
  } finally {
    isDeleting.value = false;
  }
};

onMounted(fetchData);
</script>
