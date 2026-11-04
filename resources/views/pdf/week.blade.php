@php $fmt = fn ($n) => ($n < 0 ? '-' : '') . '₡' . number_format(abs($n), 0, ',', '.'); @endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Semana {{ $d['start']->toDateString() }}</title>
  <style>@include('pdf._style')</style>
</head>
<body>
  <h1>{{ config('app.name') }} · Reporte semanal</h1>
  <div class="sub">
    Semana del {{ $d['start']->translatedFormat('j M') }} al {{ $d['end']->translatedFormat('j M Y') }}
    · Pago: sábado {{ $d['payAt']->format('j/m') }} a las 7:00 PM
  </div>

  <h2>Resumen</h2>
  <table class="kpi">
    <tr>
      <td><b>{{ $d['totals']['cuts'] }}</b>Cortes</td>
      <td><b>{{ $fmt($d['totals']['total']) }}</b>Producido</td>
      <td><b>{{ $fmt($d['totals']['owner']) }}</b>Parte del jefe</td>
      <td><b class="{{ $d['profit'] < 0 ? 'neg' : '' }}">{{ $fmt($d['profit']) }}</b>Utilidad del jefe</td>
    </tr>
  </table>

  <h2>Pago a los barberos</h2>
  <table>
    <thead>
      <tr><th>Barbero</th><th class="r">Cortes</th><th class="r">Producido</th><th class="r">Comisión</th>
          <th class="r">Propinas</th><th class="r">A pagar</th><th>Estado</th><th style="width:90px">Firma</th></tr>
    </thead>
    <tbody>
      @forelse($d['barbers'] as $b)
        @php $p = $b['payout']; @endphp
        <tr>
          <td>{{ $b['name'] }}</td><td class="r">{{ $b['cuts'] }}</td><td class="r">{{ $fmt($b['total']) }}</td>
          <td class="r">{{ $fmt($b['commission']) }}</td><td class="r">{{ $fmt($b['tips']) }}</td>
          <td class="r"><b>{{ $fmt($b['to_pay']) }}</b></td>
          <td>{{ $p ? 'Pagado (' . $p->method . ') ' . $p->paid_on->format('d/m') : 'Pendiente' }}</td>
          <td class="sign"></td>
        </tr>
      @empty
        <tr><td colspan="8" class="muted">Sin servicios cobrados esta semana.</td></tr>
      @endforelse
      @if($d['barbers']->isNotEmpty())
        <tr class="tot">
          <td>TOTAL</td><td class="r">{{ $d['totals']['cuts'] }}</td><td class="r">{{ $fmt($d['totals']['total']) }}</td>
          <td class="r">{{ $fmt($d['totals']['commission']) }}</td><td class="r">{{ $fmt($d['totals']['tips']) }}</td>
          <td class="r">{{ $fmt($d['totals']['commission'] + $d['totals']['tips']) }}</td><td colspan="2"></td>
        </tr>
      @endif
    </tbody>
  </table>

  <h2>Por día</h2>
  <table>
    <thead><tr><th>Día</th><th class="r">Cortes</th><th class="r">Producido</th><th class="r">Propinas</th></tr></thead>
    <tbody>
      @foreach($d['days'] as $day)
        <tr><td>{{ ucfirst($day['date']->translatedFormat('l j')) }}</td><td class="r">{{ $day['cuts'] }}</td>
            <td class="r">{{ $fmt($day['total']) }}</td><td class="r">{{ $fmt($day['tips']) }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <h2>Cortes por servicio y método de pago</h2>
  <table>
    <thead><tr><th>Servicio</th><th class="r">Cantidad</th><th class="r">Monto</th></tr></thead>
    <tbody>
      @forelse($d['byService'] as $r)
        <tr><td>{{ $r['name'] }}</td><td class="r">{{ $r['count'] }}</td><td class="r">{{ $fmt($r['amount']) }}</td></tr>
      @empty
        <tr><td colspan="3" class="muted">Sin servicios.</td></tr>
      @endforelse
    </tbody>
  </table>
  <br>
  <table>
    <thead><tr><th>Método</th><th class="r">Cortes</th><th class="r">Monto (con propina)</th></tr></thead>
    <tbody>
      @foreach(['efectivo' => 'Efectivo', 'sinpe' => 'SINPE', 'tarjeta' => 'Tarjeta'] as $k => $label)
        <tr><td>{{ $label }}</td><td class="r">{{ $d['byMethod'][$k]['count'] }}</td><td class="r">{{ $fmt($d['byMethod'][$k]['amount']) }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <h2>Utilidad del jefe</h2>
  <table>
    <tr><td>Parte del jefe por servicios</td><td class="r">{{ $fmt($d['totals']['owner']) }}</td></tr>
    @foreach($d['expenses'] as $e)
      <tr><td class="muted">&nbsp;&nbsp;Gasto: {{ $e->concept }} ({{ $e->date->format('d/m') }})</td><td class="r neg">-{{ $fmt($e->amount) }}</td></tr>
    @endforeach
    <tr class="final"><td>UTILIDAD DE LA SEMANA</td><td class="r">{{ $fmt($d['profit']) }}</td></tr>
  </table>

  <div class="foot">Generado el {{ now()->translatedFormat('j M Y, g:i A') }} por {{ auth()->user()->name }}.</div>
</body>
</html>