<template>
  <div class="space-y-4">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Drafts</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">List</span>
    </div>

    <AreaTabs />

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Draft Invoice</h1>
        <p class="text-xs text-gray-500 mt-0.5">Kelola dan terbitkan draft invoice yang diimpor dari Excel.</p>
      </div>

      <!-- Tombol aksi; di layar kecil tiga tombol sama lebar dengan label pendek -->
      <div class="grid grid-cols-3 sm:flex sm:items-center gap-2">
        <template v-if="selectedIds.length > 0">
          <button
            @click="askGenerateSelected"
            :disabled="generatingBatch || pushingBatch"
            :class="headerButtonClass"
            :title="`Terbitkan ${selectedIds.length} draft terpilih menjadi invoice`"
          >
            <span class="sm:hidden">{{ generatingBatch ? 'Proses...' : `Generate (${selectedIds.length})` }}</span>
            <span class="hidden sm:inline">{{ generatingBatch ? 'Menerbitkan...' : `Generate (${selectedIds.length}) Terpilih` }}</span>
          </button>
          <button
            @click="askPushSelectedToDataProgram"
            :disabled="pushingBatch || generatingBatch"
            :class="headerButtonClass"
            :title="`Kirim ${selectedIds.length} draft terpilih ke Data Program & Google Spreadsheet`"
          >
            <span class="sm:hidden">{{ pushingBatch ? 'Mengirim...' : `Kirim DP (${selectedIds.length})` }}</span>
            <span class="hidden sm:inline">{{ pushingBatch ? 'Mengirim...' : `Kirim ke Data Program (${selectedIds.length})` }}</span>
          </button>
          <button @click="selectedIds = []" :class="headerButtonClass" title="Batalkan pilihan">
            <span class="sm:hidden">Batal</span><span class="hidden sm:inline">Batal Pilih</span>
          </button>
        </template>

        <button
          v-else
          @click="askGenerateAll"
          :disabled="generatingAll"
          :class="headerButtonClass"
          title="Terbitkan invoice untuk semua draft yang berstatus Ready"
        >
          <span class="sm:hidden">{{ generatingAll ? 'Proses...' : 'Generate' }}</span>
          <span class="hidden sm:inline">{{ generatingAll ? 'Menerbitkan...' : 'Generate Semua Invoice' }}</span>
        </button>

        <button @click="showImportModal = true" :class="headerButtonClass">
          <span class="sm:hidden">Import</span><span class="hidden sm:inline">Import Excel</span>
        </button>

        <button @click="showCreateModal = true" :class="headerButtonClass">
          <span class="sm:hidden">Tambah</span><span class="hidden sm:inline">Tambah Manual</span>
        </button>
      </div>
    </div>

    <!-- Alert -->
    <Alert v-if="alertMessage" :variant="alertSuccess ? 'default' : 'destructive'">
      <CheckCircleIcon v-if="alertSuccess" class="h-4 w-4" />
      <AlertCircleIcon v-else class="h-4 w-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ alertMessage }}</span>
        <button @click="alertMessage = null" class="ml-4 text-xs opacity-60 hover:opacity-100 cursor-pointer">&times;</button>
      </AlertDescription>
    </Alert>

    <!-- Filament Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Toolbar (Search & Filter like Filament) -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Left: Quick Selection Info -->
        <div class="flex items-center gap-2 text-xs" :class="selectedIds.length === 0 && 'hidden sm:flex'">
          <span v-if="selectedIds.length > 0" class="font-medium text-gray-900 bg-gray-100 px-2.5 py-1 rounded-md">
            {{ selectedIds.length }} draft terpilih
          </span>
          <span v-else class="text-gray-400 text-xs">
            Daftar draft invoice hasil import Excel
          </span>
        </div>

        <!-- Right: Direct Filter Dropdowns, Search Box & Filter Popover; di layar kecil cari di atas, wilayah & program berdampingan -->
        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:flex-nowrap w-full sm:w-auto">
          <!-- Wilayah Filter Dropdown (Kaya Pilih Wilayah) -->
          <div class="relative sm:min-w-[135px]" :class="programOptions.length === 0 && 'col-span-2'">
            <select
              v-model="filters.region"
              @change="fetchDrafts(1)"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="">Semua Wilayah</option>
              <option v-for="r in regionOptions" :key="r" :value="r">{{ r }}</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <!-- Program Filter Dropdown (Kaya Pilih Program) -->
          <div class="relative sm:min-w-[150px]" v-if="programOptions.length > 0">
            <select
              v-model="filters.program"
              @change="fetchDrafts(1)"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option value="">Semua Program</option>
              <option v-for="p in programOptions" :key="p" :value="p">{{ p }}</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <!-- Search box & Filter Popover -->
          <div class="col-span-2 order-first sm:order-none flex items-center gap-2">
          <div class="relative flex-1 sm:w-60">
            <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="filters.search"
              @input="debounceFetch"
              type="text"
              placeholder="Search..."
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
                title="Buka Filter Tambahan"
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
            <PopoverContent class="w-72 p-3 space-y-3" align="end">
              <div class="text-xs font-semibold text-gray-900 border-b border-gray-100 pb-2 flex items-center justify-between">
                <span>Filter Draft</span>
                <button
                  v-if="activeFilterCount > 0"
                  @click="resetFilters"
                  class="text-[11px] font-normal text-rose-600 hover:underline cursor-pointer"
                >
                  Reset
                </button>
              </div>

              <!-- Wilayah Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Wilayah</label>
                <select
                  v-model="filters.region"
                  @change="fetchDrafts(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Wilayah</option>
                  <option v-for="r in regionOptions" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>

              <!-- Program Filter -->
              <div class="space-y-1" v-if="programOptions.length > 0">
                <label class="text-[11px] font-medium text-gray-700">Nama Program</label>
                <select
                  v-model="filters.program"
                  @change="fetchDrafts(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Program</option>
                  <option v-for="p in programOptions" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>

              <!-- Tipe Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tipe Invoice</label>
                <select
                  v-model="filters.invoice_type"
                  @change="fetchDrafts(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Tipe</option>
                  <option value="DSA">DSA</option>
                  <option value="NPS FL">NPS FL</option>
                  <option value="REGULAR">REGULAR</option>
                </select>
              </div>

              <!-- Status Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Status</label>
                <select
                  v-model="filters.status"
                  @change="fetchDrafts(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Status</option>
                  <option value="ready">Ready</option>
                  <option value="error">Error</option>
                  <option value="invoiced">Invoiced</option>
                </select>
              </div>

              <!-- Date Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tanggal</label>
                <input
                  v-model="filters.date"
                  @change="fetchDrafts(1)"
                  type="date"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                />
              </div>
            </PopoverContent>
          </Popover>
          </div>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per draft -->
      <div class="md:hidden">
        <div v-if="loading" class="py-8 text-center text-xs text-gray-500">Memuat data draft...</div>
        <div v-else-if="drafts.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">
          Belum ada data draft. Silakan klik tombol <strong>Import</strong>.
        </div>
        <template v-else>
          <label v-if="selectableDrafts.length > 0" class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-3 text-xs text-gray-600 cursor-pointer">
            <input
              type="checkbox"
              :checked="isAllSelected"
              :indeterminate="isIndeterminate"
              @change="toggleSelectAll"
              class="rounded border-gray-300 text-gray-900 focus:ring-0 w-4 h-4 cursor-pointer"
            />
            <span>Pilih semua draft Ready</span>
          </label>

          <ul class="divide-y divide-gray-100">
            <li
              v-for="draft in drafts"
              :key="draft.id"
              class="p-4 flex gap-3 text-xs text-gray-700"
              :class="selectedIds.includes(draft.id) && 'bg-gray-50'"
            >
              <input
                type="checkbox"
                :value="draft.id"
                v-model="selectedIds"
                :disabled="draft.status !== 'ready'"
                class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-0 w-4 h-4 cursor-pointer disabled:opacity-30 shrink-0"
              />
              <div class="flex-1 min-w-0 space-y-2">
                <div class="flex items-start justify-between gap-3">
                  <router-link :to="`/drafts/${draft.id}`" class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ draft.dealer_name || '-' }}</p>
                    <p class="mt-0.5 text-gray-500">{{ draft.dealer_code || '-' }} · {{ draft.invoice_type || '-' }}</p>
                  </router-link>
                  <div class="text-right shrink-0">
                    <span class="inline-flex items-center gap-1.5 text-gray-900">
                      <span
                        class="w-1.5 h-1.5 rounded-full"
                        :class="draft.status === 'invoiced' ? 'bg-emerald-500' : draft.status === 'ready' ? 'bg-amber-500' : 'bg-rose-500'"
                      ></span>
                      {{ draft.status === 'invoiced' ? 'Invoiced' : draft.status === 'ready' ? 'Ready' : 'Error' }}
                    </span>
                    <p v-if="draft.data_program" class="mt-0.5 text-[11px] text-gray-500">Masuk Data Program</p>
                  </div>
                </div>

                <p class="text-gray-500 truncate">{{ draft.customer_name || '-' }} · {{ draft.invoice_date || '-' }}</p>

                <div class="flex items-end justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-gray-950">{{ formatCurrency(draft.netpay) }}</p>
                    <p class="text-gray-500">Support {{ formatCurrency(draft.support_amount) }}</p>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button
                      v-if="draft.status === 'ready'"
                      type="button"
                      @click="askGenerateSingle(draft)"
                      :disabled="generatingId === draft.id"
                      class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer disabled:opacity-50"
                    >
                      {{ generatingId === draft.id ? 'Proses...' : 'Generate' }}
                    </button>
                    <button
                      v-else-if="draft.status === 'error'"
                      type="button"
                      @click="validateDraft(draft.id)"
                      class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                    >
                      Validasi
                    </button>
                    <router-link
                      v-else-if="draft.status === 'invoiced' && draft.invoice"
                      :to="`/invoices/${draft.invoice.id}`"
                      class="h-8 px-2.5 inline-flex items-center rounded-lg border border-gray-300 bg-white text-gray-700 font-medium"
                    >
                      Invoice
                    </router-link>
                    <button
                      v-if="draft.status === 'invoiced'"
                      type="button"
                      @click="askPushSingleToDataProgram(draft)"
                      :disabled="pushingId === draft.id"
                      class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer disabled:opacity-50"
                    >
                      {{ pushingId === draft.id ? 'Mengirim...' : 'Data Program' }}
                    </button>
                    <button
                      v-if="draft.status !== 'invoiced'"
                      type="button"
                      @click="askDeleteDraft(draft)"
                      class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-red-600 font-medium cursor-pointer"
                    >
                      Hapus
                    </button>
                  </div>
                </div>
              </div>
            </li>
          </ul>

          <div class="px-4 py-3 border-t border-gray-200 bg-gray-50/80 flex items-center justify-between gap-3 text-xs">
            <span class="font-semibold text-gray-900">Total</span>
            <span class="text-right">
              <span class="font-semibold text-gray-950">{{ formatCurrency(totalNetpay) }}</span>
              <span class="block text-gray-500">Support {{ formatCurrency(totalSupport) }}</span>
            </span>
          </div>
        </template>
      </div>

      <!-- Table: Styled identically to Test Program for clean spreadsheet look -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th scope="col" class="py-2.5 px-3 text-center w-[44px] border-r border-gray-200">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isIndeterminate"
                  @change="toggleSelectAll"
                  :disabled="selectableDrafts.length === 0"
                  class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                  title="Pilih semua draft Ready di halaman ini"
                />
              </th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                  Dealer Code
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                  Dealer Name
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Customer</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Tipe</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                  Tanggal
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer justify-end w-full">
                  Support
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer justify-end w-full">
                  Netpay
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 text-center">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="loading">
              <td colspan="10" class="py-8 text-center text-gray-500">
                <div class="inline-flex items-center gap-2">
                  <span class="inline-block w-4 h-4 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
                  <span>Memuat data draft...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="drafts.length === 0">
              <td colspan="10" class="py-8 text-center text-gray-500">
                Belum ada data draft. Silakan klik tombol <strong>Import Excel</strong>.
              </td>
            </tr>

            <tr
              v-else
              v-for="draft in drafts"
              :key="draft.id"
              :class="selectedIds.includes(draft.id) ? 'bg-gray-50/90' : 'hover:bg-gray-50/70'"
              class="text-gray-700 font-normal transition"
            >
              <!-- Checkbox Selection -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <input
                  type="checkbox"
                  :value="draft.id"
                  v-model="selectedIds"
                  :disabled="draft.status !== 'ready'"
                  class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                  :title="draft.status === 'ready' ? 'Pilih draft ini untuk digenerate' : (draft.status === 'invoiced' ? 'Invoice sudah diterbitkan' : 'Draft error tidak dapat digenerate')"
                />
              </td>

              <!-- Dealer Code -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                <router-link
                  :to="`/drafts/${draft.id}`"
                  class="font-semibold text-gray-900 text-xs hover:underline"
                >
                  {{ draft.dealer_code || '-' }}
                </router-link>
              </td>

              <!-- Dealer Name -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[200px] truncate text-gray-800" :title="draft.dealer_name">
                {{ draft.dealer_name || '-' }}
              </td>

              <!-- Customer -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[160px] truncate text-gray-600" :title="draft.customer_name">
                {{ draft.customer_name || '-' }}
              </td>

              <!-- Tipe -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-600">
                {{ draft.invoice_type || '-' }}
              </td>

              <!-- Status -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <span
                    class="w-1.5 h-1.5 rounded-full shrink-0"
                    :class="draft.status === 'invoiced' ? 'bg-emerald-500' : draft.status === 'ready' ? 'bg-amber-500' : 'bg-rose-500'"
                  ></span>
                  <span class="text-xs text-gray-900">
                    {{ draft.status === 'invoiced' ? 'Invoiced' : draft.status === 'ready' ? 'Ready' : 'Error' }}
                  </span>
                </div>
                <p
                  v-if="draft.data_program"
                  class="mt-0.5 pl-3 text-[11px] text-gray-500"
                  title="Sudah tersinkron di Data Program & Google Spreadsheet"
                >
                  Masuk Data Program
                </p>
              </td>

              <!-- Tanggal -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-600">
                {{ draft.invoice_date || '-' }}
              </td>

              <!-- Support Amount -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap text-gray-700 font-medium">
                {{ formatCurrency(draft.support_amount) }}
              </td>

              <!-- Netpay -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap font-bold text-gray-950">
                {{ formatCurrency(draft.netpay) }}
              </td>

              <!-- Action Buttons -->
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                <div class="inline-flex items-center rounded-md border border-gray-200 bg-white shadow-2xs divide-x divide-gray-200 overflow-hidden">
                  <!-- 1. View Button -->
                  <router-link
                    :to="`/drafts/${draft.id}`"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                    title="Lihat Detail Draft"
                  >
                    <EyeIcon class="w-3.5 h-3.5 text-gray-600" />
                  </router-link>

                  <!-- 2. Generate Action (Ready) -->
                  <button
                    v-if="draft.status === 'ready'"
                    type="button"
                    @click="askGenerateSingle(draft)"
                    :disabled="generatingId === draft.id"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer disabled:opacity-50"
                    title="Generate Invoice"
                  >
                    <span v-if="generatingId === draft.id" class="inline-block w-3.5 h-3.5 border-2 border-gray-400 border-t-gray-900 rounded-full animate-spin shrink-0"></span>
                    <CheckSquareIcon v-else class="w-3.5 h-3.5 text-gray-600" />
                  </button>

                  <!-- 3. Validasi Action (Error) -->
                  <button
                    v-else-if="draft.status === 'error'"
                    type="button"
                    @click="validateDraft(draft.id)"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                    title="Validasi Ulang Rumus Draft"
                  >
                    <RefreshCwIcon class="w-3.5 h-3.5 text-gray-600" />
                  </button>

                  <!-- 4. Invoiced Action (Invoiced) -->
                  <router-link
                    v-else-if="draft.status === 'invoiced' && draft.invoice"
                    :to="`/invoices/${draft.invoice.id}`"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                    :title="`Lihat Invoice (${draft.invoice.invoice_number})`"
                  >
                    <FileTextIcon class="w-3.5 h-3.5 text-gray-600" />
                  </router-link>

                  <!-- 5. Kirim ke Data Program Action (setelah invoice diterbitkan) -->
                  <button
                    v-if="draft.status === 'invoiced'"
                    type="button"
                    @click="askPushSingleToDataProgram(draft)"
                    :disabled="pushingId === draft.id"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer disabled:opacity-50"
                    :title="draft.data_program ? 'Sudah di Data Program (Klik untuk perbarui)' : 'Kirim ke Data Program & Google Spreadsheet'"
                  >
                    <span v-if="pushingId === draft.id" class="inline-block w-3.5 h-3.5 border-2 border-gray-400 border-t-gray-900 rounded-full animate-spin shrink-0"></span>
                    <TableIcon v-else :class="draft.data_program ? 'text-emerald-600' : 'text-gray-600'" class="w-3.5 h-3.5" />
                  </button>

                  <!-- 6. Delete Action (Draft not yet invoiced) -->
                  <button
                    v-if="draft.status !== 'invoiced'"
                    type="button"
                    @click="askDeleteDraft(draft)"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                    title="Hapus Draft"
                  >
                    <Trash2Icon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>

          <tfoot v-if="drafts.length > 0" class="border-t border-gray-200 bg-gray-50/80 font-medium">
            <tr>
              <td colspan="7" class="py-2.5 px-3 text-xs font-semibold text-gray-900 border-r border-gray-200">
                Total
              </td>
              <td class="py-2.5 px-3 text-right whitespace-nowrap text-xs text-gray-800 border-r border-gray-200 font-medium">
                {{ formatCurrency(totalSupport) }}
              </td>
              <td class="py-2.5 px-3 text-right whitespace-nowrap text-xs font-bold text-gray-950 border-r border-gray-200">
                {{ formatCurrency(totalNetpay) }}
              </td>
              <td class="py-2.5 px-3"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Filament Pagination Footer -->
      <FilamentPagination
        :total="pagination.total"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        @page-change="fetchDrafts"
        @per-page-change="(p) => { pagination.per_page = p; fetchDrafts(1); }"
      />
    </div>

    <!-- Import Modal -->
    <ImportModal
      :is-open="showImportModal"
      @close="showImportModal = false"
      @imported="fetchDrafts(1)"
    />

    <!-- Create Draft Modal (Tambah Manual) -->
    <CreateDraftModal
      :is-open="showCreateModal"
      @close="showCreateModal = false"
      @created="onDraftCreated"
    />

    <!-- Confirmation Modal -->
    <ConfirmModal
      v-model="confirmState.show"
      :title="confirmState.title"
      :message="confirmState.message"
      :confirm-text="confirmState.confirmText"
      :loading="confirmState.loading"
      icon="warning"
      @confirm="onConfirmAction"
    />

    <!-- Generate Invoice Modal with Bill To Selection -->
    <GenerateInvoiceModal
      v-model="generateModalState.show"
      :title="generateModalState.title"
      :subtitle="generateModalState.subtitle"
      :target-info="generateModalState.targetInfo"
      :confirm-text="generateModalState.confirmText"
      :loading="generateModalState.loading"
      @confirm="onGenerateConfirm"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import AreaTabs from '@/components/AreaTabs.vue';
