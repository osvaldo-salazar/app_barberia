@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 500px;">
  <h4 class="mb-3">{{ $service->exists ? 'Editar servicio' : 'Nuevo servicio' }}</h4>

  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $service->exists ? route('servicios.update', $service) : route('servicios.store') }}">
    @csrf
    @if($service->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label">Nombre</label>
      <input name="name" class="form-control" value="{{ old('name', $service->name) }}" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Precio</label>
      <div class="input-group">
        <span class="input-group-text">₡</span>
        <input name="price" type="number" min="0" step="100" inputmode="numeric"
               class="form-control" value="{{ old('price', $service->price) }}" required>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Duración</label>
      <select name="duration_minutes" class="form-select" required>
        @foreach([15, 30, 45, 60, 90, 120] as $m)
          <option value="{{ $m }}" @selected(old('duration_minutes', $service->duration_minutes) == $m)>{{ $m }} min</option>
        @endforeach
      </select>
    </div>

    <button class="btn btn-dark">Guardar</button>
    <a href="{{ route('servicios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
  </form>
</div>
@endsection