@extends('data_programs.layout')

@section('title', 'Konfirmasi Selesai')
@section('tag', 'Konfirmasi sukses')
@section('tag_class', 'text-emerald-700')
@section('footer', 'Halaman ini boleh ditutup. Data sudah tersimpan dan tersinkron ke Google Spreadsheet.')

@php
    $rupiah = fn ($amount) => 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');

    [$title, $description, $status] = match ($action) {
        'setuju' => ['Dealer setuju dipotong', 'Pengajuan dicatat dan otomatis diteruskan ke WhatsApp Tim AR untuk proses pemotongan piutang.', ['label' => 'DEALER SETUJU (PROSES AR)', 'tone' => 'blue']],
        'setuju_transfer' => ['Dealer setuju ditransfer', 'Pengajuan dicatat dan otomatis diteruskan ke WhatsApp Tim SCM untuk proses transfer.', ['label' => \App\Models\DataProgram::TRANSFER_APPROVED, 'tone' => 'blue']],
        'potong' => ['Pemotongan selesai diproses', 'Status klaim menjadi SUDAH POTONG, otomatis diinfokan ke Tim SCM dan diteruskan ke Tim Pajak untuk cek PPh.', ['label' => 'SUDAH POTONG', 'tone' => 'emerald']],
        'transfer' => ['Transfer selesai dicatat', 'Status klaim menjadi DONE TRANSFER, lalu diteruskan ke Tim Pajak untuk cek PPh.', ['label' => \App\Models\DataProgram::DONE_TRANSFER, 'tone' => 'emerald']],
        'cek_pajak' => ['Cek pajak tersimpan', 'Cek Pajak, Selisih, dan Note PPh tersimpan, lalu hasilnya diteruskan ke Tim AP.', ['label' => 'SUDAH DICEK PAJAK', 'tone' => 'emerald']],
        'kembalikan_ke_ar' => ['Klaim dikembalikan ke Tim AR', 'Status pemotongan telah dibatalkan dan pengajuan dikembalikan ke antrean Tim AR untuk ditinjau ulang.', ['label' => 'DIKEMBALIKAN KE AR', 'tone' => 'amber']],
        default => ['Klaim ditunda', 'Status dicatat sebagai pending. Tawarkan kembali pada pesanan dealer berikutnya.', ['label' => 'PENDING DEALER', 'tone' => 'gray']],
    };

    // Daftar notifikasi WA lanjutan yang ditampilkan setelah konfirmasi
    $notifications = match ($action) {
        'setuju' => [
            [$arNotification ?? null, 'WhatsApp terkirim ke Tim AR', 'WhatsApp ke Tim AR gagal terkirim', 'Status di sistem tetap tersimpan.'],
        ],
        'setuju_transfer' => [
            [$scmTransferNotification ?? null, 'WhatsApp terkirim ke Tim SCM', 'WhatsApp ke Tim SCM gagal terkirim', 'Status di sistem tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
        ],
        'potong' => [
            [$scmCutNotification ?? null, 'Info potong terkirim ke WhatsApp Tim SCM', 'Info potong ke Tim SCM gagal terkirim', 'Status potong tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
            [$taxNotification ?? null, 'WhatsApp terkirim ke Tim Pajak', 'WhatsApp ke Tim Pajak gagal terkirim', 'Status potong tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
        ],
        'transfer' => [
            [$taxNotification ?? null, 'WhatsApp terkirim ke Tim Pajak', 'WhatsApp ke Tim Pajak gagal terkirim', 'Status transfer tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
        ],
        'cek_pajak' => [
            [$apNotification ?? null, 'Hasil terkirim ke WhatsApp Tim AP', 'WhatsApp ke Tim AP gagal terkirim', 'Cek pajak tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
        ],
        default => [],
    };

    $isTransfer = in_array($action, ['setuju_transfer', 'transfer'], true) || $dp->isTransfer();
    $rows = [['label' => 'Program', 'value' => $dp->program_name ?: $dp->program, 'sub' => preg_replace('/^periode\s+/i', '', (string) $dp->periode), 'wide' => true]];
    if ($action === 'cek_pajak') {
        $rows[] = ['label' => 'Cek Pajak', 'value' => $dp->cek_pajak_tarif];
        $rows[] = ['label' => 'Selisih', 'value' => $rupiah($dp->selisih)];
        $rows[] = ['label' => 'Note PPh', 'value' => $dp->note_pph, 'wide' => true];
    }
    if ($action === 'kembalikan_ke_ar') {
        $rows[] = ['label' => 'Alasan Pengembalian', 'value' => $alasan ?? '-', 'wide' => true];
    }
    $rows[] = ['label' => $isTransfer ? 'Nominal Transfer' : 'Nominal Potongan', 'value' => $rupiah($dp->net_pay), 'strong' => true];
    if ($action === 'setuju_transfer') {
        $rows[] = ['label' => 'Rekening', 'value' => trim("{$dp->bank} {$dp->norek}"), 'sub' => $dp->namrek];
    }
    $rows[] = match ($action) {
        'potong' => ['label' => 'No. Pembayaran', 'value' => $dp->no_pembayaran],
        'transfer' => ['label' => 'No. Referensi', 'value' => $dp->no_pembayaran],
        default => ['label' => 'Waktu (WIB)', 'value' => date('d/m/Y H:i')],
    };
    if (in_array($action, ['potong', 'transfer'], true)) {
        $rows[] = ['label' => 'Waktu (WIB)', 'value' => date('d/m/Y H:i'), 'wide' => true];
    }
@endphp

@section('content')
    <div>
        <h1 class="text-base font-medium leading-6 text-gray-950">{{ $title }}</h1>
        <p class="mt-1 text-sm leading-5 text-gray-500">{{ $description }}</p>
    </div>

    @foreach($notifications as [$notification, $sentTitle, $failedTitle, $failedNote])
        @if($notification)
            @if($notification['success'] ?? false)
                <div class="rounded-[10px] px-4 py-3 bg-emerald-50 text-xs leading-5">
                    <p class="font-medium text-emerald-900">{{ $sentTitle }}</p>
                    <p class="mt-1 text-emerald-800">{{ $notification['message'] ?? '' }}</p>
                </div>
            @else
                <div class="rounded-[10px] px-4 py-3 bg-amber-50 text-xs leading-5">
                    <p class="font-medium text-amber-900">{{ $failedTitle }}</p>
                    <p class="mt-1 text-amber-800">{{ $notification['message'] ?? '' }} {{ $failedNote }}</p>
                </div>
            @endif
        @endif
    @endforeach

    @include('data_programs.partials.claim', ['status' => $status, 'rows' => $rows])
@endsection
