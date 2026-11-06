@php
  $m     = fn ($n) => '₡' . number_format($n, 0, ',', '.');
  $multi = count($b['days']) > 1;
@endphp
<div class="modal fade" id="bd-{{ $b['id'] }}" tabindex="-1" aria-labelledby="bdt-{{ $b['id'] }}" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span class="avatar">{{ mb_strtoupper(mb_substr($b['name'], 0, 1)) }}</span>
          <div>
            <h2 class="modal-title h5 m-0" id="bdt-{{ $b['id'] }}">{{ $b['name'] }}</h2>
            <div class="small text-muted">{{ $subtitle }}</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="row row-cols-2 g-2 mb-3">
          <div class="col"><div class="stat"><div class="stat-n">{{ $b['cuts'] }}</div><div class="stat-l">{{ $b['cuts'] == 1 ? 'Corte' : 'Cortes' }}</div></div></div>
          <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $m($b['commission']) }}</div><div class="stat-l">Comisión</div></div></div>
          <div class="col"><div class="stat"><div class="stat-n fs-5">{{ $m($b['tips']) }}</div><div class="stat-l">Propinas</div></div></div>
          <div class="col"><div class="stat"><div class="stat-n fs-5 text-success">{{ $m($b['to_pay']) }}</div><div class="stat-l">Total que gana</div></div></div>
        </div>

        @forelse($b['days'] as $day)
          <div class="bd-day">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
              <div class="fw-bold text-capitalize">{{ $day['date']->translatedFormat('l j \d\e F') }}</div>
              <div class="fw-bold">{{ $m($day['earn']) }}</div>
            </div>
            <div class="small text-muted mb-1">
              {{ $day['cuts'] }} {{ $day['cuts'] == 1 ? 'corte' : 'cortes' }} · comisión {{ $m($day['commission']) }}@if($day['tips']) · propinas {{ $m($day['tips']) }}@endif
            </div>
            @foreach($day['services'] as $x)
              <div class="d-flex justify-content-between small">
                <span>{{ $x['count'] }} × {{ $x['name'] }}</span>
                <span class="text-muted">{{ $m($x['commission']) }}</span>
              </div>
            @endforeach
          </div>
        @empty
          <p class="text-muted mb-0">Sin servicios cobrados en este período.</p>
        @endforelse

        @if($multi)
          <h3 class="h6 mt-3">Total por servicio</h3>
          @foreach($b['services'] as $x)
            <div class="d-flex justify-content-between small py-1">
              <span>{{ $x['count'] }} × {{ $x['name'] }}</span>
              <span class="text-muted">{{ $m($x['commission']) }}</span>
            </div>
          @endforeach
        @endif
      </div>
    </div>
  </div>
</div>