<template>
  <div class="space-y-4">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Riwayat</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">Riwayat Program</span>
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950">Riwayat Program</h1>
        <p class="text-xs text-gray-500 mt-0.5">
          Audit dan histori pencocokan antara Form Program dengan Data Program.
        </p>
      </div>

      <!-- Action Buttons (Filament style) -->
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          @click="fetchLogs(1)"
          :disabled="loading"
          class="h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer disabled:opacity-50"
          title="Muat ulang data riwayat"
        >
          <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', loading && 'animate-spin text-gray-900']" />
          <span>{{ loading ? 'Memuat...' : 'Refresh' }}</span>
        </button>

        <button
          type="button"
          @click="handleTriggerBatchReconcile"
          :disabled="isReconciling"
          class="h-9 px-3.5 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer disabled:opacity-50"
          title="Jalankan pencocokan batch"
        >
          <RefreshCwIcon v-if="isReconciling" class="w-3.5 h-3.5 animate-spin text-emerald-600" />
          <CheckCircleIcon v-else class="w-3.5 h-3.5 text-gray-500" />
          <span>{{ isReconciling ? 'Mencocokkan...' : 'Cocokkan Form Program' }}</span>
        </button>
      </div>
    </div>

    <!-- Alert Status Sinkronisasi / Update -->
    <div
      v-if="statusMessage"
      :class="[
        'p-3 rounded-lg border text-xs flex items-center justify-between transition',
        statusError
          ? 'bg-white border-rose-200 text-rose-700'
          : 'bg-white border-gray-200 text-gray-800'
      ]"
    >
      <div class="flex items-center gap-2">
        <AlertCircleIcon v-if="statusError" class="w-4 h-4 shrink-0 text-rose-500" />
        <CheckCircleIcon v-else class="w-4 h-4 shrink-0 text-emerald-600" />
        <span>{{ statusMessage }}</span>
      </div>
      <button @click="statusMessage = ''" class="text-xs font-medium text-gray-400 hover:text-gray-700 ml-4 cursor-pointer">
        &times;
      </button>
    </div>

    <!-- Filament Table Card -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <!-- Toolbar (Search & Filter like Filament) -->
      <div class="p-3 sm:px-4 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Left: Realtime Auto-Refresh & Update info -->
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
          <div class="relative flex-1 sm:w-64">
            <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
            <input
              v-model="filters.search"
              @input="debounceFetch"
              type="text"
              placeholder="Search"
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
            <PopoverContent class="w-72 p-3 space-y-3" align="end">
              <div class="text-xs font-semibold text-gray-900 border-b border-gray-100 pb-2 flex items-center justify-between">
                <span>Filter Riwayat Program</span>
                <button
                  v-if="activeFilterCount > 0"
                  @click="resetFilters"
                  class="text-[11px] font-normal text-rose-600 hover:underline cursor-pointer"
                >
                  Reset
                </button>
              </div>

              <!-- Status Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Status Pencocokan</label>
                <select
                  v-model="filters.status"
                  @change="fetchLogs(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="ALL">Semua Status</option>
                  <option value="MATCHED">Sesuai</option>
                  <option value="DOC_INCOMPLETE">Dokumen Kurang</option>
                  <option value="NOMINAL_MISMATCH">Selisih Nominal</option>
                  <option value="NO_MATCH">Belum Ada Form</option>
                </select>
              </div>

              <!-- Filter Pemicu -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Pemicu</label>
                <select
                  v-model="filters.triggered_by"
                  @change="fetchLogs(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Pemicu</option>
                  <option value="manual_batch">Pencocokan Batch</option>
                  <option value="manual_row">Per Baris</option>
                  <option value="auto_submission">Form AI</option>
                </select>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <!-- Filament Table -->
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead class="w-[44px] text-center">No</TableHead>
            <TableHead class="min-w-[130px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Waktu
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[130px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                ID Real
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[160px]">
              <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                Nama Dealer
                <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
              </span>
            </TableHead>
            <TableHead class="min-w-[180px]">
              <span>Nama Program</span>
            </TableHead>
            <TableHead class="min-w-[120px] text-center">
              <span>Status</span>
            </TableHead>
            <TableHead class="text-right min-w-[120px]">
              <span>Data Program</span>
            </TableHead>
            <TableHead class="text-right min-w-[120px]">
              <span>Form Program</span>
            </TableHead>
            <TableHead class="text-right min-w-[110px]">
              <span>Selisih</span>
            </TableHead>
            <TableHead class="min-w-[120px] text-center">
              <span>Dokumen Form</span>
            </TableHead>
            <TableHead class="min-w-[160px]">
              <span>Keterangan</span>
            </TableHead>
            <TableHead class="text-right w-[90px] sticky right-0 bg-white">
              <span>Action</span>
            </TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <!-- Loading State -->
          <TableEmpty v-if="loading && logs.length === 0" :colspan="12">
            <div class="py-8 flex flex-col items-center justify-center gap-2 text-gray-500">
              <span class="inline-block w-5 h-5 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
              <span class="text-xs">Memuat data riwayat program...</span>
            </div>
          </TableEmpty>

          <!-- Empty State -->
          <TableEmpty v-else-if="logs.length === 0" :colspan="12">
            <div class="py-8 text-center text-gray-400 text-xs">
              Belum ada data riwayat yang tersimpan. Silakan klik tombol <strong>Cocokkan Form Program</strong> di atas.
            </div>
          </TableEmpty>

          <!-- Data Rows -->
          <TableRow
            v-else
            v-for="(row, idx) in logs"
            :key="row.id"
            class="hover:bg-gray-50 transition text-xs"
          >
            <!-- No -->
            <TableCell class="text-center text-gray-500 text-xs">
              {{ (pagination.current_page - 1) * pagination.per_page + idx + 1 }}
            </TableCell>

            <!-- Waktu -->
            <TableCell class="whitespace-nowrap text-xs text-gray-800">
              <div>{{ formatTimestamp(row.created_at).date }}</div>
              <div class="text-xs text-gray-400">{{ formatTimestamp(row.created_at).time }}</div>
            </TableCell>

            <!-- ID Real (Bold like SJ-xxxx) -->
            <TableCell>
              <span class="text-gray-950 text-xs">
                {{ row.kode_bt || row.program_submission?.id_real || '-' }}
              </span>
            </TableCell>

            <!-- Nama Dealer -->
            <TableCell class="text-xs text-gray-800" :title="row.dealer_name">
              <div class="line-clamp-2">{{ row.dealer_name || '-' }}</div>
            </TableCell>

            <!-- Nama Program -->
            <TableCell class="text-xs text-gray-600" :title="row.program_name">
              <div class="line-clamp-2">{{ row.program_name || '-' }}</div>
            </TableCell>

            <!-- Status Pencocokan (Filament Badge) -->
            <TableCell class="text-center whitespace-nowrap">
              <FilamentBadge
                v-if="row.status === 'MATCHED'"
                color="success"
              >
                SESUAI
              </FilamentBadge>
              <FilamentBadge
                v-else-if="row.status === 'DOC_INCOMPLETE'"
                color="warning"
              >
                DOKUMEN KURANG
              </FilamentBadge>
              <FilamentBadge
                v-else-if="row.status === 'NOMINAL_MISMATCH'"
                color="danger"
              >
                SELISIH
              </FilamentBadge>
              <FilamentBadge
                v-else-if="row.status === 'NO_MATCH'"
                color="gray"
              >
                BELUM ADA FORM
              </FilamentBadge>
              <FilamentBadge
                v-else
                color="gray"
              >
                {{ row.status }}
              </FilamentBadge>
            </TableCell>

            <!-- Data Program -->
            <TableCell class="whitespace-nowrap text-right text-xs text-gray-800">
              {{ formatRupiah(row.dp_amount) }}
            </TableCell>

            <!-- Form Program -->
            <TableCell class="whitespace-nowrap text-right text-xs text-gray-800">
              {{ row.submission_amount ? formatRupiah(row.submission_amount) : (row.program_submission_id ? '0' : '-') }}
            </TableCell>

            <!-- Selisih -->
            <TableCell class="whitespace-nowrap text-right text-xs">
              <span v-if="row.selisih === 0 || row.selisih === '0' || row.selisih === 0.0" class="text-gray-400">
                0
              </span>
              <span v-else-if="row.selisih !== null && row.selisih !== undefined && row.selisih !== ''" class="font-medium text-rose-600">
                {{ formatRupiah(row.selisih) }}
              </span>
              <span v-else class="text-gray-400">-</span>
            </TableCell>

            <!-- Dokumen Form -->
            <TableCell class="text-center whitespace-nowrap">
              <div v-if="row.program_submission" class="inline-flex items-center justify-center gap-1.5">
                <a
                  v-if="isValidUrl(row.program_submission.credit_note_url)"
                  :href="row.program_submission.credit_note_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-gray-200 bg-white text-gray-700 hover:text-gray-950 text-xs font-medium hover:bg-gray-50 transition cursor-pointer"
                  title="Credit Note"
                >
                  <FileTextIcon class="w-3.5 h-3.5 text-gray-500" />
                  <span>CN</span>
                </a>
                <a
                  v-if="isValidUrl(row.program_submission.agreement_url)"
                  :href="row.program_submission.agreement_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-gray-200 bg-white text-gray-700 hover:text-gray-950 text-xs font-medium hover:bg-gray-50 transition cursor-pointer"
                  title="Agreement"
                >
                  <FileTextIcon class="w-3.5 h-3.5 text-gray-500" />
                  <span>Agr</span>
                </a>
                <a
                  v-if="isValidUrl(row.program_submission.tax_invoice_url)"
                  :href="row.program_submission.tax_invoice_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-gray-200 bg-white text-gray-700 hover:text-gray-950 text-xs font-medium hover:bg-gray-50 transition cursor-pointer"
                  title="Faktur Pajak"
                >
                  <FileTextIcon class="w-3.5 h-3.5 text-gray-500" />
                  <span>Faktur</span>
                </a>
                <span
                  v-if="!isValidUrl(row.program_submission.credit_note_url) && !isValidUrl(row.program_submission.agreement_url) && !isValidUrl(row.program_submission.tax_invoice_url)"
                  class="text-gray-400 text-xs"
                >
                  -
                </span>
              </div>
              <span v-else class="text-gray-400 text-xs">-</span>
            </TableCell>

            <!-- Keterangan -->
            <TableCell class="text-xs text-gray-600">
              <div class="line-clamp-2" :title="row.notes">{{ row.notes || '-' }}</div>
            </TableCell>

            <!-- Action -->
            <TableCell class="text-right whitespace-nowrap sticky right-0 bg-white">
              <button
                type="button"
                @click="openDetailModal(row)"
                class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-gray-950 hover:underline cursor-pointer"
                title="Lihat Detail Komparasi"
              >
                <EyeIcon class="w-4 h-4 text-gray-500" />
                <span>Detail</span>
              </button>
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
        @page-change="fetchLogs"
        @per-page-change="(p) => { pagination.per_page = p; fetchLogs(1); }"
      />
    </div>

    <!-- Detail Komparasi Modal (Polos & Rapi, Tanpa Slop) -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showDetailModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
          @click.self="showDetailModal = false"
        >
          <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden font-sans my-6">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/70 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-gray-900">Detail Riwayat Rekonsiliasi</h3>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                  Dealer: <strong class="text-gray-800">{{ selectedLog?.dealer_name || '-' }}</strong>
                  <span v-if="selectedLog?.kode_bt" class="text-gray-400"> ({{ selectedLog.kode_bt }})</span>
                  &bull; {{ selectedLog?.program_name }}
                </p>
              </div>
              <button
                type="button"
                @click="showDetailModal = false"
                class="w-8 h-8 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              >
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Modal Body (Tabel Polos) -->
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
              <!-- Ringkasan Hasil Catatan -->
              <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 flex items-center justify-between">
                <div>
                  <span class="text-gray-500">Catatan Hasil:</span>
                  <span class="font-medium text-gray-900 ml-1.5">{{ selectedLog?.notes || '-' }}</span>
                </div>
                <div class="shrink-0 ml-4">
                  <span
                    v-if="selectedLog?.status === 'MATCHED'"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold"
                  >
                    <CheckIcon class="w-3 h-3" />
                    <span>Sesuai</span>
                  </span>
                  <span
                    v-else-if="selectedLog?.status === 'DOC_INCOMPLETE'"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-xs font-medium"
                  >
                    <span>Dokumen Kurang</span>
                  </span>
                  <span
                    v-else-if="selectedLog?.status === 'NOMINAL_MISMATCH'"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 text-xs font-medium"
                  >
                    <span>Selisih Nominal</span>
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 border border-gray-200 text-xs font-medium"
                  >
                    <span>{{ selectedLog?.status }}</span>
                  </span>
                </div>
              </div>

              <!-- Tabel Komparasi -->
              <div class="rounded-lg border border-gray-200 overflow-hidden">
                <table class="w-full text-xs text-left">
                  <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-medium">
                    <tr>
                      <th class="px-3.5 py-2.5 w-1/3">Parameter</th>
                      <th class="px-3.5 py-2.5 w-1/3">Data Program (Master)</th>
                      <th class="px-3.5 py-2.5 w-1/3">Form Program (Respon)</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 text-gray-700">
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">ID Real / Kode BT</td>
                      <td class="px-3.5 py-2">{{ selectedLog?.kode_bt || '-' }}</td>
                      <td class="px-3.5 py-2">{{ selectedLog?.program_submission?.id_real || '-' }}</td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Nama Dealer</td>
                      <td class="px-3.5 py-2">{{ selectedLog?.dealer_name || '-' }}</td>
                      <td class="px-3.5 py-2">{{ selectedLog?.program_submission?.dealer_name || '-' }}</td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Nama Program</td>
                      <td class="px-3.5 py-2">{{ selectedLog?.program_name || '-' }}</td>
                      <td class="px-3.5 py-2">{{ selectedLog?.program_submission?.program_name || '-' }}</td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Nominal (DPP / Net)</td>
                      <td class="px-3.5 py-2 font-medium">{{ formatRupiah(selectedLog?.dp_amount) }}</td>
                      <td class="px-3.5 py-2 font-medium">{{ formatRupiah(selectedLog?.submission_amount) }}</td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Selisih Nominal</td>
                      <td colspan="2" class="px-3.5 py-2">
                        {{ selectedLog?.selisih === 0 || selectedLog?.selisih === '0' || selectedLog?.selisih === 0.0 ? '0' : formatRupiah(selectedLog?.selisih) }}
                      </td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Dokumen CN</td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(selectedLog?.data_program?.cn)" :href="selectedLog.data_program.cn" target="_blank" class="text-blue-600 hover:underline">Buka Link</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(selectedLog?.program_submission?.credit_note_url)" :href="selectedLog.program_submission.credit_note_url" target="_blank" class="text-blue-600 hover:underline">Buka Berkas Form</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Dokumen Agreement</td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(selectedLog?.data_program?.agrement)" :href="selectedLog.data_program.agrement" target="_blank" class="text-blue-600 hover:underline">Buka Link</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(selectedLog?.program_submission?.agreement_url)" :href="selectedLog.program_submission.agreement_url" target="_blank" class="text-blue-600 hover:underline">Buka Berkas Form</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Dokumen Faktur Pajak</td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(selectedLog?.data_program?.cek_fp)" :href="selectedLog.data_program.cek_fp" target="_blank" class="text-blue-600 hover:underline">Buka Link</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-3.5 py-2">
                        <a v-if="isValidUrl(selectedLog?.program_submission?.tax_invoice_url)" :href="selectedLog.program_submission.tax_invoice_url" target="_blank" class="text-blue-600 hover:underline">Buka Berkas Form</a>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Status Potong Purchase</td>
                      <td colspan="2" class="px-3.5 py-2">{{ selectedLog?.status_potong_purchase || '-' }}</td>
                    </tr>
                    <tr>
                      <td class="px-3.5 py-2 text-gray-500 font-medium bg-gray-50/50">Cek Dokumen</td>
                      <td colspan="2" class="px-3.5 py-2">{{ selectedLog?.cek_dokumen || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 border-t border-gray-200 bg-gray-50/70 flex items-center justify-end">
              <button
                type="button"
                @click="showDetailModal = false"
                class="px-3.5 py-1.5 rounded-md border border-gray-200 bg-white hover:bg-gray-100 text-gray-700 text-xs font-medium transition cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import {
  Search as SearchIcon,
  RefreshCw as RefreshCwIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  Check as CheckIcon,
  Eye as EyeIcon,
  X as XIcon,
  FileText as FileTextIcon,
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  Filter as FilterIcon,
} from 'lucide-vue-next';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import FilamentBadge from '@/components/ui/FilamentBadge.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
  TableEmpty,
} from '@/components/ui/table';

const logs = ref([]);
const loading = ref(false);
const isReconciling = ref(false);
const statusMessage = ref('');
const statusError = ref(false);
const autoRefresh = ref(true);
const lastUpdatedText = ref('');
const isFilterOpen = ref(false);

const showDetailModal = ref(false);
const selectedLog = ref(null);

const filters = reactive({
  search: '',
  status: 'ALL',
  triggered_by: '',
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.status && filters.status !== 'ALL') count++;
  if (filters.triggered_by) count++;
  return count;
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

let debounceTimer = null;
let autoRefreshTimer = null;

const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchLogs(1);
  }, 350);
};

