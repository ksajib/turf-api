<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .header { border-bottom: 3px solid #15803d; padding-bottom: 18px; margin-bottom: 22px; }
        .brand { color: #15803d; font-size: 22px; font-weight: bold; }
        .title { color: #475569; font-size: 13px; margin-top: 5px; }
        .meta { width: 100%; margin-bottom: 20px; }
        .meta td { width: 50%; vertical-align: top; padding: 4px 0; }
        .label { color: #64748b; font-size: 10px; text-transform: uppercase; }
        .value { font-size: 12px; margin-top: 3px; }
        table.breakdown { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .breakdown th, .breakdown td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .breakdown th:last-child, .breakdown td:last-child { text-align: right; }
        .total td { color: #15803d; font-size: 15px; font-weight: bold; }
        .status { margin-top: 24px; padding: 10px; background: #ecfdf3; color: #166534; }
    </style>
</head>
<body>
    <div class="header"><div class="brand">Turf Management</div><div class="title">Subscription invoice</div></div>
    <table class="meta">
        <tr><td><div class="label">Invoice</div><div class="value">{{ $invoice }}</div></td><td><div class="label">Payment date</div><div class="value">{{ $paymentDate }}</div></td></tr>
        <tr><td><div class="label">Business</div><div class="value">{{ $business }}</div></td><td><div class="label">Transaction</div><div class="value">{{ $transaction }}</div></td></tr>
        <tr><td><div class="label">Owner</div><div class="value">{{ $owner }}</div></td><td><div class="label">Email</div><div class="value">{{ $email }}</div></td></tr>
        <tr><td><div class="label">Plan</div><div class="value">{{ $plan }}</div></td><td><div class="label">Billing</div><div class="value">{{ $billing }}</div></td></tr>
    </table>
    <table class="breakdown">
        <tr><th>Description</th><th>Amount (BDT)</th></tr>
        <tr><td>Subscription fee</td><td>{{ $subtotal }}</td></tr>
        <tr><td>Discount</td><td>{{ $discount }}</td></tr>
        <tr class="total"><td>Total payable</td><td>{{ $total }}</td></tr>
    </table>
    <div class="status">Payment status: {{ $status }}</div>
</body>
</html>
