@php $money = ! auth()->user()->isReception(); @endphp

@forelse($clients as $c)
  <a href="{{ route('clientes.show', $c) }}" class="client-row">
    <span class="avatar">{{ mb_strtoupper(mb_substr($c->name, 0, 1)) }}</span>

    <span class="flex-grow-1" style="min-width: 0">
      <span class="d-block fw-bold text-truncate">
        {{ $c->full_name }}
        @if($c->visits >= 5)<i class="bi bi-star-fill text-warning ms-1" title="Cliente frecuente"></i>@endif
      </span>
      <span class="d-block small text-muted text-truncate">
        +506 {{ $c->phone_formatted }}@if($c->notes) · {{ \Illuminate\Support\Str::limit($c->notes, 40) }}@endif
      </span>
    </span>

    <span class="text-end small flex-shrink-0">
      <span class="d-block fw-bold">{{ $c->visits }} {{ $c->visits == 1 ? 'visita' : 'visitas' }}</span>
      <span class="d-block text-muted">
        {{ $c->last_visit ? \Carbon\Carbon::parse($c->last_visit)->translatedFormat('j M Y') : 'Sin visitas' }}
      </span>
      @if($money && $c->spent)
        <span class="d-none d-md-block text-muted">₡{{ number_format($c->spent, 0, ',', '.') }}</span>
      @endif
    </span>
  </a>
@empty
  <div class="alert alert-light border">
    {{ $q ? 'No encontramos clientes con esa búsqueda.' : 'Todavía no hay clientes. Se crean solos cuando agendas una cita.' }}
  </div>
@endforelse

<div class="mt-3">{{ $clients->links() }}</div>