<template>
  <div class="space-y-4 font-sans">
    <!-- Header Page (Identical to Form Program) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold tracking-tight text-gray-900">Data Program</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Sinkronisasi master data program (56 kolom) langsung dari Google Spreadsheet.
        </p>
      </div>

      <!-- Action Buttons (Spreadsheet Dropdown & Direct Sync) -->
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
            class="absolute right-0 top-full mt-1.5 w-48 bg-white rounded-lg shadow-lg border border-gray-200 p-1 z-30 font-sans"
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
              @click="handleExportCsv"
              :disabled="isExporting"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <DownloadIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>{{ isExporting ? 'Mengekspor...' : 'Export CSV' }}</span>
            </button>
          </div>
        </div>

        <button
          type="button"
          @click="triggerSync"
          :disabled="isSyncing"
          class="h-8 px-2.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white text-xs font-normal text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs cursor-pointer disabled:opacity-50"
        >
          <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', isSyncing && 'animate-spin text-emerald-600']" />
          <span>Sinkronkan Sekarang</span>
        </button>

        <!-- Tombol Cocokkan / Rekonsiliasi dengan Form Program -->
        <button
          type="button"
          @click="openReconcileModal"
          :disabled="isReconciling"
          class="h-8 px-2.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white text-xs font-normal text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition shadow-2xs cursor-pointer disabled:opacity-50"
          title="Cocokkan finansial (DPP/Net Pay) & pindahkan link Drive dari Form Program"
        >
          <RefreshCwIcon v-if="isReconciling" class="w-3.5 h-3.5 animate-spin text-gray-600" />
          <CheckCircleIcon v-else class="w-3.5 h-3.5 text-gray-600" />
          <span>{{ isReconciling ? 'Mencocokkan...' : 'Cocokkan Form Program' }}</span>
        </button>
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

    <!-- Toolbar Filters (Identical to Form Program) -->
    <div class="flex flex-wrap items-center justify-between gap-2.5 py-1">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
          <SearchIcon class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" />
          <input
            v-model="filters.search"
            @input="debounceFetch"
            type="text"
            placeholder="Cari dealer, program, kode BT, no PO/SJ..."
            class="h-9 w-64 sm:w-80 pl-9 pr-3 rounded-md border border-gray-200 bg-white text-xs text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black"
          />
        </div>

        <!-- Filter Region -->
        <select
          v-model="filters.region"
          @change="fetchData(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Region</option>
          <option v-for="reg in filterOptions.regions" :key="reg" :value="reg">
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
          @change="fetchData(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Status Purchase</option>
          <option v-for="opt in filterOptions.status_purchase" :key="opt" :value="opt">
            {{ opt }}
          </option>
        </select>

        <!-- Filter Status AR -->
        <select
          v-model="filters.status_ar"
          @change="fetchData(1)"
          class="h-9 rounded-md border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option value="">Semua Status AR</option>
          <option v-for="opt in filterOptions.status_ar" :key="opt" :value="opt">
            {{ opt }}
          </option>
        </select>

        <button
          v-if="hasActiveFilters"
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

    <!-- Official Shadcn Table Card (Horizontal Scrollable, Exactly like Form Program) -->
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow class="border-b border-gray-200 text-xs hover:bg-transparent">
            <!-- 1. No (Frozen) -->
            <TableHead class="w-[50px] min-w-[50px] max-w-[50px] text-center font-medium text-gray-500 sticky z-20 bg-white border-b border-gray-200" style="left: 0px;">No</TableHead>
            <!-- 2. Nama Dealer (Frozen) -->
            <TableHead class="w-[180px] min-w-[180px] max-w-[180px] font-medium text-gray-500 sticky z-20 bg-white border-b border-gray-200" style="left: 50px;">Nama Dealer</TableHead>
            <!-- 3. Program (Frozen) -->
            <TableHead class="w-[110px] min-w-[110px] max-w-[110px] whitespace-nowrap font-medium text-gray-500 sticky z-20 bg-white border-b border-gray-200" style="left: 230px;">Program</TableHead>
            <!-- 4. Kode BT (Frozen) -->
            <TableHead class="w-[90px] min-w-[90px] max-w-[90px] whitespace-nowrap font-medium text-gray-500 sticky z-20 bg-white border-b border-gray-200" style="left: 340px;">Kode BT</TableHead>
            <!-- 5. Nama Program (Frozen) -->
            <TableHead class="w-[220px] min-w-[220px] max-w-[220px] font-medium text-gray-500 sticky z-20 bg-white border-b border-gray-200" style="left: 430px;">Nama Program</TableHead>
            <!-- 6. Aksi (Frozen with divider shadow) -->
            <TableHead class="w-[90px] min-w-[90px] max-w-[90px] text-center font-medium text-gray-500 sticky z-20 bg-white border-b border-r border-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.08)]" style="left: 650px;">Aksi</TableHead>
            <!-- 7. Periode -->
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Periode</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Region</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">No PO</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">ID GS</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Kode Supplier</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Status DL</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Sales Person</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Telemarketing</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Wajib Pajak</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">TRF PPh</TableHead>
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
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Ket Faktur</TableHead>
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
            <TableHead class="whitespace-nowrap font-medium text-gray-500">No. UID</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">No. Pembayaran</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl Bank PPh</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">T/F</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl Proses</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Tgl SJ</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">No. SJ</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Info Bank</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Pending Potongan</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">NPWP</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Nama NPWP</TableHead>
            <TableHead class="min-w-[150px] font-medium text-gray-500">Program 2</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500 text-center">CN</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500 text-center">Agrement</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500 text-center">Cek FP</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500 text-center">Cek Evidance</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Noted</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Norek</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Namrek</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Bank</TableHead>
            <TableHead class="whitespace-nowrap font-medium text-gray-500">Big Region</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <!-- Loading State -->
          <TableEmpty v-if="isLoading && items.length === 0" :colspan="58">
            <div class="inline-flex items-center gap-2 text-gray-500 py-8">
              <RefreshCwIcon class="w-4 h-4 animate-spin text-gray-400" />
              <span>Memuat data program...</span>
            </div>
          </TableEmpty>

          <!-- Empty State -->
          <TableEmpty v-else-if="items.length === 0" :colspan="58">
            <div class="max-w-md mx-auto py-8 space-y-1.5 text-center text-gray-500">
              <p class="font-medium text-gray-800">Belum ada data program yang tersimpan.</p>
              <p class="text-xs text-gray-500">
                Klik tombol <strong>"Sinkronkan Sekarang"</strong> di atas untuk memuat data dari spreadsheet Anda.
              </p>
            </div>
          </TableEmpty>

          <!-- Data Rows (All typography exactly matches Form Program: text-xs, text-gray-700, normal font weight, Inter sans) -->
          <TableRow
            v-else
            v-for="(row, idx) in items"
            :key="row.id"
            class="group hover:bg-gray-50/80 transition text-xs"
          >
            <!-- 1. No (Frozen) -->
            <TableCell class="w-[50px] min-w-[50px] max-w-[50px] text-center text-xs text-gray-500 py-2.5 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 0px;">
              {{ (pagination.current_page - 1) * pagination.per_page + idx + 1 }}
            </TableCell>

            <!-- 2. Nama Dealer (Frozen) -->
            <TableCell class="w-[180px] min-w-[180px] max-w-[180px] text-xs text-gray-700 py-2.5 leading-snug sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 50px;">
              <div class="line-clamp-2 text-gray-800 break-words" :title="row.dealer_name">{{ row.dealer_name || '-' }}</div>
            </TableCell>

            <!-- 3. Program (Frozen) -->
            <TableCell class="w-[110px] min-w-[110px] max-w-[110px] whitespace-nowrap text-xs text-gray-700 py-2.5 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 230px;">
              <div class="truncate" :title="row.program">{{ row.program || '-' }}</div>
            </TableCell>

            <!-- 4. Kode BT (Frozen) -->
            <TableCell class="w-[90px] min-w-[90px] max-w-[90px] whitespace-nowrap text-xs text-gray-700 py-2.5 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 340px;">
              <div class="truncate" :title="row.kode_bt">{{ row.kode_bt || '-' }}</div>
            </TableCell>

            <!-- 5. Nama Program (Frozen) -->
            <TableCell class="w-[220px] min-w-[220px] max-w-[220px] text-xs text-gray-700 py-2.5 leading-snug sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 430px;">
              <div class="line-clamp-2 break-words" :title="row.program_name">{{ row.program_name || '-' }}</div>
            </TableCell>

            <!-- 6. Aksi / Cek Data (Frozen with divider shadow) -->
            <TableCell class="w-[90px] min-w-[90px] max-w-[90px] text-center text-xs py-2 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-r border-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.08)]" style="left: 650px;">
              <div class="flex items-center justify-center gap-1.5">
                <button
                  type="button"
                  @click.stop="testMatchSingleRow(row)"
                  :disabled="reconcilingRowId === row.id"
                  class="w-7 h-7 shrink-0 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white hover:bg-gray-100 hover:border-gray-300 text-gray-700 transition shadow-2xs cursor-pointer disabled:opacity-50"
                  title="Cek kecocokan data dengan Form Program"
                >
                  <RefreshCwIcon v-if="reconcilingRowId === row.id" class="w-3.5 h-3.5 animate-spin text-gray-500" />
                  <CheckCircleIcon v-else class="w-3.5 h-3.5 text-gray-700 hover:text-black" />
                </button>

                <button
                  v-if="row.status_potong_purchase === 'BISA DI POTONG'"
                  type="button"
                  @click.stop="sendWaToTelemarketing(row)"
                  :disabled="sendingWaId === row.id"
                  class="w-7 h-7 shrink-0 inline-flex items-center justify-center rounded-md border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition shadow-2xs cursor-pointer disabled:opacity-50"
                  title="Kirim ke WhatsApp Telemarketing (Tawarkan Potong Order ke Dealer)"
                >
                  <RefreshCwIcon v-if="sendingWaId === row.id" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
                  <SendIcon v-else class="w-3.5 h-3.5 text-emerald-600" />
                </button>
              </div>
            </TableCell>

              <!-- 6. Periode -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.periode || '-' }}
              </TableCell>

              <!-- 7. Region -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.region || '-' }}
              </TableCell>

              <!-- 8. No PO -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_po || '-' }}
              </TableCell>

              <!-- 9. ID GS -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.id_gs || '-' }}
              </TableCell>

              <!-- 10. Kode Supplier -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.kode_supplier || '-' }}
              </TableCell>

              <!-- 11. Status DL -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.status_dl || '-' }}
              </TableCell>

              <!-- 12. Sales Person -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.sales_person || '-' }}
              </TableCell>

              <!-- 13. Telemarketing -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.telemarketing || '-' }}
              </TableCell>

              <!-- 14. Wajib Pajak -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.wajib_pajak || '-' }}
              </TableCell>

              <!-- 15. TRF PPH -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.trf_pph || '-' }}
              </TableCell>

              <!-- 16. Incentive -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.incentive) }}
              </TableCell>

              <!-- 17. DPP -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.dpp) }}
              </TableCell>

              <!-- 18. DPP Lain -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.dpp_lain) }}
              </TableCell>

              <!-- 19. PPN -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.ppn) }}
              </TableCell>

              <!-- 20. Nilai PPh -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.nilai_pph) }}
              </TableCell>

              <!-- 21. Net Pay -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.net_pay) }}
              </TableCell>

              <!-- 22. Cek Pajak Tarif PPh -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                {{ formatRupiah(row.cek_pajak_tarif) }}
              </TableCell>

              <!-- 23. Selisih -->
              <TableCell class="whitespace-nowrap text-right text-xs text-gray-700 py-2.5">
                <span v-if="row.selisih === 0 || row.selisih === '0' || row.selisih === 0.0" class="text-gray-400">
                  0
                </span>
                <span v-else-if="row.selisih !== null && row.selisih !== undefined && row.selisih !== ''">
                  {{ formatRupiah(row.selisih) }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- 24. Note PPh -->
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

              <!-- 25. No Faktur Pajak -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_faktur_pajak || '-' }}
              </TableCell>

              <!-- 26. Ket Faktur Pajak -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.ket_faktur_pajak || '-' }}
              </TableCell>

              <!-- 27. No PO/SJ -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_po_sj || '-' }}
              </TableCell>

              <!-- 28. No Transaksi -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_transaksi || '-' }}
              </TableCell>

              <!-- 29. Tgl Input -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_input || '-' }}
              </TableCell>

              <!-- 30. Tgl Share CN -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_share_cn || '-' }}
              </TableCell>

              <!-- 31. Lama Pending -->
              <TableCell class="text-center text-xs text-gray-700 py-2.5 whitespace-nowrap">
                <span v-if="row.lama_pending" class="px-1.5 py-0.5 rounded bg-white border border-gray-200 text-xs text-gray-700">
                  {{ row.lama_pending }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- 32. Keterangan -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.keterangan || '-' }}
              </TableCell>

              <!-- 33. Cek Dokumen -->
              <TableCell class="text-xs text-gray-700 py-2.5 leading-snug whitespace-nowrap">
                <div
                  v-if="row.cek_dokumen"
                  :class="[
                    'line-clamp-2 max-w-[160px] text-xs px-1.5 py-0.5 rounded inline-block',
                    row.cek_dokumen === 'LENGKAP' || row.cek_dokumen === 'OK'
                      ? 'bg-teal-50 text-teal-700 border border-teal-200'
                      : 'bg-amber-50 text-amber-800 border border-amber-200'
                  ]"
                  :title="row.cek_dokumen"
                >
                  {{ row.cek_dokumen }}
                </div>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- 34. Status Potong By Purchase -->
              <TableCell class="py-2.5 whitespace-nowrap">
                <span
                  v-if="row.status_potong_purchase"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-normal border inline-block',
                    row.status_potong_purchase === 'BISA DI POTONG'
                      ? 'bg-teal-50 text-teal-700 border-teal-300'
                      : row.status_potong_purchase === 'SUDAH POTONG'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                      : row.status_potong_purchase === 'DONE TRANSFER'
                      ? 'bg-blue-50 text-blue-700 border-blue-300'
                      : row.status_potong_purchase === 'BELUM BISA POTONG'
                      ? 'bg-rose-50 text-rose-700 border-rose-300'
                      : 'bg-gray-50 text-gray-600 border-gray-200'
                  ]"
                >
                  {{ row.status_potong_purchase }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- 35. Status Potong By AR -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                <span
                  v-if="row.status_potong_ar"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-medium border inline-block',
                    row.status_potong_ar.includes('DEALER SETUJU')
                      ? 'bg-blue-50 text-blue-700 border-blue-200'
                      : row.status_potong_ar === 'DONE'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : row.status_potong_ar.includes('PENDING')
                      ? 'bg-amber-50 text-amber-700 border-amber-200'
                      : 'bg-white border-gray-200 text-gray-700'
                  ]"
                >
                  {{ row.status_potong_ar }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </TableCell>

              <!-- 36. Tanggal Potong/TF -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_potong_tf || '-' }}
              </TableCell>

              <!-- 37. No. UID -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_uid || '-' }}
              </TableCell>

              <!-- 38. No. Pembayaran -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_pembayaran || '-' }}
              </TableCell>

              <!-- 39. Tgl Input Bank PPh -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_input_bank_pph || '-' }}
              </TableCell>

              <!-- 40. T/F -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tf_status || '-' }}
              </TableCell>

              <!-- 41. Tgl Proses -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_proses || '-' }}
              </TableCell>

              <!-- 42. Tgl SJ -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.tgl_sj || '-' }}
              </TableCell>

              <!-- 43. No. SJ -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.no_sj || '-' }}
              </TableCell>

              <!-- 44. Info Bank -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.info_bank || '-' }}
              </TableCell>

              <!-- 45. Pending Potongan -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.pending_potongan || '-' }}
              </TableCell>

              <!-- 46. NPWP -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.npwp || '-' }}
              </TableCell>

              <!-- 47. Nama NPWP -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.nama_npwp || '-' }}
              </TableCell>

              <!-- 48. Program 2 -->
              <TableCell class="text-xs text-gray-700 py-2.5 leading-snug">
                <div class="line-clamp-2 min-w-[150px]" :title="row.program_2">{{ row.program_2 || '-' }}</div>
              </TableCell>

              <!-- 49. CN -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.cn)">
                  <a
                    :href="row.cn"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer shadow-2xs"
                    title="Buka Dokumen Credit Note di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>CN</span>
                  </a>
                </template>
                <span v-else-if="row.cn" class="text-xs text-gray-700">{{ row.cn }}</span>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 50. Agrement -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.agrement)">
                  <a
                    :href="row.agrement"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer shadow-2xs"
                    title="Buka Dokumen Agreement di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>Agr</span>
                  </a>
                </template>
                <span v-else-if="row.agrement" class="text-xs text-gray-700">{{ row.agrement }}</span>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 51. Cek FP -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.cek_fp)">
                  <a
                    :href="row.cek_fp"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer shadow-2xs"
                    title="Buka Dokumen Faktur Pajak di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>FP</span>
                  </a>
                </template>
                <span v-else-if="row.cek_fp" class="text-xs text-gray-700">{{ row.cek_fp }}</span>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 52. Cek Evidance -->
              <TableCell class="text-center py-2.5 whitespace-nowrap">
                <template v-if="isValidUrl(row.cek_evidance)">
                  <a
                    :href="row.cek_evidance"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs transition cursor-pointer shadow-2xs"
                    title="Buka Dokumen Evidance di Google Drive"
                  >
                    <FileTextIcon class="w-3 h-3 text-gray-500 shrink-0" />
                    <span>Evidance</span>
                  </a>
                </template>
                <span v-else-if="row.cek_evidance" class="text-xs text-gray-700">{{ row.cek_evidance }}</span>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 53. Noted -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.noted || '-' }}
              </TableCell>

              <!-- 54. Norek -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.norek || '-' }}
              </TableCell>

              <!-- 55. Namrek -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.namrek || '-' }}
              </TableCell>

              <!-- 56. Bank -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.bank || '-' }}
              </TableCell>

              <!-- 57. Big Region -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                {{ row.big_region || '-' }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>

      <!-- Pagination Footer (Identical to Form Program) -->
      <div
        v-if="pagination.total > 0"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-200 bg-white text-xs text-gray-500 font-sans"
      >
        <div class="flex items-center gap-3">
          <div>
            Menampilkan <span class="font-semibold text-gray-900">{{ items.length }}</span> dari
            <span class="font-semibold text-gray-900">{{ Number(pagination.total).toLocaleString('id-ID') }}</span> total baris
          </div>
          <div class="flex items-center gap-1.5 ml-2 pl-3 border-l border-gray-200">
            <span>Per halaman:</span>
            <select
              v-model="pagination.per_page"
              @change="fetchData(1)"
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
            :disabled="pagination.current_page <= 1 || isLoading"
            @click="fetchData(1)"
            class="px-2 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
            title="Halaman Pertama"
          >
            &laquo; Pertama
          </button>
          <button
            type="button"
            :disabled="pagination.current_page <= 1 || isLoading"
            @click="fetchData(pagination.current_page - 1)"
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
            :disabled="pagination.current_page >= pagination.last_page || isLoading"
            @click="fetchData(pagination.current_page + 1)"
            class="px-2.5 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
          >
            Selanjutnya
          </button>
          <button
            type="button"
            :disabled="pagination.current_page >= pagination.last_page || isLoading"
            @click="fetchData(pagination.last_page)"
            class="px-2 py-1.5 rounded border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
            title="Halaman Terakhir"
          >
            Terakhir &raquo;
          </button>

          <RefreshCwIcon v-if="isLoading" class="w-3.5 h-3.5 text-gray-400 animate-spin ml-1.5" />
        </div>
      </div>
    </div>

    <!-- Modal Rekonsiliasi & Pencocokan Form Program -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showReconcileModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs font-sans"
          @click.self="showReconcileModal = false"
        >
          <div class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-700">
                  <CheckCircleIcon class="w-4 h-4" />
                </div>
                <div>
                  <h3 class="text-sm font-bold text-gray-900">Cocokkan dengan Form Program</h3>
                  <p class="text-[11px] text-gray-500">
                    Otomatisasi pencocokan identitas, finansial, dan pemindahan link Drive
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="showReconcileModal = false"
                class="w-7 h-7 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              >
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Content -->
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
              <!-- Penjelasan Konsep Alur -->
              <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl space-y-1.5 text-gray-600">
                <div class="font-semibold text-gray-800 flex items-center gap-1.5">
                  <InfoIcon class="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                  <span>Aturan & Cara Kerja:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-[11px] text-gray-600 pl-1 leading-relaxed">
                  <li><strong>Identitas & Finansial:</strong> Sistem mencari Dealer & Program yang cocok, lalu memvalidasi nominal DPP & Net Pay (toleransi selisih &le; Rp 10).</li>
                  <li><strong>Pemindahan Dokumen:</strong> Link Google Drive (CN, Agreement, Faktur Pajak) dari Form Program otomatis disalin ke kolom <code>cn</code>, <code>agrement</code>, <code>cek_fp</code>.</li>
                  <li><strong>Penentuan Status:</strong> Jika data finansial cocok dan ketiga dokumen lengkap, status diset <strong>BISA DI POTONG</strong>. Jika ada selisih nominal atau dokumen belum lengkap, status diset <strong>BELUM BISA POTONG</strong> dengan rincian selisih di kolom keterangan.</li>
                </ul>
              </div>

              <!-- Form Options -->
              <div class="space-y-3 pt-1">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[11px] font-medium text-gray-700 mb-1">Tahun Periode</label>
                    <select
                      v-model="reconcileForm.year"
                      class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                    >
                      <option value="2026">2026</option>
                      <option value="2025">2025</option>
                      <option value="">Semua Tahun</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[11px] font-medium text-gray-700 mb-1">Maksimal Baris</label>
                    <select
                      v-model="reconcileForm.limit"
                      class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                    >
                      <option :value="50">50 baris</option>
                      <option :value="100">100 baris</option>
                      <option :value="200">200 baris</option>
                      <option :value="500">500 baris</option>
                      <option :value="0">Semua yang memenuhi syarat</option>
                    </select>
                  </div>
                </div>

                <div class="space-y-2 pt-1">
                  <label class="flex items-start gap-2 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      v-model="reconcileForm.only_unreconciled"
                      class="mt-0.5 rounded border-gray-300 text-black focus:ring-black cursor-pointer"
                    />
                    <span class="text-[11px] text-gray-700">
                      Hanya proses baris yang belum memiliki link CN atau belum berstatus
                    </span>
                  </label>
                  <label class="flex items-start gap-2 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      v-model="reconcileForm.force"
                      class="mt-0.5 rounded border-gray-300 text-black focus:ring-black cursor-pointer"
                    />
                    <span class="text-[11px] text-gray-700">
                      Perbarui ulang dokumen meskipun kolom CN/Agrement/FP sudah terisi sebelumnya
                    </span>
                  </label>
                  <label class="flex items-start gap-2 cursor-pointer select-none bg-indigo-50/50 p-2 rounded-lg border border-indigo-100">
                    <input
                      type="checkbox"
                      v-model="reconcileForm.push_to_sheet"
                      class="mt-0.5 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-600 cursor-pointer"
                    />
                    <div>
                      <span class="text-[11px] font-medium text-indigo-900 block">
                        Sinkronisasi Balik ke Google Spreadsheet (Two-Way Sync)
                      </span>
                      <span class="text-[10px] text-indigo-700 block">
                        Kirim otomatis perubahan status, keterangan, dan link Drive ke Google Spreadsheet master.
                      </span>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Hasil Eksekusi Terakhir -->
              <div v-if="reconcileResult" class="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between text-emerald-900 font-semibold text-xs">
                  <div class="flex items-center gap-1.5">
                    <CheckCircleIcon class="w-4 h-4 text-emerald-600" />
                    <span>Hasil Pencocokan:</span>
                  </div>
                  <span class="text-[11px] font-normal text-emerald-700">
                    {{ reconcileResult.matched_count }} dari {{ reconcileResult.total_evaluated }} cocok
                  </span>
                </div>

                <!-- Metric Cards -->
                <div class="grid grid-cols-3 gap-2 pt-1 text-center">
                  <div class="bg-white p-2 rounded-lg border border-emerald-100">
                    <div class="text-sm font-bold text-teal-700">{{ reconcileResult.bisa_potong_count }}</div>
                    <div class="text-[10px] text-gray-500">Bisa Dipotong</div>
                  </div>
                  <div class="bg-white p-2 rounded-lg border border-emerald-100">
                    <div class="text-sm font-bold text-amber-700">{{ reconcileResult.belum_bisa_potong_count }}</div>
                    <div class="text-[10px] text-gray-500">Belum Bisa Dipotong</div>
                  </div>
                  <div class="bg-white p-2 rounded-lg border border-emerald-100">
                    <div class="text-sm font-bold text-indigo-700">{{ reconcileResult.drive_transferred_count }}</div>
                    <div class="text-[10px] text-gray-500">Drive Dipindahkan</div>
                  </div>
                </div>

                <!-- Preview sample of matches -->
                <div v-if="reconcileResult.results && reconcileResult.results.length > 0" class="pt-1.5 space-y-1 max-h-36 overflow-y-auto">
                  <div
                    v-for="(item, idx) in reconcileResult.results.slice(0, 5)"
                    :key="idx"
                    class="p-2 bg-white rounded border border-emerald-100 text-[11px] space-y-0.5"
                  >
                    <div class="flex items-center justify-between font-medium text-gray-800">
                      <span class="truncate max-w-[240px]">{{ item.dealer }}</span>
                      <span
                        :class="[
                          'px-1.5 py-0.5 rounded text-[10px]',
                          item.status === 'BISA DI POTONG' ? 'bg-teal-50 text-teal-700 border border-teal-200' : 'bg-amber-50 text-amber-700 border border-amber-200'
                        ]"
                      >
                        {{ item.status }}
                      </span>
                    </div>
                    <div class="text-[10px] text-gray-500 truncate" :title="item.keterangan">{{ item.keterangan }}</div>
                  </div>
                  <div v-if="reconcileResult.results.length > 5" class="text-center text-[10px] text-emerald-700 pt-1">
                    +{{ reconcileResult.results.length - 5 }} data lainnya berhasil diperbarui
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2 text-xs">
              <button
                type="button"
                @click="showReconcileModal = false"
                class="h-8 px-3 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-100 transition cursor-pointer"
              >
                {{ reconcileResult ? 'Selesai' : 'Batal' }}
              </button>
              <button
                type="button"
                @click="handleReconcile"
                :disabled="isReconciling"
                class="h-8 px-3.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition cursor-pointer disabled:opacity-50 flex items-center gap-1.5 shadow-xs"
              >
                <RefreshCwIcon v-if="isReconciling" class="w-3.5 h-3.5 animate-spin" />
                <ZapIcon v-else class="w-3.5 h-3.5" />
                <span>{{ isReconciling ? 'Sedang Memproses...' : 'Mulai Pencocokan Sekarang' }}</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal Hasil Uji Kecocokan Satu-per-Satu (Single Row Test Match) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showTestModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs font-sans overflow-y-auto"
          @click.self="showTestModal = false"
        >
          <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-2xl overflow-hidden my-8">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center"
                  :class="testResult?.matched ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-amber-50 text-amber-600 border border-amber-100'"
                >
                  <CheckCircleIcon v-if="testResult?.matched" class="w-4 h-4" />
                  <AlertCircleIcon v-else class="w-4 h-4" />
                </div>
                <div>
                  <h3 class="text-sm font-bold text-gray-900">Hasil Uji Kecocokan Data Program</h3>
                  <p class="text-[11px] text-gray-500">
                    {{ testRow?.dealer_name }} &bull; {{ testRow?.program_name }}
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="showTestModal = false"
                class="w-7 h-7 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              >
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
              <!-- Result Banner -->
              <div
                v-if="testResult?.matched"
                class="p-4 rounded-xl border flex items-start gap-3 bg-emerald-50/60 border-emerald-200"
              >
                <CheckCircleIcon class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                <div class="space-y-1 text-xs">
                  <div class="font-semibold text-emerald-900 flex items-center gap-2">
                    <span>BERHASIL DICOMPARE DENGAN FORM PROGRAM #{{ testResult.details?.submission_id }}</span>
                    <span
                      :class="[
                        'px-2 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase',
                        testResult.details?.status_potong_purchase === 'BISA DI POTONG' ? 'bg-teal-100 text-teal-800' : 'bg-amber-100 text-amber-800'
                      ]"
                    >
                      {{ testResult.details?.status_potong_purchase }}
                    </span>
                  </div>
                  <p class="text-emerald-800 text-[11px] leading-relaxed">
                    Status Cek Dokumen: <strong class="underline decoration-emerald-400">{{ testResult.details?.cek_dokumen }}</strong>.
                    Data di tabel lokal dan Google Spreadsheet telah diperbarui secara otomatis.
                  </p>
                </div>
              </div>

              <div
                v-else
                class="p-4 rounded-xl border flex items-start gap-3 bg-amber-50/70 border-amber-200"
              >
                <AlertCircleIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div class="space-y-1 text-xs">
                  <div class="font-semibold text-amber-900">
                    TIDAK ADA DATA FORM PROGRAM YANG COCOK
                  </div>
                  <p class="text-amber-800 text-[11px] leading-relaxed">
                    Sistem mengevaluasi seluruh pengajuan Form Program namun tidak menemukan pasangan yang memenuhi ke-4 kriteria ketat (Region, ID Real, Nama Dealer, Program) serta kecocokan finansial.
                  </p>
                </div>
              </div>

              <!-- 4 Kriteria Utama Pencocokan (Region, ID Real, Nama Dealer, Nama Program) -->
              <div class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between">
                  <span class="font-semibold text-gray-800 text-[11px]">Evaluasi 4 Kriteria Wajib</span>
                  <span class="text-[10px] text-gray-500 font-mono">Region &bull; Kode BT &bull; Dealer &bull; Program</span>
                </div>
                <div class="divide-y divide-gray-100 text-xs">
                  <!-- 1. Region -->
                  <div class="p-3 flex items-center justify-between gap-4">
                    <div class="w-28 text-gray-500 font-medium text-[11px]">1. Region</div>
                    <div class="flex-1 flex items-center gap-2 text-[11px]">
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testRow?.region">
                        {{ testRow?.region || testRow?.big_region || '-' }}
                      </div>
                      <ArrowRightIcon class="w-3 h-3 text-gray-400 shrink-0" />
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testResult?.details?.submission?.region">
                        {{ testResult?.details?.submission?.region || '(Tidak ada)' }}
                      </div>
                    </div>
                    <div class="shrink-0">
                      <span
                        v-if="testResult?.details?.criteria_match?.region"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                      >
                        <CheckIcon class="w-3 h-3" /> Cocok
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200"
                      >
                        <XIcon class="w-3 h-3" /> Beda
                      </span>
                    </div>
                  </div>

                  <!-- 2. Kode BT / ID Real -->
                  <div class="p-3 flex items-center justify-between gap-4">
                    <div class="w-28 text-gray-500 font-medium text-[11px]">2. Kode BT / ID Real</div>
                    <div class="flex-1 flex items-center gap-2 text-[11px]">
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testRow?.kode_bt">
                        {{ testRow?.kode_bt || '-' }}
                      </div>
                      <ArrowRightIcon class="w-3 h-3 text-gray-400 shrink-0" />
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testResult?.details?.submission?.id_real">
                        {{ testResult?.details?.submission?.id_real || '(Tidak ada)' }}
                      </div>
                    </div>
                    <div class="shrink-0">
                      <span
                        v-if="testResult?.details?.criteria_match?.kode_bt"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                      >
                        <CheckIcon class="w-3 h-3" /> Cocok
                      </span>
                      <span
                        v-else-if="testResult?.details?.criteria_match?.kode_bt === false"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200"
                      >
                        <XIcon class="w-3 h-3" /> Beda
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-500 border border-gray-200"
                      >
                        Opsional
                      </span>
                    </div>
                  </div>

                  <!-- 3. Nama Dealer -->
                  <div class="p-3 flex items-center justify-between gap-4">
                    <div class="w-28 text-gray-500 font-medium text-[11px]">3. Nama Dealer</div>
                    <div class="flex-1 flex items-center gap-2 text-[11px]">
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testRow?.dealer_name">
                        {{ testRow?.dealer_name || '-' }}
                      </div>
                      <ArrowRightIcon class="w-3 h-3 text-gray-400 shrink-0" />
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testResult?.details?.submission?.dealer_name">
                        {{ testResult?.details?.submission?.dealer_name || '(Tidak ada)' }}
                      </div>
                    </div>
                    <div class="shrink-0">
                      <span
                        v-if="testResult?.details?.criteria_match?.dealer"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                      >
                        <CheckIcon class="w-3 h-3" /> Cocok
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200"
                      >
                        <XIcon class="w-3 h-3" /> Beda
                      </span>
                    </div>
                  </div>

                  <!-- 4. Nama Program -->
                  <div class="p-3 flex items-center justify-between gap-4">
                    <div class="w-28 text-gray-500 font-medium text-[11px]">4. Nama Program</div>
                    <div class="flex-1 flex items-center gap-2 text-[11px]">
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testRow?.program_name">
                        {{ testRow?.program_name || '-' }}
                      </div>
                      <ArrowRightIcon class="w-3 h-3 text-gray-400 shrink-0" />
                      <div class="bg-gray-50 px-2 py-1 rounded border border-gray-200 text-gray-800 truncate max-w-[150px]" :title="testResult?.details?.submission?.program_name">
                        {{ testResult?.details?.submission?.program_name || '(Tidak ada)' }}
                      </div>
                    </div>
                    <div class="shrink-0">
                      <span
                        v-if="testResult?.details?.criteria_match?.program"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                      >
                        <CheckIcon class="w-3 h-3" /> Cocok
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200"
                      >
                        <XIcon class="w-3 h-3" /> Beda
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Validasi Aturan Pajak PKP vs Non-PKP -->
              <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/40 space-y-3">
                <div class="flex items-center justify-between">
                  <div class="font-semibold text-gray-800 text-[11px] flex items-center gap-1.5">
                    <ShieldCheckIcon class="w-4 h-4 text-indigo-600" />
                    <span>Kategori Pajak & Aturan Dokumen</span>
                  </div>
                  <span
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-semibold',
                      testResult?.details?.is_pkp ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-blue-50 text-blue-700 border border-blue-200'
                    ]"
                  >
                    {{ testResult?.details?.is_pkp ? 'WAJIB PAJAK PKP' : 'NON-PKP' }}
                  </span>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                  <div class="bg-white p-2.5 rounded-lg border border-gray-200">
                    <div class="text-[10px] text-gray-500 mb-0.5">Wajib Pajak (Master)</div>
                    <div class="font-semibold text-gray-800">{{ testResult?.details?.wajib_pajak || testRow?.wajib_pajak || '-' }}</div>
                  </div>
                  <div class="bg-white p-2.5 rounded-lg border border-gray-200">
                    <div class="text-[10px] text-gray-500 mb-0.5">Persyaratan Dokumen</div>
                    <div class="font-semibold" :class="testResult?.details?.is_pkp ? 'text-purple-700' : 'text-blue-700'">
                      {{ testResult?.details?.is_pkp ? 'Wajib CN, AGR & Faktur Pajak' : 'Hanya butuh CN & AGR (Bebas FP)' }}
                    </div>
                  </div>
                </div>

                <div class="text-[11px] text-gray-600 bg-white p-2.5 rounded-lg border border-gray-200 leading-relaxed">
                  <template v-if="testResult?.details?.is_pkp">
                    <strong>Aturan PKP:</strong> Wajib melampirkan Faktur Pajak yang sah bersama CN dan Agreement. Jika Faktur Pajak kosong, status dipotong tidak dapat disetujui (BELUM BISA POTONG).
                  </template>
                  <template v-else>
                    <strong>Aturan Non-PKP:</strong> Dealer Non-PKP (atau Pribadi Non-PKP) tidak menerbitkan faktur pajak. Cukup dokumen CN dan Agreement yang lengkap untuk status <strong>BISA DI POTONG</strong> dan Cek Dokumen <strong>LENGKAP</strong>.
                  </template>
                </div>
              </div>

              <!-- Pemindahan Link Dokumen Google Drive -->
              <div class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 text-[11px] font-semibold text-gray-800">
                  Dokumen Pendukung (Google Drive)
                </div>
                <div class="p-3 divide-y divide-gray-100 text-xs">
                  <!-- CN -->
                  <div class="py-2 flex items-center justify-between gap-3">
                    <div class="w-24 text-gray-600 font-medium text-[11px]">1. Credit Note (CN)</div>
                    <div class="flex-1 truncate">
                      <a
                        v-if="isValidUrl(testRow?.cn)"
                        :href="testRow.cn"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-600 hover:underline flex items-center gap-1 truncate text-[11px]"
                      >
                        <ExternalLinkIcon class="w-3 h-3 shrink-0" />
                        <span class="truncate">{{ testRow.cn }}</span>
                      </a>
                      <span v-else class="text-gray-400 text-[11px]">Belum ada dokumen</span>
                    </div>
                  </div>

                  <!-- Agrement -->
                  <div class="py-2 flex items-center justify-between gap-3">
                    <div class="w-24 text-gray-600 font-medium text-[11px]">2. Agreement</div>
                    <div class="flex-1 truncate">
                      <a
                        v-if="isValidUrl(testRow?.agrement)"
                        :href="testRow.agrement"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-600 hover:underline flex items-center gap-1 truncate text-[11px]"
                      >
                        <ExternalLinkIcon class="w-3 h-3 shrink-0" />
                        <span class="truncate">{{ testRow.agrement }}</span>
                      </a>
                      <span v-else class="text-gray-400 text-[11px]">Belum ada dokumen</span>
                    </div>
                  </div>

                  <!-- Faktur Pajak -->
                  <div class="py-2 flex items-center justify-between gap-3">
                    <div class="w-24 text-gray-600 font-medium text-[11px]">3. Faktur Pajak</div>
                    <div class="flex-1 truncate">
                      <a
                        v-if="isValidUrl(testRow?.cek_fp)"
                        :href="testRow.cek_fp"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-600 hover:underline flex items-center gap-1 truncate text-[11px]"
                      >
                        <ExternalLinkIcon class="w-3 h-3 shrink-0" />
                        <span class="truncate">{{ testRow.cek_fp }}</span>
                      </a>
                      <span v-else-if="!testResult?.details?.is_pkp" class="text-emerald-600 text-[11px] font-medium">
                        Bebas Faktur Pajak (Non-PKP)
                      </span>
                      <span v-else class="text-gray-400 text-[11px]">Belum ada dokumen</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Catatan Rekonsiliasi AI (Noted) -->
              <div v-if="testRow?.noted" class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-3 text-[11px] space-y-1">
                <div class="text-indigo-700 font-semibold flex items-center gap-1.5">
                  <SparklesIcon class="w-3.5 h-3.5 text-indigo-500" />
                  <span>Catatan Rekonsiliasi AI (Noted):</span>
                </div>
                <div class="text-gray-800 break-words font-mono text-[10px] leading-relaxed">
                  {{ testRow.noted }}
                </div>
              </div>

              <!-- Keterangan Manual -->
              <div v-if="testRow?.keterangan" class="bg-gray-50 border border-gray-200 rounded-xl p-3 text-[11px] space-y-1">
                <div class="text-gray-500 font-semibold">Keterangan Manual (Spreadsheet):</div>
                <div class="text-gray-800 break-words text-[11px]">
                  {{ testRow.keterangan }}
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs">
              <button
                type="button"
                @click="testMatchSingleRow(testRow, true)"
                :disabled="reconcilingRowId === testRow?.id"
                class="h-8 px-3 rounded-lg border border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50 transition cursor-pointer flex items-center gap-1.5 font-medium disabled:opacity-50"
              >
                <RefreshCwIcon :class="['w-3.5 h-3.5 text-indigo-600', reconcilingRowId === testRow?.id && 'animate-spin']" />
                <span>{{ reconcilingRowId === testRow?.id ? 'Menguji Ulang...' : 'Uji Ulang (Paksa)' }}</span>
              </button>
              <div class="flex items-center gap-2">
                <button
                  v-if="testRow?.status_potong_purchase === 'BISA DI POTONG'"
                  type="button"
                  @click="sendWaToTelemarketing(testRow)"
                  :disabled="sendingWaId === testRow?.id"
                  class="h-8 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer flex items-center gap-1.5 font-medium disabled:opacity-50 text-xs shadow-2xs"
                  title="Kirim info klaim ini ke WhatsApp Telemarketing"
                >
                  <RefreshCwIcon v-if="sendingWaId === testRow?.id" class="w-3.5 h-3.5 animate-spin" />
                  <SendIcon v-else class="w-3.5 h-3.5" />
                  <span>Kirim ke WA Telemarketing</span>
                </button>
                <button
                  type="button"
                  @click="showTestModal = false"
                  class="h-8 px-4 rounded-lg bg-gray-900 text-white font-medium hover:bg-gray-800 transition cursor-pointer"
                >
                  Tutup
                </button>
              </div>
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
  FileSpreadsheet as FileSpreadsheetIcon,
  FileText as FileTextIcon,
  Download as DownloadIcon,
  ChevronDown as ChevronDownIcon,
  X as XIcon,
  Zap as ZapIcon,
  Send as SendIcon,
  Info as InfoIcon,
  ArrowRight as ArrowRightIcon,
  ShieldCheck as ShieldCheckIcon,
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

