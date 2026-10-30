@extends('layouts.app')

@section('content')
@php
  $user      = auth()->user();
  $money     = ! $user->isReception();
  $open      = 10 * 60;
  $weekStart = $date->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
  $isToday   = $date->isToday();
  $grid      = $appointments->whereIn('status', ['pendiente', 'completada']);
  $others    = $appointments->whereNotIn('status', ['pendiente', 'completada']);
  $labels    = ['pendiente' => 'Pendiente', 'completada' => 'Completada', 'cancelada' => 'Cancelada', 'no_asistio' => 'No vino'];
@endphp

<div class="container-xxl">

  {{-- Título --}}
  <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
    <div>
      <h1 class="h4 m-0 text-capitalize">{{ $date->translatedFormat('l j \d\e F') }}</h1>
      @unless($isToday) <a href="{{ route('agenda') }}" class="small">Volver a hoy</a> @endunless
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

  @if(session('ok'))   <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())  <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  {{-- Resumen del día --}}
  <div class="row row-cols-2 row-cols-lg-{{ $money ? 4 : 3 }} g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n">{{ $stats['total'] }}</div><div class="stat-l">Citas del día</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n">{{ $stats['pending'] }}</div><div class="stat-l">Pendientes</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n">{{ $stats['done'] }}</div><div class="stat-l">Completadas</div></div></div>
    @if($money)
      <div class="col"><div class="stat">
        <div class="stat-n">₡{{ number_format($stats['money'], 0, ',', '.') }}</div>
        <div class="stat-l">{{ $user->isBarber() ? 'Tu ganancia de hoy' : 'Producido hoy' }}</div>
      </div></div>
    @endif
  </div>

  {{-- Agenda por columnas --}}
  @if($columns->isEmpty())
    <div class="alert alert-warning">No hay barberos activos. Crea uno en la sección Barberos.</div>
  @else
    <div class="agenda-scroll" style="--cols: {{ $columns->count() }}">

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
          @for($h = 10; $h < 19; $h++)
            <span style="top: calc({{ ($h - 10) * 60 }} * var(--pm))">{{ \Carbon\Carbon::createFromTime($h)->format('g A') }}</span>
          @endfor
        </div>

        @foreach($columns as $b)
          <div class="agenda-col" data-barber="{{ $b->id }}" title="Toca un espacio libre para agendar">
            @foreach($grid->where('barber_id', $b->id) as $a)
              @php
                $s   = $a->starts_at->hour * 60 + $a->starts_at->minute;
                $e   = $a->ends_at->hour * 60 + $a->ends_at->minute;
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
                <span class="appt-svc">{{ $a->service->name }}</span>
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
          <span class="flex-grow-1 text-truncate">{{ $a->client_name }} {{ $a->client_last_name }} · {{ $a->barber->name }}</span>
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
    <form method="POST" action="{{ route('citas.store') }}" id="formNueva" class="modal-content">
      @csrf
      <input type="hidden" name="_modal" value="nueva">
      <div class="modal-header">
        <h2 class="modal-title h5" id="nuevaTitle">Nueva cita</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6"><input name="client_name" class="form-control" placeholder="Nombre" value="{{ old('client_name') }}" required></div>
          <div class="col-6"><input name="client_last_name" class="form-control" placeholder="Apellido" value="{{ old('client_last_name') }}" required></div>
          <div class="col-12">
            <div class="input-group">
              <span class="input-group-text">+506</span>
              <input name="phone" class="form-control" inputmode="numeric" maxlength="8" placeholder="8888 8888" value="{{ old('phone') }}" required>
            </div>
          </div>
          <div class="col-12">
            <select name="barber_id" class="form-select" required>
              @foreach($barbers as $b)
                <option value="{{ $b->id }}" @selected(old('barber_id') == $b->id)>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <select name="service_id" class="form-select" required>
              @foreach($services as $s)
                <option value="{{ $s->id }}" @selected(old('service_id') == $s->id)>
                  {{ $s->name }}@if($money) - ₡{{ number_format($s->price, 0, ',', '.') }}@endif ({{ $s->duration_minutes }} min)
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-6"><input type="date" name="date" class="form-control" value="{{ old('date', $date->toDateString()) }}" required></div>
          <div class="col-6">
            <select name="time" class="form-select" required>
              @foreach($slots as $slot)
                <option value="{{ $slot }}" @selected(old('time') == $slot)>{{ \Carbon\Carbon::createFromFormat('H:i', $slot)->format('g:i A') }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-dark w-100">Agendar cita</button>
      </div>
    </form>
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
    </div>

    {{-- Acciones: cita pendiente --}}
    <div class="sheet-actions" id="viewPending" hidden>
      <button type="button" class="btn btn-success full" id="btnCharge"><i class="bi bi-cash-coin me-1"></i>Cobrar</button>
      <a class="btn btn-outline-dark" id="btnEdit"><i class="bi bi-pencil me-1"></i>Editar</a>
      <button type="button" class="btn btn-outline-dark" id="btnNoShow"><i class="bi bi-person-x me-1"></i>No vino</button>
      <button type="button" class="btn btn-outline-dark" id="btnCancel"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
      <button type="button" class="btn btn-outline-danger" id="btnDelete"><i class="bi bi-trash3 me-1"></i>Eliminar</button>
    </div>

    {{-- Acciones: cita cerrada --}}
    <div class="sheet-actions" id="viewReopen" hidden>
      <button type="button" class="btn btn-outline-dark full" id="btnReopen"><i class="bi bi-arrow-counterclockwise me-1"></i>Reabrir cita</button>
    </div>

    {{-- Cobro --}}
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

    {{-- Formularios ocultos para estado y eliminar --}}
    <form method="POST" id="fStatus">@csrf @method('PATCH')<input type="hidden" name="status"></form>
    <form method="POST" id="fDelete">@csrf @method('DELETE')</form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = id => document.getElementById(id);
  const money = @json($money);
  const isToday = @json($isToday);
  const labels = { pendiente: 'Pendiente', completada: 'Completada', cancelada: 'Cancelada', no_asistio: 'No vino' };
  const fmt = n => '₡' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const pm = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pm'));

  /* ---------- Panel de detalle ---------- */
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
    $('sheetTitle').textContent   = cur.name;
    $('sheetBarber').textContent  = 'Con ' + cur.barber;
    const badge = $('sheetBadge');
    badge.textContent = labels[cur.status];
    badge.className = 'badge st-' + cur.status;

    $('sheetTime').textContent    = cur.day + ' · ' + cur.time;
    $('sheetService').textContent = cur.service;
    $('sheetPrice').textContent   = fmt(cur.price);
    document.querySelectorAll('[data-money]').forEach(el => el.hidden = !money);

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

  /* ---------- Nueva cita ---------- */
  const modalEl = $('modalNueva');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modalEl.addEventListener('shown.bs.modal', () => modalEl.querySelector('[name=client_name]').focus());

  document.querySelectorAll('[data-new]').forEach(b => b.addEventListener('click', () => modal.show()));

  // Tocar un espacio libre: precarga barbero y hora (en bloques de 30 min)
  document.querySelectorAll('.agenda-col').forEach(col => {
    col.addEventListener('click', e => {
      if (e.target !== col) return;
      const mins = 600 + Math.floor(e.offsetY / pm / 30) * 30;
      const f = $('formNueva');
      f.elements.barber_id.value = col.dataset.barber;
      f.elements.time.value =
        String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
      modal.show();
    });
  });

  @if(old('_modal') === 'nueva')
    modal.show();   // reabre el formulario si hubo un error de validación
  @endif

  /* ---------- Línea de la hora actual ---------- */
  const body = document.querySelector('.agenda-body');
  const scroller = document.querySelector('.agenda-scroll');
  if (body && isToday) {
    const line = document.createElement('div');
    line.className = 'agenda-now';
    body.appendChild(line);

    const place = () => {
      const n = new Date();
      const m = n.getHours() * 60 + n.getMinutes() - 600;
      line.hidden = m < 0 || m > 540;
      line.style.top = (m * pm) + 'px';
      return m;
    };

    const m = place();
    setInterval(place, 60000);
    if (m > 0 && scroller) scroller.scrollTop = Math.max(0, m * pm - 140);
  }
});
</script>
@endpush