<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Klaim Program - SCM Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6 sm:p-8 text-center">
        @if($action === 'setuju')
            <!-- Telemarketing Success Icon -->
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-blue-100 ring-8 ring-blue-50/50">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-1">Dealer Setuju Dipotong!</h1>
            <p class="text-xs text-gray-500 mb-4">Pengajuan berhasil dicatat di sistem dan diteruskan ke WhatsApp Tim AR untuk proses pemotongan piutang pada invoice order.</p>

            @if(isset($arNotification))
                @if($arNotification['success'] ?? false)
                    <div class="mb-5 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-left text-xs text-emerald-800 flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <p class="font-semibold">WhatsApp Berhasil Terkirim ke AR</p>
                            <p class="text-[11px] text-emerald-700 mt-0.5">{{ $arNotification['message'] ?? 'Pesan notifikasi telah masuk ke nomor WhatsApp Tim AR.' }}</p>
                        </div>
                    </div>
                @else
                    <div class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-xl text-left text-xs text-amber-800 flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="font-semibold">Perhatian Pengiriman WhatsApp ke AR</p>
                            <p class="text-[11px] text-amber-700 mt-0.5">{{ $arNotification['message'] ?? 'Koneksi ke gateway WhatsApp sedang sibuk. Status di database tetap tersimpan.' }}</p>
                        </div>
                    </div>
                @endif
            @endif

            <!-- Status Pill -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                <span>STATUS AR: DEALER SETUJU (PROSES AR)</span>
            </div>
        @elseif($action === 'potong')
            <!-- AR Potong Success Icon -->
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-100 ring-8 ring-emerald-50/50">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-1">Konfirmasi Berhasil!</h1>
            <p class="text-xs text-gray-500 mb-6">Status klaim program dealer telah berhasil diperbarui di sistem SCM.</p>

            <!-- Status Pill -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>STATUS: SUDAH POTONG</span>
            </div>
        @else
            <!-- Tunda / Pending Icon -->
            <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-amber-100 ring-8 ring-amber-50/50">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-1">Klaim Ditunda</h1>
            <p class="text-xs text-gray-500 mb-6">Status dicatat sebagai pending. Anda dapat menawarkannya kembali pada pesanan dealer berikutnya.</p>

            <!-- Status Pill -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>STATUS AR: PENDING DEALER</span>
            </div>
        @endif

        <!-- Card Detail -->
        <div class="bg-gray-50/80 rounded-xl border border-gray-100 p-4 text-left text-xs space-y-2.5 mb-6">
            <div class="flex justify-between items-start pb-2 border-b border-gray-200/60">
                <span class="text-gray-500">Nama Dealer:</span>
                <span class="font-semibold text-gray-900 text-right max-w-[200px] truncate">{{ $submission->dealer_name ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center pb-2 border-b border-gray-200/60">
                <span class="text-gray-500">ID Real:</span>
                <span class="font-mono font-medium text-gray-800">{{ $submission->id_real ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-start pb-2 border-b border-gray-200/60">
                <span class="text-gray-500">Nama Program:</span>
                <span class="font-medium text-gray-800 text-right max-w-[200px] truncate">{{ $submission->program_name ?: '-' }}</span>
            </div>
            @if(!empty($submission->net_pay))
            <div class="flex justify-between items-center pb-2 border-b border-gray-200/60">
                <span class="text-gray-500">Nominal Potongan:</span>
                <span class="font-semibold text-emerald-600">Rp {{ number_format($submission->net_pay, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between items-center pb-2 border-b border-gray-200/60">
                <span class="text-gray-500">Region:</span>
                <span class="text-gray-700">{{ $submission->region ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-500">Waktu Konfirmasi:</span>
                <span class="text-gray-700">{{ date('d/m/Y H:i:s') }} WIB</span>
            </div>
        </div>

        <p class="text-[11px] text-gray-400">
            Anda dapat menutup jendela ini sekarang. Perubahan telah tersinkronisasi otomatis dengan dashboard SCM Invoice.
        </p>
    </div>
</body>
</html>
