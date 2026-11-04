@extends('layouts.app')

@section('content')
@php
  $user      = auth()->user();
  $money     = ! $user->isReception();

  $open      = config('barberia.day_start') * 60;       // minuto en que empieza la agenda
  $dayTo     = config('barberia.day_end') * 60;
  $span      = $dayTo - $open;
  $openMin   = config('barberia.open_hour') * 60;       // horario normal
  $closeMin  = config('barberia.close_hour') * 60;

  $weekStart = $date->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
  $isToday   = $date->isToday();
  $grid      = $appointments->whereIn('status', ['pendiente', 'completada']);
  $others    = $appointments->whereNotIn('status', ['pendiente', 'completada']);
  $labels    = ['pendiente' => 'Pendiente', 'completada' => 'Completada', 'cancelada' => 'Cancelada', 'no_asistio' => 'No vino'];

  $prefillData = $prefill ? [
    'name' => $prefill->name,
    'last_name' => $prefill->last_name,
    'phone' => $prefill->phone,
    'visits' => (int) $prefill->visits,
    'notes' => $prefill->notes,
  ] : null;

  $mins = fn ($c) => $c->hour * 60 + $c->minute;
  $outside = $grid->filter(fn ($a) =>
      $mins($a->starts_at) < $openMin ||
      ($a->ends_at->isSameDay($a->starts_at) ? $mins($a->ends_at) : 1440) > $closeMin
  )->sortBy('starts_at');
@endphp

