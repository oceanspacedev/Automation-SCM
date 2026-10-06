<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master User</span>
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Master User</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar akun pengguna, peran, dan pengaturan izin hak akses modul.
        </p>
      </div>

      <div>
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-3.5 inline-flex items-center gap-1.5 rounded-lg border border-blue-600 bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium shadow-2xs transition cursor-pointer"
        >
          <span>Tambah Pengguna</span>
        </button>
      </div>
    </div>

    <!-- Pending Approval Banner (Clean & Subtle) -->
    <div
      v-if="pendingCount > 0"
      class="rounded-lg border border-amber-200 bg-amber-50/70 p-2.5 px-3.5 flex items-center justify-between text-xs text-amber-900"
    >
      <div class="flex items-center gap-2">
        <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
        <span>Terdapat <strong>{{ pendingCount }}</strong> akun pendaftar baru menunggu persetujuan (ACC).</span>
      </div>
      <button
        type="button"
        @click="filterStatus = filterStatus === 'inactive' ? 'all' : 'inactive'"
        class="text-xs font-semibold text-amber-900 underline hover:text-black cursor-pointer"
      >
        {{ filterStatus === 'inactive' ? 'Tampilkan Semua' : 'Filter Menunggu ACC' }}
      </button>
    </div>

    <!-- Table Card (Clean spreadsheet aesthetic) -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ filteredUsers.length }} data</span>
        </div>

        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
          <!-- Role Filter -->
          <div class="relative min-w-[130px]">
            <select
              v-model="filterRole"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="all">Semua Peran</option>
              <option value="admin">Admin</option>
              <option value="scm">SCM</option>
              <option value="ar">AR</option>
              <option value="telemarketing">Telemarketing</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <!-- Status Filter -->
          <div class="relative min-w-[160px]">
            <select
              v-model="filterStatus"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="all">Semua Status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Menunggu ACC / Nonaktif</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <!-- Search Input -->
          <div class="relative w-full sm:w-64">
            <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Cari nama, email, no. WA..."
              class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-7 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Clean Ordinary Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-12 border-r border-gray-200">No</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Email</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Peran</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Hak Akses Modul</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">No. WhatsApp</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-center w-28">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="isLoading" class="text-center">
              <td colspan="8" class="py-10 text-gray-500">
                <RefreshCwIcon class="w-4 h-4 animate-spin mx-auto mb-1 text-gray-400" />
                <span>Memuat data...</span>
              </td>
            </tr>

            <tr v-else-if="filteredUsers.length === 0" class="text-center">
              <td colspan="8" class="py-10 text-gray-500">
                <span>Tidak ada data pengguna.</span>
              </td>
            </tr>

            <tr
              v-else
              v-for="(user, idx) in filteredUsers"
              :key="user.id"
              class="hover:bg-gray-50/70 text-gray-800"
            >
              <!-- 1. No -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-600">
                {{ idx + 1 }}
              </td>

              <!-- 2. Nama -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap font-medium text-gray-950">
                <span>{{ user.name }}</span>
                <span v-if="currentUser?.id === user.id" class="text-gray-400 font-normal ml-1">(Anda)</span>
              </td>

              <!-- 3. Email -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ user.email }}
              </td>

              <!-- 4. Peran -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ formatRole(user.role) }}
              </td>

              <!-- 5. Hak Akses Modul -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal break-words leading-relaxed text-gray-700 min-w-[240px] max-w-[340px]">
                {{ formatPermissionsDetail(user) || '-' }}
              </td>

              <!-- 6. No. WhatsApp -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                <span v-if="user.whatsapp">+{{ user.whatsapp }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 7. Status -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-700">
                <button
                  type="button"
                  @click="handleToggleStatus(user)"
                  :disabled="currentUser?.id === user.id && user.is_active"
                  class="cursor-pointer text-xs transition disabled:cursor-not-allowed hover:underline"
                  :class="user.is_active ? 'text-gray-900 font-medium' : 'text-amber-700 font-medium'"
                  :title="user.is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan / ACC'"
                >
                  {{ user.is_active ? 'Aktif' : 'Menunggu ACC' }}
                </button>
              </td>

              <!-- 8. Aksi -->
              <td class="py-2.5 px-3 text-center whitespace-nowrap text-gray-700">
                <div class="inline-flex items-center justify-center gap-1">
                  <!-- Tombol ACC / Setujui (Hanya untuk akun nonaktif / pending) -->
                  <button
                    v-if="!user.is_active"
                    type="button"
                    @click="handleApproveUser(user)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 transition cursor-pointer"
                    title="Setujui (ACC) Pengguna"
                  >
                    <CheckIcon class="w-3.5 h-3.5" />
                  </button>

                  <!-- Tombol Edit -->
                  <button
                    type="button"
                    @click="openEditModal(user)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition cursor-pointer"
                    title="Edit Pengguna"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>

                  <!-- Tombol Hapus -->
                  <button
                    type="button"
                    @click="confirmDeleteUser(user)"
                    :disabled="currentUser?.id === user.id"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer disabled:opacity-20 disabled:cursor-not-allowed"
                    title="Hapus Pengguna"
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

    <!-- ================= MODAL TAMBAH / EDIT USER ================= -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-lg w-full p-5 shadow-lg border border-gray-200 space-y-3.5">
        <!-- Header Modal -->
        <div class="flex items-center justify-between border-b border-gray-200 pb-2.5">
          <h3 class="text-sm font-bold text-gray-950">
            {{ isEditMode ? 'Edit Pengguna' : 'Tambah Pengguna' }}
          </h3>
          <button
            type="button"
            @click="isModalOpen = false"
            class="text-gray-400 hover:text-gray-600 cursor-pointer"
          >
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Form Body -->
        <form @submit.prevent="submitUserForm" class="space-y-3 text-xs">
          <div v-if="formError" class="p-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
            {{ formError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Nama Lengkap -->
            <div>
              <label class="block font-medium text-gray-700 mb-1">Nama Lengkap</label>
              <input
                v-model="userForm.name"
                type="text"
                required
                placeholder="Nama pengguna"
                class="w-full h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>

            <!-- Email -->
            <div>
              <label class="block font-medium text-gray-700 mb-1">Email</label>
              <input
                v-model="userForm.email"
                type="email"
                required
                placeholder="email@perusahaan.com"
                class="w-full h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Nomor WhatsApp -->
            <div>
              <label class="block font-medium text-gray-700 mb-1">No. WhatsApp</label>
              <input
                v-model="userForm.whatsapp"
                type="text"
                placeholder="08xxxxxxxxxx"
                class="w-full h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>

            <!-- Pilihan Role -->
            <div>
              <label class="block font-medium text-gray-700 mb-1">Peran (Role)</label>
              <div class="relative">
                <select
                  v-model="userForm.role"
                  @change="handleRoleChange"
                  required
                  class="w-full h-8 px-2.5 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                >
                  <option value="admin">Admin</option>
                  <option value="scm">SCM</option>
                  <option value="ar">AR</option>
                  <option value="telemarketing">Telemarketing</option>
                </select>
                <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
              </div>
            </div>
          </div>

          <!-- Hak Akses (User Permissions) - Compact Grid No Scroll -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-medium text-gray-700">Hak Akses Modul</label>
              <button
                type="button"
                @click="toggleAllPermissions"
                class="text-[11px] text-gray-500 hover:text-black underline cursor-pointer"
              >
                {{ areAllPermissionsChecked ? 'Hapus Semua' : 'Pilih Semua' }}
              </button>
            </div>
            <div class="p-2.5 rounded-lg border border-gray-200 bg-gray-50/50 grid grid-cols-2 sm:grid-cols-3 gap-2">
              <label
                v-for="(label, key) in permissionList"
                :key="key"
                class="flex items-center gap-2 cursor-pointer text-gray-700 hover:text-gray-950 select-none py-0.5"
              >
                <input
                  type="checkbox"
                  :value="key"
                  v-model="userForm.permissions"
                  class="rounded border-gray-300 text-gray-900 focus:ring-gray-900 cursor-pointer"
                />
                <span class="text-xs">{{ label }}</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
            <!-- Password -->
            <div>
              <label class="block font-medium text-gray-700 mb-1">
                Password {{ isEditMode ? '(Opsional)' : '' }}
              </label>
              <input
                v-model="userForm.password"
                type="password"
                :required="!isEditMode"
                placeholder="Minimal 6 karakter"
                class="w-full h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>

            <!-- Status Aktif / Nonaktif -->
            <div class="pt-5 flex items-center gap-2">
              <input
                id="isActiveCheck"
                type="checkbox"
                v-model="userForm.is_active"
                class="rounded border-gray-300 text-gray-900 focus:ring-gray-900 cursor-pointer"
              />
              <label for="isActiveCheck" class="text-xs text-gray-700 font-medium cursor-pointer select-none">
                {{ userForm.is_active ? 'Status: Aktif (ACC Disetujui)' : 'Status: Menunggu Persetujuan (ACC)' }}
              </label>
            </div>
          </div>

          <!-- Actions Button -->
          <div class="pt-2 flex items-center justify-end gap-2 border-t border-gray-100">
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
              class="h-8 px-4 rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-xs font-medium cursor-pointer disabled:opacity-50"
            >
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL KONFIRMASI HAPUS ================= -->
    <div
      v-if="userToDelete"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Pengguna</h3>
        <p class="text-xs text-gray-600">
          Hapus pengguna <strong class="text-gray-900">{{ userToDelete.name }}</strong> ({{ userToDelete.email }})?
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="userToDelete = null"
            class="h-8 px-3 rounded-lg border border-gray-300 hover:bg-gray-50 text-xs font-medium text-gray-700 cursor-pointer"
          >
            Batal
          </button>
          <button
            type="button"
            @click="executeDeleteUser"
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import {
  ChevronRightIcon,
  ChevronDownIcon,
  SearchIcon,
  XIcon,
  RefreshCwIcon,
  Check as CheckIcon,
  Pencil as PencilIcon,
  Trash2 as Trash2Icon,
} from 'lucide-vue-next';

const { user: currentUser } = useAuth();

// Permission List Mapping (Clean & Simple)
const permissionList = {
  drafts: 'Draft',
  invoices: 'Invoice',
  form_program: 'Form Program',
  data_program: 'Data Program',
  riwayat_program: 'Riwayat Program',
  email_logs: 'Riwayat Email',
  dashboard: 'Dashboard',
  master_user: 'Master User',
};

const roleDefaults = {
  admin: ['drafts', 'invoices', 'form_program', 'data_program', 'riwayat_program', 'email_logs', 'dashboard', 'master_user'],
  scm: ['drafts', 'invoices', 'form_program', 'data_program', 'riwayat_program', 'email_logs', 'dashboard', 'master_user'],
  ar: ['drafts', 'invoices', 'riwayat_program', 'dashboard'],
  telemarketing: ['form_program', 'riwayat_program'],
};

// State
const users = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const filterRole = ref('all');
const filterStatus = ref('all');

// Modal Form State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isSaving = ref(false);
const formError = ref('');
const userForm = ref({
  id: null,
  name: '',
  email: '',
  whatsapp: '',
  role: 'scm',
  permissions: [],
  password: '',
  is_active: true,
});

// Delete Modal State
const userToDelete = ref(null);
const isDeleting = ref(false);

// Filtered Users Computed
const filteredUsers = computed(() => {
  return users.value.filter((u) => {
    if (filterRole.value !== 'all' && u.role !== filterRole.value) {
      return false;
    }
    if (filterStatus.value === 'active' && !u.is_active) {
      return false;
    }
    if (filterStatus.value === 'inactive' && u.is_active) {
      return false;
    }
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const matchName = (u.name || '').toLowerCase().includes(q);
      const matchEmail = (u.email || '').toLowerCase().includes(q);
      const matchWa = (u.whatsapp || '').includes(q);
      return matchName || matchEmail || matchWa;
    }
    return true;
  });
});

const pendingCount = computed(() => {
  return users.value.filter((u) => !u.is_active).length;
});

const areAllPermissionsChecked = computed(() => {
  const allKeys = Object.keys(permissionList);
  return allKeys.every((k) => userForm.value.permissions.includes(k));
});

const toggleAllPermissions = () => {
  if (areAllPermissionsChecked.value) {
    userForm.value.permissions = [];
  } else {
    userForm.value.permissions = Object.keys(permissionList);
  }
};

const handleRoleChange = () => {
  const defaults = roleDefaults[userForm.value.role];
  if (defaults) {
    userForm.value.permissions = [...defaults];
  }
};

// Fetch Data
const fetchUsers = async () => {
  isLoading.value = true;
  try {
    const res = await axios.get('/api/users');
    if (res.data?.success) {
      users.value = res.data.data || [];
    }
  } catch (err) {
    console.error('Gagal memuat master user:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchUsers();
});

// Modal Actions
const openCreateModal = () => {
  isEditMode.value = false;
  formError.value = '';
  userForm.value = {
    id: null,
    name: '',
    email: '',
    whatsapp: '',
    role: 'scm',
    permissions: [...roleDefaults.scm],
    password: '',
    is_active: true,
  };
  isModalOpen.value = true;
};

const openEditModal = (user) => {
  isEditMode.value = true;
  formError.value = '';

  // Resolve user permissions or fallback to role defaults
  let perms = [];
  if (Array.isArray(user.permissions) && user.permissions.length > 0) {
    perms = [...user.permissions];
  } else if (roleDefaults[user.role]) {
    perms = [...roleDefaults[user.role]];
  }

  userForm.value = {
    id: user.id,
    name: user.name || '',
    email: user.email || '',
    whatsapp: user.whatsapp || '',
    role: user.role || 'scm',
    permissions: perms,
    password: '',
    is_active: Boolean(user.is_active),
  };
  isModalOpen.value = true;
};

const submitUserForm = async () => {
  isSaving.value = true;
  formError.value = '';
  try {
    if (isEditMode.value) {
      await axios.put(`/api/users/${userForm.value.id}`, userForm.value);
    } else {
      await axios.post('/api/users', userForm.value);
    }
    isModalOpen.value = false;
    await fetchUsers();
  } catch (err) {
    const msg = err.response?.data?.message || err.response?.data?.error || 'Gagal menyimpan data pengguna.';
    formError.value = msg;
  } finally {
    isSaving.value = false;
  }
};

const handleToggleStatus = async (user) => {
  try {
    const res = await axios.patch(`/api/users/${user.id}/toggle-status`);
    if (res.data?.success) {
      user.is_active = res.data.data.is_active;
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal mengubah status pengguna.');
  }
};

const handleApproveUser = async (user) => {
  try {
    const res = await axios.patch(`/api/users/${user.id}/approve`);
    if (res.data?.success) {
      user.is_active = true;
      await fetchUsers();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menyetujui akun pengguna.');
  }
};

const confirmDeleteUser = (user) => {
  userToDelete.value = user;
};

const executeDeleteUser = async () => {
  if (!userToDelete.value) return;
  isDeleting.value = true;
  try {
    await axios.delete(`/api/users/${userToDelete.value.id}`);
    userToDelete.value = null;
    await fetchUsers();
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus pengguna.');
  } finally {
    isDeleting.value = false;
  }
};

const formatRole = (role) => {
  switch (role) {
    case 'admin':
      return 'Admin';
    case 'scm':
      return 'SCM';
    case 'ar':
      return 'AR';
    case 'telemarketing':
      return 'Telemarketing';
    default:
      return role || '-';
  }
};

const getUserPermissions = (user) => {
  if (Array.isArray(user.permissions) && user.permissions.length > 0) {
    return user.permissions;
  }
  return roleDefaults[user.role] || [];
};

const formatPermissionsSummary = (user) => {
  const perms = getUserPermissions(user);
  const total = Object.keys(permissionList).length;
  if (perms.length >= total) {
    return 'Semua Modul';
  }
  if (perms.length === 0) {
    return 'Tidak ada akses';
  }
  return `${perms.length} Modul Terpilih`;
};

const formatPermissionsDetail = (user) => {
  const perms = getUserPermissions(user);
  return perms.map((p) => permissionList[p] || p).join(', ');
};
</script>
