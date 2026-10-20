<template>
  <div class="space-y-4 font-sans">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Data Program</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span>{{ area.label }}</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">List</span>
    </div>

    <AreaTabs />

    <!-- Header Page (Filament style) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Data Program {{ area.label }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Sinkronisasi master data program (56 kolom) {{ area.label }} langsung dari Google Spreadsheet.
        </p>
      </div>

      <!-- Action Buttons (Spreadsheet Dropdown & Direct Sync); di layar kecil dua tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-2 sm:flex sm:flex-wrap sm:items-center gap-2">
        <!-- Dropdown Spreadsheet -->
        <div class="relative" ref="spreadsheetDropdownRef">
          <button
            type="button"
            @click="showSpreadsheetDropdown = !showSpreadsheetDropdown"
            class="h-9 w-full sm:w-auto px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer whitespace-nowrap"
            title="Menu aksi Spreadsheet & Export"
          >
            <RefreshCwIcon v-if="isSyncing" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
            <FileSpreadsheetIcon v-else class="hidden sm:block w-3.5 h-3.5 text-gray-500" />
            <span class="sm:hidden">{{ isSyncing ? 'Sinkron...' : 'Spreadsheet' }}</span>
            <span class="hidden sm:inline">{{ isSyncing ? 'Menyinkronkan...' : 'Spreadsheet' }}</span>
            <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 ml-0.5 transition-transform duration-150" :class="showSpreadsheetDropdown && 'rotate-180'" />
          </button>

          <!-- Dropdown Menu; di layar kecil rata kiri agar tidak keluar layar -->
          <div
            v-if="showSpreadsheetDropdown"
            class="absolute left-0 sm:left-auto sm:right-0 top-full mt-1.5 w-48 bg-white rounded-lg shadow-lg border border-gray-200 p-1 z-50 font-sans"
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
              @click="openUrlModal"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer"
            >
              <SettingsIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>Atur Link Spreadsheet</span>
            </button>

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

        <!-- Dropdown Pencocokan & Sinkronisasi Test Program -->
        <div class="relative" ref="reconcileDropdownRef">
          <button
            type="button"
            @click="showReconcileDropdown = !showReconcileDropdown"
            class="h-9 w-full sm:w-auto px-2 sm:px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer whitespace-nowrap"
            title="Menu sinkronisasi & pencocokan Test Program"
          >
            <RefreshCwIcon v-if="isSyncing || isReconciling" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
            <CheckCircleIcon v-else class="hidden sm:block w-3.5 h-3.5 text-gray-500" />
            <span class="sm:hidden">{{ isSyncing ? 'Sinkron...' : (isReconciling ? 'Mencocokkan...' : 'Cocokkan') }}</span>
            <span class="hidden sm:inline">{{ isSyncing ? 'Menyinkronkan...' : (isReconciling ? 'Mencocokkan...' : 'Cocokkan Test Program') }}</span>
            <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 ml-0.5 transition-transform duration-150" :class="showReconcileDropdown && 'rotate-180'" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showReconcileDropdown"
            class="absolute right-0 top-full mt-1.5 w-52 bg-white rounded-lg shadow-lg border border-gray-200 p-1 z-50 font-sans"
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

            <!-- 2. Cocokkan Test Program -->
            <button
              type="button"
              @click="handleOpenReconcileFromDropdown"
              :disabled="isReconciling"
              class="w-full text-left px-2.5 py-1.5 text-xs font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <CheckCircleIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
              <span>Cocokkan Test Program</span>
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
      <!-- Toolbar (Search & Filter like Filament); di layar kecil cari di baris sendiri, Auto-Refresh & filter di bawahnya -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 grid grid-cols-[1fr_auto] items-center sm:flex sm:flex-row justify-between gap-3">
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
        <div class="contents sm:flex items-center gap-2 sm:self-auto sm:w-auto">
          <!-- Search box with Magnifying glass -->
          <div class="relative flex-1 sm:w-72 col-span-2 order-first sm:order-none">
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
            <PopoverContent class="w-80 p-3 space-y-3 max-h-[85vh] overflow-y-auto" align="end">
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
              <div class="space-y-1" ref="programDropdownRef">
                <label class="text-[11px] font-medium text-gray-700">Nama Program</label>
                <div>
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

                  <!-- Program List Inside Filter (In-flow to prevent overlap) -->
                  <div
                    v-if="showProgramDropdown"
                    class="mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xs overflow-hidden text-xs"
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
                        <span>Semua Program ({{ filterOptions.programs.length }})</span>
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
                      <div v-if="filteredProgramOptions.length === 0" class="px-3 py-2 text-center text-gray-400 text-xs">
                        Tidak ada program yang cocok
                      </div>
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

      <!-- Layar kecil: satu kartu per data program -->
      <div class="md:hidden">
        <p v-if="isLoading && items.length === 0" class="py-8 text-center text-xs text-gray-500">Memuat data program...</p>
        <div v-else-if="items.length === 0" class="py-8 px-4 space-y-1.5 text-center text-xs text-gray-500">
          <p class="font-medium text-gray-800">Belum ada data program yang tersimpan.</p>
          <p>Klik tombol <strong>"Sinkronkan Sekarang"</strong> di atas untuk memuat data dari spreadsheet Anda.</p>
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="row in items" :key="row.id" class="p-4 space-y-2 text-xs text-gray-700">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 break-words">{{ row.dealer_name || '-' }}</p>
              <p class="mt-0.5 text-gray-500 break-words">{{ row.kode_bt || '-' }} · {{ row.program || '-' }}</p>
            </div>
            <p class="text-gray-900 break-words">{{ row.program_name || '-' }}</p>
            <p class="text-gray-500 break-words">{{ row.region || '-' }} · {{ row.telemarketing || '-' }}</p>
            <div v-if="row.status_potong_purchase || row.cek_dokumen" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-gray-900">
              <span v-if="row.status_potong_purchase" class="inline-flex items-center gap-1.5">
                <span
                  class="w-1.5 h-1.5 rounded-full shrink-0"
                  :class="
                    row.status_potong_purchase === 'BISA DI POTONG'
                      ? 'bg-emerald-500'
                      : row.status_potong_purchase === 'SUDAH POTONG'
                      ? 'bg-sky-500'
                      : row.status_potong_purchase === 'DONE TRANSFER'
                      ? 'bg-violet-500'
                      : row.status_potong_purchase === 'BELUM BISA POTONG'
                      ? 'bg-rose-500'
                      : 'bg-gray-400'
                  "
                ></span>
                {{ row.status_potong_purchase }}
              </span>
              <span v-if="row.cek_dokumen" class="inline-flex items-center gap-1.5">
                <span
                  class="w-1.5 h-1.5 rounded-full shrink-0"
                  :class="row.cek_dokumen === 'LENGKAP' || row.cek_dokumen === 'OK' ? 'bg-emerald-500' : 'bg-amber-500'"
                ></span>
                <span><span class="text-gray-500">Dokumen</span> {{ row.cek_dokumen }}</span>
              </span>
            </div>
            <p v-if="row.no_po" class="text-gray-500 break-words">No PO <span class="text-gray-900">{{ row.no_po }}</span></p>

            <div class="flex items-end justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-950">{{ formatRupiah(row.net_pay) }}</p>
                <p class="text-gray-500">Net Pay</p>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="testMatchSingleRow(row)"
                  :disabled="reconcilingRowId === row.id"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer disabled:opacity-50"
                >
                  {{ reconcilingRowId === row.id ? 'Menguji...' : 'Lihat' }}
                </button>
                <button
                  type="button"
                  @click="openEditModal(row)"
                  class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                >
                  Edit
                </button>
                <button
                  v-if="row.status_potong_purchase === 'BISA DI POTONG'"
                  type="button"
                  @click="sendWaToTelemarketing(row)"
                  :disabled="sendingWaId === row.id"
                  class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer disabled:opacity-50"
                >
                  <WhatsAppIcon v-if="sendingWaId !== row.id" class="w-3.5 h-3.5 text-[#25D366]" />
                  {{ sendingWaId === row.id ? 'Mengirim...' : 'WA' }}
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Table: Styled identically to Test Program for clean spreadsheet look -->
      <div ref="gridContainer" class="hidden md:block relative overflow-auto max-h-[calc(100vh-240px)]" @scroll="refreshBoxes">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <!-- 1. No (Locked Top-Left) -->
              <th scope="col" class="py-2.5 px-3 text-center w-[50px] min-w-[50px] max-w-[50px] border-r border-b border-gray-200 bg-gray-50 sticky left-0 top-0 z-40 shadow-[0_1px_0_0_#e5e7eb]" style="left: 0px; top: 0px;">No</th>
              <!-- 2. Nama Dealer (Locked Top-Left) -->
              <th scope="col" class="py-2.5 px-3 w-[180px] min-w-[180px] max-w-[180px] border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-40 shadow-[0_1px_0_0_#e5e7eb]" style="left: 50px; top: 0px;">
                <span class="inline-flex items-center gap-1">Nama Dealer <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
              </th>
              <!-- 3. Program (Locked Top-Left) -->
              <th scope="col" class="py-2.5 px-3 w-[130px] min-w-[130px] max-w-[130px] border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-40 shadow-[0_1px_0_0_#e5e7eb]" style="left: 230px; top: 0px;">
                <span class="inline-flex items-center gap-1">Program <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
              </th>
              <!-- 4. Kode BT (Locked Top-Left) -->
              <th scope="col" class="py-2.5 px-3 w-[100px] min-w-[100px] max-w-[100px] border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-40 shadow-[0_1px_0_0_#e5e7eb]" style="left: 360px; top: 0px;">
                <span class="inline-flex items-center gap-1">Kode BT <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
              </th>
              <!-- 5. Nama Program (Locked Top-Left with divider shadow) -->
              <th scope="col" class="py-2.5 px-3 w-[240px] min-w-[240px] max-w-[240px] border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-40 shadow-[3px_0_6px_-2px_rgba(0,0,0,0.08),0_1px_0_0_#e5e7eb]" style="left: 460px; top: 0px;">
                <span class="inline-flex items-center gap-1">Nama Program <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" /></span>
              </th>
              <!-- 6. Periode -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Periode</th>
              <!-- 8. Region -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Region</th>
              <!-- 9. No PO -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No PO</th>
              <!-- 10. ID GS -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">ID GS</th>
              <!-- 12. Status DL -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Status DL</th>
              <!-- 13. Sales Person -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Sales Person</th>
              <!-- 14. Telemarketing -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Telemarketing</th>
              <!-- 15. Wajib Pajak -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Wajib Pajak</th>
              <!-- 16. TRF PPh -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">TRF PPh</th>
              <!-- 17-27. Finansial & Pajak -->
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Incentive</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">DPP</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">DPP Lain</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">PPN</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Nilai PPh</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Net Pay</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Cek Pajak</th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Selisih</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Note PPh</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No Faktur</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Ket Faktur</th>
              <!-- 28-34. Administrasi & Status -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No PO/SJ</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No Transaksi</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Tgl Input</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Tgl Share CN</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Pending</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Keterangan</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Cek Dokumen</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Status Potong Purchase</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Status AR</th>
              <!-- 37-46. Tanggal, Bank & Rekening -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Tgl Potong/TF</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No. UID</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No. Pembayaran</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Tgl Bank PPh</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">T/F</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Tgl Proses</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Tgl SJ</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">No. SJ</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Info Bank</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Pending Potongan</th>
              <!-- 47-49. NPWP & Program 2 -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">NPWP</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Nama NPWP</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Program 2</th>
              <!-- 50-53. Dokumen Lampiran -->
              <th scope="col" class="py-2.5 px-3 text-center border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">CN</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Agr</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">FP</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Evid</th>
              <!-- 54-58. Catatan, Bank & Big Region -->
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Noted</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Norek</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Namrek</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Bank</th>
              <th scope="col" class="py-2.5 px-3 border-r border-b border-gray-200 bg-gray-50 sticky top-0 z-30 shadow-[0_1px_0_0_#e5e7eb]" style="top: 0px;">Big Region</th>
              <!-- Kolom Aksi (Sticky Right di pojok kanan) -->
              <th scope="col" class="py-2.5 px-3 text-center border-l border-b border-gray-200 bg-gray-50 sticky right-0 top-0 z-40 min-w-[195px] shadow-[-3px_0_6px_-2px_rgba(0,0,0,0.08),0_1px_0_0_#e5e7eb]" style="right: 0px; top: 0px;">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white" @click="onGridClick">
            <!-- Loading State -->
            <tr v-if="isLoading && items.length === 0">
              <td colspan="58" class="py-8 text-center text-gray-500">
                <div class="inline-flex items-center gap-2">
                  <RefreshCwIcon class="w-4 h-4 animate-spin text-gray-400" />
                  <span>Memuat data program...</span>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="items.length === 0">
              <td colspan="58" class="py-8 text-center text-gray-500">
                <div class="max-w-md mx-auto space-y-1.5 text-center">
                  <p class="font-medium text-gray-800 text-xs">Belum ada data program yang tersimpan.</p>
                  <p class="text-xs text-gray-500">
                    Klik tombol <strong>"Sinkronkan Sekarang"</strong> di atas untuk memuat data dari spreadsheet Anda.
                  </p>
                </div>
              </td>
            </tr>

            <!-- Data Rows (Exact Test Program aesthetic) -->
            <tr
              v-else
              v-for="(row, idx) in items"
              :key="row.id"
              :data-row-index="idx"
              @dblclick="onGridDblClick($event) || openEditModal(row)"
              class="group hover:bg-gray-50/70 text-gray-700 font-normal cursor-cell"
            >
              <!-- 1. No (Locked) -->
              <td class="py-2.5 px-3 text-center w-[50px] min-w-[50px] max-w-[50px] border-r border-gray-100 whitespace-nowrap sticky left-0 z-20 bg-white group-hover:bg-gray-50 transition-colors" style="left: 0px;">
                {{ (pagination.current_page - 1) * pagination.per_page + idx + 1 }}
              </td>

              <!-- 2. Nama Dealer (Locked) -->
              <td class="py-2.5 px-3 w-[180px] min-w-[180px] max-w-[180px] border-r border-gray-100 whitespace-normal sticky z-20 bg-white group-hover:bg-gray-50 transition-colors" style="left: 50px;">
                <div class="text-gray-700 break-words leading-snug" :title="row.dealer_name">{{ row.dealer_name || '-' }}</div>
              </td>

              <!-- 3. Program (Locked) -->
              <td class="py-2.5 px-3 w-[130px] min-w-[130px] max-w-[130px] border-r border-gray-100 whitespace-nowrap sticky z-20 bg-white group-hover:bg-gray-50 transition-colors" style="left: 230px;">
                <div class="truncate" :title="row.program">{{ row.program || '-' }}</div>
              </td>

              <!-- 4. Kode BT (Locked) -->
              <td class="py-2.5 px-3 w-[100px] min-w-[100px] max-w-[100px] border-r border-gray-100 whitespace-nowrap sticky z-20 bg-white group-hover:bg-gray-50 transition-colors" style="left: 360px;">
                <div class="truncate" :title="row.kode_bt">{{ row.kode_bt || '-' }}</div>
              </td>

              <!-- 5. Nama Program (Locked with divider shadow) -->
              <td class="py-2.5 px-3 w-[240px] min-w-[240px] max-w-[240px] border-r border-gray-100 whitespace-normal sticky z-20 bg-white group-hover:bg-gray-50 transition-colors shadow-[3px_0_6px_-2px_rgba(0,0,0,0.08)]" style="left: 460px;">
                <div class="text-gray-700 break-words leading-snug" :title="row.program_name">{{ row.program_name || '-' }}</div>
              </td>

              <!-- 6. Periode -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.periode || '-' }}
              </td>

              <!-- 8. Region -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.region || '-' }}
              </td>

              <!-- 9. No PO -->
              <td data-field="no_po" class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.no_po || '-' }}
              </td>

              <!-- 10. ID GS -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.id_gs || '-' }}
              </td>

              <!-- 12. Status DL -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.status_dl || '-' }}
              </td>

              <!-- 13. Sales Person -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.sales_person || '-' }}
              </td>

              <!-- 14. Telemarketing -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                <div>{{ row.telemarketing || '-' }}</div>
                <div v-if="row.telemarketing_phone" class="text-[11px] text-gray-500">{{ row.telemarketing_phone }}</div>
              </td>

              <!-- 15. Wajib Pajak -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.wajib_pajak || '-' }}
              </td>

              <!-- 16. TRF PPH -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.trf_pph || '-' }}
              </td>

              <!-- 17. Incentive -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap font-medium text-gray-900">
                {{ formatRupiah(row.incentive) }}
              </td>

              <!-- 18. DPP -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ formatRupiah(row.dpp) }}
              </td>

              <!-- 19. DPP Lain -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ formatRupiah(row.dpp_lain) }}
              </td>

              <!-- 20. PPN -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ formatRupiah(row.ppn) }}
              </td>

              <!-- 21. Nilai PPh -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ formatRupiah(row.nilai_pph) }}
              </td>

              <!-- 22. Net Pay -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap font-bold text-gray-950">
                {{ formatRupiah(row.net_pay) }}
              </td>

              <!-- 23. Cek Pajak Tarif PPh -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ formatRupiah(row.cek_pajak_tarif) }}
              </td>

              <!-- 24. Selisih -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap">
                <span v-if="row.selisih === 0 || row.selisih === '0' || row.selisih === 0.0" class="text-gray-400">0</span>
                <span v-else-if="row.selisih !== null && row.selisih !== undefined && row.selisih !== ''" class="font-medium text-rose-600">
                  {{ formatRupiah(row.selisih) }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </td>

              <!-- 25. Note PPh -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-700">
                <span v-if="row.note_pph === 'ok'" class="text-emerald-700 font-medium">ok</span>
                <span v-else-if="row.note_pph" class="text-amber-700">{{ row.note_pph }}</span>
                <span v-else class="text-gray-300">-</span>
              </td>

              <!-- 26. No Faktur Pajak -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                {{ row.no_faktur_pajak || '-' }}
              </td>

              <!-- 27. Ket Faktur Pajak -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ row.ket_faktur_pajak || '-' }}
              </td>

              <!-- 28. No PO/SJ -->
              <td data-field="no_po_sj" class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ row.no_po_sj || '-' }}
              </td>

              <!-- 29. No Transaksi -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ row.no_transaksi || '-' }}
              </td>

              <!-- 30. Tgl Input -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ row.tgl_input || '-' }}
              </td>

              <!-- 31. Tgl Share CN -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-800">
                {{ row.tgl_share_cn || '-' }}
              </td>

              <!-- 32. Lama Pending -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.lama_pending || '-' }}
              </td>

              <!-- 33. Keterangan -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[200px] truncate text-gray-700" :title="row.keterangan">
                {{ row.keterangan || '-' }}
              </td>

              <!-- 34. Cek Dokumen -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                <span
                  v-if="row.cek_dokumen"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-medium border inline-block',
                    row.cek_dokumen === 'LENGKAP' || row.cek_dokumen === 'OK'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-amber-50 text-amber-700 border-amber-200'
                  ]"
                >
                  {{ row.cek_dokumen }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </td>

              <!-- 35. Status Potong By Purchase -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                <span
                  v-if="row.status_potong_purchase"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-normal border inline-block',
                    row.status_potong_purchase === 'BISA DI POTONG'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : row.status_potong_purchase === 'SUDAH POTONG'
                      ? 'bg-sky-50 text-sky-700 border-sky-200'
                      : row.status_potong_purchase === 'DONE TRANSFER'
                      ? 'bg-violet-50 text-violet-700 border-violet-200'
                      : row.status_potong_purchase === 'BELUM BISA POTONG'
                      ? 'bg-rose-50 text-rose-700 border-rose-200'
                      : 'bg-gray-50 text-gray-600 border-gray-200'
                  ]"
                >
                  {{ row.status_potong_purchase }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </td>

              <!-- 36. Status Potong By AR -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                <span
                  v-if="row.status_potong_ar"
                  :class="[
                    'px-2 py-0.5 rounded text-xs font-normal border inline-block',
                    row.status_potong_ar.includes('DEALER SETUJU')
                      ? 'bg-sky-50 text-sky-700 border-sky-200'
                      : row.status_potong_ar === 'DONE'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : row.status_potong_ar.includes('PENDING')
                      ? 'bg-amber-50 text-amber-700 border-amber-200'
                      : 'bg-gray-50 text-gray-600 border-gray-200'
                  ]"
                >
                  {{ row.status_potong_ar }}
                </span>
                <span v-else class="text-gray-300">-</span>
              </td>

              <!-- 37. Tanggal Potong/TF -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.tgl_potong_tf || '-' }}
              </td>

              <!-- 38. No. UID -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.no_uid || '-' }}
              </td>

              <!-- 39. No. Pembayaran -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.no_pembayaran || '-' }}
              </td>

              <!-- 40. Tgl Input Bank PPh -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.tgl_input_bank_pph || '-' }}
              </td>

              <!-- 41. T/F -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.tf_status || '-' }}
              </td>

              <!-- 42. Tgl Proses -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.tgl_proses || '-' }}
              </td>

              <!-- 43. Tgl SJ -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.tgl_sj || '-' }}
              </td>

              <!-- 44. No. SJ -->
              <td data-field="no_sj" class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.no_sj || '-' }}
              </td>

              <!-- 45. Info Bank -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.info_bank || '-' }}
              </td>

              <!-- 46. Pending Potongan -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.pending_potongan || '-' }}
              </td>

              <!-- 47. NPWP -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.npwp || '-' }}
              </td>

              <!-- 48. Nama NPWP -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[180px] truncate text-gray-700" :title="row.nama_npwp">
                {{ row.nama_npwp || '-' }}
              </td>

              <!-- 49. Program 2 -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[180px] truncate text-gray-700" :title="row.program_2">
                {{ row.program_2 || '-' }}
              </td>

              <!-- 50. CN -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="isValidUrl(row.cn)"
                  :href="row.cn"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  CN
                </a>
                <span v-else-if="row.cn" class="text-xs text-gray-700">{{ row.cn }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 51. Agrement -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="isValidUrl(row.agrement)"
                  :href="row.agrement"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  Agr
                </a>
                <span v-else-if="row.agrement" class="text-xs text-gray-700">{{ row.agrement }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 52. Cek FP -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="isValidUrl(row.cek_fp)"
                  :href="row.cek_fp"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  FP
                </a>
                <span v-else-if="row.cek_fp" class="text-xs text-gray-700">{{ row.cek_fp }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 53. Cek Evidance -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <a
                  v-if="isValidUrl(row.cek_evidance)"
                  :href="row.cek_evidance"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-gray-700 underline hover:text-gray-900"
                >
                  Evid
                </a>
                <span v-else-if="row.cek_evidance" class="text-xs text-gray-700">{{ row.cek_evidance }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>

              <!-- 54. Noted -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[200px] truncate text-gray-700" :title="row.noted">
                {{ row.noted || '-' }}
              </td>

              <!-- 55. Norek -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.norek || '-' }}
              </td>

              <!-- 56. Namrek -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[180px] truncate text-gray-700" :title="row.namrek">
                {{ row.namrek || '-' }}
              </td>

              <!-- 57. Bank -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.bank || '-' }}
              </td>

              <!-- 58. Big Region -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-700">
                {{ row.big_region || '-' }}
              </td>

              <!-- Kolom Aksi (Sticky Right di pojok kanan) -->
              <td
                class="py-2.5 px-3 text-center border-l border-gray-100 whitespace-nowrap sticky right-0 z-20 bg-white group-hover:bg-gray-50 transition-colors shadow-[-3px_0_6px_-2px_rgba(0,0,0,0.08)] min-w-[195px]"
                style="right: 0px;"
              >
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    @click.stop="testMatchSingleRow(row)"
                    :disabled="reconcilingRowId === row.id"
                    class="h-7 px-2.5 inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-medium transition cursor-pointer shadow-2xs disabled:opacity-50"
                    title="Cek kecocokan data dengan Test Program"
                  >
                    <RefreshCwIcon v-if="reconcilingRowId === row.id" class="w-3 h-3 animate-spin text-gray-400" />
                    <EyeIcon v-else class="w-3.5 h-3.5 text-gray-500" />
                    <span>Lihat</span>
                  </button>

                  <button
                    type="button"
                    @click.stop="openEditModal(row)"
                    class="h-7 px-2.5 inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white hover:bg-gray-100 text-gray-800 text-xs font-medium transition cursor-pointer shadow-2xs"
                    title="Edit data program ini di web"
                  >
                    <PencilIcon class="w-3.5 h-3.5 text-gray-600" />
                    <span>Edit</span>
                  </button>

                  <button
                    v-if="row.status_potong_purchase === 'BISA DI POTONG'"
                    type="button"
                    @click.stop="sendWaToTelemarketing(row)"
                    :disabled="sendingWaId === row.id"
                    class="h-7 px-2 inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-medium transition cursor-pointer shadow-2xs disabled:opacity-50"
                    :title="row.telemarketing_notified_at ? `WA telemarketing terkirim ${formatDateTime(row.telemarketing_notified_at)}. Klik untuk kirim ulang.` : 'Kirim ke WhatsApp Telemarketing'"
                  >
                    <RefreshCwIcon v-if="sendingWaId === row.id" class="w-3 h-3 animate-spin text-emerald-600" />
                    <WhatsAppIcon v-else class="w-3.5 h-3.5 text-[#25D366]" />
                    <span>WA</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Sel terpilih, editor & fill handle (klik dua kali untuk mengisi, tarik kotak kecil ke bawah untuk mengisi baris berikutnya) -->
        <div
          v-if="selectionBox && !cellEditor.open"
          class="pointer-events-none absolute z-[25] ring-2 ring-inset ring-gray-900"
          :style="boxStyle(selectionBox)"
        ></div>
        <div
          v-if="fillBox"
          class="pointer-events-none absolute z-[25] border border-dashed border-gray-900 bg-gray-900/5"
          :style="boxStyle(fillBox)"
        ></div>
        <div
          v-if="selectionBox && !cellEditor.open"
          class="absolute z-[26] size-2 bg-gray-900 ring-2 ring-white cursor-crosshair"
          :style="{ top: `${selectionBox.top + selectionBox.height - 5}px`, left: `${selectionBox.left + selectionBox.width - 5}px` }"
          title="Tarik ke bawah untuk mengisi baris di bawahnya (tahan Ctrl untuk menyalin tanpa menambah angka)"
          @mousedown.prevent.stop="startFill"
        ></div>
        <input
          v-if="cellEditor.open && selectionBox"
          ref="cellEditorInput"
          v-model="cellEditor.text"
          type="text"
          class="absolute z-[26] min-w-[160px] px-3 text-xs text-gray-900 bg-white border-2 border-gray-900 outline-none shadow-sm"
          :style="boxStyle(selectionBox)"
          @keydown="onEditorKeydown"
          @blur="commitEdit"
        />
      </div>

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

    <!-- Modal Rekonsiliasi & Pencocokan Test Program -->
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
                  <h3 class="text-sm font-bold text-gray-900">Cocokkan dengan Test Program</h3>
                  <p class="text-[11px] text-gray-500">
                    Otomatisasi pencocokan identitas, finansial, dan pemindahan link dokumen
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
                  <li><strong>Pemindahan Dokumen:</strong> Link Dokumen (CN, Agreement, Faktur Pajak) dari Test Program otomatis disalin ke kolom <code>cn</code>, <code>agrement</code>, <code>cek_fp</code>.</li>
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
          class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/40 backdrop-blur-xs font-sans"
          @click.self="showTestModal = false"
        >
          <div class="w-full max-w-3xl bg-white rounded-xl shadow-xl border border-gray-200 flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-4 sm:px-6 pt-5 pb-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
              <div class="min-w-0">
                <h3 class="text-base font-semibold text-gray-950">Hasil Uji Kecocokan</h3>
                <p class="mt-0.5 text-xs text-gray-500 truncate">
                  {{ testRow?.dealer_name || '-' }}<template v-if="testRow?.kode_bt"> · {{ testRow.kode_bt }}</template> · {{ testRow?.program_name || '-' }}
                </p>
              </div>
              <button type="button" @click="showTestModal = false" class="p-1 -m-1 text-gray-400 hover:text-gray-700 cursor-pointer shrink-0" title="Tutup">
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <div class="px-4 sm:px-6 py-5 space-y-5 overflow-y-auto text-xs">
              <!-- Ringkasan -->
              <div class="space-y-1">
                <p class="flex items-center gap-2 text-sm font-medium text-gray-950">
                  <span class="w-2 h-2 rounded-full shrink-0" :class="testResult?.matched ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                  <span>{{ testResult?.matched ? `Cocok dengan Form Program #${testResult.details?.submission_id}` : 'Belum ada Form Program yang cocok' }}</span>
                </p>
                <p class="pl-4 text-gray-500">
                  Status potong <span class="text-gray-900">{{ testResult?.details?.status_potong_purchase || testRow?.status_potong_purchase || '-' }}</span>
                  <span class="mx-1.5 text-gray-300">·</span>
                  Cek dokumen <span class="text-gray-900">{{ testResult?.details?.cek_dokumen || testRow?.cek_dokumen || '-' }}</span>
                  <span class="mx-1.5 text-gray-300">·</span>
                  <span class="text-gray-900">{{ testResult?.details?.is_pkp ? 'PKP' : 'Non-PKP' }}</span><template v-if="testRow?.wajib_pajak && testRow.wajib_pajak !== '-'"> ({{ testRow.wajib_pajak }})</template>
                </p>
                <p v-if="testRow?.telemarketing_notified_at" class="pl-4 text-gray-500">
                  WA telemarketing terkirim <span class="text-gray-900">{{ formatDateTime(testRow.telemarketing_notified_at) }}</span>
                </p>
              </div>

              <!-- Perbandingan Data Program vs Form Program; di layar kecil tabel digeser di dalam kotaknya -->
              <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead class="border-b border-gray-200 text-gray-500">
                    <tr>
                      <th class="py-2 pr-4 w-[22%] font-medium"></th>
                      <th class="py-2 pr-4 w-[32%] font-medium">Data Program</th>
                      <th class="py-2 pr-4 w-[32%] font-medium">Form Program</th>
                      <th class="py-2 text-right font-medium">Hasil</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 text-gray-900">
                    <tr v-for="item in testComparisonRows" :key="item.label">
                      <td class="py-2.5 pr-4 text-gray-500">{{ item.label }}</td>
                      <td class="py-2.5 pr-4">{{ item.dataProgram || '-' }}</td>
                      <td class="py-2.5 pr-4">{{ item.form || '-' }}</td>
                      <td class="py-2.5 text-right whitespace-nowrap" :class="testResultClass(item.result)">{{ item.result }}</td>
                    </tr>
                    <tr v-for="doc in testDocumentRows" :key="doc.label">
                      <td class="py-2.5 pr-4 text-gray-500">{{ doc.label }}</td>
                      <td class="py-2.5 pr-4">
                        <a v-if="isValidUrl(doc.dataProgram)" :href="doc.dataProgram" target="_blank" rel="noopener noreferrer" class="underline hover:text-gray-600">Lihat berkas</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="py-2.5 pr-4">
                        <a v-if="isValidUrl(doc.form)" :href="doc.form" target="_blank" rel="noopener noreferrer" class="underline hover:text-gray-600">Lihat berkas</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="py-2.5 text-right whitespace-nowrap" :class="testResultClass(doc.result)">{{ doc.result }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p v-if="testRow?.match_note" class="text-gray-500 leading-relaxed break-words">
                <span class="text-gray-900">Hasil cocok:</span> {{ testRow.match_note }}
              </p>
            </div>

            <!-- Footer -->
            <div class="px-4 sm:px-6 py-3.5 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs shrink-0">
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="testMatchSingleRow(testRow, true)"
                  :disabled="reconcilingRowId === testRow?.id"
                  class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium transition cursor-pointer disabled:opacity-50"
                  title="Cocokkan ulang baris ini dengan Form Program"
                >
                  {{ reconcilingRowId === testRow?.id ? 'Menguji...' : 'Uji Ulang' }}
                </button>
                <button
                  type="button"
                  @click="openHistoryModal(testRow)"
                  class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium transition cursor-pointer"
                  title="Riwayat pencocokan baris ini"
                >
                  Riwayat
                </button>
              </div>
              <div class="ml-auto flex items-center gap-2">
                <button
                  type="button"
                  @click="showTestModal = false"
                  class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium transition cursor-pointer"
                >
                  Tutup
                </button>
                <button
                  v-if="testRow?.status_potong_purchase === 'BISA DI POTONG'"
                  type="button"
                  @click="sendWaToTelemarketing(testRow)"
                  :disabled="sendingWaId === testRow?.id"
                  class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white font-medium transition cursor-pointer disabled:opacity-50"
                >
                  <span class="sm:hidden">{{ sendingWaId === testRow?.id ? 'Mengirim...' : 'Kirim WA' }}</span>
                  <span class="hidden sm:inline">{{ sendingWaId === testRow?.id ? 'Mengirim...' : 'Kirim ke WA Telemarketing' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal Riwayat Pencocokan Test Program -->
    <ReconciliationHistoryModal
      v-model="showHistoryModal"
      :initial-data-program-id="historyInitialRowId"
      :initial-batch-id="historyInitialBatchId"
      :filtered-row-name="historyFilteredRowName"
    />

    <!-- Modal Pilihan Kontak Telemarketing -->
    <TelemarketingSelectModal
      :is-open="telemarketingModal.isOpen"
      :target-data="telemarketingModal.target"
      :is-submitting="isSendingTelemarketing"
      @close="telemarketingModal.isOpen = false"
      @send="handleConfirmSendTelemarketing"
    />

    <!-- Modal Atur Link Spreadsheet (per area) -->
    <div
      v-if="urlModal.isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl max-w-lg w-full p-5 shadow-xl border border-gray-200 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-200 pb-2.5">
          <h3 class="text-sm font-bold text-gray-950">Link Spreadsheet Data Program {{ area.label }}</h3>
          <button type="button" @click="urlModal.isOpen = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveUrlConfig" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-medium text-gray-900 mb-1">Link Google Spreadsheet</label>
            <input
              v-model="urlModal.url"
              type="url"
              placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=0"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
            <p class="text-[11px] text-gray-500 mt-1">Pastikan akses spreadsheet "Siapa saja yang memiliki link". Dipakai untuk Sinkronkan Sekarang.</p>
          </div>

          <div>
            <label class="block font-medium text-gray-900 mb-1">URL Google Apps Script Web App</label>
            <input
              v-model="urlModal.webappUrl"
              type="url"
              placeholder="https://script.google.com/macros/s/.../exec"
              class="w-full h-9 px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
            <p class="text-[11px] text-gray-500 mt-1">Dipakai untuk mengirim data draft & hasil pencocokan ke spreadsheet {{ area.label }}.</p>
          </div>

          <p v-if="urlModal.message" class="text-[11px]" :class="urlModal.success ? 'text-emerald-700' : 'text-red-600'">{{ urlModal.message }}</p>

          <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100">
            <button
              type="button"
              @click="urlModal.isOpen = false"
              class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="urlModal.isSaving"
              class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-xs font-medium cursor-pointer disabled:opacity-50"
            >
              {{ urlModal.isSaving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Edit Data Program -->
    <EditDataProgramModal
      :is-open="editModal.isOpen"
      :data-program="editModal.data"
      @close="editModal.isOpen = false"
      @saved="handleDataProgramSaved"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import AreaTabs from '@/components/AreaTabs.vue';
import WhatsAppIcon from '@/components/WhatsAppIcon.vue';
import TelemarketingSelectModal from '@/components/TelemarketingSelectModal.vue';
import { useArea } from '@/composables/useArea';
import EditDataProgramModal from '@/components/EditDataProgramModal.vue';
import { useSpreadsheetGrid } from '@/composables/useSpreadsheetGrid';
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
  Info as InfoIcon,
  ArrowRight as ArrowRightIcon,
  History as HistoryIcon,
  Eye as EyeIcon,
  Pencil as PencilIcon,
  Settings as SettingsIcon,
} from 'lucide-vue-next';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import ReconciliationHistoryModal from '@/components/ReconciliationHistoryModal.vue';

// ===== AREA (Jabar/Jateng) =====
const { area } = useArea();

// ===== LINK SPREADSHEET PER AREA =====
const urlModal = reactive({
  isOpen: false,
  isSaving: false,
  url: '',
  webappUrl: '',
  message: '',
  success: false,
});

const openUrlModal = async () => {
  showSpreadsheetDropdown.value = false;
  urlModal.message = '';
  urlModal.isOpen = true;
  try {
    const res = await axios.get('/api/data-program/url');
    urlModal.url = res.data?.url || '';
    urlModal.webappUrl = res.data?.webapp_url || '';
  } catch {
    // Keep current values
  }
};

const saveUrlConfig = async () => {
  urlModal.isSaving = true;
  urlModal.message = '';
  try {
    const res = await axios.post('/api/data-program/url', {
      url: urlModal.url.trim() || null,
      webapp_url: urlModal.webappUrl.trim() || null,
    });
    urlModal.success = true;
    urlModal.message = res.data?.message || 'Link spreadsheet berhasil disimpan.';
    googleSheetUrl.value = res.data?.url || '';
    setTimeout(() => {
      urlModal.isOpen = false;
    }, 1000);
  } catch (err) {
    urlModal.success = false;
    urlModal.message = err.response?.data?.message || 'Gagal menyimpan link spreadsheet.';
  } finally {
    urlModal.isSaving = false;
  }
};

const isValidUrl = (url) => {
  if (!url) return false;
  return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'));
};

// Link bawaan hanya untuk Jawa Barat; area lain diatur lewat "Atur Link Spreadsheet"
const googleSheetUrl = ref(area.value.value === 'jabar' ? 'https://docs.google.com/spreadsheets/d/1AFEAxjycq50heaxJeAH9YjpVWFs3GeprplJDOJMzjSU/edit?gid=241795572#gid=241795572' : '');

const items = ref([]);
const isLoading = ref(false);
const isSyncing = ref(false);
const isExporting = ref(false);
const syncMessage = ref('');
const syncError = ref(false);
const autoRefresh = ref(false);
let autoRefreshTimer = null;
const lastUpdatedText = ref('');

// Modal Edit Data Program
const editModal = reactive({
  isOpen: false,
  data: null,
});

const openEditModal = (row) => {
  editModal.data = { ...row };
  editModal.isOpen = true;
};

const handleDataProgramSaved = (updatedRow) => {
  if (!updatedRow?.id) return;
  const index = items.value.findIndex((item) => item.id === updatedRow.id);
  if (index !== -1) {
    items.value[index] = { ...items.value[index], ...updatedRow };
  }
  syncMessage.value = `Data program #${updatedRow.id} (${updatedRow.dealer_name || ''}) berhasil diperbarui!`;
  syncError.value = false;
  setTimeout(() => {
    if (syncMessage.value.startsWith('Data program #')) {
      syncMessage.value = '';
    }
  }, 4000);
};

// Kolom No PO, No PO/SJ & No SJ diisi langsung di tabel seperti spreadsheet: klik dua kali untuk mengisi, tarik fill handle ke bawah
const gridContainer = ref(null);

const saveCells = async (field, changes) => {
  const previousValues = changes.map(({ row }) => row[field]);
  changes.forEach(({ row, value }) => { row[field] = value; });

  try {
    const res = await axios.put('/api/data-program/bulk', {
      field,
      rows: changes.map(({ row, value }) => ({ id: row.id, value })),
    });
    (res.data?.data || []).forEach((updated) => {
      const item = items.value.find((row) => row.id === updated.id);
      if (item) Object.assign(item, updated);
    });
    if (changes.length > 1) {
      syncError.value = false;
      syncMessage.value = res.data?.message || `${changes.length} baris berhasil diisi.`;
    }
  } catch (err) {
    changes.forEach(({ row }, index) => { row[field] = previousValues[index]; });
    syncError.value = true;
    syncMessage.value = 'Gagal menyimpan isian: ' + (err.response?.data?.message || err.message);
  } finally {
    nextTick(refreshBoxes);
  }
};

const {
  cellEditor,
  cellEditorInput,
  selectionBox,
  fillBox,
  boxStyle,
  refreshBoxes,
  onGridClick,
  onGridDblClick,
  onEditorKeydown,
  commitEdit,
  startFill,
} = useSpreadsheetGrid({
  container: gridContainer,
  rows: items,
  save: saveCells,
});

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
const formatDateTime = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? String(value)
    : `${date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })} ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
};

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

// Baris perbandingan Data Program vs Form Program di modal Hasil Uji Kecocokan
const testComparisonRows = computed(() => {
  const details = testResult.value?.details || {};
  const form = details.submission || null;
  const row = testRow.value || {};
  const criteria = details.criteria_match || {};
  const result = (matched) => (!form ? '-' : matched ? 'Cocok' : 'Beda');

  let amountResult = '-';
  if (form && (details.financial_match || criteria.financial)) amountResult = 'Cocok';
  else if (form && details.selisih) amountResult = `Selisih ${formatRupiah(details.selisih)}`;

  return [
    { label: 'Region', dataProgram: row.region || row.big_region, form: form?.region, result: result(criteria.region) },
    {
      label: 'Kode BT / ID Realme',
      dataProgram: row.kode_bt,
      form: form?.id_real,
      result: !form ? '-' : criteria.kode_bt === true ? 'Cocok' : criteria.kode_bt === false ? 'Beda' : 'Opsional',
    },
    { label: 'Dealer', dataProgram: row.dealer_name, form: form?.dealer_name, result: result(criteria.dealer) },
    { label: 'Program', dataProgram: row.program_name, form: form?.program_name, result: result(criteria.program) },
    {
      label: 'Net Pay',
      dataProgram: formatRupiah(row.net_pay || row.dpp),
      form: form ? formatRupiah(form.net_pay || form.dpp) : null,
      result: amountResult,
    },
  ];
});

const testDocumentRows = computed(() => {
  const details = testResult.value?.details || {};
  const form = details.submission || {};
  const row = testRow.value || {};

  return [
    { label: 'Credit Note', dataProgram: row.cn, form: form.credit_note_url, required: true },
    { label: 'Agreement', dataProgram: row.agrement, form: form.agreement_url, required: true },
    { label: 'Faktur Pajak', dataProgram: row.cek_fp, form: form.tax_invoice_url, required: !!details.is_pkp },
  ].map((doc) => ({
    ...doc,
    result: isValidUrl(doc.dataProgram) || isValidUrl(doc.form) ? 'Ada' : doc.required ? 'Kosong' : 'Tidak wajib',
  }));
});

const testResultClass = (result) => {
  if (result === 'Cocok' || result === 'Ada') return 'text-emerald-700';
  if (result === 'Beda' || result === 'Kosong' || String(result).startsWith('Selisih')) return 'text-rose-600';
  return 'text-gray-400';
};

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

// Modal state for choosing Telemarketing recipient
const telemarketingModal = reactive({
  isOpen: false,
  target: null,
});
const isSendingTelemarketing = ref(false);
const sendingWaId = ref(null);

// Send WhatsApp notification to Telemarketing (opens contact selection modal)
const sendWaToTelemarketing = (row) => {
  if (!row) return;
  telemarketingModal.target = row;
  telemarketingModal.isOpen = true;
};

// Handle confirmed send from TelemarketingSelectModal
const handleConfirmSendTelemarketing = async ({ phone, telemarketing_name, target }) => {
  if (!target) return;
  isSendingTelemarketing.value = true;
  sendingWaId.value = target.id;

  try {
    const res = await axios.post(`/api/data-program/${target.id}/send-wa-telemarketing`, {
      phone,
      telemarketing_name,
    });
    alert(res.data.message || `Notifikasi klaim "${target.dealer_name || target.kode_bt}" berhasil dikirim ke WhatsApp Telemarketing!`);
    telemarketingModal.isOpen = false;
    target.telemarketing_notified_at = res.data.telemarketing_notified_at || new Date().toISOString();
    if (telemarketing_name) {
      target.telemarketing = telemarketing_name;
    }
  } catch (err) {
    alert('Gagal mengirim WhatsApp ke Telemarketing: ' + (err.response?.data?.message || err.message));
  } finally {
    isSendingTelemarketing.value = false;
    sendingWaId.value = null;
  }
};

onMounted(async () => {
  fetchData(1);
  try {
    const res = await axios.get('/api/data-program/url');
    if (res.data?.url) {
      googleSheetUrl.value = res.data.url;
    }
  } catch {
    // Keep default URL on error
  }
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
