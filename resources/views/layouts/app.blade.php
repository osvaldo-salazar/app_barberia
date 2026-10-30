<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#121B31">
  <link rel="manifest" href="/manifest.json">
  <title>{{ config('app.name', 'Barbería') }}</title>

  {{-- Aplica el tema antes de pintar para evitar el parpadeo --}}
  <script>
    (function () {
      try {
        var t = localStorage.getItem('bb-theme');
        if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
      } catch (e) {}
    })();
  </script>

  @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
@php
  $authUser = auth()->user();
  $links = [];
  if ($authUser) {
      $links[] = ['agenda', 'Agenda', 'calendar3', 'agenda'];
      if (! $authUser->isReception()) {
          $links[] = ['comisiones', 'Comisiones', 'cash-coin', 'comisiones'];
      }
      if ($authUser->isAdmin()) {
          $links[] = ['barberos.index', 'Barberos', 'person-badge', 'barberos.*'];
          $links[] = ['servicios.index', 'Servicios', 'tags', 'servicios.*'];
          $links[] = ['usuarios.index', 'Usuarios', 'people', 'usuarios.*'];
      }
  }
@endphp

<header class="bb-top">
  <div class="container-xxl bb-top-inner">
    <a class="bb-brand" href="{{ $authUser ? route('agenda') : url('/') }}">
      <i class="bi bi-scissors"></i><span>{{ config('app.name', 'Barbería') }}</span>
    </a>

    @auth
      <nav class="bb-nav d-none d-lg-flex">
        @foreach($links as [$r, $label, $icon, $pattern])
          <a href="{{ route($r) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
            <i class="bi bi-{{ $icon }}"></i>{{ $label }}
          </a>
        @endforeach
      </nav>
    @endauth

    <div class="bb-actions">
      <button type="button" class="bb-iconbtn" id="themeBtn" aria-label="Cambiar entre modo claro y oscuro">
        <i class="bi bi-moon-stars icon-moon"></i><i class="bi bi-sun icon-sun"></i>
      </button>

      @auth
        <div class="dropdown">
          <button class="bb-iconbtn" data-bs-toggle="dropdown" aria-label="Mi cuenta"><i class="bi bi-person-circle"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-3 py-2">
              <div class="fw-bold">{{ $authUser->name }}</div>
              <div class="small text-muted text-capitalize">{{ $authUser->role }}</div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
              </form>
            </li>
          </ul>
        </div>
      @endauth
    </div>
  </div>
  <div class="bb-pole"></div>
</header>

<main class="bb-main">
  @yield('content')
</main>

@auth
  @if(count($links) > 1)
    <nav class="bb-tabbar d-lg-none">
      @foreach($links as [$r, $label, $icon, $pattern])
        <a href="{{ route($r) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
          <i class="bi bi-{{ $icon }}"></i><span>{{ $label }}</span>
        </a>
      @endforeach
    </nav>
  @endif
@endauth

<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('themeBtn')?.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      try { localStorage.setItem('bb-theme', next); } catch (e) {}
    });
  });
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');
</script>

@stack('scripts')
</body>
</html>