<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $payload['invoice_type'] === 'credit_note' ? 'CREDIT NOTE' : ($payload['invoice_type'] === 'debit_note' ? 'DEBIT NOTE' : 'SALES INVOICE') }} {{ $payload['serial']['formatted'] }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        body { font-family: 'DejaVu Sans', 'Helvetica', sans-serif; font-size: 11pt; color: #111; margin: 0; }
        h1, h2, h3 { margin: 0 0 6pt 0; }
        .doc-title { font-size: 18pt; letter-spacing: 1pt; text-align: center; margin-bottom: 12pt; border-bottom: 2pt solid #111; padding-bottom: 6pt; }
        .header { display: flex; justify-content: space-between; gap: 24pt; margin-bottom: 12pt; }
        .header .seller { width: 60%; }
        .header .meta { width: 38%; text-align: right; font-size: 10pt; }
        .header .meta dl { margin: 0; }
        .header .meta dt { font-weight: bold; display: inline; }
        .header .meta dd { display: inline; margin: 0 0 0 4pt; }
        .header .meta .row { margin-bottom: 2pt; }
        .buyer { border: 1pt solid #ccc; padding: 8pt; margin-bottom: 12pt; }
        .buyer h3 { font-size: 10pt; text-transform: uppercase; color: #666; margin-bottom: 4pt; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
        table.lines th, table.lines td { border-bottom: 1pt solid #ccc; padding: 4pt 6pt; font-size: 10pt; vertical-align: top; }
        table.lines th { background: #f3f3f3; text-align: left; }
        table.lines td.num, table.lines th.num { text-align: right; white-space: nowrap; }
        .totals { width: 38%; margin-left: auto; }
        .totals tr td { padding: 2pt 6pt; font-size: 10pt; }
        .totals tr td.num { text-align: right; white-space: nowrap; }
        .totals tr.grand td { font-weight: bold; border-top: 1pt solid #111; font-size: 12pt; }
        .stamp { display: inline-block; padding: 4pt 12pt; border: 2pt solid #b00; color: #b00; font-weight: bold; letter-spacing: 1pt; transform: rotate(-6deg); margin: 8pt 0; }
        .stamp.exempt { border-color: #555; color: #555; }
        .footer { margin-top: 24pt; font-size: 9pt; color: #666; border-top: 1pt solid #ccc; padding-top: 8pt; }
        .void-watermark { position: fixed; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-25deg); font-size: 96pt; color: rgba(180,0,0,0.15); pointer-events: none; font-weight: bold; letter-spacing: 8pt; }
        .qr-stub { float: right; width: 90pt; height: 90pt; border: 1pt solid #ccc; text-align: center; font-size: 7pt; padding-top: 38pt; color: #888; box-sizing: border-box; }
    </style>
</head>
<body>
    @if($invoice->isVoided())
        <div class="void-watermark">VOIDED</div>
    @endif

    <div class="doc-title">
        @php($t = $payload['invoice_type'])
        {{ $t === 'credit_note' ? 'CREDIT NOTE' : ($t === 'debit_note' ? 'DEBIT NOTE' : ($t === 'official_receipt' ? 'OFFICIAL RECEIPT' : 'SALES INVOICE')) }}
    </div>

    <div class="header">
        <div class="seller">
            <h2>{{ $payload['seller']['registered_name'] }}</h2>
            @if($payload['seller']['business_style'])
                <div>Business Style: {{ $payload['seller']['business_style'] }}</div>
            @endif
            <div>{{ $payload['seller']['address'] }}</div>
            <div><strong>TIN:</strong> {{ $payload['seller']['tin'] }}-{{ $payload['seller']['branch_code'] }}</div>
            <div><strong>VAT Status:</strong> {{ str_replace('_', ' ', $payload['seller']['vat_status']) }}</div>
            @if(!empty($payload['seller']['accreditation_number']))
                <div><strong>Accreditation No.:</strong> {{ $payload['seller']['accreditation_number'] }}</div>
            @endif
            @if(!empty($payload['seller']['machine_identification_number']))
                <div><strong>MIN:</strong> {{ $payload['seller']['machine_identification_number'] }}</div>
            @endif
            @if(!empty($payload['seller']['software_license_number']))
                <div><strong>SLN:</strong> {{ $payload['seller']['software_license_number'] }}</div>
            @endif
        </div>
        <div class="meta">
            <div class="qr-stub">QR<br>{{ substr($payload['eis_unique_id'], 0, 12) }}</div>
            <div class="row"><strong>Serial No.:</strong> {{ $payload['serial']['formatted'] }}</div>
            <div class="row"><strong>EIS Unique ID:</strong><br>{{ $payload['eis_unique_id'] }}</div>
            <div class="row"><strong>Issued:</strong> {{ $payload['issued_at'] }}</div>
            <div class="row"><strong>Branch:</strong> {{ $payload['branch']['code'] }} — {{ $payload['branch']['name'] }}</div>
            @if(!empty($payload['supersedes']))
                <div class="row"><strong>Supersedes:</strong> {{ $payload['supersedes']['serial'] }}</div>
            @endif
        </div>
    </div>

    @if(!empty($payload['buyer']))
        <div class="buyer">
            <h3>Sold To</h3>
            <div><strong>{{ $payload['buyer']['registered_name'] ?? '—' }}</strong></div>
            @if(!empty($payload['buyer']['business_style']))
                <div>Business Style: {{ $payload['buyer']['business_style'] }}</div>
            @endif
            @if(!empty($payload['buyer']['tin']))
                <div>TIN: {{ $payload['buyer']['tin'] }}</div>
            @endif
            @if(!empty($payload['buyer']['address']))
                <div>{{ $payload['buyer']['address'] }}</div>
            @endif
        </div>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="num">Qty</th>
                <th>Unit</th>
                <th class="num">Unit Price</th>
                <th>VAT</th>
                <th class="num">Net</th>
                <th class="num">VAT</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payload['lines'] as $line)
                <tr>
                    <td>{{ $line['line_number'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ rtrim(rtrim($line['quantity'], '0'), '.') ?: '0' }}</td>
                    <td>{{ $line['unit'] }}</td>
                    <td class="num">{{ $line['unit_price'] }}</td>
                    <td>
                        @if($line['classification'] === 'vatable') VATable
                        @elseif($line['classification'] === 'zero_rated') Zero-Rated
                        @else VAT-Exempt
                        @endif
                    </td>
                    <td class="num">{{ $line['line_net'] }}</td>
                    <td class="num">{{ $line['line_vat'] }}</td>
                    <td class="num">{{ $line['line_total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($payload['totals']['has_zero_rated_sale'])
        <div class="stamp">ZERO-RATED SALE</div>
    @endif

    @if((float) $payload['totals']['vat_exempt_sales'] > 0)
        <div class="stamp exempt">VAT-EXEMPT</div>
    @endif

    <table class="totals">
        <tr><td>VATable Sales</td><td class="num">₱{{ $payload['totals']['vatable_sales'] }}</td></tr>
        <tr><td>VAT-Exempt Sales</td><td class="num">₱{{ $payload['totals']['vat_exempt_sales'] }}</td></tr>
        <tr><td>Zero-Rated Sales</td><td class="num">₱{{ $payload['totals']['zero_rated_sales'] }}</td></tr>
        <tr><td>VAT Amount (12%)</td><td class="num">₱{{ $payload['totals']['vat_amount'] }}</td></tr>
        <tr><td>Subtotal</td><td class="num">₱{{ $payload['totals']['subtotal'] }}</td></tr>
        <tr class="grand"><td>TOTAL AMOUNT DUE</td><td class="num">₱{{ $payload['totals']['total_amount'] }}</td></tr>
    </table>

    @if($invoice->isVoided() && $invoice->void_reason)
        <div style="margin-top: 24pt; padding: 8pt; border: 1pt solid #b00; color: #b00;">
            <strong>VOIDED.</strong> Reason: {{ $invoice->void_reason }}
        </div>
    @endif

    <div class="footer">
        Generated by Million Dollar Software (MDS) — BIR-accreditable e-invoicing.
        EIS schema {{ $payload['schema_version'] }}.
        This document is the official invoice for the sale described above per RR 11-2025.
    </div>
</body>
</html>
