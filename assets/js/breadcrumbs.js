(function () {
  'use strict';

  if (window.fbcBreadcrumbs) {
    return;
  }

  var observed = [];

  function connected(node) {
    if (!node) {
      return false;
    }
    if (typeof node.isConnected === 'boolean') {
      return node.isConnected;
    }
    return document.documentElement.contains(node);
  }

  function readGap(list) {
    var style = window.getComputedStyle(list);
    var gap = parseFloat(style.columnGap || style.gap || '0');
    return isNaN(gap) ? 0 : gap;
  }

  function measure(el) {
    var wasHidden = el.hidden;
    if (wasHidden) {
      el.hidden = false;
      el.style.position = 'absolute';
      el.style.visibility = 'hidden';
      el.style.pointerEvents = 'none';
    }
    var width = el.getBoundingClientRect().width;
    if (wasHidden) {
      el.hidden = true;
      el.style.position = '';
      el.style.visibility = '';
      el.style.pointerEvents = '';
    }
    return width;
  }

  function hiddenKey(crumbs, hideCount) {
    var parts = [];
    var last = crumbs.length - 1;
    for (var i = 1; i <= hideCount && i < last; i++) {
      parts.push((crumbs[i].getAttribute('data-fbc-label') || '') + '|' + (crumbs[i].getAttribute('data-fbc-url') || ''));
    }
    return parts.join('\n');
  }

  function closeMenu(ellipsis) {
    if (!ellipsis) {
      return;
    }
    var menu = ellipsis.querySelector('.fbc-menu');
    var button = ellipsis.querySelector('.fbc-ellipsis-btn');
    if (menu) {
      menu.hidden = true;
    }
    if (button) {
      button.setAttribute('aria-expanded', 'false');
    }
  }

  function closeAll(except) {
    var open = document.querySelectorAll('.fbc-ellipsis-btn[aria-expanded="true"]');
    for (var i = 0; i < open.length; i++) {
      var ellipsis = open[i].closest('[data-fbc-ellipsis]');
      if (except && ellipsis === except) {
        continue;
      }
      closeMenu(ellipsis);
    }
  }

  function placeMenu(ellipsis, menu) {
    menu.style.left = '0';
    menu.style.right = 'auto';
    var rect = menu.getBoundingClientRect();
    if (rect.right > window.innerWidth - 8 && rect.left > 8) {
      menu.style.left = 'auto';
      menu.style.right = '0';
    }
  }

  function menuItems(menu) {
    return menu.querySelectorAll('[role="menuitem"]');
  }

  function focusAt(items, index) {
    if (!items.length) {
      return;
    }
    if (index < 0) {
      index = 0;
    }
    if (index >= items.length) {
      index = items.length - 1;
    }
    items[index].focus();
  }

  function fillMenu(menu, crumbs, hideCount) {
    var key = hiddenKey(crumbs, hideCount);
    if (menu.getAttribute('data-key') === key) {
      return;
    }
    var focusedInside = menu.contains(document.activeElement);
    menu.setAttribute('data-key', key);
    menu.textContent = '';

    var last = crumbs.length - 1;
    for (var i = 1; i <= hideCount && i < last; i++) {
      var label = crumbs[i].getAttribute('data-fbc-label') || '';
      var url = crumbs[i].getAttribute('data-fbc-url') || '';
      if (!label) {
        continue;
      }
      var node;
      if (url) {
        node = document.createElement('a');
        node.href = url;
        node.className = 'fbc-menu-link fbc-link';
      } else {
        node = document.createElement('span');
        node.className = 'fbc-menu-link';
      }
      node.setAttribute('role', 'menuitem');
      node.tabIndex = -1;
      node.textContent = label;
      menu.appendChild(node);
    }

    if (focusedInside) {
      var button = menu.parentNode && menu.parentNode.querySelector('.fbc-ellipsis-btn');
      if (button) {
        button.focus();
      }
    }
  }

  function totalWidth(widths, hideCount, ellipsisWidth, gap) {
    var count = 0;
    var sum = 0;
    var last = widths.length - 1;
    for (var i = 0; i < widths.length; i++) {
      var hideThis = i > 0 && i < last && (i - 1) < hideCount;
      if (hideThis) {
        continue;
      }
      if (count > 0) {
        sum += gap;
      }
      sum += widths[i];
      count++;
    }
    if (hideCount > 0) {
      sum += gap + ellipsisWidth;
    }
    return sum;
  }

  function layout(nav) {
    if (!connected(nav)) {
      return;
    }

    var list = nav.querySelector('.fbc-list');
    var ellipsis = nav.querySelector('[data-fbc-ellipsis]');
    if (!list) {
      return;
    }

    nav.classList.add('is-ready');
    var crumbs = list.querySelectorAll('[data-fbc-item]');
    var widths = [];
    var i;
    for (i = 0; i < crumbs.length; i++) {
      crumbs[i].hidden = false;
      crumbs[i].classList.remove('is-truncated');
      widths.push(measure(crumbs[i]));
    }

    if (!ellipsis || crumbs.length < 3) {
      if (ellipsis) {
        ellipsis.hidden = true;
        closeMenu(ellipsis);
      }
      var shortSum = 0;
      var shortGap = readGap(list);
      for (var n = 0; n < widths.length; n++) {
        if (n > 0) {
          shortSum += shortGap;
        }
        shortSum += widths[n];
      }
      var shortTight = nav.clientWidth > 0 && shortSum > nav.clientWidth + 1 && crumbs.length > 0;
      nav.classList.toggle('is-tight', shortTight);
      if (shortTight) {
        crumbs[crumbs.length - 1].classList.add('is-truncated');
      }
      return;
    }

    var available = nav.clientWidth;
    if (available <= 0) {
      ellipsis.hidden = true;
      return;
    }

    var maxMiddle = parseInt(nav.getAttribute('data-max-middle') || '0', 10);
    if (isNaN(maxMiddle) || maxMiddle < 0) {
      maxMiddle = 0;
    }

    var middleCount = crumbs.length - 2;
    var hideCount = 0;
    if (maxMiddle > 0 && middleCount > maxMiddle) {
      hideCount = middleCount - maxMiddle;
    }

    var gap = readGap(list);
    var ellipsisWidth = measure(ellipsis);
    var guard = 0;
    while (hideCount < middleCount && totalWidth(widths, hideCount, ellipsisWidth, gap) > available + 1 && guard < 24) {
      hideCount++;
      guard++;
    }

    var last = crumbs.length - 1;
    for (i = 0; i < crumbs.length; i++) {
      var hideThis = i > 0 && i < last && (i - 1) < hideCount;
      crumbs[i].hidden = hideThis;
    }

    var menu = ellipsis.querySelector('.fbc-menu');
    if (hideCount <= 0) {
      ellipsis.hidden = true;
      closeMenu(ellipsis);
      if (menu) {
        menu.textContent = '';
        menu.setAttribute('data-key', '');
      }
    } else {
      ellipsis.hidden = false;
      if (menu) {
        fillMenu(menu, crumbs, hideCount);
        if (!menu.hidden) {
          placeMenu(ellipsis, menu);
        }
      }
    }

    var stillOverflows = totalWidth(widths, hideCount, ellipsisWidth, gap) > available + 1;
    nav.classList.toggle('is-tight', stillOverflows);
    if (stillOverflows) {
      crumbs[last].classList.add('is-truncated');
    }
  }

  function schedule(nav) {
    if (nav.getAttribute('data-fbc-frame') === '1') {
      return;
    }
    nav.setAttribute('data-fbc-frame', '1');
    window.requestAnimationFrame(function () {
      if (connected(nav)) {
        nav.removeAttribute('data-fbc-frame');
        layout(nav);
      }
    });
  }

  function setup(nav) {
    if (nav.getAttribute('data-fbc-ready') === '1') {
      return;
    }
    nav.setAttribute('data-fbc-ready', '1');

    if (typeof window.ResizeObserver === 'function') {
      var observer = new ResizeObserver(function () {
        schedule(nav);
      });
      observer.observe(nav);
      observed.push({ nav: nav, observer: observer });
    }

    schedule(nav);
  }

  function boot() {
    var navs = document.querySelectorAll('nav.fbc:not([data-fbc-ready])');
    for (var i = 0; i < navs.length; i++) {
      setup(navs[i]);
    }
  }

  function relayout() {
    var navs = document.querySelectorAll('nav.fbc[data-fbc-ready]');
    for (var i = 0; i < navs.length; i++) {
      schedule(navs[i]);
    }
  }

  function sweep() {
    observed = observed.filter(function (entry) {
      if (connected(entry.nav)) {
        return true;
      }
      entry.observer.disconnect();
      return false;
    });
  }

  function openMenu(ellipsis) {
    var menu = ellipsis.querySelector('.fbc-menu');
    var button = ellipsis.querySelector('.fbc-ellipsis-btn');
    if (!menu || !button || ellipsis.hidden) {
      return;
    }
    closeAll(ellipsis);
    menu.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    placeMenu(ellipsis, menu);
  }

  function toggleMenu(button) {
    var ellipsis = button.closest('[data-fbc-ellipsis]');
    if (!ellipsis || ellipsis.hidden) {
      return;
    }
    if (button.getAttribute('aria-expanded') === 'true') {
      closeMenu(ellipsis);
      return;
    }
    openMenu(ellipsis);
  }

  function isEscape(event) {
    return event.key === 'Escape' || event.key === 'Esc';
  }

  function onClick(event) {
    if (!event.target || !event.target.closest) {
      return;
    }
    var button = event.target.closest('.fbc-ellipsis-btn');
    if (button) {
      toggleMenu(button);
      return;
    }
    if (event.target.closest('.fbc-menu')) {
      return;
    }
    closeAll(null);
  }

  function onKeydown(event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }

    var button = target.closest('.fbc-ellipsis-btn');
    if (button && event.key === 'ArrowDown') {
      event.preventDefault();
      var fromButton = button.closest('[data-fbc-ellipsis]');
      if (!fromButton) {
        return;
      }
      openMenu(fromButton);
      focusAt(menuItems(fromButton.querySelector('.fbc-menu')), 0);
      return;
    }

    var menu = target.closest('.fbc-menu');
    if (!menu) {
      if (isEscape(event)) {
        var expanded = document.querySelector('.fbc-ellipsis-btn[aria-expanded="true"]');
        if (expanded) {
          closeMenu(expanded.closest('[data-fbc-ellipsis]'));
          expanded.focus();
        }
      }
      return;
    }

    var items = menuItems(menu);
    var index = Array.prototype.indexOf.call(items, target);
    var ellipsis = menu.closest('[data-fbc-ellipsis]');
    var menuButton = ellipsis ? ellipsis.querySelector('.fbc-ellipsis-btn') : null;

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      focusAt(items, index + 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (index <= 0 && menuButton) {
        menuButton.focus();
      } else {
        focusAt(items, index - 1);
      }
    } else if (event.key === 'Home') {
      event.preventDefault();
      focusAt(items, 0);
    } else if (event.key === 'End') {
      event.preventDefault();
      focusAt(items, items.length - 1);
    } else if (isEscape(event)) {
      event.preventDefault();
      closeMenu(ellipsis);
      if (menuButton) {
        menuButton.focus();
      }
    } else if (event.key === 'Tab') {
      closeMenu(ellipsis);
    }
  }

  function start() {
    if (start.done) {
      return;
    }
    start.done = true;

    boot();
    document.addEventListener('click', onClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', relayout);

    var changes = new MutationObserver(function () {
      sweep();
      boot();
    });
    changes.observe(document.documentElement, { childList: true, subtree: true });

    if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
      document.fonts.ready.then(relayout);
    }
  }

  window.fbcBreadcrumbs = {
    refresh: function () {
      boot();
      relayout();
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
    boot();
  } else {
    start();
  }
})();
