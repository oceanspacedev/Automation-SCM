<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-98"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-98"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-xs font-sans"
        @click.self="handleClose"
      >
        <!-- Modal Dialog Box -->
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-4xl w-full flex flex-col max-h-[90vh] overflow-hidden text-xs">
          
          <!-- Modal Header (Rapi, Bersih, Tanpa Icon Kotak) -->
          <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-white shrink-0">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-900 truncate">Edit Data Program</h3>
                <span class="text-xs text-gray-400 font-mono">#{{ dataProgram?.id }}</span>
                <span v-if="dataProgram?.row_hash" class="text-[11px] text-gray-400 font-mono hidden sm:inline">
                  ({{ dataProgram.row_hash }})
                </span>
              </div>
              <p class="text-xs text-gray-500 truncate mt-0.5">
                {{ dataProgram?.dealer_name || 'Dealer' }} &bull; {{ dataProgram?.program_name || dataProgram?.program || '-' }}
              </p>
            </div>

            <button
              type="button"
              @click="handleClose"
              class="w-8 h-8 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              title="Tutup (Esc)"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Alert Notification (Minimal & Rapi) -->
          <div v-if="errorMessage" class="mx-6 mt-3 p-2.5 rounded-md bg-red-50 border border-red-200 text-red-700 flex items-center justify-between shrink-0">
            <span>{{ errorMessage }}</span>
            <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-700 font-bold ml-2">&times;</button>
          </div>

          <div v-if="successMessage" class="mx-6 mt-3 p-2.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shrink-0">
            <span>{{ successMessage }}</span>
            <button type="button" @click="successMessage = ''" class="text-emerald-500 hover:text-emerald-800 font-bold ml-2">&times;</button>
          </div>

          <!-- Tab Navigation (Clean Text Tabs, Tanpa Icon & Tanpa Badge Kuning) -->
          <div class="px-6 border-b border-gray-200 bg-white shrink-0 flex items-center gap-6 overflow-x-auto scrollbar-none">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              type="button"
              @click="activeTab = tab.id"
              :class="[
                'py-3 text-xs border-b-2 transition cursor-pointer whitespace-nowrap',
                activeTab === tab.id
                  ? 'border-gray-900 text-gray-900 font-semibold'
                  : 'border-transparent text-gray-500 hover:text-gray-800 font-medium'
              ]"
            >
              {{ tab.label }}
            </button>
          </div>

          <!-- Modal Body (Form Container with Scroll) -->
          <form @submit.prevent="handleSubmit" id="editDataProgramForm" class="p-6 overflow-y-auto flex-1 space-y-4">

            <!-- TAB 1: DEALER & PROGRAM -->
            <div v-show="activeTab === 'dealer'" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">Nama Dealer / Toko</label>
                  <input
                    v-model="form.dealer_name"
                    type="text"
                    placeholder="Contoh: CV MAJU BERSAMA"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tipe Program</label>
                  <input
                    v-model="form.program"
                    type="text"
                    placeholder="Contoh: NPS FL / FL"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Kode BT</label>
                  <input
                    v-model="form.kode_bt"
                    type="text"
                    placeholder="Contoh: BT03818"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div class="sm:col-span-2">
                  <label class="block font-medium text-gray-700 mb-1">Nama Program</label>
                  <input
                    v-model="form.program_name"
                    type="text"
                    placeholder="Contoh: Cashback C11 Juli 2026"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Periode</label>
                  <input
                    v-model="form.periode"
                    type="text"
                    placeholder="Contoh: Juli 2026"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Region</label>
                  <input
                    v-model="form.region"
                    type="text"
                    placeholder="Contoh: JABODETABEK"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Big Region</label>
                  <input
                    v-model="form.big_region"
                    type="text"
                    placeholder="Contoh: JABODETABEK"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">ID GS / ID Dealer</label>
                  <input
                    v-model="form.id_gs"
                    type="text"
                    placeholder="Contoh: DLR0012"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Status DL</label>
                  <select
                    v-model="form.status_dl"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="">- Pilih Status DL -</option>
                    <option value="DL">DL</option>
                    <option value="NON DL">NON DL</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Sales Person</label>
                  <input
                    v-model="form.sales_person"
                    type="text"
                    placeholder="Nama Sales Person..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Telemarketing</label>
                  <input
                    v-model="form.telemarketing"
                    type="text"
                    placeholder="Nama Telemarketing..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>
              </div>
            </div>

            <!-- TAB 2: FINANSIAL & PAJAK -->
            <div v-show="activeTab === 'financial'" class="space-y-4">
              <div class="flex items-center justify-between pb-1 border-b border-gray-100">
                <span class="text-xs font-medium text-gray-700">Rincian Finansial & Pajak</span>
                <button
                  type="button"
                  @click="autoCalculateFinancials"
                  class="px-2.5 py-1 rounded border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs transition cursor-pointer"
                >
                  Hitung Ulang Net Pay
                </button>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">Wajib Pajak</label>
                  <select
                    v-model="form.wajib_pajak"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="">- Pilih Wajib Pajak -</option>
                    <option value="BADAN">BADAN</option>
                    <option value="OP">OP</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">TRF PPh</label>
                  <select
                    v-model="form.trf_pph"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="">- Pilih TRF PPh -</option>
                    <option value="BADAN">BADAN</option>
                    <option value="OP">OP</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Incentive (Rp)</label>
                  <input
                    v-model.number="form.incentive"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-right font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">DPP (Rp)</label>
                  <input
                    v-model.number="form.dpp"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-right font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">DPP Lain (Rp)</label>
                  <input
                    v-model.number="form.dpp_lain"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-right font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">PPN (Rp)</label>
                  <input
                    v-model.number="form.ppn"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-right font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Nilai PPh (Rp)</label>
                  <input
                    v-model.number="form.nilai_pph"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-right font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Net Pay (Rp)</label>
                  <input
                    v-model.number="form.net_pay"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-gray-50 text-xs text-right font-mono font-semibold text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Selisih (Rp)</label>
                  <input
                    v-model.number="form.selisih"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-right font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Cek Pajak Tarif</label>
                  <input
                    v-model="form.cek_pajak_tarif"
                    type="text"
                    placeholder="Contoh: SESUAI / TIDAK SESUAI"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Note PPh</label>
                  <input
                    v-model="form.note_pph"
                    type="text"
                    placeholder="Catatan PPh..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">No Faktur Pajak</label>
                  <input
                    v-model="form.no_faktur_pajak"
                    type="text"
                    placeholder="010.001-26.00000000"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Keterangan Faktur Pajak</label>
                  <input
                    v-model="form.ket_faktur_pajak"
                    type="text"
                    placeholder="Keterangan FP..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">NPWP</label>
                  <input
                    v-model="form.npwp"
                    type="text"
                    placeholder="00.000.000.0-000.000"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Nama NPWP</label>
                  <input
                    v-model="form.nama_npwp"
                    type="text"
                    placeholder="Nama pada kartu NPWP..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>
              </div>
            </div>

            <!-- TAB 3: ADMINISTRASI & TRANSAKSI -->
            <div v-show="activeTab === 'admin'" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">No. PO</label>
                  <input
                    v-model="form.no_po"
                    type="text"
                    placeholder="PO-..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">No. PO / SJ</label>
                  <input
                    v-model="form.no_po_sj"
                    type="text"
                    placeholder="PO/SJ..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">No. Transaksi</label>
                  <input
                    v-model="form.no_transaksi"
                    type="text"
                    placeholder="TRX-..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tanggal Input</label>
                  <input
                    v-model="form.tgl_input"
                    type="date"
                    @change="handleTglInputChange"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition cursor-pointer"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Lama Pending (Hari)</label>
                  <input
                    v-model="form.lama_pending"
                    type="text"
                    placeholder="Contoh: 0, 15, 45"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Keterangan Aging</label>
                  <select
                    v-model="form.keterangan"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="KURANG DARI 30 HARI">KURANG DARI 30 HARI</option>
                    <option value="LEBIH DARI 30 HARI">LEBIH DARI 30 HARI</option>
                    <option value="">- Kosongkan -</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tanggal Share CN</label>
                  <input
                    v-model="form.tgl_share_cn"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tanggal Potong / TF</label>
                  <input
                    v-model="form.tgl_potong_tf"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">No. UID</label>
                  <input
                    v-model="form.no_uid"
                    type="text"
                    placeholder="UID..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">No. Pembayaran</label>
                  <input
                    v-model="form.no_pembayaran"
                    type="text"
                    placeholder="No Pembayaran..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tgl Input Bank PPh</label>
                  <input
                    v-model="form.tgl_input_bank_pph"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">T/F Status</label>
                  <select
                    v-model="form.tf_status"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="">- Pilih Status T/F -</option>
                    <option value="TF">TF</option>
                    <option value="POTONG">POTONG</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tgl Proses</label>
                  <input
                    v-model="form.tgl_proses"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Tgl SJ (Surat Jalan)</label>
                  <input
                    v-model="form.tgl_sj"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">No. SJ (Surat Jalan)</label>
                  <input
                    v-model="form.no_sj"
                    type="text"
                    placeholder="SJ-..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Info Bank</label>
                  <input
                    v-model="form.info_bank"
                    type="text"
                    placeholder="Info rekening/cabang..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div class="sm:col-span-2">
                  <label class="block font-medium text-gray-700 mb-1">Pending Potongan</label>
                  <input
                    v-model="form.pending_potongan"
                    type="text"
                    placeholder="Keterangan pending potongan..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>
              </div>
            </div>

            <!-- TAB 4: DOKUMEN & MATCHING -->
            <div v-show="activeTab === 'docs'" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">Cek Dokumen</label>
                  <input
                    v-model="form.cek_dokumen"
                    type="text"
                    placeholder="LENGKAP / TIDAK LENGKAP / BELUM MATCH TEST"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Status Potong Purchase</label>
                  <select
                    v-model="form.status_potong_purchase"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="">- Pilih Status -</option>
                    <option value="BISA DI POTONG">BISA DI POTONG</option>
                    <option value="BELUM BISA POTONG">BELUM BISA POTONG</option>
                    <option value="SUDAH POTONG">SUDAH POTONG</option>
                    <option value="PENDING">PENDING</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Status Potong AR</label>
                  <select
                    v-model="form.status_potong_ar"
                    class="w-full h-8.5 px-2.5 rounded-lg border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition cursor-pointer"
                  >
                    <option value="">- Pilih Status AR -</option>
                    <option value="BELUM POTONG">BELUM POTONG</option>
                    <option value="SUDAH POTONG">SUDAH POTONG</option>
                  </select>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Cek Faktur Pajak (FP)</label>
                  <input
                    v-model="form.cek_fp"
                    type="text"
                    placeholder="ADA / TIDAK ADA / TIDAK PERLU"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Cek Evidence</label>
                  <input
                    v-model="form.cek_evidance"
                    type="text"
                    placeholder="ADA / TIDAK ADA"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Program 2</label>
                  <input
                    v-model="form.program_2"
                    type="text"
                    placeholder="Program 2..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div class="sm:col-span-2">
                  <label class="block font-medium text-gray-700 mb-1">Link Dokumen Credit Note (CN)</label>
                  <input
                    v-model="form.cn"
                    type="text"
                    placeholder="https://drive.google.com/... atau rumus HYPERLINK"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div class="sm:col-span-2">
                  <label class="block font-medium text-gray-700 mb-1">Link Dokumen Agreement (Agr)</label>
                  <input
                    v-model="form.agrement"
                    type="text"
                    placeholder="https://drive.google.com/..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>
              </div>
            </div>

            <!-- TAB 5: REKENING & CATATAN -->
            <div v-show="activeTab === 'bank'" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">Nama Bank</label>
                  <input
                    v-model="form.bank"
                    type="text"
                    placeholder="Contoh: BCA, MANDIRI, BRI, BNI"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Nomor Rekening (Norek)</label>
                  <input
                    v-model="form.norek"
                    type="text"
                    placeholder="1234567890"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">Atas Nama Rekening (Namrek)</label>
                  <input
                    v-model="form.namrek"
                    type="text"
                    placeholder="Nama pemilik rekening..."
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition"
                  />
                </div>
              </div>

              <div>
                <label class="block font-medium text-gray-700 mb-1">Noted / Catatan Bebas</label>
                <textarea
                  v-model="form.noted"
                  rows="4"
                  placeholder="Catatan khusus, referensi draft, memo internal, dsb..."
                  class="w-full p-3 rounded-lg border border-gray-300 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 bg-white transition leading-relaxed"
                ></textarea>
              </div>
            </div>

          </form>

          <!-- Modal Footer (Clean & Rapi) -->
          <div class="px-6 py-3.5 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
            <!-- Left: Checkbox (Tanpa Icon Warna) -->
            <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs text-gray-700">
              <input
                v-model="pushToSheet"
                type="checkbox"
                class="rounded border-gray-300 text-gray-900 focus:ring-0 w-4 h-4 cursor-pointer"
              />
              <span>Kirim perubahan ke Google Spreadsheet</span>
            </label>

            <!-- Right: Action Buttons (Minimal & Profesional) -->
            <div class="flex items-center justify-end gap-2">
              <button
                type="button"
                @click="handleClose"
                :disabled="isSubmitting"
                class="px-4 h-8.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-medium transition cursor-pointer disabled:opacity-50"
              >
                Batal
              </button>

              <button
                type="submit"
                form="editDataProgramForm"
                :disabled="isSubmitting"
                class="px-5 h-8.5 rounded-lg bg-gray-900 hover:bg-black text-white text-xs font-medium transition cursor-pointer disabled:opacity-50 inline-flex items-center gap-1.5"
              >
                <RefreshCwIcon v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import axios from 'axios';
