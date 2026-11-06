@extends('layouts.app')

@section('content')
<div class="container">
  <div class="auth-card">
    <div class="text-center mb-3">
      <i class="bi bi-key fs-1"></i>
      <h1 class="h4 mt-2 mb-1">Crea tu clave nueva</h1>
      <p class="text-muted small mb-0">Mínimo 8 caracteres, con letras y números.</p>
    </div>

    @if($errors->any()) <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif

    <div class="card"><div class="card-body p-4">
      <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label class="form-label">Correo</label>
        <input type="email" name="email" class="form-control mb-3" value="{{ old('email', $email) }}" readonly required>

        <label for="password" class="form-label">Clave nueva</label>
        <div class="input-group mb-3">
          <input id="password" type="password" name="password" class="form-control" required autofocus autocomplete="new-password">
          <button type="button" class="btn btn-outline-dark" id="togglePass" aria-label="Mostrar u ocultar la clave"><i class="bi bi-eye"></i></button>
        </div>

        <label for="password_confirmation" class="form-label">Repite la clave</label>
        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control mb-3" required autocomplete="new-password">

        <button class="btn btn-dark w-100">Cambiar clave</button>
      </form>
    </div></div>

    <div class="text-center mt-3"><a href="{{ route('password.request') }}" class="small">Pedir un enlace nuevo</a></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const inputs = [document.getElementById('password'), document.getElementById('password_confirmation')];
  document.getElementById('togglePass').addEventListener('click', e => {
    const show = inputs[0].type === 'password';
    inputs.forEach(i => i.type = show ? 'text' : 'password');
    e.currentTarget.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
  });
});
</script>
@endpush