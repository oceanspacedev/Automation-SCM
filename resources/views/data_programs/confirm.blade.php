<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Klaim Program - SCM Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body, input, button, select, textarea {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            letter-spacing: -0.011em;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 text-xs min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl border border-gray-200 shadow-2xs p-5">

        <!-- Top Bar Header -->
        <div class="pb-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">SCM Automation</span>
            <span class="text-xs font-medium px-2.5 py-0.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-700">
                {{ $role === 'ar' ? 'Tim AR' : 'Telemarketing' }}
            </span>
        </div>

        <div class="py-3.5">
            <h1 class="text-sm font-semibold text-gray-950">
                @if($role === 'ar')
                    Konfirmasi Pemotongan Piutang (AR)
                @else
                    Konfirmasi Klaim Program Dealer
                @endif
            </h1>
            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                @if($role === 'ar')
                    Mohon konfirmasi jika pemotongan saldo telah diproses pada invoice order dealer.
                @else
                    Apakah dealer setuju untuk memotong saldo insentif klaim ini pada order pembelian mereka?
                @endif
            </p>
        </div>

        @if($dp->status_potong_purchase === 'SUDAH POTONG')
            <div class="mb-3.5 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800">
                Klaim ini sudah selesai dipotong pada tanggal {{ $dp->tgl_potong_tf ?: 'sebelumnya' }}.
            </div>
        @elseif($dp->status_potong_ar === 'DEALER SETUJU (PROSES AR)' && $role !== 'ar')
            <div class="mb-3.5 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700">
                Pengajuan ini sudah disetujui sebelumnya dan telah diteruskan ke Tim AR.
            </div>
        @endif

        <!-- Details List (Persis sama dengan tabel di Invoice Detail) -->
        <div class="border-t border-b border-gray-100 py-2.5 my-2">
            <table class="w-full text-xs">
                <tbody>
                    <tr>
                        <td class="py-1 text-gray-500 w-36 align-top">Nama Dealer</td>
                        <td class="py-1 text-gray-900 font-medium text-right">{{ $dp->dealer_name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Kode BT / ID Real</td>
                        <td class="py-1 text-gray-800 text-right">{{ $dp->kode_bt ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Nama Program</td>
                        <td class="py-1 text-gray-800 text-right">{{ $dp->program_name ?: ($dp->program ?: '-') }}</td>
                    </tr>
                    @if($dp->periode)
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Periode</td>
                        <td class="py-1 text-gray-800 text-right">{{ $dp->periode }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Nominal Potongan</td>
                        <td class="py-1 font-semibold text-gray-950 text-right">Rp {{ number_format((float) ($dp->net_pay ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Status Purchase</td>
                        <td class="py-1 text-right">
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                {{ $dp->status_potong_purchase ?: 'BISA DI POTONG' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Region / Sales</td>
                        <td class="py-1 text-gray-800 text-right">{{ $dp->region ?: ($dp->big_region ?: '-') }} / {{ $dp->sales_person ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Status Dokumen</td>
                        <td class="py-1 text-gray-900 font-medium text-right">{{ $dp->cek_dokumen ?: 'LENGKAP' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Buttons Form -->
        <form method="POST" action="{{ request()->fullUrl() }}" class="pt-3">
            @csrf
            <input type="hidden" name="role" value="{{ $role }}">

            @if($role === 'ar')
                @php($showPaymentForm = $errors->has('no_pembayaran') || old('no_pembayaran'))

                <!-- Step 1: Pilihan aksi -->
                <div id="ar-actions" class="space-y-2 {{ $showPaymentForm ? 'hidden' : '' }}">
                    <button type="button" id="btn-show-payment-form" onclick="togglePaymentForm(true)"
                            class="w-full h-9 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium transition cursor-pointer shadow-2xs">
                        Iya, Sudah Dipotong
                    </button>
                    <button type="submit" name="action" value="tunda" formnovalidate id="btn-tunda"
                            class="w-full h-9 px-4 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-xs font-medium border border-gray-200 transition cursor-pointer shadow-2xs">
                        Tunda Pemotongan
                    </button>
                </div>

                <!-- Step 2: Input No. Pembayaran -->
                <div id="payment-form" class="{{ $showPaymentForm ? '' : 'hidden' }} space-y-3">
                    <div>
                        <label for="no_pembayaran" class="block text-xs font-medium text-gray-700 mb-1">
                            No. Pembayaran
                        </label>
                        <input type="text" id="no_pembayaran" name="no_pembayaran"
                               value="{{ old('no_pembayaran', $dp->no_pembayaran) }}"
                               placeholder="Contoh: PAY/2026/10/0001"
                               maxlength="255"
                               autocomplete="off"
                               {{ $showPaymentForm ? 'required' : '' }}
                               class="w-full h-9 px-3 text-xs bg-white text-gray-900 placeholder-gray-400 border {{ $errors->has('no_pembayaran') ? 'border-red-400 focus:border-red-500' : 'border-gray-200 focus:border-gray-900' }} rounded-lg focus:outline-none focus:ring-1 focus:ring-gray-900 transition">
                        @error('no_pembayaran')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="togglePaymentForm(false)" id="btn-batal"
                                class="h-9 px-3 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-xs font-medium border border-gray-200 transition cursor-pointer shadow-2xs">
                            Batal
                        </button>
                        <button type="submit" name="action" value="potong" id="btn-submit-potong"
                                class="h-9 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium transition cursor-pointer shadow-2xs">
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
                            class="w-full h-9 px-4 bg-[#1D70F5] hover:bg-blue-600 text-white rounded-lg text-xs font-medium transition cursor-pointer shadow-2xs">
                        Dealer Setuju Dipotong (Kirim ke AR)
                    </button>
                    <button type="submit" name="action" value="tunda"
                            class="w-full h-9 px-4 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-xs font-medium border border-gray-200 transition cursor-pointer shadow-2xs">
                        Dealer Menolak / Tunda Dulu
                    </button>
                </div>
            @endif
        </form>

        <p class="text-[11px] text-gray-400 text-center mt-4">
            Keputusan akan tersimpan dan tersinkronisasi otomatis ke Google Spreadsheet master.
        </p>
    </div>
</body>
</html>
