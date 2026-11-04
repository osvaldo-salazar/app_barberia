@extends('layouts.app')

@section('content')
<div class="container-xxl" style="max-width: 960px">
  <h1 class="h4 mb-3">Clientes</h1>

  <div class="row row-cols-3 g-2 mb-3">
    <div class="col"><div class="stat"><div class="stat-n">{{ $totals['all'] }}</div><div class="stat-l">Clientes</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n">{{ $totals['new'] }}</div><div class="stat-l">Nuevos este mes</div></div></div>
    <div class="col"><div class="stat"><div class="stat-n">{{ $totals['regular'] }}</div><div class="stat-l">Frecuentes (5+ visitas)</div></div></div>
  </div>

  <form class="row g-2 mb-3" method="GET" id="clientSearch" action="{{ route('clientes.index') }}">
    <div class="col">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="q" class="form-control" autocomplete="off" autofocus
               placeholder="Buscar por nombre o teléfono" value="{{ $q }}">
      </div>
    </div>
    <div class="col-auto">
      <select name="orden" class="form-select" aria-label="Ordenar">
        <option value="visitas"  @selected($sort === 'visitas')>Más visitas</option>
        <option value="reciente" @selected($sort === 'reciente')>Visita más reciente</option>
        <option value="nombre"   @selected($sort === 'nombre')>Nombre</option>
      </select>
    </div>
  </form>

  <div id="clientList">
    @include('clientes._list')
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('clientSearch');
  const list = document.getElementById('clientList');
  let timer, ctrl;

  async function load() {
    const params = new URLSearchParams({ q: form.elements.q.value.trim(), orden: form.elements.orden.value });
    ctrl?.abort();
    ctrl = new AbortController();
    list.style.opacity = .55;
    try {
      const r = await fetch(form.action + '?' + params, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: ctrl.signal,
      });
      if (!r.ok) throw new Error('http');
      list.innerHTML = await r.text();
      history.replaceState(null, '', '?' + params);
    } catch (e) {
      if (e.name === 'AbortError') return;   // llegó una búsqueda más nueva
    }
    list.style.opacity = 1;
  }

  form.elements.q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
  form.elements.orden.addEventListener('change', load);
  form.addEventListener('submit', e => { e.preventDefault(); load(); });
});
</script>
@endpush