/*
 * Filtering for the API reference.
 *
 * An enhancement, never a requirement: the page is fully rendered and fully
 * navigable with this file blocked. All it does is hide what does not match.
 *
 * It filters BOTH columns from one box, because a sidebar showing forty
 * endpoints while the page shows two is worse than no filter at all. Groups and
 * whole scopes disappear when nothing inside them survives, so the headings
 * never dangle over emptiness.
 */
(function () {
  'use strict';

  var box = document.getElementById('docs-filter');
  if (!box) return;

  var empty = document.getElementById('docs-empty');
  var ops = Array.prototype.slice.call(document.querySelectorAll('[data-op]'));
  var navOps = Array.prototype.slice.call(document.querySelectorAll('[data-nav-op]'));

  function apply(term) {
    var matches = 0;

    [ops, navOps].forEach(function (set) {
      set.forEach(function (el) {
        var hit = term === '' || (el.getAttribute('data-search') || '').indexOf(term) !== -1;
        el.classList.toggle('hide', !hit);
        if (set === ops && hit) matches++;
      });
    });

    // A heading with nothing under it reads as a bug, so containers follow
    // their children rather than being filtered on their own text.
    ['[data-group]', '[data-nav-group]', '[data-scope]', '[data-nav-scope]'].forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (box) {
        var visible = box.querySelector('[data-op]:not(.hide), [data-nav-op]:not(.hide)');
        box.classList.toggle('hide', !visible);
      });
    });

    if (empty) empty.classList.toggle('hide', matches !== 0 || term === '');
  }

  var timer;
  box.addEventListener('input', function () {
    // Debounced: a hundred endpoints is enough that filtering on every
    // keystroke is felt on a slow machine.
    clearTimeout(timer);
    timer = setTimeout(function () { apply(box.value.trim().toLowerCase()); }, 80);
  });

  // Escape clears, which is what a filter box is expected to do.
  box.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { box.value = ''; apply(''); }
  });
})();

/*
 * Scrollspy: which resource the reader is currently inside.
 *
 * An IntersectionObserver rather than a scroll handler, so nothing runs on
 * every frame while somebody flings through a hundred endpoints. The rail is
 * scrolled to follow only when the active item has actually left view, because
 * yanking a list somebody is reading is worse than letting it fall behind.
 *
 * Like the filter, this is an enhancement. With it blocked the rail is still a
 * complete list of working links; nothing simply lights up.
 */
(function () {
  'use strict';

  var targets = Array.prototype.slice.call(document.querySelectorAll('[data-spy]'));
  if (!targets.length || !('IntersectionObserver' in window)) return;

  var links = {};
  Array.prototype.forEach.call(document.querySelectorAll('[data-spy-link]'), function (a) {
    links[a.getAttribute('data-spy-link')] = a;
  });

  var rail = document.querySelector('.rail-list');
  var current = null;

  function light(id) {
    if (id === current || !links[id]) return;
    if (current && links[current]) links[current].classList.remove('on');
    current = id;
    links[id].classList.add('on');

    if (!rail) return;
    var a = links[id].getBoundingClientRect();
    var r = rail.getBoundingClientRect();
    if (a.top < r.top || a.bottom > r.bottom) {
      links[id].scrollIntoView({ block: 'nearest' });
    }
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) light(entry.target.getAttribute('data-spy'));
    });
  }, {
    // A band across the top of the screen: a heading counts as "where you are"
    // when it reaches the top, not when it first peeks in at the foot.
    rootMargin: '0px 0px -70% 0px',
    threshold: 0,
  });

  targets.forEach(function (t) { observer.observe(t); });
})();
