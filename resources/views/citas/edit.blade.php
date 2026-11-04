@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 520px;">
  <h4 class="mb-3">Editar cita</h4>

  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0 ps-3">@foreach($errors->all() as $msg) <li>{{ $msg }}</li> @endforeach</ul>
    </div>
  @endif

  <form method="POST" id="formEdit" action="{{ route('citas.update', $appointment) }}" novalidate>
    @csrf @method('PUT')

    <div class="row g-2">
      <div class="col-12">
        <div class="client-info d-flex align-items-center gap-2">
          <span class="avatar">{{ mb_strtoupper(mb_substr($appointment->client_name, 0, 1)) }}</span>
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-bold text-truncate">{{ $appointment->client_name }} {{ $appointment->client_last_name }}</div>
            <div class="small text-muted">+506 {{ substr($appointment->phone, 0, 4) }}-{{ substr($appointment->phone, 4) }}</div>
          </div>
          @if($appointment->client_id)
            <a class="btn btn-sm btn-outline-dark" href="{{ route('clientes.show', $appointment->client_id) }}">Ver ficha</a>
          @endif
        </div>
        <div class="form-text">Para cambiar el nombre o el teléfono, edita la ficha del cliente.</div>
      </div>

      <div class="col-12 col-sm-6">
        <label class="form-label small text-muted mb-1">Barbero</label>
        <select name="barber_id" class="form-select" required>
          @foreach($barbers as $b)
            <option value="{{ $b->id }}" @selected(old('barber_id', $appointment->barber_id) == $b->id)>{{ $b->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-6">
        <label class="form-label small text-muted mb-1">Servicio</label>
        <select name="service_id" class="form-select" required>
          @foreach($services as $s)
            <option value="{{ $s->id }}" @selected(old('service_id', $appointment->service_id) == $s->id)>
              {{ $s->name }} - ₡{{ number_format($s->price, 0, ',', '.') }} ({{ $s->duration_minutes }} min)
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-12">
        <label class="form-label small text-muted mb-1">Fecha</label>
        <input type="date" name="date" class="form-control"
               value="{{ old('date', $appointment->starts_at->toDateString()) }}" required>
      </div>
      <div class="col-12">
        <label class="form-label small text-muted mb-1">Hora</label>
        @include('citas._time_picker', ['value' => old('time', $appointment->starts_at->format('H:i'))])
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-dark">Guardar cambios</button>
      <a href="{{ route('agenda', ['fecha' => $appointment->starts_at->toDateString()]) }}" class="btn btn-outline-dark">Cancelar</a>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('formEdit');
  const tp = window.bbTimePicker(form, {
    url: @json(route('citas.horarios')),
    exclude: {{ $appointment->id }},
  });
  ['barber_id', 'service_id', 'date'].forEach(n =>
    form.elements[n].addEventListener('change', () => tp.refresh()));

  form.addEventListener('submit', e => {
    if (!form.elements.time.value) {
      e.preventDefault();
      alert('Elige una hora.');
    }
  });

  tp.setTime(form.elements.time.value);
});
</script>
@endpush