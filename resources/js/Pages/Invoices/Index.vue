<template>
  <div class="space-y-4">
    <!-- Breadcrumbs (Filament style) -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Invoices</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">List</span>
    </div>

    <AreaTabs />

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="min-w-0">
        <!-- Layar kecil: badge Google turun ke baris berikutnya bila tidak muat -->
        <div class="flex flex-wrap md:flex-nowrap items-center gap-x-2.5 gap-y-1">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Invoices</h1>
          <!-- Google Connection Status Badge with Action Popover -->
          <Popover v-if="googleStatus.is_connected">
            <PopoverTrigger as-child>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-normal bg-gray-50 hover:bg-gray-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 border border-gray-200 dark:border-slate-700 transition cursor-pointer max-w-full"
                title="Klik untuk opsi akun Google / Gmail"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="truncate">Gmail: {{ googleStatus.account_email }}</span>
                <ChevronDownIcon class="w-3 h-3 text-gray-400 shrink-0" />
              </button>
            </PopoverTrigger>
            <PopoverContent align="start" class="w-64 p-3 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl text-xs space-y-2.5">
              <div>
                <p class="font-medium text-gray-900 dark:text-white">Akun Gmail Terhubung</p>
                <p class="text-gray-500 dark:text-slate-400 text-[11px] truncate mt-0.5">{{ googleStatus.account_email }}</p>
              </div>
              <div class="pt-2 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between gap-2">
                <a
                  v-if="canEdit"
                  href="/auth/google/redirect"
                  class="px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:text-slate-200 bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition cursor-pointer border border-gray-200 dark:border-slate-700"
                  title="Ganti akun Google Workspace"
                >
                  Ganti Akun
                </a>
                <button
                  v-if="canEdit"
                  type="button"
                  @click="disconnectGoogle"
                  :disabled="disconnectingGoogle"
                  class="px-2.5 py-1 text-[11px] font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-md transition cursor-pointer disabled:opacity-50"
                  title="Putuskan / Logout akun Gmail ini"
                >
                  {{ disconnectingGoogle ? 'Memproses...' : 'Logout Gmail' }}
                </button>
              </div>
            </PopoverContent>
          </Popover>
          <a
            v-else-if="canEdit"
            href="/auth/google/redirect"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-normal bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 transition cursor-pointer"
            title="Klik untuk menghubungkan akun Google Workspace (Gmail API)"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
            <span>Hubungkan Google Workspace</span>
          </a>
        </div>
        <p class="text-xs text-gray-500 mt-0.5">Daftar invoice resmi yang telah diterbitkan.</p>
      </div>

      <!-- Tombol aksi; di layar kecil tombol membungkus rapi -->
      <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
        <template v-if="selectedIds.length > 0">
          <button
            v-if="canDelete"
            @click="askDeleteBatch"
            :disabled="deletingBatch || sendingBatch"
            :class="headerDangerButtonClass"
            :title="`Hapus ${selectedIds.length} invoice terpilih`"
          >
            <Trash2Icon class="w-3.5 h-3.5 mr-1 text-rose-600 dark:text-rose-300 shrink-0" />
            <span class="sm:hidden">{{ deletingBatch ? 'Menghapus...' : `Hapus (${selectedIds.length})` }}</span>
            <span class="hidden sm:inline">{{ deletingBatch ? 'Menghapus...' : `Hapus (${selectedIds.length}) Terpilih` }}</span>
          </button>

          <button
            v-if="canEdit && hasUnsentSelected"
            @click="askSendBatch"
            :disabled="sendingBatch || deletingBatch"
            :class="headerButtonClass"
            :title="`Kirim ${unsentSelectedCount} invoice terpilih`"
          >
            <span class="sm:hidden">{{ sendingBatch ? 'Mengirim...' : `Kirim (${unsentSelectedCount})` }}</span>
            <span class="hidden sm:inline">{{ sendingBatch ? 'Mengirim...' : `Kirim (${unsentSelectedCount}) Terpilih` }}</span>
          </button>

          <button
            v-if="canEdit && hasSentSelected"
            @click="askResendBatch"
            :disabled="sendingBatch || deletingBatch"
            :class="headerButtonClass"
            :title="`Kirim ulang ${sentSelectedCount} invoice terpilih ke Email & WhatsApp (tanpa masuk ke Data Program lagi)`"
          >
            <RotateCcwIcon class="w-3.5 h-3.5 mr-1 text-amber-600" />
            <span class="sm:hidden">{{ sendingBatch ? 'Mengirim Ulang...' : `Kirim Ulang (${sentSelectedCount})` }}</span>
            <span class="hidden sm:inline">{{ sendingBatch ? 'Mengirim Ulang...' : `Kirim Ulang (${sentSelectedCount}) Terpilih` }}</span>
          </button>

          <button
            v-if="canEdit && unuploadedSelectedCount > 0"
            @click="askRemindUploadBatch"
            :disabled="remindingBatch"
            :class="headerButtonClass"
            :title="`Kirim WhatsApp ke telemarketing mengingatkan untuk upload dokumen ${unuploadedSelectedCount} invoice terpilih ke Form Program`"
          >
            <BellIcon class="w-3.5 h-3.5 mr-1 text-gray-600" />
            <span class="sm:hidden">{{ remindingBatch ? 'Mengingatkan...' : `Ingatkan Upload (${unuploadedSelectedCount})` }}</span>
            <span class="hidden sm:inline">{{ remindingBatch ? 'Mengingatkan...' : `Ingatkan Upload (${unuploadedSelectedCount})` }}</span>
          </button>

          <button
            @click="selectedIds = []"
            :class="headerButtonClass"
            title="Batalkan pilihan"
          >
            <span class="sm:hidden">Batal</span><span class="hidden sm:inline">Batal Pilih</span>
          </button>

          <router-link :to="`/drafts/${brand.slug}/${area.value}`" :class="headerButtonClass">
            Draft Invoice
          </router-link>
        </template>

        <template v-else>
          <button
            v-if="canEdit"
            @click="askSendAll"
            :disabled="sendingAll"
            :class="headerButtonClass"
            title="Kirim semua invoice yang belum terkirim: Email ke dealer, WhatsApp ke telemarketing per region"
          >
            <span class="sm:hidden">{{ sendingAll ? 'Mengirim...' : 'Kirim Semua' }}</span>
            <span class="hidden sm:inline">{{ sendingAll ? 'Mengirim Semua...' : 'Kirim Semua Notifikasi' }}</span>
          </button>

          <router-link :to="`/drafts/${brand.slug}/${area.value}`" :class="headerButtonClass">
            Draft Invoice
          </router-link>
        </template>
      </div>
    </div>

    <!-- Alert Notification -->
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
        <!-- Left: Quick Selection Info / Upload Status Quick Filters -->
        <div class="flex items-center gap-2 text-xs">
          <span v-if="selectedIds.length > 0" class="font-medium text-gray-900 bg-gray-100 px-2.5 py-1 rounded-md">
            {{ selectedIds.length }} invoice terpilih
          </span>
          <div v-else class="flex items-center gap-1.5 overflow-x-auto">
            <button
              type="button"
              @click="setUploadFilter('')"
              :class="[
                'h-8 px-3 rounded-lg text-xs font-medium transition cursor-pointer select-none whitespace-nowrap bg-white border',
                !filters.upload_status
                  ? 'border-gray-900 text-gray-950 font-semibold shadow-2xs'
                  : 'border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              ]"
            >
              Semua
            </button>
            <button
              type="button"
              @click="setUploadFilter('unuploaded')"
              :class="[
                'h-8 px-3 rounded-lg text-xs font-medium transition cursor-pointer select-none whitespace-nowrap bg-white border',
                filters.upload_status === 'unuploaded'
                  ? 'border-gray-900 text-gray-950 font-semibold shadow-2xs'
                  : 'border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              ]"
              title="Tampilkan invoice yang belum di-upload ke Form Program"
            >
              Belum Upload
            </button>
            <button
              type="button"
              @click="setUploadFilter('uploaded')"
              :class="[
                'h-8 px-3 rounded-lg text-xs font-medium transition cursor-pointer select-none whitespace-nowrap bg-white border',
                filters.upload_status === 'uploaded'
                  ? 'border-gray-900 text-gray-950 font-semibold shadow-2xs'
                  : 'border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              ]"
              title="Tampilkan invoice yang sudah di-upload ke Form Program"
            >
              Sudah Upload
            </button>
          </div>
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

          <!-- Filter Popover with Active Badge Count (like photo: funnel with badge) -->
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
                <span>Filter Invoices</span>
                <button
                  v-if="activeFilterCount > 0"
                  @click="resetFilters"
                  class="text-[11px] font-normal text-rose-600 hover:underline cursor-pointer"
                >
                  Reset
                </button>
              </div>

              <!-- Status Upload Form Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Status Upload Form</label>
                <select
                  v-model="filters.upload_status"
                  @change="fetchInvoices(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua</option>
                  <option value="unuploaded">Belum Upload</option>
                  <option value="uploaded">Sudah Upload (Form Uploaded)</option>
                </select>
              </div>

              <!-- Tipe Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tipe Invoice</label>
                <select
                  v-model="filters.invoice_type"
                  @change="fetchInvoices(1)"
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
                  @change="fetchInvoices(1)"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                >
                  <option value="">Semua Status</option>
                  <option value="generated">Generated</option>
                  <option value="sent">Sent (Terkirim)</option>
                  <option value="paid">Paid</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>

              <!-- Date Filter -->
              <div class="space-y-1">
                <label class="text-[11px] font-medium text-gray-700">Tanggal</label>
                <input
                  v-model="filters.date"
                  @change="fetchInvoices(1)"
                  type="date"
                  class="w-full h-8 px-2.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-950 cursor-pointer"
                />
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>

      <!-- Layar kecil: satu kartu per invoice -->
      <div class="md:hidden">
        <div v-if="loading" class="py-8 text-center text-xs text-gray-500">Memuat data invoice...</div>
        <div v-else-if="invoices.length === 0" class="py-8 px-4 text-center text-xs text-gray-500">
          Belum ada invoice yang dibuat. Silakan generate invoice dari menu <strong>Draft</strong>.
        </div>
        <template v-else>
          <label v-if="(canEdit || canDelete) && invoices.length > 0" class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-3 text-xs text-gray-600 cursor-pointer">
            <input
              type="checkbox"
              :checked="isAllSelected"
              :indeterminate="isIndeterminate"
              @change="toggleSelectAll"
              class="rounded border-gray-300 text-gray-900 focus:ring-0 w-4 h-4 cursor-pointer"
            />
            <span>Pilih semua invoice di halaman ini</span>
          </label>

          <ul class="divide-y divide-gray-100">
            <li
              v-for="inv in invoices"
              :key="inv.id"
              class="p-4 flex gap-3 text-xs text-gray-700"
              :class="selectedIds.includes(inv.id) && 'bg-gray-50'"
            >
              <input
                v-if="canEdit || canDelete"
                type="checkbox"
                :value="inv.id"
                v-model="selectedIds"
                class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-0 w-4 h-4 cursor-pointer shrink-0"
              />
              <div class="flex-1 min-w-0 space-y-2">
                <div class="flex items-start justify-between gap-3">
                  <router-link :to="`/invoices/${inv.id}`" class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 break-words">{{ inv.invoice_number }}</p>
                    <p class="mt-0.5 text-gray-500">{{ inv.invoice_type || '-' }} · {{ inv.invoice_date || '-' }}</p>
                  </router-link>
                  <p class="text-sm font-semibold text-gray-950 whitespace-nowrap shrink-0">{{ formatCurrency(inv.netpay) }}</p>
                </div>

                <p class="text-gray-800 truncate">{{ inv.dealer_code }} - {{ inv.dealer_name }}</p>
                <p class="text-gray-500 truncate">{{ inv.customer_name || '-' }}</p>

                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                  <div class="inline-flex items-center gap-1.5 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-gray-900 font-medium">
                      <span
                        class="w-1.5 h-1.5 rounded-full"
                        :class="inv.status === 'sent' ? 'bg-emerald-500' : (inv.status === 'paid' ? 'bg-blue-500' : (inv.status === 'cancelled' ? 'bg-rose-500' : 'bg-gray-400'))"
                      ></span>
                      {{ inv.status === 'sent' ? 'DELIVERED' : (inv.status ? inv.status.toUpperCase() : 'GENERATED') }}
                    </span>
                    <span class="text-gray-300">·</span>
                    <button
                      v-if="!inv.is_uploaded_to_form && canEdit"
                      type="button"
                      @click="askRemindUploadSingle(inv)"
                      class="inline-flex items-center gap-1 text-[11px] text-amber-700 hover:text-amber-900 font-medium cursor-pointer underline"
                      title="Dokumen belum di-upload ke Form. Klik untuk ingatkan WA ke Telemarketing."
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                      Belum Upload
                    </button>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 text-[11px] font-medium"
                      :class="inv.is_uploaded_to_form ? 'text-emerald-700' : 'text-gray-500'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="inv.is_uploaded_to_form ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                      {{ inv.is_uploaded_to_form ? 'Form Uploaded' : 'Belum Upload' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <router-link
                      :to="`/invoices/${inv.id}`"
                      class="h-8 px-2.5 inline-flex items-center rounded-lg border border-gray-300 bg-white text-gray-700 font-medium"
                    >
                      Lihat
                    </router-link>
                    <a
                      :href="`/invoices/${inv.id}/pdf`"
                      target="_blank"
                      class="h-8 px-2.5 inline-flex items-center rounded-lg border border-gray-300 bg-white text-gray-700 font-medium"
                    >
                      PDF
                    </a>
                    <span
                      v-if="isAlreadySent(inv)"
                      class="inline-flex items-center gap-1.5"
                    >
                      <button
                        v-if="canEdit"
                        type="button"
                        @click="openEmailModal(inv)"
                        class="h-8 px-2.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 font-medium cursor-pointer"
                        title="Kirim ulang notifikasi invoice (Email/WhatsApp)"
                      >
                        Kirim Ulang
                      </button>
                    </span>
                    <button
                      v-else-if="canEdit"
                      type="button"
                      @click="openEmailModal(inv)"
                      class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium cursor-pointer"
                    >
                      Kirim
                    </button>
                    <button
                      v-if="canDelete"
                      type="button"
                      @click.stop="deleteRecord(inv)"
                      class="h-8 px-2.5 rounded-lg border border-gray-300 bg-white text-red-600 dark:text-rose-300 font-medium cursor-pointer"
                      title="Hapus invoice ini"
                    >
                      Hapus
                    </button>
                  </div>
                </div>
              </div>
            </li>
          </ul>

          <div class="px-4 py-3 border-t border-gray-200 bg-gray-50/80 flex items-center justify-between gap-3 text-xs">
            <span class="font-semibold text-gray-900">Total Netpay (Halaman ini)</span>
            <span class="font-semibold text-gray-950 whitespace-nowrap">{{ formatCurrency(totalNetpay) }}</span>
          </div>
        </template>
      </div>

      <!-- Table: Styled identically to Test Program for clean spreadsheet look -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-900 border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-gray-700 font-semibold whitespace-nowrap">
              <th v-if="canEdit || canDelete" scope="col" class="py-2.5 px-3 text-center w-[44px] border-r border-gray-200">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isIndeterminate"
                  @change="toggleSelectAll"
                  :disabled="invoices.length === 0"
                  class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer disabled:opacity-30"
                  title="Pilih semua invoice di halaman ini"
                />
              </th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                  Nomor Invoice
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                  Dealer Tujuan
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Customer</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">Tipe</th>
              <th scope="col" class="py-2.5 px-3 text-center border-r border-gray-200">Status</th>
              <th scope="col" class="py-2.5 px-3 border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer">
                  Tanggal
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 text-right border-r border-gray-200">
                <span class="inline-flex items-center gap-1.5 select-none cursor-pointer justify-end w-full">
                  Total Netpay
                  <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400" />
                </span>
              </th>
              <th scope="col" class="py-2.5 px-3 text-center">Action</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="loading">
              <td :colspan="canEdit || canDelete ? 9 : 8" class="py-8 text-center text-gray-500">
                <div class="inline-flex items-center gap-2">
                  <span class="inline-block w-4 h-4 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin"></span>
                  <span>Memuat data invoice...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="invoices.length === 0">
              <td :colspan="canEdit || canDelete ? 9 : 8" class="py-8 text-center text-gray-400 text-xs">
                Belum ada invoice yang dibuat. Silakan generate invoice dari menu <strong>Draft</strong>.
              </td>
            </tr>

            <tr
              v-else
              v-for="inv in invoices"
              :key="inv.id"
              :class="[selectedIds.includes(inv.id) ? 'bg-gray-50' : 'hover:bg-gray-50/70', 'text-gray-700 font-normal transition']"
            >
              <!-- Checkbox Selection -->
              <td v-if="canEdit || canDelete" class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <input
                  type="checkbox"
                  :value="inv.id"
                  v-model="selectedIds"
                  class="rounded border-gray-300 text-gray-900 focus:ring-0 focus:ring-offset-0 w-4 h-4 cursor-pointer"
                  :title="`Pilih invoice ${inv.invoice_number}`"
                />
              </td>

              <!-- Nomor Invoice -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap">
                <router-link
                  :to="`/invoices/${inv.id}`"
                  class="font-semibold text-gray-950 text-xs hover:underline inline-flex items-center gap-1"
                >
                  {{ inv.invoice_number }}
                </router-link>
              </td>

              <!-- Dealer Tujuan -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[200px] truncate text-gray-800" :title="`${inv.dealer_code} - ${inv.dealer_name}`">
                {{ inv.dealer_code }} - {{ inv.dealer_name }}
              </td>

              <!-- Customer -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap max-w-[180px] truncate text-gray-600" :title="inv.customer_name">
                {{ inv.customer_name || '-' }}
              </td>

              <!-- Tipe -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-600">
                {{ inv.invoice_type || '-' }}
              </td>

              <!-- Status -->
              <td class="py-2.5 px-3 text-center border-r border-gray-100 whitespace-nowrap">
                <div class="inline-flex flex-col items-center gap-0.5">
                  <span
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border',
                      inv.status === 'sent'
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : (inv.status === 'paid'
                          ? 'bg-blue-50 text-blue-700 border-blue-200'
                          : (inv.status === 'cancelled'
                            ? 'bg-rose-50 text-rose-700 border-rose-200'
                            : 'bg-gray-100 text-gray-700 border-gray-200'))
                    ]"
                  >
                    {{ inv.status === 'sent' ? 'DELIVERED' : (inv.status ? inv.status.toUpperCase() : 'GENERATED') }}
                  </span>
                  <button
                    v-if="!inv.is_uploaded_to_form && canEdit"
                    type="button"
                    @click.stop="askRemindUploadSingle(inv)"
                    class="inline-flex items-center gap-1 text-[10px] text-amber-700 hover:text-amber-900 font-medium cursor-pointer transition select-none hover:underline"
                    title="Dokumen belum di-upload ke Form Program. Klik untuk kirim pengingat WhatsApp ke Telemarketing."
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                    <span>Belum Upload</span>
                  </button>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 text-[10px] font-medium select-none"
                    :class="inv.is_uploaded_to_form ? 'text-emerald-700' : 'text-gray-400'"
                    :title="inv.is_uploaded_to_form ? 'Dokumen sudah di-upload ke Form Program' : 'Dokumen belum di-upload ke Form Program'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="inv.is_uploaded_to_form ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                    <span>{{ inv.is_uploaded_to_form ? 'Form Uploaded' : 'Belum Upload' }}</span>
                  </span>
                </div>
              </td>

              <!-- Tanggal -->
              <td class="py-2.5 px-3 border-r border-gray-100 whitespace-nowrap text-gray-600">
                {{ inv.invoice_date || '-' }}
              </td>

              <!-- Total Netpay -->
              <td class="py-2.5 px-3 text-right border-r border-gray-100 whitespace-nowrap font-bold text-gray-950">
                {{ formatCurrency(inv.netpay) }}
              </td>

              <!-- Action Buttons -->
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                <div class="inline-flex items-center rounded-md border border-gray-200 bg-white shadow-2xs divide-x divide-gray-200 overflow-hidden">
                  <!-- 1. View Button -->
                  <router-link
                    :to="`/invoices/${inv.id}`"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                    title="Lihat Detail Invoice"
                  >
                    <EyeIcon class="w-3.5 h-3.5 text-gray-600" />
                  </router-link>

                  <!-- 2. PDF Link Button -->
                  <a
                    :href="`/invoices/${inv.id}/pdf`"
                    target="_blank"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                    title="Unduh PDF"
                  >
                    <FileTextIcon class="w-3.5 h-3.5 text-gray-600" />
                  </a>

                  <!-- 3. Kirim / Kirim Ulang Action -->
                  <button
                    v-if="canEdit"
                    type="button"
                    @click="openEmailModal(inv)"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition cursor-pointer"
                    :title="isAlreadySent(inv) ? 'Kirim Ulang Invoice via Email/WhatsApp' : 'Kirim Invoice via Email/WhatsApp'"
                  >
                    <RotateCcwIcon v-if="isAlreadySent(inv)" class="w-3.5 h-3.5 text-amber-600" />
                    <SendIcon v-else class="w-3.5 h-3.5 text-gray-600" />
                  </button>

                  <!-- 4. Hapus Button (Paling Kanan) -->
                  <button
                    v-if="canDelete"
                    type="button"
                    @click.stop="deleteRecord(inv)"
                    class="h-7 w-7.5 inline-flex items-center justify-center text-gray-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-rose-300 hover:bg-red-50 dark:hover:bg-rose-950/50 transition cursor-pointer"
                    title="Hapus Invoice Ini"
                  >
                    <Trash2Icon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>

          <!-- Semantic Table Footer -->
          <tfoot v-if="invoices.length > 0" class="border-t-2 border-gray-300 bg-gray-50/80 font-semibold text-gray-900">
            <tr>
              <td :colspan="canEdit || canDelete ? 7 : 6" class="py-2.5 px-3 text-xs text-gray-700 border-r border-gray-200">
                Total Netpay (Halaman ini)
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
        @page-change="fetchInvoices"
        @per-page-change="(p) => { pagination.per_page = p; fetchInvoices(1); }"
      />
    </div>

    <!-- Send Email Modal with Sender Selector -->
    <SendEmailModal
      v-model="showEmailModal"
      :invoice-id="selectedInvoice?.id"
      :invoice-number="selectedInvoice?.invoice_number"
      :dealer-name="selectedInvoice?.dealer_name"
      :customer-name="selectedInvoice?.customer_name"
      :default-email="selectedInvoice?.email || selectedInvoice?.draft?.email"
      :default-whatsapp="selectedInvoice?.whatsapp || selectedInvoice?.draft?.whatsapp"
      :is-resend="selectedInvoice ? isAlreadySent(selectedInvoice) : false"
      @sent="onEmailSent"
    />

    <!-- Modern Confirmation Modal (Replacing native browser popup) -->
    <ConfirmModal
      v-model="confirmState.show"
      :title="confirmState.title"
      :message="confirmState.message"
      :confirm-text="confirmState.confirmText"
      :variant="confirmState.variant || 'default'"
      :icon="confirmState.icon || 'mail'"
      :loading="confirmState.loading"
      @confirm="onConfirmAction"
    >
      <div v-if="confirmState.showSenderSelect" class="mt-3 space-y-1.5 text-left">
        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700">
          Kirim dari
        </label>
        <select
          v-model="batchSenderId"
          class="w-full h-10 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-black cursor-pointer"
        >
          <option v-for="acc in emailAccounts" :key="acc.id" :value="acc.id">
            {{ acc.name }} &lt;{{ acc.email }}&gt; {{ acc.is_default ? '(Default)' : '' }}
          </option>
        </select>
        <p v-if="selectedBatchSender" class="text-[11px] text-gray-500">
          Pengirim: <span class="font-medium text-gray-800">{{ selectedBatchSender.name }}</span> ({{ selectedBatchSender.email }})
        </p>
      </div>
    </ConfirmModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { CalendarDate, parseDate } from '@internationalized/date';
import {
  CalendarIcon,
  MailIcon,
  Send as SendIcon,
  Eye as EyeIcon,
  FileText as FileTextIcon,
  MailCheck as MailCheckIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  Search as SearchIcon,
  Filter as FilterIcon,
  Trash2 as Trash2Icon,
  RotateCcw as RotateCcwIcon,
  Bell as BellIcon,
  Check as CheckIcon,
} from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AreaTabs from '@/components/AreaTabs.vue';
import { useArea } from '@/composables/useArea';
import { useBrands } from '@/composables/useBrands';
import { useAutoRefresh, createRequestTracker } from '@/composables/useAutoRefresh';
import { useAuth } from '@/composables/useAuth';
import SendEmailModal from '@/components/SendEmailModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';

// Tombol header: tanpa ikon, satu gaya untuk semua aksi
const headerButtonClass = 'h-9 px-2 sm:px-3.5 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs disabled:opacity-50 transition cursor-pointer whitespace-nowrap';
const headerDangerButtonClass = 'h-9 px-2 sm:px-3.5 inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-xs font-medium text-rose-700 dark:bg-rose-950/40 dark:border-rose-800/80 dark:text-rose-300 dark:hover:bg-rose-900/60 dark:hover:text-rose-100 shadow-2xs disabled:opacity-50 transition cursor-pointer whitespace-nowrap';

const { area } = useArea();
const { brand } = useBrands();
const { canEdit, canDelete } = useAuth();
const deleteRecord = (record) => {
  if (!canDelete.value) return;
  confirmState.title = 'Hapus Invoice';
  confirmState.message = `Apakah Anda yakin ingin menghapus invoice ${record.invoice_number}? Data terkait di Data Program juga akan ikut dihapus.`;
  confirmState.confirmText = 'Ya, Hapus Invoice';
  confirmState.variant = 'danger';
  confirmState.icon = 'trash';
  confirmState.showSenderSelect = false;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      const res = await axios.delete('/api/invoices/' + record.id);
      alertSuccess.value = true;
      alertMessage.value = res.data?.message || 'Invoice dan data program terkait berhasil dihapus.';
      selectedIds.value = selectedIds.value.filter(id => id !== record.id);
      await fetchInvoices(pagination.current_page || 1);
      confirmState.show = false;
    } catch (error) {
      alertSuccess.value = false;
      alertMessage.value = error.response?.data?.message || 'Gagal menghapus invoice.';
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const invoices = ref([]);
const loading = ref(false);
const showEmailModal = ref(false);
const selectedInvoice = ref(null);
const sendingId = ref(null);
const sendingAll = ref(false);
const sendingBatch = ref(false);
const remindingBatch = ref(false);
const deletingBatch = ref(false);
const selectedIds = ref([]);
const alertMessage = ref(null);
const alertSuccess = ref(true);
const isFilterOpen = ref(false);

const defaultFallbackAccounts = [
  { id: 1, name: 'Rebate. MSI', email: 'ade@mediaselulerindonesia.com', is_default: true },
  { id: 2, name: 'Program CS', email: 'admin.scm@completeselular.com', is_default: false },
  { id: 3, name: 'Program MSI', email: 'admin.scm@mediaselulerindonesia.com', is_default: false },
  { id: 4, name: 'Program SMI', email: 'admin.scm@satumediaindonesia.com', is_default: false },
  { id: 5, name: 'Program Top', email: 'admin.scm@topselular.com', is_default: false },
];

const emailAccounts = ref([...defaultFallbackAccounts]);
const batchSenderId = ref(1);

const selectedBatchSender = computed(() => {
  return emailAccounts.value.find(acc => acc.id === batchSenderId.value) || emailAccounts.value[0] || null;
});

const fetchEmailAccounts = async () => {
  try {
    const res = await axios.get('/api/email-accounts');
    if (Array.isArray(res.data.data) && res.data.data.length > 0) {
      emailAccounts.value = res.data.data;
      if (!batchSenderId.value || !emailAccounts.value.some(a => a.id === batchSenderId.value)) {
        const def = emailAccounts.value.find(a => a.is_default);
        batchSenderId.value = def ? def.id : emailAccounts.value[0].id;
      }
    }
  } catch (err) {
    console.error('Failed to fetch email accounts', err);
  }
};

const googleStatus = reactive({
  is_configured: false,
  is_connected: false,
  account_email: null,
});

const fetchGoogleStatus = async () => {
  try {
    const res = await axios.get('/api/google/status');
    Object.assign(googleStatus, res.data);
  } catch (err) {
    console.error('Failed to fetch Google status', err);
  }
};

const disconnectingGoogle = ref(false);
const disconnectGoogle = async () => {
  if (!confirm('Putuskan koneksi akun Google / Gmail ini?')) return;
  disconnectingGoogle.value = true;
  try {
    await axios.post('/api/google/disconnect');
    await fetchGoogleStatus();
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal memutuskan koneksi Google.');
  } finally {
    disconnectingGoogle.value = false;
  }
};

const confirmState = reactive({
  show: false,
  title: '',
  message: '',
  confirmText: 'Ya, Kirim Sekarang',
  loading: false,
  showSenderSelect: false,
  variant: 'default',
  icon: 'mail',
  action: null,
});

const onConfirmAction = async () => {
  if (confirmState.action) {
    await confirmState.action();
  }
};

const isFullySent = (inv) => {
  return !!(inv.email_sent_at && inv.whatsapp_sent_at);
};

const isAlreadySent = (inv) => {
  return inv.status === 'sent' || (!!inv.email_sent_at && !!inv.whatsapp_sent_at);
};

// Semua invoice di halaman dapat dipilih untuk aksi bulk (mis. hapus banyak atau kirim yang belum terkirim)
const selectableInvoices = computed(() => invoices.value);

const unsentSelectedInvoices = computed(() => {
  return invoices.value.filter(inv => selectedIds.value.includes(inv.id) && !isAlreadySent(inv));
});

const unsentSelectedCount = computed(() => unsentSelectedInvoices.value.length);
const hasUnsentSelected = computed(() => unsentSelectedCount.value > 0);

const sentSelectedInvoices = computed(() => {
  return invoices.value.filter(inv => selectedIds.value.includes(inv.id) && isAlreadySent(inv));
});
const sentSelectedCount = computed(() => sentSelectedInvoices.value.length);
const hasSentSelected = computed(() => sentSelectedCount.value > 0);

const unuploadedSelectedInvoices = computed(() => {
  return invoices.value.filter(inv => selectedIds.value.includes(inv.id) && !inv.is_uploaded_to_form);
});
const unuploadedSelectedCount = computed(() => unuploadedSelectedInvoices.value.length);

const isAllSelected = computed(() => {
  return selectableInvoices.value.length > 0 &&
    selectableInvoices.value.every(inv => selectedIds.value.includes(inv.id));
});

const isIndeterminate = computed(() => {
  const selectedCount = selectableInvoices.value.filter(inv => selectedIds.value.includes(inv.id)).length;
  return selectedCount > 0 && selectedCount < selectableInvoices.value.length;
});

const toggleSelectAll = (e) => {
  if (!canEdit.value && !canDelete.value) return;
  if (e.target.checked) {
    const newIds = new Set([...selectedIds.value, ...selectableInvoices.value.map(i => i.id)]);
    selectedIds.value = Array.from(newIds);
  } else {
    const selectableIdSet = new Set(selectableInvoices.value.map(i => i.id));
    selectedIds.value = selectedIds.value.filter(id => !selectableIdSet.has(id));
  }
};

const askDeleteBatch = () => {
  if (!canDelete.value || selectedIds.value.length === 0 || deletingBatch.value) return;
  confirmState.title = 'Hapus Invoice Terpilih';
  confirmState.message = `Apakah Anda yakin ingin menghapus ${selectedIds.value.length} invoice terpilih? Data terkait di Data Program juga akan ikut dihapus.`;
  confirmState.confirmText = `Ya, Hapus (${selectedIds.value.length}) Invoice`;
  confirmState.variant = 'danger';
  confirmState.icon = 'trash';
  confirmState.showSenderSelect = false;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await executeDeleteBatch();
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const executeDeleteBatch = async () => {
  if (!canDelete.value) return;
  deletingBatch.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/delete-batch', {
      ids: selectedIds.value,
    });
    alertSuccess.value = true;
    alertMessage.value = res.data.message || `Berhasil menghapus ${selectedIds.value.length} invoice beserta data di Data Program.`;
    selectedIds.value = [];
    await fetchInvoices(pagination.current_page || 1);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal menghapus batch invoice.';
  } finally {
    deletingBatch.value = false;
  }
};

