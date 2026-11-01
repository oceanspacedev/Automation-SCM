<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master Brand</span>
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Master Brand</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Brand program (Realme, Oppo, ...). Brand aktif muncul di menu Draft, Invoice, Form Program, dan Data Program, masing-masing dengan Jawa Barat & Jawa Tengah.
        </p>
      </div>

      <button
        type="button"
        @click="openCreateModal"
        class="h-9 px-3.5 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
      >
        Tambah Brand
      </button>
    </div>

    <p v-if="actionMessage" class="text-xs" :class="actionError ? 'text-red-600' : 'text-emerald-700'">{{ actionMessage }}</p>

    <!-- Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <div class="p-3 sm:px-4 border-b border-gray-200 text-xs text-gray-600 font-medium">
        Total: {{ brands.length }} brand
      </div>

      <!-- Layar kecil: satu kartu per brand -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="b in brands" :key="b.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 break-words">{{ b.name }}</p>
                <p class="text-gray-500 mt-0.5">/form/{{ b.slug }}/jabar</p>
              </div>
              <button
                type="button"
                @click="toggleStatus(b)"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer shrink-0"
                :class="b.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="b.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                <span>{{ b.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </button>
            </div>
            <div class="flex items-center justify-end gap-1.5">
              <button type="button" @click="openEditModal(b)" class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer">
                Edit
              </button>
              <button type="button" @click="brandToDelete = b" class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-red-600 font-medium cursor-pointer">
                Hapus
              </button>
            </div>
          </li>
        </ul>
      </div>

      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-12 border-r border-gray-200">No</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">NAMA BRAND</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">KODE URL</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">FORM PUBLIK</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">URUTAN</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading">
              <td colspan="7" class="py-8 text-center text-gray-500">Memuat data...</td>
            </tr>
            <tr v-else v-for="(b, index) in brands" :key="b.id" class="hover:bg-gray-50/80 transition">
              <td class="py-2 px-3 text-center text-gray-500 border-r border-gray-200">{{ index + 1 }}</td>
              <td class="py-2 px-3 font-medium text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ b.name }}</td>
              <td class="py-2 px-3 text-gray-700 border-r border-gray-200 whitespace-nowrap">{{ b.slug }}</td>
              <td class="py-2 px-3 text-gray-700 border-r border-gray-200 whitespace-nowrap">/form/{{ b.slug }}/jabar · /form/{{ b.slug }}/jateng</td>
              <td class="py-2 px-3 text-center text-gray-700 border-r border-gray-200">{{ b.sort_order }}</td>
              <td class="py-2 px-3 text-center border-r border-gray-200 whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleStatus(b)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium transition cursor-pointer"
                  :class="b.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="b.is_active ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                  <span>{{ b.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
              </td>
              <td class="py-2 px-3 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <button
                    type="button"
                    @click="openEditModal(b)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-gray-100 text-gray-600 transition cursor-pointer"
                    title="Edit brand"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="brandToDelete = b"
                    class="h-7 w-7 inline-flex items-center justify-center rounded border border-gray-200 hover:bg-red-50 text-red-600 hover:border-red-200 transition cursor-pointer"
                    title="Hapus brand"
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
          <h3 class="text-sm font-bold text-gray-950">{{ formData.id ? 'Edit Brand' : 'Tambah Brand' }}</h3>
          <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-medium text-gray-900 mb-1">Nama Brand <span class="text-rose-500">*</span></label>
            <input
              type="text"
              v-model="formData.name"
              required
              placeholder="cth: Oppo"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
            <p class="mt-1 text-[11px] text-gray-500">
              <template v-if="formData.id">Kode URL tetap <strong class="text-gray-700">{{ formData.slug }}</strong> agar link lama tidak rusak.</template>
              <template v-else>Kode URL dibuat otomatis dari nama, mis. Oppo → /form/oppo/jabar.</template>
            </p>
          </div>

          <div>
            <label class="block font-medium text-gray-900 mb-1">Urutan di menu</label>
            <input
              type="number"
              min="0"
              v-model.number="formData.sort_order"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="brand_is_active"
              v-model="formData.is_active"
              class="w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-0 cursor-pointer"
            />
            <label for="brand_is_active" class="text-xs text-gray-800 cursor-pointer select-none">Aktif (tampil di menu)</label>
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
              {{ isSaving ? 'Menyimpan...' : 'Simpan Brand' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Hapus -->
    <div v-if="brandToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Brand</h3>
        <p class="text-xs text-gray-600">
          Hapus brand <strong class="text-gray-900">{{ brandToDelete.name }}</strong>? Brand yang sudah punya data tidak bisa dihapus, cukup dinonaktifkan.
        </p>
        <p v-if="deleteError" class="text-[11px] text-red-600">{{ deleteError }}</p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="closeDeleteModal"
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
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { loadBrands } from '@/composables/useBrands';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import {
  ChevronRight as ChevronRightIcon,
  X as XIcon,
  Pencil as PencilIcon,
  Trash2 as Trash2Icon,
} from 'lucide-vue-next';

const brands = ref([]);
const isLoading = ref(false);
const actionMessage = ref('');
const actionError = ref(false);

const isModalOpen = ref(false);
const isSaving = ref(false);
const errorMessage = ref('');
const brandToDelete = ref(null);
const isDeleting = ref(false);
const deleteError = ref('');

const formData = reactive({
  id: null,
  slug: '',
  name: '',
  sort_order: 0,
  is_active: true,
});

// Muat ulang daftar brand halaman ini sekaligus menu (yang memakai daftar brand yang sama)
const fetchBrands = async ({ silent = false } = {}) => {
  if (!silent) isLoading.value = true;
  try {
    brands.value = await loadBrands(true);
  } finally {
    if (!silent) isLoading.value = false;
  }
};

useAutoRefresh(() => fetchBrands({ silent: true }), {
  isPaused: () => isLoading.value || isModalOpen.value || brandToDelete.value !== null || isSaving.value || isDeleting.value,
});

const showActionMessage = (message, isError = false) => {
  actionMessage.value = message;
  actionError.value = isError;
};

const openCreateModal = () => {
  Object.assign(formData, { id: null, slug: '', name: '', sort_order: brands.value.length, is_active: true });
  errorMessage.value = '';
  isModalOpen.value = true;
};

const openEditModal = (b) => {
  Object.assign(formData, { id: b.id, slug: b.slug, name: b.name, sort_order: b.sort_order ?? 0, is_active: b.is_active !== false });
  errorMessage.value = '';
  isModalOpen.value = true;
};

const submitForm = async () => {
  isSaving.value = true;
  errorMessage.value = '';
  try {
    const payload = { name: formData.name, sort_order: formData.sort_order || 0, is_active: formData.is_active };
    const res = formData.id
      ? await axios.put(`/api/brands/${formData.id}`, payload)
      : await axios.post('/api/brands', payload);
    isModalOpen.value = false;
    showActionMessage(res.data?.message || 'Brand berhasil disimpan.');
    fetchBrands();
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal menyimpan brand.';
  } finally {
    isSaving.value = false;
  }
};

const toggleStatus = async (b) => {
  try {
    const res = await axios.patch(`/api/brands/${b.id}/toggle-status`);
    showActionMessage(res.data?.message || 'Status brand diubah.');
    fetchBrands();
  } catch (err) {
    showActionMessage(err.response?.data?.message || 'Gagal mengubah status brand.', true);
  }
};

const closeDeleteModal = () => {
  brandToDelete.value = null;
  deleteError.value = '';
};

const executeDelete = async () => {
  if (!brandToDelete.value) return;
  isDeleting.value = true;
  deleteError.value = '';
  try {
    const res = await axios.delete(`/api/brands/${brandToDelete.value.id}`);
    closeDeleteModal();
    showActionMessage(res.data?.message || 'Brand berhasil dihapus.');
    fetchBrands();
  } catch (err) {
    deleteError.value = err.response?.data?.message || 'Gagal menghapus brand.';
  } finally {
    isDeleting.value = false;
  }
};

onMounted(fetchBrands);
</script>
