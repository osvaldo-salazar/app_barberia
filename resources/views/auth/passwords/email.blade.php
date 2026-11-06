@extends('layouts.app')

@section('content')
<div class="container">
  <div class="auth-card">
    <div class="text-center mb-3">
      <i class="bi bi-shield-lock fs-1"></i>
      <h1 class="h4 mt-2 mb-1">Recuperar mi clave</h1>
      <p class="text-muted small mb-0">Escribe tu correo registrado y te enviaremos un enlace para cambiarla.</p>
    </div>

    @if(session('status')) <div class="alert alert-info">{{ session('status') }}</div> @endif
    @if($errors->any())    <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif

    <div class="card"><div class="card-body p-4">
      <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <label for="email" class="form-label">Correo electrónico</label>
        <input id="email" type="email" name="email" class="form-control mb-3" value="{{ old('email') }}" required autofocus autocomplete="username">
        <button class="btn btn-dark w-100">Enviar enlace</button>
      </form>
      <p class="small text-muted mt-3 mb-0">
        Solo barberos y administradores pueden recuperar su clave por correo. Si eres de recepción, pídele al administrador que te la cambie.
      </p>
    </div></div>

    <div class="text-center mt-3"><a href="{{ route('login') }}" class="small"><i class="bi bi-chevron-left"></i> Volver a iniciar sesión</a></div>
  </div>
</div>
@endsection