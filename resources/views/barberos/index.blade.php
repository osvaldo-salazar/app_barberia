
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Barberos</h4>
        <a href="{{ route('barberos.create') }}" class="btn btn-dark">
            + Nuevo
        </a>
    </div>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th class="text-end">% barbero</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($barbers as $b)
                    <tr class="{{ $b->active ? '' : 'text-muted' }}">
                        <td>{{ $b->name }}</td>

                        <td class="text-end">
                            {{ rtrim(rtrim(number_format($b->commission_percent, 2), '0'), '.') }}%
                        </td>

                        <td>
                            <span class="badge bg-{{ $b->active ? 'success' : 'secondary' }}">
                                {{ $b->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td class="text-end text-nowrap">
                            {{-- Editar --}}
                            <a href="{{ route('barberos.edit', $b) }}"
                               class="btn btn-sm btn-outline-dark">
                                Editar
                            </a>

                            {{-- Activar / desactivar --}}
                            <form method="POST"
                                  action="{{ route('barberos.toggle', $b) }}"
                                  class="d-inline">
                                @csrf
                                @method('PATCH')

                                <button type="submit"
                                        class="btn btn-sm btn-outline-{{ $b->active ? 'danger' : 'success' }}">
                                    {{ $b->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>

                            {{-- Eliminar --}}
                              <form method="POST"
                                    action="{{ route('barberos.destroy', ['barber' => $b->id]) }}"
                                    class="d-inline"
                                    onsubmit="return confirm('¿Seguro que deseas eliminar este barbero?')">
                                  @csrf
                                  @method('DELETE')

                                  <button type="submit" class="btn btn-sm btn-outline-danger">
                                      Eliminar
                                  </button>
                              </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No hay barberos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