<div class="container-xxl">
  <h1 class="visually-hidden">Agenda</h1>

  {{-- Fecha (abre el calendario) --}}
  <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-outline-dark d-flex align-items-center gap-2" id="btnCal">
        <i class="bi bi-calendar3"></i>
        <span class="fw-bold text-capitalize">{{ $date->translatedFormat('l j \d\e F, Y') }}</span>
        <i class="bi bi-chevron-down small"></i>
      </button>
      @unless($isToday)
        <a href="{{ route('agenda') }}" class="btn btn-sm btn-outline-dark">Hoy</a>
      @endunless
    </div>
    <button type="button" class="btn btn-dark d-none d-lg-inline-flex align-items-center gap-2" data-new>
      <i class="bi bi-plus-lg"></i> Nueva cita
    </button>
  </div>

  {{-- Semana domingo a sábado --}}
  <div class="week mb-3">
    <a class="btn btn-sm btn-outline-dark" aria-label="Semana anterior"
       href="{{ route('agenda', ['fecha' => $date->copy()->subWeek()->toDateString()]) }}"><i class="bi bi-chevron-left"></i></a>
    @for($i = 0; $i < 7; $i++)
      @php $d = $weekStart->copy()->addDays($i); @endphp
      <a href="{{ route('agenda', ['fecha' => $d->toDateString()]) }}"
         class="week-day {{ $d->isSameDay($date) ? 'active' : '' }} {{ $d->isToday() ? 'today' : '' }}">
        <small>{{ mb_substr($d->translatedFormat('l'), 0, 3) }}</small>
        <span class="d">{{ $d->day }}</span>
      </a>
    @endfor
    <a class="btn btn-sm btn-outline-dark" aria-label="Semana siguiente"
       href="{{ route('agenda', ['fecha' => $date->copy()->addWeek()->toDateString()]) }}"><i class="bi bi-chevron-right"></i></a>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  @if($errors->any() && old('_modal') !== 'nueva')
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  {{-- Resumen del día --}}
  <div class="stats-row mb-3">
    <div class="stat"><div class="stat-n">{{ $stats['total'] }}</div><div class="stat-l">Citas del día</div></div>
    <div class="stat"><div class="stat-n">{{ $stats['pending'] }}</div><div class="stat-l">Pendientes</div></div>
    <div class="stat"><div class="stat-n">{{ $stats['done'] }}</div><div class="stat-l">Completadas</div></div>
    @if($money)
      <div class="stat">
        <div class="stat-n">₡{{ number_format($stats['money'], 0, ',', '.') }}</div>
        <div class="stat-l">{{ $user->isBarber() ? 'Tu ganancia de hoy' : 'Producido hoy' }}</div>
      </div>
    @endif
  </div>

  {{-- Aviso de citas fuera del horario normal --}}
  @if($outside->isNotEmpty())
    <button type="button" class="btn btn-sm btn-outline-dark mb-2" id="btnOutside"
            data-min="{{ $mins($outside->first()->starts_at) }}">
      <i class="bi bi-moon-stars me-1"></i>
      {{ $outside->count() }} {{ $outside->count() === 1 ? 'cita fuera' : 'citas fuera' }} del horario normal
    </button>
  @endif

  {{-- Agenda por columnas --}}
  @if($columns->isEmpty())
    <div class="alert alert-warning">No hay barberos activos. Crea uno en la sección Barberos.</div>
  @else
    <div class="agenda-scroll" style="--cols: {{ $columns->count() }}; --span: {{ $span }}">

      <div class="agenda-grid agenda-head">
        <div class="agenda-corner"></div>
        @foreach($columns as $b)
          <div class="agenda-barber">
            <span class="avatar">{{ mb_strtoupper(mb_substr($b->name, 0, 1)) }}</span>
            <span class="text-truncate">{{ $b->name }}</span>
            @if($b->trashed() || ! $b->active) <small class="text-muted">(inactivo)</small> @endif
          </div>
        @endforeach
      </div>

      <div class="agenda-grid agenda-body">
        <div class="agenda-ruler">
          @for($m = $open; $m < $dayTo; $m += 60)
            <span style="top: calc({{ $m - $open }} * var(--pm))">{{ \Carbon\Carbon::createFromTime(intdiv($m, 60))->format('g A') }}</span>
          @endfor
        </div>

        @foreach($columns as $b)
          <div class="agenda-col" data-barber="{{ $b->id }}" title="Toca un espacio libre para agendar">

            @if($openMin > $open)
              <div class="off-hours before" style="height: calc({{ $openMin - $open }} * var(--pm))"></div>
            @endif
            @if($closeMin < $dayTo)
              <div class="off-hours after" style="top: calc({{ $closeMin - $open }} * var(--pm))"></div>
            @endif

            @foreach($blocks->filter(fn ($x) => $x['barber_id'] === null || $x['barber_id'] == $b->id) as $blk)
              <div class="block-area" title="{{ $blk['reason'] }}"
                   style="top: calc({{ $blk['from'] - $open }} * var(--pm)); height: calc({{ $blk['to'] - $blk['from'] }} * var(--pm));">
                <span>{{ $blk['reason'] }}</span>
              </div>
            @endforeach

            @foreach($grid->where('barber_id', $b->id) as $a)
              @php
                $s   = $a->starts_at->hour * 60 + $a->starts_at->minute;
                $e   = $a->ends_at->isSameDay($a->starts_at) ? $a->ends_at->hour * 60 + $a->ends_at->minute : 1440;
                $top = $s - $open;
                $dur = $e - $s;
              @endphp
              <button type="button"
                      class="appt appt-{{ $a->status }} {{ $dur <= 30 ? 'appt-short' : '' }}"
                      style="top: calc({{ $top }} * var(--pm)); height: calc({{ $dur }} * var(--pm) - 2px);"
                      @include('citas._attrs', ['a' => $a])>
                <span class="appt-time">
                  @if($a->status === 'completada')<i class="bi bi-check-circle-fill"></i>@endif
                  {{ $a->starts_at->format('g:i') }}
                </span>
                <span class="appt-name">{{ $a->client_name }} {{ $a->client_last_name }}</span>
                <span class="appt-svc">{{ $a->service?->name ?? 'Servicio eliminado' }}</span>
              </button>
            @endforeach
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Canceladas y no asistió --}}
  @if($others->isNotEmpty())
    <details class="others mt-3">
      <summary>Canceladas y no asistió ({{ $others->count() }})</summary>
      @foreach($others as $a)
        <button type="button" class="appt-row appt-{{ $a->status }}" @include('citas._attrs', ['a' => $a])>
          <span class="fw-bold">{{ $a->starts_at->format('g:i A') }}</span>
          <span class="flex-grow-1 text-truncate">{{ $a->client_name }} {{ $a->client_last_name }} · {{ $a->barber?->name ?? 'Barbero eliminado' }}</span>
          <span class="badge st-{{ $a->status }}">{{ $labels[$a->status] }}</span>
        </button>
      @endforeach
    </details>
  @endif
</div>

{{-- Botón flotante (celular) --}}
<button type="button" class="bb-fab d-lg-none" data-new aria-label="Nueva cita"><i class="bi bi-plus-lg"></i></button>

