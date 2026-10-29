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
                {{ $a->service->name ?? 'Servicio eliminado' }}
                ·
                {{ $a->service->name ?? 'Servicio no disponible' }}
                ·
                {{ $a->barber?->name ?? 'Barbero eliminado' }}
                @if($a->barber && $a->barber->trashed())
                    <span class="text-danger">(Barbero eliminado)</span>
                @endif
                · ₡{{ number_format($a->price, 0, ',', '.') }}·
                ₡{{ number_format($a->price, 0, ',', '.') }}             
                </div>

              @if($a->payment_method)
                <div class="small">Pagó con <strong>{{ ucfirst($a->payment_method) }}</strong>
                  @if($a->tip) · Propina ₡{{ number_format($a->tip, 0, ',', '.') }} @endif
                </div>
              @endif
              <div class="small">
                <a href="https://wa.me/506{{ $a->phone }}?text={{ urlencode('Hola ' . $a->client_name . ', le recordamos su cita en la barbería hoy a las ' . $a->starts_at->format('g:i A')) }}" target="_blank">
                  WhatsApp +506 {{ substr($a->phone, 0, 4) }}-{{ substr($a->phone, 4) }}
                </a>
              </div>
            </div>

            @if($a->status === 'pendiente')
            <div class="d-flex flex-wrap gap-1">
              @if($a->status === 'pendiente')
                <button class="btn btn-sm btn-success btn-listo" type="button"
                        data-action="{{ route('citas.estado', $a) }}"
                        data-nombre="{{ $a->client_name }} {{ $a->client_last_name }}"
                        data-precio="{{ $a->price }}">Listo</button>

                <a href="{{ route('citas.edit', $a) }}" class="btn btn-sm btn-outline-dark">Editar</a>

                @foreach(['no_asistio' => ['No vino', 'outline-danger'], 'cancelada' => ['Cancelar', 'outline-secondary']] as $st => [$label, $color])
                  <form method="POST" action="{{ route('citas.estado', $a) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $st }}">
                    <button class="btn btn-sm btn-{{ $color }}">{{ $label }}</button>
                  </form>
                @endforeach

                <form method="POST" action="{{ route('citas.destroy', $a) }}"
                      onsubmit="return confirm('¿Eliminar esta cita?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-danger">Eliminar</button>
                </form>
              @else
                <form method="POST" action="{{ route('citas.estado', $a) }}"
                      onsubmit="return confirm('¿Reabrir la cita? Se borrará el pago registrado.')">
                  @csrf @method('PATCH')
                  <input type="hidden" name="status" value="pendiente">
                  <button class="btn btn-sm btn-outline-warning">Reabrir</button>
                </form>
              @endif
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

<div class="modal fade" id="modalPago" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" id="formPago" class="modal-content">
      @csrf @method('PATCH')
      <input type="hidden" name="status" value="completada">
      <div class="modal-header">
        <h5 class="modal-title">Cobrar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1 fw-bold" id="pagoNombre"></p>
        <p class="text-muted" id="pagoPrecio"></p>

        <label class="form-label">Método de pago</label>
        <div class="btn-group w-100 mb-3" role="group">
          <input type="radio" class="btn-check" name="payment_method" id="pm1" value="efectivo" required>
          <label class="btn btn-outline-dark" for="pm1">Efectivo</label>
          <input type="radio" class="btn-check" name="payment_method" id="pm2" value="sinpe">
          <label class="btn btn-outline-dark" for="pm2">SINPE</label>
          <input type="radio" class="btn-check" name="payment_method" id="pm3" value="tarjeta">
          <label class="btn btn-outline-dark" for="pm3">Tarjeta</label>
        </div>

        <label class="form-label">Propina (opcional)</label>
        <div class="input-group">
          <span class="input-group-text">₡</span>
          <input type="number" name="tip" class="form-control" min="0" step="100" inputmode="numeric" value="0">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success w-100">Confirmar cobro</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.querySelectorAll('.btn-listo').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('formPago').action = btn.dataset.action;
      document.getElementById('pagoNombre').textContent = btn.dataset.nombre;
      document.getElementById('pagoPrecio').textContent =
        'Total: ₡' + Number(btn.dataset.precio).toLocaleString('es-CR');
      new bootstrap.Modal(document.getElementById('modalPago')).show();
    });
  });
</script>
@endsection