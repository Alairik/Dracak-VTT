// Obsluha, která dřív bývala v atributech onclick / onsubmit / onchange přímo v HTML. Content-Security-Policy (includes/csp.php) inline atributy
// nepouští, proto se v šablonách píše jen data-atribut a tady je jeho chování:
//   <form data-potvrdit="Opravdu smazat?">          před odesláním se zeptá (confirm); Zrušit = neodešle se
//   <select data-odeslat-pri-zmene>                 po změně odešle formulář, ve kterém leží
//   <button data-tisk>                              otevře tiskový dialog
//   <a data-otevri="id">                             rozbalí <details id="id"> a odroluje k němu
(function () {
  'use strict';
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.getAttribute && f.hasAttribute('data-potvrdit') && !window.confirm(f.getAttribute('data-potvrdit'))) {
      e.preventDefault();
    }
  });
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.hasAttribute && el.hasAttribute('data-odeslat-pri-zmene') && el.form) {
      el.form.submit();
    }
  });
  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('[data-tisk]') : null;
    if (b) {
      window.print();
    }
    var o = e.target && e.target.closest ? e.target.closest('[data-otevri]') : null;
    if (o) {
      var cil = document.getElementById(o.getAttribute('data-otevri'));
      if (cil) {
        e.preventDefault();
        cil.open = true;
        cil.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        var pole = cil.querySelector('textarea');
        if (pole) { pole.focus(); }
      }
    }
  });
})();
