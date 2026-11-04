@extends('layouts.app')

@section('content')
@php
  $fmt      = fn ($n) => '₡' . number_format($n, 0, ',', '.');
  $expected = $s['byMethod']['efectivo'];
  $total    = $s['services'] + $s['tips'];
  $prev     = $date->copy()->subDay()->toDateString();
  $next     = $date->copy()->addDay()->toDateString();
  $diffText = fn ($d) => $d == 0 ? 'Cuadra' : ($d > 0 ? 'Sobra ' . $fmt($d) : 'Falta ' . $fmt(abs($d)));
  $diffCls  = fn ($d) => $d == 0 ? 'text-success' : ($d > 0 ? 'text-warning' : 'text-danger');
@endphp

<div class="container-xxl" style="max-width: 960px">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <div>
      <h1 class="h4 m-0">Cierre de caja</h1>
      <div class="text-muted text-capitalize small">{{ $date->translatedFormat('l j \d\e F, Y') }}</div>
    </div>
    <div class="d-flex align-items-center gap-1">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('caja', ['fecha' => $prev]) }}" aria-label="Día anterior"><i class="bi bi-chevron-left"></i></a>
      <input type="date" class="form-control form-control-sm w-auto" value="{{ $date->toDateString() }}"
             max="{{ today()->toDateString() }}"
             onchange="location='{{ route('caja') }}?fecha=' + this.value">
      @if($date->lt(today()))
        <a class="btn btn-outline-dark btn-sm" href="{{ route('caja', ['fecha' => $next]) }}" aria-label="Día siguiente"><i class="bi bi-chevron-right"></i></a>
      @endif
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0 ps-3">@foreach($errors->all() as $msg) <li>{{ $msg }}</li> @endforeach</ul>
    </div>
  @endif

  @if($s['pending'])
    <div class="alert alert-warning">
      Quedan <strong>{{ $s['pending'] }}</strong> {{ $s['pending'] == 1 ? 'cita pendiente' : 'citas pendientes' }} sin cobrar ese día.
      <a href="{{ route('agenda', ['fecha' => $date->toDateString()]) }}">Revisar en la agenda</a>
    </div>
  @endif

  @if($s['byMethod']['sin_metodo'] > 0)
    <div class="alert alert-info">
      Hay {{ $fmt($s['byMethod']['sin_metodo']) }} cobrados sin método de pago (citas anteriores a que existieran los métodos).
      Se suman al total pero no a efectivo, SINPE ni tarjeta.
    </div>
  @endif

  <div class="row row-cols-2 row-cols-lg-4 g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n">{{ $s['count'] }}</div><div class="stat-l">Servicios cobrados</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($s['services']) }}</div><div class="stat-l">Producido</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($s['tips']) }}</div><div class="stat-l">Propinas</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($total) }}</div><div class="stat-l">Total cobrado</div></div></div>
  </div>

  <div class="row row-cols-3 g-2 mb-4">
    @foreach(['efectivo' => ['Efectivo', 'cash'], 'sinpe' => ['SINPE', 'phone'], 'tarjeta' => ['Tarjeta', 'credit-card']] as $k => [$label, $icon])
      <div class="col"><div class="stat">
        <div class="stat-l"><i class="bi bi-{{ $icon }} me-1"></i>{{ $label }}</div>
        <div class="stat-n fs-5">{{ $fmt($s['byMethod'][$k]) }}</div>
      </div></div>
    @endforeach
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">Contar el efectivo</div>
        <div class="card-body">

          @if($closing)
            <div class="client-info mb-3">
              <div class="fw-bold"><i class="bi bi-check-circle-fill text-success me-1"></i>Cierre guardado</div>
              <div class="small text-muted">
                {{ $closing->closer?->name ?? 'Usuario eliminado' }} · {{ $closing->updated_at->translatedFormat('j M, g:i A') }}
              </div>
              <div class="fw-bold {{ $diffCls($closing->difference) }}">{{ $diffText($closing->difference) }}</div>
            </div>
            @if($stale)
              <div class="alert alert-warning py-2 small">
                Las citas de este día cambiaron después del cierre. Vuelve a contar y guarda para actualizarlo.
              </div>
            @endif
          @endif

          <form method="POST" action="{{ route('caja.store') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">

            <p class="mb-2">Efectivo esperado: <strong>{{ $fmt($expected) }}</strong></p>

            <label class="form-label small text-muted mb-1">Efectivo contado en caja</label>
            <div class="input-group mb-1">
              <span class="input-group-text">₡</span>
              <input type="number" name="counted_cash" id="counted" class="form-control" min="0" step="1" inputmode="numeric"
                     value="{{ old('counted_cash', $closing?->counted_cash) }}" required>
            </div>
            <div id="diff" class="fw-bold mb-3" style="min-height:1.5rem"></div>

            <label class="form-label small text-muted mb-1">Notas (opcional)</label>
            <textarea name="notes" rows="2" maxlength="500" class="form-control mb-3"
                      placeholder="Ej: se sacó ₡5.000 para productos">{{ old('notes', $closing?->notes) }}</textarea>

            <button class="btn btn-dark w-100">{{ $closing ? 'Actualizar cierre' : 'Guardar cierre' }}</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <h2 class="h6 mb-2">Por barbero</h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-dark">
            <tr>
              <th>Barbero</th><th class="text-end">Serv.</th><th class="text-end">Producido</th>
              <th class="text-end">Gana barbero</th><th class="text-end">Propinas</th><th class="text-end">Gana jefe</th>
            </tr>
          </thead>
          <tbody>
            @forelse($s['barbers'] as $r)
              <tr>
                <td>{{ $r['name'] }}</td>
                <td class="text-end">{{ $r['cuts'] }}</td>
                <td class="text-end">{{ $fmt($r['total']) }}</td>
                <td class="text-end fw-bold">{{ $fmt($r['barber']) }}</td>
                <td class="text-end">{{ $fmt($r['tips']) }}</td>
                <td class="text-end">{{ $fmt($r['owner']) }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">No hay servicios cobrados este día.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const expected = @json($expected);
  const input = document.getElementById('counted');
  const out = document.getElementById('diff');
  const fmt = n => '₡' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  const update = () => {
    if (input.value === '') { out.textContent = ''; return; }
    const d = Number(input.value) - expected;
    out.className = 'fw-bold mb-3 ' + (d === 0 ? 'text-success' : d > 0 ? 'text-warning' : 'text-danger');
    out.textContent = d === 0 ? 'Cuadra' : (d > 0 ? 'Sobra ' : 'Falta ') + fmt(Math.abs(d));
  };

  input.addEventListener('input', update);
  update();
});
</script>
@endpush