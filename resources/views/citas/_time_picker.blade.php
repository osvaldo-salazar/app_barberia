@php
  $minTime = sprintf('%02d:00', config('barberia.day_start'));
  $maxMin  = config('barberia.day_end') * 60 - 5;
  $maxTime = sprintf('%02d:%02d', intdiv($maxMin, 60), $maxMin % 60);
@endphp

<div data-timepicker>
  <input type="hidden" name="time" value="{{ $value ?? '' }}">

  <div class="slot-grid" data-slots></div>
  <div class="small text-muted mt-1" data-slots-msg></div>

  <div data-extra-wrap class="mt-2" hidden>
    <button type="button" class="btn btn-sm btn-outline-dark" data-extra-toggle>
      <i class="bi bi-moon-stars me-1"></i><span></span>
    </button>
    <div data-extra class="mt-2" hidden>
      <div class="small text-muted mb-1" data-early-label>Más temprano</div>
      <div class="slot-grid mb-2" data-early></div>
      <div class="small text-muted mb-1" data-late-label>Más tarde</div>
      <div class="slot-grid" data-late></div>
    </div>
  </div>

  <button type="button" class="btn btn-link btn-sm px-0" data-custom-toggle>Elegir otra hora</button>
  <input type="time" class="form-control form-control-sm w-auto" data-custom
         min="{{ $minTime }}" max="{{ $maxTime }}" step="300" hidden>
</div>