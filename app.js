// Photo upload: click/drag-drop, max 4 images, live previews
const drop = document.getElementById('drop'), input = document.getElementById('photos'), prev = document.getElementById('previews');
if (drop && input) {
  const show = () => {
    prev.innerHTML = '';
    [...input.files].slice(0, 4).forEach(f => {
      if (!/^image\/(png|jpe?g)$/.test(f.type) || f.size > 10 * 1048576) return;
      const i = document.createElement('img'); i.src = URL.createObjectURL(f); i.alt = f.name; prev.appendChild(i);
    });
  };
  ['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
  drop.addEventListener('drop', e => { input.files = e.dataTransfer.files; show(); });
  drop.addEventListener('click', e => { if (e.target !== input) input.click(); });
  input.addEventListener('change', show);
}
// Pin exact location (browser geolocation → hidden lat/lng)
const pin = document.getElementById('pin');
if (pin) pin.addEventListener('click', () => {
  const m = document.getElementById('pinmsg');
  if (!navigator.geolocation) { m.textContent = 'Location is not supported on this device.'; return; }
  m.textContent = 'Finding your location...';
  navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('lat').value = p.coords.latitude.toFixed(7);
    document.getElementById('lng').value = p.coords.longitude.toFixed(7);
    m.textContent = 'Location pinned ✔';
  }, () => m.textContent = 'Could not get location. Allow location access and try again.');
});
// Live filter for report rows (My Reports search + top search)
['rsearch', 'gsearch'].forEach(id => {
  const s = document.getElementById(id);
  if (s) s.addEventListener('input', () => {
    const v = s.value.toLowerCase();
    document.querySelectorAll('.row[data-s]').forEach(r => r.style.display = r.dataset.s.includes(v) ? '' : 'none');
  });
});
// Registration: confirm-password check
const reg = document.querySelector('form input[name=confirm]');
if (reg) reg.form.addEventListener('submit', e => {
  const f = e.target;
  if (f.password.value !== f.confirm.value) { e.preventDefault(); f.confirm.setCustomValidity('Passwords do not match'); f.confirm.reportValidity(); }
  else f.confirm.setCustomValidity('');
});
