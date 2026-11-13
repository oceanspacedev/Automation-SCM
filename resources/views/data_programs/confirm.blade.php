@extends('data_programs.layout')

@section('title', 'Konfirmasi Klaim Program')
@section('tag', match ($role) { 'ar' => 'Tim AR', 'pajak' => 'Tim Pajak', 'scm' => 'Tim SCM', default => 'Telemarketing' })
@section('footer', 'Keputusan tersimpan dan tersinkron otomatis ke Google Spreadsheet master')

@php
    $rupiah = fn ($amount) => 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    // Tarif PPh 0.025 ditampilkan 2,5%
    $tarif = is_numeric($dp->trf_pph) && (float) $dp->trf_pph > 0 && (float) $dp->trf_pph < 1
        ? str_replace('.', ',', rtrim(rtrim(number_format((float) $dp->trf_pph * 100, 2, '.', ''), '0'), '.')).'%'
        : $dp->trf_pph;
    $periode = preg_replace('/^periode\s+/i', '', (string) $dp->periode);
    $program = $dp->program_name ?: $dp->program;
    $transfer = $dp->isTransfer();

    $rows = match ($role) {
        'pajak' => [
            ['label' => 'Program', 'value' => $program, 'sub' => $periode, 'wide' => true],
            ['label' => 'DPP', 'value' => $rupiah($dp->dpp)],
            ['label' => 'PPN', 'value' => $rupiah($dp->ppn)],
            ['label' => 'PPh', 'value' => $rupiah($dp->nilai_pph)],
            ['label' => 'Net Pay', 'value' => $rupiah($dp->net_pay), 'strong' => true],
            ['label' => 'Wajib Pajak', 'value' => $dp->wajib_pajak],
            ['label' => 'Tarif PPh', 'value' => $tarif],
            ['label' => $transfer ? 'Tgl Transfer' : 'Tgl Potong', 'value' => $dp->tgl_potong_tf],
            ['label' => $transfer ? 'No. Referensi' : 'No. Pembayaran', 'value' => $dp->no_pembayaran],
            ['label' => 'NPWP', 'value' => $dp->npwp, 'sub' => $dp->nama_npwp, 'wide' => true],
        ],
        'scm' => [
            ['label' => 'Program', 'value' => $program, 'sub' => $periode, 'wide' => true],
            ['label' => 'Nominal Transfer', 'value' => $rupiah($dp->net_pay), 'strong' => true],
            ['label' => 'Bank', 'value' => $dp->bank],
            ['label' => 'No. Rekening', 'value' => $dp->norek],
            ['label' => 'Atas Nama', 'value' => $dp->namrek, 'wide' => true],
        ],
        default => [
            ['label' => 'Program', 'value' => $program, 'sub' => $periode, 'wide' => true],
            ['label' => 'Nominal Potongan', 'value' => $rupiah($dp->net_pay), 'strong' => true],
            ['label' => 'Status Dokumen', 'value' => $dp->cek_dokumen ?: 'LENGKAP'],
        ],
    };
    if (! in_array($role, ['pajak', 'scm', 'ar'], true)) {
        $rows[1]['label'] = 'Nominal Klaim';
    }

    // Aksi tautan ini hanya tampil di tahap yang diterima controller (tautan lama tidak memproses klaim dua kali)
    $stage ??= app(\App\Services\ProgramApprovalFlow::class)->stage($dp);
    $settled = in_array($stage, ['cut', 'tax_checked'], true);
    $canAct = match ($role) {
        'pajak' => $settled,
        'ar' => $stage === 'approved',
        'scm' => $stage === 'awaiting_transfer' && filled($dp->bank) && filled($dp->norek) && filled($dp->namrek),
        default => ! $settled,
    };
    $blockedNote = match (true) {
        $canAct, $settled && $role !== 'pajak' => null,
        $role === 'ar' && $stage === 'awaiting_transfer' => 'Klaim ini dialihkan ke transfer lewat Tim SCM, jadi tidak perlu dipotong.',
        $role === 'scm' && $stage === 'awaiting_transfer' => 'Rekening dealer belum lengkap. Minta telemarketing mengisinya lewat tautan WA klaim ini.',
        $role === 'scm' && $stage === 'approved' => 'Klaim ini dipotong lewat AR, jadi tidak perlu ditransfer.',
        $role === 'pajak' => 'Klaim ini belum dipotong atau ditransfer, jadi belum bisa dicek pajak.',
        default => 'Klaim ini belum menunggu konfirmasi dari tautan ini.',
    };

    $inputClass = 'w-full h-10 px-3 text-sm bg-white text-gray-900 placeholder:text-gray-400 border rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#1769e0] focus:border-[#1769e0] transition-colors';
    // Satu warna aksen (biru logo SCM) untuk aksi utama di semua peran; fokus keyboard terlihat
    $buttonClass = 'w-full h-10 px-4 rounded-[10px] text-sm font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1769e0]';
