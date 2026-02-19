// instant-quote.js (patched) — part 1/3
(function () {
  'use strict';

  if (window.__qp_instant_quote_inited) {
    console.log('qp: instant-quote already initialized; skipping duplicate init.');
    return;
  }
  window.__qp_instant_quote_inited = true;

  var UNITS_PER_FULL_LOAD = 24;

  function fmtCurrency(n) {
    try {
      var currency = (window.QP_RUNTIME && window.QP_RUNTIME.currency) || (window.qp_vars && window.qp_vars.currency) || 'USD';
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(Number(n || 0));
    } catch (e) {
      return '$' + Number(n || 0).toFixed(2);
    }
  }

  function unitsForPrice(price) {
    price = Number(price || 0);
    if (price >= 125) return 12;
    if (price >= 100) return 9;
    if (price >= 65) return 6;
    if (price >= 36) return 4;
    return 2;
  }

  function organizeByCategory(products) {
    var map = {};
    var order = [];
    (products || []).forEach(function (p) {
      var cats = (p.categories && p.categories.length) ? p.categories : (p.category ? [p.category] : ['other']);
      var cat = cats && cats.length ? String(cats[0]).trim() : 'other';
      var catKey = (cat || 'other').toString().toLowerCase();
      if (catKey === 'uncategorized') return;
      if (!map[cat]) { map[cat] = []; order.push(cat); }
      map[cat].push(p);
    });
    order = order.filter(function (c) { return map[c] && map[c].length; });
    return { map: map, order: order };
  }

  /* --- Top category bar helpers (improved & accessible) --- */
  function buildTopCategoryBar(categories) {
    try {
      // Avoid recreating
      if (document.getElementById('qp-top-cats')) return;

      var root = document.getElementById('qp-instant-quote-root');
      if (!root) return;

      var bar = document.createElement('div');
      bar.id = 'qp-top-cats';
      bar.setAttribute('role', 'navigation');
      bar.setAttribute('aria-label', 'Quick pick categories');

      var inner = document.createElement('div');
      inner.className = 'qp-cats-wrapper';
      inner.id = 'qp-cats-wrapper';
      inner.setAttribute('role', 'toolbar');
      inner.setAttribute('aria-label', 'Category navigation');

      (categories || []).forEach(function (cat, i) {
        var raw = (cat || 'Other').toString();
        var label = raw.replace(/[-_]/g, ' ').replace(/\s+/g, ' ').trim();
        label = label.replace(/\b\w/g, function (c) { return c.toUpperCase(); });

        var targetId = 'qp-cat-' + i;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'qp-cat-btn';
        btn.setAttribute('data-cat', raw);
        btn.setAttribute('data-target', targetId);
        btn.setAttribute('aria-pressed', 'false');
        btn.setAttribute('aria-controls', targetId);
        btn.title = label;
        btn.textContent = label;

        // Click → smooth scroll & set active
        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          var tid = btn.getAttribute('data-target');
          var target = document.getElementById(tid);
          if (!target) return;
          var barEl = document.getElementById('qp-top-cats');
          var offset = (barEl ? barEl.offsetHeight : 0) + 12;
          var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
          window.scrollTo({ top: Math.max(0, Math.floor(top)), behavior: 'smooth' });
          setActiveCategoryButton(btn);
        }, false);

        // Keyboard navigation for pills
        btn.addEventListener('keydown', function (ev) {
          var KEY_LEFT = 37, KEY_RIGHT = 39, KEY_HOME = 36, KEY_END = 35;
          var all = Array.from(document.querySelectorAll('#qp-cats-wrapper .qp-cat-btn'));
          var idx = all.indexOf(ev.currentTarget);
          if (ev.keyCode === KEY_LEFT) {
            ev.preventDefault();
            var prev = all[(idx - 1 + all.length) % all.length];
            if (prev) prev.focus();
          } else if (ev.keyCode === KEY_RIGHT) {
            ev.preventDefault();
            var next = all[(idx + 1) % all.length];
            if (next) next.focus();
          } else if (ev.keyCode === KEY_HOME) {
            ev.preventDefault();
            if (all.length) all[0].focus();
          } else if (ev.keyCode === KEY_END) {
            ev.preventDefault();
            if (all.length) all[all.length - 1].focus();
          }
        }, false);

        inner.appendChild(btn);
      });

      bar.appendChild(inner);
      document.body.appendChild(bar);

      // Observer will run now that buttons exist; renderFullUi ensures sections exist first
      if (typeof wireTopBarScrollObserver === 'function') {
        wireTopBarScrollObserver();
      }
    } catch (e) {
      console.warn('qp: buildTopCategoryBar error', e);
    }
  }

  function setActiveCategoryButton(btn) {
    try {
      var all = Array.from(document.querySelectorAll('#qp-cats-wrapper .qp-cat-btn'));
      all.forEach(function (b) {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      if (btn) {
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        // Ensure the active pill is visible within the horizontal scroller
        try { btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' }); } catch (e) {}
      }
    } catch (e) { /* ignore */ }
  }

  function wireTopBarScrollObserver() {
    try {
      var sections = Array.from(document.querySelectorAll('.qp-category-section'));
      if (!sections.length) return;
      var barEl = document.getElementById('qp-top-cats');
      var buttons = Array.from(document.querySelectorAll('#qp-cats-wrapper .qp-cat-btn'));
      var tick = false;
      function onScroll() {
        if (tick) return;
        tick = true;
        window.requestAnimationFrame(function () {
          try {
            var offset = (barEl ? barEl.offsetHeight : 0) + 20;
            var fromTop = window.pageYOffset + offset + 2;
            var activeIdx = 0;
            for (var i = 0; i < sections.length; i++) {
              var s = sections[i];
              if (!s) continue;
              var top = s.getBoundingClientRect().top + window.pageYOffset;
              if (top <= fromTop) activeIdx = i;
            }
            var targetId = 'qp-cat-' + activeIdx;
            var btn = buttons.find(function (b) { return b.getAttribute('data-target') === targetId; }) || null;
            setActiveCategoryButton(btn);
          } catch (e) { console.warn('qp: topbar scroll handler error', e); }
          tick = false;
        });
      }
      window.removeEventListener('scroll', window.__qp_topbar_scroll_handler || onScroll);
      window.__qp_topbar_scroll_handler = onScroll;
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    } catch (e) {
      console.warn('qp: wireTopBarScrollObserver error', e);
    }
  }

  var selectedMap = {};
  var selectedOrder = [];

  function domOrderFallback() {
    try {
      var cards = Array.from(document.querySelectorAll('.qp-card'));
      return cards.map(function (c) { return (c.dataset && c.dataset.key) ? c.dataset.key : c.getAttribute('data-key'); })
                  .filter(function (k) { return k && selectedMap[k]; });
    } catch (e) { return []; }
  }

  function computeTotalsFromSelected() {
    var originalTotalPrice = 0;
    var totalEnv = 0;
    var totalUnits = 0;

    var itemSummaries = Object.keys(selectedMap).map(function (k) {
      var it = selectedMap[k];
      var qty = Math.max(0, Number(it.qty || 0));
      var price = Number(it.price || 0);
      var env = Number(it.env || 0);
      var unitsPerItem = unitsForPrice(price);
      var unitsTotal = unitsPerItem * qty;
      originalTotalPrice += price * qty;
      totalEnv += env * qty;
      totalUnits += unitsTotal;
      return {
        key: k,
        qty: qty,
        price: price,
        env: env,
        unitsPerItem: unitsPerItem,
        unitsTotal: unitsTotal,
        itemTotalPrice: price * qty,
        unitValue: unitsPerItem > 0 ? (price / unitsPerItem) : 0
      };
    });

    var fullLoads = Math.floor(totalUnits / UNITS_PER_FULL_LOAD);
    var unitsToConsume = fullLoads * UNITS_PER_FULL_LOAD;

    var orderToUse = (selectedOrder && selectedOrder.length) ? selectedOrder.slice() : domOrderFallback();
    var unitStream = [];
    for (var oi = 0; oi < orderToUse.length; oi++) {
      var key = orderToUse[oi];
      var summary = itemSummaries.find(function (s) { return s.key === key; });
      if (!summary) continue;
      if (summary.qty <= 0 || summary.unitsPerItem <= 0) continue;
      for (var q = 0; q < summary.qty; q++) {
        for (var u = 0; u < summary.unitsPerItem; u++) {
          unitStream.push({ key: key, unitValue: summary.unitValue });
        }
      }
    }

    var unitsConsumed = 0;
    var valueRemoved = 0;
    while (unitsConsumed < unitsToConsume && unitsConsumed < unitStream.length) {
      var unit = unitStream[unitsConsumed];
      valueRemoved += unit.unitValue;
      unitsConsumed++;
    }

    var fullLoadPrice = (window.QP_RUNTIME && Number(window.QP_RUNTIME.fullLoadPrice)) || (window.qp_vars && Number(window.qp_vars.fullLoadPrice)) || 250;
    var fullLoadsCost = fullLoads * fullLoadPrice;
    var remainderPrice = Math.max(0, originalTotalPrice - valueRemoved);
    var total = fullLoadsCost + remainderPrice + totalEnv;

    fullLoadsCost = Math.round(fullLoadsCost * 100) / 100;
    remainderPrice = Math.round(remainderPrice * 100) / 100;
    totalEnv = Math.round(totalEnv * 100) / 100;
    total = Math.round(total * 100) / 100;

    return {
      originalTotalPrice: Number(Math.round(originalTotalPrice * 100) / 100),
      totalEnv: totalEnv,
      totalUnits: totalUnits,
      fullLoads: fullLoads,
      fullLoadsCost: fullLoadsCost,
      remainderUnits: totalUnits - (fullLoads * UNITS_PER_FULL_LOAD),
      remainderPrice: remainderPrice,
      total: total,
      total_formatted: fmtCurrency(total)
    };
  }
  // instant-quote.js — part 2/3
  function updateFooterDisplay() {
    var computed = computeTotalsFromSelected();
    var chosenList = document.getElementById('qp-chosen-list');
    var totalEl = document.getElementById('qp-total');
    var checkoutBtn = document.getElementById('qp-footer-checkout');

    if (chosenList) {
      chosenList.innerHTML = '';
      Object.keys(selectedMap).forEach(function (k) {
        var it = selectedMap[k];
        var chip = document.createElement('div');
        chip.className = 'qp-chosen-item';
        chip.textContent = (it.name || k) + (it.qty && it.qty > 0 ? ' ×' + it.qty : '');
        chosenList.appendChild(chip);
      });
      if (!Object.keys(selectedMap).length) {
        chosenList.innerHTML = '<div class="qp-empty">No items selected</div>';
      }
    }

    if (totalEl) totalEl.textContent = computed.total_formatted;

    var fullBadge = document.getElementById('qp-fullloads-badge');
    if (!fullBadge) {
      var totalWrap = document.getElementById('qp-total');
      if (totalWrap && totalWrap.parentNode) {
        fullBadge = document.createElement('div');
        fullBadge.id = 'qp-fullloads-badge';
        fullBadge.style.fontSize = '0.9rem';
        fullBadge.style.color = '#0a2e3b';
        fullBadge.style.opacity = '0.9';
        fullBadge.style.marginLeft = '8px';
        totalWrap.parentNode.insertBefore(fullBadge, totalWrap.nextSibling);
      }
    }
    if (fullBadge) {
      if (computed.fullLoads > 0) {
        fullBadge.textContent = computed.fullLoads + ' full ' + (computed.fullLoads === 1 ? 'load' : 'loads') + ' (' + fmtCurrency(computed.fullLoadsCost) + ')';
        fullBadge.style.display = 'inline-block';
      } else {
        fullBadge.textContent = '';
        fullBadge.style.display = 'none';
      }
    }

    if (checkoutBtn) checkoutBtn.disabled = Object.keys(selectedMap).length === 0;
  }

  // Utility: return absolute admin-ajax URL & nonce from localized vars
  var AJAX_URL = (window.qp_ajax && window.qp_ajax.url) ? window.qp_ajax.url : (window.ajaxurl || (location.origin + '/wp-admin/admin-ajax.php'));
  var AJAX_NONCE = (window.qp_ajax && window.qp_ajax.nonce) ? window.qp_ajax.nonce : '';
  var CHECKOUT = (window.qp_vars && window.qp_vars.checkout_url) ? window.qp_vars.checkout_url : '/checkout/';
  try { CHECKOUT = new URL(CHECKOUT, location.origin).href; } catch (e) { CHECKOUT = location.origin + (CHECKOUT.charAt(0) === '/' ? CHECKOUT : '/' + CHECKOUT); }

  function saveMultiItemPayloadAndGo() {
    var itemsOut = [];
    try {
      var sel = (typeof window.__qp_instant_select === 'function') ? window.__qp_instant_select().selectedMap : (selectedMap || {});
      Object.keys(sel).forEach(function (k) {
        var it = sel[k];
        var qty = Math.max(0, Math.floor(Number(it.qty || 0)));
        if (qty <= 0) return;
        var price = Number(it.price || 0);
        var unitsPerItem = unitsForPrice(price);

        // Prefer numeric product id
        var numericId = null;
        if (it.id !== undefined && it.id !== null && it.id !== '') {
          var maybe = Number(it.id);
          if (!isNaN(maybe) && Number.isInteger(maybe) && maybe > 0) numericId = maybe;
        }
        var idForServer = numericId !== null ? numericId : (it.slug || k);

        itemsOut.push({
          id: idForServer,
          slug: it.slug || k,
          name: it.name || k,
          sku: it.sku || '',
          qty: qty,
          price: price,
          env: Number(it.env || 0),
          image: it.image || '',
          units_per_item: unitsPerItem,
          units_total: unitsPerItem * qty
        });
      });
    } catch (e) {
      console.warn('qp: build from selectedMap failed, falling back to DOM scan', e);
      var cards = Array.from(document.querySelectorAll('.qp-card'));
      cards.forEach(function (card) {
        try {
          var qtyAttr = card.getAttribute('data-qty');
          var input = card.querySelector('.qp-qty-input');
          var qty = Math.max(0, Math.floor(Number((input && input.value) ? input.value : (qtyAttr || 0)) || 0));
          if (qty <= 0) return;

          var idAttr = card.getAttribute('data-product-id') || '';
          var numericId = null;
          if (idAttr) {
            var maybe = Number(idAttr);
            if (!isNaN(maybe) && Number.isInteger(maybe) && maybe > 0) numericId = maybe;
          }
          var slug = card.getAttribute('data-slug') || card.getAttribute('data-key') || '';
          var name = card.getAttribute('data-name') || '';
          var price = Number(card.getAttribute('data-price') || 0);
          var env = Number(card.getAttribute('data-envfee') || 0);
          var imgEl = card.querySelector('img');
          var image = imgEl ? (imgEl.src || '') : '';
          var unitsPerItem = unitsForPrice(price);

          itemsOut.push({
            id: numericId !== null ? numericId : slug || (card.getAttribute('data-key') || ''),
            slug: slug,
            name: name,
            sku: card.getAttribute('data-sku') || '',
            qty: qty,
            price: price,
            env: env,
            image: image,
            units_per_item: unitsPerItem,
            units_total: unitsPerItem * qty
          });
        } catch (err) {
          console.warn('qp: failed to read card for payload', err);
        }
      });
    }

    if (!itemsOut.length) {
      console.warn('qp: no items to save to cart');
      // Redirect to checkout anyway
      window.location.assign(CHECKOUT);
      return;
    }

    var computed = computeTotalsFromSelected();
    var payload = {
      items: itemsOut,
      fullLoads: computed.fullLoads,
      fullLoadsCost: computed.fullLoadsCost,
      itemsCost: computed.originalTotalPrice,
      remainderPrice: computed.remainderPrice,
      totalEnvFees: computed.totalEnv,
      total: Number(computed.total).toFixed(2),
      total_formatted: computed.total_formatted,
      ts: Date.now()
    };

    // Save local copy (for checkout-side fallback)
    try {
      localStorage.setItem('qp_instant_quote_payload', JSON.stringify(payload));
      sessionStorage.setItem('qp_instant_quote_payload', JSON.stringify(payload));
      localStorage.setItem('qp_coming_from_instant', '1');
      localStorage.setItem('qp_auto_checkout', '1');
      console.log('qp: multi-item payload (local copy) saved', payload);
    } catch (e) {
      console.warn('qp: failed to save payload locally', e);
    }

    // Prevent duplicate applies
    try {
      var appliedTs = null;
      try { appliedTs = Number(localStorage.getItem('qp_payload_applied_ts')); } catch (e) { appliedTs = null; }
      if (appliedTs && (Date.now() - appliedTs < 10 * 60 * 1000)) {
        console.info('qp: payload already applied recently; redirecting to Checkout.');
        setTimeout(function () { window.location.assign(CHECKOUT + (CHECKOUT.indexOf('?') === -1 ? '?' : '&') + 'qp_ts=' + Date.now()); }, 200);
        return;
      }
    } catch (e) { /* ignore */ }

    try {
      if (localStorage.getItem('qp_payload_applying') === '1') {
        console.info('qp: payload apply already in progress; skipping duplicate.');
        return;
      }
      localStorage.setItem('qp_payload_applying', '1');
    } catch (e) { /* ignore */ }

    // Disable Confirm button to avoid double-clicks
    var checkoutBtnEl = document.getElementById('qp-footer-checkout') || document.querySelector('#qp-footer-checkout');
    try { if (checkoutBtnEl) { checkoutBtnEl.disabled = true; checkoutBtnEl.classList.add('is-processing'); } } catch (e) {}

    // Send payload to server to populate Woo cart
    var form = new URLSearchParams();
    form.append('action', 'qp_apply_payload');
    form.append('payload', JSON.stringify(payload));
    if (AJAX_NONCE) form.append('qp_nonce', AJAX_NONCE);

    fetch(AJAX_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: form
    }).then(function (res) {
      if (!res.ok) throw new Error('Network response not ok: ' + res.status);
      return res.json().catch(function () { return { success: false, parseError: true }; });
    }).then(function (json) {
      console.log('qp: admin-ajax response', json);

      var addedTotal = 0;
      try {
        if (json && json.success && json.data) {
          if (typeof json.data.added === 'number') addedTotal = Number(json.data.added);
          else if (Array.isArray(json.data.items)) {
            addedTotal = json.data.items.reduce(function (sum, it) {
              try {
                if (!it) return sum;
                if (it.status === 'added' && it.requested && it.requested.qty) {
                  return sum + Number(it.requested.qty || 0);
                }
                if (it.status === 'added') return sum + 1;
                return sum;
              } catch (e) { return sum; }
            }, 0);
          }
        }
      } catch (e) { addedTotal = 0; }

      if (json && json.success) {
        if (json.data && Array.isArray(json.data.items)) {
          var failed = json.data.items.filter(function (it) { return it.status !== 'added'; });
          if (failed.length) {
            console.warn('qp: some items failed to add to server cart', failed);
            try { alert('Some items could not be added to the cart. See console for details.'); } catch (e) {}
          } else {
            console.log('qp: all payload items added to server cart');
          }
        } else {
          console.log('qp: payload apply reported success (no per-item details)');
        }

        try {
          if (addedTotal > 0) {
            // mark as applied but keep payload in storage so checkout-side UI can still read it
            localStorage.setItem('qp_payload_applied_ts', String(Date.now()));
            localStorage.removeItem('qp_payload_applying');
            window.location.assign(CHECKOUT);
            return;
          } else {
            localStorage.removeItem('qp_payload_applying');
            try { if (checkoutBtnEl) { checkoutBtnEl.disabled = false; checkoutBtnEl.classList.remove('is-processing'); } } catch (e) {}
            window.location.assign(CHECKOUT);
            return;
          }
        } catch (e) {
          console.warn('qp: error handling apply response', e);
          localStorage.removeItem('qp_payload_applying');
          window.location.assign(CHECKOUT);
          return;
        }
      } else {
        console.warn('qp: server responded with error', json);
        try { alert('Failed to apply payload to server cart. Proceeding to Checkout (client-side fallback).'); } catch (e) {}
        localStorage.removeItem('qp_payload_applying');
        try { if (checkoutBtnEl) { checkoutBtnEl.disabled = false; checkoutBtnEl.classList.remove('is-processing'); } } catch (e) {}
        window.location.assign(CHECKOUT);
      }
    }).catch(function (err) {
      console.warn('qp: ajax apply payload failed', err);
      try { localStorage.removeItem('qp_payload_applying'); } catch (e) {}
      try { if (checkoutBtnEl) { checkoutBtnEl.disabled = false; checkoutBtnEl.classList.remove('is-processing'); } } catch (e) {}
      window.location.assign(CHECKOUT);
    });
  }

  function ensureSelectedOrderContains(key) { if (!key) return; if (selectedOrder.indexOf(key) === -1) selectedOrder.push(key); }
  function removeFromSelectedOrder(key) { var idx = selectedOrder.indexOf(key); if (idx !== -1) selectedOrder.splice(idx, 1); }

  function setCardQty(card, qty) {
    qty = Math.max(0, Math.floor(Number(qty || 0)));
    card.setAttribute('data-qty', String(qty));
    var input = card.querySelector('.qp-qty-input');
    if (input) input.value = String(qty);

    var key = card.dataset.key || card.getAttribute('data-key');
    if (!key) return;

    if (qty > 0) {
      card.classList.add('qp-selected');
      ensureSelectedOrderContains(key);
      selectedMap[key] = selectedMap[key] || {
        id: card.getAttribute('data-product-id') || '',
        name: card.getAttribute('data-name') || '',
        price: Number(card.getAttribute('data-price') || 0),
        env: Number(card.getAttribute('data-envfee') || 0),
        image: (card.querySelector('img') && card.querySelector('img').src) || '',
        slug: card.getAttribute('data-slug') || key,
        qty: qty
      };
      selectedMap[key].qty = qty;
    } else {
      card.classList.remove('qp-selected');
      if (selectedMap[key]) delete selectedMap[key];
      removeFromSelectedOrder(key);
    }
    updateFooterDisplay();
  }

  function toggleCardSelection(card) {
    if (!card) return;
    var key = card.dataset.key || card.getAttribute('data-key');
    if (!key) return;
    var currentQty = Number(card.getAttribute('data-qty') || 0);
    if (card.classList.contains('qp-selected')) setCardQty(card, 0);
    else if (currentQty <= 0) setCardQty(card, 1);
    else setCardQty(card, currentQty);
  }
  // instant-quote.js — part 3/3
  function renderFullUi() {
    try {
      var root = document.getElementById('qp-instant-quote-root');
      if (!root) return;
      if (root.dataset.qpRendered === '1') return;

      var products = window.QP_PRODUCTS || [];
      var grouping = organizeByCategory(products);
      var categories = grouping.order;
      var map = grouping.map;

      var productRow = document.createElement('div');
      productRow.className = 'product-row qp-product-row';

      categories.forEach(function (cat, idx) {
        var section = document.createElement('section');
        section.className = 'qp-category-section';
        section.id = 'qp-cat-' + idx;

        var heading = document.createElement('h3');
        heading.className = 'qp-category-title';
        heading.textContent = (cat || 'Other').replace(/[-_]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });

        var grid = document.createElement('div');
        grid.className = 'qp-grid';

        (map[cat] || []).forEach(function (p) {
          var card = document.createElement('div');
          card.className = 'qp-card';
          card.setAttribute('role', 'button');
          card.setAttribute('tabindex', '0');

          var key = p.slug || p.key || (p.name || '').toLowerCase().replace(/\s+/g, '-');
          card.setAttribute('data-key', key);
          if (p.id) card.setAttribute('data-product-id', String(p.id));
          if (typeof p.price !== 'undefined') card.setAttribute('data-price', String(p.price));
          if (p.location) card.setAttribute('data-location', p.location);
          if (p.slug) card.setAttribute('data-slug', p.slug);
          card.setAttribute('data-name', p.name || '');
          card.setAttribute('data-qty', '0');

          // determine environment fee (default from product, override for certain appliances)
          var envFee = Number(p.envfee || 0);
          var _name = (p.name || '').toString().toLowerCase();
          var _slug = (p.slug || '').toString().toLowerCase();
          var applianceKeywords = ['fridge', 'freezer', 'water cooler', 'water-cooler', 'watercooler', 'airconditioner', 'air-conditioner', 'air conditioner', 'ac', 'a/c'];
          for (var ki = 0; ki < applianceKeywords.length; ki++) {
            var kw = applianceKeywords[ki];
            if (kw && (_name.indexOf(kw) !== -1 || _slug.indexOf(kw) !== -1)) {
              envFee = 40;
              break;
            }
          }
          card.setAttribute('data-envfee', String(envFee));

          var pic = document.createElement('span');
          pic.className = 'qp-media';
          var img = document.createElement('img');
          if (p.image) img.src = p.image;
          img.alt = p.name || '';
          pic.appendChild(img);

          var nameSpan = document.createElement('span');
          nameSpan.className = 'qp-name';
          nameSpan.innerHTML = p.name || '';

          var note = null;
          if (envFee && Number(envFee) > 0) {
            note = document.createElement('span');
            note.className = 'qp-note';
            note.textContent = '+$' + Number(envFee).toFixed(2) + ' enviro fee';
          }

          var priceSpan = document.createElement('span');
          priceSpan.className = 'qp-price';
          priceSpan.textContent = (typeof p.price !== 'undefined') ? (fmtCurrency(p.price) + (p.location ? ' ' + p.location : '')) : '';

          var footer = document.createElement('div');
          footer.className = 'qp-card-footer';

          var qtyWrap = document.createElement('div');
          qtyWrap.className = 'qp-qty';

          var minus = document.createElement('button');
          minus.type = 'button';
          minus.className = 'qp-btn qp-qty-btn';
          minus.setAttribute('data-action', 'decrease');
          minus.textContent = '−';

          var qtyInput = document.createElement('input');
          qtyInput.type = 'number';
          qtyInput.min = '0';
          qtyInput.value = '0';
          qtyInput.className = 'qp-qty-input';
          qtyInput.setAttribute('aria-label', 'Quantity');

          var plus = document.createElement('button');
          plus.type = 'button';
          plus.className = 'qp-btn qp-qty-btn';
          plus.setAttribute('data-action', 'increase');
          plus.textContent = '+';

          qtyWrap.appendChild(minus);
          qtyWrap.appendChild(qtyInput);
          qtyWrap.appendChild(plus);

          footer.appendChild(priceSpan);
          footer.appendChild(qtyWrap);

          card.appendChild(pic);
          card.appendChild(nameSpan);
          if (note) card.appendChild(note);
          card.appendChild(footer);

          grid.appendChild(card);
        });

        section.appendChild(heading);
        section.appendChild(grid);
        productRow.appendChild(section);
      });

      // Render the content (clear and insert productRow)
      var rootEl = document.getElementById('qp-instant-quote-root');
      rootEl.innerHTML = '';

      // Ensure spacer exists at top of root so fixed bar doesn't cover first section
      if (!document.getElementById('qp-top-spacer')) {
        var spacer = document.createElement('div');
        spacer.id = 'qp-top-spacer';
        rootEl.appendChild(spacer);
      }

      // Append the constructed content (sections/grids)
      rootEl.appendChild(productRow);

      // Now create the fixed category bar (sections/buttons are in the DOM)
      try {
        if (typeof grouping !== 'undefined' && Array.isArray(grouping.order)) {
          buildTopCategoryBar(grouping.order);
        } else {
          buildTopCategoryBar(categories || []);
        }
      } catch (e) { console.warn('qp: buildTopCategoryBar call failed', e); }

      if (!document.getElementById('qp-floating')) {
        var floating = document.createElement('div');
        floating.id = 'qp-floating';
        floating.innerHTML = '\
<div class="qp-floating-left">\
  <div class="qp-total" id="qp-total">' + fmtCurrency(0) + '</div>\
</div>\
<div class="qp-floating-center">\
  <div class="qp-chosen-list" id="qp-chosen-list"></div>\
</div>\
<div class="qp-floating-right">\
  <button id="qp-footer-clear" class="qp-btn-secondary">Clear</button>\
  <button id="qp-footer-checkout" class="qp-btn-primary" disabled>Confirm & Continue</button>\
</div>';
        document.body.appendChild(floating);
      }

      if (!document.getElementById('qp-live')) {
        var live = document.createElement('div');
        live.id = 'qp-live';
        live.className = 'visually-hidden';
        live.setAttribute('aria-live', 'polite');
        live.setAttribute('aria-atomic', 'true');
        document.body.appendChild(live);
      }

      rootEl.dataset.qpRendered = '1';
    } catch (e) {
      console.warn('qp: renderFullUi error', e);
    }
  }

  function wireInteractions() {
    document.querySelectorAll('.qp-grid').forEach(function (g) {
      if (g.dataset.qpGridWired) return;
      g.dataset.qpGridWired = '1';

      g.addEventListener('click', function (e) {
        var qtyBtn = e.target.closest('.qp-qty-btn');
        if (qtyBtn) {
          e.preventDefault();
          e.stopPropagation();
          var card = qtyBtn.closest('.qp-card');
          if (!card) return;
          var action = qtyBtn.getAttribute('data-action');
          var input = card.querySelector('.qp-qty-input');
          var current = Number(input && input.value ? input.value : card.getAttribute('data-qty') || 0);
          var next = action === 'increase' ? current + 1 : Math.max(0, current - 1);
          setCardQty(card, next);
          return;
        }

        var inputEl = e.target.closest('.qp-qty-input');
        if (inputEl) return;

        var card = e.target.closest('.qp-card');
        if (!card || !g.contains(card)) return;

        if (e.metaKey || e.ctrlKey || e.shiftKey) {
          e.preventDefault();
          e.stopPropagation();
          toggleCardSelection(card);
          return;
        }
        if (Object.keys(selectedMap).length > 0) {
          e.preventDefault();
          e.stopPropagation();
          toggleCardSelection(card);
          return;
        }

        var imgEl = card.querySelector('img');
        var data = {
          key: card.getAttribute('data-key') || '',
          name: card.getAttribute('data-name') || '',
          price: Number(card.getAttribute('data-price') || 0),
          location: card.getAttribute('data-location') || 'curb',
          envFee: Number(card.getAttribute('data-envfee') || 0),
          img: imgEl ? imgEl.src : '',
          alt: imgEl ? (imgEl.alt || '') : '',
          slug: card.getAttribute('data-slug') || '',
          productId: card.getAttribute('data-product-id') || ''
        };
        if (typeof window.__qp_openModal === 'function') {
          window.__qp_openModal(data);
        } else {
          var ev = new CustomEvent('qp:openModal', { detail: data });
          document.dispatchEvent(ev);
        }
      }, false);

      g.addEventListener('change', function (e) {
        var input = e.target.closest('.qp-qty-input');
        if (!input) return;
        var card = input.closest('.qp-card');
        if (!card) return;
        var val = Math.max(0, Math.floor(Number(input.value || 0) || 0));
        setCardQty(card, val);
      }, false);
    });

    // .qp-cat-btn click wiring handled in buildTopCategoryBar

    var clearBtn = document.getElementById('qp-footer-clear');
    if (clearBtn && !clearBtn.dataset.qpFooterClearWired) {
      clearBtn.dataset.qpFooterClearWired = '1';
      clearBtn.addEventListener('click', function () {
        document.querySelectorAll('.qp-card').forEach(function (c) { setCardQty(c, 0); });
        selectedMap = {}; selectedOrder = [];
        updateFooterDisplay();
      });
    }

    var checkoutBtn = document.getElementById('qp-footer-checkout');
    if (checkoutBtn && !checkoutBtn.dataset.qpFooterCheckoutWired) {
      checkoutBtn.dataset.qpFooterCheckoutWired = '1';
      checkoutBtn.addEventListener('click', function () {
        if (!Object.keys(selectedMap).length && !document.querySelectorAll('.qp-card').length) return;
        saveMultiItemPayloadAndGo();
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        var active = document.activeElement;
        if (active && active.classList && active.classList.contains('qp-card')) {
          e.preventDefault();
          var imgEl = active.querySelector('img');
          var data = {
            key: active.getAttribute('data-key') || '',
            name: active.getAttribute('data-name') || '',
            price: Number(active.getAttribute('data-price') || 0),
            location: active.getAttribute('data-location') || 'curb',
            envFee: Number(active.getAttribute('data-envfee') || 0),
            img: imgEl ? imgEl.src : '',
            alt: imgEl ? (imgEl.alt || '') : '',
            slug: active.getAttribute('data-slug') || '',
            productId: active.getAttribute('data-product-id') || ''
          };
          if (typeof window.__qp_openModal === 'function') window.__qp_openModal(data);
        }
      }
    }, false);
  }

  // Public helpers
  window.__qp_instant_select = function () { return { selectedMap: selectedMap, selectedOrder: selectedOrder }; };
  window.saveMultiItemPayloadAndGo = saveMultiItemPayloadAndGo;

  window.__qp_instant_rendered = true;

  (function boot() {
    var root = document.getElementById('qp-instant-quote-root');
    if (!root) {
      console.warn('qp: no #qp-instant-quote-root found — instant-quote will not render.');
      return;
    }
    renderFullUi();
    wireInteractions();
    updateFooterDisplay();
  })();

})();