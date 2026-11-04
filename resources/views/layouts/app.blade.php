
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

                if (!t) {
                    t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }

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

        // Opciones disponibles para todos los usuarios autenticados
        $links[] = ['agenda', 'Agenda', 'calendar3', 'agenda'];
        $links[] = ['clientes.index', 'Clientes', 'person-lines-fill', 'clientes.*'];

        // Opciones para admin y barbero
        if (! $authUser->isReception()) {
            $links[] = ['comisiones', 'Comisiones', 'cash-coin', 'comisiones'];
        }

        // Opciones exclusivas del administrador
        if ($authUser->isAdmin()) {
            $links[] = ['dashboard', 'Dashboard', 'bar-chart-line', 'dashboard'];
            $links[] = ['caja', 'Cierre de caja', 'receipt', 'caja*'];
            $links[] = ['semana', 'Cierre semanal', 'calendar-week', 'semana*'];
            $links[] = ['gastos.index', 'Gastos', 'wallet2', 'gastos.*'];
        }

        // Bloqueos: admin y barbero
        if (! $authUser->isReception()) {
            $links[] = ['bloqueos.index', 'Bloqueos', 'calendar-x', 'bloqueos.*'];
        }

        // Administración: solo admin
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
            <i class="bi bi-scissors"></i>
            <span>{{ config('app.name', 'Barbería') }}</span>
        </a>

        {{-- Menú principal para PC --}}
        @auth
            @php
                $inline = array_slice($links, 0, 5);
                $menu = array_slice($links, 5);
            @endphp

            <nav class="bb-nav d-none d-lg-flex">

                @foreach($inline as [$r, $label, $icon, $pattern])
                    <a href="{{ route($r) }}"
                       class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
                        <i class="bi bi-{{ $icon }}"></i>
                        {{ $label }}
                    </a>
                @endforeach

                @if($menu)
                    <div class="dropdown">

                        <a href="#"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false"
                           class="{{ collect($menu)->contains(fn ($l) => request()->routeIs($l[3])) ? 'active' : '' }}">

                            <i class="bi bi-three-dots"></i>
                            Más
                        </a>

                        <ul class="dropdown-menu">

                            @foreach($menu as [$r, $label, $icon, $pattern])
                                <li>
                                    <a class="dropdown-item" href="{{ route($r) }}">
                                        <i class="bi bi-{{ $icon }} me-2"></i>
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach

                        </ul>

                    </div>
                @endif

            </nav>
        @endauth

        {{-- Acciones de cabecera --}}
        <div class="bb-actions">

            <button type="button"
                    class="bb-iconbtn"
                    id="themeBtn"
                    aria-label="Cambiar entre modo claro y oscuro">

                <i class="bi bi-moon-stars icon-moon"></i>
                <i class="bi bi-sun icon-sun"></i>

            </button>

            @auth
                <div class="dropdown">

                    <button class="bb-iconbtn"
                            data-bs-toggle="dropdown"
                            aria-label="Mi cuenta">

                        <i class="bi bi-person-circle"></i>

                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">

                        <li class="px-3 py-2">

                            <div class="fw-bold">
                                {{ $authUser->name }}
                            </div>

                            <div class="small text-muted text-capitalize">
                                {{ $authUser->role }}
                            </div>

                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <button class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-2"></i>
                                    Cerrar sesión
                                </button>

                            </form>

                        </li>

                    </ul>

                </div>
            @endauth

        </div>

    </div>

    <div class="bb-pole"></div>

</header>

{{-- Contenido principal --}}
<main class="bb-main">
    @yield('content')
</main>

{{-- Menú inferior para dispositivos móviles --}}
@auth

    @if(count($links) > 1)

        @php
            $primary = array_slice($links, 0, 3);
            $more = array_slice($links, 3);
        @endphp

        <nav class="bb-tabbar d-lg-none">

            @foreach($primary as [$r, $label, $icon, $pattern])

                <a href="{{ route($r) }}"
                   class="{{ request()->routeIs($pattern) ? 'active' : '' }}">

                    <i class="bi bi-{{ $icon }}"></i>
                    <span>{{ $label }}</span>

                </a>

            @endforeach

            @if($more)

                <div class="dropup">

                    <a href="#"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false"
                       class="{{ collect($more)->contains(fn ($l) => request()->routeIs($l[3])) ? 'active' : '' }}">

                        <i class="bi bi-three-dots"></i>
                        <span>Más</span>

                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">

                        @foreach($more as [$r, $label, $icon, $pattern])

                            <li>
                                <a class="dropdown-item" href="{{ route($r) }}">

                                    <i class="bi bi-{{ $icon }} me-2"></i>
                                    {{ $label }}

                                </a>
                            </li>

                        @endforeach

                    </ul>

                </div>

            @endif

        </nav>

    @endif

@endauth

{{-- Tema claro / oscuro --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {

        document.getElementById('themeBtn')?.addEventListener('click', () => {

            const next = document.documentElement.getAttribute('data-bs-theme') === 'dark'
                ? 'light'
                : 'dark';

            document.documentElement.setAttribute('data-bs-theme', next);

            try {
                localStorage.setItem('bb-theme', next);
            } catch (e) {}

        });

    });

    // Service Worker de la PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
    }
</script>

@stack('scripts')

</body>
</html>