import { parseDate } from '@internationalized/date';
import {
  CalendarIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  Eye as EyeIcon,
  CheckSquare as CheckSquareIcon,
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  Search as SearchIcon,
  Filter as FilterIcon,
  RefreshCw as RefreshCwIcon,
  FileText as FileTextIcon,
  Trash2 as Trash2Icon,
  Table as TableIcon,
} from '@lucide/vue';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useAutoRefresh, createRequestTracker } from '@/composables/useAutoRefresh';
import ImportModal from '@/components/ImportModal.vue';
import CreateDraftModal from '@/components/CreateDraftModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import GenerateInvoiceModal from '@/components/GenerateInvoiceModal.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';

// Tombol header: tanpa ikon, satu gaya untuk semua aksi
const headerButtonClass = 'h-9 px-2 sm:px-3.5 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs disabled:opacity-50 transition cursor-pointer whitespace-nowrap';

const drafts = ref([]);
const loading = ref(false);
const showImportModal = ref(false);
const showCreateModal = ref(false);
const isFilterOpen = ref(false);
const selectedIds = ref([]);
const generatingId = ref(null);
const generatingBatch = ref(false);
const generatingAll = ref(false);
const pushingId = ref(null);
const pushingBatch = ref(false);
const alertMessage = ref(null);
const alertSuccess = ref(true);

