@extends('layouts.app')

@section('content')
@php
  $money   = ! auth()->user()->isReception();
  $isAdmin = auth()->user()->isAdmin();
  $labels  = ['pendiente' => 'Pendiente', 'completada' => 'Completada', 'cancelada' => 'Cancelada', 'no_asistio' => 'No vino'];
@endphp
<div class="container-xxl" style="max-width: 960px">

  <a href="{{ route('clientes.index') }}" class="small"><i class="bi bi-chevron-left"></i> Clientes</a>

  <div class="d-flex align-items-center justify-content-between gap-3 my-3 flex-wrap">
    <div class="d-flex align-items-center gap-3">
      <span class="avatar" style="width:3rem;height:3rem;font-size:1.2rem">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span>
      <div>
        <h1 class="h4 m-0">
          {{ $client->full_name }}
          @if($stats['visits'] >= 5)<i class="bi bi-star-fill text-warning ms-1" title="Cliente frecuente"></i>@endif
        </h1>
        <a href="https://wa.me/506{{ $client->phone }}" target="_blank" rel="noopener">
          <i class="bi bi-whatsapp"></i> +506 {{ $client->phone_formatted }}
        </a>
      </div>
    </div>
    <a href="{{ route('agenda', ['cliente' => $client->id]) }}" class="btn btn-dark">
      <i class="bi bi-plus-lg me-1"></i>Agendar cita
    </a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <div class="row row-cols-2 row-cols-lg-{{ $money ? 5 : 4 }} g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n">{{ $stats['visits'] }}</div><div class="stat-l">Visitas</div></div></div>
    <div class="col"><div class="stat">
      <div class="stat-n fs-6">{{ $stats['last'] ? \Carbon\Carbon::parse($stats['last'])->translatedFormat('j M Y') : '—' }}</div>
      <div class="stat-l">Última visita</div>
    </div></div>
    <div class="col"><div class="stat"><div class="stat-n fs-6">{{ $stats['favorite'] ?? '—' }}</div><div class="stat-l">Servicio favorito</div></div></div>
    @if($money)
      <div class="col"><div class="stat"><div class="stat-n fs-6">₡{{ number_format($stats['spent'], 0, ',', '.') }}</div><div class="stat-l">Total gastado</div></div></div>
    @endif
    <div class="col"><div class="stat"><div class="stat-n">{{ $stats['noshows'] }}</div><div class="stat-l">Veces que no vino</div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">Datos y notas</div>
        <div class="card-body">
          <form method="POST" action="{{ route('clientes.update', $client) }}">
            @csrf @method('PUT')

            <div class="row g-2 mb-2">
              <div class="col-6"><input name="name" class="form-control" placeholder="Nombre" value="{{ old('name', $client->name) }}" required></div>
              <div class="col-6"><input name="last_name" class="form-control" placeholder="Apellido" value="{{ old('last_name', $client->last_name) }}" required></div>
            </div>
            <div class="input-group mb-2">
              <span class="input-group-text">+506</span>
              <input name="phone" class="form-control" inputmode="numeric" maxlength="8" value="{{ old('phone', $client->phone) }}" required>
            </div>
            <label class="form-label small text-muted mb-1">Notas del cliente</label>
            <textarea name="notes" rows="4" maxlength="1000" class="form-control mb-3"
                      placeholder="Ej: degradado 2 a los lados, barba con navaja">{{ old('notes', $client->notes) }}</textarea>

            <button class="btn btn-dark w-100">Guardar</button>
          </form>

          @if($isAdmin)
            <form method="POST" action="{{ route('clientes.destroy', $client) }}" class="mt-3"
                  data-confirm="¿Eliminar a {{ $client->full_name }}? Se pierden sus notas. Sus citas pasadas se conservan.">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash3 me-1"></i>Eliminar cliente</button>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <h2 class="h6 mb-2">Historial</h2>
      @forelse($history as $a)
        <div class="appt-row appt-{{ $a->status }}">
          <div class="flex-grow-1" style="min-width: 0">
            <div class="fw-bold">{{ $a->starts_at->translatedFormat('j M Y') }} · {{ $a->starts_at->format('g:i A') }}</div>
                <div class="small text-muted text-truncate">
                    {{ $a->service?->name ?? 'Servicio eliminado' }}
                    ·
                    {{ $a->barber?->name ?? 'Barbero eliminado' }}
                </div>
          </div>
          <div class="text-end">
            <span class="badge st-{{ $a->status }}">{{ $labels[$a->status] }}</span>
            @if($money && $a->status === 'completada')
              <div class="small text-muted mt-1">₡{{ number_format($a->price, 0, ',', '.') }}</div>
            @endif
          </div>
        </div>
      @empty
        <div class="alert alert-light border">Este cliente todavía no tiene citas.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection