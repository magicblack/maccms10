(function () {
  function focusGbookInput() {
    var input = document.querySelector('[data-component="gbook_form"] textarea[name="gbook_content"]');
    if (!input) {
      input = document.querySelector('.pb-gbook-form textarea[name="gbook_content"]');
    }
    if (input) {
      input.focus();
      try {
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (e) {
        input.scrollIntoView(true);
      }
    }
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-gbook-focus]');
    if (!btn) {
      return;
    }
    ev.preventDefault();
    focusGbookInput();
  });

  document.querySelectorAll('.pb-gbook-form__captcha-img').forEach(function (img) {
    img.addEventListener('click', function () {
      img.src = img.src.split('?')[0] + '?v=' + Date.now();
    });
  });
})();
