(function () {
  var KEY = 'np2_age_ok';
  var gate = document.getElementById('np2-age-gate');
  var btn = document.getElementById('np2-age-enter');
  if (!gate) return;
  function ok() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
  function pass() {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
    gate.hidden = true;
    document.body.classList.remove('np2-age-lock');
  }
  if (ok()) { gate.hidden = true; return; }
  gate.hidden = false;
  document.body.classList.add('np2-age-lock');
  btn && btn.addEventListener('click', pass);
})();
