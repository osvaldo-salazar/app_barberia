@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 520px;">
  <h4 class="mb-3">Editar cita</h4>

  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <form method="POST" action="{{ route('citas.update', $appointment) }}">
    @csrf @method('PUT')

    <div class="mb-2">
      <input name="client_name" class="form-control" placeholder="Nombre"
             value="{{ old('client_name', $appointment->client_name) }}" required>
    </div>
    <div class="mb-2">
      <input name="client_last_name" class="form-control" placeholder="Apellido"
             value="{{ old('client_last_name', $appointment->client_last_name) }}" required>
    </div>
    <div class="input-group mb-2">
      <span class="input-group-text">+506</span>
      <input name="phone" class="form-control" inputmode="numeric" maxlength="8"
             value="{{ old('phone', $appointment->phone) }}" required>
    </div>
    <div class="mb-2">
      <select name="barber_id" class="form-select" required>
        @foreach($barbers as $b)
          <option value="{{ $b->id }}" @selected(old('barber_id', $appointment->barber_id) == $b->id)>{{ $b->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="mb-2">
      <select name="service_id" class="form-select" required>
        @foreach($services as $s)
          <option value="{{ $s->id }}" @selected(old('service_id', $appointment->service_id) == $s->id)>
            {{ $s->name }} - ₡{{ number_format($s->price, 0, ',', '.') }} ({{ $s->duration_minutes }} min)
          </option>
        @endforeach
      </select>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-6">
        <input type="date" name="date" class="form-control"
               value="{{ old('date', $appointment->starts_at->toDateString()) }}" required>
      </div>
      <div class="col-6">
        <select name="time" class="form-select" required>
          @foreach($slots as $slot)
            <option value="{{ $slot }}" @selected(old('time', $appointment->starts_at->format('H:i')) == $slot)>
              {{ \Carbon\Carbon::createFromFormat('H:i', $slot)->format('g:i A') }}
            </option>
          @endforeach
        </select>
      </div>
    </div>

    <button class="btn btn-dark">Guardar cambios</button>
    <a href="{{ route('agenda', ['fecha' => $appointment->starts_at->toDateString()]) }}" class="btn btn-outline-secondary">Cancelar</a>
  </form>
</div>
@endsection