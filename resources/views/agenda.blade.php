@extends('layouts.app')

@section('content')
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <a class="btn btn-outline-secondary" href="{{ route('agenda', ['fecha' => $date->copy()->subDay()->toDateString()]) }}">&laquo;</a>
    <div class="text-center">
      <h4 class="m-0 text-capitalize">{{ $date->translatedFormat('l d \d\e F') }}</h4>
      <a href="{{ route('agenda') }}" class="small">Ir a hoy</a>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('agenda', ['fecha' => $date->copy()->addDay()->toDateString()]) }}">&raquo;</a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <div class="row g-3">

    {{-- Formulario --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header fw-bold">Nueva cita</div>
        <div class="card-body">
          <form method="POST" action="{{ route('citas.store') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">

            <div class="mb-2">
              <input name="client_name" class="form-control" placeholder="Nombre" value="{{ old('client_name') }}" required>
            </div>
            <div class="mb-2">
              <input name="client_last_name" class="form-control" placeholder="Apellido" value="{{ old('client_last_name') }}" required>
            </div>
            <div class="input-group mb-2">
              <span class="input-group-text">+506</span>
              <input name="phone" class="form-control" inputmode="numeric" maxlength="8" placeholder="8888 8888" value="{{ old('phone') }}" required>
            </div>
            <div class="mb-2">
              <select name="barber_id" class="form-select" required>
                @foreach($barbers as $b)
                  <option value="{{ $b->id }}" @selected(old('barber_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-2">
              <select name="service_id" class="form-select" required>
                @foreach($services as $s)
                  <option value="{{ $s->id }}" @selected(old('service_id') == $s->id)>
                    {{ $s->name }} - ₡{{ number_format($s->price, 0, ',', '.') }} ({{ $s->duration_minutes }} min)
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <select name="time" class="form-select" required>
                @foreach($slots as $slot)
                  <option value="{{ $slot }}" @selected(old('time') == $slot)>{{ \Carbon\Carbon::createFromFormat('H:i', $slot)->format('g:i A') }}</option>
                @endforeach
              </select>
            </div>
            <button class="btn btn-dark w-100">Agendar</button>
          </form>
        </div>
      </div>
    </div>

    {{-- Citas del día --}}
    <div class="col-lg-8">
      @forelse($appointments as $a)
        @php
          $badge = match($a->status) {
            'completada' => 'success', 'cancelada' => 'secondary',
            'no_asistio' => 'danger',  default => 'warning',
          };
        @endphp
        <div class="card mb-2">
          <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <div class="fw-bold">
                {{ $a->starts_at->format('g:i A') }} - {{ $a->ends_at->format('g:i A') }}
                <span class="badge bg-{{ $badge }}">{{ str_replace('_', ' ', $a->status) }}</span>
              </div>
              <div>{{ $a->client_name }} {{ $a->client_last_name }}</div>
              <div class="small text-muted">
                {{ $a->service->name }} · {{ $a->barber->name }} · ₡{{ number_format($a->price, 0, ',', '.') }}
              </div>
              <div class="small">
                <a href="https://wa.me/506{{ $a->phone }}?text={{ urlencode('Hola ' . $a->client_name . ', le recordamos su cita en la barbería hoy a las ' . $a->starts_at->format('g:i A')) }}" target="_blank">
                  WhatsApp +506 {{ substr($a->phone, 0, 4) }}-{{ substr($a->phone, 4) }}
                </a>
              </div>
            </div>

            @if($a->status === 'pendiente')
            <div class="d-flex gap-1">
              @foreach(['completada' => ['Listo', 'success'], 'no_asistio' => ['No vino', 'outline-danger'], 'cancelada' => ['Cancelar', 'outline-secondary']] as $st => [$label, $color])
                <form method="POST" action="{{ route('citas.estado', $a) }}">
                  @csrf @method('PATCH')
                  <input type="hidden" name="status" value="{{ $st }}">
                  <button class="btn btn-sm btn-{{ $color }}">{{ $label }}</button>
                </form>
              @endforeach
            </div>
            @endif
          </div>
        </div>
      @empty
        <div class="alert alert-light border">No hay citas para este día.</div>
      @endforelse
    </div>

  </div>
</div>
@endsection