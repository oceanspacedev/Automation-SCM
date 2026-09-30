<template>
  <div class="space-y-4 font-sans">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Data Program</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">List</span>
    </div>

    <!-- Header Page (Filament style) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Data Program</h1>
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
            class="h-9 px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer"
            title="Menu aksi Spreadsheet & Export"
          >
            <RefreshCwIcon v-if="isSyncing" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
            <FileSpreadsheetIcon v-else class="w-3.5 h-3.5 text-gray-500" />
            <span>{{ isSyncing ? 'Menyinkronkan...' : 'Spreadsheet' }}</span>
            <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 ml-0.5 transition-transform duration-150" :class="showSpreadsheetDropdown && 'rotate-180'" />
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

        <!-- Dropdown Pencocokan & Sinkronisasi Form Program -->
        <div class="relative" ref="reconcileDropdownRef">
          <button
            type="button"
            @click="showReconcileDropdown = !showReconcileDropdown"
            class="h-9 px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer"
            title="Menu sinkronisasi & pencocokan Form Program"
          >
            <RefreshCwIcon v-if="isSyncing || isReconciling" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
            <CheckCircleIcon v-else class="w-3.5 h-3.5 text-gray-500" />
            <span>{{ isSyncing ? 'Menyinkronkan...' : (isReconciling ? 'Mencocokkan...' : 'Cocokkan Form Program') }}</span>
            <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 ml-0.5 transition-transform duration-150" :class="showReconcileDropdown && 'rotate-180'" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showReconcileDropdown"
            class="absolute right-0 top-full mt-1.5 w-52 bg-white rounded-lg shadow-lg border border-gray-200 p-1 z-30 font-sans"
          >
            <!-- 1. Sinkronkan Sekarang -->
            <button
              type="button"
              @click="handleTriggerSyncFromDropdown"
              :disabled="isSyncing"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500 shrink-0', isSyncing && 'animate-spin text-emerald-600']" />
              <span>Sinkronkan Sekarang</span>
            </button>

            <!-- 2. Cocokkan Form Program -->
            <button
              type="button"
              @click="handleOpenReconcileFromDropdown"
              :disabled="isReconciling"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <CheckCircleIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>Cocokkan Form Program</span>
            </button>

            <div class="my-1 border-t border-gray-100"></div>

            <!-- 3. Riwayat Pencocokan -->
            <button
              type="button"
              @click="handleOpenHistoryFromDropdown"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer"
            >
              <HistoryIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>Riwayat Pencocokan</span>
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

    <!-- Filament Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Toolbar (Search & Filter like Filament) -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Left: Auto-Refresh & status -->
        <div class="flex items-center gap-3 text-xs text-gray-500">
          <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="autoRefresh"
              class="rounded border-gray-300 text-gray-900 focus:ring-0 w-3.5 h-3.5 cursor-pointer"
            />
            <span class="text-xs text-gray-600">Auto-Refresh (30s)</span>
          </label>
          <span v-if="lastUpdatedText" class="text-xs text-gray-400 hidden sm:inline">
            &bull; Update: {{ lastUpdatedText }}
          </span>
        </div>

        <!-- Right: Search Box & Filter Popover -->
        <div class="flex items-center gap-2 self-end sm:self-auto w-full sm:w-auto">
          <!-- Search box with Magnifying glass -->
          <div class="relative flex-1 sm:w-72">
            <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="filters.search"
              @input="debounceFetch"
              type="text"
              placeholder="Search dealer, program, kode BT, PO..."
              class="h-9 w-full pl-9 pr-3 text-xs text-gray-900 bg-white border border-gray-300 rounded-lg placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 transition"
            />
          </div>

          <!-- Filter Popover with Active Badge Count (funnel with badge) -->
          <Popover v-model:open="isFilterOpen">
            <PopoverTrigger as-child>
              <button
                type="button"
                :class="[
                  'h-9 px-2.5 rounded-lg border text-xs font-medium inline-flex items-center gap-1.5 transition shadow-2xs cursor-pointer',
                  activeFilterCount > 0
                    ? 'border-gray-900 bg-gray-50 text-gray-950'
                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                ]"
                title="Buka Filter"
              >
                <FilterIcon class="w-4 h-4 text-gray-600" />
                <span
                  :class="[
                    'w-4 h-4 rounded-full text-[10px] font-semibold flex items-center justify-center',
                    activeFilterCount > 0 ? 'bg-gray-950 text-white' : 'bg-gray-100 text-gray-600'
                  ]"
                >
                  {{ activeFilterCount }}
                </span>
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-80 p-3 space-y-3" align="end">
              <div class="text-xs font-semibold text-gray-900 border-b border-gray-100 pb-2 flex items-center justify-between">
                <span>Filter Data Program</span>
                <button
                  v-if="activeFilterCount > 0"
                  @click="resetFilters"
                  class="text-[11px] font-normal text-rose-600 hover:underline cursor-pointer"
                >
                  Reset
                </button>
              </div>

              <!-- Region Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Region</label>
                <select
                  v-model="filters.region"
                  @change="fetchData(1)"
                  class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                >
                  <option value="">Semua Region</option>
                  <option v-for="reg in filterOptions.regions" :key="reg" :value="reg">{{ reg }}</option>
                </select>
              </div>

              <!-- Program Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Nama Program</label>
                <div class="relative" ref="programDropdownRef">
                  <button
                    type="button"
                    @click="toggleProgramDropdown"
                    class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs flex items-center justify-between gap-2 hover:border-gray-300 focus:outline-none focus:ring-1 focus:ring-black transition cursor-pointer"
                    :class="filters.program ? 'border-gray-900 text-gray-900 font-medium bg-gray-50/60' : 'text-gray-700'"
                    :title="filters.program || 'Filter berdasarkan Nama Program'"
                  >
                    <span class="truncate text-left">{{ filters.program || 'Semua Program' }}</span>
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

                  <!-- Program Popover Inside Filter -->
                  <div
                    v-if="showProgramDropdown"
                    class="absolute top-full left-0 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl z-50 overflow-hidden text-xs"
                  >
                    <div class="p-2 border-b border-gray-100 bg-gray-50/70 flex items-center gap-2">
                      <SearchIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                      <input
                        ref="programSearchInput"
                        v-model="programSearchQuery"
                        type="text"
                        placeholder="Cari program..."
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
                    <div class="max-h-48 overflow-y-auto py-1 divide-y divide-gray-50">
                      <button
                        type="button"
                        @click="selectProgram('')"
                        class="w-full text-left px-3 py-1.5 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer text-xs"
                        :class="!filters.program ? 'bg-gray-50 font-medium text-gray-900' : 'text-gray-600'"
                      >
                        <span>Semua Program</span>
                        <CheckIcon v-if="!filters.program" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
                      </button>
                      <button
                        v-for="prog in filteredProgramOptions"
                        :key="prog"
                        type="button"
                        @click="selectProgram(prog)"
                        class="w-full text-left px-3 py-1.5 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer text-xs"
                        :class="filters.program === prog ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700'"
                      >
                        <span class="truncate pr-2" :title="prog">{{ prog }}</span>
                        <CheckIcon v-if="filters.program === prog" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Filter Status Purchase -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Status Purchase</label>
                <select
                  v-model="filters.status_purchase"
                  @change="fetchData(1)"
                  class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                >
                  <option value="">Semua Status Purchase</option>
                  <option v-for="opt in filterOptions.status_purchase" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>

              <!-- Filter Status AR -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Status AR</label>
                <select
                  v-model="filters.status_ar"
                  @change="fetchData(1)"
                  class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
                >
                  <option value="">Semua Status AR</option>
                  <option v-for="opt in filterOptions.status_ar" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <Table container-class="max-h-[calc(100vh-240px)]">
        <TableHeader>
          <TableRow class="border-b border-gray-200 text-xs hover:bg-transparent bg-white">
            <!-- 1. No (Frozen) -->
            <TableHead class="w-[50px] min-w-[50px] max-w-[50px] text-center font-semibold text-gray-950 sticky top-0 z-30 bg-white border-b border-gray-200" style="left: 0px;">No</TableHead>
            <!-- 2. Nama Dealer (Frozen) -->
            <TableHead class="w-[180px] min-w-[180px] max-w-[180px] font-semibold text-gray-950 sticky top-0 z-30 bg-white border-b border-gray-200" style="left: 50px;">
              <span class="inline-flex items-center gap-1">Nama Dealer <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
            </TableHead>
            <!-- 3. Program (Frozen) -->
            <TableHead class="w-[150px] min-w-[150px] max-w-[150px] font-semibold text-gray-950 sticky top-0 z-30 bg-white border-b border-gray-200" style="left: 230px;">
              <span class="inline-flex items-center gap-1">Program <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
            </TableHead>
            <!-- 4. Kode BT (Frozen) -->
            <TableHead class="w-[110px] min-w-[110px] max-w-[110px] font-semibold text-gray-950 sticky top-0 z-30 bg-white border-b border-gray-200" style="left: 380px;">
              <span class="inline-flex items-center gap-1">Kode BT <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
            </TableHead>
            <!-- 5. Nama Program (Frozen) -->
            <TableHead class="w-[300px] min-w-[300px] max-w-[300px] font-semibold text-gray-950 sticky top-0 z-30 bg-white border-b border-gray-200" style="left: 490px;">
              <span class="inline-flex items-center gap-1">Nama Program <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
            </TableHead>
            <!-- 6. Aksi (Frozen with divider shadow) -->
            <TableHead class="w-[90px] min-w-[90px] max-w-[90px] text-center font-semibold text-gray-950 sticky top-0 z-30 bg-white border-b border-r border-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.08),0_1px_0_0_#e5e7eb]" style="left: 790px;">Aksi</TableHead>
              <!-- 7. Periode -->
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Periode</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Region</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">No PO</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">ID GS</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Kode Supplier</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Status DL</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Sales Person</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Telemarketing</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Wajib Pajak</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">TRF PPh</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">Incentive</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">DPP</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">DPP Lain</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">PPN</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">Nilai PPh</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">Net Pay</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">Cek Pajak</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-right">Selisih</TableHead>
              <TableHead class="min-w-[100px] text-center font-semibold text-gray-950">Note PPh</TableHead>
              <TableHead class="min-w-[140px] font-semibold text-gray-950">No Faktur</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Ket Faktur</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">No PO/SJ</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">No Transaksi</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Tgl Input</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Tgl Share CN</TableHead>
              <TableHead class="w-[80px] text-center font-semibold text-gray-950">Pending</TableHead>
              <TableHead class="min-w-[150px] font-semibold text-gray-950">Keterangan</TableHead>
              <TableHead class="min-w-[150px] font-semibold text-gray-950">Cek Dokumen</TableHead>
              <TableHead class="min-w-[190px] font-semibold text-gray-950">Status Potong Purchase</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Status AR</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Tgl Potong/TF</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">No. UID</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">No. Pembayaran</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Tgl Bank PPh</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">T/F</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Tgl Proses</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Tgl SJ</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">No. SJ</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Info Bank</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Pending Potongan</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">NPWP</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Nama NPWP</TableHead>
              <TableHead class="min-w-[150px] font-semibold text-gray-950">Program 2</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-center">CN</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-center">Agrement</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-center">Cek FP</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950 text-center">Cek Evidance</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Noted</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Norek</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Namrek</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Bank</TableHead>
              <TableHead class="whitespace-nowrap font-semibold text-gray-950">Big Region</TableHead>
            </TableRow>
          </TableHeader>
        <TableBody>
          <!-- Loading State -->
          <TableEmpty v-if="isLoading && items.length === 0" :colspan="58">
            <div class="inline-flex items-center gap-2 text-gray-500 py-8">
              <RefreshCwIcon class="w-4 h-4 animate-spin text-gray-400" />
              <span class="text-xs">Memuat data program...</span>
            </div>
          </TableEmpty>

          <!-- Empty State -->
          <TableEmpty v-else-if="items.length === 0" :colspan="58">
            <div class="max-w-md mx-auto py-8 space-y-1.5 text-center text-gray-500">
              <p class="font-medium text-gray-800 text-xs">Belum ada data program yang tersimpan.</p>
              <p class="text-xs text-gray-500">
                Klik tombol <strong>"Sinkronkan Sekarang"</strong> di atas untuk memuat data dari spreadsheet Anda.
              </p>
            </div>
          </TableEmpty>

          <!-- Data Rows (All typography exactly matches Filament standard: text-xs, text-gray-800, Inter sans) -->
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
              <div class="line-clamp-2 text-gray-700 break-words" :title="row.dealer_name">{{ row.dealer_name || '-' }}</div>
            </TableCell>

            <!-- 3. Program (Frozen) -->
            <TableCell class="w-[150px] min-w-[150px] max-w-[150px] text-xs text-gray-700 py-2.5 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 230px;">
              <div class="truncate" :title="row.program">{{ row.program || '-' }}</div>
            </TableCell>

            <!-- 4. Kode BT (Frozen) -->
            <TableCell class="w-[110px] min-w-[110px] max-w-[110px] text-xs text-gray-700 py-2.5 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 380px;">
              <div class="truncate" :title="row.kode_bt">{{ row.kode_bt || '-' }}</div>
            </TableCell>

            <!-- 5. Nama Program (Frozen) -->
            <TableCell class="w-[300px] min-w-[300px] max-w-[300px] text-xs text-gray-700 py-2.5 leading-snug sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-gray-200" style="left: 490px;">
              {{ row.program_name || '-' }}
            </TableCell>

            <!-- 6. Aksi / Cek Data (Frozen with divider shadow) -->
            <TableCell class="w-[90px] min-w-[90px] max-w-[90px] text-center text-xs py-2 sticky z-10 bg-white group-hover:bg-gray-50/80 transition-colors border-b border-r border-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.08)]" style="left: 790px;">
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
                  class="w-7 h-7 shrink-0 inline-flex items-center justify-center rounded-md border border-emerald-200/80 bg-emerald-50/70 hover:bg-emerald-100 text-emerald-700 transition shadow-2xs cursor-pointer disabled:opacity-50"
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
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50/70 text-emerald-700 border border-emerald-200/80 text-xs font-normal"
                >
                  <CheckIcon class="w-3.5 h-3.5 text-emerald-600" />
                  <span>ok</span>
                </span>
                <span
                  v-else-if="row.note_pph"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50/70 text-amber-700 border border-amber-200/80 text-xs font-normal"
                >
                  <AlertCircleIcon class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                  <span>{{ row.note_pph }}</span>
                </span>
                <span v-else class="text-gray-300 text-xs">-</span>
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
                <span v-if="row.lama_pending" class="px-2 py-0.5 rounded bg-white border border-gray-200 text-xs text-gray-700">
                  {{ row.lama_pending }}
                </span>
                <span v-else class="text-gray-300 text-xs">-</span>
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
                    'line-clamp-2 max-w-[160px] text-xs px-2 py-0.5 rounded inline-block border',
                    row.cek_dokumen === 'LENGKAP' || row.cek_dokumen === 'OK'
                      ? 'bg-emerald-50/70 text-emerald-700 border-emerald-200/80'
                      : 'bg-amber-50/70 text-amber-700 border-amber-200/80'
                  ]"
                  :title="row.cek_dokumen"
                >
                  {{ row.cek_dokumen }}
                </div>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 34. Status Potong By Purchase -->
              <TableCell class="py-2.5 whitespace-nowrap">
                <span
                  v-if="row.status_potong_purchase"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-normal border inline-block',
                    row.status_potong_purchase === 'BISA DI POTONG'
                      ? 'bg-emerald-50/70 text-emerald-700 border-emerald-200/80'
                      : row.status_potong_purchase === 'SUDAH POTONG'
                      ? 'bg-sky-50/70 text-sky-700 border-sky-200/80'
                      : row.status_potong_purchase === 'DONE TRANSFER'
                      ? 'bg-violet-50/70 text-violet-700 border-violet-200/80'
                      : row.status_potong_purchase === 'BELUM BISA POTONG'
                      ? 'bg-rose-50/70 text-rose-700 border-rose-200/80'
                      : 'bg-gray-50 text-gray-600 border-gray-200'
                  ]"
                >
                  {{ row.status_potong_purchase }}
                </span>
                <span v-else class="text-gray-300 text-xs">-</span>
              </TableCell>

              <!-- 35. Status Potong By AR -->
              <TableCell class="whitespace-nowrap text-xs text-gray-700 py-2.5">
                <span
                  v-if="row.status_potong_ar"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-normal border inline-block',
                    row.status_potong_ar.includes('DEALER SETUJU')
                      ? 'bg-sky-50/70 text-sky-700 border-sky-200/80'
                      : row.status_potong_ar === 'DONE'
                      ? 'bg-emerald-50/70 text-emerald-700 border-emerald-200/80'
                      : row.status_potong_ar.includes('PENDING')
                      ? 'bg-amber-50/70 text-amber-700 border-amber-200/80'
                      : 'bg-gray-50 text-gray-600 border-gray-200'
                  ]"
                >
                  {{ row.status_potong_ar }}
                </span>
                <span v-else class="text-gray-300 text-xs">-</span>
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
                    <FileTextIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
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
                    <FileTextIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
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
                    <FileTextIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
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
                    <FileTextIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
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

      <!-- Filament Pagination Footer -->
      <FilamentPagination
        :total="pagination.total"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        :per-page-options="[15, 25, 50, 100]"
        @page-change="fetchData"
        @per-page-change="changePerPage"
      />
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
                  <div class="pt-2 flex items-center justify-between border-t border-emerald-100">
                    <span v-if="reconcileResult.results.length > 5" class="text-[10px] text-emerald-800">
                      +{{ reconcileResult.results.length - 5 }} data lainnya berhasil diperbarui
                    </span>
                    <button
                      type="button"
                      @click="openHistoryFromReconcile"
                      class="text-[11px] font-medium text-emerald-900 hover:underline inline-flex items-center gap-1 cursor-pointer ml-auto"
                    >
                      <HistoryIcon class="w-3.5 h-3.5 text-emerald-700" />
                      <span>Buka Riwayat Lengkap Batch Ini</span>
                    </button>
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
          <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden font-sans my-6">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/70 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-gray-900">Hasil Uji Kecocokan Data Program</h3>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                  Dealer: <strong class="text-gray-800">{{ testRow?.dealer_name || '-' }}</strong>
                  <span v-if="testRow?.kode_bt" class="text-gray-400"> ({{ testRow.kode_bt }})</span>
                  &bull; {{ testRow?.program_name }}
                </p>
              </div>
              <button
                type="button"
                @click="showTestModal = false"
                class="w-8 h-8 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              >
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Body (Tabel Polos yang Rapi) -->
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
              <!-- Ringkasan Status -->
              <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 flex items-center justify-between">
                <div>
                  <span class="font-medium text-gray-900">
                    {{ testResult?.matched ? 'Berhasil dicocokkan dengan Form Program #' + testResult.details?.submission_id : 'Belum ditemukan data Form Program yang cocok' }}
                  </span>
                  <div class="text-[11px] text-gray-500 mt-0.5">
                    Status Potong: <strong class="text-gray-800">{{ testResult?.details?.status_potong_purchase || testRow?.status_potong_purchase || '-' }}</strong>
                    &bull; Cek Dokumen: <strong class="text-gray-800">{{ testResult?.details?.cek_dokumen || testRow?.cek_dokumen || '-' }}</strong>
                  </div>
                </div>
                <div class="shrink-0 ml-4">
                  <span
                    v-if="testResult?.matched"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold"
                  >
                    <CheckIcon class="w-3 h-3" />
                    <span>Sesuai</span>
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 border border-gray-200 text-xs font-medium"
                  >
                    <span>Belum Cocok</span>
                  </span>
                </div>
              </div>

              <!-- Tabel Komparasi Rapi -->
              <div class="rounded-lg border border-gray-200 overflow-hidden">
                <table class="w-full text-xs text-left">
                  <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-medium">
                    <tr>
                      <th class="px-3.5 py-2.5 w-1/4">Parameter</th>
                      <th class="px-3.5 py-2.5 w-1/3">Data Program (Master)</th>
                      <th class="px-3.5 py-2.5 w-1/3">Form Program (Respon)</th>
                      <th class="px-3.5 py-2.5 w-[85px] text-center">Hasil</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 text-gray-700">
                    <!-- 1. Region -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Region</td>
                      <td class="px-3.5 py-2">{{ testRow?.region || testRow?.big_region || '-' }}</td>
                      <td class="px-3.5 py-2">{{ testResult?.details?.submission?.region || '-' }}</td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="testResult?.details?.criteria_match?.region" class="text-emerald-700 font-medium">Cocok</span>
                        <span v-else class="text-rose-600 font-medium">Beda</span>
                      </td>
                    </tr>

                    <!-- 2. Kode BT / ID Real -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Kode BT / ID Real</td>
                      <td class="px-3.5 py-2">{{ testRow?.kode_bt || '-' }}</td>
                      <td class="px-3.5 py-2">{{ testResult?.details?.submission?.id_real || '-' }}</td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="testResult?.details?.criteria_match?.kode_bt" class="text-emerald-700 font-medium">Cocok</span>
                        <span v-else-if="testResult?.details?.criteria_match?.kode_bt === false" class="text-rose-600 font-medium">Beda</span>
                        <span v-else class="text-gray-400">Opsional</span>
                      </td>
                    </tr>

                    <!-- 3. Nama Dealer -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Nama Dealer</td>
                      <td class="px-3.5 py-2">{{ testRow?.dealer_name || '-' }}</td>
                      <td class="px-3.5 py-2">{{ testResult?.details?.submission?.dealer_name || '-' }}</td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="testResult?.details?.criteria_match?.dealer" class="text-emerald-700 font-medium">Cocok</span>
                        <span v-else class="text-rose-600 font-medium">Beda</span>
                      </td>
                    </tr>

                    <!-- 4. Nama Program -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Nama Program</td>
                      <td class="px-3.5 py-2">{{ testRow?.program_name || '-' }}</td>
                      <td class="px-3.5 py-2">{{ testResult?.details?.submission?.program_name || '-' }}</td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="testResult?.details?.criteria_match?.program" class="text-emerald-700 font-medium">Cocok</span>
                        <span v-else class="text-rose-600 font-medium">Beda</span>
                      </td>
                    </tr>

                    <!-- 5. Nominal Finansial (DPP / Net Pay) -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Nominal (DPP / Net)</td>
                      <td class="px-3.5 py-2 font-medium">{{ formatRupiah(testRow?.net_pay || testRow?.dpp) }}</td>
                      <td class="px-3.5 py-2 font-medium">{{ formatRupiah(testResult?.details?.submission?.net_pay || testResult?.details?.submission?.dpp) }}</td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="testResult?.details?.financial_match || testResult?.details?.criteria_match?.financial" class="text-emerald-700 font-medium">Cocok</span>
                        <span v-else class="text-gray-500">
                          {{ testResult?.details?.selisih ? formatRupiah(testResult.details.selisih) : '-' }}
                        </span>
                      </td>
                    </tr>

                    <!-- 6. Dokumen CN -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Dokumen CN</td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(testRow?.cn)" :href="testRow.cn" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Buka Berkas</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(testResult?.details?.submission?.credit_note_url)" :href="testResult.details.submission.credit_note_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Buka Berkas Form</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="isValidUrl(testRow?.cn) || isValidUrl(testResult?.details?.submission?.credit_note_url)" class="text-emerald-700 font-medium">Ada</span>
                        <span v-else class="text-rose-600 font-medium">Kosong</span>
                      </td>
                    </tr>

                    <!-- 7. Dokumen Agreement -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Dokumen Agreement</td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(testRow?.agrement)" :href="testRow.agrement" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Buka Berkas</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(testResult?.details?.submission?.agreement_url)" :href="testResult.details.submission.agreement_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Buka Berkas Form</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="isValidUrl(testRow?.agrement) || isValidUrl(testResult?.details?.submission?.agreement_url)" class="text-emerald-700 font-medium">Ada</span>
                        <span v-else class="text-rose-600 font-medium">Kosong</span>
                      </td>
                    </tr>

                    <!-- 8. Dokumen Faktur Pajak -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Dokumen Faktur Pajak</td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(testRow?.cek_fp)" :href="testRow.cek_fp" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Buka Berkas</a>
                        <span v-else-if="!testResult?.details?.is_pkp" class="text-gray-500">Bebas FP (Non-PKP)</span>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(testResult?.details?.submission?.tax_invoice_url)" :href="testResult.details.submission.tax_invoice_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Buka Berkas Form</a>
                        <span v-else-if="!testResult?.details?.is_pkp" class="text-gray-500">Bebas FP (Non-PKP)</span>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2 text-center">
                        <span v-if="!testResult?.details?.is_pkp" class="text-gray-500 font-medium">Bebas FP</span>
                        <span v-else-if="isValidUrl(testRow?.cek_fp) || isValidUrl(testResult?.details?.submission?.tax_invoice_url)" class="text-emerald-700 font-medium">Ada</span>
                        <span v-else class="text-rose-600 font-medium">Kosong</span>
                      </td>
                    </tr>

                    <!-- 9. Wajib Pajak & Aturan -->
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Wajib Pajak & Aturan</td>
                      <td colspan="3" class="px-3.5 py-2">
                        <div class="flex items-center gap-2">
                          <span class="font-medium text-gray-900">{{ testResult?.details?.wajib_pajak || testRow?.wajib_pajak || '-' }}</span>
                          <span class="text-gray-400">&bull;</span>
                          <span class="text-gray-600">
                            {{ testResult?.details?.is_pkp ? 'PKP (Wajib melampirkan Faktur Pajak yang sah bersama CN & Agreement)' : 'NON-PKP (Cukup dokumen CN & Agreement)' }}
                          </span>
                        </div>
                      </td>
                    </tr>

                    <!-- 10. Catatan Rekonsiliasi -->
                    <tr v-if="testRow?.noted || testRow?.keterangan || testResult?.details?.keterangan">
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Catatan Rekonsiliasi</td>
                      <td colspan="3" class="px-3.5 py-2 text-gray-700">
                        {{ testRow?.noted || testRow?.keterangan || testResult?.details?.keterangan || '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-gray-50/70 border-t border-gray-200 flex items-center justify-between text-xs">
              <button
                type="button"
                @click="testMatchSingleRow(testRow, true)"
                :disabled="reconcilingRowId === testRow?.id"
                class="h-8 px-3 rounded-md border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition cursor-pointer flex items-center gap-1.5 font-medium disabled:opacity-50 text-xs shadow-2xs"
              >
                <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', reconcilingRowId === testRow?.id && 'animate-spin']" />
                <span>{{ reconcilingRowId === testRow?.id ? 'Menguji Ulang...' : 'Uji Ulang (Paksa)' }}</span>
              </button>
              <div class="flex items-center gap-2">
                <button
                  v-if="testRow?.status_potong_purchase === 'BISA DI POTONG'"
                  type="button"
                  @click="sendWaToTelemarketing(testRow)"
                  :disabled="sendingWaId === testRow?.id"
                  class="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer flex items-center gap-1.5 font-medium disabled:opacity-50 text-xs shadow-2xs"
                  title="Kirim info klaim ini ke WhatsApp Telemarketing"
                >
                  <RefreshCwIcon v-if="sendingWaId === testRow?.id" class="w-3.5 h-3.5 animate-spin" />
                  <SendIcon v-else class="w-3.5 h-3.5" />
                  <span>Kirim ke WA Telemarketing</span>
                </button>
                <button
                  type="button"
                  @click="openHistoryModal(testRow)"
                  class="h-8 px-3 rounded-md border border-gray-200 bg-white text-gray-700 hover:bg-gray-100 transition cursor-pointer flex items-center gap-1.5 font-normal text-xs"
                  title="Lihat riwayat pencocokan untuk baris ini"
                >
                  <HistoryIcon class="w-3.5 h-3.5 text-gray-500" />
                  <span>Riwayat Baris Ini</span>
                </button>
                <button
                  type="button"
                  @click="showTestModal = false"
                  class="h-8 px-3.5 rounded-md border border-gray-200 bg-white hover:bg-gray-100 text-gray-700 font-medium transition cursor-pointer text-xs"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal Riwayat Pencocokan Form Program -->
    <ReconciliationHistoryModal
      v-model="showHistoryModal"
      :initial-data-program-id="historyInitialRowId"
      :initial-batch-id="historyInitialBatchId"
      :filtered-row-name="historyFilteredRowName"
    />
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
  ChevronRight as ChevronRightIcon,
  Filter as FilterIcon,
  X as XIcon,
  Zap as ZapIcon,
  Send as SendIcon,
  Info as InfoIcon,
  ArrowRight as ArrowRightIcon,
  ShieldCheck as ShieldCheckIcon,
  History as HistoryIcon,
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
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import FilamentBadge from '@/components/ui/FilamentBadge.vue';
import ReconciliationHistoryModal from '@/components/ReconciliationHistoryModal.vue';

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

// Dropdown Pencocokan Form Program state
const showReconcileDropdown = ref(false);
const reconcileDropdownRef = ref(null);

const handleClickOutsideReconcileDropdown = (e) => {
  if (reconcileDropdownRef.value && !reconcileDropdownRef.value.contains(e.target)) {
    showReconcileDropdown.value = false;
  }
};

const handleTriggerSyncFromDropdown = () => {
  showReconcileDropdown.value = false;
  triggerSync();
};

const handleOpenReconcileFromDropdown = () => {
  showReconcileDropdown.value = false;
  openReconcileModal();
};

const handleOpenHistoryFromDropdown = () => {
  showReconcileDropdown.value = false;
  openHistoryModal(null);
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

const isFilterOpen = ref(false);

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.region) count++;
  if (filters.program) count++;
  if (filters.status_purchase) count++;
  if (filters.status_ar) count++;
  return count;
});

const changePerPage = (newPerPage) => {
  pagination.per_page = Number(newPerPage);
  fetchData(1);
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

// State for Reconciliation History Modal
const showHistoryModal = ref(false);
const historyInitialRowId = ref(null);
const historyInitialBatchId = ref(null);
const historyFilteredRowName = ref(null);

const openHistoryModal = (row = null, batchId = null) => {
  historyInitialRowId.value = row ? row.id : null;
  historyFilteredRowName.value = row ? (row.dealer_name || row.kode_bt) : null;
  historyInitialBatchId.value = batchId || null;
  showHistoryModal.value = true;
};

const openHistoryFromReconcile = () => {
  const batchId = reconcileResult.value?.batch_id || null;
  showReconcileModal.value = false;
  openHistoryModal(null, batchId);
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
  document.addEventListener('click', handleClickOutsideReconcileDropdown);
  document.addEventListener('click', handleClickOutsideProgramDropdown);
});

onUnmounted(() => {
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
  document.removeEventListener('click', handleClickOutsideSpreadsheetDropdown);
  document.removeEventListener('click', handleClickOutsideReconcileDropdown);
  document.removeEventListener('click', handleClickOutsideProgramDropdown);
});
</script>
