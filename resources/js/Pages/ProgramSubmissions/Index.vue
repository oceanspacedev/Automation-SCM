<template>
  <div class="space-y-4">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold tracking-tight text-gray-900">Form Program</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Sinkronisasi respon Google Spreadsheet program cashback, SO, dan kelayakan dokumen.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Dropdown Spreadsheet -->
        <div class="relative" ref="spreadsheetDropdownRef">
          <button
            type="button"
            @click="showSpreadsheetDropdown = !showSpreadsheetDropdown"
            class="h-8 px-2.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white text-xs font-normal text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs cursor-pointer"
            title="Menu aksi Spreadsheet & Export"
          >
            <RefreshCwIcon v-if="isSyncing" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
            <FileSpreadsheetIcon v-else class="w-3.5 h-3.5 text-gray-500" />
            <span>{{ isSyncing ? 'Menyinkronkan...' : 'Spreadsheet' }}</span>
            <ChevronDownIcon class="w-3 h-3 text-gray-400 ml-0.5 transition-transform duration-150" :class="showSpreadsheetDropdown && 'rotate-180'" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showSpreadsheetDropdown"
            class="absolute right-0 top-full mt-1.5 w-44 bg-white rounded-lg shadow-lg border border-gray-200 p-1 z-30 font-sans"
          >
            <button
              type="button"
              @click="handleTriggerSync"
              :disabled="isSyncing"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500 shrink-0', isSyncing && 'animate-spin text-emerald-600']" />
              <span>{{ isSyncing ? 'Menyinkronkan...' : 'Sinkronkan Sekarang' }}</span>
            </button>

            <a
              :href="googleSheetUrl"
              target="_blank"
              rel="noopener noreferrer"
              @click="showSpreadsheetDropdown = false"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer"
            >
              <ExternalLinkIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
              <span>Buka Spreadsheet</span>
            </a>

            <button
              type="button"
              @click="handleExportExcel"
              :disabled="isExporting"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-3.5 h-3.5 shrink-0">
                <path fill="#166e40" d="M37 6H17a2 2 0 0 0-2 2v32a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                <path fill="#23a455" d="M37 6H24v36h13a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                <path fill="#2ecc71" opacity=".35" d="M24 13h15v4H24zm0 7h15v4H24zm0 7h15v4H24zm0 7h15v4H24z"/>
                <path fill="#107c41" d="M22 13H8a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V15a2 2 0 0 0-2-2z"/>
                <path fill="#ffffff" d="M12.4 28.5l2.4-4.8 2.4 4.8h2.3l-3.5-6.5 3.3-6.5h-2.3l-2.2 4.7-2.2-4.7h-2.3l3.3 6.5-3.5 6.5h2.3z"/>
              </svg>
              <span>{{ isExporting ? 'Mengekspor...' : 'Export Excel' }}</span>
            </button>
          </div>
        </div>

        <!-- Dropdown Fitur AI -->
        <div class="relative" ref="aiDropdownRef">
          <button
            type="button"
            @click="showAiDropdown = !showAiDropdown"
            class="h-8 px-2.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white text-xs font-normal text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs cursor-pointer"
          >
            <BotIcon class="w-3.5 h-3.5 text-gray-500" />
            <span>Fitur AI</span>
            <ChevronDownIcon class="w-3 h-3 text-gray-400 ml-0.5 transition-transform duration-150" :class="showAiDropdown && 'rotate-180'" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showAiDropdown"
            class="absolute right-0 top-full mt-1.5 w-48 bg-white rounded-lg shadow-lg border border-gray-200 p-1 z-30 font-sans"
          >
            <button
              type="button"
              @click="handleOpenModelAiModal"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer"
            >
              <BotIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>Model AI</span>
            </button>

            <button
              type="button"
              @click="handleTriggerBgAi"
              :disabled="isTriggeringBgAi || aiStats.is_running"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <RefreshCwIcon v-if="isTriggeringBgAi || aiStats.is_running" class="w-3.5 h-3.5 animate-spin text-emerald-600 shrink-0" />
              <BotIcon v-else class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>{{ (isTriggeringBgAi || aiStats.is_running) ? 'AI Sedang Berjalan...' : 'Jalankan AI Background' }}</span>
            </button>

            <button
              type="button"
              @click="handleOpenBatchAiModal"
              :disabled="isAnalyzingBatch"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <RefreshCwIcon v-if="isAnalyzingBatch" class="w-3.5 h-3.5 animate-spin text-gray-500 shrink-0" />
              <BotIcon v-else class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>{{ isAnalyzingBatch ? 'Menganalisis...' : 'Analisis AI Semua' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Alert Status Sinkronisasi / Update -->
    <div
      v-if="syncMessage"
      :class="[
        'p-3 rounded-lg border text-xs flex items-center justify-between transition',
        syncError
          ? 'bg-white border-rose-200 text-rose-700'
          : 'bg-white border-gray-200 text-gray-800'
      ]"
    >
      <div class="flex items-center gap-2">
        <AlertCircleIcon v-if="syncError" class="w-4 h-4 shrink-0 text-rose-500" />
        <CheckCircleIcon v-else class="w-4 h-4 shrink-0 text-emerald-600" />
        <span>{{ syncMessage }}</span>
      </div>
      <button @click="syncMessage = ''" class="text-xs font-medium text-gray-400 hover:text-gray-700 ml-4 cursor-pointer">
        &times;
      </button>
    </div>

    <!-- Toolbar Filters -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 py-1">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
          <SearchIcon class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" />
          <input
            v-model="filters.search"
            @input="debounceFetch"
            type="text"
            placeholder="Cari dealer, ID Real, program, sales, No PO/SJ, TRX..."
            class="h-9 w-64 sm:w-80 pl-9 pr-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
          />
        </div>

        <!-- Filter Region -->
        <select
          v-model="filters.region"
          @change="fetchSubmissions(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Region</option>
          <option v-for="reg in regionOptions" :key="reg" :value="reg">
            {{ reg }}
          </option>
        </select>

        <!-- Filter Nama Program (Searchable Dropdown) -->
        <div class="relative" ref="programDropdownRef">
          <button
            type="button"
            @click="toggleProgramDropdown"
            class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs flex items-center justify-between gap-2 hover:border-gray-300 focus:outline-none focus:ring-1 focus:ring-black transition cursor-pointer min-w-[140px] max-w-[240px]"
            :class="filters.program ? 'border-gray-900 text-gray-900 font-medium bg-gray-50/60' : 'text-gray-700'"
            :title="filters.program || 'Filter berdasarkan Nama Program'"
          >
            <span class="truncate text-left">
              {{ filters.program || 'Semua Program' }}
            </span>
            <div class="flex items-center gap-1 shrink-0">
              <span
                v-if="filters.program"
                @click.stop="clearProgramFilter"
                class="text-gray-400 hover:text-gray-700 p-0.5 rounded cursor-pointer"
                title="Hapus filter program"
              >
                <XIcon class="w-3 h-3" />
              </span>
              <ChevronDownIcon
                class="w-3.5 h-3.5 text-gray-400 transition-transform duration-150"
                :class="showProgramDropdown && 'rotate-180'"
              />
            </div>
          </button>

          <!-- Dropdown Popover -->
          <div
            v-if="showProgramDropdown"
            class="absolute top-full left-0 mt-1.5 w-72 sm:w-84 max-w-[90vw] bg-white border border-gray-200 rounded-lg shadow-xl z-50 overflow-hidden text-xs"
          >
            <!-- Search Inside Dropdown -->
            <div class="p-2 border-b border-gray-100 bg-gray-50/70 flex items-center gap-2">
              <SearchIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
              <input
                ref="programSearchInput"
                v-model="programSearchQuery"
                type="text"
                placeholder="Cari nama program..."
                class="w-full bg-transparent border-none text-xs text-gray-900 placeholder-gray-400 focus:outline-none"
                @click.stop
                @keydown.esc="showProgramDropdown = false"
              />
              <button
                v-if="programSearchQuery"
                @click.stop="programSearchQuery = ''"
                type="button"
                class="text-gray-400 hover:text-gray-600 cursor-pointer"
              >
                <XIcon class="w-3 h-3" />
              </button>
            </div>

            <!-- Program List Items -->
            <div class="max-h-60 overflow-y-auto py-1 divide-y divide-gray-50">
              <button
                type="button"
                @click="selectProgram('')"
                class="w-full text-left px-3 py-2 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer text-xs"
                :class="!filters.program ? 'bg-gray-50 font-medium text-gray-900' : 'text-gray-600'"
              >
                <span>Semua Program</span>
                <CheckIcon v-if="!filters.program" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
              </button>

              <div
                v-if="filteredProgramOptions.length === 0"
                class="px-3 py-4 text-center text-gray-400 text-xs"
              >
                Tidak ada nama program yang cocok
              </div>

              <button
                v-for="prog in filteredProgramOptions"
                :key="prog"
                type="button"
                @click="selectProgram(prog)"
                class="w-full text-left px-3 py-2 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer text-xs"
                :class="filters.program === prog ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700'"
              >
                <span class="truncate pr-2" :title="prog">{{ prog }}</span>
                <CheckIcon v-if="filters.program === prog" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
              </button>
            </div>

            <!-- Footer count -->
            <div class="px-3 py-1.5 bg-gray-50 border-t border-gray-100 text-[10px] text-gray-500 flex justify-between items-center">
              <span>{{ filteredProgramOptions.length }} program ditemukan</span>
              <span v-if="filters.program" class="text-black font-medium cursor-pointer hover:underline" @click="selectProgram('')">
                Reset
              </span>
            </div>
          </div>
        </div>

        <!-- Filter Status Purchase -->
        <select
          v-model="filters.status_purchase"
          @change="fetchSubmissions(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Status Purchase</option>
          <option v-for="opt in statusPurchaseOptions" :key="opt" :value="opt">
            {{ opt }}
          </option>
        </select>

        <!-- Filter Keterangan -->
        <select
          v-model="filters.keterangan"
          @change="fetchSubmissions(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Keterangan</option>
          <option v-for="opt in keteranganOptions" :key="opt" :value="opt">
            {{ opt }}
          </option>
        </select>

        <button
          v-if="filters.search || filters.region || filters.program || filters.status_purchase || filters.keterangan"
          type="button"
          @click="resetFilters"
          class="h-9 px-2.5 text-xs text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-md transition cursor-pointer"
        >
          Reset Filter
        </button>
      </div>

      <!-- Realtime Auto-Refresh & Status -->
      <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
          <input
            type="checkbox"
            v-model="autoRefresh"
            class="rounded border-gray-300 text-black focus:ring-black cursor-pointer"
          />
          <span>Auto-Refresh (30d)</span>
        </label>

        <span v-if="lastUpdatedText" class="text-xs text-gray-400">
          Update: {{ lastUpdatedText }}
        </span>
      </div>
    </div>

    <!-- Official Shadcn Table Card (Horizontal Scrollable) -->
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
      <div class="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow class="border-b border-gray-200 text-xs hover:bg-transparent">
              <TableHead class="w-[44px] text-center font-medium text-gray-500">No</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Waktu</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Region</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">ID Real</TableHead>
              <TableHead class="min-w-[170px] font-medium text-gray-500">Nama Dealer</TableHead>
              <TableHead class="min-w-[200px] font-medium text-gray-500">Nama Program</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Nama Sales</TableHead>
              <TableHead class="min-w-[65px] text-center font-medium text-gray-500">CN</TableHead>
              <TableHead class="min-w-[65px] text-center font-medium text-gray-500">Agr</TableHead>
              <TableHead class="min-w-[70px] text-center font-medium text-gray-500">Faktur</TableHead>

              <!-- 11 Kolom Finansial & Audit Pajak (Sesuai Format OneDrive) -->
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">Incentive</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">DPP</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">DPP Lain</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">PPN</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">Nilai PPh</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">Net Pay</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">Cek Pajak</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500 text-right">Selisih</TableHead>
              <TableHead class="min-w-[100px] text-center font-medium text-gray-500">Note PPh</TableHead>
              <TableHead class="min-w-[140px] font-medium text-gray-500">No Faktur</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl Faktur</TableHead>

              <!-- Kolom Tracking Manual & Status Potong -->
              <TableHead class="whitespace-nowrap font-medium text-gray-500">No PO/SJ</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">No Transaksi</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl Input</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl Share CN</TableHead>
              <TableHead class="w-[80px] text-center font-medium text-gray-500">Pending</TableHead>
              <TableHead class="min-w-[150px] font-medium text-gray-500">Keterangan</TableHead>
              <TableHead class="min-w-[150px] font-medium text-gray-500">Cek Dokumen</TableHead>
              <TableHead class="min-w-[190px] font-medium text-gray-500">Status Potong Purchase</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Status AR</TableHead>
              <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl Potong/TF</TableHead>
              <TableHead class="w-[90px] text-center font-medium text-gray-500 sticky right-0 bg-white border-b border-gray-200">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <!-- Loading State -->
            <TableEmpty v-if="loading && submissions.length === 0" :colspan="32">
              <div class="inline-flex items-center gap-2 text-gray-500 py-8">
                <RefreshCwIcon class="w-4 h-4 animate-spin text-gray-400" />
                <span>Memuat data form program...</span>
              </div>
            </TableEmpty>

            <!-- Empty State -->
            <TableEmpty v-else-if="submissions.length === 0" :colspan="32">
              <div class="max-w-md mx-auto py-8 space-y-1.5 text-center text-gray-500">
                <p class="font-medium text-gray-800">Belum ada data form program yang tersimpan.</p>
                <p class="text-xs text-gray-500">
                  Klik tombol <strong>"Sinkronkan Sekarang"</strong> di atas untuk memuat data dari spreadsheet Anda.
                </p>
              </div>
            </TableEmpty>

            <!-- Data Rows -->
            <TableRow
              v-else
              v-for="(row, idx) in submissions"
              :key="row.id"
              class="hover:bg-gray-50/80 transition text-xs"
            >
              <!-- No -->
              <TableCell class="text-center text-gray-500 py-2.5">
                {{ (pagination.current_page - 1) * pagination.per_page + idx + 1 }}
              </TableCell>

              <!-- Waktu -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5 leading-tight">
                <div>{{ formatTimestamp(row.submission_timestamp).date }}</div>
                <div class="text-[11px] text-gray-400">{{ formatTimestamp(row.submission_timestamp).time }}</div>
              </TableCell>

              <!-- Region -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5">
                {{ row.region || '-' }}
              </TableCell>

              <!-- ID Real -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.id_real || '-' }}
              </TableCell>

              <!-- Nama Dealer -->
              <TableCell class="text-gray-700 py-2.5 leading-snug">
                <div class="line-clamp-2 text-gray-800" :title="row.dealer_name">{{ row.dealer_name || '-' }}</div>
              </TableCell>

              <!-- Nama Program -->
              <TableCell class="text-gray-700 py-2.5 leading-snug">
                <div class="line-clamp-2" :title="row.program_name">{{ row.program_name || '-' }}</div>
              </TableCell>

              <!-- Nama Sales -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5">
                {{ row.sales_name || '-' }}
              </TableCell>

              <!-- Dokumen Credit Note -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.credit_note_url)">
                  <a
                    v-if="row.doc_validation?.cn?.status === 'swapped'"
                    :href="row.credit_note_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs transition cursor-pointer font-medium"
                    :title="row.doc_validation.cn.message || 'File di kolom CN terdeteksi tertukar (berisi ' + formatDocTypeName(row.doc_validation.cn.actual_type) + ')'"
                  >
                    <AlertTriangleIcon class="w-3 h-3 text-amber-600 shrink-0" />
                    <span>CN</span>
                    <span class="text-[10px] text-amber-700 bg-amber-200/60 px-1 py-0.2 rounded font-normal leading-none">isi: {{ formatDocTypeName(row.doc_validation.cn.actual_type) }}</span>
                  </a>
                  <a
                    v-else-if="row.doc_validation?.cn?.status === 'invalid'"
                    :href="row.credit_note_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs transition cursor-pointer font-medium"
                    :title="row.doc_validation.cn.message || 'Dokumen di kolom CN tidak sesuai / bukan Credit Note'"
                  >
                    <XCircleIcon class="w-3 h-3 text-rose-600 shrink-0" />
                    <span>CN</span>
                    <span class="text-[10px] text-rose-600 bg-rose-200/60 px-1 py-0.2 rounded font-normal leading-none">Salah</span>
                  </a>
                  <a
                    v-else
                    :href="row.credit_note_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer"
                    title="Buka Dokumen Credit Note di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>CN</span>
                  </a>
                </template>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- Agreement -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.agreement_url)">
                  <a
                    v-if="row.doc_validation?.agr?.status === 'swapped'"
                    :href="row.agreement_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs transition cursor-pointer font-medium"
                    :title="row.doc_validation.agr.message || 'File di kolom Agr terdeteksi tertukar (berisi ' + formatDocTypeName(row.doc_validation.agr.actual_type) + ')'"
                  >
                    <AlertTriangleIcon class="w-3 h-3 text-amber-600 shrink-0" />
                    <span>Agr</span>
                    <span class="text-[10px] text-amber-700 bg-amber-200/60 px-1 py-0.2 rounded font-normal leading-none">isi: {{ formatDocTypeName(row.doc_validation.agr.actual_type) }}</span>
                  </a>
                  <a
                    v-else-if="row.doc_validation?.agr?.status === 'invalid'"
                    :href="row.agreement_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs transition cursor-pointer font-medium"
                    :title="row.doc_validation.agr.message || 'Dokumen di kolom Agr tidak sesuai / bukan Agreement'"
                  >
                    <XCircleIcon class="w-3 h-3 text-rose-600 shrink-0" />
                    <span>Agr</span>
                    <span class="text-[10px] text-rose-600 bg-rose-200/60 px-1 py-0.2 rounded font-normal leading-none">Salah</span>
                  </a>
                  <a
                    v-else
                    :href="row.agreement_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer"
                    title="Buka Dokumen Agreement di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>Agr</span>
                  </a>
                </template>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- Faktur Pajak -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.tax_invoice_url)">
                  <a
                    v-if="row.doc_validation?.faktur?.status === 'swapped'"
                    :href="row.tax_invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs transition cursor-pointer font-medium"
                    :title="row.doc_validation.faktur.message || 'File di kolom Faktur terdeteksi tertukar (berisi ' + formatDocTypeName(row.doc_validation.faktur.actual_type) + ')'"
                  >
                    <AlertTriangleIcon class="w-3 h-3 text-amber-600 shrink-0" />
                    <span>Faktur</span>
                    <span class="text-[10px] text-amber-700 bg-amber-200/60 px-1 py-0.2 rounded font-normal leading-none">isi: {{ formatDocTypeName(row.doc_validation.faktur.actual_type) }}</span>
                  </a>
                  <a
                    v-else-if="row.doc_validation?.faktur?.status === 'invalid'"
                    :href="row.tax_invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs transition cursor-pointer font-medium"
                    :title="row.doc_validation.faktur.message || 'Dokumen di kolom Faktur tidak sesuai / bukan Faktur Pajak'"
                  >
                    <XCircleIcon class="w-3 h-3 text-rose-600 shrink-0" />
                    <span>Faktur</span>
                    <span class="text-[10px] text-rose-600 bg-rose-200/60 px-1 py-0.2 rounded font-normal leading-none">Salah</span>
                  </a>
                  <a
                    v-else
                    :href="row.tax_invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer"
                    title="Buka Faktur Pajak di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>Faktur</span>
                  </a>
                </template>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 11 Kolom Finansial & Audit Pajak (Format OneDrive) -->
              <!-- Incentive -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.incentive) }}
              </TableCell>

              <!-- DPP -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.dpp) }}
              </TableCell>

              <!-- DPP Lain -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.dpp_lain) }}
              </TableCell>

              <!-- PPN -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.ppn) }}
              </TableCell>

              <!-- Nilai PPh -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.nilai_pph) }}
              </TableCell>

              <!-- Net Pay -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.net_pay) }}
              </TableCell>

              <!-- Cek Pajak Tarif PPh -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.cek_pajak_tarif_pph) }}
              </TableCell>

              <!-- Selisih -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                <span v-if="row.selisih === 0 || row.selisih === '0' || row.selisih === 0.0" class="text-gray-400">
                  0
                </span>
                <span v-else-if="row.selisih !== null && row.selisih !== undefined && row.selisih !== ''">
                  {{ formatRupiah(row.selisih) }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- Note PPh -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <span
                  v-if="row.note_pph === 'ok'"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold"
                >
                  <CheckIcon class="w-3 h-3" />
                  <span>ok</span>
                </span>
                <span
                  v-else-if="row.note_pph"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-medium"
                >
                  <AlertCircleIcon class="w-3 h-3 text-amber-600 shrink-0" />
                  <span>{{ row.note_pph }}</span>
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- No Faktur -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_faktur || '-' }}
              </TableCell>

              <!-- Tgl Faktur -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_faktur || '-' }}
              </TableCell>

              <!-- Kolom: No PO/SJ -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_po_sj || '-' }}
              </TableCell>

              <!-- Kolom: No Transaksi -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_transaksi || '-' }}
              </TableCell>

              <!-- Kolom: Tgl Input -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5">
                {{ row.tgl_input || '-' }}
              </TableCell>

              <!-- Kolom: Tgl Share CN -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5">
                {{ row.tgl_share_cn || '-' }}
              </TableCell>

              <!-- Kolom: Lama Pending -->
              <TableCell class="text-center text-gray-700 py-2.5 whitespace-nowrap">
                <span v-if="row.lama_pending" class="px-1.5 py-0.5 rounded bg-white border border-gray-200 text-xs text-gray-700">
                  {{ row.lama_pending }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- Kolom: Keterangan (Dropdown Polos Putih) -->
              <TableCell class="py-2.5 whitespace-nowrap">
                <select
                  :value="row.keterangan || ''"
                  @change="quickUpdateKeterangan(row, $event.target.value)"
                  class="h-7 px-2 rounded-md text-xs bg-white text-gray-800 border border-gray-200 hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer font-normal"
                >
                  <option value="">- Pilih Keterangan -</option>
                  <option v-for="opt in keteranganOptions" :key="opt" :value="opt">
                    {{ opt }}
                  </option>
                </select>
              </TableCell>

              <!-- Kolom: Cek Dokumen -->
              <TableCell class="text-gray-700 py-2.5 leading-snug">
                <div
                  :class="[
                    'line-clamp-2 max-w-[160px] text-xs px-1.5 py-0.5 rounded inline-block',
                    row.cek_dokumen === 'LENGKAP'
                      ? 'bg-teal-50 text-teal-700 border border-teal-200'
                      : row.cek_dokumen
                      ? 'bg-amber-50 text-amber-800 border border-amber-200'
                      : 'text-gray-400'
                  ]"
                  :title="row.cek_dokumen"
                >
                  {{ row.cek_dokumen || '-' }}
                </div>
              </TableCell>

              <!-- Kolom: Status Potong by Purchase (Dropdown Cepat Langsung di Baris) -->
              <TableCell class="py-2.5 whitespace-nowrap">
                <select
                  :value="row.status_potong_purchase || ''"
                  @change="quickUpdateStatus(row, $event.target.value)"
                  :class="[
                    'h-7 px-2 rounded-md text-xs font-normal border focus:outline-none focus:ring-1 focus:ring-black cursor-pointer transition',
                    row.status_potong_purchase === 'BISA DI POTONG'
                      ? 'bg-teal-50 text-teal-700 border-teal-300'
                      : row.status_potong_purchase === 'SUDAH POTONG'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                      : row.status_potong_purchase === 'DONE TRANSFER'
                      ? 'bg-blue-50 text-blue-700 border-blue-300'
                      : row.status_potong_purchase === 'BELUM BISA POTONG'
                      ? 'bg-rose-50 text-rose-700 border-rose-300'
                      : 'bg-gray-50 text-gray-500 border-gray-200'
                  ]"
                >
                  <option value="">- Pilih Status -</option>
                  <option value="BELUM BISA POTONG">BELUM BISA POTONG</option>
                  <option value="BISA DI POTONG">BISA DI POTONG</option>
                  <option value="SUDAH POTONG">SUDAH POTONG</option>
                  <option value="DONE TRANSFER">DONE TRANSFER</option>
                </select>
              </TableCell>

              <!-- Kolom: Status Potong by AR -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5">
                <span v-if="row.status_potong_ar" class="px-1.5 py-0.5 rounded bg-white border border-gray-200 text-gray-700 text-xs">
                  {{ row.status_potong_ar }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- Kolom: Tgl Potong/TF -->
              <TableCell class="whitespace-nowrap text-gray-700 py-2.5">
                {{ row.tgl_potong_tf || '-' }}
              </TableCell>

              <!-- Aksi: Tombol AI Baris, WA AR, & Edit Row Modal -->
              <TableCell class="text-center py-2.5 whitespace-nowrap sticky right-0 bg-white">
                <div class="inline-flex items-center justify-center gap-1.5">
                  <!-- Tombol Analisis AI Khusus Baris Ini -->
                  <button
                    type="button"
                    @click="analyzeRowWithAi(row)"
                    :disabled="analyzingRowId === row.id"
                    class="w-8 h-8 shrink-0 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition shadow-2xs cursor-pointer disabled:opacity-50"
                    title="Analisis AI untuk Baris Ini (Baca Dokumen & Finansial)"
                  >
                    <RefreshCwIcon v-if="analyzingRowId === row.id" class="w-3.5 h-3.5 animate-spin text-gray-500" />
                    <BotIcon v-else class="w-3.5 h-3.5 text-gray-600" />
                  </button>

                  <button
                    v-if="row.status_potong_purchase === 'BISA DI POTONG'"
                    type="button"
                    @click="sendWaToAr(row)"
                    :disabled="sendingWaId === row.id"
                    class="w-8 h-8 shrink-0 inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition shadow-2xs cursor-pointer disabled:opacity-50"
                    title="Kirim Notifikasi Klaim ke WhatsApp AR (081224290502)"
                  >
                    <RefreshCwIcon v-if="sendingWaId === row.id" class="w-3.5 h-3.5 animate-spin" />
                    <SendIcon v-else class="w-3.5 h-3.5" />
                  </button>

                  <button
                    type="button"
                    @click="openEditModal(row)"
                    class="w-8 h-8 shrink-0 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-gray-600 hover:text-gray-900 transition shadow-2xs cursor-pointer"
                    title="Edit Data Tracking & Status Potong"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="pagination.total > 0"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-200 bg-white text-xs text-gray-500"
      >
        <div class="flex items-center gap-3">
          <div>
            Menampilkan <span class="font-semibold text-gray-900">{{ submissions.length }}</span> dari
            <span class="font-semibold text-gray-900">{{ Number(pagination.total).toLocaleString('id-ID') }}</span> total respon
          </div>
          <div class="flex items-center gap-1.5 ml-2 pl-3 border-l border-gray-200">
            <span>Per halaman:</span>
            <select
              v-model="pagination.per_page"
              @change="changePerPage"
              class="h-7 px-1.5 rounded border border-gray-200 bg-white text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
            >
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-1.5">
          <button
            type="button"
            :disabled="pagination.current_page <= 1 || loading"
            @click="fetchSubmissions(1)"
            class="px-2 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
            title="Halaman Pertama"
          >
            &laquo; Pertama
          </button>
          <button
            type="button"
            :disabled="pagination.current_page <= 1 || loading"
            @click="fetchSubmissions(pagination.current_page - 1)"
            class="px-2.5 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
          >
            Sebelumnya
          </button>

          <div class="flex items-center gap-1 px-1">
            <span>Halaman</span>
            <input
              type="number"
              min="1"
              :max="pagination.last_page"
              :value="pagination.current_page"
              @keydown.enter="onPageInputEnter($event)"
              @blur="onPageInputBlur($event)"
              class="w-14 h-7 text-center rounded border border-gray-200 bg-white text-xs font-semibold text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
              title="Ketik nomor halaman lalu tekan Enter"
            />
            <span>dari {{ pagination.last_page || 1 }}</span>
          </div>

          <button
            type="button"
            :disabled="pagination.current_page >= pagination.last_page || loading"
            @click="fetchSubmissions(pagination.current_page + 1)"
            class="px-2.5 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
          >
            Selanjutnya
          </button>
          <button
            type="button"
            :disabled="pagination.current_page >= pagination.last_page || loading"
            @click="fetchSubmissions(pagination.last_page)"
            class="px-2 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
            title="Halaman Terakhir"
          >
            Terakhir &raquo;
          </button>

          <RefreshCwIcon v-if="loading" class="w-3.5 h-3.5 text-gray-400 animate-spin ml-1.5" />
        </div>
      </div>
    </div>

    <!-- Edit Tracking & Status Potong Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showEditModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
          @click.self="showEditModal = false"
        >
          <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden font-sans">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
              <div>
                <h3 class="text-base font-bold text-gray-900">Edit Data Tracking & Status Potong</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                  Dealer: <strong class="text-gray-800">{{ editingSubmission?.dealer_name || '-' }}</strong> 
                  <span v-if="editingSubmission?.id_real">({{ editingSubmission?.id_real }})</span>
                  &bull; {{ editingSubmission?.program_name }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="analyzeCurrentRowWithAi"
                  :disabled="isAnalyzingCurrent"
                  class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium transition cursor-pointer shadow-2xs disabled:opacity-50"
                  title="Analisis dokumen baris ini dengan AI sekarang"
                >
                  <RefreshCwIcon v-if="isAnalyzingCurrent" class="w-3.5 h-3.5 animate-spin text-gray-500" />
                  <BotIcon v-else class="w-3.5 h-3.5 text-gray-600" />
                  <span>{{ isAnalyzingCurrent ? 'Menganalisis...' : 'Analisis AI Baris Ini' }}</span>
                </button>
                <button
                  type="button"
                  @click="showEditModal = false"
                  class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
                >
                  <XIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Modal Form Body -->
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
              <!-- Warning banner if any document is swapped or invalid -->
              <div
                v-if="hasSwappedOrInvalidDocs(editingSubmission)"
                class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-xs space-y-1.5"
              >
                <div class="font-semibold flex items-center gap-1.5 text-amber-900">
                  <AlertTriangleIcon class="w-4 h-4 text-amber-600 shrink-0" />
                  <span>Peringatan Kelayakan Dokumen:</span>
                </div>
                <ul class="list-disc list-inside text-[11px] text-amber-800 space-y-0.5 pl-1">
                  <li v-if="editingSubmission.doc_validation?.cn?.status !== 'valid' && editingSubmission.doc_validation?.cn">
                    <strong>Kolom CN:</strong> {{ editingSubmission.doc_validation.cn.message || 'File tidak sesuai atau tertukar' }}
                  </li>
                  <li v-if="editingSubmission.doc_validation?.agr?.status !== 'valid' && editingSubmission.doc_validation?.agr">
                    <strong>Kolom Agr:</strong> {{ editingSubmission.doc_validation.agr.message || 'File tidak sesuai atau tertukar' }}
                  </li>
                  <li v-if="editingSubmission.doc_validation?.faktur?.status !== 'valid' && editingSubmission.doc_validation?.faktur">
                    <strong>Kolom Faktur:</strong> {{ editingSubmission.doc_validation.faktur.message || 'File tidak sesuai atau tertukar' }}
                  </li>
                </ul>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- No PO/SJ -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">NO PO / SJ:</label>
                  <input
                    v-model="editForm.no_po_sj"
                    type="text"
                    placeholder="Contoh: PO/2026/09/123"
                    class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                  />
                </div>

                <!-- No Transaksi -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">NO TRANSAKSI:</label>
                  <input
                    v-model="editForm.no_transaksi"
                    type="text"
                    placeholder="Contoh: TRX-998822"
                    class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                  />
                </div>

                <!-- Tgl Input -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">Tgl Input:</label>
                  <DatePicker
                    v-model="editForm.tgl_input"
                    placeholder="Pilih Tgl Input"
                    format="DD/MM/YYYY"
                  />
                </div>

                <!-- Tgl Share CN -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">Tgl Share CN:</label>
                  <DatePicker
                    v-model="editForm.tgl_share_cn"
                    placeholder="Pilih Tgl Share CN"
                    format="DD/MM/YYYY"
                  />
                </div>

                <!-- Lama Pending -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">Lama Pending:</label>
                  <input
                    v-model="editForm.lama_pending"
                    type="text"
                    placeholder="Contoh: 1 Hari / Kurang dari 30 Hari"
                    class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                  />
                </div>

                <!-- Tanggal Potong/TF -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">TANGGAL POTONG/TF:</label>
                  <DatePicker
                    v-model="editForm.tgl_potong_tf"
                    placeholder="Pilih Tgl Potong/TF"
                    format="DD/MM/YYYY"
                  />
                </div>

                <!-- STATUS POTONG BY PURCHASE -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">STATUS POTONG BY PURCHASE:</label>
                  <select
                    v-model="editForm.status_potong_purchase"
                    class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs font-medium text-gray-900 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                  >
                    <option value="">- Belum Ditentukan -</option>
                    <option value="BELUM BISA POTONG">BELUM BISA POTONG</option>
                    <option value="BISA DI POTONG">BISA DI POTONG</option>
                    <option value="SUDAH POTONG">SUDAH POTONG</option>
                    <option value="DONE TRANSFER">DONE TRANSFER</option>
                  </select>
                </div>

                <!-- STATUS POTONG BY AR -->
                <div>
                  <label class="block font-semibold text-gray-700 mb-1">STATUS POTONG BY AR:</label>
                  <input
                    v-model="editForm.status_potong_ar"
                    type="text"
                    placeholder="Contoh: DONE / PENDING"
                    class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                  />
                </div>
              </div>

              <!-- CEK DOKUMEN -->
              <div>
                <label class="block font-semibold text-gray-700 mb-1">CEK DOKUMEN:</label>
                <input
                  v-model="editForm.cek_dokumen"
                  type="text"
                  placeholder="Contoh: LENGKAP, AGR BELUM ADA, FP KURANG..."
                  class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                />
              </div>

              <!-- Keterangan (Dropdown Polos Putih) -->
              <div>
                <label class="block font-semibold text-gray-700 mb-1">Keterangan:</label>
                <select
                  v-model="editForm.keterangan"
                  class="h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                >
                  <option value="">- Pilih Keterangan -</option>
                  <option v-for="opt in keteranganOptions" :key="opt" :value="opt">
                    {{ opt }}
                  </option>
                </select>
              </div>

              <!-- Data Finansial & Pajak (Sesuai OneDrive) -->
              <div class="pt-4 border-t border-gray-100 space-y-3">
                <h4 class="font-bold text-gray-900 flex items-center gap-1.5 text-xs">
                  <FileSpreadsheetIcon class="w-4 h-4 text-emerald-600" />
                  <span>Data Finansial & Audit Pajak (Format OneDrive)</span>
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">Incentive:</label>
                    <input
                      v-model.number="editForm.incentive"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">DPP:</label>
                    <input
                      v-model.number="editForm.dpp"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">DPP Lain:</label>
                    <input
                      v-model.number="editForm.dpp_lain"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">PPN:</label>
                    <input
                      v-model.number="editForm.ppn"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">Nilai PPh:</label>
                    <input
                      v-model.number="editForm.nilai_pph"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">Net Pay:</label>
                    <input
                      v-model.number="editForm.net_pay"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">Cek Pajak (Tarif):</label>
                    <input
                      v-model.number="editForm.cek_pajak_tarif_pph"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">Selisih:</label>
                    <input
                      v-model.number="editForm.selisih"
                      type="number"
                      step="any"
                      placeholder="0"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div class="col-span-2">
                    <label class="block font-semibold text-gray-700 mb-1">Note PPh (ok, CAP?, TTD?, NPWP?):</label>
                    <input
                      v-model="editForm.note_pph"
                      type="text"
                      placeholder="Contoh: ok, CAP?, TTD?, NPWP?"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">No Faktur:</label>
                    <input
                      v-model="editForm.no_faktur"
                      type="text"
                      placeholder="010.xxx-xx.xxxxxxxx"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1">Tgl Faktur:</label>
                    <input
                      v-model="editForm.tgl_faktur"
                      type="text"
                      placeholder="DD/MM/YYYY"
                      class="h-9 w-full px-2.5 rounded-md border border-gray-200 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-2">
              <button
                v-if="editingSubmission && editForm.status_potong_purchase === 'BISA DI POTONG'"
                type="button"
                @click="sendWaToAr(editingSubmission)"
                :disabled="isSendingWaModal"
                class="px-3 py-1.5 rounded-md border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-medium transition cursor-pointer flex items-center gap-1.5 shadow-2xs disabled:opacity-50"
                title="Kirim notifikasi klaim ini langsung ke WhatsApp AR"
              >
                <RefreshCwIcon v-if="isSendingWaModal" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
                <SendIcon v-else class="w-3.5 h-3.5 text-emerald-600" />
                <span>{{ isSendingWaModal ? 'Mengirim ke AR...' : 'Kirim WA ke AR' }}</span>
              </button>
              <div v-else></div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="showEditModal = false"
                  :disabled="isSaving"
                  class="px-3.5 py-1.5 rounded-md border border-gray-200 bg-white hover:bg-gray-100 text-xs text-gray-700 transition cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="button"
                  @click="saveEditModal"
                  :disabled="isSaving"
                  class="px-4 py-1.5 rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 text-xs font-medium transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                >
                  <RefreshCwIcon v-if="isSaving" class="w-3.5 h-3.5 animate-spin text-gray-500" />
                  <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal Model AI -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showModelAiModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
          @click.self="showModelAiModal = false"
        >
          <div class="relative w-full max-w-xl bg-white rounded-xl shadow-xl border border-gray-200 font-sans">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between rounded-t-xl">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center shrink-0 border border-gray-200">
                  <BotIcon class="w-4 h-4" />
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 leading-tight">Model AI</h3>
                  <p class="text-xs text-gray-500 mt-0.5">Pilih model AI untuk evaluasi kelayakan dokumen (CN, Agr, Faktur).</p>
                </div>
              </div>
              <button
                type="button"
                @click="showModelAiModal = false"
                class="w-8 h-8 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              >
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 text-xs">
              <!-- Active Model Highlight Banner -->
              <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                  <span class="text-gray-600 shrink-0">Model Aktif:</span>
                  <span class="font-mono text-[11px] font-semibold text-gray-900 bg-white px-2 py-0.5 rounded border border-gray-300 truncate">
                    {{ selectedModel }}
                  </span>
                </div>
                <span class="text-[11px] text-gray-500 font-medium shrink-0">Total {{ aiAvailableModels.length }} Model</span>
              </div>

              <!-- Custom Searchable Model Dropdown (Opens DOWNWARDS) -->
              <div class="relative" ref="modelDropdownRef">
                <label class="block font-medium text-gray-700 text-xs mb-1.5">Pilih Model AI (Semua Router):</label>

                <!-- Trigger Button -->
                <button
                  type="button"
                  @click="showModelDropdown = !showModelDropdown"
                  class="w-full h-9 px-3 bg-white border border-gray-300 rounded-md text-xs text-gray-900 flex items-center justify-between hover:border-gray-400 focus:outline-none focus:border-gray-900 transition cursor-pointer shadow-2xs"
                >
                  <div class="flex items-center gap-2 min-w-0 truncate">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    <span class="font-mono text-[11px] truncate text-gray-800">{{ selectedModel }}</span>
                  </div>
                  <ChevronDownIcon
                    class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-150"
                    :class="showModelDropdown && 'rotate-180'"
                  />
                </button>

                <!-- Dropdown Menu (Opens DOWNWARDS with top-full mt-1.5) -->
                <div
                  v-if="showModelDropdown"
                  class="absolute top-full left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-lg shadow-xl z-50 overflow-hidden text-xs"
                >
                  <!-- Search Input -->
                  <div class="p-2 border-b border-gray-100 bg-gray-50/70 flex items-center gap-2">
                    <SearchIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                    <input
                      v-model="modelSearchQuery"
                      type="text"
                      placeholder="Cari model AI... (contoh: gemini, gpt, deepseek, claude)"
                      class="w-full bg-transparent border-none text-xs text-gray-900 placeholder-gray-400 focus:outline-none"
                      @click.stop
                    />
                    <button
                      v-if="modelSearchQuery"
                      @click.stop="modelSearchQuery = ''"
                      type="button"
                      class="text-gray-400 hover:text-gray-600 cursor-pointer"
                    >
                      <XIcon class="w-3 h-3" />
                    </button>
                  </div>

                  <!-- Model List Items -->
                  <div class="max-h-48 overflow-y-auto py-1 divide-y divide-gray-50">
                    <div
                      v-if="filteredAiModels.length === 0"
                      class="px-3 py-4 text-center text-gray-400 text-xs"
                    >
                      Tidak ada model yang cocok
                    </div>
                    <button
                      v-for="m in filteredAiModels"
                      :key="m"
                      type="button"
                      @click="selectModel(m)"
                      class="w-full text-left px-3 py-2 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer"
                      :class="selectedModel === m ? 'bg-gray-50 font-medium text-gray-900' : 'text-gray-700'"
                    >
                      <div class="flex items-center gap-2 min-w-0">
                        <span
                          class="w-1.5 h-1.5 rounded-full shrink-0"
                          :class="selectedModel === m ? 'bg-emerald-500' : 'bg-transparent'"
                        ></span>
                        <span class="font-mono text-[11px] truncate">{{ m }}</span>
                      </div>
                      <CheckIcon v-if="selectedModel === m" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
                    </button>
                  </div>

                  <!-- Footer count -->
                  <div class="px-3 py-1.5 bg-gray-50 border-t border-gray-100 text-[10px] text-gray-500 flex justify-between items-center">
                    <span>{{ filteredAiModels.length }} model tersedia</span>
                    <span class="text-gray-400">Pilih untuk mengaktifkan</span>
                  </div>
                </div>
              </div>

              <!-- Quick Picks (Pilihan Model Unggulan) -->
              <div class="pt-1">
                <label class="block font-medium text-gray-700 text-xs mb-2">Pilihan Cepat Model Unggulan:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div
                    v-for="item in recommendedModels"
                    :key="item.id"
                    @click="selectModel(item.id)"
                    :class="[
                      'p-2.5 rounded-lg border transition cursor-pointer flex items-center justify-between text-left select-none',
                      selectedModel === item.id
                        ? 'bg-white border-gray-900 ring-1 ring-gray-900 shadow-xs'
                        : 'bg-white border-gray-200 hover:border-gray-400 hover:bg-gray-50/50'
                    ]"
                  >
                    <div class="min-w-0 pr-2">
                      <div class="font-medium text-xs text-gray-900 flex items-center gap-1.5">
                        <span class="truncate">{{ item.name }}</span>
                        <span v-if="item.badge" class="px-1.5 py-0.2 rounded text-[10px] font-normal bg-gray-100 text-gray-600 border border-gray-200 shrink-0">
                          {{ item.badge }}
                        </span>
                      </div>
                      <div class="text-[10px] text-gray-400 font-mono mt-0.5 truncate">{{ item.id }}</div>
                    </div>
                    <div
                      :class="[
                        'w-4 h-4 rounded-full border flex items-center justify-center shrink-0 transition',
                        selectedModel === item.id ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white'
                      ]"
                    >
                      <CheckIcon v-if="selectedModel === item.id" class="w-2.5 h-2.5 stroke-[3]" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-2 rounded-b-xl">
              <button
                type="button"
                @click="showModelAiModal = false"
                class="px-3.5 py-1.5 rounded-md border border-gray-200 bg-white hover:bg-gray-50 text-xs text-gray-700 transition cursor-pointer"
              >
                Tutup
              </button>
              <button
                type="button"
                @click="saveSelectedModel"
                :disabled="isSavingModel"
                class="px-4 py-1.5 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 text-xs font-medium transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
              >
                <RefreshCwIcon v-if="isSavingModel" class="w-3.5 h-3.5 animate-spin text-gray-500" />
                <span>{{ isSavingModel ? 'Menyimpan...' : 'Simpan Model Terpilih' }}</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal Analisis AI Batch -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showBatchAiModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
          @click.self="showBatchAiModal = false"
        >
          <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden font-sans">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <BotIcon class="w-4 h-4 text-gray-700" />
                <h3 class="text-sm font-semibold text-gray-900">Analisis AI Dokumen Batch</h3>
              </div>
              <button
                type="button"
                @click="showBatchAiModal = false"
                :disabled="isAnalyzingBatch"
                class="w-8 h-8 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              >
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <div class="p-6 space-y-3 text-xs">
              <p class="text-gray-600 leading-relaxed">
                Fitur ini akan menganalisis kelengkapan dokumen <strong>Credit Note (CN)</strong>, <strong>Agreement (Agr)</strong>, dan <strong>Faktur Pajak</strong> secara otomatis untuk <strong>SELURUH</strong> data program yang belum dicek tanpa batasan jumlah.
              </p>

              <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 space-y-1">
                <div class="font-medium flex items-center gap-1.5 text-gray-900">
                  <BotIcon class="w-4 h-4 text-gray-600" />
                  <span>Aturan Evaluasi AI:</span>
                </div>
                <ul class="list-disc list-inside text-[11px] space-y-0.5 text-gray-600">
                  <li>Ketiga dokumen lengkap &rarr; <strong>BISA DI POTONG</strong> & status LENGKAP</li>
                  <li>Ada dokumen kurang &rarr; <strong>BELUM BISA POTONG</strong> & rincian dokumen yang belum diunggah (misal: AGR & FAKTUR BELUM ADA)</li>
                </ul>
              </div>

              <!-- Batch Result summary if completed -->
              <div v-if="batchResultSummary" class="p-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 text-xs">
                {{ batchResultSummary }}
              </div>
            </div>

            <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-2">
              <button
                type="button"
                @click="showBatchAiModal = false"
                :disabled="isAnalyzingBatch"
                class="px-3.5 py-1.5 rounded-md border border-gray-200 bg-white hover:bg-gray-100 text-xs text-gray-700 transition cursor-pointer"
              >
                Tutup
              </button>
              <button
                type="button"
                @click="runBackgroundFromModal"
                :disabled="isAnalyzingBatch"
                class="px-3.5 py-1.5 rounded-md border border-gray-200 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                title="Jalankan di latar belakang agar bebas berpindah halaman"
              >
                <SparklesIcon class="w-3.5 h-3.5 text-gray-500" />
                <span>Jalankan di Background</span>
              </button>
              <button
                type="button"
                @click="runBatchAiAnalysis"
                :disabled="isAnalyzingBatch"
                class="px-4 py-1.5 rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 text-xs font-medium transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
              >
                <RefreshCwIcon v-if="isAnalyzingBatch" class="w-3.5 h-3.5 animate-spin text-gray-500" />
                <BotIcon v-else class="w-3.5 h-3.5 text-gray-600" />
                <span>{{ isAnalyzingBatch ? 'Sedang Menganalisis...' : 'Mulai Analisis Langsung' }}</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import {
  Search as SearchIcon,
  RefreshCw as RefreshCwIcon,
  ExternalLink as ExternalLinkIcon,
  AlertCircle as AlertCircleIcon,
  CheckCircle as CheckCircleIcon,
  Check as CheckIcon,
  FileText as FileTextIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  Pencil as PencilIcon,
  X as XIcon,
  Sparkles as SparklesIcon,
  Settings as SettingsIcon,
  Eye as EyeIcon,
  EyeOff as EyeOffIcon,
  Bot as BotIcon,
  ChevronDown as ChevronDownIcon,
  Send as SendIcon,
  AlertTriangle as AlertTriangleIcon,
  XCircle as XCircleIcon,
} from 'lucide-vue-next';
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
  TableEmpty,
} from '@/components/ui/table';
import DatePicker from '@/components/ui/DatePicker.vue';

