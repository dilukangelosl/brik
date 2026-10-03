import { on } from '../core.js';

const inCanvas = () => document.body.classList.contains('brik-canvas-mode');
const i18n = () => (window.brikFront && window.brikFront.i18n) || {};

// Forms are previews inside the builder.
on('.brik-search-form, .brik-login-form', (form) => {
  form.addEventListener('submit', (e) => {
    if (inCanvas()) e.preventDefault();
  });
});

function fieldEl(form, name) {
  return form.querySelector(`[data-field="${CSS.escape(name)}"]`);
}

function setError(form, name, message) {
  const field = fieldEl(form, name);
  if (!field) return null;
  const error = field.querySelector('.brik-form-error');
  field.querySelectorAll('input, select, textarea').forEach((input) => {
    if (message) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  });
  if (error) {
    error.textContent = message || '';
    error.hidden = !message;
  }
  return field;
}

function clearErrors(form) {
  form.querySelectorAll('[data-field]').forEach((field) => setError(form, field.dataset.field, ''));
}

/** Mirrors the server rules so most mistakes are caught without a round trip. */
function localErrors(form) {
  const errors = {};
  const msgs = {
    required: form.dataset.msgRequired || 'This field is required.',
    consent: form.dataset.msgConsent || 'Please tick this box to continue.',
    email: form.dataset.msgEmail || 'Please enter a valid email address.',
  };
  form.querySelectorAll('[data-field]').forEach((field) => {
    const name = field.dataset.field;
    if (field.tagName === 'FIELDSET') {
      if (field.hasAttribute('data-required') && !field.querySelector('input:checked')) {
        errors[name] = msgs.required;
      }
      return;
    }
    const input = field.querySelector('input, select, textarea');
    if (input && input.type === 'file' && input.files && input.files[0] && input.dataset.maxBytes && input.files[0].size > Number(input.dataset.maxBytes)) {
      errors[name] = form.dataset.msgSize || 'This file is too large.';
      return;
    }
    if (!input || input.checkValidity()) return;
    const v = input.validity;
    if (v.valueMissing) {
      errors[name] = input.type === 'checkbox'
        ? msgs.consent
        : msgs.required;
    } else if (v.typeMismatch && input.type === 'email') {
      errors[name] = msgs.email;
    } else {
      errors[name] = input.validationMessage;
    }
  });
  return errors;
}

function showAlert(form, type, message) {
  form.querySelectorAll('.brik-form-alert').forEach((alert) => {
    const match = alert.dataset.alert === type;
    alert.hidden = !match;
    if (match && message) alert.querySelector('.brik-form-alert-text').textContent = message;
  });
}

function focusFirst(form, errors) {
  const first = Object.keys(errors).map((n) => fieldEl(form, n)).find(Boolean);
  const input = first && first.querySelector('input, select, textarea');
  if (input) input.focus();
}

// Upload fields show the chosen file's name, and a thumbnail for images.
function showFile(input) {
  const field = input.closest('[data-field]');
  const name = field && field.querySelector('.brik-upload-name');
  const preview = field && field.querySelector('.brik-upload-preview');
  if (!name || !preview) return;
  const file = input.files && input.files[0];
  if (!preview.dataset.icon) preview.dataset.icon = preview.innerHTML;
  name.textContent = file ? file.name : name.dataset.empty;
  if (file && file.type.startsWith('image/')) {
    const img = document.createElement('img');
    img.className = 'size-full object-cover';
    img.alt = '';
    img.src = URL.createObjectURL(file);
    img.onload = () => URL.revokeObjectURL(img.src);
    img.onerror = () => {
      preview.innerHTML = preview.dataset.icon;
    };
    preview.replaceChildren(img);
  } else {
    preview.innerHTML = preview.dataset.icon;
  }
}

on('[data-brik-form]', (form) => {
  // Native validation stays on without JS; with JS errors are shown inline instead.
  form.noValidate = true;

  form.addEventListener('change', (e) => {
    if (e.target.matches('input[type="file"]')) showFile(e.target);
  });
  form.addEventListener('reset', () => {
    setTimeout(() => form.querySelectorAll('input[type="file"]').forEach(showFile));
  });

  form.addEventListener('input', (e) => {
    const field = e.target.closest('[data-field]');
    if (field && e.target.getAttribute('aria-invalid')) setError(form, field.dataset.field, '');
  });
  form.addEventListener('change', (e) => {
    const field = e.target.closest('[data-field]');
    if (field && field.tagName === 'FIELDSET') setError(form, field.dataset.field, '');
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (inCanvas() || form.dataset.busy) return;

    clearErrors(form);
    showAlert(form, '', '');

    const errors = localErrors(form);
    if (Object.keys(errors).length) {
      Object.entries(errors).forEach(([name, msg]) => setError(form, name, msg));
      focusFirst(form, errors);
      return;
    }

    const button = form.querySelector('.brik-form-submit');
    form.dataset.busy = '1';
    form.setAttribute('aria-busy', 'true');
    if (button) {
      button.disabled = true;
      button.setAttribute('data-loading', '');
    }

    const data = new FormData(form);
    data.append('page_url', window.location.href.split('#')[0]);

    let result;
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Brik-Form': '1', Accept: 'application/json' },
      });
      result = await res.json().catch(() => ({}));
    } catch (err) {
      result = {};
    }

    delete form.dataset.busy;
    form.removeAttribute('aria-busy');
    if (button) {
      button.disabled = false;
      button.removeAttribute('data-loading');
    }

    if (result && result.success) {
      if (result.redirect) {
        window.location.assign(result.redirect);
        return;
      }
      // Edit forms keep their values; everything else starts fresh.
      if (!form.hasAttribute('data-keep')) form.reset();
      showAlert(form, 'success', result.message);
      form.dispatchEvent(new CustomEvent('brik:form-sent', { bubbles: true, detail: result }));
      return;
    }

    const serverErrors = (result && result.errors) || {};
    Object.entries(serverErrors).forEach(([name, msg]) => setError(form, name, msg));
    focusFirst(form, serverErrors);
    showAlert(form, 'error', (result && result.message) || i18n().error);
  });
});
