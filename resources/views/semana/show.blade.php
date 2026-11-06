@extends('layouts.app')

@section('content')
@php
  $fmt   = fn ($n) => ($n < 0 ? '-' : '') . '₡' . number_format(abs($n), 0, ',', '.');
  $prev  = $d['start']->copy()->subWeek()->toDateString();
  $next  = $d['start']->copy()->addWeek()->toDateString();
  $thisWeek = $d['start']->isSameDay(now()->startOfWeek(\Carbon\Carbon::SUNDAY));
  $q = ['fecha' => $d['start']->toDateString()];
@endphp

<div class="container-xxl" style="max-width: 1000px">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <div>
      <h1 class="h4 m-0">Cierre semanal</h1>
      <div class="text-muted small">
        Del {{ $d['start']->translatedFormat('D j M') }} al {{ $d['end']->translatedFormat('D j M Y') }}
        · Pago: sábado {{ $d['payAt']->format('j/m') }} a las 7:00 PM
      </div>
    </div>
    <div class="d-flex align-items-center gap-1 flex-wrap">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('semana', ['fecha' => $prev]) }}" aria-label="Semana anterior"><i class="bi bi-chevron-left"></i></a>
      <input type="date" class="form-control form-control-sm w-auto" value="{{ $d['start']->toDateString() }}"
             onchange="if (this.value) location='{{ route('semana') }}?fecha=' + this.value">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('semana', ['fecha' => $next]) }}" aria-label="Semana siguiente"><i class="bi bi-chevron-right"></i></a>
      <a class="btn btn-dark btn-sm" href="{{ route('semana.pdf', $q) }}"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
      <a class="btn btn-outline-dark btn-sm" href="{{ route('semana.csv', $q) }}"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0 ps-3">@foreach($errors->all() as $msg) <li>{{ $msg }}</li> @endforeach</ul>
    </div>
  @endif

  @if($thisWeek)
    <div class="alert alert-info py-2">
      <i class="bi bi-clock me-1"></i>
      {{ $d['payAt']->isPast() ? 'La hora de pago (sábado 7:00 PM) ya pasó.' : 'Hora de pago: ' . $d['payAt']->diffForHumans() . '.' }}
    </div>
  @endif

  @if($d['unclosed'])
    <div class="alert alert-warning py-2">
      Hay <strong>{{ $d['unclosed'] }}</strong> {{ $d['unclosed'] == 1 ? 'cita' : 'citas' }} de la semana sin cobrar ni cerrar.
      Ciérralas en la agenda antes de pagar, porque no suman a las comisiones.
    </div>
  @endif

 @php
    $sinpe = $d['sinpe'] ?? collect();
    $missing = $sinpe->whereNull('proof_path')->count();
@endphp

@if($sinpe->isNotEmpty())
  <div class="alert {{ $missing ? 'alert-warning' : 'alert-info' }} py-2">
    <i class="bi bi-image me-1"></i>SINPE de la semana: {{ $sinpe->count() - $missing }} de {{ $sinpe->count() }} con comprobante.
    @if($missing) Revísalos día por día en el <a href="{{ route('caja') }}">cierre de caja</a>. @endif
  </div>
