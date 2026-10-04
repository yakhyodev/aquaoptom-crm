<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — AquaOptom CRM</title>
    <style>
        @page {
            margin: 1.5cm;
            size: a4 landscape;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif, Arial;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header .meta {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 8px;
            font-size: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .kpi-box {
            padding: 8px 12px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            text-align: center;
        }
        .kpi-val {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .kpi-lbl {
            font-size: 9px;
            text-transform: uppercase;
            color: #475569;
            margin-top: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            font-size: 10px;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #0f172a;
        }
        table.data-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-mono {
            font-family: monospace;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">
            AquaOptom CRM • Suv va ichimliklar ulgurji savdo tizimi
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Davr:</strong> {{ $metadata['period_label'] ?? '-' }}</td>
            <td><strong>Eksport vaqti:</strong> {{ $metadata['exported_at'] ?? '-' }}</td>
            <td><strong>Mas'ul:</strong> {{ $metadata['user_name'] ?? '-' }}</td>
            <td><strong>To'liqlik holati:</strong> {{ $metadata['completeness_status'] ?? 'To\'liq' }}</td>
        </tr>
    </table>

    @if(!empty($kpis))
        <table class="kpi-container">
            <tr>
                @foreach($kpis as $lbl => $val)
                    <td class="kpi-box">
                        <div class="kpi-val">{{ is_numeric($val) ? number_format($val, 0, '.', ' ') : $val }}</div>
                        <div class="kpi-lbl">{{ $lbl }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                @foreach($headers as $th)
                    <th class="{{ in_array($loop->index, $alignRightColumns ?? []) ? 'text-right' : '' }}">{{ $th }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td class="{{ in_array($loop->index, $alignRightColumns ?? []) ? 'text-right font-mono' : '' }}">
                            {{ is_numeric($cell) && in_array($loop->index, $alignRightColumns ?? []) ? number_format($cell, 0, '.', ' ') : $cell }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" class="text-center" style="padding: 20px; color: #94a3b8;">
                        Ma'lumotlar mavjud emas.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(!empty($totals))
            <tfoot>
                <tr style="background-color: #e2e8f0; font-weight: bold;">
                    @foreach($totals as $tot)
                        <td class="{{ in_array($loop->index, $alignRightColumns ?? []) ? 'text-right font-mono' : '' }}">
                            {{ is_numeric($tot) ? number_format($tot, 0, '.', ' ') : $tot }}
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Hujjat AquaOptom CRM orqali yaratildi. Sana: {{ $metadata['exported_at'] ?? now()->format('Y-m-d H:i') }}. Maxfiy boshqaruv hujjati.
    </div>
</body>
</html>
