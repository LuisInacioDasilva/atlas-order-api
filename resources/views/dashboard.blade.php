<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>Atlas · Pedidos</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #080d19; color: #e9f2ff; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at 85% -10%, #173568 0, transparent 37%), #080d19; }
        main { max-width: 1180px; margin: auto; padding: 46px 26px 80px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .brand { display: flex; gap: 12px; align-items: center; color: #a8c3e6; font-size: 13px; letter-spacing: .17em; font-weight: 800; }
        .mark { display: grid; place-items: center; width: 32px; height: 32px; background: #397ef4; color: white; border-radius: 10px; letter-spacing: 0; }
        .pill { border: 1px solid #255a52; color: #70e5b6; background: #0f2b2b; border-radius: 99px; padding: 8px 12px; font-size: 12px; font-weight: 700; }
        h1 { font-size: clamp(38px, 6vw, 64px); letter-spacing: -.055em; margin: 60px 0 12px; line-height: 1.06; }
        .lede { color: #91a7c8; max-width: 660px; line-height: 1.6; margin: 0 0 38px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .stat, .panel { background: #111b2c; border: 1px solid #273950; border-radius: 18px; }
        .stat { padding: 24px; }
        .stat small { display: block; text-transform: uppercase; color: #8da6c7; font-size: 11px; letter-spacing: .12em; font-weight: 700; }
        .stat strong { display: block; margin-top: 12px; font-size: 34px; letter-spacing: -.04em; }
        .section { display: flex; align-items: baseline; justify-content: space-between; margin: 44px 0 16px; }
        h2 { font-size: 20px; margin: 0; }
        .section span, .muted { color: #7890af; font-size: 12px; }
        .panel { overflow: hidden; }
        table { border-collapse: collapse; width: 100%; }
        th { color: #7890af; font-size: 11px; text-align: left; text-transform: uppercase; letter-spacing: .1em; }
        td, th { padding: 18px 22px; border-bottom: 1px solid #26354b; }
        tr:last-child td { border-bottom: 0; }
        td { font-size: 13px; }
        .email { color: #839cbd; font-size: 12px; display: block; margin-top: 4px; }
        code { color: #94bdfd; font-size: 12px; }
        .status { border-radius: 99px; padding: 6px 10px; font-size: 11px; font-weight: 800; background: #1e3b66; color: #a9cbff; }
        .status.approved { background: #183c37; color: #78e2b6; }
        .status.fulfilled { background: #32402b; color: #c9ed8c; }
        .status.cancelled { background: #492733; color: #ffabb7; }
        .empty { padding: 34px 22px; color: #93a9c5; line-height: 1.5; }
        .foot { margin-top: 22px; color: #627b9b; font-size: 12px; }
        @media (max-width: 760px) { .stats { grid-template-columns: repeat(2, 1fr); } main { padding: 28px 14px; } .hide-sm { display: none; } td, th { padding: 14px 10px; } }
    </style>
</head>
<body>
<main>
    <div class="top"><div class="brand"><span class="mark">A</span> ATLAS / ORDER API</div><span class="pill">● DEMO LOCAL</span></div>
    <h1>Pedidos sob controle.</h1>
    <p class="lede">Uma API de pedidos com transições explícitas, idempotência e trilha de auditoria. Este painel é só de leitura e fica disponível apenas no ambiente local.</p>

    <div class="stats">
        @foreach (['pending' => 'Pendentes', 'approved' => 'Aprovados', 'fulfilled' => 'Concluídos', 'cancelled' => 'Cancelados'] as $key => $label)
            <div class="stat"><small>{{ $label }}</small><strong>{{ $totals[$key] ?? 0 }}</strong></div>
        @endforeach
    </div>

    <div class="section"><h2>Últimos pedidos</h2><span>Atualização a cada 15 segundos</span></div>
    <div class="panel">
        @if ($orders->isEmpty())
            <div class="empty">Ainda não há pedidos. Use a API para criar o primeiro; o exemplo de chamada está no README.</div>
        @else
            <table>
                <thead><tr><th>Cliente</th><th class="hide-sm">Pedido</th><th>Valor</th><th>Status</th><th class="hide-sm">Versão</th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td><strong>{{ $order->customer_name }}</strong><span class="email">{{ $order->customer_email }}</span></td>
                        <td class="hide-sm"><code>{{ substr($order->id, 0, 8) }}…</code></td>
                        <td>{{ $order->currency }} {{ number_format($order->amount_cents / 100, 2, ',', '.') }}</td>
                        <td><span class="status {{ $order->status }}">{{ strtoupper($order->status) }}</span></td>
                        <td class="hide-sm">v{{ $order->version }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <p class="foot">Dados sintéticos para demonstração. O painel não é publicado em outros ambientes.</p>
</main>
</body>
</html>