const confirmState = reactive({
  show: false,
  title: '',
  message: '',
  confirmText: '',
  loading: false,
  action: null,
});

const onConfirmAction = async () => {
  if (confirmState.action) {
    await confirmState.action();
  }
};

const generateModalState = reactive({
  show: false,
  title: 'Terbitkan Invoice',
  subtitle: '',
  targetInfo: '',
  confirmText: 'Ya, Terbitkan Invoice',
  loading: false,
  action: null,
});

const onGenerateConfirm = async (billTo) => {
  if (generateModalState.action) {
    await generateModalState.action(billTo);
  }
};

const selectableDrafts = computed(() => {
  return drafts.value.filter(d => d.status === 'ready');
});

const isAllSelected = computed(() => {
  return selectableDrafts.value.length > 0 &&
    selectableDrafts.value.every(d => selectedIds.value.includes(d.id));
});

const isIndeterminate = computed(() => {
  const selectedCount = selectableDrafts.value.filter(d => selectedIds.value.includes(d.id)).length;
  return selectedCount > 0 && selectedCount < selectableDrafts.value.length;
});

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    const newIds = new Set([...selectedIds.value, ...selectableDrafts.value.map(d => d.id)]);
    selectedIds.value = Array.from(newIds);
  } else {
    const selectableIdSet = new Set(selectableDrafts.value.map(d => d.id));
    selectedIds.value = selectedIds.value.filter(id => !selectableIdSet.has(id));
  }
};

