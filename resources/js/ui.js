import * as bootstrap from 'bootstrap';

const ICON = { success: 'check-circle-fill', danger: 'exclamation-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };

const mk = (tag, cls, text) => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
};

/* ---------- Avisos flotantes ---------- */
export function toast(message, type = 'success', title = '', delay = 6000) {
  const host = document.getElementById('toastStack');
  if (!host || !message) return;

  const el = mk('div', 'toast bb-toast bb-toast-' + type);
  el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
  el.setAttribute('aria-atomic', 'true');

  const body = mk('div', 'flex-grow-1');
  if (title) body.append(mk('div', 'fw-bold', title));
  body.append(mk('div', 'bb-toast-msg', message));

  const close = mk('button', 'btn-close');
  close.type = 'button';
  close.setAttribute('data-bs-dismiss', 'toast');
  close.setAttribute('aria-label', 'Cerrar');

  const row = mk('div', 'd-flex align-items-start gap-2 p-3');
  row.append(mk('i', 'bi bi-' + ICON[type] + ' bb-toast-icon'), body, close);

  const bar = mk('div', 'bb-toast-bar');
  bar.style.animationDuration = delay + 'ms';

  el.append(row, bar);
  host.append(el);
  el.addEventListener('hidden.bs.toast', () => el.remove());
  new bootstrap.Toast(el, { delay }).show();
}

/* ---------- Cuadro de confirmación ---------- */
function confirmDialog(opts = {}) {
  const o = { title: '¿Estás seguro?', text: '', ok: 'Confirmar', cancel: 'No, volver', danger: false, ...opts };
  const modalEl = document.getElementById('confirmModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const q = id => modalEl.querySelector(id);

  q('#confirmTitle').textContent = o.title;
  q('#confirmText').textContent = o.text;
  q('#confirmText').hidden = !o.text;
  q('#confirmCancel').textContent = o.cancel;

  const ok = q('#confirmOk');
  ok.textContent = o.ok;
  ok.className = 'btn ' + (o.danger ? 'btn-danger' : 'btn-dark');

  q('#confirmIcon').className = 'confirm-icon ' + (o.danger ? 'is-danger' : 'is-info');
  q('#confirmIcon i').className = 'bi bi-' + (o.danger ? 'exclamation-triangle-fill' : 'question-circle-fill');

  return new Promise(resolve => {
    let answer = false;
    const onOk = () => { answer = true; modal.hide(); };
    ok.addEventListener('click', onOk, { once: true });
    modalEl.addEventListener('hidden.bs.modal', () => {
      ok.removeEventListener('click', onOk);
      resolve(answer);
    }, { once: true });
    modal.show();
  });
}

/* ---------- Reducir fotos antes de subirlas ---------- */
export async function shrinkImage(file, max = 1400, quality = 0.82) {
  if (!file || !file.type.startsWith('image/')) return file;
  try {
    const bmp = await createImageBitmap(file);   // respeta la orientación de la foto
    const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
    const w = Math.round(bmp.width * scale);
    const h = Math.round(bmp.height * scale);

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(bmp, 0, 0, w, h);
    bmp.close?.();

    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', quality));
    return blob ? new File([blob], 'comprobante.jpg', { type: 'image/jpeg' }) : file;
  } catch (e) {
    return file;   // si el navegador no puede, se sube la original
  }
}

/* ---------- Textos de confirmación automáticos ---------- */
function parseConfirm(text) {
  const i = text.indexOf('?');
  const title = i >= 0 ? text.slice(0, i + 1) : 'Confirma esta acción';
  const body  = i >= 0 ? text.slice(i + 1).trim() : text;
  const danger = /eliminar|borrar|deshacer|cancelar/i.test(text);

  let ok = 'Confirmar';
  if (/eliminar|borrar/i.test(text)) ok = 'Sí, eliminar';
  else if (/deshacer/i.test(text))   ok = 'Sí, deshacer';
  else if (/cancelar/i.test(text))   ok = 'Sí, cancelar';
  else if (/pagado/i.test(text))     ok = 'Sí, marcar pagado';

  return { title, text: body, ok, danger };
}

function busy(btn) {
  if (!btn || btn.dataset.busy) return;
  btn.dataset.busy = '1';
  setTimeout(() => {
    btn.disabled = true;
    btn.prepend(mk('span', 'spinner-border spinner-border-sm me-2'));
  }, 0);
}

document.addEventListener('submit', async e => {
  if (e.defaultPrevented) return;
  const form = e.target;

  if (form.dataset.confirm && !form.dataset.confirmed) {
    e.preventDefault();
    if (await confirmDialog(parseConfirm(form.dataset.confirm))) {
      form.dataset.confirmed = '1';
      form.requestSubmit ? form.requestSubmit() : form.submit();
    }
    return;
  }

  busy(e.submitter || form.querySelector('button:not([type="button"]), input[type="submit"]'));
});

// Si vuelves con el botón "atrás", los botones no se quedan bloqueados
window.addEventListener('pageshow', e => {
  if (!e.persisted) return;
  document.querySelectorAll('[data-busy]').forEach(b => {
    b.disabled = false;
    delete b.dataset.busy;
    b.querySelector('.spinner-border')?.remove();
  });
});

/* ---------- Visor de fotos ---------- */
export function lightbox(src, caption = '') {
  const el = document.getElementById('lightbox');
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightboxCaption').textContent = caption;
  const modal = bootstrap.Modal.getOrCreateInstance(el);

  return new Promise(resolve => {
    el.addEventListener('hidden.bs.modal', () => resolve(), { once: true });
    modal.show();
  });
}

document.addEventListener('click', e => {
  const t = e.target.closest('[data-lightbox]');
  if (!t) return;
  e.preventDefault();
  lightbox(t.dataset.lightbox, t.dataset.caption || '');
});

/* ---------- Los mensajes verdes de las páginas pasan a ser avisos flotantes ---------- */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert-success').forEach(a => {
    toast(a.textContent.trim(), 'success', '¡Listo!');
    a.remove();
  });
});

window.bbUi = { toast, confirm: confirmDialog, shrinkImage };