const googleSheetUrl = 'https://docs.google.com/spreadsheets/d/1jf_i5r4Nn3q0RE6n_gIyCFn1XPlAWjYdqQOvWXewfXs/edit#gid=2012509458';

const submissions = ref([]);
const regionOptions = ref([]);
const programOptions = ref([]);
const statusPurchaseOptions = ref(['BELUM BISA POTONG', 'BISA DI POTONG', 'SUDAH POTONG', 'DONE TRANSFER']);
const keteranganOptions = ref(['LEBIH DARI 30 HARI', 'KURANG DARI 30 HARI']);
const loading = ref(false);
const isSyncing = ref(false);
const isExporting = ref(false);
const syncMessage = ref('');
const syncError = ref(false);
const autoRefresh = ref(true);
const lastUpdatedText = ref('');
const sendingWaId = ref(null);
const isSendingWaModal = ref(false);

// Dropdown Spreadsheet state
const showSpreadsheetDropdown = ref(false);
const spreadsheetDropdownRef = ref(null);

const handleClickOutsideSpreadsheetDropdown = (e) => {
  if (spreadsheetDropdownRef.value && !spreadsheetDropdownRef.value.contains(e.target)) {
    showSpreadsheetDropdown.value = false;
  }
};

const handleTriggerSync = () => {
  showSpreadsheetDropdown.value = false;
  triggerSync();
};

