<template>
  <div class="min-h-screen bg-[#faf5ee] py-6 sm:py-10 px-3 sm:px-6 font-sans text-gray-900 selection:bg-orange-100 selection:text-orange-900">
    <div class="max-w-2xl mx-auto space-y-4">
      
      <!-- ================= SUCCESS SCREEN (GOOGLE FORM STYLE) ================= -->
      <div v-if="isSubmitted" class="space-y-4 animate-in fade-in duration-200">
        <!-- Header Card -->
        <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
          <div class="h-2.5 bg-[#ea580c]"></div>
          <div class="p-6 sm:p-8 space-y-4">
            <h1 class="text-2xl sm:text-3xl font-normal text-gray-950 tracking-tight">
              PROGRAM REALME JABAR
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
          <div class="h-2.5 bg-[#ea580c]"></div>
          
          <div class="p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-normal text-gray-950 tracking-tight">
              PROGRAM REALME JABAR
            </h1>
          </div>
        </div>

        <!-- CARD 1: NAMA PROGRAM -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.program_name ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-1">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              NAMA PROGRAM <span class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500">Pilih salah satu program Realme yang diajukan.</p>
          </div>

          <div class="space-y-2.5 pt-1">
            <label
              v-for="prog in defaultProgramsList"
              :key="prog"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition group"
            >
              <input
                type="radio"
                name="program_name_radio"
                :value="prog"
                v-model="form.program_name"
                class="w-4 h-4 text-orange-600 focus:ring-orange-500 border-gray-300 cursor-pointer"
                @change="handleProgramChange(prog)"
              />
              <span
                class="text-xs sm:text-sm text-gray-800 transition"
                :class="form.program_name === prog ? 'font-semibold text-gray-950' : 'font-normal'"
              >
                {{ prog }}
              </span>
            </label>

            <!-- Option "Yang lain:" -->
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition group">
              <input
                type="radio"
                name="program_name_radio"
                value="__OTHER__"
                v-model="programRadioSelection"
                class="w-4 h-4 text-orange-600 focus:ring-orange-500 border-gray-300 cursor-pointer shrink-0"
                @change="handleOtherProgramSelected"
              />
              <div class="flex items-center gap-2 flex-1 min-w-0">
                <span class="text-xs sm:text-sm text-gray-800 shrink-0">Yang lain:</span>
                <input
                  type="text"
                  v-model="form.program_custom"
                  @focus="programRadioSelection = '__OTHER__'; form.program_name = form.program_custom"
                  @input="form.program_name = form.program_custom"
                  placeholder="Ketik nama program..."
                  class="flex-1 min-w-0 text-xs sm:text-sm border-b border-gray-300 focus:border-orange-500 focus:outline-none py-1 bg-transparent transition"
                />
              </div>
            </div>
          </div>

          <div v-if="validationErrors.program_name" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.program_name }}</span>
          </div>
        </div>

        <!-- CARD 2: REGION -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.region ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-1">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              REGION <span class="text-rose-500">*</span>
            </label>
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
                class="w-4 h-4 text-orange-600 focus:ring-orange-500 border-gray-300 cursor-pointer"
                @change="validationErrors.region = ''"
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

        <!-- CARD 3: ID REALME -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition"
          :class="validationErrors.id_real ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <label class="block text-sm sm:text-base font-medium text-gray-900">
            ID REALME <span class="text-rose-500">*</span>
          </label>

          <div class="pt-1">
            <input
              type="text"
              v-model="form.id_real"
              @blur="validateField('id_real')"
              placeholder="Jawaban Anda"
              class="w-full text-xs sm:text-sm border-b pb-1.5 focus:outline-none transition bg-transparent placeholder:text-gray-400"
              :class="validationErrors.id_real ? 'border-rose-500' : 'border-gray-300 focus:border-orange-500'"
            />
          </div>

          <div v-if="validationErrors.id_real" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.id_real }}</span>
          </div>
        </div>

        <!-- CARD 4: NAMA DEALER -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition"
          :class="validationErrors.dealer_name ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="flex items-center justify-between gap-2">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              NAMA DEALER <span class="text-rose-500">*</span>
            </label>
            <span
              v-if="isAutoFilledDealer"
              class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
            >
              <CheckIcon class="w-3 h-3" /> Terisi Otomatis dari Dokumen
            </span>
          </div>

          <div class="pt-1">
            <input
              type="text"
              v-model="form.dealer_name"
              @input="isAutoFilledDealer = false"
              @blur="validateField('dealer_name'); triggerAiPreValidationDebounced()"
              placeholder="Jawaban Anda"
              class="w-full text-xs sm:text-sm border-b pb-1.5 focus:outline-none transition bg-transparent placeholder:text-gray-400"
              :class="validationErrors.dealer_name ? 'border-rose-500' : 'border-gray-300 focus:border-orange-500'"
            />
          </div>

          <!-- Dealer mismatch warning in card -->
          <div
            v-if="aiAnalysis.dealerMismatch && form.dealer_name.trim() !== '-'"
            class="p-3 rounded-lg border border-amber-200 bg-amber-50 text-xs text-amber-900 space-y-2 animate-in fade-in duration-150"
          >
            <div class="flex items-start gap-2">
              <AlertCircleIcon class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div class="space-y-1">
                <p class="font-semibold text-amber-950">Nama Dealer Berbeda dengan Dokumen</p>
                <p>
                  Nama pada dokumen Credit Note adalah <strong>"{{ aiAnalysis.extractedDealerName }}"</strong>, berbeda dengan isian formulir <strong>"{{ form.dealer_name }}"</strong>.
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3 pt-1">
              <button
                type="button"
                @click="applyDealerFromCn"
                class="px-2.5 py-1 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-medium transition cursor-pointer"
              >
                Gunakan "{{ aiAnalysis.extractedDealerName }}"
              </button>
              <button
                type="button"
                @click="form.dealer_name = '-'; isAutoFilledDealer = true; aiAnalysis.dealerMismatch = false"
                class="text-[11px] text-amber-800 underline hover:text-amber-950 cursor-pointer"
              >
                Set '-' agar otomatis
              </button>
            </div>
          </div>

          <div v-if="validationErrors.dealer_name" class="flex items-center gap-1.5 text-xs text-rose-600 pt-1">
            <AlertCircleIcon class="w-4 h-4 shrink-0" />
            <span>{{ validationErrors.dealer_name }}</span>
          </div>
        </div>

        <!-- CARD 5: NAMA SALES (DM) -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-3 transition relative"
          :class="validationErrors.sales_name ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <label class="block text-sm sm:text-base font-medium text-gray-900">
            NAMA SALES (DM) <span class="text-rose-500">*</span>
          </label>

          <!-- Custom Searchable Dropdown Matching Google Form Select -->
          <div class="pt-1 relative" ref="salesDropdownRef">
            <button
              type="button"
              @click="isSalesDropdownOpen = !isSalesDropdownOpen"
              class="w-full sm:w-72 h-10 px-3.5 border rounded-lg flex items-center justify-between text-xs sm:text-sm bg-white hover:bg-gray-50 transition cursor-pointer"
              :class="validationErrors.sales_name ? 'border-rose-500' : 'border-gray-300 focus:border-orange-500'"
            >
              <span :class="form.sales_name ? 'text-gray-900 font-medium' : 'text-gray-400'">
                {{ form.sales_name || 'Pilih' }}
              </span>
              <ChevronDownIcon class="w-4 h-4 text-gray-400 transition-transform duration-150" :class="isSalesDropdownOpen && 'rotate-180'" />
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="isSalesDropdownOpen"
              class="absolute left-0 top-full mt-1.5 w-full sm:w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50 p-2 space-y-1 max-h-72 flex flex-col"
            >
              <div class="px-2 pt-1 pb-1">
                <input
                  type="text"
                  v-model="salesSearchQuery"
                  placeholder="Cari nama sales..."
                  class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-200 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-orange-500"
                />
              </div>

              <div class="overflow-y-auto flex-1 divide-y divide-gray-50">
                <button
                  v-for="sales in filteredSalesList"
                  :key="sales"
                  type="button"
                  @click="selectSales(sales)"
                  class="w-full text-left px-3 py-2 text-xs hover:bg-orange-50 hover:text-orange-950 rounded-lg flex items-center justify-between transition cursor-pointer"
                  :class="form.sales_name === sales ? 'bg-orange-50 text-orange-900 font-semibold' : 'text-gray-700'"
                >
                  <span class="truncate">{{ sales }}</span>
                  <CheckIcon v-if="form.sales_name === sales" class="w-3.5 h-3.5 text-orange-600 shrink-0 ml-1" />
                </button>

                <div v-if="filteredSalesList.length === 0" class="p-3 text-center text-xs text-gray-400">
                  Sales tidak ditemukan. Ketik nama manual di bawah.
                </div>
              </div>

              <!-- Manual Sales Input Option -->
              <div class="pt-2 border-t border-gray-100 p-1">
                <div class="flex items-center gap-1.5">
                  <input
                    type="text"
                    v-model="form.sales_custom"
                    placeholder="Atau ketik sales baru..."
                    class="flex-1 px-2.5 py-1 text-xs border border-gray-200 rounded-md focus:outline-none focus:border-orange-500"
                  />
                  <button
                    type="button"
                    @click="applyCustomSales"
                    class="px-2.5 py-1 text-xs font-medium bg-gray-900 text-white rounded-md hover:bg-gray-800 transition cursor-pointer shrink-0"
                  >
                    Gunakan
                  </button>
                </div>
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
              Untuk pengiriman notifikasi otomatis konfirmasi status potong klaim dari sistem SCM Realme.
            </p>
          </div>

          <div class="pt-1">
            <input
              type="tel"
              v-model="form.whatsapp"
              placeholder="Contoh: 081224290502"
              class="w-full text-xs sm:text-sm border-b pb-1.5 focus:outline-none border-gray-300 focus:border-orange-500 transition bg-transparent placeholder:text-gray-400"
            />
          </div>
        </div>

        <!-- ================= DOKUMEN UPLOAD CARDS ================= -->

        <!-- CARD 7: DOKUMEN CREDIT NOTE/INVOICE * -->
        <div
          class="bg-white rounded-xl shadow-xs border p-6 space-y-4 transition"
          :class="validationErrors.credit_note ? 'border-rose-300 ring-1 ring-rose-100' : 'border-gray-200'"
        >
          <div class="space-y-1">
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

          <!-- Loading verification indicator -->
          <div v-if="isScanningDoc.credit_note" class="pt-1 flex items-center gap-2 text-xs text-gray-500">
            <RefreshCwIcon class="w-3.5 h-3.5 animate-spin text-gray-400 shrink-0" />
            <span>Memverifikasi dokumen...</span>
          </div>

          <!-- Warning ONLY if invalid or wrong document -->
          <div v-else-if="aiAnalysis.docValidation?.cn && aiAnalysis.docValidation.cn.status !== 'valid'" class="pt-1">
            <div class="p-2.5 rounded-lg border border-red-200 bg-red-50 text-xs text-red-800 flex items-center gap-2">
              <AlertCircleIcon class="w-4 h-4 text-red-600 shrink-0" />
              <span>
                {{
                  aiAnalysis.docValidation.cn.status === 'swapped'
                    ? 'File yang diunggah adalah Dokumen Agreement, bukan Credit Note. Mohon unggah file Credit Note yang benar.'
                    : (aiAnalysis.docValidation.cn.message || 'File tidak sesuai / bukan Credit Note.')
                }}
              </span>
            </div>
          </div>

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
          <div class="space-y-1">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              AGREEMENT <span class="text-rose-500">*</span>
            </label>
            <p class="text-xs text-gray-500">
              Upload 1 file yang didukung: PDF, drawing, atau image. Maks 10 GB.
            </p>
          </div>

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
              <span>Tambahkan file</span>
            </button>
          </div>

          <!-- Loading verification indicator -->
          <div v-if="isScanningDoc.agreement" class="pt-1 flex items-center gap-2 text-xs text-gray-500">
            <RefreshCwIcon class="w-3.5 h-3.5 animate-spin text-gray-400 shrink-0" />
            <span>Memverifikasi dokumen...</span>
          </div>

          <!-- Warning ONLY if invalid or wrong document -->
          <div v-else-if="aiAnalysis.docValidation?.agr && aiAnalysis.docValidation.agr.status !== 'valid'" class="pt-1">
            <div class="p-2.5 rounded-lg border border-red-200 bg-red-50 text-xs text-red-800 flex items-center gap-2">
              <AlertCircleIcon class="w-4 h-4 text-red-600 shrink-0" />
              <span>
                {{
                  aiAnalysis.docValidation.agr.status === 'swapped'
                    ? 'File yang diunggah adalah Dokumen Credit Note, bukan Agreement. Mohon unggah file Agreement yang benar.'
                    : (aiAnalysis.docValidation.agr.message || 'File tidak sesuai / bukan Agreement.')
                }}
              </span>
            </div>
          </div>

          <!-- Warning for Agreement Dealer Mismatch -->
          <div
            v-if="aiAnalysis.agrDealerMismatch && form.dealer_name.trim() !== '-'"
            class="p-3 rounded-lg border border-amber-200 bg-amber-50 text-xs text-amber-900 space-y-2 animate-in fade-in duration-150"
          >
            <div class="flex items-start gap-2">
              <AlertCircleIcon class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div class="space-y-1">
                <p class="font-semibold text-amber-950">Nama Dealer di Agreement Tidak Sesuai</p>
                <p>
                  Nama dealer pada dokumen Agreement adalah <strong>"{{ aiAnalysis.extractedAgrDealerName }}"</strong>, berbeda dengan isian formulir <strong>"{{ form.dealer_name }}"</strong>.
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3 pt-1">
              <button
                type="button"
                @click="applyDealerFromAgr"
                class="px-2.5 py-1 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-medium transition cursor-pointer"
              >
                Gunakan "{{ aiAnalysis.extractedAgrDealerName }}"
              </button>
            </div>
          </div>

          <!-- Warning for Agreement Program Mismatch -->
          <div
            v-if="aiAnalysis.agrProgramMismatch"
            class="p-3 rounded-lg border border-amber-200 bg-amber-50 text-xs text-amber-900 space-y-1.5 animate-in fade-in duration-150"
          >
            <div class="flex items-start gap-2">
              <AlertCircleIcon class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div class="space-y-1">
                <p class="font-semibold text-amber-950">Nama Program di Agreement Tidak Sesuai</p>
                <p>
                  Nama program pada dokumen Agreement adalah <strong>"{{ aiAnalysis.extractedAgrProgramName }}"</strong>, berbeda dengan program yang dipilih <strong>"{{ form.program_name }}"</strong>.
                </p>
              </div>
            </div>
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
          <div class="space-y-1">
            <label class="block text-sm sm:text-base font-medium text-gray-900">
              FAKTUR PAJAK
            </label>
            <p class="text-xs text-gray-500">
              Upload 1 file yang didukung: PDF, drawing, atau image. Maks 10 GB. <em>(Opsional untuk toko Non-PKP, wajib bagi PKP).</em>
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

          <!-- Loading verification indicator -->
          <div v-if="isScanningDoc.tax_invoice" class="pt-1 flex items-center gap-2 text-xs text-gray-500">
            <RefreshCwIcon class="w-3.5 h-3.5 animate-spin text-gray-400 shrink-0" />
            <span>Memverifikasi dokumen...</span>
          </div>

          <!-- AI Warning ONLY if invalid -->
          <div v-else-if="aiAnalysis.docValidation?.faktur && filePreviews.tax_invoice" class="pt-1">
            <div
              v-if="aiAnalysis.docValidation.faktur.status === 'invalid'"
              class="p-2.5 rounded-lg border border-red-200 bg-red-50 text-xs text-red-800 flex items-center gap-2"
            >
              <AlertCircleIcon class="w-4 h-4 text-red-600 shrink-0" />
              <span>{{ aiAnalysis.docValidation.faktur.message || 'File tidak sesuai / bukan Faktur Pajak.' }}</span>
            </div>
          </div>
        </div>

        <!-- ================= FORM ACTIONS ================= -->
        <div class="pt-2 flex items-center justify-between gap-4">
          <button
            type="submit"
            :disabled="isSubmitting"
            class="h-10 px-8 rounded-lg bg-[#d9381e] hover:bg-[#c23315] text-white text-sm font-semibold shadow-xs transition cursor-pointer flex items-center gap-2 active:scale-98 disabled:opacity-50"
          >
            <RefreshCwIcon v-if="isSubmitting" class="w-4 h-4 animate-spin" />
            <span>{{ isSubmitting ? submitProgressText : 'Kirim' }}</span>
          </button>

          <button
            type="button"
            @click="handleClearForm"
            :disabled="isSubmitting"
            class="text-xs font-medium text-[#d9381e] hover:text-[#b32b14] hover:underline cursor-pointer transition disabled:opacity-50"
          >
            Kosongkan formulir
          </button>
        </div>

        <!-- Google Form bottom footer notes -->
        <div class="pt-6 text-center space-y-1 text-xs text-gray-500">
          <p>Formulir Pengajuan Program REALME Jawa Barat</p>
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
import {
  UploadCloud as UploadCloudIcon,
  FileText as FileTextIcon,
  RefreshCw as RefreshCwIcon,
  ChevronDown as ChevronDownIcon,
  Check as CheckIcon,
  X as XIcon,
  AlertCircle as AlertCircleIcon,
} from 'lucide-vue-next';

