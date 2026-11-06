@extends('layouts.app')

@section('content')
@php $sent = $items->whereNotNull('reminded_at')->count(); @endphp
<div class="container-xxl" style="max-width: 760px">

  <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
    <div>
      <h1 class="h4 m-0">Recordatorios por WhatsApp</h1>
      <div class="text-muted small text-capitalize">{{ $date->translatedFormat('l j \d\e F, Y') }}</div>
    </div>
    <div class="d-flex align-items-center gap-1">
      <a class="btn btn-outline-dark btn-sm" aria-label="Día anterior"
         href="{{ route('recordatorios.index', ['fecha' => $date->copy()->subDay()->toDateString()]) }}"><i class="bi bi-chevron-left"></i></a>
      <input type="date" class="form-control form-control-sm w-auto" value="{{ $date->toDateString() }}"
             onchange="if (this.value) location='{{ route('recordatorios.index') }}?fecha=' + this.value">
      <a class="btn btn-outline-dark btn-sm" aria-label="Día siguiente"
         href="{{ route('recordatorios.index', ['fecha' => $date->copy()->addDay()->toDateString()]) }}"><i class="bi bi-chevron-right"></i></a>
    </div>
  </div>

  @if($items->isEmpty())
    <div class="alert alert-light border">No hay citas pendientes para ese día.</div>
  @else
    <div class="goal-card mb-3">
      <div class="d-flex justify-content-between small mb-1 gap-2">
        <span class="fw-bold" id="sentLabel">{{ $sent }} de {{ $items->count() }} enviados</span>
        <span class="text-muted">Toca «Enviar» y se abre WhatsApp con el mensaje listo</span>
      </div>
      <div class="progress" style="height:.6rem">
        <div class="progress-bar bg-success" id="sentBar" style="width: {{ $items->count() ? $sent / $items->count() * 100 : 0 }}%"></div>
      </div>
    </div>

    @foreach($items as $a)
      <div class="client-row {{ $a->reminded_at ? 'is-sent' : '' }}" data-item data-mark="{{ route('recordatorios.mark', $a) }}">
        <span class="fw-bold text-nowrap" style="min-width:4.6rem">{{ $a->starts_at->format('g:i A') }}</span>
        <span class="flex-grow-1" style="min-width:0">
          <span class="d-block fw-bold text-truncate">{{ $a->client_name }} {{ $a->client_last_name }}</span>
          <span class="d-block small text-muted text-truncate">
            {{ $a->service->name }} · {{ $a->barber->name }} · +506 {{ substr($a->phone, 0, 4) }}-{{ substr($a->phone, 4) }}
          </span>
          <span class="d-block small" data-state>{{ $a->reminded_at ? 'Enviado ✓ ' . $a->reminded_at->format('g:i A') : 'Pendiente de enviar' }}</span>
        </span>
        <span class="d-flex gap-1">
          <a href="{{ $a->wa_url }}" target="_blank" rel="noopener" class="btn btn-success btn-sm" data-wa>
            <i class="bi bi-whatsapp me-1"></i>Enviar
          </a>
          <button type="button" class="btn btn-outline-dark btn-sm" data-undo title="Marcar como no enviado"
                  aria-label="Marcar como no enviado" @if(! $a->reminded_at) hidden @endif><i class="bi bi-arrow-counterclockwise"></i></button>
        </span>
      </div>
    @endforeach
  @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const token = document.querySelector('meta[name=csrf-token]').content;
  const total = {{ $items->count() }};
  const bar   = document.getElementById('sentBar');
  const label = document.getElementById('sentLabel');

  function refresh() {
    if (!bar) return;
    const n = document.querySelectorAll('[data-item].is-sent').length;
    label.textContent = n + ' de ' + total + ' enviados';
    bar.style.width = (total ? n / total * 100 : 0) + '%';
  }

  async function mark(card, undo) {
    try {
      const r = await fetch(card.dataset.mark, {
        method: 'POST', keepalive: true,
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ undo }),
      });
      if (!r.ok) throw new Error('http');

      const d = await r.json();
      card.classList.toggle('is-sent', d.sent);
      card.querySelector('[data-state]').textContent = d.sent ? 'Enviado ✓ ' + d.at : 'Pendiente de enviar';
      card.querySelector('[data-undo]').hidden = !d.sent;
      refresh();
    } catch (e) {
      bbUi.toast('No se pudo marcar como enviado. Intenta de nuevo.', 'danger');
    }
  }

  document.querySelectorAll('[data-wa]').forEach(a =>
    a.addEventListener('click', () => mark(a.closest('[data-item]'), false)));
  document.querySelectorAll('[data-undo]').forEach(b =>
    b.addEventListener('click', () => mark(b.closest('[data-item]'), true)));
});
</script>
@endpush