<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>INVOICE - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 48px 48px 26px 48px;
            size: a4 portrait;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11.5px;
            color: #000;
            line-height: 1.38;
            margin: 0;
            padding: 0;
        }
        table {
            border-collapse: collapse;
        }
        td,
        th {
            padding: 0;
            vertical-align: top;
        }
        .dealer-title {
            font-weight: bold;
            font-size: 13.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .dealer-line {
            font-size: 12px;
            margin-bottom: 1px;
        }
        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 27px;
            margin: 14px 0 14px 0;
        }
        .meta-table {
            width: 100%;
        }
        .bill-to div {
            margin-bottom: 1px;
        }
        .info-table {
            width: 262px;
            margin-left: auto;
        }
        .info-table td {
            padding: 1px 0 2px 0;
            border-bottom: 1px solid #ddd;
        }
        .items-table {
            width: 100%;
            margin-top: 18px;
            border: 1px solid #444;
        }
        .items-table th {
            border: 1px solid #444;
            border-bottom: 2px solid #444;
            padding: 5px 6px;
            font-weight: bold;
            text-align: center;
        }
        .items-table td {
            border-left: 1px solid #444;
            border-right: 1px solid #444;
            padding: 6px 6px;
            height: 108px;
        }
        .text-right {
            text-align: right;
        }
        .ket-block {
            margin-top: 6px;
            line-height: 1.25;
        }
        .summary-table {
            width: 352px;
            margin: 18px 7px 0 auto;
        }
        .summary-table td {
            padding: 2px 0 4px 0;
        }
        .sum-label {
            font-weight: bold;
        }
        .terbilang {
            font-style: italic;
            margin-top: 18px;
        }
        .transfer {
            margin-top: 34px;
        }
        .transfer-title {
            font-weight: bold;
            margin-bottom: 8px;
        }
        .transfer-table td {
            padding: 1px 0 3px 0;
        }
        .signature {
            margin-top: 32px;
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        .sign-space {
            height: 72px;
        }
        .footer-note {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            font-size: 9px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    @php
        $rupiah = fn ($value) => 'Rp  '.number_format((float) $value, 0, ',', '.');
        $period = $invoice->program_period ?? '-';
    @endphp

    <!-- Catatan di kaki halaman -->
    <div class="footer-note">
        <div style="font-weight: bold;">NOTE :</div>
        <div>Saldo program dapat digunakan maksimal 30 hari sejak tanggal terbit.</div>
    </div>

    <!-- Header Dealer -->
    <div class="dealer-title">{{ $invoice->dealer_name ?? '-' }} ({{ $invoice->dealer_code ?? '-' }})</div>
    <div class="dealer-line">{{ $invoice->address ?? '-' }}</div>
    <div class="dealer-line">NPWP: {{ $invoice->npwp ?? '-' }}</div>

    <div class="doc-title">INVOICE</div>

    <!-- Bill to & nomor invoice -->
    <table class="meta-table">
        <tr>
            <td class="bill-to" style="width: 58%;">
                <div style="font-weight: bold;">Bill to:</div>
                <div style="font-weight: bold;">{{ $invoice->customer_name ?? '-' }}</div>
                <div>{!! nl2br(e($invoice->customer_address ?: ($invoice->draft?->address ?: ''))) !!}</div>
                <div>{{ $invoice->customer_npwp ?: '' }}</div>
            </td>
            <td style="width: 42%;">
                <table class="info-table">
                    <tr>
                        <td style="font-weight: bold;">Nomor Invoice :</td>
                        <td class="text-right">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Tanggal Invoice :</td>
                        <td class="text-right">{{ $invoice->invoice_date ?? date('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Item -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30%;">No Kode</th>
                <th style="width: 45%;">Deskripsi</th>
                <th style="width: 25%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1. {{ $invoice->item_code ?? '-' }}</td>
                <td>{{ $invoice->item_name ?? '-' }}</td>
                <td class="text-right">{{ $rupiah($invoice->dpp) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="ket-block">
        <div><strong>Ket :</strong> {{ $invoice->program_name ?? '-' }}</div>
        <div>{{ \Illuminate\Support\Str::startsWith($period, 'Periode') ? $period : 'Periode '.$period }}</div>
    </div>

    <!-- Ringkasan -->
    <table class="summary-table">
        <tr>
            <td class="sum-label">DPP :</td>
            <td class="text-right">{{ $rupiah($invoice->dpp) }}</td>
        </tr>
        <tr>
            <td class="sum-label">DPP LAIN :</td>
            <td class="text-right">{{ $rupiah($invoice->dpp_lain) }}</td>
        </tr>
        <tr>
            <td class="sum-label">PPN 12% :</td>
            <td class="text-right">{{ $rupiah($invoice->ppn) }}</td>
        </tr>
        <tr>
            <td class="sum-label">Jumlah (DPP+PPN 12%) :</td>
            <td class="text-right">{{ $rupiah($invoice->dpp + $invoice->ppn) }}</td>
        </tr>
        <tr>
            <td class="sum-label">PPH :</td>
            <td class="text-right">{{ $rupiah($invoice->pph) }}</td>
        </tr>
        <tr>
            <td class="sum-label">Net Pay :</td>
            <td class="text-right" style="font-weight: bold;">{{ $rupiah($invoice->netpay) }}</td>
        </tr>
    </table>

    <div class="terbilang">
        <strong>Terbilang :</strong> {{ \Illuminate\Support\Str::lower($invoice->terbilang) }}
    </div>

    <!-- Rekening tujuan transfer (diisi dealer) -->
    <div class="transfer">
        <div class="transfer-title">Mohon lakukan transfer ke rekening bank berikut:</div>
        <table class="transfer-table">
            <tr>
                <td style="width: 160px;">Nama Pemilik Rekening</td>
                <td>:</td>
            </tr>
            <tr>
                <td>Nama Bank</td>
                <td>:</td>
            </tr>
            <tr>
                <td>Nomor Rekening</td>
                <td>:</td>
            </tr>
        </table>
    </div>

    <div class="signature">
        <div>HORMAT KAMI,</div>
        <div class="sign-space"></div>
        <div>{{ $invoice->dealer_name ?? '-' }}</div>
    </div>

</body>
</html>
