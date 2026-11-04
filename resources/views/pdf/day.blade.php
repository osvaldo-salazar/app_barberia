@php
  $fmt = fn ($n) => ($n < 0 ? '-' : '') . '₡' . number_format(abs($n), 0, ',', '.');
  $total = $s['services'] + $s['tips'];
  $stay  = $closing ? $closing->counted_cash : $expected;
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Caja {{ $date->toDateString() }}</title>
  <style>@include('pdf._style')</style>
</head>
<body>
  <h1>{{ config('app.name') }} · Reporte diario</h1>
  <div class="sub">{{ ucfirst($date->translatedFormat('l j \d\e F \d\e Y')) }}</div>

  <h2>Resumen del día</h2>
  <table class="kpi">
    <tr>
      <td><b>{{ $s['cuts'] }}</b>Cortes realizados</td>
      <td><b>{{ $fmt($s['services']) }}</b>Producido</td>
      <td><b>{{ $fmt($s['tips']) }}</b>Propinas</td>
      <td><b>{{ $fmt($total) }}</b>Total cobrado</td>
    </tr>
  </table>
  <div class="muted" style="margin-top:5px">
    Pendientes sin cobrar: {{ $s['pending'] }} · No vinieron: {{ $s['noshow'] }} · Canceladas: {{ $s['canceled'] }}
  </div>

  <h2>Cortes por método de pago</h2>
  <table>
    <thead><tr><th>Método</th><th class="r">Cortes</th><th class="r">Monto (con propina)</th></tr></thead>
    <tbody>
      @foreach(['efectivo' => 'Efectivo', 'sinpe' => 'SINPE', 'tarjeta' => 'Tarjeta'] as $k => $label)
        <tr><td>{{ $label }}</td><td class="r">{{ $s['byMethod'][$k]['count'] }}</td><td class="r">{{ $fmt($s['byMethod'][$k]['amount']) }}</td></tr>
      @endforeach
      @if($s['byMethod']['sin_metodo']['count'])
        <tr><td>Sin método registrado</td><td class="r">{{ $s['byMethod']['sin_metodo']['count'] }}</td><td class="r">{{ $fmt($s['byMethod']['sin_metodo']['amount']) }}</td></tr>
      @endif
      <tr class="tot"><td>Total</td><td class="r">{{ $s['cuts'] }}</td><td class="r">{{ $fmt($total) }}</td></tr>
    </tbody>
  </table>

  <h2>Cortes por servicio</h2>
  <table>
    <thead><tr><th>Servicio</th><th class="r">Cantidad</th><th class="r">Monto</th></tr></thead>
    <tbody>
      @forelse($s['byService'] as $r)
        <tr><td>{{ $r['name'] }}</td><td class="r">{{ $r['count'] }}</td><td class="r">{{ $fmt($r['amount']) }}</td></tr>
      @empty
        <tr><td colspan="3" class="muted">Sin servicios cobrados.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2>Por barbero</h2>
  <table>
    <thead><tr><th>Barbero</th><th class="r">Cortes</th><th class="r">Producido</th><th class="r">Gana barbero</th><th class="r">Propinas</th><th class="r">Gana jefe</th></tr></thead>
    <tbody>
      @forelse($s['byBarber'] as $r)
        <tr><td>{{ $r['name'] }}</td><td class="r">{{ $r['cuts'] }}</td><td class="r">{{ $fmt($r['total']) }}</td>
            <td class="r">{{ $fmt($r['barber']) }}</td><td class="r">{{ $fmt($r['tips']) }}</td><td class="r">{{ $fmt($r['owner']) }}</td></tr>
      @empty
        <tr><td colspan="6" class="muted">Sin servicios cobrados.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2>Caja de efectivo</h2>
  <table>
    <tr><td>Caja al empezar el día</td><td class="r">{{ $fmt($opening) }}</td></tr>
    <tr><td>+ Efectivo cobrado</td><td class="r">{{ $fmt($cashSales) }}</td></tr>
    @foreach($m['items'] as $it)
      <tr><td class="muted">&nbsp;&nbsp;{{ $it['kind'] }}: {{ $it['label'] }}</td>
          <td class="r {{ $it['amount'] < 0 ? 'neg' : 'pos' }}">{{ $fmt($it['amount']) }}</td></tr>
    @endforeach
    <tr class="tot"><td>= Esperado en caja</td><td class="r">{{ $fmt($expected) }}</td></tr>
    @if($closing)
      <tr><td>Efectivo contado</td><td class="r">{{ $fmt($closing->counted_cash) }}</td></tr>
      <tr><td>Diferencia</td>
          <td class="r {{ $closing->difference < 0 ? 'neg' : '' }}">
            {{ $closing->difference == 0 ? 'Cuadra' : ($closing->difference > 0 ? 'Sobra ' : 'Falta ') . $fmt(abs($closing->difference)) }}
          </td></tr>
    @endif
    <tr class="final"><td>QUEDA EN CAJA {{ $closing ? '' : '(estimado, falta cerrar)' }}</td><td class="r">{{ $fmt($stay) }}</td></tr>
  </table>
  @if($closing && $closing->notes)
    <p class="muted">Notas: {{ $closing->notes }}</p>
  @endif

  <div class="foot">
    Generado el {{ now()->translatedFormat('j M Y, g:i A') }} por {{ auth()->user()->name }}.
    @if($closing) Cierre guardado por {{ $closing->closer?->name ?? 'usuario eliminado' }}.@endif
  </div>
</body>
</html>