const handleExportExcel = () => {
  showSpreadsheetDropdown.value = false;
  exportExcel();
};

// AI state
const analyzingRowId = ref(null);
const isAnalyzingCurrent = ref(false);
const isAnalyzingBatch = ref(false);
const showBatchAiModal = ref(false);
const batchResultSummary = ref('');

// Dropdown Fitur AI state
const showAiDropdown = ref(false);
const aiDropdownRef = ref(null);

const handleClickOutsideAiDropdown = (e) => {
  if (aiDropdownRef.value && !aiDropdownRef.value.contains(e.target)) {
    showAiDropdown.value = false;
  }
};

const handleOpenModelAiModal = () => {
  showAiDropdown.value = false;
  openModelAiModal();
};

const handleTriggerBgAi = () => {
  showAiDropdown.value = false;
  triggerBackgroundAi();
};

const handleOpenBatchAiModal = () => {
  showAiDropdown.value = false;
  openBatchAiModal();
};

// Model AI modal & selection state
const showModelAiModal = ref(false);
const isSavingModel = ref(false);
const isTriggeringBgAi = ref(false);
const selectedModel = ref('ag/gemini-3-flash');
const aiAvailableModels = ref([]);
const showModelDropdown = ref(false);
const modelSearchQuery = ref('');
const modelDropdownRef = ref(null);