const regionOptions = [
  'BIG BANDUNG',
  'BIG KARAWANG',
  'BIG TASIK',
  'BIG CIREBON',
  'JABODETABEK',
  'JAWA BARAT',
];

const programOptions = ref([]);

const fetchProgramOptions = async () => {
  try {
    const res = await axios.get('/api/programs/options');
    if (res.data?.names?.length) {
      programOptions.value = res.data.names;
    }
  } catch (e) {
    // Keep fallback
  }
};

const filters = reactive({
  search: '',
  region: '',
  program: '',
  invoice_type: '',
  status: '',
  date: '',
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.region) count++;
  if (filters.program) count++;
  if (filters.invoice_type) count++;
  if (filters.status) count++;
  if (filters.date) count++;
  return count;
});

const selectedCalendarDate = computed(() => {
  if (!filters.date) return undefined;
  try { return parseDate(filters.date); } catch { return undefined; }
});

const onDateSelect = (val) => {
  if (!val) { filters.date = ''; fetchDrafts(1); return; }
  filters.date = val.toString();
  fetchDrafts(1);
};

const formatDateDisplay = (dateStr) => {
  if (!dateStr) return '';
  try {
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateStr));
  } catch { return dateStr; }
};

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});

const totalSupport = computed(() => {
  return drafts.value.reduce((acc, d) => acc + (Number(d.support_amount) || 0), 0);
});

