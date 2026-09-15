<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>INVOICE - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 30px 35px 25px 35px;
            size: a4 portrait;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            color: #000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .header-dealer {
            margin-bottom: 4px;
        }
        .dealer-title {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        .header-divider {
            border: 0;
            border-top: 1.5px solid #000;
            margin: 6px 0 16px 0;
        }
        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 0;
        }
        .info-right-table {
            border-collapse: collapse;
            float: right;
        }
        .info-right-table td {
            padding: 2px 0;
            font-size: 10.5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: 10px;
        }
        .items-table th {
            border: 1px solid #000;
            padding: 5px 6px;
            font-weight: bold;
            font-size: 10.5px;
            background: #fff;
        }
        .items-table td {
            border: 1px solid #000;
            padding: 8px 6px;
            font-size: 10.5px;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .ket-block {
            margin-top: 6px;
            margin-bottom: 6px;
            font-size: 10.5px;
        }
        .summary-table {
            width: 310px;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 6px;
        }
        .summary-table td {
            padding: 2px 4px;
            font-size: 10.5px;
        }
        .sum-label {
            text-align: left;
            width: 60%;
        }
        .sum-val {
            text-align: right;
            width: 40%;
        }
        .sum-bold td {
            font-weight: bold;
        }
        .terbilang-bar {
            font-style: italic;
            font-size: 10px;
            margin: 10px 0 12px 0;
            clear: both;
        }
        .terbilang-label {
            font-weight: bold;
            font-style: italic;
        }
        .note-box {
            font-size: 9.5px;
            line-height: 1.5;
            margin-top: 8px;
            width: 68%;
            float: left;
        }
        .signature-container {
            float: right;
            width: 200px;
            text-align: center;
            margin-top: 16px;
        }
        .sign-title {
            font-weight: bold;
            font-size: 10.5px;
        }
        .sign-space {
            height: 60px;
        }
        .sign-name {
            font-size: 10px;
        }
        .clearfix {
            clear: both;
        }
    </style>
</head>
<body>

    <!-- Header Dealer -->
    <div class="header-dealer">
        <div class="dealer-title">{{ $invoice->dealer_name ?? '-' }} ({{ $invoice->dealer_code ?? '-' }})</div>
        <div>{{ $invoice->address ?? '-' }}</div>
        <div>NPWP: {{ $invoice->npwp ?? '-' }}</div>
    </div>

    <hr class="header-divider">

    <!-- Title -->
    <div class="doc-title">INVOICE</div>

    <!-- Metadata Section -->
    <table class="meta-table">
        <tr>
            <td style="width: 55%;">
                <div>Bill to:</div>
                <div style="font-weight: bold; margin-top: 3px;">{{ $invoice->customer_name ?? '-' }}</div>
                <div style="margin-top: 2px;">{!! nl2br(e($invoice->customer_address ?: ($invoice->draft?->address ?: ''))) !!}</div>
                <div>{{ $invoice->customer_npwp ?: '' }}</div>
            </td>
            <td style="width: 45%;">
                <table class="info-right-table">
                    <tr>
                        <td style="text-align: left; padding-right: 4px; white-space: nowrap;">Nomor Invoice</td>
                        <td style="text-align: center; width: 14px; white-space: nowrap;">:</td>
                        <td style="text-align: left; padding-left: 4px; min-width: 95px; white-space: nowrap;">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding-right: 4px; white-space: nowrap;">Tanggal Invoice</td>
                        <td style="text-align: center; width: 14px; white-space: nowrap;">:</td>
                        <td style="text-align: left; padding-left: 4px; white-space: nowrap;">{{ $invoice->invoice_date ?? date('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding-right: 4px; white-space: nowrap;">Nama DSA</td>
                        <td style="text-align: center; width: 14px; white-space: nowrap;">:</td>
                        <td style="text-align: left; padding-left: 4px; white-space: nowrap;">{{ $invoice->rsm ?? $invoice->draft?->rsm ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 18%; text-align: center;">No Kode</th>
                <th style="width: 58%; text-align: center;">Deskripsi</th>
                <th style="width: 24%; text-align: center;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1. {{ $invoice->item_code ?? '-' }}</td>
                <td>{{ $invoice->item_name ?? '-' }}</td>
                <td class="text-right">Rp {{ number_format($invoice->dpp, 0, '.', ',') }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Ket & Periode (outside table) -->
    <div class="ket-block">
        <div><strong>Ket :</strong> {{ $invoice->program_name ?? '-' }}</div>
        <div>{{ \Illuminate\Support\Str::startsWith($invoice->program_period ?? '', 'Periode') ? $invoice->program_period : 'Periode ' . ($invoice->program_period ?? '-') }}</div>
    </div>

    <!-- Summary Breakdown -->
    <table class="summary-table">
        <tr>
            <td class="sum-label">DPP :</td>
            <td class="sum-val">Rp {{ number_format($invoice->dpp, 0, '.', ',') }}</td>
        </tr>
        <tr>
            <td class="sum-label">DPP LAIN :</td>
            <td class="sum-val">Rp {{ number_format($invoice->dpp_lain, 0, '.', ',') }}</td>
        </tr>
        <tr>
            <td class="sum-label">PPN :</td>
            <td class="sum-val">Rp {{ number_format($invoice->ppn, 0, '.', ',') }}</td>
        </tr>
        <tr>
            <td class="sum-label"><strong>Jumlah (DPP+PPN) :</strong></td>
            <td class="sum-val"><strong>Rp {{ number_format($invoice->dpp + $invoice->ppn, 0, '.', ',') }}</strong></td>
        </tr>
        <tr>
            <td class="sum-label">PPH :</td>
            <td class="sum-val">Rp {{ number_format($invoice->pph, 0, '.', ',') }}</td>
        </tr>
        <tr>
            <td class="sum-label">Net Pay :</td>
            <td class="sum-val"><strong>Rp {{ number_format($invoice->netpay, 0, '.', ',') }}</strong></td>
        </tr>
    </table>

    <!-- Terbilang -->
    <div class="terbilang-bar">
        <span class="terbilang-label">Terbilang :</span>
        <span>{{ lcfirst($invoice->terbilang) }}</span>
    </div>

    <!-- Notes & Signature -->
    <div style="width: 100%;">
        <div class="note-box">
            <div style="font-weight: bold; margin-bottom: 3px;">NOTE :</div>
            <div>Mohon segera ttd dan stampel maksimal 30 hari sejak tanggal terbit.</div>
            <div>Untuk Dealer PKP, mohon bantu terbitkan Faktur Pajak sesuai tanggal berjalan apabila sudah lewat dari tanggal 5</div>
        </div>

        <div class="signature-container">
            <div class="sign-title">HORMAT KAMI,</div>
            <div class="sign-space"></div>
            <div class="sign-name">( <span style="text-decoration: underline;">Nama Jelas</span> )</div>
        </div>
        <div class="clearfix"></div>
    </div>

</body>
</html>
