(() => {
  const select = document.querySelector('.activity-filter select[name="activity"]');
  if (!select || !select.form) return;

  const form = select.form;
  select.addEventListener('change', () => form.requestSubmit());
  form.querySelector('button').hidden = true;
})();