const totalNetpay = computed(() => {
  return drafts.value.reduce((acc, d) => acc + (Number(d.netpay) || 0), 0);
});

let debounceTimeout = null;
const debounceFetch = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchDrafts(1);
  }, 300);
};

const startDraftsRequest = createRequestTracker();

const fetchDrafts = async (page = 1, { silent = false } = {}) => {
  const isLatest = startDraftsRequest();
  if (!silent) loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      ...filters,
    };
    const res = await axios.get('/api/drafts', { params });
    if (!isLatest()) return;
    drafts.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.per_page = res.data.per_page;
    pagination.total = res.data.total;
  } catch (err) {
    console.error('Error fetching drafts', err);
  } finally {
    if (!silent && isLatest()) loading.value = false;
  }
};

// Daftar draft dimuat ulang otomatis; berhenti selama modal terbuka atau generate / kirim ulang berjalan
useAutoRefresh(() => fetchDrafts(pagination.current_page, { silent: true }), {
  isPaused: () => loading.value || showImportModal.value || showCreateModal.value || confirmState.show || generateModalState.show
    || generatingId.value !== null || generatingBatch.value || generatingAll.value || pushingId.value !== null || pushingBatch.value,
});

const resetFilters = () => {
  filters.search = '';
  filters.region = '';
  filters.program = '';
  filters.invoice_type = '';
  filters.status = '';
  filters.date = '';
  isFilterOpen.value = false;
  fetchDrafts(1);
};

