<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt - {{ $doc['document_number'] }}</title>
  <style>
    @page { margin: 15mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 10px; line-height: 1.5; }
    .header { border-bottom: 2px solid #059669; padding-bottom: 15px; margin-bottom: 20px; }
    .header .issuer { color: #059669; font-size: 8px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; margin: 0; }
    .header h1 { margin: 2px 0; font-size: 20px; color: #111827; }
    .header .tagline { color: #6b7280; font-size: 10px; margin: 0; }
    .header .meta { color: #6b7280; font-size: 8px; margin: 4px 0 0; }
    .header-right { text-align: right; margin-top: -70px; }
    .header-right .label { font-size: 8px; color: #6b7280; margin: 0; }
    .header-right .number { font-family: 'DejaVu Sans Mono', monospace; font-size: 14px; font-weight: 700; margin: 2px 0; }
    .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 8px; font-weight: 700; background: #d1fae5; color: #065f46; }
    .banner { background: linear-gradient(90deg, #059669, #10b981); color: #fff; text-align: center; padding: 8px; font-weight: 700; letter-spacing: 0.15em; font-size: 10px; margin: 20px 0; border-radius: 6px; }
    .info-grid { display: flex; gap: 15px; margin-bottom: 20px; }
    .info-box { background: #f9fafb; padding: 12px; border-radius: 6px; flex: 1; }
    .info-box .title { font-size: 7px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 6px; }
    .info-box .name { font-weight: 700; margin: 0; font-size: 11px; }
    .info-box .detail { font-size: 8px; color: #6b7280; margin: 2px 0; }
    .info-right { font-size: 10px; min-width: 180px; }
    .info-right p { margin: 2px 0; }
    .info-right span { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9px; }
    th { text-align: left; padding: 6px 8px; border-bottom: 2px solid #e5e7eb; color: #6b7280; font-size: 7px; text-transform: uppercase; letter-spacing: 0.05em; }
    td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: 700; }
    .totals { text-align: right; margin-top: 20px; font-size: 11px; padding-top: 10px; border-top: 2px solid #e5e7eb; }
    .totals p { margin: 3px 0; }
    .totals .total-received { font-size: 16px; font-weight: 700; color: #059669; margin-top: 6px; }
    .payment-details { margin-top: 16px; padding: 10px; background: #f9fafb; border-radius: 6px; font-size: 8px; }
    .payment-details .row { display: flex; gap: 20px; margin: 2px 0; }
    .payment-details .label { color: #6b7280; min-width: 100px; }
    .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; text-align: center; }
  </style>
</head>
<body>
  <div class="header">
    <p class="issuer">{{ $doc['issuer']['legal_name'] }}</p>
    <h1>{{ $doc['issuer']['product_name'] }}</h1>
    <p class="tagline">{{ $doc['issuer']['product_tagline'] }}</p>
    <p class="meta">{{ $doc['issuer']['product_of'] }}</p>

    <div class="header-right">
      <p class="label">{{ $doc['document_type_label'] }}</p>
      <p class="number">{{ $doc['document_number'] }}</p>
      <span class="badge">Paid</span>
    </div>
  </div>

  <div class="banner">PAYMENT RECEIPT</div>

  <div class="info-grid">
    <div class="info-box">
      <p class="title">Received From</p>
      <p class="name">{{ $doc['bill_to']['facility_name'] ?? 'N/A' }}</p>
      @if($doc['bill_to']['facility_code'])
        <p class="detail">Code: {{ $doc['bill_to']['facility_code'] }}</p>
      @endif
      @if($doc['bill_to']['email'])
        <p class="detail">{{ $doc['bill_to']['email'] }}</p>
      @endif
      @if($doc['bill_to']['phone'])
        <p class="detail">{{ $doc['bill_to']['phone'] }}</p>
      @endif
      @if($doc['bill_to']['address'])
        <p class="detail">{{ $doc['bill_to']['address'] }}</p>
      @endif
    </div>
    <div class="info-right">
      <p><span>Issued:</span> <strong>{{ $doc['issued_at'] ?? '—' }}</strong></p>
      <p><span>Paid:</span> <strong>{{ $doc['paid_at'] ?? '—' }}</strong></p>
      @if($doc['product']['plan_name'])
        <p><span>Plan:</span> <strong>{{ $doc['product']['plan_name'] }}</strong></p>
      @endif
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="text-right">Qty</th>
        <th class="text-right">Unit Price</th>
        <th class="text-right">Total</th>
      </tr>
    </thead>
    <tbody>
      @forelse($doc['line_items'] as $item)
      <tr>
        <td>{{ $item['description'] }}</td>
        <td class="text-right">{{ $item['quantity'] }}</td>
        <td class="text-right">{{ $doc['currency'] }} {{ number_format((float) $item['unit_price'], 2) }}</td>
        <td class="text-right font-bold">{{ $doc['currency'] }} {{ number_format((float) $item['total'], 2) }}</td>
      </tr>
      @empty
      <tr>
        <td colspan="4" class="text-center" style="color:#9ca3af;">No line items</td>
      </tr>
      @endforelse
    </tbody>
  </table>

  <div class="totals">
    <p>Subtotal: <strong>{{ $doc['currency'] }} {{ number_format($doc['subtotal'], 2) }}</strong></p>
    <p class="total-received">Amount received: {{ $doc['currency'] }} {{ number_format($doc['paid_amount'], 2) }}</p>
  </div>

  @if($doc['payment'])
  <div class="payment-details">
    <p style="font-size:9px;font-weight:700;margin:0 0 6px;color:#374151;">Payment Details</p>
    <div class="row"><span class="label">Method:</span> <strong>{{ $doc['payment']['method_label'] }}</strong></div>
    <div class="row"><span class="label">Reference:</span> <strong>{{ $doc['payment']['transaction_reference'] ?? '—' }}</strong></div>
    <div class="row"><span class="label">Receipt No:</span> <strong>{{ $doc['payment']['receipt_number'] ?? '—' }}</strong></div>
    <div class="row"><span class="label">Approved:</span> <strong>{{ $doc['paid_at'] ?? '—' }}</strong></div>
  </div>
  @endif

  <div class="footer">
    <p>Payment received by {{ $doc['issuer']['legal_name'] }} for Custocare subscription services.</p>
    <p>{{ $doc['issuer']['address_line'] }} · {{ $doc['issuer']['website_label'] }}</p>
    <p style="margin-top:6px;">Electronically Generated · Powered by Custospark Company Ltd</p>
  </div>
</body>
</html>