import {
  X as XIcon,
  RefreshCw as RefreshCwIcon,
} from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  dataProgram: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'saved']);

const activeTab = ref('dealer');
const isSubmitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const pushToSheet = ref(true);

const tabs = [
  { id: 'dealer', label: 'Dealer & Program' },
  { id: 'financial', label: 'Finansial & Pajak' },
  { id: 'admin', label: 'Administrasi & Transaksi' },
  { id: 'docs', label: 'Dokumen & Matching' },
  { id: 'bank', label: 'Rekening & Catatan' },
];

const form = reactive({
  // Identitas & Dealer
  dealer_name: '',
  program: '',
  kode_bt: '',
  program_name: '',
  periode: '',
  region: '',
  big_region: '',
  id_gs: '',
  status_dl: '',
  sales_person: '',
  telemarketing: '',

  // Finansial & Pajak
  wajib_pajak: '',
  trf_pph: '',
  incentive: 0,
  dpp: 0,
  dpp_lain: 0,
  ppn: 0,
  nilai_pph: 0,
  net_pay: 0,
  cek_pajak_tarif: '',
  selisih: 0,
  note_pph: '',
  no_faktur_pajak: '',
  ket_faktur_pajak: '',
  npwp: '',
  nama_npwp: '',

  // Administrasi & Transaksi
  no_po: '',
  no_po_sj: '',
  no_transaksi: '',
  tgl_input: '',
  tgl_share_cn: '',
  lama_pending: '',
  keterangan: 'KURANG DARI 30 HARI',
  tgl_potong_tf: '',
  no_uid: '',
  no_pembayaran: '',
  tgl_input_bank_pph: '',
  tf_status: '',
  tgl_proses: '',
  tgl_sj: '',
  no_sj: '',
  info_bank: '',
  pending_potongan: '',

  // Dokumen & Matching
  cek_dokumen: '',
  status_potong_purchase: '',
  status_potong_ar: '',
  cn: '',
  agrement: '',
  cek_fp: '',
  cek_evidance: '',
  program_2: '',

  // Rekening & Catatan
  bank: '',
  norek: '',
  namrek: '',
  noted: '',
});