const filteredAiModels = computed(() => {
  if (!modelSearchQuery.value.trim()) {
    return aiAvailableModels.value;
  }
  const q = modelSearchQuery.value.toLowerCase().trim();
  return aiAvailableModels.value.filter((m) => m.toLowerCase().includes(q));
});

const selectModel = (modelId) => {
  selectedModel.value = modelId;
  showModelDropdown.value = false;
  modelSearchQuery.value = '';
};

const handleClickOutsideModelDropdown = (e) => {
  if (modelDropdownRef.value && !modelDropdownRef.value.contains(e.target)) {
    showModelDropdown.value = false;
  }
};

// Program filter dropdown state & helpers
const showProgramDropdown = ref(false);
const programDropdownRef = ref(null);
const programSearchQuery = ref('');
const programSearchInput = ref(null);

const filteredProgramOptions = computed(() => {
  const q = programSearchQuery.value.trim().toLowerCase();
  if (!q) return programOptions.value;
  return programOptions.value.filter((p) => p.toLowerCase().includes(q));
});

const toggleProgramDropdown = () => {
  showProgramDropdown.value = !showProgramDropdown.value;
  if (showProgramDropdown.value) {
    programSearchQuery.value = '';
    nextTick(() => {
      programSearchInput.value?.focus();
    });
  }
};