const isValidUrl = (url) => {
  if (!url) return false;
  return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'));
};

const googleSheetUrl = 'https://docs.google.com/spreadsheets/d/1w8J_ahdfk-Dj0NZWkXf1GJucpusQ4vQaerdU5wfnkJc/edit?gid=1715579975#gid=1715579975';

const items = ref([]);
const isLoading = ref(false);
const isSyncing = ref(false);
const isExporting = ref(false);
const syncMessage = ref('');
const syncError = ref(false);
const autoRefresh = ref(false);
let autoRefreshTimer = null;
const lastUpdatedText = ref('');

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

const handleExportCsv = () => {
  showSpreadsheetDropdown.value = false;
  exportCsv();
};

// Filter states
const filters = reactive({
  search: '',
  region: '',
  program: '',
  status_purchase: '',
  status_ar: '',
});

const filterOptions = reactive({
  regions: [],
  programs: [],
  status_purchase: [],
  status_ar: [],
});

// Dropdown Nama Program state
const showProgramDropdown = ref(false);
const programDropdownRef = ref(null);
const programSearchQuery = ref('');
const programSearchInput = ref(null);

const filteredProgramOptions = computed(() => {
  if (!filterOptions.programs) return [];
  if (!programSearchQuery.value) return filterOptions.programs;
  const q = programSearchQuery.value.toLowerCase();
  return filterOptions.programs.filter((p) => p && p.toLowerCase().includes(q));
});

