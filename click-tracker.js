(function () {
  'use strict';

  var endpoint = 'track_click.php';

  function normalizeLabel(raw) {
    if (!raw) return '';
    var s = String(raw);
    s = s.replace(/\s+/g, ' ').trim();
    if (s.length > 200) s = s.slice(0, 200);
    return s;
  }

  function shouldIgnoreHref(href) {
    if (!href) return true;
    if (href[0] === '#') return true;

    var lower = href.toLowerCase();
    if (lower.indexOf('javascript:') === 0) return true;
    if (lower.indexOf('mailto:') === 0) return true;
    if (lower.indexOf('tel:') === 0) return true;

    return false;
  }

  function sendClick(targetUrl, label) {
    try {
      var payload = {
        target: targetUrl,
        source: window.location.pathname + window.location.search,
        label: label || ''
      };

      var bodyJson = JSON.stringify(payload);

      if (navigator.sendBeacon) {
        var blob = new Blob([bodyJson], { type: 'application/json' });
        navigator.sendBeacon(endpoint, blob);
        return;
      }

      // Fallback
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: bodyJson,
        keepalive: true,
        credentials: 'same-origin'
      }).catch(function () {});
    } catch (e) {
      // ignore
    }
  }

  document.addEventListener(
    'click',
    function (e) {
      try {
        var el = e.target;
        while (el && el.tagName !== 'A') el = el.parentElement;
        if (!el) return;

        var href = el.getAttribute('href');
        if (shouldIgnoreHref(href)) return;

        // Normalize to absolute URL (stores full URL for external too)
        var absUrl = new URL(href, window.location.href).toString();

        // Prevent recursive self-tracking
        if (absUrl.indexOf(endpoint) !== -1) return;

        var label = '';
        var explicit = el.getAttribute('data-track-label');
        if (explicit) {
          label = normalizeLabel(explicit);
        } else {
          label = normalizeLabel(el.getAttribute('title') || el.textContent || '');
        }

        sendClick(absUrl, label);
      } catch (err) {
        // ignore
      }
    },
    true
  );
})();