const selectProgram = (prog) => {
  filters.program = prog;
  showProgramDropdown.value = false;
  programSearchQuery.value = '';
  fetchSubmissions(1);
};

const clearProgramFilter = (e) => {
  if (e) e.stopPropagation();
  filters.program = '';
  programSearchQuery.value = '';
  fetchSubmissions(1);
};

const handleClickOutsideProgramDropdown = (e) => {
  if (programDropdownRef.value && !programDropdownRef.value.contains(e.target)) {
    showProgramDropdown.value = false;
  }
};

const aiStats = reactive({
  total_2026: 0,
  analyzed_2026: 0,
  unanalyzed_2026: 0,
  bisa_potong_count: 0,
  belum_bisa_potong_count: 0,
  is_running: false,
  running_info: null,
});

const recommendedModels = [
  {
    id: 'ag/gemini-3-flash',
    name: 'Gemini 3 Flash',
    badge: 'Rekomendasi',
    speed: '~1 detik',
    tag: 'Sangat Cepat & Patuh JSON',
    description: 'Model utama berkecepatan tinggi, konsisten menganalisis kelengkapan dokumen dengan akurat.',
  },
  {
    id: 'cx/gpt-5.4-mini',
    name: 'GPT 5.4 Mini',
    badge: 'Ringan',
    speed: '~1-2 detik',
    tag: 'Efisien & Cepat',
    description: 'Model ringkas dan cepat, sangat cocok untuk pemrosesan dokumen dalam jumlah besar.',
  },
  {
    id: 'ag/gemini-3.7-flash-high',
    name: 'Gemini 3.7 Flash',
    badge: 'Akurasi Tinggi',
    speed: '~2-3 detik',
    tag: 'Akurasi Ekstra',
    description: 'Model dengan penalaran lebih tinggi untuk verifikasi link dokumen yang kompleks.',
  },
  {
    id: 'ag/claude-sonnet-4-6',
    name: 'Claude Sonnet 4.6',
    badge: 'Penalaran',
    speed: '~3-4 detik',
    tag: 'Penalaran Tinggi',
    description: 'Model canggih dari Anthropic dengan pemahaman konteks mendalam dan ketat.',
  },
];