{{-- ===== Modal: nueva cita ===== --}}
<div class="modal fade" id="modalNueva" tabindex="-1" aria-labelledby="nuevaTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <form method="POST" action="{{ route('citas.store') }}" id="formNueva" class="modal-content" novalidate>
      @csrf
      <input type="hidden" name="_modal" value="nueva">
      <input type="hidden" name="_cmode" value="{{ old('_cmode', 'search') }}">

      <div class="modal-header">
        <h2 class="modal-title h5" id="nuevaTitle">Nueva cita</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">

        {{-- Errores de validación del servidor --}}
        @if($errors->any() && old('_modal') === 'nueva')
          <div class="alert alert-danger py-2">
            <ul class="mb-0 ps-3">
              @foreach($errors->all() as $msg)
                <li>{{ $msg }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="row g-2">

          {{-- Cliente --}}
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Cliente</label>

            <div id="cpSearch">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="cpInput" class="form-control" autocomplete="off"
                       placeholder="Buscar por nombre o teléfono">
                <button type="button" class="btn btn-dark" id="cpNew">
                  <i class="bi bi-person-plus me-1"></i><span class="d-none d-sm-inline">Nuevo</span>
                </button>
              </div>
              <div class="position-relative">
                <div class="list-group client-suggest" id="clientSuggest" hidden></div>
              </div>
            </div>

            <div id="cpSelected" class="client-info" hidden>
              <div class="d-flex align-items-center gap-2">
                <span class="avatar" id="cpAvatar"></span>
                <div class="flex-grow-1" style="min-width:0">
                  <div class="fw-bold text-truncate">
                    <span id="cpName"></span>
                    <i class="bi bi-star-fill text-warning ms-1" id="cpStar" title="Cliente frecuente" hidden></i>
                  </div>
                  <div class="small text-muted" id="cpMeta"></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-dark" id="cpChange">Cambiar</button>
              </div>
              <div class="small mt-2" id="cpNotes" hidden></div>
            </div>

            {{-- Cliente nuevo --}}
            <div id="cpFields" hidden>
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-bold">Cliente nuevo</span>
                <button type="button" class="btn btn-link btn-sm p-0" id="cpBack">Volver a buscar</button>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <input name="client_name" class="form-control @error('client_name') is-invalid @enderror"
                         placeholder="Nombre" autocomplete="off" maxlength="60" value="{{ old('client_name') }}">
                </div>
                <div class="col-6">
                  <input name="client_last_name" class="form-control @error('client_last_name') is-invalid @enderror"
                         placeholder="Apellido" autocomplete="off" maxlength="60" value="{{ old('client_last_name') }}">
                </div>
                <div class="col-12">
                  <div class="input-group">
                    <span class="input-group-text">+506</span>
                    <input name="phone" class="form-control @error('phone') is-invalid @enderror"
                           inputmode="numeric" placeholder="8888 8888" autocomplete="off" maxlength="14" value="{{ old('phone') }}">
                  </div>
                </div>
              </div>
              <div class="alert alert-warning py-2 px-3 small mt-2 mb-0" id="cpDup" hidden>
                <span id="cpDupText"></span>
                <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="cpDupUse">Usar este cliente</button>
              </div>
            </div>
          </div>

          {{-- Barbero y servicio --}}
          <div class="col-12 col-sm-6">
            <label class="form-label small text-muted mb-1">Barbero</label>
            <select name="barber_id" class="form-select" required>
              @foreach($barbers as $b)
                <option value="{{ $b->id }}" @selected(old('barber_id') == $b->id)>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label small text-muted mb-1">Servicio</label>
            <select name="service_id" class="form-select" required>
              @foreach($services as $s)
                <option value="{{ $s->id }}" @selected(old('service_id') == $s->id)>
                  {{ $s->name }}@if($money) - ₡{{ number_format($s->price, 0, ',', '.') }}@endif ({{ $s->duration_minutes }} min)
                </option>
              @endforeach
            </select>
          </div>

          {{-- Fecha y hora --}}
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Fecha</label>
            <input type="date" name="date" class="form-control" value="{{ old('date', $date->toDateString()) }}" required>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Hora</label>
            @include('citas._time_picker', ['value' => old('time')])
          </div>
        </div>
      </div>

      <div class="modal-footer flex-column align-items-stretch">
        <div class="text-danger small" id="formMsg" hidden></div>
        <button class="btn btn-dark w-100">Agendar cita</button>
      </div>
    </form>
  </div>
</div>

{{-- ===== Panel inferior: calendario ===== --}}
<div class="offcanvas offcanvas-bottom sheet" tabindex="-1" id="calSheet" aria-labelledby="calTitle">
  <div class="sheet-grab"></div>
  <div class="offcanvas-body">
    <h2 class="visually-hidden" id="calTitle">Elegir fecha</h2>

    <div class="d-flex align-items-center gap-2 mb-2">
      <button type="button" class="btn btn-outline-dark btn-sm" id="calPrev" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></button>
      <select id="calMonth" class="form-select form-select-sm" aria-label="Mes"></select>
      <select id="calYear" class="form-select form-select-sm" style="max-width:6.5rem" aria-label="Año"></select>
      <button type="button" class="btn btn-outline-dark btn-sm" id="calNext" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></button>
    </div>

    <div class="cal-head"><span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span></div>
    <div class="cal-grid" id="calGrid"></div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <small class="text-muted cal-legend"><i class="cal-dot"></i>Días con citas</small>
      <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda') }}">Ir a hoy</a>
    </div>
  </div>
</div>

{{-- ===== Panel inferior: detalle de la cita ===== --}}
<div class="offcanvas offcanvas-bottom sheet" tabindex="-1" id="sheet" aria-labelledby="sheetTitle">
  <div class="sheet-grab"></div>
  <div class="offcanvas-body">

    <div class="d-flex justify-content-between align-items-start gap-2">
      <div>
        <h2 class="h5 m-0" id="sheetTitle"></h2>
        <div class="text-muted small" id="sheetBarber"></div>
      </div>
      <span class="badge" id="sheetBadge"></span>
    </div>

    <div class="sheet-info my-3">
      <div><i class="bi bi-clock"></i><span id="sheetTime"></span></div>
      <div><i class="bi bi-scissors"></i><span id="sheetService"></span></div>
      <div data-money><i class="bi bi-cash"></i><span id="sheetPrice"></span></div>
      <div id="sheetPayRow" hidden><i class="bi bi-wallet2"></i><span id="sheetPay"></span></div>
      <div><i class="bi bi-whatsapp"></i><a id="sheetWa" target="_blank" rel="noopener"><span id="sheetPhone"></span></a></div>
      <div id="sheetClientRow"><i class="bi bi-person-check"></i><span id="sheetVisits"></span>
        <a id="sheetClientLink" class="ms-2">Ver ficha</a></div>
      <div id="sheetNotesRow" hidden><i class="bi bi-journal-text"></i><span id="sheetNotes"></span></div>
    </div>

    <div class="sheet-actions" id="viewPending" hidden>
      <button type="button" class="btn btn-success full" id="btnCharge"><i class="bi bi-cash-coin me-1"></i>Cobrar</button>
      <a class="btn btn-outline-dark" id="btnEdit"><i class="bi bi-pencil me-1"></i>Editar</a>
      <button type="button" class="btn btn-outline-dark" id="btnNoShow"><i class="bi bi-person-x me-1"></i>No vino</button>
      <button type="button" class="btn btn-outline-dark" id="btnCancel"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
      <button type="button" class="btn btn-outline-danger" id="btnDelete"><i class="bi bi-trash3 me-1"></i>Eliminar</button>
    </div>

    <div class="sheet-actions" id="viewReopen" hidden>
      <button type="button" class="btn btn-outline-dark full" id="btnReopen"><i class="bi bi-arrow-counterclockwise me-1"></i>Reabrir cita</button>
    </div>

    <form method="POST" id="fPay" hidden>
      @csrf @method('PATCH')
      <input type="hidden" name="status" value="completada">
      <p class="fw-bold mb-2">Total: <span id="payTotal"></span></p>

      <div class="btn-group w-100 mb-3" role="group" aria-label="Método de pago">
        <input type="radio" class="btn-check" name="payment_method" id="pmE" value="efectivo" required>
        <label class="btn btn-outline-dark" for="pmE"><i class="bi bi-cash me-1"></i>Efectivo</label>
        <input type="radio" class="btn-check" name="payment_method" id="pmS" value="sinpe">
        <label class="btn btn-outline-dark" for="pmS"><i class="bi bi-phone me-1"></i>SINPE</label>
        <input type="radio" class="btn-check" name="payment_method" id="pmT" value="tarjeta">
        <label class="btn btn-outline-dark" for="pmT"><i class="bi bi-credit-card me-1"></i>Tarjeta</label>
      </div>

      <label class="form-label">Propina (opcional)</label>
      <div class="input-group mb-3">
        <span class="input-group-text">₡</span>
        <input type="number" name="tip" class="form-control" min="0" step="100" inputmode="numeric" value="0">
      </div>
      <button class="btn btn-success w-100">Confirmar cobro</button>
    </form>

    <form method="POST" id="fStatus">@csrf @method('PATCH')<input type="hidden" name="status"></form>
    <form method="POST" id="fDelete">@csrf @method('DELETE')</form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = id => document.getElementById(id);
  const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text) e.textContent = text;
    return e;
  };
  const fmt = n => '₡' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const normPhone = v => {
    const d = String(v).replace(/\D/g, '');
    return d.length === 11 && d.startsWith('506') ? d.slice(3) : d;
  };

  const money    = @json($money);
  const isToday  = @json($isToday);
  const pageDate = @json($date->toDateString());
  const todayIso = @json(today()->toDateString());
  const pm = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pm'));

  const DAY_FROM = @json($open);       // minuto en que empieza la agenda
  const SPAN     = @json($span);
  const OPEN_MIN = @json($openMin);    // hora normal de entrada

  const labels = { pendiente: 'Pendiente', completada: 'Completada', cancelada: 'Cancelada', no_asistio: 'No vino' };

  /* =============== Detalle de la cita =============== */
  const sheet = bootstrap.Offcanvas.getOrCreateInstance($('sheet'));
  let cur = null;

  document.addEventListener('click', e => {
    const b = e.target.closest('[data-appt]');
    if (!b) return;
    cur = b.dataset;
    fill();
    sheet.show();
  });

  function fill() {
    $('sheetTitle').textContent  = cur.name;
    $('sheetBarber').textContent = 'Con ' + cur.barber;
    const badge = $('sheetBadge');
    badge.textContent = labels[cur.status];
    badge.className = 'badge st-' + cur.status;

    $('sheetTime').textContent    = cur.day + ' · ' + cur.time;
    $('sheetService').textContent = cur.service;
    $('sheetPrice').textContent   = fmt(cur.price);
    document.querySelectorAll('[data-money]').forEach(x => x.hidden = !money);

    const paid = cur.status === 'completada' && cur.pay;
    $('sheetPayRow').hidden = !(paid && money);
    if (paid) {
      $('sheetPay').textContent = cur.pay.charAt(0).toUpperCase() + cur.pay.slice(1) +
        (Number(cur.tip) ? ' · propina ' + fmt(cur.tip) : '');
    }

    $('sheetPhone').textContent = '+506 ' + cur.phone.slice(0, 4) + '-' + cur.phone.slice(4);
    $('sheetWa').href = 'https://wa.me/506' + cur.phone + '?text=' + encodeURIComponent(
      `Hola ${cur.first}, le recordamos su cita el ${cur.day} a las ${cur.start} en la barbería.`
    );

    const v = Number(cur.visits || 0);
    $('sheetVisits').textContent = v === 0 ? 'Primera visita'
      : v === 1 ? '1 visita completada' : v + ' visitas completadas';
    $('sheetClientRow').hidden = !cur.clientUrl;
    $('sheetClientLink').href = cur.clientUrl || '#';
    $('sheetNotesRow').hidden = !cur.notes;
    $('sheetNotes').textContent = cur.notes || '';

    const pending = cur.status === 'pendiente';
    $('viewPending').hidden = !pending;
    $('btnCharge').hidden   = !money;
    $('viewReopen').hidden  = pending || (cur.status === 'completada' && !money);
    $('fPay').hidden = true;
    $('fPay').reset();
    $('btnEdit').href = cur.urlEdit;
  }

  function setStatus(status, ask) {
    if (ask && !confirm(ask)) return;
    const f = $('fStatus');
    f.action = cur.urlStatus;
    f.elements.status.value = status;
    f.submit();
  }

  $('btnNoShow').onclick = () => setStatus('no_asistio');
  $('btnCancel').onclick = () => setStatus('cancelada', '¿Cancelar esta cita?');
  $('btnReopen').onclick = () => setStatus('pendiente',
    '¿Reabrir la cita?' + (cur.status === 'completada' ? ' Se borrará el pago registrado.' : ''));
  $('btnDelete').onclick = () => {
    if (!confirm('¿Eliminar esta cita?')) return;
    const f = $('fDelete');
    f.action = cur.urlDelete;
    f.submit();
  };
  $('btnCharge').onclick = () => {
    $('fPay').action = cur.urlStatus;
    $('payTotal').textContent = fmt(cur.price);
    $('viewPending').hidden = true;
    $('fPay').hidden = false;
  };

  /* =============== Nueva cita =============== */
  const modalEl = $('modalNueva');
  const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
  const formN   = $('formNueva');

  const inName  = formN.elements.client_name;
  const inLast  = formN.elements.client_last_name;
  const inPhone = formN.elements.phone;
  const cmode   = formN.elements._cmode;
  const cpInput = $('cpInput');
  const box     = $('clientSuggest');
  const formMsg = $('formMsg');
  const searchUrl = @json(route('clientes.search'));

  const tp = window.bbTimePicker(formN, { url: @json(route('citas.horarios')) });
  ['barber_id', 'service_id', 'date'].forEach(n =>
    formN.elements[n].addEventListener('change', () => tp.refresh()));

  let mode = 'search', timer, ctrl;

  function hideSuggest() { box.hidden = true; box.replaceChildren(); }

  function setMode(m) {
    mode = m;
    cmode.value = m;
    $('cpSearch').hidden   = m !== 'search';
    $('cpSelected').hidden = m !== 'selected';
    $('cpFields').hidden   = m !== 'new';
    [inName, inLast, inPhone].forEach(i => i.required = m === 'new');
    hideSuggest();
    $('cpDup').hidden = true;
    formMsg.hidden = true;
  }

  function clearClient() {
    inName.value = inLast.value = inPhone.value = '';
    [inName, inLast, inPhone].forEach(i => i.classList.remove('is-invalid'));
  }

  function selectClient(c) {
    inName.value  = c.name;
    inLast.value  = c.last_name;
    inPhone.value = c.phone;

    [inName, inLast, inPhone].forEach(i => i.classList.remove('is-invalid'));

    $('cpAvatar').textContent = c.name.charAt(0).toUpperCase();
    $('cpName').textContent   = c.name + ' ' + c.last_name;
    $('cpStar').hidden = !(c.visits >= 5);

    const meta = ['+506 ' + c.phone.slice(0, 4) + '-' + c.phone.slice(4)];
    if (c.visits !== null && c.visits !== undefined) {
      meta.push(c.visits === 0 ? 'Primera visita' : c.visits + (c.visits === 1 ? ' visita' : ' visitas'));
    }
    if (c.last_visit) meta.push('última ' + c.last_visit);
    $('cpMeta').textContent = meta.join(' · ');

    $('cpNotes').textContent = c.notes ? 'Nota: ' + c.notes : '';
    $('cpNotes').hidden = !c.notes;
    setMode('selected');
  }

  function newClient(text = '') {
    clearClient();
    const t = text.trim();
    if (t) {
      if (/^[\d\s+\-]+$/.test(t)) {
        inPhone.value = normPhone(t).slice(0, 8);
      } else {
        const [first, ...rest] = t.split(/\s+/);
        inName.value = first;
        inLast.value = rest.join(' ');
      }
    }
    setMode('new');
    (!inName.value ? inName : !inLast.value ? inLast : inPhone).focus();
  }

  function renderResults(list, q) {
    box.replaceChildren();
    list.forEach(c => {
      const b = el('button', 'list-group-item list-group-item-action');
      b.type = 'button';
      b.append(
        el('div', 'fw-bold', c.name + ' ' + c.last_name),
        el('small', 'text-muted', '+506 ' + c.phone.slice(0, 4) + '-' + c.phone.slice(4) + ' · ' +
          c.visits + (c.visits === 1 ? ' visita' : ' visitas'))
      );
      b.addEventListener('click', () => selectClient(c));
      box.append(b);
    });

    const add = el('button', 'list-group-item list-group-item-action client-add');
    add.type = 'button';
    add.append(el('i', 'bi bi-person-plus me-2'),
      document.createTextNode(list.length ? 'Crear cliente nuevo' : 'Sin resultados. Crear «' + q + '»'));
    add.addEventListener('click', () => newClient(q));
    box.append(add);
    box.hidden = false;
  }

  async function runSearch(q) {
    ctrl?.abort();
    ctrl = new AbortController();
    try {
      const r = await fetch(searchUrl + '?q=' + encodeURIComponent(q),
        { headers: { Accept: 'application/json' }, signal: ctrl.signal });
      if (r.ok && mode === 'search') renderResults(await r.json(), q);
    } catch (e) { /* búsqueda cancelada */ }
  }

  cpInput.addEventListener('input', () => {
    clearTimeout(timer);
    const q = cpInput.value.trim();
    if (q.length < 2) { hideSuggest(); return; }
    timer = setTimeout(() => runSearch(q), 200);
  });

  // Enter elige el primer resultado (y evita enviar el formulario)
  cpInput.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    box.querySelector('button:not(.client-add)')?.click();
  });

  $('cpNew').onclick    = () => newClient(cpInput.value);
  $('cpChange').onclick = () => { clearClient(); cpInput.value = ''; setMode('search'); cpInput.focus(); };
  $('cpBack').onclick   = () => { clearClient(); setMode('search'); cpInput.focus(); };

  // Aviso si el teléfono que escribes ya pertenece a un cliente
  inPhone.addEventListener('blur', () => { inPhone.value = normPhone(inPhone.value); });
  inPhone.addEventListener('input', async () => {
    $('cpDup').hidden = true;
    const d = normPhone(inPhone.value);
    if (mode !== 'new' || d.length !== 8) return;
    try {
      const r = await fetch(searchUrl + '?q=' + d, { headers: { Accept: 'application/json' } });
      const hit = r.ok ? (await r.json()).find(c => c.phone === d) : null;
      if (hit) {
        $('cpDupText').textContent = 'Ese teléfono ya es de ' + hit.name + ' ' + hit.last_name + '.';
        $('cpDupUse').onclick = () => selectClient(hit);
        $('cpDup').hidden = false;
      }
    } catch (e) { /* sin conexión */ }
  });

  document.addEventListener('click', e => {
    if (!box.contains(e.target) && e.target !== cpInput) hideSuggest();
  });

  // Validación en español antes de enviar
  const NAME_RE = /^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u;

  formN.addEventListener('submit', e => {
    const errs = [];
    [inName, inLast, inPhone].forEach(i => i.classList.remove('is-invalid'));

    if (mode === 'search') {
      errs.push('Busca un cliente o crea uno nuevo con el botón Nuevo.');
    } else if (mode === 'new') {
      const checkName = (input, label) => {
        const v = input.value.trim();
        if (!v) {
          errs.push('Escribe el ' + label + ' del cliente.');
          input.classList.add('is-invalid');
        } else if (!NAME_RE.test(v)) {
          errs.push('El ' + label + ' solo puede tener letras.');
          input.classList.add('is-invalid');
        }
      };

      checkName(inName, 'nombre');
      checkName(inLast, 'apellido');

      const d = normPhone(inPhone.value);
      inPhone.value = d;

      if (!d) {
        errs.push('Escribe el teléfono del cliente.');
        inPhone.classList.add('is-invalid');
      } else if (!/^[2-8]\d{7}$/.test(d)) {
        errs.push('El teléfono debe tener 8 dígitos y empezar con 2 a 8.');
        inPhone.classList.add('is-invalid');
      } else if (!$('cpDup').hidden) {
        errs.push('Ese teléfono ya está registrado. Usa el botón «Usar este cliente».');
        inPhone.classList.add('is-invalid');
      }
    }

    if (!formN.elements.barber_id.value)  errs.push('Elige un barbero.');
    if (!formN.elements.service_id.value) errs.push('Elige un servicio.');
    if (!formN.elements.date.value)       errs.push('Elige una fecha.');
    if (!formN.elements.time.value)       errs.push('Elige una hora.');

    if (errs.length) {
      e.preventDefault();
      formMsg.replaceChildren(...errs.map(t => el('div', '', t)));
      formMsg.hidden = false;
      return;
    }

    formMsg.hidden = true;
    formN.querySelector('button.btn-dark.w-100').disabled = true;
  });

  modalEl.addEventListener('shown.bs.modal', () => {
    if (mode === 'search') cpInput.focus();
    else if (mode === 'new') (inName.value ? inLast : inName).focus();
  });
  modalEl.addEventListener('hidden.bs.modal', hideSuggest);

  function openNew(barberId, time) {
    clearClient();
    cpInput.value = '';
    setMode('search');
    formN.elements.date.value = pageDate;
    if (barberId) formN.elements.barber_id.value = barberId;
    tp.reset();
    modal.show();
    tp.setTime(time || '');
  }

  document.querySelectorAll('[data-new]').forEach(b => b.addEventListener('click', () => openNew()));

  // Tocar un espacio libre: precarga barbero y hora (de 15 en 15 min)
  document.querySelectorAll('.agenda-col').forEach(col => {
    col.addEventListener('click', e => {
      if (e.target !== col) return;
      const mins = DAY_FROM + Math.floor(e.offsetY / pm / 15) * 15;
      openNew(col.dataset.barber,
        String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0'));
    });
  });

  @if($prefillData)
    selectClient(@json($prefillData));
    tp.reset();
    modal.show();
    tp.refresh();
  @elseif(old('_modal') === 'nueva')
    @if(old('_cmode') === 'new')
      setMode('new');
    @else
      selectClient({
        name: @json(old('client_name')),
        last_name: @json(old('client_last_name')),
        phone: @json(old('phone')),
        visits: null,
        notes: null
      });
    @endif

    modal.show();
    tp.setTime(@json(old('time')));
  @endif

  /* =============== Calendario =============== */
  const calSheet = bootstrap.Offcanvas.getOrCreateInstance($('calSheet'));
  const MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  const monthSel = $('calMonth'), yearSel = $('calYear'), calGrid = $('calGrid');
  const calUrl = @json(route('citas.calendario'));
  const agendaUrl = @json(route('agenda'));
  const cache = {};
  const pad = n => String(n).padStart(2, '0');
  const [py, pmo] = pageDate.split('-').map(Number);
  let cy = py, cm = pmo - 1;

  MONTHS.forEach((n, i) => monthSel.append(new Option(n.charAt(0).toUpperCase() + n.slice(1), i)));
  const y0 = Number(todayIso.slice(0, 4));
  for (let y = Math.min(y0 - 3, py); y <= Math.max(y0 + 2, py); y++) yearSel.append(new Option(y, y));

  function ensureYear(y) {
    if ([...yearSel.options].some(o => +o.value === y)) return;
    const ys = [...yearSel.options].map(o => +o.value).concat(y).sort((a, b) => a - b);
    yearSel.replaceChildren(...ys.map(v => new Option(v, v)));
  }

  function paint(counts) {
    calGrid.replaceChildren();
    const lead  = new Date(cy, cm, 1).getDay();           // 0 = domingo
    const total = new Date(cy, cm + 1, 0).getDate();
    const base  = cy + '-' + pad(cm + 1) + '-';

    for (let i = 0; i < lead; i++) calGrid.append(document.createElement('span'));
    for (let d = 1; d <= total; d++) {
      const iso = base + pad(d);
      const a = el('a',
        'cal-day' + (iso === todayIso ? ' is-today' : '') + (iso === pageDate ? ' is-selected' : ''),
        String(d));
      a.href = agendaUrl + '?fecha=' + iso;
      if (counts[iso]) {
        a.append(el('i', 'cal-dot'));
        a.title = counts[iso] + (counts[iso] === 1 ? ' cita' : ' citas');
      }
      calGrid.append(a);
    }
  }

  async function drawCal() {
    ensureYear(cy);
    monthSel.value = cm;
    yearSel.value = cy;
    const key = cy + '-' + pad(cm + 1);
    paint(cache[key] || {});
    if (cache[key]) return;
    try {
      const r = await fetch(calUrl + '?mes=' + key, { headers: { Accept: 'application/json' } });
      if (!r.ok) return;
      cache[key] = await r.json();
      if (key === cy + '-' + pad(cm + 1)) paint(cache[key]);
    } catch (e) { /* sin conexión: queda sin puntos */ }
  }

  $('btnCal').onclick  = () => { cy = py; cm = pmo - 1; drawCal(); calSheet.show(); };
  $('calPrev').onclick = () => { cm--; if (cm < 0)  { cm = 11; cy--; } drawCal(); };
  $('calNext').onclick = () => { cm++; if (cm > 11) { cm = 0;  cy++; } drawCal(); };
  monthSel.onchange = () => { cm = +monthSel.value; drawCal(); };
  yearSel.onchange  = () => { cy = +yearSel.value;  drawCal(); };

  /* =============== Posición inicial y línea de la hora actual =============== */
  const body = document.querySelector('.agenda-body');
  const scroller = document.querySelector('.agenda-scroll');

  if (body && scroller) {
    let target = (OPEN_MIN - DAY_FROM) * pm - 12;       // empieza en la hora de entrada

    if (isToday) {
      const line = el('div', 'agenda-now');
      body.appendChild(line);

      const place = () => {
        const n = new Date();
        const m = n.getHours() * 60 + n.getMinutes() - DAY_FROM;
        line.hidden = m < 0 || m > SPAN;
        line.style.top = (m * pm) + 'px';
        return m;
      };

      const m = place();
      setInterval(place, 60000);
      if (m > 0 && m < SPAN) target = m * pm - 140;     // hoy: cerca de la hora actual
    }

    scroller.scrollTop = Math.max(0, target);

    $('btnOutside')?.addEventListener('click', () => {
      const min = Number($('btnOutside').dataset.min);
      scroller.scrollTo({ top: Math.max(0, (min - DAY_FROM) * pm - 24), behavior: 'smooth' });
    });
  }
});
</script>
@endpush