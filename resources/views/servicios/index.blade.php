@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Servicios</h4>
    <a href="{{ route('servicios.create') }}" class="btn btn-dark">+ Nuevo</a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-dark">
        <tr><th>Servicio</th><th class="text-end">Precio</th><th class="text-end">Duración</th><th>Estado</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($services as $s)
          <tr class="{{ $s->active ? '' : 'text-muted' }}">
            <td>{{ $s->name }}</td>
            <td class="text-end">₡{{ number_format($s->price, 0, ',', '.') }}</td>
            <td class="text-end">{{ $s->duration_minutes }} min</td>
            <td>
              <span class="badge bg-{{ $s->active ? 'success' : 'secondary' }}">
                {{ $s->active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="text-end">
              <a href="{{ route('servicios.edit', $s) }}" class="btn btn-sm btn-outline-dark">Editar</a>
              <form method="POST" action="{{ route('servicios.toggle', $s) }}" class="d-inline">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-{{ $s->active ? 'danger' : 'success' }}">
                  {{ $s->active ? 'Desactivar' : 'Activar' }}
                </button>
                <form method="POST" action="{{ route('servicios.destroy', $s) }}" class="d-inline"
                    onsubmit="return confirm('¿Eliminar a {{ $s->name }}? Su historial de citas se conserva.')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
              </form>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection