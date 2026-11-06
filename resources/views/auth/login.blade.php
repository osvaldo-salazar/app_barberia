@extends('layouts.app')

@section('content')
<div class="container">
  <div class="auth-card">
    <div class="text-center mb-3">
      <i class="bi bi-scissors fs-1"></i>
      <h1 class="h4 mt-2 mb-1">Iniciar sesión</h1>
      <p class="text-muted small mb-0">Entra con tu correo y tu clave.</p>
    </div>

    @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif

    <div class="card"><div class="card-body p-4">
      <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="mb-3">
          <label for="email" class="form-label">Correo electrónico</label>
          <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                 class="form-control @error('email') is-invalid @enderror">
          @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Clave</label>
          <div class="input-group">
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="form-control @error('password') is-invalid @enderror">
            <button type="button" class="btn btn-outline-dark" id="togglePass" aria-label="Mostrar u ocultar la clave"><i class="bi bi-eye"></i></button>
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
            <label class="form-check-label" for="remember">Recordarme</label>
          </div>
          <a href="{{ route('password.request') }}" class="small">¿Olvidaste tu clave?</a>
        </div>

        <button class="btn btn-dark w-100">Entrar</button>
      </form>
    </div></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('password');
  document.getElementById('togglePass').addEventListener('click', e => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    e.currentTarget.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
  });
});
</script>
@endpush