// Edit Modal state
const showEditModal = ref(false);
const editingSubmission = ref(null);
const isSaving = ref(false);

const editForm = reactive({
  no_po_sj: '',
  no_transaksi: '',
  tgl_input: '',
  tgl_share_cn: '',
  lama_pending: '',
  keterangan: '',
  cek_dokumen: '',
  status_potong_purchase: '',
  status_potong_ar: '',
  tgl_potong_tf: '',
  incentive: null,
  dpp: null,
  dpp_lain: null,
  ppn: null,
  nilai_pph: null,
  net_pay: null,
  cek_pajak_tarif_pph: null,
  selisih: null,
  note_pph: '',
  no_faktur: '',
  tgl_faktur: '',
});

const filters = reactive({
  search: '',
  region: '',
  program: '',
  status_purchase: '',
  keterangan: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

let debounceTimer = null;
let pollTimer = null;
let autoSyncTimer = null;
let isAutoSyncing = false;
let currentAbortController = null;

const isValidUrl = (url) => {
  if (!url) return false;
  return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'));
};

const formatTimestamp = (ts) => {
  if (!ts) return { date: '-', time: '' };
  try {
    const d = new Date(ts);
    if (!isNaN(d.getTime())) {
      const day = String(d.getDate()).padStart(2, '0');
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const year = d.getFullYear();
      const hours = String(d.getHours()).padStart(2, '0');
      const mins = String(d.getMinutes()).padStart(2, '0');
      return {
        date: `${day}/${month}/${year}`,
        time: `${hours}:${mins}`,
      };
    }
  } catch (e) {
    // ignore
  }
  return { date: ts, time: '' };
};

const changePerPage = () => {
  fetchSubmissions(1);
};

const onPageInputEnter = (event) => {
  const targetVal = parseInt(event.target.value, 10);
  if (!isNaN(targetVal) && targetVal >= 1 && targetVal <= pagination.last_page) {
    fetchSubmissions(targetVal);
  } else {
    event.target.value = pagination.current_page;
  }
};

const onPageInputBlur = (event) => {
  const targetVal = parseInt(event.target.value, 10);
  if (!isNaN(targetVal) && targetVal >= 1 && targetVal <= pagination.last_page && targetVal !== pagination.current_page) {
    fetchSubmissions(targetVal);
  } else {
    event.target.value = pagination.current_page;
  }
};

const fetchSubmissions = async (page = 1, silent = false) => {
  // Abort any prior pending request to avoid lag or queued stale requests
  if (currentAbortController) {
    currentAbortController.abort();
  }
  currentAbortController = new AbortController();

  if (!silent) {
    loading.value = true;
  }
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (filters.search) params.search = filters.search;
    if (filters.region) params.region = filters.region;
    if (filters.program) params.program = filters.program;
    if (filters.status_purchase) params.status_purchase = filters.status_purchase;
    if (filters.keterangan) params.keterangan = filters.keterangan;

    const res = await axios.get('/api/program-submissions', {
      params,
      signal: currentAbortController.signal,
    });
    const data = res.data;

    submissions.value = data.submissions.data || [];
    pagination.current_page = data.submissions.current_page || 1;
    pagination.last_page = data.submissions.last_page || 1;
    pagination.total = data.submissions.total || 0;

    if (data.regions) {
      regionOptions.value = Array.isArray(data.regions)
        ? data.regions
        : Object.values(data.regions).filter((v) => typeof v === 'string');
    }
    if (data.programs) {
      programOptions.value = Array.isArray(data.programs)
        ? data.programs
        : Object.values(data.programs).filter((v) => typeof v === 'string');
    }
    if (data.status_purchase_options && Array.isArray(data.status_purchase_options)) {
      statusPurchaseOptions.value = data.status_purchase_options;
    }
    if (data.keterangan_options && Array.isArray(data.keterangan_options)) {
      keteranganOptions.value = data.keterangan_options;
    }

    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const mins = String(now.getMinutes()).padStart(2, '0');
    const secs = String(now.getSeconds()).padStart(2, '0');
    lastUpdatedText.value = `${hours}:${mins}:${secs}`;
  } catch (err) {
    if (axios.isCancel(err) || err.name === 'CanceledError' || err.code === 'ERR_CANCELED') {
      return;
    }
    if (!silent) {
      syncMessage.value = 'Gagal memuat data: ' + (err.response?.data?.message || err.message);
      syncError.value = true;
    }
  } finally {
    if (!silent) {
      loading.value = false;
    }
  }
};

const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchSubmissions(1);
  }, 400);
};

