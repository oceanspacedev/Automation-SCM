<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Form Program</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span>{{ area.label }}</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Hasil Input Form</span>
    </div>

    <AreaTabs />

    <!-- Header Page -->
    <div class="flex items-start sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Form Program {{ area.label }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar hasil inputan pengajuan program {{ area.label }} dari formulir web.
        </p>
      </div>

      <!-- Action Dropdown -->
      <div class="relative shrink-0" ref="dropdownRef">
        <button
          type="button"
          @click="isDropdownOpen = !isDropdownOpen"
          class="h-9 px-3.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer"
        >
          <span>Aksi</span>
          <ChevronDownIcon class="w-3 h-3 text-gray-400 transition-transform duration-150" :class="{ 'rotate-180': isDropdownOpen }" />
        </button>

        <!-- Dropdown Menu -->
        <div
          v-if="isDropdownOpen"
          class="absolute right-0 mt-1.5 w-52 rounded-xl border border-gray-200 bg-white shadow-lg z-50 py-1 overflow-hidden"
        >
          <a
            :href="publicFormUrl"
            target="_blank"
            rel="noopener noreferrer"
            @click="isDropdownOpen = false"
            class="block px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            Buka Form Input
          </a>

          <a
            :href="googleSheetUrl"
            target="_blank"
            rel="noopener noreferrer"
            @click="isDropdownOpen = false"
            class="block px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            Buka Spreadsheet
          </a>

          <button
            type="button"
            @click="openConfigModal(); isDropdownOpen = false"
            class="w-full text-left px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            Integrasi Sheet
          </button>

          <div class="h-px bg-gray-100 mx-3 my-1"></div>

          <button
            type="button"
            @click="fetchSubmissions(); isDropdownOpen = false"
            :disabled="isLoading"
            class="w-full text-left px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer disabled:opacity-50"
          >
            {{ isLoading ? 'Memuat...' : 'Refresh Data' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-3 sm:px-4 border-b border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="text-xs text-gray-600 font-medium">
          <span>Total: {{ pagination.total }} data</span>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
          <!-- Toggle Kolom Finansial (Hidden by default); di layar kecil data tampil sebagai kartu -->
          <button
            type="button"
            @click="showFinancialColumns = !showFinancialColumns"
            :class="showFinancialColumns ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'"
            class="hidden md:inline-flex h-9 px-3 items-center gap-1.5 rounded-lg border text-xs font-medium shadow-2xs transition cursor-pointer shrink-0"
            :title="showFinancialColumns ? 'Sembunyikan 11 kolom finansial' : 'Tampilkan 11 kolom finansial di tabel'"
          >
            <EyeOffIcon v-if="showFinancialColumns" class="w-3.5 h-3.5" />
            <EyeIcon v-else class="w-3.5 h-3.5 text-gray-500" />
            <span>{{ showFinancialColumns ? 'Tutup Finansial' : 'Kolom Finansial' }}</span>
          </button>

          <!-- Region Filter -->
          <div class="relative sm:min-w-[140px] order-2 sm:order-none">
            <select
              v-model="selectedRegion"
              @change="applyFilters"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="">Semua Region</option>
              <option v-for="r in regionOptions" :key="r" :value="r">{{ r }}</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <!-- Search Input -->
          <div class="relative w-full sm:w-64">
            <SearchIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
            <input
              type="text"
              v-model="searchQuery"
              @input="handleSearchDebounced"
              placeholder="Cari dealer, ID Realme, sales..."
              class="h-9 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 shadow-2xs"
            />
            <button
              v-if="searchQuery"
              @click="clearSearch"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer"
            >
              <XIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per pengajuan -->
      <div class="md:hidden">
        <p v-if="isLoading" class="py-8 text-center text-xs text-gray-500">Memuat data...</p>
        <p v-else-if="items.length === 0" class="py-8 text-center text-xs text-gray-500">Belum ada data inputan form.</p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="item in items" :key="item.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 break-words">{{ item.dealer_name || '-' }}</p>
                <p class="mt-0.5 text-gray-500">{{ item.id_real || '-' }} · {{ item.region || '-' }}</p>
              </div>
              <button
                type="button"
                @click="openFinancialModal(item)"
                class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium transition cursor-pointer shrink-0"
              >
                {{ analyzingRowIds.has(item.id) ? 'Menganalisis...' : 'Lihat' }}
              </button>
            </div>
            <p class="text-gray-900 break-words">{{ item.program_name || '-' }}</p>
            <p class="text-gray-500">{{ item.sales_name || '-' }} · {{ item.submission_timestamp || '-' }}</p>
            <div class="flex items-center gap-4">
              <a v-if="item.credit_note_url" :href="item.credit_note_url" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline">CN</a>
              <span v-else class="text-gray-400">CN (-)</span>
              <a v-if="item.agreement_url" :href="item.agreement_url" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline">Agreement</a>
              <span v-else class="text-gray-400">Agreement (-)</span>
              <a v-if="item.tax_invoice_url" :href="item.tax_invoice_url" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline">Faktur</a>
              <span v-else class="text-gray-400">Faktur (-)</span>
            </div>
          </li>
        </ul>
      </div>

      <!-- Plain Ordinary Table (No colors, No AI styling) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-12 border-r border-gray-200">No</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Waktu</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Region</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">ID Realme</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama Dealer</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama Program</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Nama Sales</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">No. WA</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">CN</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Agr</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Faktur</th>

              <!-- Kolom Lihat Finansial -->
              <th scope="col" class="py-2.5 px-3 text-center" :class="{ 'border-r border-gray-200': showFinancialColumns }">Lihat</th>

              <!-- 11 Kolom Finansial & Pajak (Hidden by default, muncul saat showFinancialColumns aktif) -->
              <template v-if="showFinancialColumns">
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">Incentive</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">DPP</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">DPP Lain</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">PPN</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">Nilai PPh</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">Net Pay</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">Cek Pajak</th>
                <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200 font-semibold whitespace-nowrap">Selisih</th>
                <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200 font-semibold whitespace-nowrap">Note PPh</th>
                <th scope="col" class="py-2.5 px-3 border-r border-gray-200 font-semibold whitespace-nowrap">No Faktur</th>
                <th scope="col" class="py-2.5 px-3 font-semibold whitespace-nowrap">Tgl Faktur</th>
              </template>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <!-- Loading -->
            <tr v-if="isLoading">
              <td :colspan="showFinancialColumns ? 23 : 12" class="py-8 text-center text-gray-500">
                Memuat data...
              </td>
            </tr>

            <!-- Empty -->
            <tr v-else-if="items.length === 0">
              <td :colspan="showFinancialColumns ? 23 : 12" class="py-8 text-center text-gray-500">
                Belum ada data inputan form.
              </td>
            </tr>

            <!-- Data Rows (Uniform Font & Style) -->
            <tr
              v-else
              v-for="(item, idx) in items"
              :key="item.id"
              class="hover:bg-gray-50/70 text-gray-700 font-normal"
            >
              <!-- 1. No -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                {{ (pagination.currentPage - 1) * pagination.perPage + idx + 1 }}
              </td>

              <!-- 2. Waktu -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ item.submission_timestamp || '-' }}
              </td>

              <!-- 3. Region -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ item.region || '-' }}
              </td>

              <!-- 4. ID Real -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ item.id_real || '-' }}
              </td>

              <!-- 5. Nama Dealer -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal min-w-[180px] max-w-[220px] break-words leading-snug" :title="item.dealer_name">
                {{ item.dealer_name || '-' }}
              </td>

              <!-- 6. Nama Program -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-normal min-w-[220px] max-w-[260px] break-words leading-snug" :title="item.program_name">
                {{ item.program_name || '-' }}
              </td>

              <!-- 7. Nama Sales -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ item.sales_name || '-' }}
              </td>

              <!-- 8. No. WA -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ item.whatsapp || '-' }}
              </td>

              <!-- 9. CN -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="item.credit_note_url"
                  :href="item.credit_note_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  CN
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 10. Agr -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="item.agreement_url"
                  :href="item.agreement_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  Agr
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 11. Faktur -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="item.tax_invoice_url"
                  :href="item.tax_invoice_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  Faktur
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 12. Kolom Lihat Finansial -->
              <td class="py-2.5 px-3 text-center whitespace-nowrap" :class="{ 'border-r border-gray-100': showFinancialColumns }">
                <button
                  type="button"
                  @click="openFinancialModal(item)"
                  class="h-7 px-2.5 inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-medium transition cursor-pointer shadow-2xs"
                  title="Lihat Detail Finansial & Pajak"
                >
                  <RefreshCwIcon v-if="analyzingRowIds.has(item.id)" class="w-3 h-3 animate-spin text-gray-400" />
                  <EyeIcon v-else class="w-3.5 h-3.5 text-gray-500" />
                  <span>Lihat</span>
                </button>
              </td>

              <!-- 11 Kolom Finansial jika di-unhide -->
              <template v-if="showFinancialColumns">
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap font-medium text-gray-900">
                  {{ formatRupiah(item.incentive) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                  {{ formatRupiah(item.dpp) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                  {{ formatRupiah(item.dpp_lain) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                  {{ formatRupiah(item.ppn) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                  {{ formatRupiah(item.nilai_pph) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap font-bold text-gray-950">
                  {{ formatRupiah(item.net_pay) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                  {{ formatRupiah(item.cek_pajak_tarif_pph) }}
                </td>
                <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap">
                  <span v-if="item.selisih === 0 || item.selisih === '0' || item.selisih === 0.0" class="text-gray-400">0</span>
                  <span v-else-if="item.selisih !== null && item.selisih !== undefined">{{ formatRupiah(item.selisih) }}</span>
                  <span v-else class="text-gray-300">-</span>
                </td>
                <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-700">
                  {{ item.note_pph || '-' }}
                </td>
                <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                  {{ item.no_faktur || '-' }}
                </td>
                <td class="py-2.5 px-3 whitespace-nowrap text-gray-800">
                  {{ item.tgl_faktur || '-' }}
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination.total > 0"
        class="p-3 sm:px-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600"
      >
        <div class="flex items-center gap-2">
          <span>Menampilkan {{ pagination.from || 0 }} - {{ pagination.to || 0 }} dari {{ pagination.total }} data</span>
          <select
            v-model="pagination.perPage"
            @change="handlePerPageChange"
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
            @click="goToPage(pagination.currentPage - 1)"
            :disabled="pagination.currentPage <= 1 || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <ChevronLeftIcon class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Sebelumnya</span>
          </button>

          <span class="px-2 font-medium text-gray-900">
            Hal {{ pagination.currentPage }} dari {{ pagination.lastPage || 1 }}
          </span>

          <button
            type="button"
            @click="goToPage(pagination.currentPage + 1)"
            :disabled="pagination.currentPage >= pagination.lastPage || isLoading"
            class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 disabled:opacity-40 transition cursor-pointer flex items-center gap-1"
          >
            <span class="hidden sm:inline">Selanjutnya</span>
            <ChevronRightIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL INTEGRASI SPREADSHEET ================= -->
    <div
      v-if="isConfigModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
    >
      <div
        class="bg-white rounded-2xl shadow-xl border border-gray-200 max-w-xl w-full p-6 space-y-4 animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
          <div class="flex items-center gap-2">
            <FileSpreadsheetIcon class="w-5 h-5 text-emerald-600" />
            <h3 class="text-sm font-semibold text-gray-900">Integrasi Google Spreadsheet</h3>
          </div>
          <button
            type="button"
            @click="isConfigModalOpen = false"
            class="p-1 text-gray-400 hover:text-gray-600 rounded-md transition cursor-pointer"
          >
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-3 text-xs text-gray-600">
          <div class="space-y-1">
            <label class="font-medium text-gray-900 block">Link Google Spreadsheet</label>
            <div class="flex items-center gap-2">
              <input
                type="text"
                v-model="configSpreadsheetUrl"
                class="flex-1 h-9 px-3 rounded-lg border border-gray-300 text-xs bg-gray-50 text-gray-800 focus:outline-none"
              />
              <a
                :href="configSpreadsheetUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="h-9 px-3 inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 cursor-pointer shrink-0"
              >
                <ExternalLinkIcon class="w-3.5 h-3.5" />
                <span>Buka</span>
              </a>
            </div>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-gray-900 block">URL Google Apps Script Web App</label>
            <input
              type="text"
              v-model="configWebappUrl"
              placeholder="https://script.google.com/macros/s/.../exec"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:ring-1 focus:ring-gray-900 focus:outline-none"
            />
            <p class="text-[11px] text-gray-400">
              Setiap kali form diisi, data akan otomatis dikirim (POST) ke URL Web App ini dan dicatat di spreadsheet.
            </p>
          </div>

          <!-- Petunjuk Apps Script -->
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-800 text-[11px]">Kode Google Apps Script:</span>
              <button
                type="button"
                @click="copyAppsScriptCode"
                class="inline-flex items-center gap-1 text-[11px] text-gray-700 hover:text-gray-900 font-medium cursor-pointer"
              >
                <CheckIcon v-if="hasCopiedScript" class="w-3.5 h-3.5 text-emerald-600" />
                <CopyIcon v-else class="w-3.5 h-3.5" />
                <span>{{ hasCopiedScript ? 'Tersalin!' : 'Salin Kode' }}</span>
              </button>
            </div>
            <pre class="bg-gray-900 text-gray-100 p-2.5 rounded text-[10px] font-mono overflow-x-auto max-h-36 select-all leading-tight">{{ appsScriptTemplate }}</pre>
            <ol class="list-decimal list-inside text-[11px] text-gray-500 space-y-0.5 pt-1">
              <li>Buka spreadsheet, klik menu <strong>Ekstensi &gt; Apps Script</strong>.</li>
              <li>Tempel kode di atas lalu klik tombol <strong>Terapkan (Deploy) &gt; Kelola deployment &gt; Edit (atau Deployment Baru)</strong>.</li>
              <li>Pilih jenis <strong>Aplikasi Web</strong> (Akses: <em>Siapa saja / Anyone</em>) lalu Deploy.</li>
              <li>Kembali ke spreadsheet dan refresh halaman: akan muncul menu <strong>⚡ Otomasi SCM &gt; ✨ Rapikan Tampilan Link</strong> untuk merapikan semua baris lama dengan 1-klik!</li>
              <li><em>(Opsional)</em> Jika ingin tanggapan Google Form otomatis masuk ke Web SCM: di Apps Script buka menu <strong>Pemicu (Triggers / jam) &gt; Tambahkan Pemicu</strong>, pilih fungsi <code>onFormSubmit</code> dan jenis acara <code>Saat mengirim formulir</code>.</li>
            </ol>
          </div>

          <div v-if="saveMessage" class="p-2.5 rounded-lg border text-xs" :class="saveSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'">
            {{ saveMessage }}
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
          <button
            type="button"
            @click="isConfigModalOpen = false"
            class="px-3.5 h-8 text-xs font-medium rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 transition cursor-pointer"
          >
            Tutup
          </button>
          <button
            type="button"
            @click="saveSpreadsheetConfig"
            :disabled="isSavingConfig"
            class="px-4 h-8 text-xs font-semibold rounded-lg bg-gray-900 hover:bg-black text-white transition cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
          >
            <RefreshCwIcon v-if="isSavingConfig" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ isSavingConfig ? 'Menyimpan...' : 'Simpan Konfigurasi' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ================= MODAL LIHAT FINANSIAL ================= -->
    <div
      v-if="isFinancialModalOpen && selectedItem"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
      @click.self="isFinancialModalOpen = false"
    >
      <div
        class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-2xl w-full flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
      >
        <!-- Header -->
        <div class="px-6 pt-5 pb-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
          <div class="min-w-0">
            <h3 class="text-base font-semibold text-gray-950">
              Detail Finansial & Pajak
              <span v-if="analyzingRowIds.has(selectedItem.id)" class="ml-1.5 text-xs font-normal text-amber-600">Menganalisis AI…</span>
            </h3>
            <p class="mt-0.5 text-xs text-gray-500 truncate">
              {{ selectedItem.dealer_name || '-' }}<template v-if="selectedItem.id_real"> · {{ selectedItem.id_real }}</template>
              · {{ selectedItem.program_name || '-' }}<template v-if="selectedItem.region"> · {{ selectedItem.region }}</template>
            </p>
          </div>
          <button type="button" @click="isFinancialModalOpen = false" class="p-1 -m-1 text-gray-400 hover:text-gray-700 cursor-pointer shrink-0" title="Tutup">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="px-6 py-5 space-y-5 overflow-y-auto text-xs">
          <!-- Ringkasan -->
          <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3">
            <div v-for="item in financialSummary(selectedItem)" :key="item.label" class="min-w-0">
              <dt class="text-gray-500">{{ item.label }}</dt>
              <dd class="mt-0.5 text-gray-900 truncate" :title="item.value">{{ item.value || '-' }}</dd>
            </div>
          </dl>

          <!-- Rincian Finansial & Pajak -->
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 border-t border-gray-100">
            <div v-for="row in financialRows(selectedItem)" :key="row.label" class="flex items-center justify-between gap-4 py-2.5 border-b border-gray-100">
              <dt class="text-gray-500">{{ row.label }}</dt>
              <dd class="text-gray-900 text-right">{{ row.value }}</dd>
            </div>
          </dl>

          <!-- Dokumen -->
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="text-gray-500">Dokumen</span>
            <template v-for="doc in financialDocuments(selectedItem)" :key="doc.label">
              <a v-if="doc.url" :href="doc.url" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline hover:text-gray-600">{{ doc.label }}</a>
              <span v-else class="text-gray-400">{{ doc.label }} (-)</span>
            </template>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3.5 border-t border-gray-100 flex items-center justify-between gap-2 text-xs shrink-0">
          <button
            type="button"
            @click="analyzeRowWithAi(selectedItem)"
            :disabled="analyzingRowIds.has(selectedItem.id)"
            class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium transition cursor-pointer disabled:opacity-50"
            title="Analisis dokumen pengajuan ini dengan AI sekarang"
          >
            {{ analyzingRowIds.has(selectedItem.id) ? 'Menganalisis...' : 'Analisis AI Ulang' }}
          </button>
          <button
            type="button"
            @click="isFinancialModalOpen = false"
            class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium transition cursor-pointer"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AreaTabs from '@/components/AreaTabs.vue';
import {
  ChevronRight as ChevronRightIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronDown as ChevronDownIcon,
  RefreshCw as RefreshCwIcon,
  ExternalLink as ExternalLinkIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  Copy as CopyIcon,
  Check as CheckIcon,
  Search as SearchIcon,
  X as XIcon,
  Eye as EyeIcon,
  EyeOff as EyeOffIcon,
} from 'lucide-vue-next';
import { useArea, DEFAULT_AREA } from '@/composables/useArea';
import { useBrands } from '@/composables/useBrands';

// ===== AREA (Jabar/Jateng) =====
const { area } = useArea();
const { brand } = useBrands();
const isDefaultArea = area.value.value === DEFAULT_AREA;
// Form publik per brand & area: /form/realme/jabar, /form/realme/jateng (link lama /form/jabar diarahkan ke Realme)
const publicFormUrl = `/form/${brand.value.slug}/${area.value.value}`;

// ===== DROPDOWN =====
const isDropdownOpen = ref(false);
const dropdownRef = ref(null);

function handleOutsideClick(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isDropdownOpen.value = false;
  }
}

// ===== FINANCIAL COLUMNS & MODAL =====
const showFinancialColumns = ref(false);
const isFinancialModalOpen = ref(false);
const selectedItem = ref(null);
const analyzingRowIds = ref(new Set());

function formatRupiah(val) {
  if (val === null || val === undefined || val === '') return '-';
  const num = Number(val);
  if (isNaN(num)) return '-';
  return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

// Isi modal Detail Finansial & Pajak
function financialSummary(item) {
  return [
    { label: 'Status potong', value: item.status_potong_purchase },
    { label: 'Cek dokumen', value: item.cek_dokumen },
    { label: 'Sales (DM)', value: item.sales_name },
    { label: 'Waktu submit', value: item.submission_timestamp },
  ];
}

function financialRows(item) {
  const selisih = item.selisih === null || item.selisih === undefined || item.selisih === '' ? '-' : Number(item.selisih) === 0 ? '0' : formatRupiah(item.selisih);

  return [
    { label: 'Incentive', value: formatRupiah(item.incentive) },
    { label: 'DPP', value: formatRupiah(item.dpp) },
    { label: 'DPP Lain', value: formatRupiah(item.dpp_lain) },
    { label: 'PPN', value: formatRupiah(item.ppn) },
    { label: 'Nilai PPh', value: formatRupiah(item.nilai_pph) },
    { label: 'Net Pay', value: formatRupiah(item.net_pay) },
    { label: 'Cek Pajak', value: formatRupiah(item.cek_pajak_tarif_pph) },
    { label: 'Selisih', value: selisih },
    { label: 'Note PPh', value: item.note_pph || '-' },
    { label: 'No Faktur', value: item.no_faktur || '-' },
    { label: 'Tgl Faktur', value: item.tgl_faktur || '-' },
  ];
}

function financialDocuments(item) {
  return [
    { label: 'Credit Note', url: item.credit_note_url },
    { label: 'Agreement', url: item.agreement_url },
    { label: 'Faktur Pajak', url: item.tax_invoice_url },
  ];
}

function openFinancialModal(item) {
  selectedItem.value = item;
  isFinancialModalOpen.value = true;
  // Otomatis jalankan AI jika baris ini belum pernah dianalisis
  if (item.credit_note_url && (item.incentive === null || item.incentive === undefined) && !analyzingRowIds.value.has(item.id)) {
    analyzeRowWithAi(item, true);
  }
}

async function analyzeRowWithAi(item, isSilent = false) {
  if (!item?.id) return;
  analyzingRowIds.value.add(item.id);
  try {
    const res = await axios.post(`/api/program-submissions/${item.id}/analyze-ai`);
    if (res.data?.data) {
      const fresh = res.data.data.submission || res.data.data;
      Object.assign(item, fresh);
      if (selectedItem.value && selectedItem.value.id === item.id) {
        Object.assign(selectedItem.value, fresh);
      }
    }
  } catch (err) {
    if (!isSilent) {
      alert('Gagal menganalisis dokumen: ' + (err.response?.data?.message || err.message));
    }
  } finally {
    analyzingRowIds.value.delete(item.id);
  }
}

function autoAnalyzePendingSubmissions(newItems) {
  if (!Array.isArray(newItems)) return;
  const pending = newItems.filter(
    (item) =>
      item.credit_note_url &&
      (item.incentive === null || item.incentive === undefined) &&
      !analyzingRowIds.value.has(item.id)
  );
  // Auto-analisis data baru bertahap (2 item sekaligus)
  for (const item of pending.slice(0, 2)) {
    analyzeRowWithAi(item, true);
  }
}

const items = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const selectedRegion = ref('');
const regionOptions = ref([]);

const pagination = reactive({
  currentPage: 1,
  lastPage: 1,
  perPage: 15,
  total: 0,
  from: 0,
  to: 0,
});

let pollTimer = null;
onMounted(() => {
  fetchSubmissions();
  loadSheetUrl();
  document.addEventListener('click', handleOutsideClick);
  // Sinkronkan data baru di latar belakang setiap 30 detik (seperti Form Program)
  pollTimer = setInterval(() => {
    if (!isLoading.value) {
      fetchSubmissions(true);
    }
  }, 30000);
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
  if (pollTimer) clearInterval(pollTimer);
});

let searchDebounceTimer = null;
function handleSearchDebounced() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    pagination.currentPage = 1;
    fetchSubmissions();
  }, 350);
}

function clearSearch() {
  searchQuery.value = '';
  pagination.currentPage = 1;
  fetchSubmissions();
}

function applyFilters() {
  pagination.currentPage = 1;
  fetchSubmissions();
}

function handlePerPageChange() {
  pagination.currentPage = 1;
  fetchSubmissions();
}

function goToPage(page) {
  if (page < 1 || page > pagination.lastPage) return;
  pagination.currentPage = page;
  fetchSubmissions();
}

async function fetchSubmissions(isSilent = false) {
  if (!isSilent) isLoading.value = true;
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.perPage,
      source: 'web_form',
    };

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (selectedRegion.value) {
      params.region = selectedRegion.value;
    }

    const res = await axios.get('/api/program-submissions', { params });
    if (res.data) {
      const p = res.data.submissions;
      items.value = p.data || [];
      pagination.currentPage = p.current_page || 1;
      pagination.lastPage = p.last_page || 1;
      pagination.total = p.total || 0;
      pagination.from = p.from || 0;
      pagination.to = p.to || 0;

      if (Array.isArray(res.data.regions)) {
        regionOptions.value = res.data.regions;
      }

      // Otomatis jalankan analisis AI jika ada data baru/pending yang belum dianalisis
      autoAnalyzePendingSubmissions(items.value);
    }
  } catch (err) {
    console.error('Gagal mengambil data submissions:', err);
  } finally {
    if (!isSilent) isLoading.value = false;
  }
}

// ================= GOOGLE SPREADSHEET CONFIGURATION =================
// Link bawaan hanya untuk Jawa Barat; area lain diatur lewat "Konfigurasi Spreadsheet"
const googleSheetUrl = ref(isDefaultArea ? 'https://docs.google.com/spreadsheets/d/1rDYiHsNR43H44g2xyV-igyp1Ijv_jp8v8FblAMHqof8/edit?gid=0#gid=0' : '');
const isConfigModalOpen = ref(false);
const isSavingConfig = ref(false);
const configWebappUrl = ref(isDefaultArea ? 'https://script.google.com/macros/s/AKfycbz7fKNsgUfZeVtRVXGkZIKTr7PMQJPCcwuJZGkxAW_qSa2n_4m9z-459-FCaaSrxYhq/exec' : '');
const configSpreadsheetUrl = ref(googleSheetUrl.value);
const saveMessage = ref('');
const saveSuccess = ref(false);
const hasCopiedScript = ref(false);

const appsScriptTemplate = `/**
 * Google Apps Script untuk SCM Form Submission & Tampilan Link Rapi
 * Fitur:
 * 1. doPost: Otomatis memformat link dokumen menjadi hyperlink pendek ("Lihat CN", "Lihat Agreement", "Lihat Faktur") saat dikirim dari Web SCM.
 * 2. onFormSubmit: Otomatis mem-forward respon Google Form ke Web SCM secara realtime.
 * 3. rapikanSemuaLink: 1-Klik merapikan SEMUA baris lama yang link-nya masih kepanjangan.
 * 4. onOpen: Menambahkan menu khusus di atas Google Spreadsheet ("⚡ Otomasi SCM").
 */

// Domain Web SCM Automation untuk Webhook 2 Arah
var SCM_WEBHOOK_URL = "https://scm-customer.oceanspace.co.id/api/webhooks/form-program?area=${area.value.value}";

function formatHyperlink(val, label) {
  if (!val || typeof val !== "string") return "-";
  var str = val.trim();
  if (str === "" || str === "-") return "-";
  if (str.indexOf("=HYPERLINK") === 0) return str;
  if (str.indexOf("http://") === 0 || str.indexOf("https://") === 0) {
    return '=HYPERLINK("' + str.replace(/"/g, '""') + '"; "' + label + '")';
  }
  return str;
}

/**
 * 1. Menerima kiriman data dari Web SCM -> Tulis ke Spreadsheet
 */
function doPost(e) {
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var data = JSON.parse(e.postData.contents);
    
    // Periksa apakah kolom 1 adalah NO atau TIMESTAMP
    var headers = sheet.getRange(1, 1, 1, Math.max(sheet.getLastColumn(), 1)).getValues()[0];
    var firstHeader = (headers[0] || "").toString().trim().toUpperCase();
    var hasNoCol = firstHeader === "NO";
    
    var cnLink = formatHyperlink(data.credit_note_formula || data.credit_note_url, "Lihat CN");
    var agrLink = formatHyperlink(data.agreement_formula || data.agreement_url, "Lihat Agreement");
    var taxLink = formatHyperlink(data.tax_invoice_formula || data.tax_invoice_url, "Lihat Faktur");
    
    var rowData = [];
    if (hasNoCol) {
      rowData.push(data.no || sheet.getLastRow());
    }
    
    rowData.push(
      data.timestamp || Utilities.formatDate(new Date(), "GMT+7", "dd/MM/yyyy HH:mm:ss"),
      data.region || "",
      data.id_real || "",
      data.dealer_name || "",
      data.program_name || "",
      data.sales_name || "",
      data.whatsapp || "",
      cnLink,
      agrLink,
      taxLink
    );
    
    sheet.appendRow(rowData);
    
    var lastRow = sheet.getLastRow();
    var linkStartCol = hasNoCol ? 9 : 8;
    
    // Format sel dokumen agar rapi di tengah dan tidak meleber
    var linkRange = sheet.getRange(lastRow, linkStartCol, 1, 3);
    linkRange.setHorizontalAlignment("center");
    linkRange.setVerticalAlignment("middle");
    linkRange.setWrapStrategy(SpreadsheetApp.WrapStrategy.CLIP);
    
    return ContentService.createTextOutput(JSON.stringify({ 
      success: true, 
      message: "Data berhasil ditambahkan dengan tampilan link rapi",
      row: lastRow 
    })).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ 
      success: false, 
      error: err.toString() 
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

function doGet(e) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var values = sheet.getDataRange().getValues();
  return ContentService.createTextOutput(JSON.stringify({ 
    success: true, 
    rows: values 
  })).setMimeType(ContentService.MimeType.JSON);
}

/**
 * 2. Meneruskan Respon Google Form -> Otomatis kirim ke Web SCM
 * Pasang trigger: 'Saat mengirim formulir' (onFormSubmit) di menu Pemicu (Triggers)
 */
function onFormSubmit(e) {
  try {
    if (!e || !e.namedValues) return;
    var payload = {
      namedValues: e.namedValues,
      source: "google_form_webhook"
    };
    UrlFetchApp.fetch(SCM_WEBHOOK_URL, {
      method: "post",
      contentType: "application/json",
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    });
  } catch (err) {
    Logger.log("Gagal mengirim webhook ke SCM: " + err);
  }
}

/**
 * Menu otomatis di Google Spreadsheet untuk merapikan semua link & kirim baris ke SCM.
 */
function onOpen() {
  try {
    SpreadsheetApp.getUi()
      .createMenu("⚡ Otomasi SCM")
      .addItem("✨ Rapikan Tampilan Link", "rapikanSemuaLink")
      .addItem("🚀 Kirim Baris Aktif ke Web SCM", "kirimBarisAktifKeWebScm")
      .addToUi();
  } catch (e) {}
}

/**
 * Kirim baris yang sedang dipilih kursor ke Web SCM
 */
function kirimBarisAktifKeWebScm() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var row = sheet.getActiveRange().getRow();
  if (row < 2) {
    SpreadsheetApp.getActiveSpreadsheet().toast("Pilih baris data (baris 2 ke bawah).", "Perhatian", 4);
    return;
  }
  var lastCol = sheet.getLastColumn();
  var headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0];
  var values = sheet.getRange(row, 1, 1, lastCol).getValues()[0];
  var namedValues = {};
  for (var i = 0; i < headers.length; i++) {
    var key = headers[i].toString().trim();
    if (key) {
      namedValues[key] = [values[i] !== null && values[i] !== undefined ? values[i].toString() : ""];
    }
  }
  try {
    var resp = UrlFetchApp.fetch(SCM_WEBHOOK_URL, {
      method: "post",
      contentType: "application/json",
      payload: JSON.stringify({ namedValues: namedValues }),
      muteHttpExceptions: true
    });
    SpreadsheetApp.getActiveSpreadsheet().toast("Baris " + row + " berhasil dikirim ke Web SCM!", "Sukses", 4);
  } catch (err) {
    SpreadsheetApp.getActiveSpreadsheet().toast("Gagal kirim: " + err, "Error", 5);
  }
}

/**
 * Fungsi 1-Klik: Merapikan semua link yang kepanjangan di kolom H, I, J menjadi 'Lihat CN', 'Lihat Agreement', 'Lihat Faktur'
 */
function rapikanSemuaLink() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var lastRow = sheet.getLastRow();
  var lastCol = sheet.getLastColumn();
  
  if (lastRow < 2) {
    SpreadsheetApp.getActiveSpreadsheet().toast("Tidak ada data baris untuk dirapikan.", "Info", 4);
    return;
  }
  
  var headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0];
  var firstHeader = (headers[0] || "").toString().trim().toUpperCase();
  var hasNoCol = firstHeader === "NO";
  
  var colCN = hasNoCol ? 9 : 8;
  var colAgr = hasNoCol ? 10 : 9;
  var colTax = hasNoCol ? 11 : 10;
  
  var numRows = lastRow - 1;
  var rangeCN = sheet.getRange(2, colCN, numRows, 1);
  var rangeAgr = sheet.getRange(2, colAgr, numRows, 1);
  var rangeTax = sheet.getRange(2, colTax, numRows, 1);
  
  var valsCN = rangeCN.getValues();
  var valsAgr = rangeAgr.getValues();
  var valsTax = rangeTax.getValues();
  
  var updatedCount = 0;
  
  for (var i = 0; i < numRows; i++) {
    var r = i + 2;
    
    // 1. Credit Note
    var valCN = (valsCN[i][0] || "").toString().trim();
    if (valCN.indexOf("http://") === 0 || valCN.indexOf("https://") === 0) {
      sheet.getRange(r, colCN).setValue(formatHyperlink(valCN, "Lihat CN"));
      updatedCount++;
    } else if (valCN === "#ERROR!") {
      sheet.getRange(r, colCN).setValue("-");
    }
    
    // 2. Agreement
    var valAgr = (valsAgr[i][0] || "").toString().trim();
    if (valAgr.indexOf("http://") === 0 || valAgr.indexOf("https://") === 0) {
      sheet.getRange(r, colAgr).setValue(formatHyperlink(valAgr, "Lihat Agreement"));
      updatedCount++;
    } else if (valAgr === "#ERROR!") {
      sheet.getRange(r, colAgr).setValue("-");
    }
    
    // 3. Faktur Pajak
    var valTax = (valsTax[i][0] || "").toString().trim();
    if (valTax.indexOf("http://") === 0 || valTax.indexOf("https://") === 0) {
      sheet.getRange(r, colTax).setValue(formatHyperlink(valTax, "Lihat Faktur"));
      updatedCount++;
    } else if (valTax === "#ERROR!") {
      sheet.getRange(r, colTax).setValue("-");
    }
  }
  
  // Atur perataan tengah & pangkas teks (CLIP) agar tidak tembus ke kolom sebelah
  sheet.getRange(2, colCN, numRows, 3)
    .setHorizontalAlignment("center")
    .setVerticalAlignment("middle")
    .setWrapStrategy(SpreadsheetApp.WrapStrategy.CLIP);
    
  // Sesuaikan lebar kolom agar proporsional dan rapi
  sheet.setColumnWidth(colCN, 130);
  sheet.setColumnWidth(colAgr, 130);
  sheet.setColumnWidth(colTax, 130);
  
  SpreadsheetApp.getActiveSpreadsheet().toast("Berhasil merapikan " + updatedCount + " link dokumen menjadi pendek & rapi!", "Selesai", 5);
}`;

async function loadSheetUrl() {
  try {
    const res = await axios.get('/api/program-submissions/config');
    googleSheetUrl.value = res.data?.spreadsheet_url || '';
  } catch (e) {
    // Keep defaults
  }
}

async function openConfigModal() {
  saveMessage.value = '';
  isConfigModalOpen.value = true;
  try {
    const res = await axios.get('/api/program-submissions/config');
    if (res.data) {
      configSpreadsheetUrl.value = res.data.spreadsheet_url || '';
      googleSheetUrl.value = res.data.spreadsheet_url || '';
      configWebappUrl.value = res.data.webapp_url || '';
    }
  } catch (e) {
    // Keep defaults
  }
}

async function saveSpreadsheetConfig() {
  isSavingConfig.value = true;
  saveMessage.value = '';
  try {
    const res = await axios.post('/api/program-submissions/config', {
      url: configWebappUrl.value.trim(),
      spreadsheet_url: configSpreadsheetUrl.value.trim(),
    });
    if (res.data?.success) {
      saveSuccess.value = true;
      saveMessage.value = 'Konfigurasi Google Spreadsheet berhasil disimpan!';
      googleSheetUrl.value = configSpreadsheetUrl.value.trim();
      setTimeout(() => {
        isConfigModalOpen.value = false;
      }, 1200);
    }
  } catch (err) {
    saveSuccess.value = false;
    saveMessage.value = err.response?.data?.message || 'Gagal menyimpan konfigurasi.';
  } finally {
    isSavingConfig.value = false;
  }
}

function copyAppsScriptCode() {
  navigator.clipboard.writeText(appsScriptTemplate);
  hasCopiedScript.value = true;
  setTimeout(() => {
    hasCopiedScript.value = false;
  }, 2000);
}
</script>
