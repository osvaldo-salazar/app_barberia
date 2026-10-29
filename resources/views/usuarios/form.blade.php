@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 520px;">
  <h4 class="mb-3">{{ $user->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h4>

  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <form method="POST" action="{{ $user->exists ? route('usuarios.update', $user) : route('usuarios.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label">Nombre</label>
      <input name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Correo</label>
      <input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Rol</label>
      <select name="role" id="role" class="form-select" required>
        @foreach(['admin' => 'Admin (jefe)', 'barbero' => 'Barbero', 'recepcion' => 'Recepción'] as $k => $label)
          <option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3" id="barberBox">
      <label class="form-label">Barbero vinculado</label>
      <select name="barber_id" class="form-select">
        <option value="">— Elige —</option>
        @foreach($barbers as $b)
          <option value="{{ $b->id }}" @selected(old('barber_id', $user->barber_id) == $b->id)>{{ $b->name }}</option>
        @endforeach
      </select>
      <div class="form-text">Así el sistema sabe cuál agenda y cuáles comisiones le corresponden.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">{{ $user->exists ? 'Nueva clave (déjala vacía para no cambiarla)' : 'Clave' }}</label>
      <input name="password" type="password" class="form-control" minlength="8" autocomplete="new-password"
             {{ $user->exists ? '' : 'required' }}>
    </div>

    <button class="btn btn-dark">Guardar</button>
    <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
  </form>
</div>

<script>
  const role = document.getElementById('role');
  const box  = document.getElementById('barberBox');
  const toggle = () => box.style.display = role.value === 'barbero' ? '' : 'none';
  role.addEventListener('change', toggle);
  toggle();
</script>
@endsection