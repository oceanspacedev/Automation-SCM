<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Master Dealer</span>
    </div>

    <AreaTabs />

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Master Dealer</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar data toko/outlet fisik Realme dan ID Realme resmi.
        </p>
      </div>

      <!-- Tombol aksi; di layar kecil tiga tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-3 sm:flex sm:items-center gap-2">
        <button
          type="button"
          @click="isImportModalOpen = true"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Import</span><span class="hidden sm:inline">Import Excel</span>
        </button>
        <button
          type="button"
          @click="downloadExport('/api/dealers/export', { search: searchQuery, region: filterRegion })"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
          title="Export dealer area ini sesuai pencarian & filter (format sama dengan file import CV TOP)"
        >
          <span class="sm:hidden">Export</span><span class="hidden sm:inline">Export Excel</span>
        </button>
        <button
          type="button"
          @click="openCreateModal"
          class="h-9 px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap"
        >
          <span class="sm:hidden">Tambah</span><span class="hidden sm:inline">Tambah Dealer</span>
        </button>
      </div>
    </div>

    <DealerImportModal
      :is-open="isImportModalOpen"
      @close="isImportModalOpen = false"
      @imported="fetchDealers(1); fetchMasterRegions()"
    />

    <!-- Table Card (Matching Form Program Plain Ordinary Table Style) -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ totalRows }} data</span>
        </div>

        <!-- Filter & pencarian; di layar kecil cari di atas, region di bawahnya -->
        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:flex-nowrap">
          <!-- Region Filter -->
          <div class="relative min-w-[140px] col-span-2">
            <select
              v-model="filterRegion"
              @change="fetchDealers(1)"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="all">Semua Region</option>
              <option v-for="r in availableRegions" :key="r" :value="r">{{ r }}</option>
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
              placeholder="Cari ID Realme, nama dealer, BT..."
              class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-7 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''; fetchDealers(1)"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per dealer -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="dealers.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">Tidak ada data dealer yang ditemukan.</p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="d in dealers" :key="d.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 break-words">{{ d.kode_bt || '-' }} · {{ d.dealer_name || '-' }}</p>
                <p class="mt-0.5 text-gray-500 break-words">{{ d.region || '-' }} · NPWP {{ d.npwp_type || '-' }}</p>
              </div>
              <button
                type="button"
                @click="toggleDealerStatus(d)"
                class="text-xs hover:underline cursor-pointer shrink-0"
                :class="d.is_active ? 'text-gray-700' : 'text-gray-400'"
              >
                {{ d.is_active ? 'Aktif' : 'Nonaktif' }}
              </button>
            </div>
            <p class="text-gray-500 break-words">ID Realme {{ d.id_realme || '-' }}<template v-if="d.gs_name"> · {{ d.gs_name }}</template></p>
            <div class="flex items-end justify-between gap-3">
              <p class="min-w-0 text-gray-500 break-words">
                Telemarketing {{ d.telemarketing || '-' }}<template v-if="d.telemarketing_phone"> · {{ d.telemarketing_phone }}</template>
              </p>
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="openEditModal(d)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                >
                  Edit
                </button>
                <button
                  type="button"
                  @click="confirmDelete(d)"
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
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Kode BT</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama Dealer</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">ID Realme / Nama GS</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Jenis NPWP</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Region</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Telemarketing</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Sales</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="isLoading">
              <td colspan="10" class="py-8 text-center text-gray-500">
                Memuat data...
              </td>
            </tr>
            <tr v-else-if="dealers.length === 0">
              <td colspan="10" class="py-8 text-center text-gray-500">
                Tidak ada data dealer yang ditemukan.
              </td>
            </tr>
            <tr
              v-else
              v-for="(d, idx) in dealers"
              :key="d.id"
              class="hover:bg-gray-50/70 text-gray-700 font-normal"
            >
              <!-- 1. No -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                {{ (currentPage - 1) * perPage + idx + 1 }}
              </td>

              <!-- 2. Kode BT -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-900">
                {{ d.kode_bt || '-' }}
              </td>

              <!-- 3. Nama Dealer -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal min-w-[180px] max-w-[260px] break-words leading-snug" :title="d.dealer_name">
                <div class="text-gray-900">{{ d.dealer_name || '-' }}</div>
                <div v-if="d.alias && d.alias !== d.dealer_name" class="text-[11px] text-gray-500">{{ d.alias }}</div>
              </td>

              <!-- 4. ID Realme & Nama GS -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal min-w-[140px] max-w-[220px] break-words leading-snug">
                <div>{{ d.id_realme || '-' }}</div>
                <div v-if="d.gs_name" class="text-[11px] text-gray-500">{{ d.gs_name }}</div>
              </td>

              <!-- Jenis NPWP -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ d.npwp_type || '-' }}
              </td>

              <!-- 5. Region -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ d.region || '-' }}
              </td>

              <!-- 6. Telemarketing -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                <div>{{ d.telemarketing || '-' }}</div>
                <div v-if="d.telemarketing_phone" class="text-[11px] text-gray-500">{{ d.telemarketing_phone }}</div>
              </td>

              <!-- 7. Sales -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ d.sales_person || '-' }}
              </td>

              <!-- 8. Status -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleDealerStatus(d)"
                  class="text-xs hover:underline cursor-pointer"
                  :class="d.is_active ? 'text-gray-700' : 'text-gray-400'"
                >
                  {{ d.is_active ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>

              <!-- 9. Aksi -->
              <td class="py-2.5 px-3 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1">
                  <button
                    type="button"
                    @click="openEditModal(d)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition cursor-pointer"
                    title="Edit Dealer"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="confirmDelete(d)"
                    class="h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                    title="Hapus Dealer"
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
            @change="fetchDealers(1)"
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
            @click="fetchDealers(currentPage - 1)"
            :disabled="currentPage <= 1 || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <ChevronLeftIcon class="w-3.5 h-3.5" />
            <span>Sebelumnya</span>
          </button>
          <button
            type="button"
            @click="fetchDealers(currentPage + 1)"
            :disabled="currentPage >= totalPages || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <span>Selanjutnya</span>
            <ChevronRightIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL TAMBAH / EDIT DEALER ================= -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-2xl w-full p-5 shadow-xl border border-gray-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-gray-200 pb-2.5">
          <h3 class="text-sm font-bold text-gray-950">
            {{ isEditMode ? 'Edit Master Dealer' : 'Tambah Master Dealer' }}
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
            <div>
              <label class="block font-medium text-gray-900 mb-1">
                Kode BT (ID Customer) <span class="text-rose-500">*</span>
              </label>
              <input
                type="text"
                v-model="formData.kode_bt"
                required
                placeholder="cth: BT02657"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 uppercase"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">
                Nama Dealer / Toko <span class="text-rose-500">*</span>
              </label>
              <input
                type="text"
                v-model="formData.dealer_name"
                required
                placeholder="cth: AWEI CELL"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Alias</label>
              <input
                type="text"
                v-model="formData.alias"
                placeholder="Nama panggilan toko"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">ID Realme (ID GS)</label>
              <input
                type="text"
                v-model="formData.id_realme"
                placeholder="cth: DLDF6507"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 uppercase"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Nama GS</label>
              <input
                type="text"
                v-model="formData.gs_name"
                placeholder="Nama toko sesuai ID Realme"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Region</label>
              <input
                type="text"
                v-model="formData.region"
                list="region-datalist"
                placeholder="cth: BIG BANDUNG"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
              <datalist id="region-datalist">
                <option v-for="r in masterRegions" :key="r" :value="r" />
              </datalist>
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Reffnote (DP)</label>
              <input
                type="text"
                v-model="formData.reffnote"
                placeholder="cth: REALME - DP BANDUNG"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
          </div>

          <div class="pt-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block font-medium text-gray-900 mb-1">NPWP / NIK</label>
              <input
                type="text"
                v-model="formData.npwp"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Nama NPWP / NIK</label>
              <input
                type="text"
                v-model="formData.npwp_name"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Jenis NPWP</label>
              <input
                type="text"
                v-model="formData.npwp_type"
                list="npwp-type-datalist"
                placeholder="PRIBADI / BADAN"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 uppercase"
              />
              <datalist id="npwp-type-datalist">
                <option v-for="t in ['PRIBADI', 'PRIBADI PKP', 'BADAN', 'BADAN NON', 'NON']" :key="t" :value="t" />
              </datalist>
            </div>
          </div>

          <div class="pt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-medium text-gray-900 mb-1">Telemarketing</label>
              <input
                type="text"
                v-model="formData.telemarketing"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">No. WA Telemarketing</label>
              <input
                type="text"
                v-model="formData.telemarketing_phone"
                placeholder="08xxxxxxxxxx"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">Sales</label>
              <input
                type="text"
                v-model="formData.sales_person"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
            <div>
              <label class="block font-medium text-gray-900 mb-1">No. WhatsApp Toko</label>
              <input
                type="text"
                v-model="formData.whatsapp"
                placeholder="08xxxxxxxxxx"
                class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
              />
            </div>
          </div>

          <!-- Alamat -->
          <div>
            <label class="block font-medium text-gray-900 mb-1">Alamat Fisik Toko</label>
            <textarea
              v-model="formData.address"
              rows="2"
              placeholder="Alamat toko..."
              class="w-full p-2.5 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            ></textarea>
          </div>

          <!-- Status Aktif Checkbox -->
          <div class="flex items-center gap-2 pt-1">
            <input
              type="checkbox"
              id="is_active_dealer"
              v-model="formData.is_active"
              class="w-4 h-4 rounded text-gray-900 focus:ring-gray-900 border-gray-300 cursor-pointer"
            />
            <label for="is_active_dealer" class="text-xs text-gray-700 cursor-pointer select-none">
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
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Dealer' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL HAPUS ================= -->
    <div
      v-if="dealerToDelete"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-sm w-full p-5 shadow-lg border border-gray-200 space-y-3">
        <h3 class="text-sm font-bold text-gray-950">Hapus Master Dealer</h3>
        <p class="text-xs text-gray-600">
          Hapus dealer <strong class="text-gray-900">{{ dealerToDelete.dealer_name }}</strong> (ID: {{ dealerToDelete.id_realme }})?
        </p>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="dealerToDelete = null"
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
import AreaTabs from '@/components/AreaTabs.vue';
import DealerImportModal from '@/components/DealerImportModal.vue';
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

const isImportModalOpen = ref(false);
const dealers = ref([]);
const totalRows = ref(0);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(25);
const isLoading = ref(false);

const fromRow = computed(() => (totalRows.value === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1));
const toRow = computed(() => Math.min(currentPage.value * perPage.value, totalRows.value));

const availableRegions = ref([]);

// Pilihan region dari Master Region area yang sedang dibuka
const masterRegions = ref([]);
const fetchMasterRegions = async () => {
  try {
    const res = await axios.get('/api/regions/options');
    masterRegions.value = res.data?.data || [];
  } catch (err) {
    // Ignore error
  }
};

const searchQuery = ref('');
const filterRegion = ref('all');
let searchTimeout = null;

const isModalOpen = ref(false);
const isEditMode = ref(false);
const isSaving = ref(false);
const dealerToDelete = ref(null);
const isDeleting = ref(false);

const formData = reactive({
  id: null,
  id_realme: '',
  dealer_name: '',
  kode_bt: '',
  alias: '',
  gs_name: '',
  npwp: '',
  npwp_name: '',
  npwp_type: '',
  reffnote: '',
  telemarketing: '',
  telemarketing_phone: '',
  sales_person: '',
  region: '',
  address: '',
  whatsapp: '',
  email: '',
  pic_name: '',
  is_active: true,
});

async function fetchDealers(page = 1) {
  isLoading.value = true;
  currentPage.value = page;

  try {
    const params = {
      page,
      per_page: perPage.value,
      search: searchQuery.value.trim() || undefined,
      region: filterRegion.value !== 'all' ? filterRegion.value : undefined,
    };
    const res = await axios.get('/api/dealers', { params });
    if (res.data?.success) {
      dealers.value = res.data.data.data || [];
      totalRows.value = res.data.data.total || 0;
      totalPages.value = res.data.data.last_page || 1;
      perPage.value = res.data.data.per_page || 25;
      if (res.data.regions?.length) {
        availableRegions.value = res.data.regions;
      }
    }
  } catch (e) {
    alert('Gagal memuat data dealer: ' + (e.response?.data?.message || e.message));
  } finally {
    isLoading.value = false;
  }
}

function handleSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchDealers(1);
  }, 300);
}