const validateDraft = async (id) => {
  try {
    const res = await axios.post(`/api/drafts/${id}/validate`);
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal validasi draft.';
    alertSuccess.value = false;
  }
};

const askGenerateSingle = (draft) => {
  if (generatingId.value === draft.id) return;
  generateModalState.title = 'Terbitkan Invoice';
  generateModalState.subtitle = 'Pilih pihak Bill To untuk dicantumkan pada lembar invoice:';
  generateModalState.targetInfo = `${draft.dealer_name || draft.dealer_code} (${draft.invoice_type || 'Invoice'})`;
  generateModalState.confirmText = 'Ya, Terbitkan Invoice';
  generateModalState.action = async (billTo) => {
    generateModalState.loading = true;
    try {
      await executeGenerateSingle(draft.id, billTo);
      generateModalState.show = false;
    } finally {
      generateModalState.loading = false;
    }
  };
  generateModalState.show = true;
};

const executeGenerateSingle = async (draftId, billTo) => {
  generatingId.value = draftId;
  alertMessage.value = null;

  try {
    const res = await axios.post(`/api/invoices/generate/${draftId}`, {
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = true;
    selectedIds.value = selectedIds.value.filter(id => id !== draftId);
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal generate invoice.';
    alertSuccess.value = false;
  } finally {
    generatingId.value = null;
  }
};

const askGenerateSelected = () => {
  if (selectedIds.value.length === 0 || generatingBatch.value) return;
  generateModalState.title = 'Terbitkan Invoice Terpilih';
  generateModalState.subtitle = `Pilih pihak Bill To untuk ${selectedIds.value.length} invoice terpilih:`;
  generateModalState.targetInfo = `${selectedIds.value.length} Draft Terpilih`;
  generateModalState.confirmText = `Ya, Terbitkan (${selectedIds.value.length}) Invoice`;
  generateModalState.action = async (billTo) => {
    generateModalState.loading = true;
    try {
      await executeGenerateSelected(billTo);
      generateModalState.show = false;
    } finally {
      generateModalState.loading = false;
    }
  };
  generateModalState.show = true;
};

const executeGenerateSelected = async (billTo) => {
  generatingBatch.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/generate-all', {
      ids: selectedIds.value,
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = res.data.success;
    selectedIds.value = [];
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal menerbitkan invoice terpilih.';
    alertSuccess.value = false;
  } finally {
    generatingBatch.value = false;
  }
};

const askGenerateAll = () => {
  if (generatingAll.value) return;
  generateModalState.title = 'Terbitkan Semua Invoice';
  generateModalState.subtitle = 'Pilih pihak Bill To untuk SEMUA draft dengan status Ready:';
  generateModalState.targetInfo = 'Semua Draft (Status Ready)';
  generateModalState.confirmText = 'Ya, Terbitkan Semua';
  generateModalState.action = async (billTo) => {
    generateModalState.loading = true;
    try {
      await executeGenerateAll(billTo);
      generateModalState.show = false;
    } finally {
      generateModalState.loading = false;
    }
  };
  generateModalState.show = true;
};

const executeGenerateAll = async (billTo) => {
  generatingAll.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/generate-all', {
      bill_to: billTo,
    });
    alertMessage.value = res.data.message;
    alertSuccess.value = res.data.success;
    selectedIds.value = [];
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal menerbitkan semua invoice.';
    alertSuccess.value = false;
  } finally {
    generatingAll.value = false;
  }
};

