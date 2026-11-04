@extends('layouts.app')

@section('content')
@php
  $fmt   = fn ($n) => ($n < 0 ? '-' : '') . '₡' . number_format(abs($n), 0, ',', '.');
  $prev  = $date->copy()->subDay()->toDateString();
  $next  = $date->copy()->addDay()->toDateString();
  $total = $s['services'] + $s['tips'];
  $diffText = fn ($d) => $d == 0 ? 'Cuadra' : ($d > 0 ? 'Sobra ' . $fmt($d) : 'Falta ' . $fmt(abs($d)));
  $diffCls  = fn ($d) => $d == 0 ? 'text-success' : ($d > 0 ? 'text-warning' : 'text-danger');
@endphp

<div class="container-xxl" style="max-width: 1000px">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <div>
      <h1 class="h4 m-0">Cierre de caja</h1>
      <div class="text-muted text-capitalize small">{{ $date->translatedFormat('l j \d\e F, Y') }}</div>
    </div>
    <div class="d-flex align-items-center gap-1 flex-wrap">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('caja', ['fecha' => $prev]) }}" aria-label="Día anterior"><i class="bi bi-chevron-left"></i></a>
      <input type="date" class="form-control form-control-sm w-auto" value="{{ $date->toDateString() }}"
             max="{{ today()->toDateString() }}"
             onchange="location='{{ route('caja') }}?fecha=' + this.value">
      @if($date->lt(today()))
        <a class="btn btn-outline-dark btn-sm" href="{{ route('caja', ['fecha' => $next]) }}" aria-label="Día siguiente"><i class="bi bi-chevron-right"></i></a>
      @endif
      <a class="btn btn-dark btn-sm" href="{{ route('caja.pdf', ['fecha' => $date->toDateString()]) }}">
        <i class="bi bi-file-earmark-pdf me-1"></i>PDF del día
      </a>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0 ps-3">@foreach($errors->all() as $msg) <li>{{ $msg }}</li> @endforeach</ul>
    </div>
  @endif

  @if($date->isSaturday())
    <div class="alert alert-info">
      <i class="bi bi-calendar-week me-1"></i>Hoy es sábado de pago. Primero marca los pagos a los barberos en el
      <a href="{{ route('semana', ['fecha' => $date->toDateString()]) }}">cierre semanal</a> (7:00 PM) y después cuenta la caja,
      para que los pagos en efectivo ya estén descontados.
    </div>
  @endif

  @if($s['pending'])
    <div class="alert alert-warning">
      Quedan <strong>{{ $s['pending'] }}</strong> {{ $s['pending'] == 1 ? 'cita pendiente' : 'citas pendientes' }} sin cobrar ese día.
      <a href="{{ route('agenda', ['fecha' => $date->toDateString()]) }}">Revisar en la agenda</a>
    </div>
  @endif

  @if($s['byMethod']['sin_metodo']['amount'] > 0)
    <div class="alert alert-info">
      Hay {{ $fmt($s['byMethod']['sin_metodo']['amount']) }} cobrados sin método de pago (de antes de que existieran los métodos).
      Se suman al total, pero no a efectivo, SINPE ni tarjeta.
    </div>
  @endif

  <div class="row row-cols-2 row-cols-lg-4 g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n">{{ $s['cuts'] }}</div><div class="stat-l">Cortes realizados</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($s['services']) }}</div><div class="stat-l">Producido</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($s['tips']) }}</div><div class="stat-l">Propinas</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($total) }}</div><div class="stat-l">Total cobrado</div></div></div>
  </div>

  <div class="row row-cols-3 g-2 mb-4">
    @foreach(['efectivo' => ['Efectivo', 'cash'], 'sinpe' => ['SINPE', 'phone'], 'tarjeta' => ['Tarjeta', 'credit-card']] as $k => [$label, $icon])
      <div class="col"><div class="stat">
        <div class="stat-l"><i class="bi bi-{{ $icon }} me-1"></i>{{ $label }}</div>
        <div class="stat-n fs-5">{{ $fmt($s['byMethod'][$k]['amount']) }}</div>
        <div class="stat-l">{{ $s['byMethod'][$k]['count'] }} {{ $s['byMethod'][$k]['count'] == 1 ? 'corte' : 'cortes' }}</div>
      </div></div>
    @endforeach
  </div>

  <div class="row g-3 mb-4">

    {{-- Caja de efectivo --}}
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Caja de efectivo</div>
        <div class="card-body">

          @if($closing)
            <div class="client-info mb-3">
              <div class="fw-bold"><i class="bi bi-check-circle-fill text-success me-1"></i>Cierre guardado</div>
              <div class="small text-muted">{{ $closing->closer?->name ?? 'Usuario eliminado' }} · {{ $closing->updated_at->translatedFormat('j M, g:i A') }}</div>
              <div class="fw-bold {{ $diffCls($closing->difference) }}">{{ $diffText($closing->difference) }}</div>
              <div class="small">Queda en caja: <strong>{{ $fmt($closing->counted_cash) }}</strong> (pasa al día siguiente)</div>
            </div>
            @if($stale)
              <div class="alert alert-warning py-2 small">
                Las citas o movimientos de este día cambiaron después del cierre. Vuelve a contar y guarda para actualizarlo.
              </div>
            @endif
          @endif

          <form method="POST" action="{{ route('caja.store') }}" novalidate>
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">

            <label class="form-label small text-muted mb-1">Caja al empezar el día</label>
            <div class="input-group mb-1">
              <span class="input-group-text">₡</span>
              <input type="number" name="opening_cash" id="opening" class="form-control" min="0" step="1" inputmode="numeric"
                     value="{{ old('opening_cash', $opening) }}" required>
            </div>
            <div class="form-text mb-2">
              Calculada con el cierre anterior: {{ $fmt($computedOpening) }}. Puedes corregirla si hace falta.
            </div>

            <table class="table table-sm mb-2">
              <tr><td>+ Efectivo cobrado hoy</td><td class="text-end">{{ $fmt($cashSales) }}</td></tr>
              <tr><td>± Movimientos de caja</td>
                  <td class="text-end {{ $m['net'] < 0 ? 'text-danger' : '' }}">{{ $fmt($m['net']) }}</td></tr>
              <tr class="fw-bold"><td>= Esperado en caja</td><td class="text-end" id="expected">{{ $fmt($expected) }}</td></tr>
            </table>

            <label class="form-label small text-muted mb-1">Efectivo contado en caja</label>
            <div class="input-group mb-1">
              <span class="input-group-text">₡</span>
              <input type="number" name="counted_cash" id="counted" class="form-control" min="0" step="1" inputmode="numeric"
                     value="{{ old('counted_cash', $closing?->counted_cash) }}" required>
            </div>
            <div id="diff" class="fw-bold mb-3" style="min-height:1.5rem"></div>

            <label class="form-label small text-muted mb-1">Notas (opcional)</label>
            <textarea name="notes" rows="2" maxlength="500" class="form-control mb-3"
                      placeholder="Ej: faltó cambio, se dejó para el día siguiente">{{ old('notes', $closing?->notes) }}</textarea>

            <button class="btn btn-dark w-100">{{ $closing ? 'Actualizar cierre' : 'Guardar cierre' }}</button>
          </form>
        </div>
      </div>
    </div>

    {{-- Movimientos --}}
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Movimientos de caja del día</div>
        <div class="card-body">
          @forelse($m['items'] as $it)
            <div class="d-flex align-items-center gap-2 py-1 border-bottom">
              <span class="badge bg-secondary">{{ $it['kind'] }}</span>
              <span class="flex-grow-1 text-truncate">{{ $it['label'] }}</span>
              <strong class="{{ $it['amount'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($it['amount']) }}</strong>
              @if($it['entry_id'])
                <form method="POST" action="{{ route('caja.mov.destroy', $it['entry_id']) }}" onsubmit="return confirm('¿Eliminar este movimiento?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" aria-label="Eliminar"><i class="bi bi-trash3"></i></button>
                </form>
              @endif
            </div>
          @empty
            <p class="text-muted small mb-2">Sin movimientos. Los gastos pagados con la caja y los pagos a barberos en efectivo aparecen aquí solos.</p>
          @endforelse

          <form method="POST" action="{{ route('caja.mov.store') }}" class="mt-3" novalidate>
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <div class="row g-2">
              <div class="col-12">
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="type" id="tOut" value="salida" checked>
                  <label class="btn btn-outline-dark" for="tOut">Salida (retiro)</label>
                  <input type="radio" class="btn-check" name="type" id="tIn" value="entrada">
                  <label class="btn btn-outline-dark" for="tIn">Entrada (aporte)</label>
                </div>
              </div>
              <div class="col-5">
                <div class="input-group">
                  <span class="input-group-text">₡</span>
                  <input type="number" name="amount" class="form-control" min="1" step="1" inputmode="numeric" placeholder="Monto" required>
                </div>
              </div>
              <div class="col-7"><input name="concept" class="form-control" maxlength="80" placeholder="Concepto (ej: retiro del jefe)" required></div>
              <div class="col-12"><button class="btn btn-outline-dark w-100"><i class="bi bi-plus-lg me-1"></i>Registrar movimiento</button></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-5">
      <h2 class="h6 mb-2">Cortes por servicio</h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-dark"><tr><th>Servicio</th><th class="text-end">Cantidad</th><th class="text-end">Monto</th></tr></thead>
          <tbody>
            @forelse($s['byService'] as $r)
              <tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['count'] }}</td><td class="text-end">{{ $fmt($r['amount']) }}</td></tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted">Sin servicios cobrados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-7">
      <h2 class="h6 mb-2">Por barbero</h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-dark">
            <tr><th>Barbero</th><th class="text-end">Cortes</th><th class="text-end">Producido</th>
                <th class="text-end">Gana barbero</th><th class="text-end">Propinas</th><th class="text-end">Gana jefe</th></tr>
          </thead>
          <tbody>
            @forelse($s['byBarber'] as $r)
              <tr>
                <td>{{ $r['name'] }}</td><td class="text-end">{{ $r['cuts'] }}</td>
                <td class="text-end">{{ $fmt($r['total']) }}</td><td class="text-end fw-bold">{{ $fmt($r['barber']) }}</td>
                <td class="text-end">{{ $fmt($r['tips']) }}</td><td class="text-end">{{ $fmt($r['owner']) }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">Sin servicios cobrados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <h2 class="h6 mb-2">Historial de caja (últimos cierres)</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead class="table-dark">
        <tr><th>Día</th><th class="text-end">Empezó con</th><th class="text-end">Efectivo</th>
            <th class="text-end">Movimientos</th><th class="text-end">Contado</th><th class="text-end">Diferencia</th></tr>
      </thead>
      <tbody>
        @forelse($history as $h)
          <tr>
            <td><a href="{{ route('caja', ['fecha' => $h->date->toDateString()]) }}">{{ $h->date->translatedFormat('D j M') }}</a></td>
            <td class="text-end">{{ $fmt($h->opening_cash) }}</td>
            <td class="text-end">{{ $fmt($h->expected_cash) }}</td>
            <td class="text-end">{{ $fmt($h->cash_moves) }}</td>
            <td class="text-end fw-bold">{{ $fmt($h->counted_cash) }}</td>
            <td class="text-end {{ $diffCls($h->difference) }}">{{ $diffText($h->difference) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">Todavía no hay cierres guardados.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sales   = @json($cashSales);
  const moves   = @json($m['net']);
  const opening = document.getElementById('opening');
  const counted = document.getElementById('counted');
  const out     = document.getElementById('diff');
  const exp     = document.getElementById('expected');
  const fmt = n => (n < 0 ? '-' : '') + '₡' + String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  const update = () => {
    const expected = Number(opening.value || 0) + sales + moves;
    exp.textContent = fmt(expected);
    if (counted.value === '') { out.textContent = ''; return; }
    const d = Number(counted.value) - expected;
    out.className = 'fw-bold mb-3 ' + (d === 0 ? 'text-success' : d > 0 ? 'text-warning' : 'text-danger');
    out.textContent = d === 0 ? 'Cuadra' : (d > 0 ? 'Sobra ' : 'Falta ') + fmt(Math.abs(d));
  };

  opening.addEventListener('input', update);
  counted.addEventListener('input', update);
  update();
});
</script>
@endpush