// Watch when dataProgram changes or modal opens
watch(
  () => [props.isOpen, props.dataProgram],
  ([isOpen, dp]) => {
    if (isOpen && dp) {
      errorMessage.value = '';
      successMessage.value = '';
      activeTab.value = 'dealer';

      form.dealer_name = dp.dealer_name ?? '';
      form.program = dp.program ?? '';
      form.kode_bt = dp.kode_bt ?? '';
      form.program_name = dp.program_name ?? '';
      form.periode = dp.periode ?? '';
      form.region = dp.region ?? '';
      form.big_region = dp.big_region ?? '';
      form.id_gs = dp.id_gs ?? '';
      form.status_dl = dp.status_dl ?? '';
      form.sales_person = dp.sales_person ?? '';
      form.telemarketing = dp.telemarketing ?? '';

      form.wajib_pajak = dp.wajib_pajak ?? '';
      form.trf_pph = dp.trf_pph ?? '';
      form.incentive = dp.incentive ? Number(dp.incentive) : 0;
      form.dpp = dp.dpp ? Number(dp.dpp) : 0;
      form.dpp_lain = dp.dpp_lain ? Number(dp.dpp_lain) : 0;
      form.ppn = dp.ppn ? Number(dp.ppn) : 0;
      form.nilai_pph = dp.nilai_pph ? Number(dp.nilai_pph) : 0;
      form.net_pay = dp.net_pay ? Number(dp.net_pay) : 0;
      form.cek_pajak_tarif = dp.cek_pajak_tarif ?? '';
      form.selisih = dp.selisih ? Number(dp.selisih) : 0;
      form.note_pph = dp.note_pph ?? '';
      form.no_faktur_pajak = dp.no_faktur_pajak ?? '';
      form.ket_faktur_pajak = dp.ket_faktur_pajak ?? '';
      form.npwp = dp.npwp ?? '';
      form.nama_npwp = dp.nama_npwp ?? '';

      form.no_po = dp.no_po ?? '';
      form.no_po_sj = dp.no_po_sj ?? '';
      form.no_transaksi = dp.no_transaksi ?? '';
      form.tgl_input = dp.tgl_input ?? '';
      form.tgl_share_cn = dp.tgl_share_cn ?? '';
      form.lama_pending = dp.lama_pending ?? '';
      form.keterangan = dp.keterangan || (Number(dp.lama_pending) > 30 ? 'LEBIH DARI 30 HARI' : 'KURANG DARI 30 HARI');
      form.tgl_potong_tf = dp.tgl_potong_tf ?? '';
      form.no_uid = dp.no_uid ?? '';
      form.no_pembayaran = dp.no_pembayaran ?? '';
      form.tgl_input_bank_pph = dp.tgl_input_bank_pph ?? '';
      form.tf_status = dp.tf_status ?? '';
      form.tgl_proses = dp.tgl_proses ?? '';
      form.tgl_sj = dp.tgl_sj ?? '';
      form.no_sj = dp.no_sj ?? '';
      form.info_bank = dp.info_bank ?? '';
      form.pending_potongan = dp.pending_potongan ?? '';

      form.cek_dokumen = dp.cek_dokumen ?? '';
      form.status_potong_purchase = dp.status_potong_purchase ?? '';
      form.status_potong_ar = dp.status_potong_ar ?? '';
      form.cn = dp.cn ?? '';
      form.agrement = dp.agrement ?? '';
      form.cek_fp = dp.cek_fp ?? '';
      form.cek_evidance = dp.cek_evidance ?? '';
      form.program_2 = dp.program_2 ?? '';

      form.bank = dp.bank ?? '';
      form.norek = dp.norek ?? '';
      form.namrek = dp.namrek ?? '';
      form.noted = dp.noted ?? '';
    }
  },
  { immediate: true }
);

