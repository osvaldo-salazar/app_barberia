@extends('layouts.app')

@section('content')
@php
  $badge = [
    'created' => ['Creó', 'success'],   'updated' => ['Cambió', 'primary'], 'deleted' => ['Eliminó', 'danger'],
    'login'   => ['Entró', 'secondary'], 'logout'  => ['Salió', 'secondary'], 'failed'  => ['Fallido', 'danger'],
    'export'  => ['Descarga', 'info'],  'security' => ['Seguridad', 'warning'],
  ];
@endphp
<div class="container-xxl" style="max-width: 1100px">
  <h1 class="h4 mb-1">Registro de actividad</h1>
  <p class="text-muted small">Quién hizo qué y cuándo. No se puede editar ni borrar desde la app. Revísalo cada semana, sobre todo los accesos fallidos, las eliminaciones y las reaperturas.</p>

  <form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <select name="usuario" class="form-select" aria-label="Usuario">
        <option value="">Todos los usuarios</option>
        @foreach($users as $u) <option value="{{ $u->id }}" @selected(request('usuario') == $u->id)>{{ $u->name }}</option> @endforeach
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="accion" class="form-select" aria-label="Tipo de movimiento">
        <option value="">Todos los movimientos</option>
        @foreach($badge as $k => [$label]) <option value="{{ $k }}" @selected(request('accion') === $k)>{{ $label }}</option> @endforeach
      </select>
    </div>
    <div class="col-6 col-md-2"><input type="date" name="desde" class="form-control" value="{{ request('desde') }}" aria-label="Desde"></div>
    <div class="col-6 col-md-2"><input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}" aria-label="Hasta"></div>
    <div class="col-12 col-md-2 d-flex gap-1">
      <input name="q" class="form-control" placeholder="Buscar" value="{{ request('q') }}" maxlength="60">
      <button class="btn btn-dark" aria-label="Filtrar"><i class="bi bi-search"></i></button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead class="table-dark"><tr><th>Cuándo</th><th>Quién</th><th>Qué</th><th>IP</th></tr></thead>
      <tbody>
        @forelse($logs as $l)
          @php [$label, $color] = $badge[$l->action] ?? [$l->action, 'secondary']; @endphp
          <tr>
            <td class="text-nowrap small">{{ $l->created_at->translatedFormat('D j M, g:i A') }}</td>
            <td class="text-nowrap">{{ $l->user_name ?? 'Sin sesión' }}</td>
            <td><span class="badge bg-{{ $color }} me-1">{{ $label }}</span>{{ $l->description }}</td>
            <td class="small text-muted text-nowrap">{{ $l->ip }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted">Sin movimientos con esos filtros.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection