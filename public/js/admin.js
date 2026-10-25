/* Admin panel interactions */
(function () {
  'use strict';

  // Mobile sidebar
  document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('sidebar');
    if (e.target.closest('[data-toggle-sidebar]')) { sidebar.classList.toggle('open'); return; }
    if (sidebar && sidebar.classList.contains('open') && !e.target.closest('#sidebar')) sidebar.classList.remove('open');
  });

  // Confirm destructive actions
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Auto-hide success flash
  document.querySelectorAll('[data-autohide]').forEach((el) => {
    setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = 0; setTimeout(() => el.remove(), 500); }, 4000);
  });

  // Tabs (hash-aware so validation redirects return to the same tab)
  document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const buttons = tabs.querySelectorAll('[data-tab]');
    const input = document.querySelector('input[name=tab]');
    const show = (id) => {
      buttons.forEach((b) => b.classList.toggle('active', b.dataset.tab === id));
      document.querySelectorAll('[data-tab-panel]').forEach((p) => p.classList.toggle('active', p.dataset.tabPanel === id));
      if (input) input.value = id;
    };
    buttons.forEach((b) => b.addEventListener('click', (e) => { e.preventDefault(); show(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab); }));
    const initial = location.hash.slice(1);
    show([...buttons].some((b) => b.dataset.tab === initial) ? initial : buttons[0].dataset.tab);
  });

  // Repeaters: <div data-repeater data-next="3"> rows + <template> + [data-add-row]
  document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add-row]');
    if (add) {
      const wrap = document.querySelector(add.dataset.addRow);
      const tpl = wrap.querySelector('template');
      const index = parseInt(wrap.dataset.next || '0', 10);
      wrap.dataset.next = index + 1;
      const html = tpl.innerHTML.replace(/__INDEX__/g, index);
      wrap.querySelector('[data-rows]').insertAdjacentHTML('beforeend', html);
    }
    const remove = e.target.closest('[data-remove-row]');
    if (remove) remove.closest('[data-row]').remove();
  });

  // Image previews
  document.addEventListener('change', (e) => {
    const input = e.target;
    if (!input.matches('input[type=file][data-preview]')) return;
    const img = document.querySelector(input.dataset.preview);
    if (img && input.files[0]) img.src = URL.createObjectURL(input.files[0]);
  });

  // Show/hide blocks based on a select value: data-show-when="#type=cod,manual"
  function syncConditional() {
    document.querySelectorAll('[data-show-when]').forEach((el) => {
      const [sel, values] = el.dataset.showWhen.split('=');
      const source = document.querySelector(sel);
      if (!source) return;
      el.style.display = values.split(',').includes(source.value) ? '' : 'none';
    });
  }
  document.addEventListener('change', syncConditional);
  syncConditional();
})();
