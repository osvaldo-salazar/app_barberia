@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 500px;">
  <h4 class="mb-3">{{ $barber->exists ? 'Editar barbero' : 'Nuevo barbero' }}</h4>

  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $barber->exists ? route('barberos.update', $barber) : route('barberos.store') }}">
    @csrf
    @if($barber->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label">Nombre</label>
      <input name="name" class="form-control" value="{{ old('name', $barber->name) }}" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Porcentaje que gana el barbero</label>
      <div class="input-group">
        <input name="commission_percent" type="number" step="0.01" min="0" max="100"
               class="form-control" value="{{ old('commission_percent', $barber->commission_percent) }}" required>
        <span class="input-group-text">%</span>
      </div>
      <div class="form-text">El resto es para el jefe. Solo aplica a citas nuevas.</div>
    </div>

    <button class="btn btn-dark">Guardar</button>
    <a href="{{ route('barberos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
  </form>
</div>
@endsection