const toggleProgramDropdown = async () => {
  showProgramDropdown.value = !showProgramDropdown.value;
  if (showProgramDropdown.value) {
    programSearchQuery.value = '';
    await nextTick();
    if (programSearchInput.value) {
      programSearchInput.value.focus();
    }
  }
};

const selectProgram = (prog) => {
  filters.program = prog;
  showProgramDropdown.value = false;
  fetchData(1);
};

const clearProgramFilter = () => {
  filters.program = '';
  showProgramDropdown.value = false;
  fetchData(1);
};

const handleClickOutsideProgramDropdown = (e) => {
  if (programDropdownRef.value && !programDropdownRef.value.contains(e.target)) {
    showProgramDropdown.value = false;
  }
};

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0,
});

const hasActiveFilters = computed(() => {
  return !!(filters.search || filters.region || filters.program || filters.status_purchase || filters.status_ar);
});

// Formatting functions (Consistent with Form Program)
const formatRupiah = (val) => {
  if (val === null || val === undefined || val === '') return '-';
  const num = Number(val);
  if (isNaN(num)) return val;
  return new Intl.NumberFormat('id-ID').format(num);
};

let debounceTimer = null;
const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchData(1);
  }, 350);
};

const resetFilters = () => {
  filters.search = '';
  filters.region = '';
  filters.program = '';
  filters.status_purchase = '';
  filters.status_ar = '';
  fetchData(1);
};