const resetFilters = () => {
  filters.search = '';
  filters.region = '';
  filters.program = '';
  filters.status_purchase = '';
  filters.keterangan = '';
  programSearchQuery.value = '';
  showProgramDropdown.value = false;
  fetchSubmissions(1);
};

// Trigger manual Google Sheet sync
const triggerSync = async () => {
  if (isSyncing.value) return;
  isSyncing.value = true;
  syncMessage.value = '';
  syncError.value = false;

  try {
    const res = await axios.post('/api/program-submissions/sync', {
      limit: 200,
    });
    if (res.data?.data?.new_count > 0) {
      syncMessage.value = `${res.data.message} ${res.data.data.new_count} data baru sedang otomatis dianalisis oleh AI di latar belakang.`;
    } else {
      syncMessage.value = res.data.message || 'Sinkronisasi berhasil diselesaikan.';
    }
    syncError.value = false;
    await fetchSubmissions(1);
    setTimeout(fetchAiConfigAndStats, 1000);
  } catch (err) {
    syncMessage.value = 'Sinkronisasi gagal: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  } finally {
    isSyncing.value = false;
  }
};

const exportExcel = () => {
  if (isExporting.value) return;
  isExporting.value = true;

  const params = new URLSearchParams();
  if (filters.search) params.append('search', filters.search);
  if (filters.region) params.append('region', filters.region);
  if (filters.program) params.append('program', filters.program);
  if (filters.status_purchase) params.append('status_purchase', filters.status_purchase);
  if (filters.keterangan) params.append('keterangan', filters.keterangan);

  const qs = params.toString();
  const url = `/api/program-submissions/export${qs ? '?' + qs : ''}`;

  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', '');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  setTimeout(() => {
    isExporting.value = false;
  }, 2000);
};

const runAutoSync = async () => {
  if (isAutoSyncing || isSyncing.value || !autoRefresh.value) return;
  isAutoSyncing = true;
  try {
    const res = await axios.post('/api/program-submissions/sync', {
      limit: 100,
    });
    if (res.data?.data?.new_count > 0) {
      syncMessage.value = `Otomatis menarik ${res.data.data.new_count} data baru dari Google Spreadsheet & analisis AI otomatis dimulai.`;
      syncError.value = false;
      await fetchSubmissions(pagination.current_page, true);
      setTimeout(fetchAiConfigAndStats, 1000);
    }
  } catch (err) {
    // Silent fail in background
  } finally {
    isAutoSyncing = false;
  }
};