const resetFilters = () => {
  filters.search = '';
  filters.status = 'ALL';
  filters.triggered_by = '';
  isFilterOpen.value = false;
  fetchLogs(1);
};

const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };

    if (filters.search) params.search = filters.search;
    if (filters.status && filters.status !== 'ALL') params.status = filters.status;
    if (filters.triggered_by) params.triggered_by = filters.triggered_by;

    const response = await axios.get('/api/data-program/reconciliation-logs', { params });
    const data = response.data;

    logs.value = data.data || [];
    if (data.pagination) {
      pagination.current_page = data.pagination.current_page;
      pagination.last_page = data.pagination.last_page;
      pagination.per_page = data.pagination.per_page;
      pagination.total = data.pagination.total;
    }

    const now = new Date();
    lastUpdatedText.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch (err) {
    statusError.value = true;
    statusMessage.value = 'Gagal memuat riwayat: ' + (err.response?.data?.message || err.message);
  } finally {
    loading.value = false;
  }
};

const changePerPage = () => {
  fetchLogs(1);
};

const onPageInputEnter = (e) => {
  const page = parseInt(e.target.value);
  if (!isNaN(page) && page >= 1 && page <= pagination.last_page) {
    fetchLogs(page);
  } else {
    e.target.value = pagination.current_page;
  }
};