const updateLastTimestamp = () => {
  const now = new Date();
  lastUpdatedText.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const fetchData = async (page = 1) => {
  isLoading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      region: filters.region || undefined,
      program: filters.program || undefined,
      status_purchase: filters.status_purchase || undefined,
      status_ar: filters.status_ar || undefined,
    };

    const res = await axios.get('/api/data-program', { params });
    items.value = res.data.items || [];
    Object.assign(pagination, res.data.pagination);
    if (res.data.filter_options) {
      filterOptions.regions = res.data.filter_options.regions || [];
      filterOptions.programs = res.data.filter_options.programs || [];
      filterOptions.status_purchase = res.data.filter_options.status_purchase || [];
      filterOptions.status_ar = res.data.filter_options.status_ar || [];
    }
    updateLastTimestamp();
  } catch (err) {
    console.error('Error fetching Data Program:', err);
  } finally {
    isLoading.value = false;
  }
};

const onPageInputEnter = (e) => {
  let page = parseInt(e.target.value, 10);
  if (isNaN(page) || page < 1) page = 1;
  if (page > pagination.last_page) page = pagination.last_page;
  fetchData(page);
};

const onPageInputBlur = (e) => {
  let page = parseInt(e.target.value, 10);
  if (isNaN(page) || page < 1) page = 1;
  if (page > pagination.last_page) page = pagination.last_page;
  if (page !== pagination.current_page) {
    fetchData(page);
  }
};

