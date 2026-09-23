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
            <span class="text-xs text-gray-500">{{ $role === 'ar' ? 'Tim AR' : 'Telemarketing' }}</span>
        </div>

        <div class="mb-4">
            <h1 class="text-base font-semibold text-gray-900">
                @if($role === 'ar')
                    Konfirmasi Pemotongan Klaim
                @else
                    Konfirmasi Klaim Program
                @endif
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                @if($role === 'ar')
                    Mohon konfirmasi jika pemotongan saldo telah diproses pada invoice order.
                @else
                    Apakah dealer setuju untuk memotong saldo insentif klaim ini pada order mereka?
                @endif
            </p>
        </div>

        @if($submission->status_potong_purchase === 'SUDAH POTONG')
            <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
                Klaim ini sudah selesai dipotong pada {{ $submission->tgl_potong_tf ?: 'tanggal sebelumnya' }}.
            </div>
        @elseif($submission->status_potong_ar === 'DEALER SETUJU (PROSES AR)' && $role !== 'ar')
            <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
                Pengajuan ini sudah disetujui sebelumnya dan telah diteruskan ke Tim AR.
            </div>
        @endif

        <!-- Details List (Consistent text-sm) -->
        <div class="border-t border-b border-gray-100 py-3 my-4 space-y-2.5 text-sm">
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-500 shrink-0">Nama Dealer</span>
                <span class="font-medium text-gray-900 text-right">{{ $submission->dealer_name ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">ID Real</span>
                <span class="text-gray-900">{{ $submission->id_real ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-500 shrink-0">Nama Program</span>
                <span class="text-gray-900 text-right">{{ $submission->program_name ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Nominal Potongan</span>
                <span class="font-semibold text-gray-900">Rp {{ number_format($submission->net_pay ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Status Purchase</span>
                <span class="text-gray-900">{{ $submission->status_potong_purchase ?: 'BISA DI POTONG' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Region / Sales</span>
                <span class="text-gray-900 text-right">{{ $submission->region ?: '-' }} / {{ $submission->sales_name ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Status Dokumen</span>
                <span class="text-gray-900">{{ $submission->cek_dokumen ?: 'LENGKAP' }}</span>
            </div>
        </div>

        <!-- Buttons Form -->
        <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-2 pt-1">
            <input type="hidden" name="role" value="{{ $role }}">

            @if($role === 'ar')
                <button type="submit" name="action" value="potong"
                        class="w-full py-2.5 px-4 bg-gray-900 hover:bg-gray-800 text-white rounded-lg text-sm font-medium transition-colors cursor-pointer text-center">
                    Iya, Sudah Dipotong (Selesai)
                </button>
                <button type="submit" name="action" value="tunda"
                        class="w-full py-2.5 px-4 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium border border-gray-300 transition-colors cursor-pointer text-center">
                    Tunda Pemotongan
                </button>
            @else
                <button type="submit" name="action" value="setuju"
                        class="w-full py-2.5 px-4 bg-gray-900 hover:bg-gray-800 text-white rounded-lg text-sm font-medium transition-colors cursor-pointer text-center">
                    Iya, Dealer Setuju (Ajukan ke AR)
                </button>
                <button type="submit" name="action" value="tunda"
                        class="w-full py-2.5 px-4 bg-white hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium border border-gray-300 transition-colors cursor-pointer text-center">
                    Tidak / Tunda (Dealer Belum Order)
                </button>
            @endif
        </form>

        <p class="text-xs text-gray-400 text-center mt-4">
            Tautan konfirmasi berlaku 7 hari.
        </p>
    </div>
</body>
</html>