// Quick status change from row select
const quickUpdateStatus = async (row, newStatus) => {
  const previousStatus = row.status_potong_purchase;
  row.status_potong_purchase = newStatus;

  try {
    await axios.patch(`/api/program-submissions/${row.id}`, {
      status_potong_purchase: newStatus,
    });
    syncMessage.value = `Status potong purchase "${row.dealer_name || row.id_real}" berhasil diubah menjadi: ${newStatus || 'Belum Ditentukan'}`;
    syncError.value = false;
  } catch (err) {
    row.status_potong_purchase = previousStatus;
    syncMessage.value = 'Gagal memperbarui status potong: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  }
};

// Quick keterangan change from row select
const quickUpdateKeterangan = async (row, newKeterangan) => {
  const previousKeterangan = row.keterangan;
  row.keterangan = newKeterangan;

  try {
    await axios.patch(`/api/program-submissions/${row.id}`, {
      keterangan: newKeterangan,
    });
    syncMessage.value = `Keterangan "${row.dealer_name || row.id_real}" berhasil diubah menjadi: ${newKeterangan || 'Belum Ditentukan'}`;
    syncError.value = false;
  } catch (err) {
    row.keterangan = previousKeterangan;
    syncMessage.value = 'Gagal memperbarui keterangan: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  }
};

// Send WhatsApp notification to AR
const sendWaToAr = async (row) => {
  if (!row) return;
  sendingWaId.value = row.id;
  isSendingWaModal.value = true;

  try {
    const res = await axios.post(`/api/program-submissions/${row.id}/send-wa-ar`);
    syncMessage.value = res.data.message || `Notifikasi klaim "${row.dealer_name || row.id_real}" berhasil dikirim ke WhatsApp AR!`;
    syncError.value = false;
  } catch (err) {
    syncMessage.value = 'Gagal mengirim WhatsApp ke AR: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  } finally {
    sendingWaId.value = null;
    isSendingWaModal.value = false;
  }
};

// Auto-select Keterangan based on lama_pending or tgl_share_cn in edit modal
watch(
  () => editForm.lama_pending,
  (newVal) => {
    if (!newVal) return;
    const num = parseInt(newVal, 10);
    if (!isNaN(num)) {
      if (num > 30) {
        editForm.keterangan = 'LEBIH DARI 30 HARI';
      } else if (num >= 0) {
        editForm.keterangan = 'KURANG DARI 30 HARI';
      }
    }
  }
);

watch(
  () => editForm.tgl_share_cn,
  (newVal) => {
    if (!newVal) return;
    let dateObj = null;
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(newVal)) {
      const [d, m, y] = newVal.split('/');
      dateObj = new Date(`${y}-${m}-${d}`);
    } else {
      dateObj = new Date(newVal);
    }
    if (!isNaN(dateObj.getTime())) {
      const now = new Date();
      const diffTime = now.getTime() - dateObj.getTime();
      const diffDays = Math.max(0, Math.floor(diffTime / (1000 * 60 * 60 * 24)));
      if (!editForm.lama_pending) {
        editForm.lama_pending = `${diffDays} Hari`;
      }
      editForm.keterangan = diffDays > 30 ? 'LEBIH DARI 30 HARI' : 'KURANG DARI 30 HARI';
    }
  }
);

// AI: Analyze Single Row directly
const analyzeRowWithAi = async (row) => {
  analyzingRowId.value = row.id;
  try {
    const res = await axios.post(`/api/program-submissions/${row.id}/analyze-ai`);
    const data = res.data.data;

    // Update row locally with all fresh data (including 11 financial columns)
    if (data.submission) {
      Object.assign(row, data.submission);
    } else {
      row.cek_dokumen = data.cek_dokumen;
      row.status_potong_purchase = data.status_potong_purchase;
      row.keterangan = data.keterangan;
      if (data.financial) {
        Object.assign(row, data.financial);
      }
    }

    syncMessage.value = `Analisis AI selesai untuk "${row.dealer_name || row.id_real}": Status ${row.cek_dokumen || '-'} → ${row.status_potong_purchase || '-'} | Note PPh: ${row.note_pph || '-'}`;
    syncError.value = false;
  } catch (err) {
    syncMessage.value = 'Gagal menganalisis dengan AI: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  } finally {
    analyzingRowId.value = null;
  }
};

// AI: Analyze Current Row inside Edit Modal
const analyzeCurrentRowWithAi = async () => {
  if (!editingSubmission.value) return;
  isAnalyzingCurrent.value = true;

  try {
    const res = await axios.post(`/api/program-submissions/${editingSubmission.value.id}/analyze-ai`);
    const data = res.data.data;

    if (data.submission) {
      Object.assign(editingSubmission.value, data.submission);
      Object.assign(editForm, {
        no_po_sj: data.submission.no_po_sj || '',
        no_transaksi: data.submission.no_transaksi || '',
        tgl_input: data.submission.tgl_input || '',
        tgl_share_cn: data.submission.tgl_share_cn || '',
        lama_pending: data.submission.lama_pending || '',
        keterangan: data.submission.keterangan || '',
        cek_dokumen: data.submission.cek_dokumen || '',
        status_potong_purchase: data.submission.status_potong_purchase || '',
        status_potong_ar: data.submission.status_potong_ar || '',
        tgl_potong_tf: data.submission.tgl_potong_tf || '',
        incentive: data.submission.incentive ?? null,
        dpp: data.submission.dpp ?? null,
        dpp_lain: data.submission.dpp_lain ?? null,
        ppn: data.submission.ppn ?? null,
        nilai_pph: data.submission.nilai_pph ?? null,
        net_pay: data.submission.net_pay ?? null,
        cek_pajak_tarif_pph: data.submission.cek_pajak_tarif_pph ?? null,
        selisih: data.submission.selisih ?? null,
        note_pph: data.submission.note_pph || '',
        no_faktur: data.submission.no_faktur || '',
        tgl_faktur: data.submission.tgl_faktur || '',
      });
    }

    syncMessage.value = `Analisis AI selesai untuk "${editingSubmission.value.dealer_name || editingSubmission.value.id_real}".`;
    syncError.value = false;
  } catch (err) {
    alert('Gagal menganalisis dokumen: ' + (err.response?.data?.message || err.message));
  } finally {
    isAnalyzingCurrent.value = false;
  }
};

// AI: Batch modal & execution
const openBatchAiModal = () => {
  batchResultSummary.value = '';
  showBatchAiModal.value = true;
};

const runBackgroundFromModal = async () => {
  showBatchAiModal.value = false;
  await triggerBackgroundAi(true);
};

const runBatchAiAnalysis = async () => {
  if (isAnalyzingBatch.value) return;
  isAnalyzingBatch.value = true;
  batchResultSummary.value = 'Memulai analisis AI bertahap...';

  let totalProcessed = 0;
  let totalSuccess = 0;
  let totalError = 0;

  try {
    while (isAnalyzingBatch.value) {
      const res = await axios.post('/api/program-submissions/analyze-ai-batch', {
        batch_size: 20,
        year: '2026',
      });

      const resData = res.data;
      const batchData = resData.data;

      if (!batchData || batchData.total === 0) {
        batchResultSummary.value = totalProcessed > 0
          ? `🎉 Seluruh data tahun 2026 telah selesai dianalisis! (Total: ${totalProcessed}, Berhasil: ${totalSuccess}, Gagal: ${totalError})`
          : 'Semua data tahun 2026 sudah selesai dianalisis sebelumnya.';
        break;
      }

      totalProcessed += batchData.total;
      totalSuccess += batchData.success_count;
      totalError += batchData.error_count;

      const sisa = resData.remaining ?? 0;
      batchResultSummary.value = `Sedang memproses... Selesai: ${totalProcessed} data (Sisa: ${sisa} data).`;

      // Live update table on current page so user sees changes in real time
      fetchSubmissions(pagination.current_page, true);

      if (sisa <= 0) {
        batchResultSummary.value = `🎉 Analisis selesai! Seluruh ${totalProcessed} data berhasil dianalisis (Berhasil: ${totalSuccess}, Gagal: ${totalError}).`;
        break;
      }
    }

    syncMessage.value = 'Analisis batch AI berhasil diselesaikan.';
    syncError.value = false;
    setTimeout(fetchAiConfigAndStats, 1000);
  } catch (err) {
    batchResultSummary.value = 'Gagal menjalankan analisis batch: ' + (err.response?.data?.message || err.message);
  } finally {
    isAnalyzingBatch.value = false;
  }
};

// Model AI Modal logic
const openModelAiModal = async () => {
  showModelAiModal.value = true;
  showModelDropdown.value = false;
  modelSearchQuery.value = '';
  await fetchAiConfigAndStats();
};

const fetchAiConfigAndStats = async () => {
  try {
    const [configRes, statsRes] = await Promise.all([
      axios.get('/api/program-submissions/ai-config'),
      axios.get('/api/program-submissions/ai-status'),
    ]);

    if (configRes.data?.config?.model) {
      selectedModel.value = configRes.data.config.model;
    }
    if (configRes.data?.models && Array.isArray(configRes.data.models)) {
      aiAvailableModels.value = configRes.data.models;
    }
    if (statsRes.data) {
      Object.assign(aiStats, statsRes.data);
    }
  } catch (err) {
    // ignore
  }
};

const saveSelectedModel = async () => {
  isSavingModel.value = true;
  try {
    const res = await axios.post('/api/program-submissions/ai-config', {
      model: selectedModel.value,
    });
    syncMessage.value = res.data.message || `Model AI berhasil diubah ke: ${selectedModel.value}`;
    syncError.value = false;
    showModelAiModal.value = false;
  } catch (err) {
    alert('Gagal menyimpan model AI: ' + (err.response?.data?.message || err.message));
  } finally {
    isSavingModel.value = false;
  }
};

const triggerBackgroundAi = async (all = false) => {
  if (isTriggeringBgAi.value) return;
  isTriggeringBgAi.value = true;

  try {
    const res = await axios.post('/api/program-submissions/ai-run-background', {
      year: '2026',
      all: all,
      limit: all ? 0 : 50,
    });
    syncMessage.value = `${res.data.message} Halaman tetap cepat dan data akan otomatis terupdate.`;
    syncError.value = false;

    // Refresh stats after launching
    setTimeout(fetchAiConfigAndStats, 2000);
  } catch (err) {
    syncMessage.value = 'Gagal menjalankan AI di latar belakang: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  } finally {
    isTriggeringBgAi.value = false;
  }
};

const formatRupiah = (val) => {
  if (val === null || val === undefined || val === '') return '-';
  const num = Number(val);
  if (isNaN(num)) return val;
  return new Intl.NumberFormat('id-ID').format(num);
};

const formatDocTypeName = (type) => {
  if (!type) return '';
  const lower = String(type).toLowerCase();
  if (lower === 'cn') return 'CN';
  if (lower === 'agr' || lower === 'agreement') return 'Agr';
  if (lower === 'faktur' || lower === 'tax_invoice') return 'Faktur';
  return 'Lain';
};

const hasSwappedOrInvalidDocs = (submission) => {
  if (!submission || !submission.doc_validation) return false;
  return Object.values(submission.doc_validation).some(
    (info) => info && (info.status === 'swapped' || info.status === 'invalid')
  );
};

// Edit Modal logic
const openEditModal = (row) => {
  editingSubmission.value = row;
  editForm.no_po_sj = row.no_po_sj || '';
  editForm.no_transaksi = row.no_transaksi || '';
  editForm.tgl_input = row.tgl_input || '';
  editForm.tgl_share_cn = row.tgl_share_cn || '';
  editForm.lama_pending = row.lama_pending || '';
  editForm.keterangan = row.keterangan || '';
  editForm.cek_dokumen = row.cek_dokumen || '';
  editForm.status_potong_purchase = row.status_potong_purchase || '';
  editForm.status_potong_ar = row.status_potong_ar || '';
  editForm.tgl_potong_tf = row.tgl_potong_tf || '';
  editForm.incentive = row.incentive ?? null;
  editForm.dpp = row.dpp ?? null;
  editForm.dpp_lain = row.dpp_lain ?? null;
  editForm.ppn = row.ppn ?? null;
  editForm.nilai_pph = row.nilai_pph ?? null;
  editForm.net_pay = row.net_pay ?? null;
  editForm.cek_pajak_tarif_pph = row.cek_pajak_tarif_pph ?? null;
  editForm.selisih = row.selisih ?? null;
  editForm.note_pph = row.note_pph || '';
  editForm.no_faktur = row.no_faktur || '';
  editForm.tgl_faktur = row.tgl_faktur || '';
  editForm.doc_validation = row.doc_validation || null;
  showEditModal.value = true;
};

const saveEditModal = async () => {
  if (!editingSubmission.value) return;
  isSaving.value = true;

  try {
    const res = await axios.patch(`/api/program-submissions/${editingSubmission.value.id}`, { ...editForm });

    // Update row locally
    Object.assign(editingSubmission.value, res.data.submission || editForm);

    syncMessage.value = `Data tracking "${editingSubmission.value.dealer_name || editingSubmission.value.id_real}" berhasil disimpan.`;
    syncError.value = false;
    showEditModal.value = false;
  } catch (err) {
    alert('Gagal menyimpan data tracking: ' + (err.response?.data?.message || err.message));
  } finally {
    isSaving.value = false;
  }
};

onMounted(() => {
  fetchSubmissions(1);
  fetchAiConfigAndStats();
  document.addEventListener('click', handleClickOutsideSpreadsheetDropdown);
  document.addEventListener('click', handleClickOutsideAiDropdown);
  document.addEventListener('click', handleClickOutsideModelDropdown);
  document.addEventListener('click', handleClickOutsideProgramDropdown);

  // Otomatis sinkronkan dari spreadsheet saat halaman dibuka
  runAutoSync();

  // Sinkronkan data baru dari spreadsheet di latar belakang setiap 30 detik
  autoSyncTimer = setInterval(() => {
    if (autoRefresh.value) {
      runAutoSync();
    }
  }, 30000);

  // Refresh tampilan data setiap 30 detik jika autoRefresh aktif
  pollTimer = setInterval(() => {
    if (autoRefresh.value && !loading.value) {
      fetchSubmissions(pagination.current_page, true);
    }
  }, 30000);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutsideSpreadsheetDropdown);
  document.removeEventListener('click', handleClickOutsideAiDropdown);
  document.removeEventListener('click', handleClickOutsideModelDropdown);
  document.removeEventListener('click', handleClickOutsideProgramDropdown);
  if (pollTimer) clearInterval(pollTimer);
  if (autoSyncTimer) clearInterval(autoSyncTimer);
  clearTimeout(debounceTimer);
  if (currentAbortController) {
    currentAbortController.abort();
  }
});
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