watch(autoRefresh, (enabled) => {
  if (enabled) {
    autoRefreshTimer = setInterval(() => {
      fetchData(pagination.current_page);
    }, 30000);
  } else if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = null;
  }
});

const triggerSync = async () => {
  if (isSyncing.value) return;
  isSyncing.value = true;
  syncMessage.value = '';
  syncError.value = false;

  try {
    const res = await axios.post('/api/data-program/sync');
    syncMessage.value = res.data.message || 'Sinkronisasi berhasil diselesaikan.';
    syncError.value = false;
    await fetchData(1);
  } catch (err) {
    syncMessage.value = 'Sinkronisasi gagal: ' + (err.response?.data?.message || err.message);
    syncError.value = true;
  } finally {
    isSyncing.value = false;
  }
};

const exportCsv = () => {
  if (isExporting.value) return;
  isExporting.value = true;

  const params = new URLSearchParams();
  if (filters.search) params.append('search', filters.search);
  if (filters.region) params.append('region', filters.region);
  if (filters.program) params.append('program', filters.program);
  if (filters.status_purchase) params.append('status_purchase', filters.status_purchase);
  if (filters.status_ar) params.append('status_ar', filters.status_ar);

  const qs = params.toString();
  const url = `/api/data-program/export${qs ? '?' + qs : ''}`;

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

// State for Reconcile with Form Program
const isReconciling = ref(false);
const showReconcileModal = ref(false);
const reconcileResult = ref(null);
const reconcileForm = reactive({
  limit: 200,
  year: '2026',
  force: false,
  only_unreconciled: true,
  push_to_sheet: true,
});

const openReconcileModal = () => {
  reconcileResult.value = null;
  showReconcileModal.value = true;
};

const handleReconcile = async () => {
  isReconciling.value = true;
  try {
    const res = await axios.post('/api/data-program/reconcile', {
      limit: reconcileForm.limit,
      year: reconcileForm.year,
      force: reconcileForm.force,
      only_unreconciled: reconcileForm.only_unreconciled,
      push_to_sheet: reconcileForm.push_to_sheet,
    });
    reconcileResult.value = res.data.data;
    syncMessage.value = res.data.message || 'Rekonsiliasi data berhasil diproses.';
    syncError.value = false;
    await fetchData(pagination.current_page);
  } catch (err) {
    syncMessage.value = err.response?.data?.message || 'Gagal melakukan rekonsiliasi.';
    syncError.value = true;
  } finally {
    isReconciling.value = false;
  }
};

// State for Single Row Reconciliation Test
const reconcilingRowId = ref(null);
const showTestModal = ref(false);
const testResult = ref(null);
const testRow = ref(null);

const testMatchSingleRow = async (row, force = false) => {
  reconcilingRowId.value = row.id;
  testRow.value = row;
  try {
    const res = await axios.post(`/api/data-program/${row.id}/reconcile`, {
      force: force,
      push_to_sheet: true,
    });
    testResult.value = res.data;
    if (res.data.data) {
      // update row in items array
      const idx = items.value.findIndex((item) => item.id === row.id);
      if (idx !== -1) {
        items.value[idx] = { ...items.value[idx], ...res.data.data };
        testRow.value = items.value[idx];
      }
    }
    showTestModal.value = true;
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal melakukan tes kecocokan data.');
  } finally {
    reconcilingRowId.value = null;
  }
};

// Send WhatsApp notification to Telemarketing
const sendingWaId = ref(null);

const sendWaToTelemarketing = async (row) => {
  if (!row) return;
  sendingWaId.value = row.id;

  try {
    const res = await axios.post(`/api/data-program/${row.id}/send-wa-telemarketing`);
    alert(res.data.message || `Notifikasi klaim "${row.dealer_name || row.kode_bt}" berhasil dikirim ke WhatsApp Telemarketing!`);
  } catch (err) {
    alert('Gagal mengirim WhatsApp ke Telemarketing: ' + (err.response?.data?.message || err.message));
  } finally {
    sendingWaId.value = null;
  }
};

onMounted(() => {
  fetchData(1);
  document.addEventListener('click', handleClickOutsideSpreadsheetDropdown);
  document.addEventListener('click', handleClickOutsideProgramDropdown);
});

onUnmounted(() => {
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
  document.removeEventListener('click', handleClickOutsideSpreadsheetDropdown);
  document.removeEventListener('click', handleClickOutsideProgramDropdown);
});
</script>
