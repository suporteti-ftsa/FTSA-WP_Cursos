document.addEventListener('click', function (e) {
  if (!e.target.classList.contains('ftsa-toggle-secret')) return;
  e.preventDefault();
  const input = e.target.parentElement.querySelector('input');
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
});
