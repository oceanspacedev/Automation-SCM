<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Selesai - SCM Automation</title>
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
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                Konfirmasi Sukses
            </span>
        </div>

        <div class="mb-4">
            @if($action === 'setuju')
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h1 class="text-base font-bold text-gray-900">Dealer Setuju Dipotong!</h1>
                <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                    Pengajuan berhasil dicatat di sistem dan <strong>otomatis diteruskan ke WhatsApp Tim AR</strong> untuk proses pemotongan piutang pada invoice order. Status di Google Spreadsheet juga telah diperbarui.
                </p>

                @if(isset($arNotification))
                    @if($arNotification['success'] ?? false)
                        <div class="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
                            <p class="font-semibold text-emerald-900 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                WhatsApp Terkirim ke Tim AR
                            </p>
                            <p class="text-xs text-emerald-700 mt-0.5">{{ $arNotification['message'] ?? 'Pesan notifikasi telah masuk ke nomor WhatsApp Tim AR.' }}</p>
                        </div>
                    @else
                        <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                            <p class="font-semibold text-amber-900">Perhatian Pengiriman WhatsApp ke AR</p>
                            <p class="text-xs text-amber-700 mt-0.5">{{ $arNotification['message'] ?? 'Status di sistem tetap tersimpan dan diteruskan.' }}</p>
                        </div>
                    @endif
                @endif
            @elseif($action === 'potong')
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h1 class="text-base font-bold text-gray-900">Pemotongan Selesai Diproses!</h1>
                <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                    Status klaim program dealer telah berhasil diselesaikan menjadi <strong>SUDAH POTONG</strong>. Data tanggal pemotongan dan status telah diperbarui di database dan Google Spreadsheet.
                </p>
            @else
                <div class="w-10 h-10 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1 class="text-base font-bold text-gray-900">Klaim Ditunda</h1>
                <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                    Status dicatat sebagai pending. Anda dapat menawarkannya kembali pada pesanan dealer berikutnya.
                </p>
            @endif
        </div>

        <!-- Details List -->
        <div class="border-t border-b border-gray-100 py-3 my-4 space-y-2.5 text-sm">
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-500 shrink-0">Nama Dealer</span>
                <span class="font-medium text-gray-900 text-right">{{ $dp->dealer_name ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Kode BT / ID Real</span>
                <span class="font-mono text-gray-900">{{ $dp->kode_bt ?: '-' }}</span>
            </div>
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-500 shrink-0">Nama Program</span>
                <span class="text-gray-900 text-right">{{ $dp->program_name ?: ($dp->program ?: '-') }}</span>
            </div>
            @if(!empty($dp->net_pay))
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Nominal Potongan</span>
                <span class="font-bold text-emerald-700">Rp {{ number_format((float)$dp->net_pay, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Status Saat Ini</span>
                <span class="font-semibold text-gray-900">
                    @if($action === 'setuju')
                        <span class="text-blue-700">STATUS AR: DEALER SETUJU (PROSES AR)</span>
                    @elseif($action === 'potong')
                        <span class="text-emerald-700">STATUS PURCHASE: SUDAH POTONG</span>
                    @else
                        <span class="text-gray-600">STATUS AR: PENDING DEALER</span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between items-center gap-4">
                <span class="text-gray-500 shrink-0">Waktu Konfirmasi</span>
                <span class="text-gray-900">{{ date('d/m/Y H:i:s') }} WIB</span>
            </div>
        </div>

        <p class="text-xs text-gray-400 text-center mt-5">
            Anda dapat menutup jendela ini sekarang. Data telah tersimpan otomatis dan disinkronkan ke Google Spreadsheet.
        </p>
    </div>
</body>
</html>
