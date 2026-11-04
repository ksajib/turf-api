<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .page { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 30px; }
        .top { width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 24px; }
        .brand { width: 68%; vertical-align: top; }
        .meta { width: 32%; vertical-align: top; text-align: right; }
        .eyebrow { color: #15803d; font-weight: 700; font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { margin: 7px 0 0; font-size: 25px; line-height: 1.2; }
        .label { color: #64748b; font-size: 11px; }
        .value { margin-top: 5px; font-weight: 700; font-size: 13px; }
        .details { width: 100%; border-bottom: 1px solid #e2e8f0; padding: 24px 0; }
        .details td { width: 50%; vertical-align: top; }
        .details td:last-child { text-align: right; }
        .section-gap { height: 24px; }
        .product { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        .product th { background: #f8fafc; color: #64748b; font-size: 11px; text-align: left; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
        .product th:last-child, .product td:last-child { text-align: right; }
        .product td { padding: 18px 14px; vertical-align: top; }
        .product-name { font-weight: 700; font-size: 13px; }
        .product-sub { color: #64748b; font-size: 11px; margin-top: 5px; }
        .summary { width: 48%; margin-left: 52%; padding-top: 22px; }
        .summary-row { width: 100%; padding: 7px 0; }
        .summary-row td:last-child { text-align: right; font-weight: 700; }
        .summary-total { border-top: 1px solid #e2e8f0; padding-top: 14px; font-size: 15px; }
        .footer { border-top: 1px solid #e2e8f0; margin-top: 24px; padding-top: 18px; }
        .status-paid { color: #15803d; font-weight: 700; }
        .status-due { color: #b91c1c; font-weight: 700; }
    </style>
</head>
<body>
    @php
        $amountDue = (int) $booking->price_taka;
        $totalPayment = (int) ($booking->amount_received_taka ?? 0);
        $due = max(0, $amountDue - $totalPayment);
    @endphp
    <div class="page">
        <table class="top"><tr><td class="brand"><div class="eyebrow">Payment invoice</div><h1>Booking payment invoice</h1></td><td class="meta"><div class="label">Invoice number</div><div class="value">INV-{{ $booking->booking_number }}</div><div class="label" style="margin-top: 14px;">Issued date</div><div class="value">{{ $booking->booking_date?->format('Y-m-d') }}</div></td></tr></table>
        <table class="details"><tr><td><div class="label">Billed to</div><div class="value">{{ $booking->customer?->user?->name ?? 'Unknown customer' }}</div><div class="product-sub">{{ $booking->customer?->user?->phone ?? $booking->customer?->user?->email ?? 'No contact' }}</div></td><td><div class="label">Schedule</div><div class="value">{{ $booking->booking_date?->format('Y-m-d') }}</div><div class="product-sub">{{ $booking->turf?->name ?? 'Turf' }} · {{ substr((string) $booking->start_time, 0, 5) }} - {{ substr((string) $booking->end_time, 0, 5) }}</div></td></tr></table>
        <div class="section-gap"></div>
        <table class="product"><tr><th>Product / service</th><th>Amount</th></tr><tr><td><div class="product-name">{{ $booking->turf?->name ?? 'Turf booking' }} · {{ $booking->duration_minutes }} minutes</div><div class="product-sub">{{ $booking->turf?->sport ?? 'Sports' }} · {{ $booking->turf?->format ?? 'Turf reservation' }}</div></td><td class="product-name">BDT {{ number_format($amountDue) }}</td></tr></table>
        <table class="summary"><tr class="summary-row"><td class="label">Amount due</td><td>BDT {{ number_format($amountDue) }}</td></tr><tr class="summary-row"><td class="label">Due</td><td class="{{ $due ? 'status-due' : 'status-paid' }}">BDT {{ number_format($due) }}</td></tr><tr class="summary-row summary-total"><td><strong>Total payment</strong></td><td>BDT {{ number_format($totalPayment) }}</td></tr></table>
        <table class="footer"><tr><td><span class="label">Payment method</span><br><strong>{{ $booking->payment_method ?? 'Cash' }}</strong></td><td style="text-align:right"><span class="label">Payment status</span><br><strong class="{{ $booking->payment_status === 'paid' ? 'status-paid' : 'status-due' }}">{{ $booking->payment_status === 'paid' ? 'Paid' : ucfirst($booking->payment_status) }}</strong></td></tr></table>
    </div>
</body>
</html>
