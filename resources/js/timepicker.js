export function bbTimePicker(form, { url, exclude = null } = {}) {
  const root   = form.querySelector('[data-timepicker]');
  const hidden = root.querySelector('input[name="time"]');
  const grid   = root.querySelector('[data-slots]');
  const msg    = root.querySelector('[data-slots-msg]');
  const toggle = root.querySelector('[data-custom-toggle]');
  const custom = root.querySelector('[data-custom]');

  let ctrl = null;
  let custMode = false;
  let wanted = '';

  const label = t => {
    const [h, m] = t.split(':').map(Number);
    return (((h + 11) % 12) + 1) + ':' + String(m).padStart(2, '0') + ' ' + (h >= 12 ? 'PM' : 'AM');
  };

  function markActive() {
    grid.querySelectorAll('button').forEach(b =>
      b.classList.toggle('active', !custMode && b.dataset.t === hidden.value));
  }

  function setCustom(on, value) {
    custMode = on;
    custom.hidden = !on;
    toggle.textContent = on ? 'Ver horarios libres' : 'Elegir otra hora';
    if (on) {
      custom.value = value || custom.value || '';
      hidden.value = custom.value;
    } else {
      hidden.value = '';
    }
    markActive();
  }

  function render(slots) {
    grid.replaceChildren();
    slots.forEach(t => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-outline-dark btn-sm';
      b.dataset.t = t;
      b.textContent = label(t);
      b.addEventListener('click', () => {
        setCustom(false);
        hidden.value = t;
        markActive();
      });
      grid.append(b);
    });
    msg.textContent = slots.length
      ? ''
      : 'No hay horarios libres con esa duración. Prueba otro día o elige otra hora.';
  }

  async function refresh() {
    const barber  = form.elements.barber_id.value;
    const service = form.elements.service_id.value;
    const date    = form.elements.date.value;
    if (!barber || !service || !date) return;

    ctrl?.abort();
    ctrl = new AbortController();
    msg.textContent = 'Buscando horarios…';

    try {
      const qs = new URLSearchParams({ barber_id: barber, service_id: service, date });
      if (exclude) qs.set('exclude', exclude);

      const r = await fetch(url + '?' + qs, {
        headers: { Accept: 'application/json' },
        signal: ctrl.signal,
      });
      if (!r.ok) throw new Error('http');
      const { slots } = await r.json();

      render(slots);

      if (wanted) {
        if (slots.includes(wanted)) { setCustom(false); hidden.value = wanted; }
        else setCustom(true, wanted);
        wanted = '';
      } else if (!custMode && !slots.includes(hidden.value)) {
        hidden.value = '';
      }
      markActive();
    } catch (e) {
      if (e.name !== 'AbortError') msg.textContent = 'No se pudieron cargar los horarios.';
    }
  }

  function setTime(t) { wanted = t || ''; refresh(); }

  function reset() {
    wanted = '';
    custMode = false;
    custom.hidden = true;
    custom.value = '';
    hidden.value = '';
    toggle.textContent = 'Elegir otra hora';
    grid.replaceChildren();
    msg.textContent = '';
  }

  toggle.addEventListener('click', () => setCustom(!custMode));
  custom.addEventListener('input', () => { hidden.value = custom.value; });

  return { refresh, setTime, reset };
}