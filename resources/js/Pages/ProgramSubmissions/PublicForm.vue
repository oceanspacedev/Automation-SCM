<template>
  <div class="min-h-screen bg-[#f0f4f9] py-6 sm:py-10 px-3 sm:px-6 font-sans text-gray-900 selection:bg-blue-100 selection:text-blue-900">
    <div class="max-w-2xl mx-auto space-y-4">
      
      <!-- ================= SUCCESS SCREEN (GOOGLE FORM STYLE) ================= -->
      <div v-if="isSubmitted" class="space-y-4 animate-in fade-in duration-200">
        <!-- Header Card -->
        <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
          <div class="h-2.5 bg-[#1a73e8]"></div>
          <div class="p-6 sm:p-8 space-y-4">
            <h1 class="text-2xl sm:text-3xl font-normal text-gray-950 tracking-tight">
              PROGRAM {{ brandLabel }} {{ area.short }}
            </h1>
            <p class="text-sm text-gray-800 font-normal">
              Tanggapan Anda telah direkam.
            </p>
            <div class="pt-2">
              <a
                href="#"
                @click.prevent="resetFormToSubmitAnother"
                class="text-xs text-blue-600 hover:text-blue-800 underline cursor-pointer"
              >
                Kirim tanggapan lain
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= FORM VIEW (GOOGLE FORM LAYOUT) ================= -->
      <form v-else @submit.prevent="handleSubmit" novalidate class="space-y-4">
        
        <!-- HEADER CARD -->
        <!-- HEADER CARD (GOOGLE FORM STYLE) -->
        <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
          <!-- Top Orange Bar -->
          <div class="h-2.5 bg-[#1a73e8]"></div>
          
          <div class="p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-normal text-gray-950 tracking-tight">
              PROGRAM {{ brandLabel }} {{ area.short }}
            </h1>
          </div>
        </div>

        <!-- CARD 1: ID REALME (diisi pertama: toko yang dipilih mengisi nama dealer, region, WA & agreement) -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition relative"
          :class="validationErrors.id_real ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <label class="block text-sm sm:text-base font-medium text-gray-900">
            ID REALME <span class="text-rose-500">*</span>
          </label>

          <p class="text-xs text-gray-500">
            Pilih toko dari Master Dealer, cari pakai kode BT, ID Realme, atau nama toko. Nama dealer dan region terisi otomatis.
          </p>

          <!-- Toko hanya bisa dipilih dari Master Dealer (tidak bisa diketik manual) -->
          <div class="pt-1 relative w-full sm:max-w-md" ref="dealerDropdownRef">
            <button
              type="button"
              @click="toggleDealerDropdown"
              :class="[pickerTriggerClass, validationErrors.id_real ? 'border-rose-500' : isDealerDropdownOpen ? 'border-[#1a73e8]' : 'border-gray-300 hover:border-gray-400']"
            >
              <span v-if="form.id_real" class="flex items-baseline gap-2 min-w-0 flex-1">
                <span class="text-gray-900 truncate">{{ form.dealer_name || form.id_real }}</span>
                <span
                  v-if="form.dealer_name && form.dealer_name.toLowerCase() !== form.id_real.toLowerCase()"
                  class="text-[11px] sm:text-xs text-gray-500 shrink-0"
                >{{ form.id_real }}</span>
              </span>
              <span v-else class="text-gray-400 flex-1">Pilih toko</span>

              <span class="flex items-center gap-1 shrink-0">
                <span
                  v-if="form.id_real"
                  role="button"
                  tabindex="0"
                  title="Hapus pilihan"
                  @click.stop="clearSelectedDealer"
                  @keydown.enter.stop.prevent="clearSelectedDealer"
                  class="p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100"
                >
                  <XIcon class="w-3.5 h-3.5" />
                </span>
                <ChevronDownIcon
                  class="w-4 h-4 text-gray-400 transition-transform duration-200"
                  :class="isDealerDropdownOpen && 'rotate-180'"
                />
              </span>
            </button>

            <!-- Dropdown Menu -->
            <div v-if="isDealerDropdownOpen" :class="pickerPanelClass">
              <!-- Search -->
              <div class="relative border-b border-gray-100">
                <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  type="text"
                  v-model="dealerSearchQuery"
                  @input="handleDealerSearchQueryInput"
                  placeholder="Cari kode BT, ID Realme, atau nama toko"
                  :class="pickerSearchClass"
                />
                <button
                  v-if="dealerSearchQuery"
                  type="button"
                  @click="clearDealerSearch"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                  <XIcon class="w-3.5 h-3.5" />
                </button>
              </div>

              <!-- Region filter -->
              <div class="flex items-center justify-between gap-2 px-3.5 py-2 text-xs text-gray-500">
                <span class="truncate">
                  {{ filterDealersByRegion && form.region ? form.region : 'Semua wilayah' }}
                  · {{ dealerTotal.toLocaleString('id-ID') }} toko
                </span>
                <button
                  v-if="form.region"
                  type="button"
                  @click="toggleRegionFilter"
                  class="shrink-0 text-gray-600 hover:text-gray-900 underline cursor-pointer"
                >
                  {{ filterDealersByRegion ? 'Semua wilayah' : `Hanya ${form.region}` }}
                </button>
              </div>

              <!-- List of Dealers (toko berikutnya dimuat saat digulir ke bawah) -->
              <div :class="pickerListClass" @scroll.passive="handleDealerListScroll">
                <button
                  v-for="item in dealerSuggestions"
                  :key="item.id"
                  type="button"
                  @click="selectDealerSuggestion(item)"
                  :class="[pickerOptionClass, form.id_real === item.id_realme && 'bg-[#e8f0fe]']"
                >
                  <span class="min-w-0 flex-1">
                    <span class="block text-gray-900 truncate">
                      {{ (item.dealer_name && item.dealer_name !== '.' && item.dealer_name !== '-') ? item.dealer_name : item.id_realme }}
                    </span>
                    <!-- Kode BT · ID Realme (· region bila menampilkan semua wilayah) -->
                    <span class="block mt-0.5 text-[11px] sm:text-xs text-gray-500 truncate">
                      {{ [item.kode_bt, item.id_realme !== item.kode_bt ? item.id_realme : null, !(filterDealersByRegion && form.region) ? item.region : null].filter(Boolean).join(' · ') }}
                    </span>
                  </span>
                  <CheckIcon v-if="form.id_real === item.id_realme" class="w-4 h-4 text-[#1a73e8] shrink-0" />
                </button>

                <p v-if="isLoadingDealers" class="px-3.5 py-3 text-center text-xs text-gray-400">Memuat toko...</p>
                <p v-else-if="dealerSuggestions.length === 0" class="px-3.5 py-6 text-center text-xs sm:text-sm text-gray-500">
                  Toko tidak ditemukan.
                </p>
              </div>

              <p class="px-3.5 py-2.5 border-t border-gray-100 text-xs sm:text-sm text-gray-500">
                Toko tidak ada di daftar? Minta admin menambahkannya ke Master Dealer.
              </p>
            </div>
          </div>

          <div v-if="validationErrors.id_real" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.id_real }}</span>
          </div>
        </div>

        <!-- CARD 2: NAMA DEALER -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition"
          :class="validationErrors.dealer_name ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              NAMA DEALER <span class="text-rose-500">*</span>
            </label>
            <span
              v-if="isAutoFilledFromMaster"
              class="text-xs text-gray-500 flex items-center gap-1 font-normal"
            >
              <CheckIcon class="w-3.5 h-3.5 text-gray-400" /> Terisi otomatis dari Master Dealer
            </span>
            <span
              v-else-if="isAutoFilledDealer"
              class="text-xs text-gray-500 flex items-center gap-1 font-normal"
            >
              <CheckIcon class="w-3.5 h-3.5 text-gray-400" /> Terisi otomatis
            </span>
          </div>

          <div class="pt-1">
            <input
              type="text"
              v-model="form.dealer_name"
              @input="isAutoFilledDealer = false; isAutoFilledFromMaster = false"
              @blur="validateField('dealer_name')"
              placeholder="Jawaban Anda"
              class="w-full text-xs sm:text-sm border-b pb-1.5 focus:outline-none transition bg-transparent placeholder:text-gray-400 text-gray-900 font-normal"
              :class="validationErrors.dealer_name ? 'border-rose-500' : 'border-gray-300 focus:border-[#1a73e8]'"
            />
          </div>

          <div v-if="validationErrors.dealer_name" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.dealer_name }}</span>
          </div>
        </div>

        <!-- CARD 3: REGION (terisi otomatis dari toko) -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.region ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-1">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <label class="block text-sm sm:text-base font-medium text-gray-900">
                REGION <span class="text-rose-500">*</span>
              </label>
              <span
                v-if="isAutoFilledRegion"
                class="text-xs text-gray-500 flex items-center gap-1 font-normal"
              >
                <CheckIcon class="w-3.5 h-3.5 text-gray-400" /> Terisi otomatis dari Master Dealer
              </span>
            </div>
            <p class="text-xs text-gray-500">Pilih wilayah cabang operasional toko.</p>
          </div>

          <div class="space-y-2.5 pt-1">
            <label
              v-for="reg in regionOptions"
              :key="reg"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition group"
            >
              <input
                type="radio"
                name="region_radio"
                :value="reg"
                v-model="form.region"
                class="w-4 h-4 accent-[#1a73e8] border-gray-300 cursor-pointer"
                @change="validationErrors.region = ''; isAutoFilledRegion = false"
              />
              <span
                class="text-xs sm:text-sm text-gray-800 transition"
                :class="form.region === reg ? 'font-semibold text-gray-950' : 'font-normal'"
              >
                {{ reg }}
              </span>
            </label>
          </div>

          <div v-if="validationErrors.region" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.region }}</span>
          </div>
        </div>

        <!-- CARD 4: NAMA PROGRAM -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.program_name ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-1">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              NAMA PROGRAM <span class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500">Pilih salah satu program {{ brand.name }} yang diajukan.</p>
          </div>

          <div class="space-y-2.5 pt-1">
            <!-- Dua kolom di layar lebar (urut dari atas ke bawah), satu kolom di HP -->
            <div class="sm:columns-2 sm:gap-x-4">
            <label
              v-for="prog in defaultProgramsList"
              :key="prog"
              class="flex items-start gap-3 p-2 mb-1 rounded-lg hover:bg-gray-50 cursor-pointer transition group break-inside-avoid"
            >
              <input
                type="radio"
                name="program_name_radio"
                :value="prog"
                v-model="form.program_name"
                class="w-4 h-4 mt-0.5 shrink-0 accent-[#1a73e8] border-gray-300 cursor-pointer"
                @change="handleProgramChange(prog)"
              />
              <span
                class="text-xs sm:text-sm text-gray-800 leading-snug transition"
                :class="form.program_name === prog ? 'font-semibold text-gray-950' : 'font-normal'"
              >
                {{ prog }}
              </span>
            </label>
            </div>
          </div>

          <div v-if="validationErrors.program_name" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.program_name }}</span>
          </div>
        </div>

        <!-- CARD 5: NAMA SALES (DM) -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition relative"
          :class="validationErrors.sales_name ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              NAMA SALES (DM) <span class="text-rose-500">*</span>
            </label>
            <span v-if="isAutoFilledSales" class="text-xs text-gray-500 flex items-center gap-1 font-normal">
              <CheckIcon class="w-3.5 h-3.5 text-gray-400" /> Terisi otomatis dari Master Dealer
            </span>
          </div>

          <!-- Pemilih sales: gaya sama dengan pemilih toko -->
          <div class="pt-1 relative w-full sm:max-w-md" ref="salesDropdownRef">
            <button
              type="button"
              @click="isSalesDropdownOpen = !isSalesDropdownOpen"
              :class="[pickerTriggerClass, validationErrors.sales_name ? 'border-rose-500' : isSalesDropdownOpen ? 'border-[#1a73e8]' : 'border-gray-300 hover:border-gray-400']"
            >
              <span class="truncate flex-1" :class="form.sales_name ? 'text-gray-900' : 'text-gray-400'">
                {{ form.sales_name || 'Pilih sales' }}
              </span>
              <ChevronDownIcon class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200" :class="isSalesDropdownOpen && 'rotate-180'" />
            </button>

            <div v-if="isSalesDropdownOpen" :class="pickerPanelClass">
              <div class="relative border-b border-gray-100">
                <SearchIcon class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  type="text"
                  v-model="salesSearchQuery"
                  placeholder="Cari nama sales"
                  :class="pickerSearchClass"
                />
              </div>

              <div :class="pickerListClass">
                <button
                  v-for="sales in filteredSalesList"
                  :key="sales"
                  type="button"
                  @click="selectSales(sales)"
                  :class="[pickerOptionClass, form.sales_name === sales && 'bg-[#e8f0fe]']"
                >
                  <span class="min-w-0 flex-1 truncate text-gray-900">{{ sales }}</span>
                  <CheckIcon v-if="form.sales_name === sales" class="w-4 h-4 text-[#1a73e8] shrink-0" />
                </button>

                <p v-if="filteredSalesList.length === 0" class="px-3.5 py-6 text-center text-xs sm:text-sm text-gray-500">
                  Sales tidak ditemukan. Ketik nama di bawah.
                </p>
              </div>

              <!-- Sales baru (belum ada di daftar) -->
              <div class="px-3.5 py-2.5 border-t border-gray-100 flex items-center gap-2">
                <input
                  type="text"
                  v-model="form.sales_custom"
                  placeholder="Atau ketik sales baru"
                  class="flex-1 min-w-0 h-8 px-2.5 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 border border-gray-300 rounded-md focus:outline-none focus:border-[#1a73e8]"
                />
                <button
                  type="button"
                  @click="applyCustomSales"
                  class="h-8 px-3 text-xs font-medium bg-gray-900 text-white rounded-md hover:bg-gray-800 transition cursor-pointer shrink-0"
                >
                  Gunakan
                </button>
              </div>
            </div>
          </div>

          <div v-if="validationErrors.sales_name" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.sales_name }}</span>
          </div>
        </div>

        <!-- CARD 6: NO. WHATSAPP (AKTIF) -->
        <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-3">
          <div>
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              NO. WHATSAPP SALES / TOKO (AKTIF)
            </label>
            <p class="text-xs text-gray-500 mt-0.5">
              Untuk pengiriman notifikasi otomatis konfirmasi status potong klaim dari sistem SCM.
            </p>
          </div>

          <div class="pt-1">
            <input
              type="tel"
              v-model="form.whatsapp"
              placeholder="Contoh: 081224290502"
              class="w-full text-xs sm:text-sm border-b pb-1.5 focus:outline-none border-gray-300 focus:border-[#1a73e8] transition bg-transparent placeholder:text-gray-400"
            />
          </div>
        </div>

        <!-- CARD 6B: EMAIL TOKO (wajib bila toko belum punya email di Master Dealer, lalu tersimpan ke sana) -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition"
          :class="validationErrors.email ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div>
            <label for="store-email" class="block text-sm sm:text-base font-medium text-gray-900">
              EMAIL TOKO <span v-if="emailRequired" class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mt-0.5">{{ emailHint }}</p>
          </div>

          <div class="pt-1">
            <input
              id="store-email"
              type="email"
              inputmode="email"
              autocomplete="email"
              v-model="form.email"
              @input="validationErrors.email = ''"
              placeholder="Contoh: toko@gmail.com"
              class="w-full text-xs sm:text-sm border-b pb-1.5 focus:outline-none transition bg-transparent placeholder:text-gray-400"
              :class="validationErrors.email ? 'border-rose-400 focus:border-rose-500' : 'border-gray-300 focus:border-[#1a73e8]'"
            />
          </div>

          <div v-if="validationErrors.email" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.email }}</span>
          </div>
        </div>

        <!-- ================= DOKUMEN UPLOAD CARDS ================= -->

        <!-- CARD 7: DOKUMEN CREDIT NOTE/INVOICE * -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.credit_note ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-2">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              DOKUMEN CREDIT NOTE/INVOICE <span class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500">
              Upload 1 file yang didukung: PDF, document, drawing, atau image. Maks 10 MB.
            </p>
          </div>

          <!-- File Upload Trigger & Preview -->
          <div class="space-y-2">
            <input
              type="file"
              ref="cnFileInputRef"
              @change="handleFileChange($event, 'credit_note')"
              accept=".pdf,.jpg,.jpeg,.png,.webp"
              class="hidden"
            />

            <!-- Selected File Display Chip (Clean Google Form Style) -->
            <div
              v-if="filePreviews.credit_note"
              class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white text-xs"
            >
              <div class="flex items-center gap-2.5 min-w-0 pr-2">
                <FileTextIcon class="w-5 h-5 text-gray-500 shrink-0" />
                <div class="min-w-0">
                  <p class="font-medium text-gray-900 truncate">{{ filePreviews.credit_note.name }}</p>
                  <p class="text-[11px] text-gray-400">{{ formatFileSize(filePreviews.credit_note.size) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button
                  type="button"
                  @click="openFilePicker('cn')"
                  class="text-xs text-gray-600 hover:text-gray-900 underline cursor-pointer"
                >
                  Ganti
                </button>
                <button
                  type="button"
                  @click="removeFile('credit_note')"
                  class="p-1 text-gray-400 hover:text-rose-600 rounded-md transition cursor-pointer"
                  title="Hapus file"
                >
                  <XIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Upload Button (Google Form Style) -->
            <button
              v-else
              type="button"
              @click="openFilePicker('cn')"
              class="h-9 px-4 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer"
            >
              <UploadCloudIcon class="w-4 h-4 text-gray-500" />
              <span>Tambahkan file</span>
            </button>
          </div>

          <!-- Status pemeriksaan dokumen (detail ada di Hasil Pemeriksaan di atas tombol Kirim) -->
          <p v-if="docStatus('cn') === 'issue'" class="flex items-center gap-1.5 text-xs text-rose-600">
            <AlertCircleIcon class="w-3.5 h-3.5 shrink-0" />
            <span>Perlu diperbaiki ·</span>
            <a href="#hasil-pemeriksaan" @click.prevent="scrollToAnalysis" class="underline hover:text-rose-800">lihat hasil pemeriksaan</a>
          </p>
          <p v-else-if="docStatus('cn') === 'ok'" class="flex items-center gap-1.5 text-xs text-emerald-700">
            <CheckCircle2Icon class="w-3.5 h-3.5 shrink-0" />
            <span>Sesuai</span>
          </p>

          <div v-if="validationErrors.credit_note" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.credit_note }}</span>
          </div>
        </div>

        <!-- CARD 8: AGREEMENT * -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.agreement ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-2">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              AGREEMENT <span v-if="!usesMasterAgreement" class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500">
              {{ usesMasterAgreement
                ? 'Agreement toko ini sudah ada di data master, jadi tidak perlu diunggah. Unggah file hanya kalau agreement-nya mau diganti.'
                : 'Upload 1 file yang didukung: PDF, drawing, atau image. Maks 10 GB.' }}
            </p>
          </div>

          <!-- Agreement dari data master (Master Dealer); link-nya tidak ditampilkan di form publik -->
          <p
            v-if="usesMasterAgreement && !filePreviews.agreement"
            class="px-3 py-2.5 rounded-lg border border-emerald-200 bg-emerald-50 text-xs text-emerald-800"
          >
            Memakai agreement {{ form.dealer_name || 'toko ini' }} dari data master.
          </p>

          <!-- File Upload Trigger & Preview -->
          <div class="space-y-2">
            <input
              type="file"
              ref="agrFileInputRef"
              @change="handleFileChange($event, 'agreement')"
              accept=".pdf,.jpg,.jpeg,.png,.webp"
              class="hidden"
            />

            <!-- Selected File Display Chip -->
            <div
              v-if="filePreviews.agreement"
              class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white text-xs"
            >
              <div class="flex items-center gap-2.5 min-w-0 pr-2">
                <FileTextIcon class="w-5 h-5 text-gray-500 shrink-0" />
                <div class="min-w-0">
                  <p class="font-medium text-gray-900 truncate">{{ filePreviews.agreement.name }}</p>
                  <p class="text-[11px] text-gray-400">{{ formatFileSize(filePreviews.agreement.size) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button
                  type="button"
                  @click="openFilePicker('agr')"
                  class="text-xs text-gray-600 hover:text-gray-900 underline cursor-pointer"
                >
                  Ganti
                </button>
                <button
                  type="button"
                  @click="removeFile('agreement')"
                  class="p-1 text-gray-400 hover:text-rose-600 rounded-md transition cursor-pointer"
                  title="Hapus file"
                >
                  <XIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Upload Button -->
            <button
              v-else
              type="button"
              @click="openFilePicker('agr')"
              class="h-9 px-4 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer"
            >
              <UploadCloudIcon class="w-4 h-4 text-gray-500" />
              <span>{{ usesMasterAgreement ? 'Ganti agreement' : 'Tambahkan file' }}</span>
            </button>
          </div>

          <div v-if="validationErrors.agreement" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.agreement }}</span>
          </div>
        </div>

        <!-- CARD 9: FAKTUR PAJAK (OPTIONAL / PKP) -->
        <div
          class="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-4 transition"
        >
          <div class="space-y-2">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              FAKTUR PAJAK <span v-if="dealerTaxStatus?.isPkp" class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500">
              Upload 1 file yang didukung: PDF, drawing, atau image. Maks 10 GB.
              <em v-if="!dealerTaxStatus">(Opsional untuk toko Non-PKP, wajib bagi PKP).</em>
            </p>
            <p v-if="dealerTaxStatus" class="text-xs text-gray-600">
              Jenis NPWP toko: <span class="font-medium text-gray-900">{{ dealerTaxStatus.npwpType }}</span>
              · {{ dealerTaxStatus.isPkp ? 'PKP, wajib upload Faktur Pajak.' : 'Non-PKP, Faktur Pajak tidak wajib.' }}
            </p>
          </div>

          <!-- File Upload Trigger & Preview -->
          <div class="space-y-2">
            <input
              type="file"
              ref="taxFileInputRef"
              @change="handleFileChange($event, 'tax_invoice')"
              accept=".pdf,.jpg,.jpeg,.png,.webp"
              class="hidden"
            />

            <!-- Selected File Display Chip -->
            <div
              v-if="filePreviews.tax_invoice"
              class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white text-xs"
            >
              <div class="flex items-center gap-2.5 min-w-0 pr-2">
                <FileTextIcon class="w-5 h-5 text-gray-500 shrink-0" />
                <div class="min-w-0">
                  <p class="font-medium text-gray-900 truncate">{{ filePreviews.tax_invoice.name }}</p>
                  <p class="text-[11px] text-gray-400">{{ formatFileSize(filePreviews.tax_invoice.size) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button
                  type="button"
                  @click="openFilePicker('faktur')"
                  class="text-xs text-gray-600 hover:text-gray-900 underline cursor-pointer"
                >
                  Ganti
                </button>
                <button
                  type="button"
                  @click="removeFile('tax_invoice')"
                  class="p-1 text-gray-400 hover:text-rose-600 rounded-md transition cursor-pointer"
                  title="Hapus file"
                >
                  <XIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Upload Button -->
            <button
              v-else
              type="button"
              @click="openFilePicker('faktur')"
              class="h-9 px-4 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer"
            >
              <UploadCloudIcon class="w-4 h-4 text-gray-500" />
              <span>Tambahkan file</span>
            </button>
          </div>

          <!-- Status pemeriksaan dokumen (detail ada di Hasil Pemeriksaan di atas tombol Kirim) -->
          <p v-if="docStatus('faktur') === 'issue'" class="flex items-center gap-1.5 text-xs text-rose-600">
            <AlertCircleIcon class="w-3.5 h-3.5 shrink-0" />
            <span>Perlu diperbaiki ·</span>
            <a href="#hasil-pemeriksaan" @click.prevent="scrollToAnalysis" class="underline hover:text-rose-800">lihat hasil pemeriksaan</a>
          </p>
          <p v-else-if="docStatus('faktur') === 'ok'" class="flex items-center gap-1.5 text-xs text-emerald-700">
            <CheckCircle2Icon class="w-3.5 h-3.5 shrink-0" />
            <span>Sesuai</span>
          </p>
        </div>


        <!-- ================= HASIL PEMERIKSAAN DOKUMEN (muncul setelah klik Kirim) ================= -->
        <div
          v-if="analysisRows.length"
          id="hasil-pemeriksaan"
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 scroll-mt-6"
          :class="hasAnalysisIssues ? 'border-rose-300' : 'border-gray-200'"
        >
          <div>
            <h2 class="text-sm sm:text-base font-medium text-gray-900">Hasil Pemeriksaan Dokumen</h2>
            <p class="text-xs mt-0.5" :class="hasAnalysisIssues ? 'text-rose-600' : 'text-gray-500'">
              {{ hasAnalysisIssues ? `${analysisIssueCount} hal perlu diperbaiki sebelum formulir bisa dikirim.` : 'Semua dokumen sesuai dengan isian formulir.' }}
            </p>
          </div>

          <ul class="divide-y divide-gray-100 border-t border-gray-100">
            <li v-for="row in analysisRows" :key="row.key" class="py-3 flex items-start gap-3">
              <AlertCircleIcon v-if="!row.ok" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
              <CheckCircle2Icon v-else class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />

              <div class="min-w-0 flex-1">
                <p class="text-sm text-gray-900">{{ row.label }}</p>

                <dl v-if="!row.ok && row.isComparison" class="mt-1.5 grid grid-cols-[64px_1fr] gap-x-3 gap-y-1 text-xs">
                  <dt class="text-gray-500">Formulir</dt>
                  <dd class="text-gray-700 break-words">{{ row.formValue || '—' }}</dd>
                  <dt class="text-gray-500">Dokumen</dt>
                  <dd class="text-gray-900 font-medium break-words">{{ row.docValue || '—' }}</dd>
                </dl>
                <p v-else-if="!row.ok && row.message" class="mt-1 text-xs text-gray-600">{{ row.message }}</p>
              </div>

              <button
                v-if="!row.ok && row.apply && row.docValue"
                type="button"
                @click="row.apply"
                class="shrink-0 text-xs text-gray-700 hover:text-gray-950 underline cursor-pointer"
              >
                Pakai dari dokumen
              </button>
            </li>
          </ul>
        </div>

        <!-- ================= FORM ACTIONS ================= -->
        <div class="pt-2 flex items-center justify-between gap-4">
          <button
            type="submit"
            :disabled="isSubmitting"
            class="h-10 px-8 rounded-lg bg-[#1a73e8] hover:bg-[#1765cc] text-white text-sm font-semibold shadow-xs transition cursor-pointer flex items-center gap-2 active:scale-98 disabled:opacity-50"
          >
            <RefreshCwIcon v-if="isSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ isSubmitting ? submitProgressText : 'Kirim' }}</span>
          </button>

          <button
            type="button"
            @click="handleClearForm"
            :disabled="isSubmitting"
            class="text-xs font-medium text-[#1a73e8] hover:text-[#1765cc] hover:underline cursor-pointer transition disabled:opacity-50"
          >
            Kosongkan formulir
          </button>
        </div>

        <!-- Google Form bottom footer notes -->
        <div class="pt-6 text-center space-y-1 text-xs text-gray-500">
          <p>Formulir Pengajuan Program {{ brandLabel }} {{ area.label }}</p>
          <p class="text-[11px] text-gray-400">Jangan pernah mengirimkan sandi melalui formulir ini.</p>
        </div>
      </form>
    </div>

    <!-- ================= CLEAN VALIDATION ALERT MODAL ================= -->
    <div
      v-if="validationModal.isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
    >
      <div
        class="bg-white rounded-2xl shadow-xl border border-gray-200 max-w-md w-full p-6 space-y-4 animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 mt-0.5 bg-rose-100 text-rose-600">
            <AlertCircleIcon class="w-5 h-5" />
          </div>

          <div class="space-y-1 min-w-0 flex-1">
            <h3 class="text-base font-semibold text-gray-950 leading-tight">
              {{ validationModal.title }}
            </h3>
            <p class="text-xs text-gray-600 leading-relaxed font-normal">
              {{ validationModal.message }}
            </p>
          </div>
        </div>

        <!-- Issue details list -->
        <div
          v-if="validationModal.details && validationModal.details.length"
          class="p-3 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-700 space-y-1.5"
        >
          <div
            v-for="(item, idx) in validationModal.details"
            :key="idx"
            class="flex items-start gap-2"
          >
            <span class="text-gray-400 font-bold">&bull;</span>
            <span class="leading-relaxed">{{ item }}</span>
          </div>
        </div>

        <div class="pt-2 flex items-center justify-end">
          <button
            type="button"
            @click="validationModal.isOpen = false"
            class="px-4 py-2 text-xs font-medium rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 transition cursor-pointer"
          >
            Tutup & Periksa Kembali
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { notify, confirmAction } from '@/composables/useNotify';
import { useArea } from '@/composables/useArea';
import { DEFAULT_BRAND, useBrands } from '@/composables/useBrands';
import {
  UploadCloud as UploadCloudIcon,
  FileText as FileTextIcon,
  RefreshCw as RefreshCwIcon,
  ChevronDown as ChevronDownIcon,
  Check as CheckIcon,
  X as XIcon,
  Search as SearchIcon,
  AlertCircle as AlertCircleIcon,
  AlertTriangle as AlertTriangleIcon,
  CheckCircle2 as CheckCircle2Icon,
} from 'lucide-vue-next';

// ================= FORM STATE =================
const defaultProgramsList = ref([
  'PROGRAM DSA FEBRUARI 2026',
  'PROGRAM DSA MARET 2026',
  'PROGRAM SO R14T MARET 2026',
  'PROGRAM DSA MEI 2026',
  'PROGRAM FS PO C100 & C100X MEI 2026',
  'PROGRAM ST C100 & C100X MEI 2026',
  'PROGRAM DSA JUNI 2026',
  'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
  'PROGRAM PROMOTION C100X SERIES JUNI 2026',
  'PROGRAM DSA JULI 2026',
  'PROGRAM DSA AGUSTUS 2026',
]);


// Pemilih toko & sales memakai gaya yang sama (ukuran teks mengikuti isian lain: xs di HP, sm di layar lebar)
const pickerTriggerClass = 'w-full h-10 px-3.5 border rounded-lg flex items-center justify-between gap-2 bg-white text-left text-xs sm:text-sm transition cursor-pointer';
const pickerPanelClass = 'absolute left-0 top-full mt-1.5 w-full bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden';
const pickerSearchClass = 'w-full h-10 pl-10 pr-9 text-xs sm:text-sm bg-transparent focus:outline-none text-gray-900 placeholder:text-gray-400';
const pickerListClass = 'overflow-y-auto max-h-64 py-1 [scrollbar-width:thin]';
const pickerOptionClass = 'w-full px-3.5 py-2 flex items-center gap-3 text-left text-xs sm:text-sm hover:bg-gray-50 transition cursor-pointer';

// Area form diambil dari URL (/form/realme/jabar, /form/realme/jateng)
const { area } = useArea();

// Brand form diambil dari URL (/form/realme/jabar); nama tampilannya dari Master Brand
const { brand, loadBrands } = useBrands();
const brandLabel = computed(() => brand.value.name.toUpperCase());
loadBrands();

const regionOptions = ref(area.value.value === 'jabar' ? [
  'BIG BANDUNG',
  'BIG KARAWANG',
  'BIG TASIK',
  'BIG CIREBON',
] : []);

const salesList = ref([]);
const isAutoFilledSales = ref(false);
const salesSearchQuery = ref('');
const isSalesDropdownOpen = ref(false);
const salesDropdownRef = ref(null);

const form = reactive({
  program_name: '',
  region: '',
  id_real: '',
  kode_bt: '',
  dealer_name: '',
  sales_name: '',
  sales_custom: '',
  whatsapp: '',
  email: '',
  is_pkp: false,
});

// Jenis NPWP toko dari Master Dealer: menentukan wajib/tidaknya Faktur Pajak (null bila toko/jenisnya tidak dikenali)
const dealerTaxStatus = ref(null);

// Toko yang dipilih sudah punya agreement di Master Dealer (satu per toko, dipakai di form Realme)
const dealerHasAgreement = ref(false);
const usesMasterAgreement = computed(() => brand.value.slug === DEFAULT_BRAND && dealerHasAgreement.value);

// Email toko di Master Dealer: hanya tahu ada/tidaknya (emailnya tidak dikirim ke form publik).
// Wajib diisi selama toko belum punya email; email yang diisi lalu tersimpan ke Master Dealer.
const dealerHasEmail = ref(null);
const emailRequired = computed(() => dealerHasEmail.value !== true);
const emailHint = computed(() => {
  if (dealerHasEmail.value === true) return 'Email toko ini sudah tercatat di Master Dealer, jadi boleh dikosongkan.';
  if (dealerHasEmail.value === false) return 'Toko ini belum punya email di Master Dealer, jadi wajib diisi. Email ini otomatis disimpan ke Master Dealer.';
  return 'Wajib diisi bila toko belum punya email di Master Dealer; email ini otomatis disimpan ke sana.';
});

function applyMasterDealerTax(dealer) {
  form.kode_bt = dealer?.kode_bt || '';
  form.is_pkp = dealer?.is_pkp === true;
  dealerHasAgreement.value = dealer?.has_agreement === true;
  dealerHasEmail.value = typeof dealer?.has_email === 'boolean' ? dealer.has_email : null;
  if (usesMasterAgreement.value) validationErrors.agreement = '';
  dealerTaxStatus.value = typeof dealer?.is_pkp === 'boolean'
    ? { npwpType: dealer.npwp_type, isPkp: dealer.is_pkp }
    : null;
}

const files = reactive({
  credit_note: null,
  agreement: null,
  tax_invoice: null,
});

const filePreviews = reactive({
  credit_note: null,
  agreement: null,
  tax_invoice: null,
});

const isScanningDoc = reactive({
  credit_note: false,
  agreement: false,
  tax_invoice: false,
});

// Urut sesuai formulir: toko dulu, lalu program
const validationErrors = reactive({
  id_real: '',
  dealer_name: '',
  region: '',
  program_name: '',
  sales_name: '',
  email: '',
  credit_note: '',
  agreement: '',
});

// Master Dealer Lookup State
const dealerDropdownRef = ref(null);
const isDealerDropdownOpen = ref(false);
const dealerSearchQuery = ref('');
const isAutoFilledFromMaster = ref(false);
const isAutoFilledRegion = ref(false);
const filterDealersByRegion = ref(true);
const dealerSuggestions = ref([]);
const dealerTotal = ref(0);
const isLoadingDealers = ref(false);
let suggestionsTimeout = null;

// AI Inspection State
const isAutoFilledDealer = ref(false);

const aiAnalysis = reactive({
  isScanning: false,
  hasScanned: false,
  isClean: false,
  hasSwapped: false,
  hasMerged: false,
  hasInvalid: false,
  swapDetails: {},
  cekDokumen: '',
  statusPurchase: '',
  keterangan: '',
  docValidation: null,
  financial: null,
  audit: null,
  extractedDealerName: '',
  dealerMismatch: false,
  extractedCnProgramName: '',
  cnProgramMismatch: false,
  errorMsg: '',
});

function applyDealerFromCn() {
  if (aiAnalysis.extractedDealerName) {
    form.dealer_name = aiAnalysis.extractedDealerName;
    isAutoFilledDealer.value = true;
    aiAnalysis.dealerMismatch = false;
    validationErrors.dealer_name = '';
  }
}

function applyProgramFromCn() {
  if (aiAnalysis.extractedCnProgramName) {
    handleProgramChange(aiAnalysis.extractedCnProgramName);
    aiAnalysis.cnProgramMismatch = false;
  }
}

// ================= HASIL PEMERIKSAAN DOKUMEN =================
const DOCUMENT_SLOTS = [
  { docKey: 'cn', slot: 'credit_note', label: 'Credit Note' },
  { docKey: 'faktur', slot: 'tax_invoice', label: 'Faktur Pajak' },
];

// Masalah pada file itu sendiri (tertukar, digabung, tidak valid). Ketidaksesuaian nama dealer/program punya baris sendiri.
function documentProblem(docKey) {
  const result = aiAnalysis.docValidation?.[docKey];
  if (!result || result.status === 'valid') return '';
  if (result.actual_type === 'merged') return 'Dokumen digabung dengan dokumen lain. Unggah terpisah di kolom masing-masing.';
  if (result.status === 'swapped') {
    if (docKey === 'cn') return 'Terdeteksi sebagai Agreement, bukan Credit Note.';
  }
  const hasNameMismatch = docKey === 'cn' && (aiAnalysis.dealerMismatch || aiAnalysis.cnProgramMismatch);
  return hasNameMismatch ? '' : (result.message || 'File tidak sesuai.');
}

const analysisRows = computed(() => {
  if (!aiAnalysis.hasScanned) return [];

  const rows = [];
  const isDealerFilled = !!form.dealer_name.trim() && form.dealer_name.trim() !== '-';
  const compare = (key, doc, label, formValue, docValue, isMismatch, apply) => {
    if (!docValue && !isMismatch) return;
    rows.push({ key, doc, label, formValue, docValue, ok: !isMismatch, isComparison: true, apply });
  };

  if (isDealerFilled) {
    compare('cn_dealer', 'cn', 'Nama dealer di Credit Note', form.dealer_name, aiAnalysis.extractedDealerName, aiAnalysis.dealerMismatch, applyDealerFromCn);
  }
  compare('cn_program', 'cn', 'Program di Credit Note', form.program_name, aiAnalysis.extractedCnProgramName, aiAnalysis.cnProgramMismatch,
    defaultProgramsList.value.includes(aiAnalysis.extractedCnProgramName) ? applyProgramFromCn : null);

  for (const { docKey, slot, label } of DOCUMENT_SLOTS) {
    if (!filePreviews[slot] || !aiAnalysis.docValidation?.[docKey]) continue;
    const problem = documentProblem(docKey);
    rows.push({ key: `file_${docKey}`, doc: docKey, label: `Jenis file ${label}`, ok: !problem, message: problem });
  }

  // Dokumen ditolak tanpa rincian di atas (mis. belum memenuhi syarat program)
  const isBlocked = aiAnalysis.hasSwapped || aiAnalysis.hasMerged || aiAnalysis.hasInvalid || aiAnalysis.statusPurchase === 'BELUM BISA POTONG';
  if (isBlocked && rows.every(row => row.ok)) {
    rows.push({
      key: 'eligibility',
      doc: null,
      label: 'Kelayakan dokumen',
      ok: false,
      message: aiAnalysis.keterangan || aiAnalysis.cekDokumen || 'Dokumen belum memenuhi syarat program.',
    });
  }

  // Yang perlu diperbaiki tampil paling atas
  return [...rows.filter(row => !row.ok), ...rows.filter(row => row.ok)];
});

const analysisIssueCount = computed(() => analysisRows.value.filter(row => !row.ok).length);
const hasAnalysisIssues = computed(() => analysisIssueCount.value > 0);

function docStatus(docKey) {
  const slot = DOCUMENT_SLOTS.find(item => item.docKey === docKey)?.slot;
  if (!aiAnalysis.hasScanned || !filePreviews[slot]) return null;
  if (analysisRows.value.some(row => !row.ok && row.doc === docKey)) return 'issue';
  return aiAnalysis.docValidation?.[docKey] ? 'ok' : null;
}

function scrollToAnalysis() {
  nextTick(() => {
    document.getElementById('hasil-pemeriksaan')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

// Hasil pemeriksaan lama untuk dokumen ini tidak berlaku lagi setelah file diganti/dihapus
function clearDocumentAnalysis(docKey) {
  if (!docKey) return;
  if (aiAnalysis.docValidation?.[docKey]) {
    delete aiAnalysis.docValidation[docKey];
  }
  if (docKey === 'cn') {
    aiAnalysis.extractedDealerName = '';
    aiAnalysis.extractedCnProgramName = '';
    aiAnalysis.dealerMismatch = false;
    aiAnalysis.cnProgramMismatch = false;
  }
}

const isSubmitting = ref(false);
const submitProgressText = ref('Mengirim...');
const isSubmitted = ref(false);
const submittedData = ref(null);

const validationModal = reactive({
  isOpen: false,
  title: '',
  message: '',
  details: [],
});

function openValidationModal({ title, message, details = [] }) {
  validationModal.isOpen = true;
  validationModal.title = title;
  validationModal.message = message;
  validationModal.details = details;
}

const cnFileInputRef = ref(null);
const agrFileInputRef = ref(null);
const taxFileInputRef = ref(null);

// Computed list of sales filtered by search
const filteredSalesList = computed(() => {
  if (!salesSearchQuery.value) {
    return salesList.value;
  }
  const q = salesSearchQuery.value.toLowerCase();
  return salesList.value.filter(s => s.toLowerCase().includes(q));
});

// ================= LIFECYCLE & INITIALIZATION =================
onMounted(async () => {
  document.addEventListener('click', handleClickOutside);
  await fetchFormOptions();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

function handleClickOutside(event) {
  if (salesDropdownRef.value && !salesDropdownRef.value.contains(event.target)) {
    isSalesDropdownOpen.value = false;
  }
  if (dealerDropdownRef.value && !dealerDropdownRef.value.contains(event.target)) {
    isDealerDropdownOpen.value = false;
  }
}

async function fetchFormOptions() {
  try {
    const res = await axios.get('/api/program-submissions/form-options');
    if (res.data) {
      // Program & region mengikuti Master Program / Master Region area form ini
      if (Array.isArray(res.data.programs)) {
        defaultProgramsList.value = res.data.programs;
      }
      if (Array.isArray(res.data.regions)) {
        regionOptions.value = res.data.regions;
      }
      if (res.data.sales?.length) {
        salesList.value = res.data.sales;
      }
    }
  } catch (e) {
    // Keep fallback defaults if fetch fails
  }
}

// ================= HANDLERS =================
// Program hanya bisa dipilih dari daftar Master Program
function handleProgramChange(prog) {
  if (!defaultProgramsList.value.includes(prog)) return;
  form.program_name = prog;
  validationErrors.program_name = '';
}

function selectSales(sales) {
  form.sales_name = sales;
  isAutoFilledSales.value = false;
  validationErrors.sales_name = '';
  isSalesDropdownOpen.value = false;
  salesSearchQuery.value = '';
}

function applyCustomSales() {
  if (form.sales_custom.trim()) {
    form.sales_name = form.sales_custom.trim();
    isAutoFilledSales.value = false;
    validationErrors.sales_name = '';
    isSalesDropdownOpen.value = false;
  }
}

// Sales toko dari Master Dealer mengisi Nama Sales, kecuali sales sudah dipilih sendiri
function applyStoreSales(dealer) {
  const storeSales = String(dealer?.sales_person || '').trim().toUpperCase();
  if (!storeSales || (form.sales_name && !isAutoFilledSales.value)) return;

  if (!salesList.value.includes(storeSales)) {
    salesList.value = [...salesList.value, storeSales].sort();
  }
  form.sales_name = storeSales;
  isAutoFilledSales.value = true;
  validationErrors.sales_name = '';
}

// ================= MASTER DEALER LOOKUP & DROPDOWN =================
async function toggleDealerDropdown() {
  isDealerDropdownOpen.value = !isDealerDropdownOpen.value;
  if (isDealerDropdownOpen.value) {
    dealerSearchQuery.value = '';
    await fetchDealerOptions();
  }
}

// Toko dimuat per halaman: halaman pertama saat daftar dibuka / dicari, berikutnya saat daftar digulir ke bawah
const DEALER_PAGE_SIZE = 50;
let dealerRequestId = 0;

async function fetchDealerOptions(query = '', append = false) {
  const requestId = ++dealerRequestId;
  isLoadingDealers.value = true;
  try {
    const params = {
      query: query || undefined,
      region: (filterDealersByRegion.value && form.region) ? form.region : undefined,
      limit: DEALER_PAGE_SIZE,
      offset: append ? dealerSuggestions.value.length : 0,
    };
    const res = await axios.get('/api/dealers/suggestions', { params });
    // Hasil pencarian lama yang datang terlambat diabaikan
    if (requestId !== dealerRequestId || !res.data?.success || !res.data.data) return;
    if (append) {
      // Toko yang sudah ada di daftar tidak ditambahkan dua kali (mis. data berubah di antara dua halaman)
      const loadedIds = new Set(dealerSuggestions.value.map((dealer) => dealer.id));
      dealerSuggestions.value = [...dealerSuggestions.value, ...res.data.data.filter((dealer) => !loadedIds.has(dealer.id))];
    } else {
      dealerSuggestions.value = res.data.data;
    }
    dealerTotal.value = res.data.total ?? dealerSuggestions.value.length;
  } catch (e) {
    // Ignore error
  } finally {
    if (requestId === dealerRequestId) isLoadingDealers.value = false;
  }
}

function handleDealerListScroll(event) {
  const list = event.target;
  const nearBottom = list.scrollTop + list.clientHeight >= list.scrollHeight - 48;
  if (nearBottom && !isLoadingDealers.value && dealerSuggestions.value.length < dealerTotal.value) {
    fetchDealerOptions(dealerSearchQuery.value.trim(), true);
  }
}

function handleDealerSearchQueryInput() {
  if (suggestionsTimeout) clearTimeout(suggestionsTimeout);
  suggestionsTimeout = setTimeout(() => {
    fetchDealerOptions(dealerSearchQuery.value.trim());
  }, 200);
}

function clearDealerSearch() {
  dealerSearchQuery.value = '';
  fetchDealerOptions('');
}

function toggleRegionFilter() {
  filterDealersByRegion.value = !filterDealersByRegion.value;
  fetchDealerOptions(dealerSearchQuery.value.trim());
}

function clearSelectedDealer() {
  form.id_real = '';
  form.dealer_name = '';
  applyMasterDealerTax(null);
  isAutoFilledFromMaster.value = false;
  isAutoFilledDealer.value = false;
  isAutoFilledRegion.value = false;
  // Sales yang terisi dari toko ikut dikosongkan; yang dipilih sendiri tetap
  if (isAutoFilledSales.value) {
    form.sales_name = '';
    isAutoFilledSales.value = false;
  }
  validationErrors.id_real = '';
  validationErrors.dealer_name = '';
}

function selectDealerSuggestion(dealer) {
  form.id_real = dealer.id_realme;
  form.dealer_name = dealer.dealer_name;
  applyMasterDealerTax(dealer);
  if (dealer.region) {
    const regUpper = dealer.region.trim().toUpperCase();
    if (!regionOptions.value.includes(regUpper)) {
      regionOptions.value.push(regUpper);
    }
    form.region = regUpper;
    isAutoFilledRegion.value = true;
    validationErrors.region = '';
  }
  if (dealer.whatsapp && !form.whatsapp) {
    form.whatsapp = dealer.whatsapp;
  }
  applyStoreSales(dealer);
  isAutoFilledFromMaster.value = true;
  isAutoFilledDealer.value = false;
  isDealerDropdownOpen.value = false;
  validationErrors.id_real = '';
  validationErrors.dealer_name = '';
  aiAnalysis.dealerMismatch = false;
}

function openFilePicker(slot) {
  if (slot === 'cn' && cnFileInputRef.value) {
    cnFileInputRef.value.click();
  } else if (slot === 'agr' && agrFileInputRef.value) {
    agrFileInputRef.value.click();
  } else if (slot === 'faktur' && taxFileInputRef.value) {
    taxFileInputRef.value.click();
  }
}

function handleFileChange(event, slot) {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.size > 15 * 1024 * 1024) {
    notify.error(`${file.name} melebihi batas ukuran maksimal 15 MB.`, { title: 'File terlalu besar' });
    return;
  }

  files[slot] = file;
  filePreviews[slot] = {
    name: file.name,
    size: file.size,
    type: file.type,
  };

  if (slot === 'credit_note') validationErrors.credit_note = '';
  if (slot === 'agreement') validationErrors.agreement = '';

  clearDocumentAnalysis(DOCUMENT_SLOTS.find(item => item.slot === slot)?.docKey);
}

function removeFile(slot) {
  files[slot] = null;
  filePreviews[slot] = null;
  isScanningDoc[slot] = false;

  if (slot === 'credit_note' && cnFileInputRef.value) cnFileInputRef.value.value = '';
  if (slot === 'agreement' && agrFileInputRef.value) agrFileInputRef.value.value = '';
  if (slot === 'tax_invoice' && taxFileInputRef.value) taxFileInputRef.value.value = '';

  clearDocumentAnalysis(DOCUMENT_SLOTS.find(item => item.slot === slot)?.docKey);
}


function validateField(field) {
  if (field === 'id_real') {
    const val = form.id_real.trim();
    if (!val) {
      validationErrors.id_real = 'Pertanyaan ini wajib diisi';
    } else if (val === '-' || /^[-_\s]+$/.test(val)) {
      validationErrors.id_real = 'ID REALME wajib diisi dan tidak boleh hanya berisi tanda hubung (-)';
    } else {
      validationErrors.id_real = '';
    }
  }

  if (field === 'dealer_name') {
    if (!form.dealer_name.trim()) {
      validationErrors.dealer_name = 'Pertanyaan ini wajib diisi';
    } else {
      validationErrors.dealer_name = '';
    }
  }
}

function validateAllFields() {
  let isValid = true;

  if (!form.program_name.trim()) {
    validationErrors.program_name = 'Pertanyaan ini wajib diisi';
    isValid = false;
  } else {
    validationErrors.program_name = '';
  }

  if (!form.region.trim()) {
    validationErrors.region = 'Pertanyaan ini wajib diisi';
    isValid = false;
  } else {
    validationErrors.region = '';
  }

  const idVal = form.id_real.trim();
  if (!idVal) {
    validationErrors.id_real = 'Pertanyaan ini wajib diisi';
    isValid = false;
  } else if (idVal === '-' || /^[-_\s]+$/.test(idVal)) {
    validationErrors.id_real = 'ID REALME wajib diisi dan tidak boleh hanya berisi tanda hubung (-)';
    isValid = false;
  } else {
    validationErrors.id_real = '';
  }

  if (!form.dealer_name.trim()) {
    validationErrors.dealer_name = 'Pertanyaan ini wajib diisi';
    isValid = false;
  } else {
    validationErrors.dealer_name = '';
  }

  if (!form.sales_name.trim()) {
    validationErrors.sales_name = 'Pertanyaan ini wajib diisi';
    isValid = false;
  } else {
    validationErrors.sales_name = '';
  }

  // Email wajib bila toko belum punya email di Master Dealer; bila diisi harus berformat email
  const emailVal = form.email.trim();
  if (!emailVal && emailRequired.value) {
    validationErrors.email = 'Pertanyaan ini wajib diisi';
    isValid = false;
  } else if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
    validationErrors.email = 'Format email tidak valid, contoh: toko@gmail.com';
    isValid = false;
  } else {
    validationErrors.email = '';
  }

  if (!files.credit_note) {
    validationErrors.credit_note = 'Dokumen Credit Note/Invoice wajib diunggah.';
    isValid = false;
  } else {
    validationErrors.credit_note = '';
  }

  if (!files.agreement && !usesMasterAgreement.value) {
    validationErrors.agreement = 'Dokumen Agreement wajib diunggah.';
    isValid = false;
  } else {
    validationErrors.agreement = '';
  }

  return isValid;
}

// Form Submission with Strict Pre-Validation & Error Alert Modal
async function handleSubmit() {
  if (!validateAllFields()) {
    const firstError = Object.values(validationErrors).find(e => !!e);
    if (firstError) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    return;
  }

  // Cek file sama/digabung lebih dulu (tanpa AI)
  if (files.credit_note && files.agreement && files.credit_note.name === files.agreement.name && files.credit_note.size === files.agreement.size) {
    isSubmitting.value = false;
    openValidationModal({
      title: 'Dokumen Tidak Boleh Digabung',
      message: 'File yang diunggah untuk Credit Note dan Agreement sama persis. Dokumen tidak boleh digabung menjadi satu file!',
      details: [
        'File Credit Note dan Agreement harus terpisah.',
        'Slot Credit Note: Khusus dokumen Credit Note saja.',
        'Slot Agreement: Khusus dokumen Agreement saja.',
        'Silakan pisahkan file dan unggah dokumen yang sesuai pada masing-masing slot.',
      ],
    });
    return;
  }
  if (files.credit_note && files.tax_invoice && files.credit_note.name === files.tax_invoice.name && files.credit_note.size === files.tax_invoice.size) {
    isSubmitting.value = false;
    openValidationModal({
      title: 'Dokumen Tidak Boleh Digabung',
      message: 'File yang diunggah untuk Credit Note dan Faktur Pajak sama persis. Dokumen tidak boleh digabung!',
      details: [
        'Slot Credit Note: Khusus dokumen Credit Note saja.',
        'Slot Faktur Pajak: Khusus dokumen Faktur Pajak saja.',
        'Silakan pisahkan file dan unggah dokumen yang sesuai.',
      ],
    });
    return;
  }
  if (files.agreement && files.tax_invoice && files.agreement.name === files.tax_invoice.name && files.agreement.size === files.tax_invoice.size) {
    isSubmitting.value = false;
    openValidationModal({
      title: 'Dokumen Tidak Boleh Digabung',
      message: 'File yang diunggah untuk Agreement dan Faktur Pajak sama persis. Dokumen tidak boleh digabung!',
      details: [
        'Slot Agreement: Khusus dokumen Agreement saja.',
        'Slot Faktur Pajak: Khusus dokumen Faktur Pajak saja.',
        'Silakan pisahkan file dan unggah dokumen yang sesuai.',
      ],
    });
    return;
  }

  isSubmitting.value = true;
  submitProgressText.value = 'Memeriksa kelayakan dokumen...';

  // Always pre-validate documents before final submit
  const preValData = new FormData();
  preValData.append('program_name', form.program_name.trim());
  preValData.append('region', form.region.trim());
  preValData.append('id_real', form.id_real.trim());
  if (form.kode_bt) preValData.append('kode_bt', form.kode_bt);
  preValData.append('dealer_name', form.dealer_name.trim());
  preValData.append('sales_name', form.sales_name.trim());
  if (form.whatsapp) preValData.append('whatsapp', form.whatsapp.trim());
  preValData.append('is_pkp', form.is_pkp ? '1' : '0');

  if (files.credit_note) preValData.append('credit_note_file', files.credit_note);
  if (files.agreement) preValData.append('agreement_file', files.agreement);
  if (files.tax_invoice) preValData.append('tax_invoice_file', files.tax_invoice);

  let preValResult = null;
  try {
    const checkRes = await axios.post('/api/program-submissions/pre-validate', preValData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (checkRes.data?.success && checkRes.data.data) {
      preValResult = checkRes.data.data;
      aiAnalysis.hasScanned = true;
      aiAnalysis.isClean = !!preValResult.is_clean;
      aiAnalysis.hasSwapped = !!preValResult.has_swapped;
      aiAnalysis.hasMerged = !!preValResult.has_merged;
      aiAnalysis.hasInvalid = !!preValResult.has_invalid;
      aiAnalysis.swapDetails = preValResult.swap_details || {};
      aiAnalysis.cekDokumen = preValResult.cek_dokumen || '';
      aiAnalysis.statusPurchase = preValResult.status_potong_purchase || '';
      aiAnalysis.keterangan = preValResult.keterangan || '';
      aiAnalysis.docValidation = preValResult.doc_validation || {};
      aiAnalysis.financial = preValResult.financial || {};
      aiAnalysis.extractedDealerName = preValResult.dealer_name || '';
      aiAnalysis.dealerMismatch = !!preValResult.dealer_mismatch;
      aiAnalysis.extractedCnProgramName = preValResult.cn_program_name || '';
      aiAnalysis.cnProgramMismatch = !!preValResult.cn_program_mismatch;

      // Auto-adopt dealer name if user entered '-'
      if (preValResult.dealer_name && (!form.dealer_name.trim() || form.dealer_name.trim() === '-')) {
        form.dealer_name = preValResult.dealer_name;
        isAutoFilledDealer.value = true;
        aiAnalysis.dealerMismatch = false;
        preValResult.dealer_mismatch = false;
      }

      // Sanitize minor typos in submit flow (e.g. NEWCOO CELL vs NEWCO CELL)
      if (preValResult.dealer_name && form.dealer_name.trim() && form.dealer_name.trim() !== '-') {
        if (isDealerNameSimilar(form.dealer_name, preValResult.dealer_name)) {
          aiAnalysis.dealerMismatch = false;
          preValResult.dealer_mismatch = false;
          if (aiAnalysis.docValidation?.cn?.status === 'invalid' && !aiAnalysis.cnProgramMismatch) {
            const cnMsg = (aiAnalysis.docValidation.cn.message || '').toLowerCase();
            if (cnMsg.includes('dealer') || cnMsg.includes('berbeda') || cnMsg.includes('nama')) {
              aiAnalysis.docValidation.cn.status = 'valid';
              aiAnalysis.docValidation.cn.message = 'Dokumen Credit Note terverifikasi.';
            }
          }
        }
      }
    }
  } catch (err) {
    // If pre-validation endpoint failed network-wise, backend submitForm will still validate
  }

  // Ada yang perlu diperbaiki (dokumen tertukar/digabung/tidak sesuai, nama dealer/program berbeda): tampilkan di Hasil Pemeriksaan
  if (hasAnalysisIssues.value) {
    isSubmitting.value = false;
    scrollToAnalysis();
    return;
  }

  // 3. Documents are verified clean - Proceed to submit
  submitProgressText.value = 'Mengirim formulir...';
  const formData = new FormData();
  formData.append('program_name', form.program_name.trim());
  formData.append('region', form.region.trim());
  formData.append('id_real', form.id_real.trim());
  if (form.kode_bt) formData.append('kode_bt', form.kode_bt);
  formData.append('dealer_name', form.dealer_name.trim());
  formData.append('sales_name', form.sales_name.trim());
  if (form.whatsapp) formData.append('whatsapp', form.whatsapp.trim());
  if (form.email.trim()) formData.append('email', form.email.trim());
  formData.append('is_pkp', form.is_pkp ? '1' : '0');

  if (files.credit_note) formData.append('credit_note_file', files.credit_note);
  if (files.agreement) formData.append('agreement_file', files.agreement);
  if (files.tax_invoice) formData.append('tax_invoice_file', files.tax_invoice);

  try {
    const res = await axios.post('/api/program-submissions/submit-form', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (res.data?.success) {
      submittedData.value = res.data.submission;
      isSubmitted.value = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      openValidationModal({
        title: 'Pengiriman Gagal',
        message: res.data?.message || 'Gagal mengirim formulir.',
        details: [],
      });
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Terjadi kesalahan saat mengirim formulir.';
    openValidationModal({
      title: 'Dokumen Belum Sesuai',
      message: msg,
      details: [],
    });
  } finally {
    isSubmitting.value = false;
  }
}

async function handleClearForm() {
  if (await confirmAction({ title: 'Kosongkan formulir?', message: 'Semua isian dan dokumen yang dipilih akan dihapus.', confirmText: 'Ya, Kosongkan' })) {
    form.program_name = '';
    form.region = '';
    form.id_real = '';
    form.dealer_name = '';
    applyMasterDealerTax(null);
    form.sales_name = '';
    form.sales_custom = '';
    form.whatsapp = '';
    form.email = '';

    files.credit_note = null;
    files.agreement = null;
    files.tax_invoice = null;

    filePreviews.credit_note = null;
    filePreviews.agreement = null;
    filePreviews.tax_invoice = null;

    Object.keys(validationErrors).forEach(k => validationErrors[k] = '');
    isAutoFilledDealer.value = false;
    isAutoFilledSales.value = false;
    aiAnalysis.hasScanned = false;
    aiAnalysis.docValidation = null;
    aiAnalysis.hasSwapped = false;
    aiAnalysis.hasInvalid = false;
    aiAnalysis.dealerMismatch = false;
    aiAnalysis.extractedDealerName = '';
    isScanningDoc.credit_note = false;
    isScanningDoc.agreement = false;
    isScanningDoc.tax_invoice = false;
  }
}

function resetFormToSubmitAnother() {
  handleClearForm();
  isSubmitted.value = false;
  submittedData.value = null;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Helpers
function formatFileSize(bytes) {
  if (!bytes) return '0 B';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function formatNumber(num) {
  if (num === null || num === undefined) return '0';
  return new Intl.NumberFormat('id-ID').format(Math.round(num));
}

function isDealerNameSimilar(a, b) {
  if (!a || !b) return true;
  const cleanA = a.toLowerCase().replace(/[^a-z0-9]/g, '');
  const cleanB = b.toLowerCase().replace(/[^a-z0-9]/g, '');
  if (!cleanA || !cleanB || cleanA === '-' || cleanB === '-') return true;
  if (cleanA === cleanB || cleanA.includes(cleanB) || cleanB.includes(cleanA)) return true;

  // Collapse consecutive duplicate characters (e.g. newcoo -> newco)
  const collapseA = cleanA.replace(/(.)\1+/g, '$1');
  const collapseB = cleanB.replace(/(.)\1+/g, '$1');
  if (collapseA === collapseB || collapseA.includes(collapseB) || collapseB.includes(collapseA)) return true;

  // Strip generic corporate / store words
  const stripWords = (s) => s.toLowerCase().replace(/\b(pt|cv|ud|toko|cell|cellular|selular|store|phone|telemarketing|cirebon)\b/g, '').replace(/[^a-z0-9]/g, '');
  const coreA = stripWords(a);
  const coreB = stripWords(b);
  if (coreA && coreB) {
    if (coreA === coreB || coreA.includes(coreB) || coreB.includes(coreA)) return true;
    const collapseCoreA = coreA.replace(/(.)\1+/g, '$1');
    const collapseCoreB = coreB.replace(/(.)\1+/g, '$1');
    if (collapseCoreA === collapseCoreB || collapseCoreA.includes(collapseCoreB) || collapseCoreB.includes(collapseCoreA)) return true;
    if (Math.min(coreA.length, coreB.length) >= 3 && Math.abs(coreA.length - coreB.length) <= 2) {
      let diff = 0;
      for (let i = 0; i < Math.min(coreA.length, coreB.length); i++) {
        if (coreA[i] !== coreB[i]) diff++;
      }
      if (diff <= 2) return true;
    }
  }
  return false;
}

</script>
