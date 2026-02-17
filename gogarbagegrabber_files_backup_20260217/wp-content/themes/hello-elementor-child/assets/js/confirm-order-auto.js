// confirm-order-auto.js (hardened)
// Client-side confirmation automation (Option A).
// - Reads qp_order_payload from sessionStorage/localStorage when the customer lands on the confirmation page.
// - Enriches the order with human-readable instructions based on pickup.location.
// - POSTS the order to a Make webhook URL (or logs a message if none is configured).
// - Shows a friendly confirmation UI and prevents duplicate posts by setting qp_order_posted.
//
// IMPORTANT: replace the MAKE_WEBHOOK_URL value below with your Make webhook URL before deploying.
// For better security, consider using a server-side forwarding endpoint instead (Option B).

(function () {
  'use strict';

  // Guard: prevent double initialization on the same page (if script loaded twice)
  if (window.__qp_confirm_auto_ran) {
    console.log('qp: confirm-order-auto already initialized, skipping duplicate init.');
    return;
  }
  window.__qp_confirm_auto_ran = true;

  // CONFIG: Set your Make webhook URL here (client-side). Replace the placeholder.
  const MAKE_WEBHOOK_URL = 'https://hook.make.com/REPLACE_WITH_YOUR_WEBHOOK';

  // If you want to POST to your server endpoint instead, set SERVER_ORDER_ENDPOINT and leave MAKE_WEBHOOK_URL blank:
  const SERVER_ORDER_ENDPOINT = ''; // optional; recommended if you want to keep the webhook secret server-side

  // Utility: safe JSON.parse
  function safeParse(s) {
    try { return s ? JSON.parse(s) : null; } catch (e) { return null; }
  }

  // Read stored order from sessionStorage or localStorage (prefer qp_order_payload)
  function readStoredOrder() {
    try {
      var s = sessionStorage.getItem('qp_order_payload');
      if (s) return safeParse(s);
    } catch (e) {}
    try {
      var s2 = sessionStorage.getItem('qp_instant_quote_payload');
      // don't auto-post instant-quote-only payloads — they are not full orders
      // but return them so we can display a summary if needed (we'll not auto-post).
      if (s2) return { __qp_only_instant_quote: true, payload: safeParse(s2) };
    } catch (e) {}
    try {
      var l = localStorage.getItem('qp_order_payload');
      if (l) return safeParse(l);
    } catch (e) {}
    try {
      var l2 = localStorage.getItem('qp_instant_quote_payload');
      if (l2) return { __qp_only_instant_quote: true, payload: safeParse(l2) };
    } catch (e) {}
    return null;
  }

  function locationInstructions(location) {
    if (!location) return '';
    const loc = String(location).toLowerCase();
    if (loc === 'curb') {
      return 'Please place items on the curb at least 30 minutes before the start of your scheduled time block.';
    }
    if (loc === 'garage') {
      return 'Please make sure the item is clear and accessible inside the garage. Be available to provide access. If electrical, unplug and disconnect. For fridges/freezers: empty all food and leave only the appliance and its components.';
    }
    if (loc === 'inside' || loc === 'in-house' || loc === 'house') {
      return 'Please ensure the item is accessible and unplugged if applicable. If there are many stairs or difficult access the contractor may charge extra.';
    }
    return '';
  }

  // Build enriched payload to send to Make / server
  function buildEnrichedOrder(order) {
    if (!order) return null;
    // if the caller passed an object with wrapper { __qp_only_instant_quote: true, payload: {...} }, return null
    if (order.__qp_only_instant_quote && order.payload) return null;

    const cloned = JSON.parse(JSON.stringify(order));
    const loc = (cloned.pickup && cloned.pickup.location) ? cloned.pickup.location : (cloned.pickup_location || '');
    cloned.instructions = locationInstructions(loc);
    cloned.human = {
      pickup_window: cloned.pickup ? (((cloned.pickup.date || '') + ' ' + (cloned.pickup.time_block || '')).trim()) : '',
      contact_email: cloned.customer ? (cloned.customer.email || '') : '',
      contact_name: cloned.customer ? (cloned.customer.name || '') : '',
    };
    return cloned;
  }

  // Insert UI into a sensible checkout/confirmation container if available
  function insertUiMessage(html) {
    // Prefer specific order/checkout/thank-you areas only
    var preferredSelectors = [
      '.woocommerce-order',              // order/thank-you display
      '.woocommerce-order-received',     // some themes
      '#order_review',                   // checkout area
      '.woocommerce-checkout',           // checkout page wrapper
      '.entry-content .woocommerce'      // fallback inside main content that contains Woo elements
    ];

    var container = null;
    for (var i = 0; i < preferredSelectors.length; i++) {
      container = document.querySelector(preferredSelectors[i]);
      if (container) break;
    }

    // If existing confirmation widget is present, reuse/move it into the preferred container
    var existing = document.getElementById('qp-confirmation-widget');
    if (existing && container && existing.parentNode !== container) {
      try { container.insertBefore(existing, container.firstChild); } catch (e) { /* ignore */ }
    }

    // If we have a preferred container, use that
    if (container) {
      if (!existing) {
        existing = document.createElement('div');
        existing.id = 'qp-confirmation-widget';
        existing.style.maxWidth = '980px';
        existing.style.margin = '18px auto';
        existing.style.padding = '12px';
      }
      existing.innerHTML = html;
      // ensure the element is inside the container
      if (existing.parentNode !== container) {
        try { container.insertBefore(existing, container.firstChild); } catch (e) { /* ignore */ }
      }
      return existing;
    }

    // No preferred container found — avoid inserting into document.body to prevent duplicates.
    // Optionally log so we know UI couldn't be attached safely.
    console.log('qp: no order/checkout container found — skipping insertion of confirmation UI to avoid duplicates.');
    return null;
  }

  // Show initial summary while processing
  function showProcessing(order) {
    var id = order && (order.order_id || order.id || '—');
    const html = '<div style="padding:12px;border-radius:10px;background:#fffbe9;border:1px solid #ffe2a8">'
      + '<strong>Scheduling your pickup…</strong>'
      + '<div style="margin-top:8px">Order: <strong>' + escapeHtml(id) + '</strong></div>'
      + '<div style="margin-top:6px;color:#666">We are sending your booking details. Please do not close this page.</div>'
      + '</div>';
    insertUiMessage(html);
  }

  function showSuccess(order) {
    var pickupStr = '';
    if (order && order.pickup) {
      pickupStr = escapeHtml(((order.pickup.date || '') + ' ' + (order.pickup.time_block || '')).trim());
    }
    var location = order && order.pickup ? escapeHtml(order.pickup.location || '') : '';
    const html = '<div style="padding:16px;border-radius:10px;background:#e9fffb;border:1px solid #bff0e6">'
      + '<strong style="display:block;margin-bottom:8px">Order confirmed — ' + escapeHtml(order && (order.order_id || order.id || '')) + '</strong>'
      + '<div><strong>Pickup scheduled:</strong> ' + pickupStr + '</div>'
      + '<div style="margin-top:8px"><strong>Location:</strong> ' + location + '</div>'
      + '<div style="margin-top:8px">' + escapeHtml(order.instructions || '') + '</div>'
      + '<div style="margin-top:12px">A confirmation email will be sent to <strong>' + escapeHtml((order.customer && order.customer.email) || '') + '</strong>.</div>'
      + '</div>';
    insertUiMessage(html);
  }

  function showError(order, details) {
    const html = '<div style="padding:12px;border-radius:10px;background:#fff1f0;border:1px solid #ffd6d1">'
      + '<strong>Unable to send confirmation automatically</strong>'
      + '<div style="margin-top:8px">Order: <strong>' + escapeHtml((order && (order.order_id || order.id)) || '—') + '</strong></div>'
      + '<pre style="white-space:pre-wrap;margin-top:8px;color:#900;background:#fff;padding:8px;border-radius:6px">' + escapeHtml(String(details || 'No details')) + '</pre>'
      + '<div style="margin-top:8px">Please contact support if you do not receive an email.</div>'
      + '</div>';
    insertUiMessage(html);
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]);
    });
  }

  // POST the order to Make webhook (client-side)
  async function postToMake(enriched) {
    if (!MAKE_WEBHOOK_URL || MAKE_WEBHOOK_URL.indexOf('REPLACE_WITH') !== -1) {
      return { ok: false, message: 'Make webhook URL not configured on client' };
    }
    try {
      const res = await fetch(MAKE_WEBHOOK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(enriched),
        keepalive: true
      });
      const text = await res.text();
      return { ok: res.ok, status: res.status, body: text };
    } catch (err) {
      return { ok: false, error: err && err.message ? err.message : String(err) };
    }
  }

  // POST the order to your server endpoint (recommended)
  async function postToServer(enriched) {
    if (!SERVER_ORDER_ENDPOINT) return { ok: false, message: 'No server endpoint configured' };
    try {
      const res = await fetch(SERVER_ORDER_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(enriched)
      });
      let json = null;
      try { json = await res.json(); } catch (e) { /* ignore */ }
      return { ok: res.ok, status: res.status, body: json };
    } catch (err) {
      return { ok: false, error: err && err.message ? err.message : String(err) };
    }
  }

  // Main flow
  async function run() {
    try {
      const stored = readStoredOrder();
      if (!stored) {
        console.log('qp: no stored order payload found; nothing to send.');
        return;
      }

      // If the stored value is only an instant-quote payload (not a real order), do not auto-post.
      if (stored.__qp_only_instant_quote) {
        console.log('qp: found only instant-quote payload; skipping automatic order post.');
        // optionally render a non-posting summary into the page instead:
        // you could call insertUiMessage(summaryHtml) here if desired.
        return;
      }

      // Prevent duplicate posts: check qp_order_posted in sessionStorage
      try {
        const postedId = sessionStorage.getItem('qp_order_posted');
        if (postedId && postedId === (stored.order_id || stored.id || '')) {
          console.log('qp: order already posted (session flag)', postedId);
          showSuccess(stored); // show success UI
          return;
        }
      } catch (e) { /* ignore */ }

      // Show processing UI
      showProcessing(stored);

      // Enrich payload
      const enriched = buildEnrichedOrder(stored);
      if (!enriched) {
        console.warn('qp: payload not suitable for auto-post (missing order structure)');
        showError(stored, 'Payload is not a full order. Auto-post skipped.');
        return;
      }

      // Decide destination: server preferred
      let result;
      if (SERVER_ORDER_ENDPOINT) {
        result = await postToServer(enriched);
      } else {
        result = await postToMake(enriched);
      }

      // Save post status and present UI
      if (result && result.ok) {
        try {
          // store posted flag (use order_id if present, otherwise a generated token)
          var postedToken = stored.order_id || stored.id || ('qp_posted_' + Date.now());
          sessionStorage.setItem('qp_order_posted', String(postedToken));
          // Clear stored payloads to avoid reuse
          try { sessionStorage.removeItem('qp_order_payload'); sessionStorage.removeItem('qp_instant_quote_payload'); } catch(e){}
          try { localStorage.removeItem('qp_order_payload'); localStorage.removeItem('qp_instant_quote_payload'); } catch(e){}
        } catch (e) { /* ignore */ }
        showSuccess(enriched);
        window.__qp_confirm_post_result = { ok: true, response: result };
      } else {
        console.warn('qp: post result failure', result);
        showError(enriched, result && (result.body || result.error || result.message || JSON.stringify(result)));
        window.__qp_confirm_post_result = { ok: false, response: result };
      }

    } catch (err) {
      console.error('qp: confirm automation failed', err);
      showError(null, err && err.message ? err.message : String(err));
    }
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }

})();