const onPageInputBlur = (e) => {
  const page = parseInt(e.target.value);
  if (!isNaN(page) && page >= 1 && page <= pagination.last_page && page !== pagination.current_page) {
    fetchLogs(page);
  } else {
    e.target.value = pagination.current_page;
  }
};

const openDetailModal = (row) => {
  selectedLog.value = row;
  showDetailModal.value = true;
};

const handleTriggerBatchReconcile = async () => {
  if (!confirm('Jalankan proses pencocokan antara Form Program dan Data Program?')) {
    return;
  }

  isReconciling.value = true;
  statusMessage.value = 'Sedang menjalankan pencocokan data program...';
  statusError.value = false;

  try {
    const res = await axios.post('/api/data-program/reconcile-all', {
      limit: 100,
      year: '2026',
      force: false,
      push_to_sheet: true,
    });

    statusError.value = false;
    statusMessage.value = res.data?.message || `Pencocokan selesai. Cocok: ${res.data?.matched_count || 0}`;
    await fetchLogs(1);
  } catch (err) {
    statusError.value = true;
    statusMessage.value = 'Gagal menjalankan rekonsiliasi: ' + (err.response?.data?.message || err.message);
  } finally {
    isReconciling.value = false;
  }
};

// Utilities persis Form Program
const formatRupiah = (val) => {
  if (val === null || val === undefined || val === '') return '-';
  const num = Number(val);
  if (isNaN(num)) return val;
  return new Intl.NumberFormat('id-ID').format(num);
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

const isValidUrl = (url) => {
  if (!url) return false;
  return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'));
};

onMounted(() => {
  fetchLogs(1);

  autoRefreshTimer = setInterval(() => {
    if (autoRefresh.value && !loading.value && !showDetailModal.value) {
      fetchLogs(pagination.current_page);
    }
  }, 30000);
});

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer);
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
});
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
  transform: scale(0.98);
}
</style>
