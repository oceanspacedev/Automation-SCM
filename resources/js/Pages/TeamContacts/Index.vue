<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Tim Pajak & SCM</span>
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Tim Pajak & SCM</h1>
        <p class="text-xs text-gray-500 mt-0.5">Penerima WhatsApp cek pajak dan hasilnya, untuk semua area.</p>
      </div>

      <div v-if="canEdit" class="grid grid-cols-1 sm:block">
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-blue-600 bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span>Tambah Kontak</span>
        </button>
      </div>
    </div>

    <p v-if="!isLoading && teamsWithoutContact.length" class="text-xs text-amber-700">
      {{ teamsWithoutContact.join(' & ') }} belum punya kontak aktif, WhatsApp-nya belum bisa dikirim.
    </p>

    <!-- Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ filteredContacts.length }} data</span>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:flex sm:items-center">
          <div class="relative sm:min-w-[130px]">
            <select
              v-model="filterTeam"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="all">Semua Tim</option>
              <option v-for="(label, value) in TEAM_LABELS" :key="value" :value="value">{{ label }}</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <div class="relative w-full sm:w-64">
            <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Cari nama, no. WA..."
              class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-7 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per kontak -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="filteredContacts.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">Tidak ada data kontak.</p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="c in filteredContacts" :key="c.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 break-words">{{ c.name }}</p>
                <p class="mt-0.5 text-gray-500">{{ TEAM_LABELS[c.team] }}</p>
              </div>
              <button
                v-if="canEdit"
                type="button"
                @click="toggleStatus(c)"
                class="inline-flex items-center gap-1.5 shrink-0 font-medium cursor-pointer"
                :class="c.is_active ? 'text-gray-900' : 'text-gray-500'"
                :title="c.is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="c.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
              </button>
              <span v-else class="inline-flex items-center gap-1.5 shrink-0 font-medium" :class="c.is_active ? 'text-gray-900' : 'text-gray-500'">
                <span class="w-1.5 h-1.5 rounded-full" :class="c.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
            <div class="flex items-end justify-between gap-3">
              <p class="min-w-0 text-gray-500 break-words">{{ formatPhone(c.whatsapp) }}</p>
              <div v-if="canEdit" class="flex items-center gap-1.5 shrink-0">
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
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Tim</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">No. WhatsApp</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th v-if="canEdit" scope="col" class="py-2.5 px-3 text-center w-28">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="isLoading" class="text-center">
              <td :colspan="canEdit ? 6 : 5" class="py-10 text-gray-500">Memuat data...</td>
            </tr>
            <tr v-else-if="filteredContacts.length === 0" class="text-center">
              <td :colspan="canEdit ? 6 : 5" class="py-10 text-gray-500">Tidak ada data kontak.</td>
            </tr>
            <tr v-else v-for="(c, index) in filteredContacts" :key="c.id" class="hover:bg-gray-50/70 text-gray-800">
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-600">{{ index + 1 }}</td>
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap font-medium text-gray-950">{{ c.name }}</td>
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">{{ TEAM_LABELS[c.team] }}</td>
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">{{ formatPhone(c.whatsapp) }}</td>
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <button
                  v-if="canEdit"
                  type="button"
                  @click="toggleStatus(c)"
                  class="cursor-pointer text-xs font-medium transition hover:underline"
                  :class="c.is_active ? 'text-gray-900' : 'text-gray-500'"
                  :title="c.is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan'"
                >
                  {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
                </button>
                <span v-else class="text-xs font-medium" :class="c.is_active ? 'text-gray-900' : 'text-gray-500'">
                  {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td v-if="canEdit" class="py-2.5 px-3 text-center whitespace-nowrap">
                <div class="inline-flex items-center justify-center gap-1">
                  <button
                    type="button"
                    @click="openEditModal(c)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition cursor-pointer"
                    title="Edit kontak"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="contactToDelete = c"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                    title="Hapus kontak"
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
          <h3 class="text-sm font-bold text-gray-950">{{ formData.id ? 'Edit Kontak' : 'Tambah Kontak' }}</h3>
          <button type="button" @click="isModalOpen = false" :disabled="isSaving" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-medium text-gray-900 mb-1">Tim <span class="text-rose-500">*</span></label>
            <select
              v-model="formData.team"
              required
              class="w-full h-9 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer"
            >
              <option v-for="(label, value) in TEAM_LABELS" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>

          <div>
            <label class="block font-medium text-gray-900 mb-1">Nama <span class="text-rose-500">*</span></label>
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
              id="team_contact_is_active"
              v-model="formData.is_active"
              class="w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-0 cursor-pointer"
            />
            <label for="team_contact_is_active" class="text-xs text-gray-800 cursor-pointer select-none">Aktif (menerima WhatsApp)</label>
          </div>

          <p v-if="errorMessage" class="text-red-600">{{ errorMessage }}</p>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button
              type="button"
              @click="isModalOpen = false"
              :disabled="isSaving"
              class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium cursor-pointer disabled:opacity-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer disabled:opacity-50"
            >
              {{ isSaving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Hapus -->
    <div v-if="contactToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Kontak</h3>
        <p class="text-xs text-gray-600">
          Hapus <strong class="text-gray-900">{{ contactToDelete.name }}</strong> dari {{ TEAM_LABELS[contactToDelete.team] }}?
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="contactToDelete = null"
            :disabled="isDeleting"
            class="h-8 px-3 rounded-lg border border-gray-300 hover:bg-gray-50 text-xs font-medium text-gray-700 cursor-pointer disabled:opacity-50"
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
import { useAuth } from '@/composables/useAuth';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import {
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  Search as SearchIcon,
  X as XIcon,
  Pencil as PencilIcon,
  Trash2 as Trash2Icon,
} from 'lucide-vue-next';

// Tim Pajak menerima WA cek PPh setelah AR memotong klaim; Tim SCM menerima hasil cek pajaknya
const TEAM_LABELS = { tax: 'Tim Pajak', scm: 'Tim SCM' };

const { canEdit } = useAuth();

const contacts = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const filterTeam = ref('all');

const isModalOpen = ref(false);
const isSaving = ref(false);
const errorMessage = ref('');
const contactToDelete = ref(null);
const isDeleting = ref(false);

const formData = reactive({ id: null, team: 'tax', name: '', whatsapp: '', is_active: true });

// Nomor WA ditulis seperti di Master User: 081224290502 → +6281224290502
const formatPhone = (phone) => {
  let digits = String(phone || '').replace(/\D/g, '');
  if (!digits) return '-';
  if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
  return `+${digits}`;
};

const filteredContacts = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return contacts.value.filter((c) => (filterTeam.value === 'all' || c.team === filterTeam.value)
    && (!q || [c.name, c.whatsapp].some((v) => (v || '').toLowerCase().includes(q))));
});

// Tim tanpa kontak aktif tidak bisa dikirimi WhatsApp
const teamsWithoutContact = computed(() => Object.entries(TEAM_LABELS)
  .filter(([team]) => !contacts.value.some((c) => c.team === team && c.is_active))
  .map(([, label]) => label));

const fetchData = async ({ silent = false } = {}) => {
  if (!silent) isLoading.value = true;
  try {
    const res = await axios.get('/api/team-contacts');
    contacts.value = res.data?.data || [];
  } catch (err) {
    console.error('Failed to fetch team contacts:', err);
  } finally {
    isLoading.value = false;
  }
};

useAutoRefresh(() => fetchData({ silent: true }), {
  isPaused: () => isLoading.value || isModalOpen.value || contactToDelete.value !== null || isSaving.value || isDeleting.value,
});

const openCreateModal = () => {
  if (!canEdit.value) return;
  Object.assign(formData, { id: null, team: filterTeam.value === 'scm' ? 'scm' : 'tax', name: '', whatsapp: '', is_active: true });
  errorMessage.value = '';
  isModalOpen.value = true;
};

const openEditModal = (c) => {
  if (!canEdit.value) return;
  Object.assign(formData, { id: c.id, team: c.team, name: c.name, whatsapp: c.whatsapp, is_active: c.is_active !== false });
  errorMessage.value = '';
  isModalOpen.value = true;
};

const submitForm = async () => {
  if (!canEdit.value) return;
  isSaving.value = true;
  errorMessage.value = '';
  try {
    const payload = { team: formData.team, name: formData.name, whatsapp: formData.whatsapp, is_active: formData.is_active };
    const res = formData.id
      ? await axios.put(`/api/team-contacts/${formData.id}`, payload)
      : await axios.post('/api/team-contacts', payload);
    notify.success(res.data?.message || 'Tersimpan.');
    isModalOpen.value = false;
    fetchData({ silent: true });
  } catch (err) {
    const errors = err.response?.data?.errors;
    errorMessage.value = errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || 'Gagal menyimpan kontak.');
  } finally {
    isSaving.value = false;
  }
};

const toggleStatus = async (c) => {
  if (!canEdit.value) return;
  try {
    const res = await axios.patch(`/api/team-contacts/${c.id}/toggle-status`);
    c.is_active = res.data?.data?.is_active ?? !c.is_active;
  } catch (err) {
    notify.error('Gagal mengubah status kontak.');
  }
};

const executeDelete = async () => {
  if (!canEdit.value) return;
  isDeleting.value = true;
  try {
    await axios.delete(`/api/team-contacts/${contactToDelete.value.id}`);
    contactToDelete.value = null;
    fetchData({ silent: true });
  } catch (err) {
    notify.error(err.response?.data?.message || 'Gagal menghapus kontak.');
  } finally {
    isDeleting.value = false;
  }
};

onMounted(fetchData);
</script>
