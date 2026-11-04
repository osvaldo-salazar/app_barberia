export function bbTimePicker(form, { url, exclude = null } = {}) {
  const root = form.querySelector('[data-timepicker]');
  const q = sel => root.querySelector(sel);

  const hidden     = q('input[name="time"]');
  const grid       = q('[data-slots]');
  const msg        = q('[data-slots-msg]');
  const toggle     = q('[data-custom-toggle]');
  const custom     = q('[data-custom]');
  const extraWrap  = q('[data-extra-wrap]');
  const extraBox   = q('[data-extra]');
  const extraBtn   = q('[data-extra-toggle]');
  const earlyGrid  = q('[data-early]');
  const lateGrid   = q('[data-late]');
  const earlyLabel = q('[data-early-label]');
  const lateLabel  = q('[data-late-label]');

  let ctrl = null;
  let custMode = false;
  let wanted = '';
  let extraOpen = false;
  let extraCount = 0;

  const label = t => {
    const [h, m] = t.split(':').map(Number);
    return (((h + 11) % 12) + 1) + ':' + String(m).padStart(2, '0') + ' ' + (h >= 12 ? 'PM' : 'AM');
  };

  function markActive() {
    root.querySelectorAll('.slot-grid button').forEach(b =>
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

  function setExtra(open) {
    extraOpen = open;
    extraBox.hidden = !open;
    extraBtn.querySelector('span').textContent = open
      ? 'Ocultar horas fuera del horario normal'
      : 'Fuera del horario normal (' + extraCount + ')';
  }

  function fill(target, list) {
    target.replaceChildren();
    list.forEach(t => {
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
      target.append(b);
    });
  }

  function render({ slots, early, late }) {
    fill(grid, slots);
    fill(earlyGrid, early);
    fill(lateGrid, late);

    extraCount = early.length + late.length;
    earlyLabel.hidden = earlyGrid.hidden = early.length === 0;
    lateLabel.hidden  = lateGrid.hidden  = late.length === 0;
    extraWrap.hidden  = extraCount === 0;

    // Si no queda nada en horario normal, se muestran directamente las de fuera de horario
    setExtra(slots.length === 0 && extraCount > 0);

    if (slots.length) msg.textContent = '';
    else msg.textContent = extraCount
      ? 'No quedan horarios dentro del horario normal. Mira los de fuera del horario.'
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

      const data = await r.json();
      render(data);

      const all = [...data.slots, ...data.early, ...data.late];

      if (wanted) {
        if (all.includes(wanted)) {
          setCustom(false);
          hidden.value = wanted;
        } else {
          setCustom(true, wanted);
        }
        wanted = '';
      } else if (!custMode && !all.includes(hidden.value)) {
        hidden.value = '';
      }

      // Si la hora elegida está en "fuera del horario", deja esa sección abierta
      if (!custMode && hidden.value && !data.slots.includes(hidden.value) && all.includes(hidden.value)) {
        setExtra(true);
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
    [grid, earlyGrid, lateGrid].forEach(g => g.replaceChildren());
    extraWrap.hidden = true;
    extraBox.hidden = true;
    extraOpen = false;
    msg.textContent = '';
  }

  toggle.addEventListener('click', () => setCustom(!custMode));
  extraBtn.addEventListener('click', () => setExtra(!extraOpen));
  custom.addEventListener('input', () => { hidden.value = custom.value; });

  return { refresh, setTime, reset };
}