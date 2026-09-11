<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            color: #1f2937;
            background: #ffffff;
            margin: 0;
            padding: 24px;
        }
        .content {
            max-width: 560px;
        }
        p {
            margin: 0 0 12px 0;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        table td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            font-size: 13px;
            vertical-align: top;
        }
        table td:first-child {
            color: #555;
            width: 40%;
        }
        .total-row td {
            font-weight: bold;
        }
        hr {
            border: none;
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #666;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="content">
        <p>Kepada Yth.<br>{{ $invoice->customer_name ?? $invoice->dealer_name }},</p>

        <p>
            Bersama ini kami sampaikan invoice berikut. Mohon periksa detail di bawah dan PDF yang terlampir.
        </p>

        <table>
            <tr><td>Nomor Invoice</td><td>{{ $invoice->invoice_number }}</td></tr>
            <tr><td>Tanggal Invoice</td><td>{{ $invoice->invoice_date ?? '-' }}</td></tr>
            <tr><td>Dealer</td><td>{{ $invoice->dealer_name }} ({{ $invoice->dealer_code }})</td></tr>
            <tr><td>Customer</td><td>{{ $invoice->customer_name ?? '-' }}</td></tr>
            <tr><td>Program</td><td>{{ $invoice->program_name ?? '-' }}</td></tr>
            <tr><td>Periode</td><td>{{ $invoice->program_period ?? '-' }}</td></tr>
            <tr><td>DPP</td><td>Rp {{ number_format($invoice->dpp, 0, '.', ',') }}</td></tr>
            <tr><td>DPP Lain</td><td>{{ $invoice->dpp_lain > 0 ? 'Rp ' . number_format($invoice->dpp_lain, 0, '.', ',') : '-' }}</td></tr>
            <tr><td>PPN (12%)</td><td>{{ $invoice->ppn > 0 ? 'Rp ' . number_format($invoice->ppn, 0, '.', ',') : '-' }}</td></tr>
            <tr><td>PPh</td><td>{{ $invoice->pph > 0 ? '(Rp ' . number_format($invoice->pph, 0, '.', ',') . ')' : '-' }}</td></tr>
            <tr class="total-row"><td>Net Pay</td><td>Rp {{ number_format($invoice->netpay, 0, '.', ',') }}</td></tr>
        </table>

        <p>
            Mohon segera tanda tangan dan cap maksimal 30 hari sejak tanggal terbit.<br>
            Untuk Dealer PKP, mohon bantu terbitkan Faktur Pajak sesuai tanggal berjalan apabila sudah lewat dari tanggal 5.
        </p>

        <p>Terima kasih.</p>

        <hr>

        <div class="footer">
            Ocean Space<br>
            Email ini dikirim otomatis oleh sistem SCM Invoice.
        </div>
    </div>
</body>
</html>
