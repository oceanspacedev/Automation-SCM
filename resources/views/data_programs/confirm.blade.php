@extends('data_programs.layout')

@section('title', 'Konfirmasi Klaim Program')
@section('tag', match ($role) { 'ar' => 'Tim AR', 'pajak' => 'Tim Pajak', default => 'Telemarketing' })
@section('footer', 'Keputusan tersimpan dan tersinkron otomatis ke Google Spreadsheet master.')

@php
    $rupiah = fn ($amount) => 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    // Tarif PPh 0.025 ditampilkan 2,5%
    $tarif = is_numeric($dp->trf_pph) && (float) $dp->trf_pph > 0 && (float) $dp->trf_pph < 1
        ? str_replace('.', ',', rtrim(rtrim(number_format((float) $dp->trf_pph * 100, 2, '.', ''), '0'), '.')).'%'
        : $dp->trf_pph;
    $periode = preg_replace('/^periode\s+/i', '', (string) $dp->periode);
    $program = $dp->program_name ?: $dp->program;

    $rows = $role === 'pajak'
        ? [
            ['label' => 'Program', 'value' => $program, 'sub' => $periode, 'wide' => true],
            ['label' => 'DPP', 'value' => $rupiah($dp->dpp)],
            ['label' => 'PPN', 'value' => $rupiah($dp->ppn)],
            ['label' => 'PPh', 'value' => $rupiah($dp->nilai_pph)],
            ['label' => 'Net Pay', 'value' => $rupiah($dp->net_pay), 'strong' => true],
            ['label' => 'Wajib Pajak', 'value' => $dp->wajib_pajak],
            ['label' => 'Tarif PPh', 'value' => $tarif],
            ['label' => 'Tgl Potong', 'value' => $dp->tgl_potong_tf],
            ['label' => 'No. Pembayaran', 'value' => $dp->no_pembayaran],
            ['label' => 'NPWP', 'value' => $dp->npwp, 'sub' => $dp->nama_npwp, 'wide' => true],
        ]
        : [
            ['label' => 'Program', 'value' => $program, 'sub' => $periode, 'wide' => true],
            ['label' => 'Nominal Potongan', 'value' => $rupiah($dp->net_pay), 'strong' => true],
            ['label' => 'Status Dokumen', 'value' => $dp->cek_dokumen ?: 'LENGKAP'],
        ];

    $inputClass = 'w-full h-10 px-3 text-sm bg-white text-gray-900 placeholder:text-gray-400 border rounded-lg focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition';
    $buttonClass = 'w-full h-10 px-4 rounded-lg text-sm font-medium transition cursor-pointer';
@endphp

