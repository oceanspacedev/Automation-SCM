<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Selesai - SCM Automation</title>
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

        <!-- Header -->
        <div class="pb-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">SCM Automation</span>
            <span class="text-xs font-medium px-2.5 py-0.5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700">
                Konfirmasi Sukses
            </span>
        </div>

        <div class="py-3.5">
            @if($action === 'setuju')
                <h1 class="text-sm font-semibold text-gray-950">Dealer Setuju Dipotong</h1>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    Pengajuan berhasil dicatat dan otomatis diteruskan ke WhatsApp Tim AR untuk proses pemotongan piutang. Status di Google Spreadsheet juga telah diperbarui.
                </p>

                @if(isset($arNotification))
                    @if($arNotification['success'] ?? false)
                        <div class="mt-3 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-lg text-xs">
                            <p class="font-medium text-emerald-900">WhatsApp terkirim ke Tim AR</p>
                            <p class="text-emerald-700 mt-0.5">{{ $arNotification['message'] ?? 'Pesan notifikasi telah masuk ke nomor WhatsApp Tim AR.' }}</p>
                        </div>
                    @else
                        <div class="mt-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs">
                            <p class="font-medium text-amber-900">WhatsApp ke Tim AR gagal terkirim</p>
                            <p class="text-amber-800 mt-0.5">{{ $arNotification['message'] ?? 'Status di sistem tetap tersimpan dan diteruskan.' }}</p>
                        </div>
                    @endif
                @endif
            @elseif($action === 'potong')
                <h1 class="text-sm font-semibold text-gray-950">Pemotongan Selesai Diproses</h1>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    Status klaim telah diperbarui menjadi SUDAH POTONG di database dan Google Spreadsheet.
                </p>
            @else
                <h1 class="text-sm font-semibold text-gray-950">Klaim Ditunda</h1>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    Status dicatat sebagai pending. Anda dapat menawarkannya kembali pada pesanan dealer berikutnya.
                </p>
            @endif
        </div>

        <!-- Details List (Persis sama dengan invoice) -->
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
                    @if(!empty($dp->net_pay))
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Nominal Potongan</td>
                        <td class="py-1 font-semibold text-gray-950 text-right">Rp {{ number_format((float) $dp->net_pay, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($action === 'potong' && !empty($dp->no_pembayaran))
                    <tr>
                        <td class="py-1 text-gray-500 align-top">No. Pembayaran</td>
                        <td class="py-1 font-medium text-gray-900 text-right">{{ $dp->no_pembayaran }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Status</td>
                        <td class="py-1 text-right">
                            @if($action === 'setuju')
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200">DEALER SETUJU (PROSES AR)</span>
                            @elseif($action === 'potong')
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">SUDAH POTONG</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-gray-50 text-gray-700 border border-gray-200">PENDING DEALER</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="py-1 text-gray-500 align-top">Waktu Konfirmasi</td>
                        <td class="py-1 text-gray-800 text-right">{{ date('d/m/Y H:i') }} WIB</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-[11px] text-gray-400 text-center mt-4">
            Anda dapat menutup halaman ini. Data telah tersimpan dan disinkronkan ke Google Spreadsheet.
        </p>
    </div>
</body>
</html>
