@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Usuarios</h4>
    <a href="{{ route('usuarios.create') }}" class="btn btn-dark">+ Nuevo</a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-dark">
        <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Barbero</th><th>Estado</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($users as $u)
          <tr class="{{ $u->active ? '' : 'text-muted' }}">
            <td>{{ $u->name }} @if($u->id === auth()->id()) <span class="badge bg-info">Tú</span> @endif</td>
            <td>{{ $u->email }}</td>
            <td class="text-capitalize">{{ $u->role }}</td>
            <td>{{ $u->barber?->name ?? '—' }}</td>
            <td>
              <span class="badge bg-{{ $u->active ? 'success' : 'secondary' }}">{{ $u->active ? 'Activo' : 'Inactivo' }}</span>
            </td>
            <td class="text-end text-nowrap">
              <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-outline-dark">Editar</a>
              @if($u->id !== auth()->id())
                <form method="POST" action="{{ route('usuarios.toggle', $u) }}" class="d-inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-sm btn-outline-{{ $u->active ? 'warning' : 'success' }}">
                    {{ $u->active ? 'Desactivar' : 'Activar' }}
                  </button>
                </form>
                <form method="POST" action="{{ route('usuarios.destroy', $u) }}" class="d-inline"
                      onsubmit="return confirm('¿Eliminar a {{ $u->name }}?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection