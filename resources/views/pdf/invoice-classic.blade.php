<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; margin: 0; }
        .page { padding: 22px; }
        .title { font-size: 22px; font-weight: bold; color: #7c3aed; margin-bottom: 6px; }
        .logo, .logo-fallback { width: 44px; height: 44px; border-radius: 8px; }
        .logo-fallback { background: #7c3aed; color: #fff; text-align: center; line-height: 44px; font-weight: bold; }
        .brand { font-size: 16px; font-weight: bold; }
        .head-border { border-bottom: 3px solid #7c3aed; padding-bottom: 12px; margin-bottom: 14px; }
        .bill-box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; vertical-align: top; width: 48%; }
        .bill-label { color: #7c3aed; font-size: 9px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; }
        .bill-name { font-weight: bold; font-size: 12px; margin-bottom: 4px; }
        .muted { color: #64748b; font-size: 10px; line-height: 1.45; }
        .chip { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 999px; padding: 3px 10px; font-size: 9px; margin-right: 8px; display: inline-block; margin-bottom: 10px; }
        .items { border-collapse: collapse; width: 100%; margin-top: 6px; }
        .items th { background: #7c3aed; color: #fff; padding: 9px 8px; font-size: 8px; text-transform: uppercase; border: 1px solid #6d28d9; }
        .items td { padding: 9px 8px; border: 1px solid #e2e8f0; font-size: 10px; }
        .totals { width: 260px; border: 1px solid #e2e8f0; border-collapse: collapse; margin-left: auto; }
        .totals td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .totals .label { color: #64748b; }
        .totals .value { text-align: right; font-weight: bold; }
        .grand td { background: #7c3aed; color: #fff; font-weight: bold; font-size: 12px; border: none; }
        .bank-box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; width: 48%; vertical-align: top; }
        .footer { margin-top: 16px; padding-top: 12px; border-top: 1px dashed #e2e8f0; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
@php
    $brand = $company['trade_name'] ?: ($siteName ?? 'StudyPoint');
    $initials = strtoupper(substr($brand, 0, 2));
    $hsn = $invoiceSettings['hsn_sac_code'] ?? '999293';
@endphp
<div class="page">
    <table width="100%" class="head-border"><tr>
        <td width="50%" valign="top">
            <div class="title">Tax Invoice</div>
            <div><strong>#</strong> {{ $invoice->invoice_code }}</div>
            <div><strong>Date</strong> {{ optional($invoice->issued_at)->format('d M Y') ?? now()->format('d M Y') }}</div>
            <div><strong>Status</strong> {{ strtoupper($invoice->status) }}</div>
        </td>
        <td width="50%" align="right" valign="top">
            <table align="right"><tr>
                <td>@if(!empty($logoSrc))<img src="{!! $logoSrc !!}" class="logo" alt="">@else<div class="logo-fallback">{{ $initials }}</div>@endif</td>
                <td style="padding-left:8px"><div class="brand">{{ $brand }}</div><div class="muted">{{ $company['legal_name'] }}</div></td>
            </tr></table>
        </td>
    </tr></table>

    <table width="100%"><tr>
        <td class="bill-box">
            <div class="bill-label">Billed By</div>
            <div class="bill-name">{{ $brand }}</div>
            <div class="muted">
                @if($branch?->name)Branch: <strong>{{ $branch->name }}</strong><br>@endif
                @if($branchAddressLine){{ $branchAddressLine }}<br>@endif
                GSTIN: {{ $gst['gstin'] ?? '—' }} · PAN: {{ $gst['pan'] ?? '—' }}<br>
                @if(!empty($company['email'])){{ $company['email'] }}@endif
                @if(!empty($company['phone'])) · {{ $company['phone'] }}@endif
            </div>
        </td>
        <td width="4%"></td>
        <td class="bill-box">
            <div class="bill-label">Billed To</div>
            <div class="bill-name">{{ $student?->name ?? 'Student' }}</div>
            <div class="muted">
                ID: {{ $student?->student_code ?? '—' }}<br>
                @if($student?->phone){{ $student->phone }}<br>@endif
                @if($student?->email){{ $student->email }}<br>@endif
                @if($studentAddress){{ $studentAddress }}@endif
            </div>
        </td>
    </tr></table>

    <div style="margin-top:12px">
        <span class="chip">Place of Supply: {{ $gst['state_name'] ?? $company['state'] }}</span>
        <span class="chip">HSN/SAC: {{ $hsn }}</span>
    </div>

    <table class="items">
        <thead><tr>
            <th>Item</th><th>HSN</th><th>Qty</th><th>Rate</th>
            @if($invoiceSettings['show_gst_breakdown'] ?? true)<th>Taxable</th><th>CGST</th><th>SGST</th>@else<th>GST</th>@endif
            <th>Amount</th>
        </tr></thead>
        <tbody><tr>
            <td>{{ ($invoice->document_type ?? 'payment') === 'refund' ? 'Subscription refund (remaining days credit)' : 'Study membership — ' . ($student?->plan_name ?? 'Fee') }}</td>
            <td>{{ $hsn }}</td><td>1</td>
            <td align="right">{{ number_format((float) $invoice->amount, 2) }}</td>
            @if($invoiceSettings['show_gst_breakdown'] ?? true)
                <td align="right">{{ number_format((float) $invoice->amount, 2) }}</td>
                <td align="right">{{ number_format($cgst, 2) }}</td>
                <td align="right">{{ number_format($sgst, 2) }}</td>
            @else
                <td align="right">{{ number_format((float) $invoice->gst_amount, 2) }}</td>
            @endif
            <td align="right">{{ number_format((float) $invoice->total, 2) }}</td>
        </tr></tbody>
    </table>

    <table width="100%" style="margin-top:14px"><tr>
        @if(($invoiceSettings['show_bank_details'] ?? false) && !empty($invoiceSettings['bank_name']))
        <td class="bank-box" valign="top">
            <div class="bill-label">Bank Details</div>
            <div class="muted">{{ $invoiceSettings['bank_name'] }}<br>A/C {{ $invoiceSettings['bank_account'] }}<br>IFSC {{ $invoiceSettings['bank_ifsc'] }}</div>
        </td>
        <td width="4%"></td>
        @endif
        <td align="right" valign="top">
            <table class="totals">
                <tr><td class="label">Sub Total</td><td class="value">₹{{ number_format((float) $invoice->amount, 2) }}</td></tr>
                @if($invoiceSettings['show_gst_breakdown'] ?? true)
                    <tr><td class="label">CGST</td><td class="value">₹{{ number_format($cgst, 2) }}</td></tr>
                    <tr><td class="label">SGST</td><td class="value">₹{{ number_format($sgst, 2) }}</td></tr>
                @else
                    <tr><td class="label">GST</td><td class="value">₹{{ number_format((float) $invoice->gst_amount, 2) }}</td></tr>
                @endif
                <tr class="grand"><td>Grand Total</td><td align="right">₹{{ number_format((float) $invoice->total, 2) }}</td></tr>
            </table>
        </td>
    </tr></table>

    <div class="footer">
        @if(!empty($invoiceSettings['payment_terms']))<div><strong>Terms:</strong> {{ $invoiceSettings['payment_terms'] }}</div>@endif
        <div>{{ $invoiceSettings['footer_note'] ?? '' }}</div>
    </div>
</div>
</body>
</html>
