@extends('data_programs.layout')

@section('title', 'Konfirmasi Selesai')
@section('tag', 'Konfirmasi Sukses')
@section('tag_class', 'bg-emerald-50 text-emerald-700')
@section('footer', 'Halaman ini boleh ditutup. Data sudah tersimpan dan tersinkron ke Google Spreadsheet.')

@php
    $rupiah = fn ($amount) => 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');

    [$title, $description, $status] = match ($action) {
        'setuju' => ['Dealer Setuju Dipotong', 'Pengajuan dicatat dan otomatis diteruskan ke WhatsApp Tim AR untuk proses pemotongan piutang.', ['label' => 'DEALER SETUJU (PROSES AR)', 'tone' => 'blue']],
        'potong' => ['Pemotongan Selesai Diproses', 'Status klaim menjadi SUDAH POTONG, lalu diteruskan ke Tim Pajak untuk cek PPh.', ['label' => 'SUDAH POTONG', 'tone' => 'emerald']],
        'cek_pajak' => ['Cek Pajak Tersimpan', 'Cek Pajak, Selisih, dan Note PPh tersimpan, lalu hasilnya diteruskan ke Tim SCM.', ['label' => 'SUDAH DICEK PAJAK', 'tone' => 'emerald']],
        default => ['Klaim Ditunda', 'Status dicatat sebagai pending. Tawarkan kembali pada pesanan dealer berikutnya.', ['label' => 'PENDING DEALER', 'tone' => 'gray']],
    };

    // [judul berhasil, judul gagal, keterangan bila gagal] per WhatsApp lanjutan
    [$notification, $sentTitle, $failedTitle, $failedNote] = match ($action) {
        'setuju' => [$arNotification ?? null, 'WhatsApp terkirim ke Tim AR', 'WhatsApp ke Tim AR gagal terkirim', 'Status di sistem tetap tersimpan.'],
        'potong' => [$taxNotification ?? null, 'WhatsApp terkirim ke Tim Pajak', 'WhatsApp ke Tim Pajak gagal terkirim', 'Status potong tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
        'cek_pajak' => [$scmNotification ?? null, 'Hasil terkirim ke WhatsApp Tim SCM', 'WhatsApp ke Tim SCM gagal terkirim', 'Cek pajak tetap tersimpan. Bisa dikirim ulang dari menu Riwayat Approval.'],
        default => [null, '', '', ''],
    };

    $rows = [['label' => 'Program', 'value' => $dp->program_name ?: $dp->program, 'sub' => preg_replace('/^periode\s+/i', '', (string) $dp->periode), 'wide' => true]];
    if ($action === 'cek_pajak') {
        $rows[] = ['label' => 'Cek Pajak', 'value' => $dp->cek_pajak_tarif];
        $rows[] = ['label' => 'Selisih', 'value' => $rupiah($dp->selisih)];
        $rows[] = ['label' => 'Note PPh', 'value' => $dp->note_pph, 'wide' => true];
    }
    $rows[] = ['label' => 'Nominal Potongan', 'value' => $rupiah($dp->net_pay), 'strong' => true];
    $rows[] = $action === 'potong'
        ? ['label' => 'No. Pembayaran', 'value' => $dp->no_pembayaran]
        : ['label' => 'Waktu (WIB)', 'value' => date('d/m/Y H:i')];
    if ($action === 'potong') {
        $rows[] = ['label' => 'Waktu (WIB)', 'value' => date('d/m/Y H:i'), 'wide' => true];
    }
@endphp

@section('content')
    <div>
        <h1 class="text-base font-semibold text-gray-950">{{ $title }}</h1>
        <p class="mt-1 text-sm text-gray-500 leading-relaxed">{{ $description }}</p>
    </div>

    @if($notification)
        @if($notification['success'] ?? false)
            <div class="rounded-lg px-3 py-2.5 bg-emerald-50 text-xs leading-relaxed">
                <p class="font-medium text-emerald-900">{{ $sentTitle }}</p>
                <p class="mt-0.5 text-emerald-800">{{ $notification['message'] ?? '' }}</p>
            </div>
        @else
            <div class="rounded-lg px-3 py-2.5 bg-amber-50 text-xs leading-relaxed">
                <p class="font-medium text-amber-900">{{ $failedTitle }}</p>
                <p class="mt-0.5 text-amber-800">{{ $notification['message'] ?? '' }} {{ $failedNote }}</p>
            </div>
        @endif
    @endif

    @include('data_programs.partials.claim', ['status' => $status, 'rows' => $rows])
@endsection
