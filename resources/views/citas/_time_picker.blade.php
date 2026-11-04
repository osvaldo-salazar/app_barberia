<div data-timepicker>
  <input type="hidden" name="time" value="{{ $value ?? '' }}">
  <div class="slot-grid" data-slots></div>
  <div class="small text-muted mt-1" data-slots-msg></div>
  <button type="button" class="btn btn-link btn-sm px-0" data-custom-toggle>Elegir otra hora</button>
  <input type="time" class="form-control form-control-sm w-auto" data-custom min="10:00" max="18:55" step="300" hidden>
</div>