// ================= FORM STATE =================
const defaultProgramsList = [
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
];

const programRadioSelection = ref('');

const regionOptions = ref([
  'BIG BANDUNG',
  'BIG KARAWANG',
  'BIG TASIK',
  'BIG CIREBON',
]);

const salesList = ref([]);
const salesSearchQuery = ref('');
const isSalesDropdownOpen = ref(false);
const salesDropdownRef = ref(null);

const form = reactive({
  program_name: '',
  program_custom: '',
  region: '',
  id_real: '',
  dealer_name: '',
  sales_name: '',
  sales_custom: '',
  whatsapp: '',
  is_pkp: false,
});

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

const validationErrors = reactive({
  program_name: '',
  region: '',
  id_real: '',
  dealer_name: '',
  sales_name: '',
  credit_note: '',
  agreement: '',
});

// AI Inspection State
const isAutoFilledDealer = ref(false);

const aiAnalysis = reactive({
  isScanning: false,
  hasScanned: false,
  isClean: false,
  hasSwapped: false,
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
  extractedAgrDealerName: '',
  agrDealerMismatch: false,
  extractedAgrProgramName: '',
  agrProgramMismatch: false,
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

function applyDealerFromAgr() {
  if (aiAnalysis.extractedAgrDealerName) {
    form.dealer_name = aiAnalysis.extractedAgrDealerName;
    isAutoFilledDealer.value = true;
    aiAnalysis.agrDealerMismatch = false;
    validationErrors.dealer_name = '';
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

const hasUploadedAnyDocument = computed(() => {
  return !!(files.credit_note || files.agreement || files.tax_invoice);
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
}

async function fetchFormOptions() {
  try {
    const res = await axios.get('/api/program-submissions/form-options');
    if (res.data) {
      if (res.data.regions?.length) {
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
function handleProgramChange(prog) {
  programRadioSelection.value = prog;
  form.program_name = prog;
  form.program_custom = '';
  validationErrors.program_name = '';
}

function handleOtherProgramSelected() {
  programRadioSelection.value = '__OTHER__';
  form.program_name = form.program_custom;
  validationErrors.program_name = '';
}

function selectSales(sales) {
  form.sales_name = sales;
  validationErrors.sales_name = '';
  isSalesDropdownOpen.value = false;
  salesSearchQuery.value = '';
}

function applyCustomSales() {
  if (form.sales_custom.trim()) {
    form.sales_name = form.sales_custom.trim();
    validationErrors.sales_name = '';
    isSalesDropdownOpen.value = false;
  }
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
    alert(`File ${file.name} melebihi batas ukuran maksimal 15 MB.`);
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

  const docKey = slot === 'credit_note' ? 'cn' : (slot === 'agreement' ? 'agr' : 'faktur');
  if (aiAnalysis.docValidation && aiAnalysis.docValidation[docKey]) {
    delete aiAnalysis.docValidation[docKey];
  }

  // Activate loading indicator immediately for this slot
  isScanningDoc[slot] = true;

  // Trigger AI analysis on upload
  triggerAiPreValidationDebounced();
}

function removeFile(slot) {
  files[slot] = null;
  filePreviews[slot] = null;
  isScanningDoc[slot] = false;

  if (slot === 'credit_note' && cnFileInputRef.value) cnFileInputRef.value.value = '';
  if (slot === 'agreement' && agrFileInputRef.value) agrFileInputRef.value.value = '';
  if (slot === 'tax_invoice' && taxFileInputRef.value) taxFileInputRef.value.value = '';

  if (aiAnalysis.docValidation && aiAnalysis.docValidation[slot === 'credit_note' ? 'cn' : slot === 'agreement' ? 'agr' : 'faktur']) {
    delete aiAnalysis.docValidation[slot === 'credit_note' ? 'cn' : slot === 'agreement' ? 'agr' : 'faktur'];
  }

  triggerAiPreValidationDebounced();
}



let debounceTimer = null;
function triggerAiPreValidationDebounced() {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    triggerAiPreValidation();
  }, 400);
}

// Live AI Document Pre-Validation
async function triggerAiPreValidation() {
  if (!files.credit_note && !files.agreement && !files.tax_invoice) {
    isScanningDoc.credit_note = false;
    isScanningDoc.agreement = false;
    isScanningDoc.tax_invoice = false;
    return;
  }

  aiAnalysis.isScanning = true;
  aiAnalysis.errorMsg = '';
  if (files.credit_note) isScanningDoc.credit_note = true;
  if (files.agreement) isScanningDoc.agreement = true;
  if (files.tax_invoice) isScanningDoc.tax_invoice = true;

  const formData = new FormData();
  formData.append('program_name', form.program_name || '');
  formData.append('region', form.region || '');
  formData.append('id_real', form.id_real || '');
  formData.append('dealer_name', form.dealer_name || '');
  formData.append('sales_name', form.sales_name || '');
  formData.append('whatsapp', form.whatsapp || '');
  formData.append('is_pkp', form.is_pkp ? '1' : '0');

  if (files.credit_note) {
    formData.append('credit_note_file', files.credit_note);
  }
  if (files.agreement) {
    formData.append('agreement_file', files.agreement);
  }
  if (files.tax_invoice) {
    formData.append('tax_invoice_file', files.tax_invoice);
  }

  try {
    const res = await axios.post('/api/program-submissions/pre-validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (res.data?.success && res.data.data) {
      const d = res.data.data;
      aiAnalysis.hasScanned = true;
      aiAnalysis.isClean = !!d.is_clean;
      aiAnalysis.hasSwapped = !!d.has_swapped;
      aiAnalysis.hasInvalid = !!d.has_invalid;
      aiAnalysis.swapDetails = d.swap_details || {};
      aiAnalysis.cekDokumen = d.cek_dokumen || '';
      aiAnalysis.statusPurchase = d.status_potong_purchase || '';
      aiAnalysis.keterangan = d.keterangan || '';
      aiAnalysis.docValidation = d.doc_validation || {};
      aiAnalysis.financial = d.financial || {};
      aiAnalysis.audit = d.audit || {};
      aiAnalysis.extractedDealerName = d.dealer_name || '';
      aiAnalysis.dealerMismatch = !!d.dealer_mismatch;
      aiAnalysis.extractedAgrDealerName = d.agr_dealer_name || '';
      aiAnalysis.agrDealerMismatch = !!d.agr_dealer_mismatch;
      aiAnalysis.extractedAgrProgramName = d.agr_program_name || '';
      aiAnalysis.agrProgramMismatch = !!d.agr_program_mismatch;

      // Sanitize minor typos (e.g. NEWCOO CELL vs NEWCO CELL)
      if (d.dealer_name && form.dealer_name.trim() && form.dealer_name.trim() !== '-') {
        if (isDealerNameSimilar(form.dealer_name, d.dealer_name)) {
          aiAnalysis.dealerMismatch = false;
          d.dealer_mismatch = false;
          if (aiAnalysis.docValidation?.cn?.status === 'invalid') {
            const cnMsg = (aiAnalysis.docValidation.cn.message || '').toLowerCase();
            if (cnMsg.includes('dealer') || cnMsg.includes('berbeda') || cnMsg.includes('nama')) {
              aiAnalysis.docValidation.cn.status = 'valid';
              aiAnalysis.docValidation.cn.message = 'Dokumen Credit Note terverifikasi.';
            }
          }
        }
      }
      if (d.agr_dealer_name && form.dealer_name.trim() && form.dealer_name.trim() !== '-') {
        if (isDealerNameSimilar(form.dealer_name, d.agr_dealer_name)) {
          aiAnalysis.agrDealerMismatch = false;
          d.agr_dealer_mismatch = false;
          if (aiAnalysis.docValidation?.agr?.status === 'invalid') {
            const agrMsg = (aiAnalysis.docValidation.agr.message || '').toLowerCase();
            if (agrMsg.includes('dealer') || agrMsg.includes('berbeda') || agrMsg.includes('nama')) {
              aiAnalysis.docValidation.agr.status = 'valid';
              aiAnalysis.docValidation.agr.message = 'Dokumen Agreement terverifikasi.';
            }
          }
        }
      }

      // Auto-populate dealer name if user entered '-' or left empty
      if (d.dealer_name && (!form.dealer_name.trim() || form.dealer_name.trim() === '-')) {
        form.dealer_name = d.dealer_name;
        isAutoFilledDealer.value = true;
        aiAnalysis.dealerMismatch = false;
        aiAnalysis.agrDealerMismatch = false;
        validationErrors.dealer_name = '';
      }
    }
  } catch (e) {
    aiAnalysis.errorMsg = e.response?.data?.message || e.message;
  } finally {
    aiAnalysis.isScanning = false;
    isScanningDoc.credit_note = false;
    isScanningDoc.agreement = false;
    isScanningDoc.tax_invoice = false;
  }
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

  if (!files.credit_note) {
    validationErrors.credit_note = 'Dokumen Credit Note/Invoice wajib diunggah.';
    isValid = false;
  } else {
    validationErrors.credit_note = '';
  }

  if (!files.agreement) {
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

  isSubmitting.value = true;
  submitProgressText.value = 'Memeriksa kelayakan dokumen...';

  // Always pre-validate documents before final submit
  const preValData = new FormData();
  preValData.append('program_name', form.program_name.trim());
  preValData.append('region', form.region.trim());
  preValData.append('id_real', form.id_real.trim());
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
      aiAnalysis.hasInvalid = !!preValResult.has_invalid;
      aiAnalysis.swapDetails = preValResult.swap_details || {};
      aiAnalysis.cekDokumen = preValResult.cek_dokumen || '';
      aiAnalysis.statusPurchase = preValResult.status_potong_purchase || '';
      aiAnalysis.keterangan = preValResult.keterangan || '';
      aiAnalysis.docValidation = preValResult.doc_validation || {};
      aiAnalysis.financial = preValResult.financial || {};
      aiAnalysis.extractedDealerName = preValResult.dealer_name || '';
      aiAnalysis.dealerMismatch = !!preValResult.dealer_mismatch;
      aiAnalysis.extractedAgrDealerName = preValResult.agr_dealer_name || '';
      aiAnalysis.agrDealerMismatch = !!preValResult.agr_dealer_mismatch;
      aiAnalysis.extractedAgrProgramName = preValResult.agr_program_name || '';
      aiAnalysis.agrProgramMismatch = !!preValResult.agr_program_mismatch;

      // Auto-adopt dealer name if user entered '-'
      if (preValResult.dealer_name && (!form.dealer_name.trim() || form.dealer_name.trim() === '-')) {
        form.dealer_name = preValResult.dealer_name;
        isAutoFilledDealer.value = true;
        aiAnalysis.dealerMismatch = false;
        aiAnalysis.agrDealerMismatch = false;
        preValResult.dealer_mismatch = false;
        preValResult.agr_dealer_mismatch = false;
      }

      // Sanitize minor typos in submit flow (e.g. NEWCOO CELL vs NEWCO CELL)
      if (preValResult.dealer_name && form.dealer_name.trim() && form.dealer_name.trim() !== '-') {
        if (isDealerNameSimilar(form.dealer_name, preValResult.dealer_name)) {
          aiAnalysis.dealerMismatch = false;
          preValResult.dealer_mismatch = false;
          if (aiAnalysis.docValidation?.cn?.status === 'invalid') {
            const cnMsg = (aiAnalysis.docValidation.cn.message || '').toLowerCase();
            if (cnMsg.includes('dealer') || cnMsg.includes('berbeda') || cnMsg.includes('nama')) {
              aiAnalysis.docValidation.cn.status = 'valid';
              aiAnalysis.docValidation.cn.message = 'Dokumen Credit Note terverifikasi.';
            }
          }
        }
      }
      if (preValResult.agr_dealer_name && form.dealer_name.trim() && form.dealer_name.trim() !== '-') {
        if (isDealerNameSimilar(form.dealer_name, preValResult.agr_dealer_name)) {
          aiAnalysis.agrDealerMismatch = false;
          preValResult.agr_dealer_mismatch = false;
          if (aiAnalysis.docValidation?.agr?.status === 'invalid') {
            const agrMsg = (aiAnalysis.docValidation.agr.message || '').toLowerCase();
            if (agrMsg.includes('dealer') || agrMsg.includes('berbeda') || agrMsg.includes('nama')) {
              aiAnalysis.docValidation.agr.status = 'valid';
              aiAnalysis.docValidation.agr.message = 'Dokumen Agreement terverifikasi.';
            }
          }
        }
      }
    }
  } catch (err) {
    // If pre-validation endpoint failed network-wise, backend submitForm will still validate
  }

  // 1. BLOCK IF SWAPPED
  if (preValResult?.has_swapped || aiAnalysis.hasSwapped) {
    isSubmitting.value = false;
    openValidationModal({
      title: 'Dokumen Belum Sesuai',
      message: 'Dokumen Credit Note dan Agreement yang diunggah tidak sesuai dengan peruntukannya (file terbalik). Silakan unggah file yang benar pada masing-masing kolom sebelum mengirim formulir.',
      details: [
        'Kolom Credit Note terisi file Agreement',
        'Kolom Agreement terisi file Credit Note',
      ],
    });
    return;
  }

  // 2. BLOCK IF CN DEALER NAME MISMATCH
  const hasMismatch = (preValResult?.dealer_mismatch || aiAnalysis.dealerMismatch) && form.dealer_name.trim() !== '-';
  if (hasMismatch) {
    isSubmitting.value = false;
    const docDealer = preValResult?.dealer_name || aiAnalysis.extractedDealerName || 'Dokumen Credit Note';
    openValidationModal({
      title: 'Nama Dealer Tidak Sesuai (CN)',
      message: `Nama dealer yang diisi ('${form.dealer_name}') berbeda dengan nama pada dokumen Credit Note ('${docDealer}').`,
      details: [
        `Nama di formulir: ${form.dealer_name}`,
        `Nama di dokumen Credit Note: ${docDealer}`,
        `Solusi: Samakan nama dealer dengan dokumen, atau cukup isi tanda '-' agar sistem membaca otomatis.`,
      ],
    });
    return;
  }

  // 3. BLOCK IF AGREEMENT DEALER MISMATCH
  const hasAgrDealerMismatch = (preValResult?.agr_dealer_mismatch || aiAnalysis.agrDealerMismatch) && form.dealer_name.trim() !== '-';
  if (hasAgrDealerMismatch) {
    isSubmitting.value = false;
    const docAgrDealer = preValResult?.agr_dealer_name || aiAnalysis.extractedAgrDealerName || 'Dokumen Agreement';
    openValidationModal({
      title: 'Nama Dealer di Agreement Tidak Sesuai',
      message: `Nama dealer yang diajukan ('${form.dealer_name}') berbeda dengan nama dealer pada dokumen Agreement ('${docAgrDealer}').`,
      details: [
        `Nama di formulir: ${form.dealer_name}`,
        `Nama di dokumen Agreement: ${docAgrDealer}`,
        `Solusi: Pastikan dokumen Agreement yang diunggah sesuai dengan toko/dealer yang diajukan.`,
      ],
    });
    return;
  }

  // 4. BLOCK IF AGREEMENT PROGRAM MISMATCH
  const hasAgrProgMismatch = (preValResult?.agr_program_mismatch || aiAnalysis.agrProgramMismatch);
  if (hasAgrProgMismatch) {
    isSubmitting.value = false;
    const docAgrProg = preValResult?.agr_program_name || aiAnalysis.extractedAgrProgramName || 'Dokumen Agreement';
    openValidationModal({
      title: 'Nama Program di Agreement Tidak Sesuai',
      message: `Nama program yang dipilih ('${form.program_name}') berbeda dengan nama program pada dokumen Agreement ('${docAgrProg}').`,
      details: [
        `Program di formulir: ${form.program_name}`,
        `Program di dokumen Agreement: ${docAgrProg}`,
        `Solusi: Pastikan dokumen Agreement yang diunggah sesuai dengan program yang dipilih.`,
      ],
    });
    return;
  }

  // 3. BLOCK IF INVALID OR NOT ELIGIBLE (BELUM BISA POTONG)
  if (preValResult?.has_invalid || preValResult?.status_potong_purchase === 'BELUM BISA POTONG') {
    isSubmitting.value = false;
    const issues = [];
    if (preValResult.cek_dokumen) issues.push(preValResult.cek_dokumen);
    if (preValResult.keterangan) issues.push(preValResult.keterangan);

    openValidationModal({
      title: 'Dokumen Belum Sesuai',
      message: 'Formulir tidak dapat dikirim karena dokumen yang diunggah belum sesuai atau tidak memenuhi syarat kelayakan program.',
      details: issues.length ? issues : ['File yang diunggah tidak sesuai atau tidak terbaca dengan jelas.'],
    });
    return;
  }

  // 3. Documents are verified clean - Proceed to submit
  submitProgressText.value = 'Mengirim formulir...';
  const formData = new FormData();
  formData.append('program_name', form.program_name.trim());
  formData.append('region', form.region.trim());
  formData.append('id_real', form.id_real.trim());
  formData.append('dealer_name', form.dealer_name.trim());
  formData.append('sales_name', form.sales_name.trim());
  if (form.whatsapp) formData.append('whatsapp', form.whatsapp.trim());
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

function handleClearForm() {
  if (confirm('Kosongkan semua isian dan hapus dokumen yang dipilih?')) {
    form.program_name = '';
    form.program_custom = '';
    form.region = '';
    form.id_real = '';
    form.dealer_name = '';
    form.sales_name = '';
    form.sales_custom = '';
    form.whatsapp = '';
    programRadioSelection.value = '';

    files.credit_note = null;
    files.agreement = null;
    files.tax_invoice = null;

    filePreviews.credit_note = null;
    filePreviews.agreement = null;
    filePreviews.tax_invoice = null;

    Object.keys(validationErrors).forEach(k => validationErrors[k] = '');
    isAutoFilledDealer.value = false;
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