const formatCurrency = (val) => {
  const num = Math.round(Number(val) || 0);
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
};

const askPushSingleToDataProgram = async (draft) => {
  if (pushingId.value === draft.id) return;
  pushingId.value = draft.id;
  alertMessage.value = null;
  try {
    const res = await axios.post(`/api/drafts/${draft.id}/push-to-data-program`);
    alertMessage.value = res.data.message || 'Draft berhasil dikirim ke Data Program & Google Spreadsheet.';
    alertSuccess.value = true;
    fetchDrafts(pagination.current_page);
  } catch (err) {
    alertMessage.value = err.response?.data?.message || 'Gagal mengirim draft ke Data Program.';
    alertSuccess.value = false;
  } finally {
    pushingId.value = null;
  }
};

const askPushSelectedToDataProgram = () => {
  if (selectedIds.value.length === 0 || pushingBatch.value) return;
  confirmState.title = 'Kirim ke Data Program & Spreadsheet';
  confirmState.message = `Apakah Anda yakin ingin mengirim ${selectedIds.value.length} draft terpilih ke Data Program dan Google Spreadsheet?`;
  confirmState.confirmText = `Ya, Kirim (${selectedIds.value.length}) Draft`;
  confirmState.action = async () => {
    confirmState.loading = true;
    pushingBatch.value = true;
    alertMessage.value = null;
    try {
      const res = await axios.post('/api/drafts/push-batch-to-data-program', {
        ids: selectedIds.value,
      });
      alertMessage.value = res.data.message || 'Draft terpilih berhasil dikirim ke Data Program & Google Spreadsheet.';
      alertSuccess.value = true;
      selectedIds.value = [];
      fetchDrafts(pagination.current_page);
    } catch (err) {
      alertMessage.value = err.response?.data?.message || 'Gagal mengirim draft terpilih ke Data Program.';
      alertSuccess.value = false;
    } finally {
      pushingBatch.value = false;
      confirmState.loading = false;
      confirmState.show = false;
    }
  };
  confirmState.show = true;
};

const onDraftCreated = (newDraft) => {
  alertMessage.value = `Draft invoice #${newDraft.no || newDraft.id} (${newDraft.dealer_name}) berhasil ditambahkan secara manual.`;
  alertSuccess.value = true;
  fetchDrafts(1);
};

const askDeleteDraft = (draft) => {
  confirmState.title = 'Hapus Draft Invoice';
  confirmState.message = `Apakah Anda yakin ingin menghapus draft #${draft.no || draft.id} (${draft.dealer_name})? Tindakan ini tidak dapat dibatalkan.`;
  confirmState.confirmText = 'Ya, Hapus Draft';
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      const res = await axios.delete(`/api/drafts/${draft.id}`);
      if (res.data?.success) {
        alertMessage.value = res.data.message || 'Draft berhasil dihapus.';
        alertSuccess.value = true;
        fetchDrafts(pagination.current_page);
      }
    } catch (e) {
      alertMessage.value = e.response?.data?.message || 'Gagal menghapus draft.';
      alertSuccess.value = false;
    } finally {
      confirmState.loading = false;
      confirmState.show = false;
    }
  };
  confirmState.show = true;
};

onMounted(() => {
  fetchDrafts(1);
  fetchProgramOptions();
});
</script>