const openEmailModal = (inv) => {
  if (!canEdit.value) return;
  selectedInvoice.value = inv;
  showEmailModal.value = true;
};

const onEmailSent = (payload) => {
  alertSuccess.value = true;
  alertMessage.value = `Notifikasi invoice berhasil diproses dan dikirim.`;
  const inv = invoices.value.find(i => i.id === payload.invoiceId);
  if (inv) {
    inv.status = 'sent';
    if (payload.email) inv.email_sent_at = new Date().toISOString();
    if (payload.whatsapp) inv.whatsapp_sent_at = new Date().toISOString();
  }
  selectedIds.value = selectedIds.value.filter(id => id !== payload.invoiceId);
};

const askSendSingle = (inv) => {
  if (!canEdit.value || isAlreadySent(inv)) return;
  const targetEmail = inv.email || inv.draft?.email;
  const targetWa = inv.whatsapp || inv.draft?.whatsapp;
  const destinations = [];
  if (targetEmail) destinations.push(`Email (${targetEmail})`);
  if (targetWa) destinations.push(`WhatsApp (${targetWa})`);

  confirmState.title = 'Kirim Notifikasi Invoice';
  confirmState.message = `Apakah Anda yakin ingin mengirim invoice ${inv.invoice_number} ke ${destinations.join(' & ')}?`;
  confirmState.confirmText = 'Ya, Kirim Sekarang';
  confirmState.variant = 'default';
  confirmState.icon = 'mail';
  confirmState.showSenderSelect = false;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await quickSendInvoice(inv);
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const quickSendInvoice = async (inv) => {
  if (!canEdit.value || sendingId.value || isAlreadySent(inv)) return;
  sendingId.value = inv.id;
  alertMessage.value = null;

  try {
    const res = await axios.post(`/api/invoices/${inv.id}/quick-send-email`);
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    inv.status = 'sent';
    if (inv.email || inv.draft?.email) inv.email_sent_at = new Date().toISOString();
    if (inv.whatsapp || inv.draft?.whatsapp) inv.whatsapp_sent_at = new Date().toISOString();
    selectedIds.value = selectedIds.value.filter(id => id !== inv.id);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal mengirim notifikasi invoice';
  } finally {
    sendingId.value = null;
  }
};

const askSendBatch = () => {
  if (!canEdit.value || unsentSelectedCount.value === 0 || sendingBatch.value) return;
  confirmState.title = 'Kirim Batch Notifikasi (Email & WA)';
  confirmState.message = `Kirim ${unsentSelectedCount.value} invoice terpilih? Email dikirim ke masing-masing dealer. WhatsApp dikirim ke telemarketing per region: invoice satu region dikirim dalam satu file ZIP (satu PDF per invoice).`;
  confirmState.confirmText = `Ya, Kirim (${unsentSelectedCount.value}) Invoice`;
  confirmState.variant = 'default';
  confirmState.icon = 'mail';
  confirmState.showSenderSelect = true;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await executeSendBatch();
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const executeSendBatch = async () => {
  if (!canEdit.value) return;
  sendingBatch.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/send-batch', {
      ids: unsentSelectedInvoices.value.map(i => i.id),
      sender_id: batchSenderId.value,
    });
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    selectedIds.value = [];
    fetchInvoices(pagination.current_page);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal memproses pengiriman batch.';
  } finally {
    sendingBatch.value = false;
  }
};