@endphp

@section('content')
    <div>
        <h1 class="text-base font-medium leading-6 text-gray-950">
            @if($role === 'pajak')
                Cek pajak klaim yang sudah {{ $transfer ? 'ditransfer' : 'dipotong' }}
            @elseif($role === 'ar')
                Konfirmasi pemotongan piutang (AR)
            @elseif($role === 'scm')
                Konfirmasi transfer klaim
            @else
                Konfirmasi klaim program dealer
            @endif
        </h1>
        <p class="mt-1 text-sm leading-5 text-gray-500">
            @if($role === 'pajak')
                Cek PPh-nya, lalu isi Cek Pajak, Selisih, dan Note PPh
            @elseif($role === 'ar')
                Konfirmasi bila pemotongan saldo sudah diproses pada invoice order dealer
            @elseif($role === 'scm')
                Konfirmasi bila saldo insentif sudah ditransfer ke rekening dealer
            @else
                Apakah dealer setuju saldo insentif klaim ini dipotong pada order pembeliannya, atau ditransfer?
            @endif
        </p>
    </div>

    @if($role === 'pajak')
        @if($dp->tax_checked_at)
            <p class="rounded-[10px] px-4 py-3 bg-emerald-50 text-xs text-emerald-800 leading-5">
                Sudah dicek pada {{ $dp->tax_checked_at->format('d/m/Y H:i') }} WIB. Simpan lagi bila ingin mengubah isinya.
            </p>
        @endif
    @elseif($dp->status_potong_purchase === 'SUDAH POTONG')
        <p class="rounded-[10px] px-4 py-3 bg-emerald-50 text-xs text-emerald-800 leading-5">
            Klaim ini sudah selesai dipotong pada tanggal {{ $dp->tgl_potong_tf ?: 'sebelumnya' }}.
        </p>
    @elseif($dp->status_potong_purchase === \App\Models\DataProgram::DONE_TRANSFER)
        <p class="rounded-[10px] px-4 py-3 bg-emerald-50 text-xs text-emerald-800 leading-5">
            Klaim ini sudah ditransfer pada tanggal {{ $dp->tgl_potong_tf ?: 'sebelumnya' }}.
        </p>
    @elseif($dp->status_potong_ar === 'DEALER SETUJU (PROSES AR)' && $role !== 'ar')
        <p class="rounded-[10px] px-4 py-3 bg-gray-50 text-xs text-gray-700 leading-5">
            Pengajuan ini sudah disetujui sebelumnya dan telah diteruskan ke Tim AR.
        </p>
    @elseif($dp->status_potong_ar === \App\Models\DataProgram::TRANSFER_APPROVED && $role !== 'scm')
        <p class="rounded-[10px] px-4 py-3 bg-gray-50 text-xs text-gray-700 leading-5">
            Pengajuan ini sudah disetujui untuk ditransfer dan telah diteruskan ke Tim SCM.
        </p>
    @endif

    @include('data_programs.partials.claim', [
        'status' => ['label' => $dp->status_potong_purchase ?: 'BISA DI POTONG', 'tone' => 'emerald'],
        'rows' => $rows,
        'documents' => $documents,
        'compact' => $role === 'pajak',
    ])

    @if($blockedNote)
        <p class="rounded-[10px] px-4 py-3 bg-gray-50 text-xs text-gray-700 leading-5">{{ $blockedNote }}</p>
    @endif

    @if($canAct)
    <form method="POST" action="{{ request()->fullUrl() }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="role" value="{{ $role }}">

        @if($role === 'pajak')
            <div class="space-y-4">
                <div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cek_pajak_tarif" class="block text-xs font-medium text-gray-700 mb-2">Cek Pajak</label>
                            <input type="text" id="cek_pajak_tarif" name="cek_pajak_tarif" required maxlength="100" autocomplete="off"
                                   value="{{ old('cek_pajak_tarif', $dp->cek_pajak_tarif) }}"
                                   placeholder="mis. 15.000"
                                   class="{{ $inputClass }} {{ $errors->has('cek_pajak_tarif') ? 'border-red-400' : 'border-gray-200' }}">
                        </div>
                        <div>
                            <label for="selisih" class="block text-xs font-medium text-gray-700 mb-2">Selisih <span class="font-normal text-gray-500">(otomatis, boleh diubah)</span></label>
                            <input type="text" id="selisih" name="selisih" inputmode="decimal" maxlength="30" autocomplete="off"
                                   value="{{ old('selisih', $dp->tax_checked_at || $dp->selisih ? number_format((float) $dp->selisih, 0, ',', '.') : '') }}"
                                   placeholder="mis. 0"
                                   class="{{ $inputClass }} {{ $errors->has('selisih') ? 'border-red-400' : 'border-gray-200' }}">
                        </div>
                    </div>
                    @error('cek_pajak_tarif')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('selisih')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="note_pph" class="block text-xs font-medium text-gray-700 mb-2">Note PPh</label>
                    <input type="text" id="note_pph" name="note_pph" maxlength="255" autocomplete="off"
                           value="{{ old('note_pph', $dp->note_pph) }}"
                           placeholder="mis. OK, kurang potong, salah tarif"
                           class="{{ $inputClass }} {{ $errors->has('note_pph') ? 'border-red-400' : 'border-gray-200' }}">
                    @error('note_pph')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" name="action" value="cek_pajak" id="btn-submit-cek-pajak"
                        class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                    Simpan cek pajak
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
                        class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                    Iya, sudah dipotong
                </button>
                <button type="submit" name="action" value="tunda" formnovalidate id="btn-tunda" {{ $showPaymentForm ? 'disabled' : '' }}
                        class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-900 border border-gray-200">
                    Tunda pemotongan
                </button>
            </div>

            <!-- Langkah 2: isi No. Pembayaran -->
            <div id="payment-form" class="{{ $showPaymentForm ? '' : 'hidden' }} space-y-4">
                <div>
                    <label for="no_pembayaran" class="block text-xs font-medium text-gray-700 mb-2">No. Pembayaran</label>
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
                            class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-900 border border-gray-200">
                        Batal
                    </button>
                    <button type="submit" name="action" value="potong" id="btn-submit-potong"
                            class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                        Simpan &amp; selesaikan
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
                    // Enter di isian hanya boleh menekan "Simpan", bukan tombol tunda yang tersembunyi
                    actions.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = show; });

                    if (show) {
                        input.focus();
                    }
                }
            </script>
        @elseif($role === 'scm')
            @php($showTransferForm = $errors->has('no_pembayaran') || old('no_pembayaran'))

            <!-- Langkah 1: konfirmasi -->
            <div id="scm-actions" class="{{ $showTransferForm ? 'hidden' : '' }}">
                <button type="button" id="btn-show-transfer-form" onclick="toggleStep('scm-actions', 'transfer-form', true)"
                        class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                    Sudah ditransfer
                </button>
            </div>

            <!-- Langkah 2: isi No. Referensi transfer -->
            <div id="transfer-form" class="{{ $showTransferForm ? '' : 'hidden' }} space-y-4">
                <div>
                    <label for="no_pembayaran" class="block text-xs font-medium text-gray-700 mb-2">No. Referensi Transfer</label>
                    <input type="text" id="no_pembayaran" name="no_pembayaran" data-step-required
                           value="{{ old('no_pembayaran', $dp->no_pembayaran) }}"
                           placeholder="mis. no. referensi dari bukti transfer"
                           maxlength="255"
                           autocomplete="off"
                           {{ $showTransferForm ? 'required' : '' }}
                           class="{{ $inputClass }} {{ $errors->has('no_pembayaran') ? 'border-red-400' : 'border-gray-200' }}">
                    @error('no_pembayaran')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="toggleStep('scm-actions', 'transfer-form', false)"
                            class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-900 border border-gray-200">
                        Batal
                    </button>
                    <button type="submit" name="action" value="transfer" id="btn-submit-transfer"
                            class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                        Simpan &amp; selesaikan
                    </button>
                </div>
            </div>
        @else
            @php($showAccountForm = $errors->hasAny(['bank', 'norek', 'namrek', 'ktp_npwp']) || old('norek'))
            @php($hasEvidence = filter_var(trim((string) $dp->cek_evidance), FILTER_VALIDATE_URL) !== false)

            <!-- Langkah 1: keputusan dealer -->
            <div id="telemarketing-actions" class="space-y-2 {{ $showAccountForm ? 'hidden' : '' }}">
                <button type="submit" name="action" value="setuju" formnovalidate {{ $showAccountForm ? 'disabled' : '' }}
                        class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                    Dealer setuju dipotong (kirim ke AR)
                </button>
                <button type="button" id="btn-show-account-form" onclick="toggleStep('telemarketing-actions', 'account-form', true)"
                        class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-900 border border-gray-200">
                    Dealer setuju ditransfer (kirim ke SCM)
                </button>
                <button type="submit" name="action" value="tunda" formnovalidate {{ $showAccountForm ? 'disabled' : '' }}
                        class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-900 border border-gray-200">
                    Dealer menolak / tunda dulu
                </button>
            </div>

            <!-- Langkah 2: rekening dealer untuk transfer -->
            <div id="account-form" class="{{ $showAccountForm ? '' : 'hidden' }} space-y-4">
                @foreach ([['bank', 'Bank', 'mis. BCA', 100], ['norek', 'No. Rekening', 'mis. 1234567890', 100], ['namrek', 'Atas Nama', 'Nama pemilik rekening', 255]] as [$field, $label, $placeholder, $max])
                    <div>
                        <label for="{{ $field }}" class="block text-xs font-medium text-gray-700 mb-2">{{ $label }}</label>
                        <input type="text" id="{{ $field }}" name="{{ $field }}" data-step-required
                               value="{{ old($field, $dp->{$field}) }}"
                               placeholder="{{ $placeholder }}"
                               maxlength="{{ $max }}"
                               autocomplete="off"
                               @if($field === 'norek') inputmode="numeric" @endif
                               {{ $showAccountForm ? 'required' : '' }}
                               class="{{ $inputClass }} {{ $errors->has($field) ? 'border-red-400' : 'border-gray-200' }}">
                        @error($field)
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div>
                    <label for="ktp_npwp" class="block text-xs font-medium text-gray-700 mb-2">KTP / NPWP pemilik rekening</label>
                    <input type="file" id="ktp_npwp" name="ktp_npwp" accept=".pdf,.jpg,.jpeg,.png,.webp"
                           @unless($hasEvidence) data-step-required {{ $showAccountForm ? 'required' : '' }} @endunless
                           class="block w-full text-sm text-gray-700 file:mr-3 file:h-9 file:px-3 file:rounded-[10px] file:border-0 file:bg-gray-100 file:text-sm file:font-medium file:text-gray-800 hover:file:bg-gray-200 file:cursor-pointer">
                    @if($hasEvidence)
                        <p class="mt-1 text-xs text-gray-500">Sudah ada. Unggah lagi untuk mengganti.</p>
                    @endif
                    @error('ktp_npwp')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="toggleStep('telemarketing-actions', 'account-form', false)"
                            class="{{ $buttonClass }} bg-white hover:bg-gray-50 text-gray-900 border border-gray-200">
                        Batal
                    </button>
                    <button type="submit" name="action" value="setuju_transfer" id="btn-submit-transfer-request"
                            class="{{ $buttonClass }} bg-[#1769e0] hover:bg-[#1257c2] text-white">
                        Kirim ke SCM
                    </button>
                </div>
            </div>
        @endif

        @if(! in_array($role, ['ar', 'pajak'], true))
            <script>
                // Tampilkan langkah kedua (isian wajib) atau kembali ke pilihan aksi
                function toggleStep(actionsId, formId, show) {
                    const form = document.getElementById(formId);
                    const actions = document.getElementById(actionsId);
                    actions.classList.toggle('hidden', show);
                    form.classList.toggle('hidden', !show);
                    form.querySelectorAll('[data-step-required]').forEach((input) => { input.required = show; });
                    // Enter di isian hanya boleh menekan tombol kirim langkah ini, bukan tombol tersembunyi di langkah 1
                    actions.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = show; });

                    if (show) {
                        form.querySelector('input')?.focus();
                    }
                }
            </script>
        @endif
    </form>
    @endif
@endsection
