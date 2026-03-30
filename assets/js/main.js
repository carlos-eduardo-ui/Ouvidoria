/**
 * main.js — Interações UI gerais
 * Ouvidoria Municipal
 *
 * Responsabilidades:
 *   - Navbar scroll behavior
 *   - Back-to-top button
 *   - Scroll reveal (IntersectionObserver)
 *   - Active nav link by section
 *   - Counter animation (hero stats)
 */

$(document).ready(() => {

  /* ── Navbar scroll ──────────────────── */
  const $nav = $('#mainNav');
  $(window).on('scroll.navbar', Utils.debounce(() => {
    $nav.toggleClass('scrolled', window.scrollY > 60);
  }, 80));

  /* ── Back to Top ────────────────────── */
  const $backTop = $('#backTop');
  $(window).on('scroll.backtop', Utils.debounce(() => {
    $backTop.toggleClass('visible', window.scrollY > 400);
  }, 80));
  $backTop.on('click', () => $('html, body').animate({ scrollTop: 0 }, 500, 'swing'));

  /* ── Active nav link ────────────────── */
  const sections = ['sobre', 'servicos', 'manifestacao', 'consulta', 'faq'];
  $(window).on('scroll.navlinks', Utils.debounce(() => {
    const scrollPos = window.scrollY + 120;
    sections.forEach(id => {
      const $section = $(`#${id}`);
      if (!$section.length) return;
      const top    = $section.offset().top;
      const bottom = top + $section.outerHeight();
      const $link  = $(`.nav-link[href="#${id}"]`);
      if (scrollPos >= top && scrollPos < bottom) {
        $('.nav-link').removeClass('active');
        $link.addClass('active');
      }
    });
  }, 80));

  /* ── Smooth scroll for anchor links ── */
  $('a[href^="#"]').not('[data-bs-toggle]').on('click', function (e) {
    const target = $(this).attr('href');
    if (target === '#' || !$(target).length) return;
    e.preventDefault();
    const offset = $(target).offset().top - 80;
    $('html, body').animate({ scrollTop: offset }, 600, 'swing');
  });

  /* ── Scroll Reveal ──────────────────── */
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }, i * 80);
      }
    });
  }, { threshold: 0.12 });

  // Add reveal class to elements
  const revealSelectors = [
    '.service-card', '.sobre-block', '.principio-item',
    '.consulta-card', '.custom-accordion-item',
    '.section-title', '.section-label', '.section-text',
  ];
  revealSelectors.forEach(sel => {
    document.querySelectorAll(sel).forEach(el => {
      el.classList.add('reveal');
      revealObserver.observe(el);
    });
  });

  /* ── Counter Animation (hero stats) ── */
  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const $el = $(entry.target);
        Utils.animateCounter($el);
        counterObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('.stat-number').forEach(el => {
    counterObserver.observe(el);
  });

  /* ── Tooltip initialization ─────────── */
  const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltipEls.forEach(el => new bootstrap.Tooltip(el));

  /* ── Highlight current year in footer ─ */
  const $copy = $('.footer-bottom span').first();
  const text = $copy.text().replace(/\d{4}/, new Date().getFullYear());
  $copy.text(text);

  /* ── Log environment info (dev only) ── */
  if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('%c[Ouvidoria] Dev mode — API calls are mocked.', 'color:#c9a84c;font-weight:bold');
    console.log('%cPara usar a API real, edite assets/js/ajax.js e descomente os blocos de produção.', 'color:#888');
  }

});