function openCreateModal() {
  isEditMode.value = false;
  Object.assign(formData, {
    id: null,
    id_realme: '',
    dealer_name: '',
    kode_bt: '',
    alias: '',
    gs_name: '',
    npwp: '',
    npwp_name: '',
    npwp_type: '',
    reffnote: '',
    telemarketing: '',
    telemarketing_phone: '',
    sales_person: '',
    region: filterRegion.value !== 'all' ? filterRegion.value : '',
    address: '',
    whatsapp: '',
    email: '',
    pic_name: '',
    is_active: true,
  });
  isModalOpen.value = true;
}

function openEditModal(d) {
  isEditMode.value = true;
  Object.assign(formData, {
    id: d.id,
    id_realme: d.id_realme || '',
    dealer_name: d.dealer_name,
    kode_bt: d.kode_bt || '',
    alias: d.alias || '',
    gs_name: d.gs_name || '',
    npwp: d.npwp || '',
    npwp_name: d.npwp_name || '',
    npwp_type: d.npwp_type || '',
    reffnote: d.reffnote || '',
    telemarketing: d.telemarketing || '',
    telemarketing_phone: d.telemarketing_phone || '',
    sales_person: d.sales_person || '',
    region: d.region || '',
    address: d.address || '',
    whatsapp: d.whatsapp || '',
    email: d.email || '',
    pic_name: d.pic_name || '',
    is_active: Boolean(d.is_active),
  });
  isModalOpen.value = true;
}

async function submitForm() {
  isSaving.value = true;
  try {
    if (isEditMode.value) {
      await axios.put(`/api/dealers/${formData.id}`, formData);
    } else {
      await axios.post('/api/dealers', formData);
    }
    isModalOpen.value = false;
    await fetchDealers(currentPage.value);
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan data dealer.');
  } finally {
    isSaving.value = false;
  }
}

async function toggleDealerStatus(d) {
  try {
    const res = await axios.patch(`/api/dealers/${d.id}/toggle-status`);
    if (res.data?.success) {
      d.is_active = res.data.data.is_active;
    }
  } catch (e) {
    alert('Gagal mengubah status: ' + (e.response?.data?.message || e.message));
  }
}

function confirmDelete(d) {
  dealerToDelete.value = d;
}

async function executeDelete() {
  if (!dealerToDelete.value) return;
  isDeleting.value = true;
  try {
    const res = await axios.delete(`/api/dealers/${dealerToDelete.value.id}`);
    if (res.data?.success) {
      dealerToDelete.value = null;
      await fetchDealers(currentPage.value);
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus dealer.');
  } finally {
    isDeleting.value = false;
  }
}

onMounted(() => {
  fetchMasterRegions();
  fetchDealers(1);
});
</script>