const askResendBatch = () => {
  if (!canEdit.value || sentSelectedCount.value === 0 || sendingBatch.value) return;
  confirmState.title = 'Kirim Ulang Batch Notifikasi (Email & WA)';
  confirmState.message = `Kirim ulang notifikasi untuk ${sentSelectedCount.value} invoice terpilih ke Email & WhatsApp? Pengiriman ulang ini tidak akan memasukkan data baru ke Data Program.`;
  confirmState.confirmText = `Ya, Kirim Ulang (${sentSelectedCount.value}) Invoice`;
  confirmState.variant = 'default';
  confirmState.icon = 'mail';
  confirmState.showSenderSelect = true;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await executeResendBatch();
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const executeResendBatch = async () => {
  if (!canEdit.value) return;
  sendingBatch.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/send-batch', {
      ids: sentSelectedInvoices.value.map(i => i.id),
      sender_id: batchSenderId.value,
      resend: true,
    });
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    selectedIds.value = [];
    fetchInvoices(pagination.current_page);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal memproses pengiriman ulang batch.';
  } finally {
    sendingBatch.value = false;
  }
};

const askRemindUploadSingle = (inv) => {
  if (!canEdit.value) return;
  confirmState.title = 'Ingatkan Upload Dokumen';
  confirmState.message = `Kirim pesan WhatsApp ke Telemarketing untuk mengingatkan upload invoice & credit note ${inv.invoice_number} (${inv.dealer_name}) ke Google Form / Form Program?`;
  confirmState.confirmText = 'Ya, Kirim Pengingat WA';
  confirmState.variant = 'default';
  confirmState.icon = 'mail';
  confirmState.showSenderSelect = false;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      const res = await axios.post(`/api/invoices/${inv.id}/remind-upload`);
      alertSuccess.value = true;
      alertMessage.value = res.data.message || 'Pengingat upload berhasil dikirim ke Telemarketing.';
      confirmState.show = false;
    } catch (err) {
      alertSuccess.value = false;
      alertMessage.value = err.response?.data?.message || 'Gagal mengirim pengingat upload.';
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const askRemindUploadBatch = () => {
  if (!canEdit.value || unuploadedSelectedCount.value === 0 || remindingBatch.value) return;
  confirmState.title = 'Ingatkan Upload Dokumen (Batch)';
  confirmState.message = `Kirim pesan WhatsApp ke masing-masing Telemarketing region untuk mengingatkan upload ${unuploadedSelectedCount.value} invoice terpilih ke Google Form / Form Program? Pesan akan dirangkum per region.`;
  confirmState.confirmText = `Ya, Ingatkan (${unuploadedSelectedCount.value}) Invoice`;
  confirmState.variant = 'default';
  confirmState.icon = 'mail';
  confirmState.showSenderSelect = false;
  confirmState.action = async () => {
    confirmState.loading = true;
    remindingBatch.value = true;
    try {
      const res = await axios.post('/api/invoices/remind-upload-batch', {
        ids: unuploadedSelectedInvoices.value.map(i => i.id),
      });
      alertSuccess.value = true;
      alertMessage.value = res.data.message || 'Pengingat upload berhasil dikirim ke Telemarketing.';
      selectedIds.value = [];
      confirmState.show = false;
    } catch (err) {
      alertSuccess.value = false;
      alertMessage.value = err.response?.data?.message || 'Gagal mengirim pengingat upload massal.';
    } finally {
      confirmState.loading = false;
      remindingBatch.value = false;
    }
  };
  confirmState.show = true;
};

const askSendAll = () => {
  if (!canEdit.value || sendingAll.value) return;
  confirmState.title = 'Kirim Semua Notifikasi Invoice';
  confirmState.message = 'Kirim semua invoice yang belum terkirim? Email dikirim ke masing-masing dealer. WhatsApp dikirim ke telemarketing per region: invoice satu region dikirim dalam satu file ZIP (satu PDF per invoice).';
  confirmState.confirmText = 'Ya, Kirim Semua';
  confirmState.variant = 'default';
  confirmState.icon = 'mail';
  confirmState.showSenderSelect = true;
  confirmState.action = async () => {
    confirmState.loading = true;
    try {
      await executeSendAll();
      confirmState.show = false;
    } finally {
      confirmState.loading = false;
    }
  };
  confirmState.show = true;
};

const executeSendAll = async () => {
  if (!canEdit.value) return;
  sendingAll.value = true;
  alertMessage.value = null;

  try {
    const res = await axios.post('/api/invoices/quick-send-all', {
      sender_id: batchSenderId.value,
    });
    alertSuccess.value = true;
    alertMessage.value = res.data.message;
    selectedIds.value = [];
    fetchInvoices(pagination.current_page);
  } catch (err) {
    alertSuccess.value = false;
    alertMessage.value = err.response?.data?.message || 'Gagal memproses pengiriman massal';
  } finally {
    sendingAll.value = false;
  }
};

const filters = reactive({
  search: '',
  invoice_type: '',
  status: '',
  upload_status: '',
  date: '',
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.invoice_type) count++;
  if (filters.status) count++;
  if (filters.upload_status) count++;
  if (filters.date) count++;
  return count;
});

