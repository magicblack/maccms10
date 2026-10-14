(function () {
  document.querySelectorAll('[data-np2-shuffle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('.np2-row');
      var wrap = row && row.querySelector('.np2-cards');
      if (!wrap) return;
      var cards = Array.prototype.slice.call(wrap.children);
      for (var i = cards.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = cards[i]; cards[i] = cards[j]; cards[j] = t;
      }
      cards.forEach(function (el) { wrap.appendChild(el); });
    });
  });
})();

(function(){
  document.addEventListener('error', function(e){
    var img = e.target;
    if (!img || img.tagName !== 'IMG' || !img.closest('.np2-vgrid__pic, .np2-d__poster')) return;
    if (img.dataset.np2ph) return;
    img.dataset.np2ph = '1';
    img.style.display = 'none';
    var wrap = img.parentNode;
    if (wrap && !wrap.querySelector('.np2-poster-ph')) {
      wrap.insertAdjacentHTML('beforeend', '<svg class="np2-poster-ph" viewBox="0 0 90 120" aria-hidden="true"><rect x="1.5" y="1.5" width="87" height="117" rx="10" fill="#1a1c1d" stroke="currentColor" stroke-width="1.6"/><circle cx="45" cy="58" r="18" fill="none" stroke="currentColor" stroke-width="1.8"/><polygon points="40,50 40,66 58,58" fill="currentColor"/></svg>');
    }
  }, true);
})();
