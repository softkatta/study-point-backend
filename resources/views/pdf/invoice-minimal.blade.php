<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; margin: 0; }
        .hero { background: #a7f3d0; padding: 22px 24px 30px; }
        .hero-title { font-size: 28px; font-weight: bold; letter-spacing: 1px; }
        .hero-meta { margin-top: 10px; font-size: 10px; }
        .hero-meta span { color: #0f766e; font-size: 8px; letter-spacing: 1px; margin-right: 4px; }
        .logo, .logo-fallback { width: 40px; height: 40px; border-radius: 8px; }
        .logo-fallback { background: #0f766e; color: #fff; text-align: center; line-height: 40px; font-weight: bold; }
        .brand { font-weight: bold; font-size: 14px; }
        .to-label { color: #0f766e; font-size: 8px; letter-spacing: 1px; font-weight: bold; margin-bottom: 4px; }
        .party-name { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
        .muted { color: #64748b; font-size: 10px; line-height: 1.45; }
        .summary { width: 100%; border-collapse: collapse; margin: 14px 0; }
        .summary td { border: 1px solid #e2e8f0; padding: 8px 10px; font-size: 9px; }
        .summary span { display: block; color: #0f766e; font-size: 7px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .items { width: 100%; border-collapse: collapse; }
        .items th { color: #0f766e; border-bottom: 3px solid #0f766e; padding: 8px 6px; font-size: 8px; text-transform: uppercase; text-align: left; }
        .items td { padding: 10px 6px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .totals { border-collapse: collapse; margin-left: auto; margin-top: 12px; }
        .totals td { border: 1px solid #e2e8f0; padding: 7px 12px; font-size: 10px; }
        .totals .label { color: #64748b; }
        .totals .value { text-align: right; font-weight: bold; }
        .grand { background: #ecfdf5; color: #0f766e; font-weight: bold; }
        .body { padding: 16px 24px 24px; }
        .footer { font-size: 9px; color: #64748b; margin-top: 14px; line-height: 1.5; }
    </style>
</head>
<body>
@php
    $brand = $company['trade_name'] ?: ($siteName ?? 'StudyPoint');
    $initials = strtoupper(substr($brand, 0, 2));
@endphp
@include('pdf._invoice-context')
<div class="hero">
    <table width="100%"><tr>
        <td width="50%" valign="top">
            <div class="hero-title">{{ ($invoice->document_type ?? 'payment') === 'refund' ? 'REFUND INVOICE' : 'INVOICE' }}</div>
            <div class="hero-meta">
                <div><span>DATE</span> <strong>{{ optional($invoice->issued_at)->format('d.m.Y') ?? now()->format('d.m.Y') }}</strong></div>
                <div><span>INVOICE NO</span> <strong>{{ $invoice->invoice_code }}</strong></div>
            </div>
        </td>
        <td width="50%" align="right" valign="top">
            <table align="right"><tr>
                <td>@if(!empty($logoDataUri))<img src="{{ $logoDataUri }}" class="logo">@else<div class="logo-fallback">{{ $initials }}</div>@endif</td>
                <td style="padding-left:8px;text-align:left"><div class="brand">{{ $brand }}</div><div class="muted">{{ $company['email'] }}</div></td>
            </tr></table>
            <div class="muted" style="margin-top:6px;text-align:right">
                @if($branch?->name)Branch: {{ $branch->name }}<br>@endif
                @if($branchAddressLine){{ $branchAddressLine }}<br>@endif
                GSTIN: {{ $gst['gstin'] ?? '—' }}
            </div>
        </td>
    </tr></table>
</div>

<div class="body">
    <div class="to-label">Invoice To</div>
    <div class="party-name">{{ $student?->name ?? 'Student' }}</div>
    <div class="muted">
        @if($student?->student_code)Member ID: {{ $student->student_code }}<br>@endif
        @if($student?->phone){{ $student->phone }}<br>@endif
        @if($student?->email){{ $student->email }}<br>@endif
        @if($studentAddress){{ $studentAddress }}@endif
    </div>

    <table class="summary">
        <tr>
            <td><span>Client</span><strong>{{ $student?->name ?? '—' }}</strong></td>
            <td><span>Branch</span><strong>{{ $student?->branch?->name ?? '—' }}</strong></td>
            <td><span>GSTIN</span><strong>{{ $gst['gstin'] ?? '—' }}</strong></td>
            <td><span>Status</span><strong>{{ strtoupper($invoice->status) }}</strong></td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>Item</th><th align="right">Unit Price</th><th align="right">Qty</th><th align="right">Line Total</th></tr></thead>
        <tbody><tr>
            <td>{{ ($invoice->document_type ?? 'payment') === 'refund' ? 'Subscription refund (remaining days credit)' : 'Study membership — ' . ($student?->plan_name ?? 'Membership fee') }}</td>
            <td align="right">₹{{ number_format((float) $invoice->amount, 2) }}</td>
            <td align="right">1</td>
            <td align="right">₹{{ number_format((float) $invoice->amount, 2) }}</td>
        </tr></tbody>
    </table>

    <table class="totals" align="right">
        <tr><td class="label">Subtotal</td><td class="value">₹{{ number_format((float) $invoice->amount, 2) }}</td></tr>
        @if($invoiceSettings['show_gst_breakdown'] ?? true)
            <tr><td class="label">CGST</td><td class="value">₹{{ number_format($cgst, 2) }}</td></tr>
            <tr><td class="label">SGST</td><td class="value">₹{{ number_format($sgst, 2) }}</td></tr>
        @else
            <tr><td class="label">GST</td><td class="value">₹{{ number_format((float) $invoice->gst_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="value">₹{{ number_format((float) $invoice->total, 2) }}</td></tr>
    </table>

    <div class="footer">
        @if(!empty($invoiceSettings['payment_terms']))<div>{{ $invoiceSettings['payment_terms'] }}</div>@endif
        <div>{{ $invoiceSettings['footer_note'] ?? '' }}</div>
    </div>
</div>
</body>
</html>
