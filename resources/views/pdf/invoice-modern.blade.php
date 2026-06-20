<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; margin: 0; }
        .page { padding: 24px; }
        .logo, .logo-fallback { width: 44px; height: 44px; border-radius: 10px; }
        .logo-fallback { background: #ff6b35; color: #fff; text-align: center; line-height: 44px; font-weight: bold; }
        .brand { font-size: 18px; font-weight: bold; }
        .badge { background: #ff6b35; color: #fff; border-radius: 16px; padding: 16px 20px; display: inline-block; text-align: left; }
        .badge-title { font-size: 22px; font-weight: bold; letter-spacing: 2px; }
        .party-table { margin: 20px 0; }
        .party-label { color: #ff6b35; font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .party-name { font-weight: bold; font-size: 13px; text-transform: uppercase; margin-bottom: 4px; }
        .muted { color: #64748b; line-height: 1.5; font-size: 10px; }
        .items { border-collapse: collapse; margin-top: 8px; }
        .items th { background: #f1f5f9; padding: 10px; text-align: left; font-size: 9px; text-transform: uppercase; }
        .items td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .totals { width: 260px; margin-left: auto; margin-top: 12px; }
        .totals td { padding: 5px 0; font-size: 10px; }
        .totals .label { color: #64748b; }
        .totals .value { text-align: right; font-weight: bold; }
        .grand { color: #f72525; font-size: 14px; font-weight: bold; border-top: 2px solid #f1f5f9; padding-top: 8px; }
        .footer { margin-top: 20px; padding-top: 12px; border-top: 1px dashed #e2e8f0; font-size: 9px; color: #64748b; }
        .footer-title { color: #ff6b35; font-weight: bold; text-transform: uppercase; font-size: 8px; margin-bottom: 3px; }
        .contact-bar { background: #f8fafc; padding: 10px; margin-top: 12px; font-size: 9px; color: #64748b; }
        .accent-bar { height: 4px; background: #ff6b35; margin: 12px -24px -24px; }
        .status { background: rgba(255,255,255,0.25); padding: 2px 8px; border-radius: 999px; font-size: 9px; }
    </style>
</head>
<body>
@php
    $brand = $company['trade_name'] ?: ($siteName ?? 'StudyPoint');
    $initials = strtoupper(substr($brand, 0, 2));
@endphp
@include('pdf._invoice-context')
<div class="page">
    <table width="100%"><tr>
        <td width="55%" valign="top">
            <table><tr>
                <td>@if(!empty($logoDataUri))<img src="{{ $logoDataUri }}" class="logo">@else<div class="logo-fallback">{{ $initials }}</div>@endif</td>
                <td style="padding-left:10px"><div class="brand">{{ $brand }}</div></td>
            </tr></table>
        </td>
        <td width="45%" align="right" valign="top">
            <div class="badge">
                <div class="badge-title">{{ ($invoice->document_type ?? 'payment') === 'refund' ? 'REFUND INVOICE' : 'INVOICE' }}</div>
                <div>No. {{ $invoice->invoice_code }}</div>
                <div>Date {{ optional($invoice->issued_at)->format('d M Y') ?? now()->format('d M Y') }}</div>
                <div style="margin-top:6px"><span class="status">{{ strtoupper($invoice->status) }}</span></div>
            </div>
        </td>
    </tr></table>

    <table class="party-table" width="100%"><tr>
        <td width="50%" valign="top">
            <div class="party-label">Invoice To</div>
            <div class="party-name">{{ $student?->name ?? 'Student' }}</div>
            <div class="muted">
                @if($student?->student_code)ID: {{ $student->student_code }}<br>@endif
                @if($student?->phone){{ $student->phone }}<br>@endif
                @if($student?->email){{ $student->email }}<br>@endif
                @if($studentAddress){{ $studentAddress }}@endif
            </div>
        </td>
        <td width="50%" valign="top">
            <div class="party-label">Invoice From</div>
            <div class="party-name">{{ $brand }}</div>
            <div class="muted">
                @if($branch?->name)Branch: {{ $branch->name }}<br>@endif
                @if($branchAddressLine){{ $branchAddressLine }}<br>@endif
                GSTIN: {{ $gst['gstin'] ?? '—' }}<br>
                @if(!empty($company['email'])){{ $company['email'] }}@endif
            </div>
        </td>
    </tr></table>

    <table class="items" width="100%">
        <thead><tr><th>No.</th><th>Description</th><th align="right">Price</th><th align="right">Qty</th><th align="right">Amount</th></tr></thead>
        <tbody><tr>
            <td>01</td>
            <td>{{ ($invoice->document_type ?? 'payment') === 'refund' ? 'Subscription refund (remaining days credit)' : 'Study membership — ' . ($student?->plan_name ?? 'Membership fee') }}</td>
            <td align="right">₹{{ number_format((float) $invoice->amount, 2) }}</td>
            <td align="right">1</td>
            <td align="right">₹{{ number_format((float) $invoice->amount, 2) }}</td>
        </tr></tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Sub Total</td><td class="value">₹{{ number_format((float) $invoice->amount, 2) }}</td></tr>
        @if($invoiceSettings['show_gst_breakdown'] ?? true)
            <tr><td class="label">CGST</td><td class="value">₹{{ number_format($cgst, 2) }}</td></tr>
            <tr><td class="label">SGST</td><td class="value">₹{{ number_format($sgst, 2) }}</td></tr>
        @else
            <tr><td class="label">GST</td><td class="value">₹{{ number_format((float) $invoice->gst_amount, 2) }}</td></tr>
        @endif
        <tr><td class="label grand">Grand Total</td><td class="value grand">₹{{ number_format((float) $invoice->total, 2) }}</td></tr>
    </table>

    <div class="footer">
        @if(!empty($invoiceSettings['payment_terms']))
            <div class="footer-title">Terms & Conditions</div><div>{{ $invoiceSettings['payment_terms'] }}</div><br>
        @endif
        @if(($invoiceSettings['show_bank_details'] ?? false) && !empty($invoiceSettings['bank_name']))
            <div class="footer-title">Payment Method</div>
            <div>{{ $invoiceSettings['bank_name'] }} · A/C {{ $invoiceSettings['bank_account'] }} · IFSC {{ $invoiceSettings['bank_ifsc'] }}</div><br>
        @endif
        <div>{{ $invoiceSettings['footer_note'] ?? '' }}</div>
    </div>
    <div class="contact-bar">
        @if(!empty($company['phone'])){{ $company['phone'] }} · @endif
        @if(!empty($company['email'])){{ $company['email'] }}@endif
        @if($branchAddressLine) · {{ $branchAddressLine }}@endif
    </div>
    <div class="accent-bar"></div>
</div>
</body>
</html>
