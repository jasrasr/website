(() => {
  const checkbox = document.querySelector('.activity-filter input[name="hide_me"]');
  if (!checkbox || !checkbox.form) return;

  const form = checkbox.form;
  checkbox.addEventListener('change', () => form.requestSubmit());
  form.querySelector('button').hidden = true;
})();
