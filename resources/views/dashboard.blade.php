@extends('layouts.app')

@section('content')
@php $fmt = fn ($n) => ($n < 0 ? '-' : '') . '₡' . number_format(abs($n), 0, ',', '.'); @endphp
<div class="container-xxl" style="max-width: 1100px">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <h1 class="h4 m-0">Dashboard</h1>
    <div class="btn-group" role="group" aria-label="Período">
      @foreach([4, 8, 12] as $w)
        <a href="{{ route('dashboard', ['semanas' => $w]) }}"
           class="btn btn-sm {{ $weeks === $w ? 'btn-dark' : 'btn-outline-dark' }}">{{ $w }} semanas</a>
      @endforeach
    </div>
  </div>

  <div class="row row-cols-2 row-cols-lg-4 g-2 mb-3">
    <div class="col"><div class="stat">
      <div class="stat-n fs-5">{{ $fmt($kpi['week']) }}</div>
      <div class="stat-l">
        Producido esta semana
        @if($kpi['delta'] !== null)
          <span class="{{ $kpi['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
            <i class="bi bi-{{ $kpi['delta'] >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>{{ abs($kpi['delta']) }}%
          </span>
        @endif
      </div>
    </div></div>
    <div class="col"><div class="stat">
      <div class="stat-n fs-5 {{ $kpi['profit'] < 0 ? 'text-danger' : '' }}">{{ $fmt($kpi['profit']) }}</div>
      <div class="stat-l">Utilidad esta semana</div>
    </div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $kpi['cuts'] }}</div><div class="stat-l">Cortes en {{ $weeks }} semanas</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($kpi['average']) }}</div><div class="stat-l">Ticket promedio</div></div></div>
  </div>

  @if($kpi['cuts'] === 0)
    <div class="alert alert-light border">Todavía no hay cortes cobrados en este período. Los gráficos aparecen cuando marques citas como cobradas.</div>
  @endif

  <div class="row g-3">
    <div class="col-12">
      <div class="card"><div class="card-header">Ingresos por semana</div>
        <div class="card-body"><div class="chart-box"><canvas id="chWeeks"></canvas></div></div></div>
    </div>
    <div class="col-lg-6">
      <div class="card"><div class="card-header">Barberos con más servicios</div>
        <div class="card-body"><div class="chart-box"><canvas id="chBarbers"></canvas></div></div></div>
    </div>
    <div class="col-lg-6">
      <div class="card"><div class="card-header">Servicios más pedidos</div>
        <div class="card-body"><div class="chart-box"><canvas id="chServices"></canvas></div></div></div>
    </div>
    <div class="col-lg-6">
      <div class="card"><div class="card-header">Horas más llenas</div>
        <div class="card-body"><div class="chart-box"><canvas id="chHours"></canvas></div></div></div>
    </div>
    <div class="col-lg-6">
      <div class="card"><div class="card-header">Días más llenos</div>
        <div class="card-body"><div class="chart-box"><canvas id="chDays"></canvas></div></div></div>
    </div>
    <div class="col-lg-6">
      <div class="card"><div class="card-header">Cómo te pagan</div>
        <div class="card-body"><div class="chart-box"><canvas id="chMethods"></canvas></div></div></div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const { initDashboard } = await window.bbDashboard();
  initDashboard(@json($data));
});
</script>
@endpush