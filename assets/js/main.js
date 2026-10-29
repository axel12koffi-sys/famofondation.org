/* Fondation FAMO — interactions (zéro dépendance) */
(function () {
  'use strict';

  /* ----- Navigation mobile ----- */
  var burger = document.querySelector('.burger');
  var nav = document.querySelector('.site-nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ----- Menus déroulants : aria-expanded en phase avec l'affichage ----- */
  document.querySelectorAll('.nav-item').forEach(function (item) {
    var trigger = item.querySelector('.nav-link[aria-haspopup]');
    if (!trigger) return;
    var ouvrir = function () { trigger.setAttribute('aria-expanded', 'true'); };
    var fermer = function () { trigger.setAttribute('aria-expanded', 'false'); };
    item.addEventListener('mouseenter', ouvrir);
    item.addEventListener('mouseleave', fermer);
    item.addEventListener('focusin', ouvrir);
    item.addEventListener('focusout', function (ev) {
      if (!item.contains(ev.relatedTarget)) fermer();
    });
  });

  /* ----- Hero : séquence d'entrée orchestrée ----- */
  var hero = document.querySelector('.hero');
  if (hero) {
    requestAnimationFrame(function () { hero.classList.add('in-view'); });
  }

  /* ----- Révélations au scroll (IntersectionObserver) ----- */
  var revealables = document.querySelectorAll('.reveal, .reveal-stagger');
  if ('IntersectionObserver' in window && revealables.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('in-view');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.15 });
    revealables.forEach(function (el) { io.observe(el); });
  } else {
    revealables.forEach(function (el) { el.classList.add('in-view'); });
  }

  /* ----- Compteurs animés ----- */
  var counters = document.querySelectorAll('[data-count]');
  function animateCounter(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    var suffix = el.getAttribute('data-suffix') || '';
    var dur = 1600, start = null;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased) + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  if ('IntersectionObserver' in window && counters.length) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { animateCounter(e.target); cio.unobserve(e.target); }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { cio.observe(el); });
  } else {
    counters.forEach(function (el) {
      el.textContent = el.getAttribute('data-count') + (el.getAttribute('data-suffix') || '');
    });
  }

  /* ================= Système de dons ================= */
  var cfg = window.FAMO_CONFIG || {};
  var donBox = document.getElementById('don-module');
  /* Le bouton Zeffy (mode "zeffy") est statique dans le HTML de la page
     comment-aider.html — voir le script embed-form-script.min.js chargé
     juste après lui. Rien à faire ici pour ce mode : ça évite tout
     problème de timing avec le script tiers qui scanne le DOM au
     chargement. */

  /* Modes "stripe" / "paypal" / défaut : interface montants */
  var selectedAmount = null;
  var frequency = 'once';
  var statusEl = document.getElementById('don-selection');

  function annoncerSelection() {
    if (!statusEl) return;
    var freqTxt = frequency === 'monthly' ? 'don mensuel' : 'don unique';
    statusEl.textContent = selectedAmount
      ? 'Montant sélectionné : ' + selectedAmount + ' dollars, ' + freqTxt + '.'
      : freqTxt.charAt(0).toUpperCase() + freqTxt.slice(1) + ' sélectionné.';
  }

  document.querySelectorAll('.freq-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.freq-btn').forEach(function (b) {
        b.classList.remove('selected');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('selected');
      btn.setAttribute('aria-pressed', 'true');
      frequency = btn.getAttribute('data-freq');
      annoncerSelection();
    });
  });

  var customInput = document.getElementById('don-custom');
  document.querySelectorAll('.amount').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.amount').forEach(function (b) {
        b.classList.remove('selected');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('selected');
      btn.setAttribute('aria-pressed', 'true');
      selectedAmount = btn.getAttribute('data-amount');
      if (customInput) customInput.value = '';
      annoncerSelection();
    });
  });
  if (customInput) {
    customInput.addEventListener('input', function () {
      document.querySelectorAll('.amount').forEach(function (b) {
        b.classList.remove('selected');
        b.setAttribute('aria-pressed', 'false');
      });
      selectedAmount = customInput.value ? String(parseInt(customInput.value, 10)) : null;
      annoncerSelection();
    });
  }

  var giveBtn = document.getElementById('give-btn');
  if (giveBtn) {
    giveBtn.addEventListener('click', function (ev) {
      var amount = selectedAmount;

      /* Stripe : lien du montant selon la fréquence, sinon lien "montant libre" */
      if (cfg.mode === 'stripe' && cfg.stripe) {
        var table = (frequency === 'monthly' ? cfg.stripe.monthly : cfg.stripe.once) || {};
        var link = (amount && table[amount]) || cfg.stripe.custom || '';
        if (link) {
          ev.preventDefault();
          window.open(link, '_blank', 'noopener');
          return;
        }
      }

      /* PayPal : bouton hébergé, montant pré-rempli si choisi */
      if (cfg.mode === 'paypal' && cfg.paypal && cfg.paypal.hostedButtonId) {
        ev.preventDefault();
        var url = 'https://www.paypal.com/donate?hosted_button_id=' +
                  encodeURIComponent(cfg.paypal.hostedButtonId);
        if (amount) url += '&amount=' + encodeURIComponent(amount);
        window.open(url, '_blank', 'noopener');
        return;
      }

      /* Défaut : e-mail à la fondation avec le montant en objet */
      if (amount) {
        ev.preventDefault();
        var mail = (cfg.contactEmail || 'contact@famofondation.org');
        var freqTxt = frequency === 'monthly' ? '%20mensuel' : '';
        window.location.href = 'mailto:' + mail +
          '?subject=Don' + freqTxt + '%20de%20' + amount +
          '%24%20%C3%A0%20la%20Fondation%20FAMO';
      }
      /* Sans montant choisi : le mailto par défaut du bouton s'applique. */
    });
  }
})();