@endif

  <div class="row row-cols-2 row-cols-lg-5 g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n">{{ $d['totals']['cuts'] }}</div><div class="stat-l">Cortes</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($d['totals']['total']) }}</div><div class="stat-l">Producido</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($d['totals']['owner']) }}</div><div class="stat-l">Parte del jefe</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($d['expTotal']) }}</div><div class="stat-l">Gastos de la semana</div></div></div>
    <div class="col"><div class="stat">
      <div class="stat-n fs-5 {{ $d['profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($d['profit']) }}</div>
      <div class="stat-l">Utilidad del jefe</div>
    </div></div>
  </div>

  <div class="row row-cols-3 g-2 mb-4">
    @foreach(['efectivo' => ['Efectivo', 'cash'], 'sinpe' => ['SINPE', 'phone'], 'tarjeta' => ['Tarjeta', 'credit-card']] as $k => [$label, $icon])
      <div class="col"><div class="stat">
        <div class="stat-l"><i class="bi bi-{{ $icon }} me-1"></i>{{ $label }}</div>
        <div class="stat-n fs-5">{{ $fmt($d['byMethod'][$k]['amount']) }}</div>
        <div class="stat-l">{{ $d['byMethod'][$k]['count'] }} {{ $d['byMethod'][$k]['count'] == 1 ? 'corte' : 'cortes' }}</div>
      </div></div>
    @endforeach
  </div>

  <h2 class="h6 mb-2">Pago a los barberos</h2>
  <div class="table-responsive mb-4">
    <table class="table align-middle">
      <thead class="table-dark">
        <tr><th>Barbero</th><th class="text-end">Cortes</th><th class="text-end">Producido</th>
            <th class="text-end">Comisión</th><th class="text-end">Propinas</th><th class="text-end">A pagar</th><th>Pago</th></tr>
      </thead>
      <tbody>
        @forelse($d['barbers'] as $b)
          @php $p = $b['payout']; @endphp
          <tr>
            <td>
              <a href="#" class="fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#bd-{{ $b['id'] }}">
                {{ $b['name'] }} <i class="bi bi-box-arrow-up-right small"></i>
              </a>
              <div class="small text-muted">{{ $b['services']->map(fn ($x) => $x['name'] . ' ×' . $x['count'])->implode(', ') }}</div>
            </td>            <td class="text-end">{{ $b['cuts'] }}</td>
            <td class="text-end">{{ $fmt($b['total']) }}</td>
            <td class="text-end">{{ $fmt($b['commission']) }}</td>
            <td class="text-end">{{ $fmt($b['tips']) }}</td>
            <td class="text-end fw-bold">{{ $fmt($b['to_pay']) }}</td>
            <td style="min-width: 15rem">
              @if($p)
                <span class="badge st-completada">Pagado</span>
                <span class="small">{{ $p->method === 'sinpe' ? 'SINPE' : 'Efectivo' }} · {{ $p->paid_on->format('d/m') }} · {{ $fmt($p->amount) }}</span>
                <form method="POST" action="{{ route('semana.deshacer', $p) }}" class="d-inline"
                      data-confirm="¿Deshacer este pago? Si fue en efectivo, vuelve a la caja.">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-link text-danger p-0 ms-1">Deshacer</button>
                </form>
                @if($b['changed'])
                  <div class="small text-warning">Ojo: el monto cambió después del pago (ahora sería {{ $fmt($b['to_pay']) }}).</div>
                @endif
              @else
                <form method="POST" action="{{ route('semana.pagar') }}" class="d-flex gap-1"
                      data-confirm="¿Marcar pagado {{ $fmt($b['to_pay']) }} a {{ $b['name'] }}?">
                  @csrf
                  <input type="hidden" name="week" value="{{ $d['start']->toDateString() }}">
                  <input type="hidden" name="barber_id" value="{{ $b['id'] }}">
                  <select name="method" class="form-select form-select-sm" aria-label="Método de pago">
                    <option value="efectivo">Efectivo</option>
                    <option value="sinpe">SINPE</option>
                  </select>
                  <button class="btn btn-sm btn-success text-nowrap">Marcar pagado</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted">Sin servicios cobrados esta semana.</td></tr>
        @endforelse
      </tbody>
      @if($d['barbers']->isNotEmpty())
        <tfoot class="fw-bold">
          <tr>
            <td>TOTAL</td><td class="text-end">{{ $d['totals']['cuts'] }}</td>
            <td class="text-end">{{ $fmt($d['totals']['total']) }}</td>
            <td class="text-end">{{ $fmt($d['totals']['commission']) }}</td>
            <td class="text-end">{{ $fmt($d['totals']['tips']) }}</td>
            <td class="text-end">{{ $fmt($d['totals']['commission'] + $d['totals']['tips']) }}</td><td></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <h2 class="h6 mb-2">Por día</h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-dark"><tr><th>Día</th><th class="text-end">Cortes</th><th class="text-end">Producido</th><th class="text-end">Propinas</th></tr></thead>
          <tbody>
            @foreach($d['days'] as $day)
              <tr>
                <td class="text-capitalize"><a href="{{ route('caja', ['fecha' => $day['date']->toDateString()]) }}">{{ $day['date']->translatedFormat('l j') }}</a></td>
                <td class="text-end">{{ $day['cuts'] }}</td>
                <td class="text-end">{{ $fmt($day['total']) }}</td>
                <td class="text-end">{{ $fmt($day['tips']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-6">
      <h2 class="h6 mb-2">Gastos de la semana</h2>
      @forelse($d['expenses'] as $e)
        <div class="d-flex justify-content-between py-1 border-bottom">
          <span>{{ $e->date->translatedFormat('D j') }} · {{ $e->concept }}</span>
          <strong>{{ $fmt($e->amount) }}</strong>
        </div>
      @empty
        <p class="text-muted small">Sin gastos registrados. <a href="{{ route('gastos.index') }}">Registrar un gasto</a></p>
      @endforelse

      <a href="{{ route('caja') }}" class="btn btn-outline-dark mt-3"><i class="bi bi-receipt me-1"></i>Ir a contar la caja</a>
    </div>
  </div>
</div>

@foreach($d['barbers'] as $b)
  @include('partials.barber-modal', ['b' => $b, 'subtitle' => 'Semana del ' . $d['start']->format('d/m') . ' al ' . $d['end']->format('d/m/Y')])
@endforeach

@endsection