<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Klaim Program - SCM Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl border border-gray-200 shadow-xs p-6">
        
        <!-- Header -->
        <div class="mb-5 pb-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-400">SCM Automation</span>
            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $role === 'ar' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                {{ $role === 'ar' ? 'Tim AR' : 'Telemarketing' }}
            </span>
        </div>

        <div class="mb-4">
            <h1 class="text-base font-semibold text-gray-900">
                @if($role === 'ar')
                    Konfirmasi Pemotongan Piutang (AR)
                @else
                    Konfirmasi Klaim Program Dealer
                @endif
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                @if($role === 'ar')
                    Mohon konfirmasi jika pemotongan saldo telah diproses pada invoice order dealer.
                @else
                    Apakah dealer setuju untuk memotong saldo insentif klaim ini pada order pembelian mereka?
                @endif
            </p>
        </div>

        @if($dp->status_potong_purchase === 'SUDAH POTONG')
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
                Klaim ini sudah selesai dipotong pada tanggal {{ $dp->tgl_potong_tf ?: 'sebelumnya' }}.
            </div>
        @elseif($dp->status_potong_ar === 'DEALER SETUJU (PROSES AR)' && $role !== 'ar')
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
                Pengajuan ini sudah disetujui sebelumnya dan telah diteruskan ke Tim AR.
            </div>
        @endif

        <!-- Details List -->
        <div class="border-t border-b border-gray-100 py-3 my-4 space-y-2.5 text-sm">
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-500 shrink-0">Nama Dealer</span>
                <span class="font-medium text-gray-900 text-right">{{ $dp->dealer_name ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Kode BT / ID Real</span>
                <span class="font-mono text-gray-900 font-medium">{{ $dp->kode_bt ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-500 shrink-0">Nama Program</span>
                <span class="text-gray-900 text-right">{{ $dp->program_name ?: ($dp->program ?: '-') }}</span>
            </div>
            @if($dp->periode)
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Periode</span>
                <span class="text-gray-900 text-right text-xs">{{ $dp->periode }}</span>
            </div>
            @endif
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Nominal Potongan (Net Pay)</span>
                <span class="font-bold text-gray-900 text-emerald-700">Rp {{ number_format((float) ($dp->net_pay ?? 0), 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Status Purchase</span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                    {{ $dp->status_potong_purchase ?: 'BISA DI POTONG' }}
                </span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Region / Sales</span>
                <span class="text-gray-900 text-right">{{ $dp->region ?: ($dp->big_region ?: '-') }} / {{ $dp->sales_person ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Status Dokumen</span>
                <span class="text-gray-900 font-medium">{{ $dp->cek_dokumen ?: 'LENGKAP' }}</span>
            </div>
        </div>

        <!-- Buttons Form -->
        <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-2 pt-1">
            @csrf
            <input type="hidden" name="role" value="{{ $role }}">

            @if($role === 'ar')
                <button type="submit" name="action" value="potong"
                        class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors cursor-pointer text-center flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Iya, Sudah Dipotong (Selesai)</span>
                </button>
                <button type="submit" name="action" value="tunda"
                        class="w-full py-2.5 px-4 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium border border-gray-300 transition-colors cursor-pointer text-center">
                    Tunda Pemotongan
                </button>
            @else
                <button type="submit" name="action" value="setuju"
                        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors cursor-pointer text-center flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Dealer Setuju Dipotong (Kirim ke AR)</span>
                </button>
                <button type="submit" name="action" value="tunda"
                        class="w-full py-2.5 px-4 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium border border-gray-300 transition-colors cursor-pointer text-center">
                    Dealer Menolak / Tunda Dulu
                </button>
            @endif
        </form>

        <p class="text-xs text-gray-400 text-center mt-5">
            Keputusan akan tersimpan dan tersinkronisasi otomatis ke Google Spreadsheet master.
        </p>
    </div>
</body>
</html>