// Hitung otomatis Net Pay
const autoCalculateFinancials = () => {
  const dpp = Number(form.dpp) || 0;
  const ppn = Number(form.ppn) || 0;
  const pph = Number(form.nilai_pph) || 0;
  if (ppn > 0) {
    form.net_pay = Math.round((dpp + ppn - pph) * 100) / 100;
  } else {
    form.net_pay = Math.round((dpp - pph) * 100) / 100;
  }
};

// Auto update lama pending dan aging keterangan jika tgl_input diganti
const handleTglInputChange = () => {
  if (!form.tgl_input) return;
  try {
    const inputDate = new Date(form.tgl_input);
    const today = new Date();
    const diffTime = today.getTime() - inputDate.getTime();
    const diffDays = Math.max(0, Math.floor(diffTime / (1000 * 60 * 60 * 24)));
    form.lama_pending = String(diffDays);
    form.keterangan = diffDays > 30 ? 'LEBIH DARI 30 HARI' : 'KURANG DARI 30 HARI';
  } catch (e) {
    // ignore
  }
};

const handleClose = () => {
  if (isSubmitting.value) return;
  emit('close');
};

const handleSubmit = async () => {
  if (!props.dataProgram?.id) return;

  isSubmitting.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      ...form,
      push_to_sheet: pushToSheet.value,
    };

    const res = await axios.put(`/api/data-program/${props.dataProgram.id}`, payload);

    if (res.data?.success) {
      successMessage.value = res.data.message || 'Data Program berhasil disimpan.';
      emit('saved', res.data.data);
      setTimeout(() => {
        handleClose();
      }, 700);
    } else {
      errorMessage.value = res.data?.message || 'Gagal menyimpan Data Program.';
    }
  } catch (err) {
    console.error('Error updating DataProgram:', err);
    errorMessage.value = err.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>