const setUploadFilter = (status) => {
  if (filters.upload_status === status) return;
  filters.upload_status = status;
  fetchInvoices(1);
};

const selectedCalendarDate = computed(() => {
  if (!filters.date) return undefined;
  try { return parseDate(filters.date); } catch { return undefined; }
});

const onDateSelect = (val) => {
  if (!val) { filters.date = ''; fetchInvoices(1); return; }
  filters.date = val.toString();
  fetchInvoices(1);
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

const totalNetpay = computed(() => {
  return invoices.value.reduce((acc, i) => acc + (Number(i.netpay) || 0), 0);
});

let debounceTimeout = null;
const debounceFetch = () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchInvoices(1);
  }, 300);
};

const startInvoicesRequest = createRequestTracker();

const fetchInvoices = async (page = 1, { silent = false } = {}) => {
  const isLatest = startInvoicesRequest();
  if (!silent) loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      ...filters,
    };
    const res = await axios.get('/api/invoices', { params });
    if (!isLatest()) return;
    invoices.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.per_page = res.data.per_page;
    pagination.total = res.data.total;
  } catch (err) {
    console.error('Error fetching invoices', err);
  } finally {
    if (!silent && isLatest()) loading.value = false;
  }
};

// Daftar invoice dimuat ulang otomatis; berhenti selama modal terbuka atau pengiriman berjalan
useAutoRefresh(() => fetchInvoices(pagination.current_page, { silent: true }), {
  isPaused: () => loading.value || showEmailModal.value || confirmState.show || sendingAll.value || sendingBatch.value || deletingBatch.value || sendingId.value !== null,
});

const resetFilters = () => {
  filters.search = '';
  filters.invoice_type = '';
  filters.status = '';
  filters.upload_status = '';
  filters.date = '';
  isFilterOpen.value = false;
  fetchInvoices(1);
};

const formatCurrency = (val) => {
  const num = Math.round(Number(val) || 0);
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
};

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('google_connected')) {
    alertSuccess.value = true;
    alertMessage.value = `Akun Google Workspace (${urlParams.get('account') || ''}) berhasil terhubung!`;
    window.history.replaceState({}, document.title, window.location.pathname);
  } else if (urlParams.get('google_error')) {
    alertSuccess.value = false;
    alertMessage.value = `Gagal menghubungkan akun Google: ${urlParams.get('google_error')}`;
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  fetchInvoices(1);
  fetchGoogleStatus();
  // Akun pengirim hanya dipakai untuk mengirim invoice
  if (canEdit.value) fetchEmailAccounts();
});
</script>
