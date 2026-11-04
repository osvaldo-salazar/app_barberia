@extends('layouts.app')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="container-xxl" style="max-width: 960px">
  <h1 class="h4 mb-3">Bloqueos de horario</h1>

  @if(session('ok'))   <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if(session('warn')) <div class="alert alert-warning">{{ session('warn') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0 ps-3">@foreach($errors->all() as $msg) <li>{{ $msg }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header">Nuevo bloqueo</div>
    <div class="card-body">

      <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-dark" data-preset="lunch"><i class="bi bi-cup-hot me-1"></i>Almuerzo</button>
        <button type="button" class="btn btn-sm btn-outline-dark" data-preset="off"><i class="bi bi-calendar-x me-1"></i>Día libre</button>
        <button type="button" class="btn btn-sm btn-outline-dark" data-preset="vac"><i class="bi bi-sun me-1"></i>Vacaciones</button>
      </div>

      <form method="POST" action="{{ route('bloqueos.store') }}" id="formBlock">
        @csrf
        <div class="row g-2">
          <div class="col-12 col-md-6">
            <label class="form-label small text-muted mb-1">Barbero</label>
            <select name="barber_id" class="form-select">
              @if($isAdmin) <option value="">Toda la barbería</option> @endif
              @foreach($barbers as $b)
                <option value="{{ $b->id }}" @selected(old('barber_id') == $b->id)>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted mb-1">Tipo</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="mode" id="modeDay" value="day" @checked(old('mode', 'day') === 'day')>
              <label class="btn btn-outline-dark" for="modeDay">Día completo</label>
              <input type="radio" class="btn-check" name="mode" id="modeHours" value="hours" @checked(old('mode') === 'hours')>
              <label class="btn btn-outline-dark" for="modeHours">Por horas</label>
            </div>
          </div>

          <div class="col-6">
            <label class="form-label small text-muted mb-1">Desde</label>
            <input type="date" name="from" class="form-control" value="{{ old('from', today()->toDateString()) }}" required>
          </div>
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Hasta (opcional)</label>
            <input type="date" name="to" class="form-control" value="{{ old('to') }}">
          </div>

          <div class="col-6" data-hours>
            <label class="form-label small text-muted mb-1">Hora inicial</label>
            <input type="time" name="start_time" class="form-control" min="10:00" max="18:45" step="900" value="{{ old('start_time') }}">
          </div>
          <div class="col-6" data-hours>
            <label class="form-label small text-muted mb-1">Hora final</label>
            <input type="time" name="end_time" class="form-control" min="10:15" max="19:00" step="900" value="{{ old('end_time') }}">
          </div>

          <div class="col-12">
            <label class="form-label small text-muted mb-1">Motivo (opcional)</label>
            <input name="reason" class="form-control" maxlength="80" placeholder="Ej: Almuerzo, cita médica, vacaciones" value="{{ old('reason') }}">
          </div>
        </div>

        <div class="form-text my-2">
          Por horas con un rango de fechas crea el mismo bloqueo cada día (por ejemplo, almuerzo de lunes a domingo).
        </div>
        <button class="btn btn-dark">Guardar bloqueo</button>
      </form>
    </div>
  </div>

  <h2 class="h6 mb-2">Bloqueos activos y próximos</h2>

  @forelse($blocks as $b)
    <div class="appt-row appt-cancelada">
      <div class="flex-grow-1" style="min-width: 0">
        <div class="fw-bold text-truncate">{{ $b->reason ?: 'Bloqueado' }} · {{ $b->barber?->name ?? 'Toda la barbería' }}</div>
        <div class="small text-muted">
          @if($b->all_day)
            @if($b->starts_at->isSameDay($b->ends_at))
              {{ $b->starts_at->translatedFormat('D j M Y') }} · día completo
            @else
              Del {{ $b->starts_at->translatedFormat('D j M') }} al {{ $b->ends_at->translatedFormat('D j M Y') }} · día completo
            @endif
          @else
            {{ $b->starts_at->translatedFormat('D j M Y') }} · {{ $b->starts_at->format('g:i A') }} - {{ $b->ends_at->format('g:i A') }}
          @endif
        </div>
      </div>
      <form method="POST" action="{{ route('bloqueos.destroy', $b) }}"
            onsubmit="return confirm('¿Eliminar este bloqueo?')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger" aria-label="Eliminar bloqueo"><i class="bi bi-trash3"></i></button>
      </form>
    </div>
  @empty
    <div class="alert alert-light border">No hay bloqueos. Usa los atajos de arriba para crear uno rápido.</div>
  @endforelse
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('formBlock');
  const hourBoxes = f.querySelectorAll('[data-hours]');

  function syncMode() {
    const hours = f.querySelector('[name=mode]:checked').value === 'hours';
    hourBoxes.forEach(b => {
      b.hidden = !hours;
      b.querySelectorAll('input').forEach(i => i.disabled = !hours);   // los deshabilitados no se envían
    });
  }
  f.querySelectorAll('[name=mode]').forEach(r => r.addEventListener('change', syncMode));

  const presets = {
    lunch: { mode: 'hours', start: '12:00', end: '13:00', reason: 'Almuerzo' },
    off:   { mode: 'day',   reason: 'Día libre' },
    vac:   { mode: 'day',   reason: 'Vacaciones' },
  };

  document.querySelectorAll('[data-preset]').forEach(b => b.addEventListener('click', () => {
    const p = presets[b.dataset.preset];
    f.querySelector(p.mode === 'hours' ? '#modeHours' : '#modeDay').checked = true;
    f.elements.reason.value = p.reason;
    if (p.start) { f.elements.start_time.value = p.start; f.elements.end_time.value = p.end; }
    syncMode();
  }));

  syncMode();
});
</script>
@endpush