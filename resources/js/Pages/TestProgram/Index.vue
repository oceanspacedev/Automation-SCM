<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Test Program</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Hasil Input Form</span>
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Test Program</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Daftar hasil inputan pengajuan program dari formulir web.
        </p>
      </div>

      <!-- Action Dropdown -->
      <div class="relative" ref="dropdownRef">
        <button
          type="button"
          @click="isDropdownOpen = !isDropdownOpen"
          class="h-9 px-3.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer"
        >
          <MoreHorizontalIcon class="w-3.5 h-3.5 text-gray-500" />
          <span>Aksi</span>
          <ChevronDownIcon class="w-3 h-3 text-gray-400 transition-transform duration-150" :class="{ 'rotate-180': isDropdownOpen }" />
        </button>

        <!-- Dropdown Menu -->
        <div
          v-if="isDropdownOpen"
          class="absolute right-0 mt-1.5 w-52 rounded-xl border border-gray-200 bg-white shadow-lg z-50 py-1 overflow-hidden"
        >
          <a
            href="/form-submission"
            target="_blank"
            rel="noopener noreferrer"
            @click="isDropdownOpen = false"
            class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            <ExternalLinkIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
            <span>Buka Form Input</span>
          </a>

          <a
            :href="googleSheetUrl"
            target="_blank"
            rel="noopener noreferrer"
            @click="isDropdownOpen = false"
            class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            <FileSpreadsheetIcon class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
            <span>Buka Spreadsheet</span>
          </a>

          <button
            type="button"
            @click="openConfigModal(); isDropdownOpen = false"
            class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer"
          >
            <SettingsIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
            <span>Integrasi Sheet</span>
          </button>

          <div class="h-px bg-gray-100 mx-3 my-1"></div>

          <button
            type="button"
            @click="fetchSubmissions(); isDropdownOpen = false"
            :disabled="isLoading"
            class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition cursor-pointer disabled:opacity-50"
          >
            <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-400 shrink-0', isLoading && 'animate-spin']" />
            <span>{{ isLoading ? 'Memuat...' : 'Refresh Data' }}</span>
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

        <div class="flex items-center gap-2">
          <!-- Toggle Kolom Finansial (Hidden by default) -->
          <button
            type="button"
            @click="showFinancialColumns = !showFinancialColumns"
            :class="showFinancialColumns ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'"
            class="h-9 px-3 inline-flex items-center gap-1.5 rounded-lg border text-xs font-medium shadow-2xs transition cursor-pointer shrink-0"
            :title="showFinancialColumns ? 'Sembunyikan 11 kolom finansial' : 'Tampilkan 11 kolom finansial di tabel'"
          >
            <EyeOffIcon v-if="showFinancialColumns" class="w-3.5 h-3.5" />
            <EyeIcon v-else class="w-3.5 h-3.5 text-gray-500" />
            <span>{{ showFinancialColumns ? 'Tutup Finansial' : 'Kolom Finansial' }}</span>
          </button>

          <!-- Region Filter -->
          <div class="relative min-w-[140px]">
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
              placeholder="Cari dealer, ID Real, sales..."
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

      <!-- Plain Ordinary Table (No colors, No AI styling) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-12 border-r border-gray-200">No</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Waktu</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Region</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">ID Real</th>
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
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[200px] truncate" :title="item.dealer_name">
                {{ item.dealer_name || '-' }}
              </td>

              <!-- 6. Nama Program -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[220px] truncate" :title="item.program_name">
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
                <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-800 font-mono text-[11px]">
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
            <span>Sebelumnya</span>
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
            <span>Selanjutnya</span>
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
        class="bg-white rounded-2xl shadow-xl border border-gray-200 max-w-4xl w-full p-6 space-y-4 animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-start justify-between pb-3 border-b border-gray-100">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-gray-900">Detail Finansial & Pajak</h3>
              <span v-if="analyzingRowIds.has(selectedItem.id)" class="inline-flex items-center gap-1 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-medium">
                <RefreshCwIcon class="w-3 h-3 animate-spin" />
                Menganalisis AI...
              </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              Dealer: <strong class="text-gray-900">{{ selectedItem.dealer_name || '-' }}</strong>
              <span v-if="selectedItem.id_real" class="text-gray-400"> ({{ selectedItem.id_real }})</span>
              &bull; Program: <strong class="text-gray-800">{{ selectedItem.program_name || '-' }}</strong>
              &bull; Region: {{ selectedItem.region || '-' }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="analyzeRowWithAi(selectedItem)"
              :disabled="analyzingRowIds.has(selectedItem.id)"
              class="h-8 px-3 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium transition cursor-pointer shadow-2xs disabled:opacity-50"
              title="Analisis dokumen baris ini dengan AI sekarang"
            >
              <RefreshCwIcon v-if="analyzingRowIds.has(selectedItem.id)" class="w-3.5 h-3.5 animate-spin text-gray-500" />
              <BotIcon v-else class="w-3.5 h-3.5 text-gray-600" />
              <span>{{ analyzingRowIds.has(selectedItem.id) ? 'Menganalisis...' : 'Analisis AI Ulang' }}</span>
            </button>
            <button
              type="button"
              @click="isFinancialModalOpen = false"
              class="p-1.5 text-gray-400 hover:text-gray-600 rounded-md transition cursor-pointer"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Ringkasan Status Dokumen & Potong -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
          <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
            <span class="text-gray-500 text-[11px] block">Status Potong Purchase:</span>
            <span class="font-semibold text-gray-900">{{ selectedItem.status_potong_purchase || '-' }}</span>
          </div>
          <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
            <span class="text-gray-500 text-[11px] block">Cek Dokumen:</span>
            <span class="font-semibold text-gray-900">{{ selectedItem.cek_dokumen || '-' }}</span>
          </div>
          <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
            <span class="text-gray-500 text-[11px] block">Sales (DM):</span>
            <span class="font-semibold text-gray-900">{{ selectedItem.sales_name || '-' }}</span>
          </div>
          <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200">
            <span class="text-gray-500 text-[11px] block">Waktu Submit:</span>
            <span class="font-semibold text-gray-900">{{ selectedItem.submission_timestamp || '-' }}</span>
          </div>
        </div>

        <!-- Tabel 11 Kolom Finansial & Pajak -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-900 block">Tabel Rincian Finansial & Pajak</label>
          <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-2xs">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">Incentive</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">DPP</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">DPP Lain</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">PPN</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">Nilai PPh</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">Net Pay</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">Cek Pajak</th>
                  <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">Selisih</th>
                  <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Note PPh</th>
                  <th scope="col" class="py-2.5 px-3 border-r border-gray-200">No Faktur</th>
                  <th scope="col" class="py-2.5 px-3">Tgl Faktur</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr class="whitespace-nowrap font-normal">
                  <td class="py-3 px-3 text-right border-r border-gray-100 font-semibold text-gray-900">
                    {{ formatRupiah(selectedItem.incentive) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100 text-gray-800">
                    {{ formatRupiah(selectedItem.dpp) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100 text-gray-800">
                    {{ formatRupiah(selectedItem.dpp_lain) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100 text-gray-800">
                    {{ formatRupiah(selectedItem.ppn) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100 text-gray-800">
                    {{ formatRupiah(selectedItem.nilai_pph) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100 font-bold text-gray-950">
                    {{ formatRupiah(selectedItem.net_pay) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100 text-gray-800">
                    {{ formatRupiah(selectedItem.cek_pajak_tarif_pph) }}
                  </td>
                  <td class="py-3 px-3 text-right border-r border-gray-100">
                    <span v-if="selectedItem.selisih === 0 || selectedItem.selisih === '0' || selectedItem.selisih === 0.0" class="text-gray-400">0</span>
                    <span v-else-if="selectedItem.selisih !== null && selectedItem.selisih !== undefined">{{ formatRupiah(selectedItem.selisih) }}</span>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="py-3 px-3 text-center border-r border-gray-100 text-gray-700">
                    {{ selectedItem.note_pph || '-' }}
                  </td>
                  <td class="py-3 px-3 border-r border-gray-100 text-gray-800 font-mono text-[11px]">
                    {{ selectedItem.no_faktur || '-' }}
                  </td>
                  <td class="py-3 px-3 text-gray-800">
                    {{ selectedItem.tgl_faktur || '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Dokumen Pendukung Links -->
        <div class="pt-2 flex items-center justify-between text-xs border-t border-gray-100">
          <div class="flex items-center gap-3">
            <span class="text-gray-500 font-medium">Dokumen:</span>
            <a v-if="selectedItem.credit_note_url" :href="selectedItem.credit_note_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline hover:text-blue-800">Lihat CN</a>
            <span v-else class="text-gray-400">CN (-)</span>

            <a v-if="selectedItem.agreement_url" :href="selectedItem.agreement_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline hover:text-blue-800">Lihat Agr</a>
            <span v-else class="text-gray-400">Agr (-)</span>

            <a v-if="selectedItem.tax_invoice_url" :href="selectedItem.tax_invoice_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline hover:text-blue-800">Lihat Faktur</a>
            <span v-else class="text-gray-400">Faktur (-)</span>
          </div>

          <button
            type="button"
            @click="isFinancialModalOpen = false"
            class="px-4 h-8 text-xs font-medium rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 transition cursor-pointer"
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
import {
  ChevronRight as ChevronRightIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronDown as ChevronDownIcon,
  RefreshCw as RefreshCwIcon,
  ExternalLink as ExternalLinkIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  Settings as SettingsIcon,
  Copy as CopyIcon,
  Check as CheckIcon,
  Search as SearchIcon,
  X as XIcon,
  MoreHorizontal as MoreHorizontalIcon,
  Eye as EyeIcon,
  EyeOff as EyeOffIcon,
  Bot as BotIcon,
} from 'lucide-vue-next';

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
const regionOptions = ref([
  'BIG BANDUNG',
  'BIG KARAWANG',
  'BIG TASIK',
  'BIG CIREBON',
]);

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

      if (res.data.regions?.length) {
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
const googleSheetUrl = ref('https://docs.google.com/spreadsheets/d/1rDYiHsNR43H44g2xyV-igyp1Ijv_jp8v8FblAMHqof8/edit?gid=0#gid=0');
const isConfigModalOpen = ref(false);
const isSavingConfig = ref(false);
const configWebappUrl = ref('https://script.google.com/macros/s/AKfycbz7fKNsgUfZeVtRVXGkZIKTr7PMQJPCcwuJZGkxAW_qSa2n_4m9z-459-FCaaSrxYhq/exec');
const configSpreadsheetUrl = ref('https://docs.google.com/spreadsheets/d/1rDYiHsNR43H44g2xyV-igyp1Ijv_jp8v8FblAMHqof8/edit?gid=0#gid=0');
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
var SCM_WEBHOOK_URL = "https://scm-customer.oceanspace.co.id/api/webhooks/form-program";

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

async function openConfigModal() {
  saveMessage.value = '';
  isConfigModalOpen.value = true;
  try {
    const res = await axios.get('/api/program-submissions/config');
    if (res.data) {
      if (res.data.spreadsheet_url) {
        configSpreadsheetUrl.value = res.data.spreadsheet_url;
        googleSheetUrl.value = res.data.spreadsheet_url;
      }
      if (res.data.webapp_url) {
        configWebappUrl.value = res.data.webapp_url;
      }
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