/* ---------- Galerie : filtres par lieu + lightbox ---------- */
(function () {
  var wrap = document.querySelector('.gallery-photos');
  if (!wrap) return;
  var en = document.documentElement.lang === 'en';
  var figs = Array.prototype.slice.call(wrap.querySelectorAll('figure'));

  document.querySelectorAll('.gallery-filters .chip').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = b.getAttribute('data-filter');
      document.querySelectorAll('.gallery-filters .chip').forEach(function (c) {
        var on = c === b;
        c.classList.toggle('is-active', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      figs.forEach(function (fig) {
        fig.hidden = !(f === 'all' || fig.getAttribute('data-place') === f);
      });
    });
  });

  var lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-modal', 'true');
  lb.setAttribute('aria-label', en ? 'Photo viewer' : 'Visionneuse de photos');
  lb.innerHTML = '<button type="button" class="lb-close" aria-label="' + (en ? 'Close' : 'Fermer') + '">&times;</button>' +
    '<button type="button" class="lb-prev" aria-label="' + (en ? 'Previous photo' : 'Photo précédente') + '">&#8249;</button>' +
    '<img alt=""><div class="lightbox-cap" aria-live="polite"></div>' +
    '<button type="button" class="lb-next" aria-label="' + (en ? 'Next photo' : 'Photo suivante') + '">&#8250;</button>';
  document.body.appendChild(lb);
  var img = lb.querySelector('img'), cap = lb.querySelector('.lightbox-cap');
  var idx = 0, opener = null;

  function visible() { return figs.filter(function (f) { return !f.hidden; }); }
  function show(i) {
    var v = visible(); if (!v.length) return;
    idx = (i + v.length) % v.length;
    var a = v[idx].querySelector('a'), t = v[idx].querySelector('img');
    img.src = a.getAttribute('href'); img.alt = t.alt;
    cap.textContent = v[idx].querySelector('figcaption').textContent + ' — ' + (idx + 1) + '/' + v.length;
  }
  function open(fig, trigger) {
    opener = trigger; show(visible().indexOf(fig));
    lb.classList.add('is-open'); document.body.style.overflow = 'hidden';
    lb.querySelector('.lb-close').focus();
  }
  function close() {
    lb.classList.remove('is-open'); document.body.style.overflow = '';
    if (opener) opener.focus();
  }
  wrap.addEventListener('click', function (e) {
    var a = e.target.closest('[data-lightbox]');
    if (!a) return;
    e.preventDefault(); open(a.closest('figure'), a);
  });
  lb.querySelector('.lb-close').addEventListener('click', close);
  lb.querySelector('.lb-prev').addEventListener('click', function () { show(idx - 1); });
  lb.querySelector('.lb-next').addEventListener('click', function () { show(idx + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
  document.addEventListener('keydown', function (e) {
    if (!lb.classList.contains('is-open')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === 'ArrowRight') show(idx + 1);
    else if (e.key === 'Tab') {
      var f = lb.querySelectorAll('button'), first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
})();

/* ---------- Formulaires (newsletter / contact) : envoi HTTPS sans quitter la page ---------- */
(function () {
  var en = document.documentElement.lang === 'en';
  var T = en
    ? { ok: 'Thank you! Your message has been received.', sending: 'Sending…',
        email: 'Please enter a valid email address.', err: 'Something went wrong. Please try again or write to contact@famofondation.org.' }
    : { ok: 'Merci ! Votre message a bien été reçu.', sending: 'Envoi en cours…',
        email: 'Veuillez saisir une adresse e-mail valide.', err: 'Une erreur est survenue. Réessayez ou écrivez à contact@famofondation.org.' };

  function status(form, cls, text) {
    var s = form.querySelector('.form-status');
    if (!s) {
      s = document.createElement('p');
      s.setAttribute('role', 'status');
      form.appendChild(s);
    }
    s.className = 'form-status ' + cls;
    s.textContent = text;
  }

  document.querySelectorAll('form[data-ajax-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.fetch || !window.FormData) return; // repli : envoi classique (redirection)
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      status(form, 'ok', T.sending);
      fetch(form.action, {
        method: 'POST', body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) { form.reset(); status(form, 'ok', T.ok); }
        else status(form, 'err', d.code === 'email' ? T.email : T.err);
      }).catch(function () { status(form, 'err', T.err); })
        .then(function () { if (btn) btn.disabled = false; });
    });
  });

  // Repli sans JavaScript : message après redirection (?envoi=ok|erreur)
  var m = /[?&]envoi=(ok|erreur)/.exec(location.search);
  var f = document.querySelector('form[data-ajax-form]');
  if (m && f) status(f, m[1] === 'ok' ? 'ok' : 'err', m[1] === 'ok' ? T.ok : T.err);
})();