@section('content')
    <div>
        <h1 class="text-base font-semibold text-gray-950">
            @if($role === 'pajak')
                Cek Pajak Klaim yang Sudah Dipotong
            @elseif($role === 'ar')
                Konfirmasi Pemotongan Piutang (AR)
            @else
                Konfirmasi Klaim Program Dealer
            @endif
        </h1>
        <p class="mt-1 text-sm text-gray-500 leading-relaxed">
            @if($role === 'pajak')
                Klaim ini sudah dipotong AR. Cek PPh-nya, lalu isi Cek Pajak, Selisih, dan Note PPh.
            @elseif($role === 'ar')
                Konfirmasi bila pemotongan saldo sudah diproses pada invoice order dealer.
            @else
                Apakah dealer setuju saldo insentif klaim ini dipotong pada order pembeliannya?
            @endif
        </p>
    </div>

    @if($role === 'pajak')
        @if($dp->tax_checked_at)
            <p class="rounded-lg px-3 py-2.5 bg-emerald-50 text-xs text-emerald-800 leading-relaxed">
                Sudah dicek pada {{ $dp->tax_checked_at->format('d/m/Y H:i') }} WIB. Simpan lagi bila ingin mengubah isinya.
            </p>
        @endif
    @elseif($dp->status_potong_purchase === 'SUDAH POTONG')
        <p class="rounded-lg px-3 py-2.5 bg-emerald-50 text-xs text-emerald-800 leading-relaxed">
            Klaim ini sudah selesai dipotong pada tanggal {{ $dp->tgl_potong_tf ?: 'sebelumnya' }}.
        </p>
    @elseif($dp->status_potong_ar === 'DEALER SETUJU (PROSES AR)' && $role !== 'ar')
        <p class="rounded-lg px-3 py-2.5 bg-gray-50 text-xs text-gray-700 leading-relaxed">
            Pengajuan ini sudah disetujui sebelumnya dan telah diteruskan ke Tim AR.
        </p>
    @endif

    @include('data_programs.partials.claim', [
        'status' => ['label' => $dp->status_potong_purchase ?: 'BISA DI POTONG', 'tone' => 'emerald'],
        'rows' => $rows,
        'documents' => $documents,
    ])

    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <input type="hidden" name="role" value="{{ $role }}">

        @if($role === 'pajak')
            <div class="space-y-4">
                <div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cek_pajak_tarif" class="block text-xs font-medium text-gray-700 mb-1.5">Cek Pajak</label>
                            <input type="text" id="cek_pajak_tarif" name="cek_pajak_tarif" required maxlength="100" autocomplete="off"
                                   value="{{ old('cek_pajak_tarif', $dp->cek_pajak_tarif) }}"
                                   placeholder="mis. 15.000"
                                   class="{{ $inputClass }} {{ $errors->has('cek_pajak_tarif') ? 'border-red-400' : 'border-gray-200' }}">
                        </div>
                        <div>
                            <label for="selisih" class="block text-xs font-medium text-gray-700 mb-1.5">Selisih</label>
                            <input type="text" id="selisih" name="selisih" inputmode="decimal" maxlength="30" autocomplete="off"
                                   value="{{ old('selisih', $dp->tax_checked_at || $dp->selisih ? number_format((float) $dp->selisih, 0, ',', '.') : '') }}"
                                   placeholder="mis. 0"
                                   class="{{ $inputClass }} {{ $errors->has('selisih') ? 'border-red-400' : 'border-gray-200' }}">
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">Selisih terisi otomatis: PPh − Cek Pajak (boleh diubah).</p>
                    @error('cek_pajak_tarif')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('selisih')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="note_pph" class="block text-xs font-medium text-gray-700 mb-1.5">Note PPh</label>
                    <input type="text" id="note_pph" name="note_pph" maxlength="255" autocomplete="off"
                           value="{{ old('note_pph', $dp->note_pph) }}"
                           placeholder="mis. OK, kurang potong, salah tarif"
                           class="{{ $inputClass }} {{ $errors->has('note_pph') ? 'border-red-400' : 'border-gray-200' }}">
                    @error('note_pph')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" name="action" value="cek_pajak" id="btn-submit-cek-pajak"
                        class="{{ $buttonClass }} bg-gray-900 hover:bg-gray-800 text-white">
                    Simpan Cek Pajak
                </button>
            </div>

            <script>
                // Selisih = PPh − Cek Pajak (format Indonesia: titik ribuan, koma desimal), selama belum diubah sendiri
                (function () {
                    const pph = {{ (float) ($dp->nilai_pph ?? 0) }};
                    const cekPajak = document.getElementById('cek_pajak_tarif');
                    const selisih = document.getElementById('selisih');
                    let selisihEdited = selisih.value.trim() !== '';

                    const parseAmount = (text) => {
                        const clean = text.replace(/Rp|\s/gi, '');
                        if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(clean)) return Number(clean.replace(/\./g, '').replace(',', '.'));
                        if (/^\d+([.,]\d+)?$/.test(clean)) return Number(clean.replace(',', '.'));
                        return NaN;
                    };

                    selisih.addEventListener('input', () => { selisihEdited = selisih.value.trim() !== ''; });
                    cekPajak.addEventListener('input', () => {
                        const amount = parseAmount(cekPajak.value);
                        if (selisihEdited || Number.isNaN(amount)) return;
                        selisih.value = Math.round(pph - amount).toLocaleString('id-ID');
                    });
                })();
            </script>
        @elseif($role === 'ar')
            @php($showPaymentForm = $errors->has('no_pembayaran') || old('no_pembayaran'))

            <!-- Langkah 1: pilihan aksi -->
            <div id="ar-actions" class="space-y-2 {{ $showPaymentForm ? 'hidden' : '' }}">
                <button type="button" id="btn-show-payment-form" onclick="togglePaymentForm(true)"
                        class="{{ $buttonClass }} bg-emerald-600 hover:bg-emerald-700 text-white">
                    Iya, Sudah Dipotong
                </button>
                <button type="submit" name="action" value="tunda" formnovalidate id="btn-tunda"
                        class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-700 border border-gray-200">
                    Tunda Pemotongan
                </button>
            </div>

            <!-- Langkah 2: isi No. Pembayaran -->
            <div id="payment-form" class="{{ $showPaymentForm ? '' : 'hidden' }} space-y-4">
                <div>
                    <label for="no_pembayaran" class="block text-xs font-medium text-gray-700 mb-1.5">No. Pembayaran</label>
                    <input type="text" id="no_pembayaran" name="no_pembayaran"
                           value="{{ old('no_pembayaran', $dp->no_pembayaran) }}"
                           placeholder="mis. PAY/2026/10/0001"
                           maxlength="255"
                           autocomplete="off"
                           {{ $showPaymentForm ? 'required' : '' }}
                           class="{{ $inputClass }} {{ $errors->has('no_pembayaran') ? 'border-red-400' : 'border-gray-200' }}">
                    @error('no_pembayaran')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="togglePaymentForm(false)" id="btn-batal"
                            class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-700 border border-gray-200">
                        Batal
                    </button>
                    <button type="submit" name="action" value="potong" id="btn-submit-potong"
                            class="{{ $buttonClass }} bg-emerald-600 hover:bg-emerald-700 text-white">
                        Simpan &amp; Selesaikan
                    </button>
                </div>
            </div>

            <script>
                function togglePaymentForm(show) {
                    const actions = document.getElementById('ar-actions');
                    const form = document.getElementById('payment-form');
                    const input = document.getElementById('no_pembayaran');

                    actions.classList.toggle('hidden', show);
                    form.classList.toggle('hidden', !show);
                    input.required = show;

                    if (show) {
                        input.focus();
                    }
                }
            </script>
        @else
            <div class="space-y-2">
                <button type="submit" name="action" value="setuju"
                        class="{{ $buttonClass }} bg-[#1D70F5] hover:bg-blue-600 text-white">
                    Dealer Setuju Dipotong (Kirim ke AR)
                </button>
                <button type="submit" name="action" value="tunda"
                        class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-700 border border-gray-200">
                    Dealer Menolak / Tunda Dulu
                </button>
            </div>
        @endif
    </form>
@endsection
