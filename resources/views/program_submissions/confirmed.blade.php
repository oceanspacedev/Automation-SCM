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
    <div class="max-w-md w-full bg-white rounded-xl border border-gray-200 shadow-xs p-6">
        
        <!-- Header -->
        <div class="mb-5 pb-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-400">SCM Automation</span>
            <span class="text-xs text-gray-500">Konfirmasi Selesai</span>
        </div>

        <div class="mb-4">
            @if($action === 'setuju')
                <h1 class="text-base font-semibold text-gray-900">Dealer Setuju Dipotong!</h1>
                <p class="text-sm text-gray-500 mt-1">Pengajuan berhasil dicatat di sistem dan diteruskan ke WhatsApp Tim AR untuk proses pemotongan piutang pada invoice order.</p>

                @if(isset($arNotification))
                    @if($arNotification['success'] ?? false)
                        <div class="mt-3 p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
                            <p class="font-medium text-gray-900">WhatsApp Berhasil Terkirim ke AR</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $arNotification['message'] ?? 'Pesan notifikasi telah masuk ke nomor WhatsApp Tim AR.' }}</p>
                        </div>
                    @else
                        <div class="mt-3 p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
                            <p class="font-medium text-gray-900">Perhatian Pengiriman WhatsApp ke AR</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $arNotification['message'] ?? 'Koneksi ke gateway WhatsApp sedang sibuk. Status di database tetap tersimpan.' }}</p>
                        </div>
                    @endif
                @endif
            @elseif($action === 'potong')
                <h1 class="text-base font-semibold text-gray-900">Konfirmasi Berhasil!</h1>
                <p class="text-sm text-gray-500 mt-1">Status klaim program dealer telah berhasil diperbarui di sistem SCM.</p>
            @else
                <h1 class="text-base font-semibold text-gray-900">Klaim Ditunda</h1>
                <p class="text-sm text-gray-500 mt-1">Status dicatat sebagai pending. Anda dapat menawarkannya kembali pada pesanan dealer berikutnya.</p>
            @endif
        </div>

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
            @if(!empty($submission->net_pay))
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Nominal Potongan</span>
                <span class="font-semibold text-gray-900">Rp {{ number_format($submission->net_pay, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Status Saat Ini</span>
                <span class="font-medium text-gray-900">
                    @if($action === 'setuju')
                        STATUS AR: DEALER SETUJU (PROSES AR)
                    @elseif($action === 'potong')
                        STATUS: SUDAH POTONG
                    @else
                        STATUS AR: PENDING DEALER
                    @endif
                </span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Region</span>
                <span class="text-gray-900">{{ $submission->region ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Waktu Konfirmasi</span>
                <span class="text-gray-900">{{ date('d/m/Y H:i:s') }} WIB</span>
            </div>
        </div>

        <p class="text-xs text-gray-400 text-center mt-5">
            Anda dapat menutup jendela ini sekarang. Data telah tersimpan otomatis di sistem SCM.
        </p>
    </div>
</body>
</html>
