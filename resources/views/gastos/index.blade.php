@extends('layouts.app')

@section('content')
@php
  $fmt = fn ($n) => ($n < 0 ? '-' : '') . '₡' . number_format(abs($n), 0, ',', '.');
@endphp
<div class="container-xxl" style="max-width: 960px">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <h1 class="h4 m-0">Gastos del local</h1>
    <div class="d-flex align-items-center gap-1">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('gastos.index', ['mes' => $prev]) }}" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></a>
      <input type="month" class="form-control form-control-sm w-auto" value="{{ $month->format('Y-m') }}"
             onchange="if (this.value) location='{{ route('gastos.index') }}?mes=' + this.value">
      <a class="btn btn-outline-dark btn-sm" href="{{ route('gastos.index', ['mes' => $next]) }}" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0 ps-3">@foreach($errors->all() as $msg) <li>{{ $msg }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="row row-cols-3 g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($owner) }}</div><div class="stat-l">Parte del jefe ({{ $month->translatedFormat('F') }})</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $fmt($total) }}</div><div class="stat-l">Gastos del mes</div></div></div>
    <div class="col"><div class="stat">
      <div class="stat-n fs-5 {{ $profit < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($profit) }}</div>
      <div class="stat-l">Utilidad del mes</div>
    </div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">Nuevo gasto</div>
        <div class="card-body">
          <form method="POST" action="{{ route('gastos.store') }}" novalidate>
            @csrf
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small text-muted mb-1">Fecha</label>
                <input type="date" name="date" class="form-control" max="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required>
              </div>
              <div class="col-6">
                <label class="form-label small text-muted mb-1">Categoría</label>
                <select name="category" class="form-select" required>
                  @foreach(\App\Models\Expense::CATEGORIES as $k => $label)
                    <option value="{{ $k }}" @selected(old('category') === $k)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-12">
                <input name="concept" class="form-control" maxlength="80" placeholder="Concepto (ej: recibo de luz)" value="{{ old('concept') }}" required>
              </div>
              <div class="col-12">
                <div class="input-group">
                  <span class="input-group-text">₡</span>
                  <input type="number" name="amount" class="form-control" min="1" step="1" inputmode="numeric" placeholder="Monto" value="{{ old('amount') }}" required>
                </div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="from_cash" id="fromCash" value="1" @checked(old('from_cash'))>
                  <label class="form-check-label" for="fromCash">Se pagó con dinero de la caja</label>
                </div>
              </div>
              <div class="col-12"><button class="btn btn-dark w-100">Guardar gasto</button></div>
            </div>
          </form>
        </div>
      </div>

      @if($byCategory->isNotEmpty())
        <div class="card mt-3">
          <div class="card-header">Por categoría</div>
          <div class="card-body py-2">
            @foreach($byCategory as $cat => $sum)
              <div class="d-flex justify-content-between py-1">
                <span>{{ \App\Models\Expense::CATEGORIES[$cat] ?? ucfirst($cat) }}</span>
                <strong>{{ $fmt($sum) }}</strong>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-7">
      <h2 class="h6 mb-2">Gastos de {{ $month->translatedFormat('F Y') }}</h2>
      @forelse($expenses as $e)
        <div class="appt-row appt-cancelada">
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-bold text-truncate">{{ $e->concept }}</div>
            <div class="small text-muted">
              {{ $e->date->translatedFormat('D j M') }} · {{ \App\Models\Expense::CATEGORIES[$e->category] ?? $e->category }}
              @if($e->from_cash) · <span class="badge bg-secondary">De la caja</span> @endif
            </div>
          </div>
          <div class="fw-bold">{{ $fmt($e->amount) }}</div>
          <form method="POST" action="{{ route('gastos.destroy', $e) }}" onsubmit="return confirm('¿Eliminar este gasto?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" aria-label="Eliminar gasto"><i class="bi bi-trash3"></i></button>
          </form>
        </div>
      @empty
        <div class="alert alert-light border">No hay gastos registrados en este mes.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection