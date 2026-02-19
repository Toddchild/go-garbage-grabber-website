<?php
/**
 * Template Name: Quick Pickup — Custom Checkout
 * Description: Custom checkout UI that reads instant quote payload, shows order summary,
 *              applies payload to server cart via AJAX, then mounts a standalone Stripe Elements card
 *              and performs payment via a PaymentIntent created by wp-admin-ajax (qp_create_payment_intent).
 *
 * Notes:
 * - You MUST implement the server-side AJAX action qp_create_payment_intent that returns JSON:
 *   { success: true, client_secret: '...', redirect_url: 'https://.../order-received/1234/?key=...' }
 *   using Stripe secret key on server.
 * - Do not expose Stripe secret key in client JS. Only publishable key (pk_...) is safe to show.
 * - This template mounts the card and uses the existing "Pay & Book" button (id="btn-submit").
 *
 * Behavior change in this copy: WooCommerce checkout billing fields / order review are hidden after
 * Stripe Elements is mounted so the custom checkout UI is primary. Hidden fields remain in the DOM
 * (not removed) so form submission still works; hidden values are populated by the template JS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue Stripe.js (client) so Stripe is available for init
wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, false );

// Localize values used in JS (safe)
$ajax_url     = esc_url_raw( admin_url( 'admin-ajax.php' ) );
$qp_nonce     = wp_create_nonce( 'qp_apply_nonce' );
$checkout_url = function_exists( 'wc_get_checkout_url' ) ? esc_url( wc_get_checkout_url() ) : esc_url_raw( home_url( '/checkout/' ) );
$cart_url     = function_exists( 'wc_get_cart_url' ) ? esc_url( wc_get_cart_url() ) : esc_url_raw( home_url( '/cart/' ) );

echo '<!-- qp-custom-checkout-loaded -->';

get_header();
?>

<main class="container" role="main" aria-label="Checkout">
  <div class="header">
    <div class="brand">QP</div>
    <div>
      <h1 class="h1">Confirm & Checkout</h1>
      <p class="small">Complete pickup details and pay securely on this page</p>
    </div>
  </div>

  <div class="grid" id="qp-checkout-grid">
    <!-- Left: custom pickup UI (non-nested) -->
    <section class="card" aria-labelledby="qp-form-title">
      <h2 id="qp-form-title">Pickup details</h2>

      <!-- NOTE: this is a div (not a form) to avoid nesting the WooCommerce checkout form inside it -->
      <div id="qp-checkout-form" novalidate>
        <div class="product-row" aria-live="polite">
          <div class="product-thumb" id="product-thumb"><img id="product-img" src="" alt=""></div>
          <div style="flex:1;min-width:0">
            <div id="product-name" style="font-weight:800">Item</div>
            <div class="small">Pickup location: <span id="product-location">curb</span></div>
            <div style="margin-top:6px">Base price: <strong id="product-base-amt">$0.00</strong></div>
          </div>
        </div>

        <hr style="margin:14px 0"/>

        <!-- Customer information (ids kept to match JS; name attributes added; autocomplete tokens added) -->
        <h3>Customer information</h3>

        <div class="form-row">
          <label for="cust-name">Full name</label>
        </div>
        <input id="cust-name" name="cust_name" type="text" required placeholder="Jane Doe" autocomplete="name">

        <div class="form-row">
          <div style="flex:1">
            <label for="cust-phone">Phone</label>
            <input id="cust-phone" name="cust_phone" type="tel" required placeholder="(555) 555-5555" autocomplete="tel">
          </div>
          <div style="width:12px"></div>
          <div style="flex:1">
            <label for="cust-email">Email</label>
            <input id="cust-email" name="cust_email" type="email" placeholder="you@example.com" autocomplete="email">
            <div class="small note">We send booking confirmation to this email.</div>
          </div>
        </div>

        <label for="addr-line1">Address</label>
        <input id="addr-line1" name="addr_line1" type="text" required placeholder="123 Main St" autocomplete="address-line1">
        <input id="addr-line2" name="addr_line2" type="text" placeholder="Apt, Suite (optional)" autocomplete="address-line2">
        <div class="form-row">
          <input id="addr-city" name="addr_city" type="text" required placeholder="City" autocomplete="address-level2">
          <input id="addr-postal" name="addr_postal" type="text" inputmode="text" autocomplete="postal-code" placeholder="Postal / ZIP (optional)" style="max-width:160px">
          <select id="addr-country" name="addr_country" autocomplete="country" style="max-width:160px">
            <option value="">Country (optional)</option>
            <option value="US">United States</option>
            <option value="CA">Canada</option>
            <option value="GB">United Kingdom</option>
            <option value="AU">Australia</option>
            <option value="DE">Germany</option>
            <option value="FR">France</option>
          </select>
        </div>

        <hr style="margin:14px 0"/>
        <h3>Where should we pick up?</h3>

        <div class="options">
          <div class="options-inner">
            <label class="option" data-fee="0">
              <span class="option-left">
                <input id="pickup-curb" class="visually-hidden" type="radio" name="pickup-location" value="curb" checked />
                <span class="option-label"><span class="label-text">Curb</span></span>
              </span>
              <span class="inline-right">$0</span>
            </label>

            <label class="option" data-fee="10">
              <span class="option-left">
                <input id="pickup-garage" class="visually-hidden" type="radio" name="pickup-location" value="garage" />
                <span class="option-label"><span class="label-text">Garage</span></span>
              </span>
              <span class="inline-right">$10</span>
            </label>

            <label class="option" data-fee="25">
              <span class="option-left">
                <input id="pickup-inside" class="visually-hidden" type="radio" name="pickup-location" value="inside" />
                <span class="option-label"><span class="label-text">Inside</span></span>
              </span>
              <span class="inline-right">$25</span>
            </label>

            <div class="options-other-details">
              <label for="other-details">Other details / access instructions (optional)</label>
              <input id="other-details" name="other_details" type="text" placeholder="e.g. side entrance" autocomplete="off">
            </div>

            <div style="margin-top:10px">
              <label for="stairs-count">Number of stairs (if any)</label>
              <input id="stairs-count" name="stairs_count" type="number" min="0" step="1" value="0" style="max-width:140px" inputmode="numeric" aria-label="Number of stairs">
              <div class="small note">First 5 stairs free; each extra stair costs $0.50</div>
            </div>
          </div>
        </div>

        <hr style="margin:14px 0"/>
        <h3>Schedule</h3>
        <div class="form-row">
          <div style="flex:1">
            <label for="pickup-date">Pickup date</label>
            <input id="pickup-date" name="pickup_date" type="date" required autocomplete="off">
            <div class="small">Choose a date</div>
          </div>
          <div style="align-self:flex-end">
            <input id="rush-checkbox" type="checkbox" name="rush" value="1">
            <label for="rush-checkbox">Rush next-day pickup (+$35)</label>
          </div>
        </div>

        <hr style="margin:14px 0"/>
        <label for="notes">Additional notes (optional)</label>
        <textarea id="notes" name="notes" placeholder="Access instructions, gate codes" autocomplete="off"></textarea>

        <hr style="margin:14px 0"/>

        <!-- Stripe card area (mount point) -->
        <div id="qp-stripe-card-area" style="margin-top:18px">
          <div id="stripe-card-wrapper">
            <label for="stripe-card-element">Card details</label>
            <div id="stripe-card-element" style="padding:12px 0"></div>
            <div id="stripe-card-errors" class="qp-field-error" role="alert" style="display:none;"></div>
          </div>
        </div>

        <hr style="margin:14px 0"/>
        <div class="actions" style="margin-top:18px">
          <button type="button" id="btn-back" class="btn btn-ghost">Back</button>
          <button type="button" id="btn-submit" class="btn btn-primary">Pay &amp; Book</button>
        </div>
      </div> <!-- /#qp-checkout-form -->

      <!-- Now render the WooCommerce checkout form (payment methods) inline below the custom UI -->
      <div id="qp-wc-checkout-area" style="margin-top:20px;">
        <?php
        // Render full WooCommerce checkout form here so payment gateway UI (Stripe Elements) mounts
        echo do_shortcode( '[woocommerce_checkout]' );
        ?>
      </div>
    </section>

    <!-- Right: order summary -->
    <aside class="card" aria-labelledby="qp-summary-title">
      <h3 id="qp-summary-title">Order summary</h3>
      <div id="summary-items" style="margin-top:8px">
        <div class="row"><div>Item</div><div id="sum-base">$0.00</div></div>
        <div class="row"><div>Environmental fee</div><div id="sum-env">$0.00</div></div>
        <div class="row"><div>Location fee</div><div id="sum-location">$0.00</div></div>
        <div class="row"><div>Stairs fee</div><div id="sum-stairs">$0.00</div></div>
        <div class="row"><div>Rush fee</div><div id="sum-rush">$0.00</div></div>
        <hr/>
        <div class="row total"><div>Total</div><div id="sum-total">$0.00</div></div>
        <div class="note">Taxes and payment processing fees may apply at payment.</div>
      </div>

      <div style="margin-top:12px" id="qp-summary-note"></div>
    </aside>
  </div>
</main>

<script>
(function(){
  'use strict';

  // Localized values from PHP (safe to echo)
  const AJAX_URL     = '<?php echo esc_js( $ajax_url ); ?>';
  const QP_NONCE     = '<?php echo esc_js( $qp_nonce ); ?>';
  const CHECKOUT_URL = '<?php echo esc_js( $checkout_url ); ?>';
  const CART_URL     = '<?php echo esc_js( $cart_url ); ?>';

  // Helpers
  function money(n){
    try {
      return (new Intl.NumberFormat(undefined,{style:'currency',currency: (window.QP_RUNTIME&&window.QP_RUNTIME.currency) || 'USD' })).format(Number(n||0));
    } catch(e){ return '$' + Number(n || 0).toFixed(2); }
  }
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  // Elements (cached)
  const productImg = qs('#product-img');
  const productName = qs('#product-name');
  const productBaseAmt = qs('#product-base-amt');
  const productLocationLabel = qs('#product-location');

  const sumBase = qs('#sum-base');
  const sumEnv = qs('#sum-env');
  const sumLocation = qs('#sum-location');
  const sumStairs = qs('#sum-stairs');
  const sumRush = qs('#sum-rush');
  const sumTotal = qs('#sum-total');

  const pickupLocationRadios = qsa('input[name="pickup-location"]') || [];
  const stairsInput = qs('#stairs-count');
  const rushCheckbox = qs('#rush-checkbox');

  const btnSubmit = qs('#btn-submit');
  const btnBack = qs('#btn-back');

  const pickupDateInput = qs('#pickup-date');
  let payload = null; // instant-quote payload object (parsed)
  let serverApplied = false;

  function localDateIso(addDays){
    const d = new Date();
    d.setHours(0,0,0,0);
    d.setDate(d.getDate() + (addDays||0));
    return d.toISOString().slice(0,10);
  }

  function showPickupError(msg){
    clearPickupError();
    if (!pickupDateInput) return;
    const el = document.createElement('div');
    el.className = 'qp-field-error';
    el.style.color = '#b00020';
    el.style.marginTop = '6px';
    el.style.fontSize = '.95rem';
    el.textContent = msg;
    pickupDateInput.insertAdjacentElement('afterend', el);
  }
  function clearPickupError(){
    if (!pickupDateInput) return;
    const next = pickupDateInput.nextElementSibling;
    if ( next && next.classList && next.classList.contains('qp-field-error') ) next.remove();
  }

  function updatePickupDateMin() {
    if (!pickupDateInput) return;
    const add = (rushCheckbox && rushCheckbox.checked) ? 1 : 2;
    const minIso = localDateIso(add);
    pickupDateInput.min = minIso;

    if (rushCheckbox && rushCheckbox.checked) {
      try { pickupDateInput.value = minIso; } catch (e) { /* ignore */ }
      clearPickupError();
    } else {
      if (!pickupDateInput.value || pickupDateInput.value < minIso) {
        try { pickupDateInput.value = minIso; } catch (e) { /* ignore */ }
        showPickupError('Pickup date updated to the minimum allowed: ' + minIso);
        setTimeout(clearPickupError, 3000);
      } else {
        clearPickupError();
      }
    }

    const note = qs('#qp-summary-note');
    if (note) {
      note.textContent = (rushCheckbox && rushCheckbox.checked)
        ? 'Rush booking selected: pickup date set to tomorrow (' + minIso + ').'
        : 'Standard booking: pickup date must be at least 2 days from today (' + minIso + ' or later).';
    }
  }

  function validatePickupDate(){
    if (!pickupDateInput) return { valid:true };
    const val = pickupDateInput.value;
    const add = (rushCheckbox && rushCheckbox.checked) ? 1 : 2;
    const minIso = localDateIso(add);
    clearPickupError();
    if (!val) return { valid:false, message:'Please choose a pickup date.' };
    if (val < minIso) {
      return { valid:false, message: (rushCheckbox && rushCheckbox.checked) ? ('Rush booking requires pickup date of at least tomorrow (' + minIso + ').') : ('Pickup date must be at least 2 days from today (' + minIso + ').') };
    }
    return { valid:true };
  }

  function loadPayloadFromStorage(){
    try {
      const s = sessionStorage.getItem('qp_instant_quote_payload') || localStorage.getItem('qp_instant_quote_payload');
      if (!s) return null;
      return JSON.parse(s);
    } catch(e){
      console.warn('qp: invalid payload in storage', e);
      return null;
    }
  }

  function renderFromPayload(pl){
    payload = pl || null;
    if (!payload || !payload.items || !payload.items.length) {
      if (productName) productName.textContent = 'No item selected';
      if (productBaseAmt) productBaseAmt.textContent = money(0);
      if (sumBase) sumBase.textContent = money(0);
      if (sumEnv) sumEnv.textContent = money(0);
      if (sumLocation) sumLocation.textContent = money(0);
      if (sumStairs) sumStairs.textContent = money(0);
      if (sumRush) sumRush.textContent = money(0);
      if (sumTotal) sumTotal.textContent = money(0);
      return;
    }

    const first = payload.items[0] || payload.items;
    if (first && first.image && productImg) productImg.src = first.image;
    if (productName) productName.textContent = (first && first.name) ? first.name : 'Item';

    const itemsCost = Number(payload.itemsCost || 0);
    const totalEnv = Number(payload.totalEnvFees || payload.totalEnv || 0);
    if (productBaseAmt) productBaseAmt.textContent = money(itemsCost);
    if (sumBase) sumBase.textContent = money(itemsCost);
    if (sumEnv) sumEnv.textContent = money(totalEnv);

    updateAllTotals();
  }

  function getLocationFee(){
    try {
      const sel = document.querySelector('input[name="pickup-location"]:checked');
      if (!sel) return 0;
      const parent = sel.closest('[data-fee]');
      if (parent) {
        const f = parseFloat(parent.getAttribute('data-fee') || 0);
        if (!isNaN(f)) return f;
      }
      const inline = sel.closest('.option') && sel.closest('.option').querySelector('.inline-right');
      if (inline) {
        const txt = inline.textContent.replace(/[^0-9.\-]/g,'');
        const f = parseFloat(txt || 0);
        return isNaN(f) ? 0 : f;
      }
    } catch(e){}
    return 0;
  }

  function getStairsFee(){
    try {
      const n = Math.max(0, Math.floor(Number(stairsInput && stairsInput.value || 0)));
      const extra = Math.max(0, n - 5);
      return Math.round(extra * 0.5 * 100) / 100;
    } catch(e){ return 0; }
  }

  function getRushFee(){
    return rushCheckbox && rushCheckbox.checked ? 35.00 : 0.00;
  }

  function updateAllTotals(){
    const itemsCost = Number(payload ? (payload.itemsCost || 0) : 0);
    const totalEnv = Number(payload ? (payload.totalEnvFees || payload.totalEnv || 0) : 0);
    const loc = getLocationFee();
    const stairs = getStairsFee();
    const rush = getRushFee();

    if (sumLocation) sumLocation.textContent = money(loc);
    if (sumStairs) sumStairs.textContent = money(stairs);
    if (sumRush) sumRush.textContent = money(rush);

    const total = Math.round( (Number(itemsCost) + Number(totalEnv) + loc + stairs + rush) * 100 ) / 100;
    if (sumTotal) sumTotal.textContent = money(total);
  }

  function applyPayloadToServerIfNeeded() {
    return new Promise(function(resolve){
      try {
        const s = sessionStorage.getItem('qp_instant_quote_payload') || localStorage.getItem('qp_instant_quote_payload');
        if (!s) {
          return resolve({ success:false, reason:'no_payload' });
        }
        const form = new URLSearchParams();
        form.append('action','qp_apply_payload');
        form.append('qp_nonce', QP_NONCE);
        form.append('payload', s);

        fetch(AJAX_URL, {
          method: 'POST',
          headers: { 'X-Requested-With':'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString(),
          credentials: 'same-origin'
        }).then(function(res){
          if (!res.ok) throw new Error('Network status ' + res.status);
          return res.json();
        }).then(function(json){
          if (json && json.success) {
            serverApplied = true;
            try { localStorage.setItem('qp_payload_applied_ts', String(Date.now())); } catch(e){}
            resolve(json);
          } else {
            resolve({ success:false, json:json });
          }
        }).catch(function(err){
          console.warn('qp: apply payload failed', err);
          resolve({ success:false, error:err });
        });
      } catch(e){
        resolve({ success:false, error:e });
      }
    });
  }

  function ensureHiddenCheckoutFields(wcForm) {
    if (!wcForm) return;
    function setHidden(name, value){
      let el = wcForm.querySelector('input[name="'+name+'"]');
      if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = name;
        wcForm.appendChild(el);
      }
      el.value = value;
    }
    setHidden('pickup_date', pickupDateInput ? (pickupDateInput.value || '') : '');
    setHidden('rush', rushCheckbox && rushCheckbox.checked ? '1' : '0');
    const notes = qs('#notes');
    if (notes) setHidden('qp_notes', notes.value || '');
  }

  function submitWooCheckout() {
    return new Promise(function(resolve, reject){
      const wcForm = document.querySelector('form.checkout');
      if (!wcForm) {
        window.location.href = CHECKOUT_URL;
        return resolve({ success:false, reason:'no_wc_form' });
      }

      const mappings = {
        'billing_first_name': 'cust-name',
        'billing_email': 'cust-email',
        'billing_phone': 'cust-phone',
        'billing_address_1': 'addr-line1',
        'billing_address_2': 'addr-line2',
        'billing_city': 'addr-city',
        'billing_postcode': 'addr-postal',
        'billing_country': 'addr-country'
      };

      Object.keys(mappings).forEach(function(billingName){
        const our = document.getElementById(mappings[billingName]);
        if (!our) return;
        const input = wcForm.querySelector('[name="'+billingName+'"]') || wcForm.querySelector('#'+billingName);
        if (input) {
          try { input.value = our.value || ''; } catch(e){}
          input.dispatchEvent(new Event('input', { bubbles:true }));
          input.dispatchEvent(new Event('change', { bubbles:true }));
        } else {
          const alt = document.querySelector('[name="'+billingName+'"]');
          if (alt) {
            alt.value = our.value || '';
            alt.dispatchEvent(new Event('input', { bubbles:true }));
            alt.dispatchEvent(new Event('change', { bubbles:true }));
          }
        }
      });

      ensureHiddenCheckoutFields(wcForm);

      try {
        if (window.jQuery && jQuery.fn && typeof jQuery(wcForm).trigger === 'function') {
          jQuery(wcForm).trigger('submit');
        } else {
          wcForm.submit();
        }
        resolve({ success:true });
      } catch (e) {
        console.error('qp: error submitting wc form', e);
        reject(e);
      }
    });
  }

  function copyCustomToWooFields(wcForm) {
    if (!wcForm) return;
    function setIfExists(name, value) {
      const el = wcForm.querySelector('[name="'+name+'"]');
      if (el) {
        el.value = value || '';
        el.dispatchEvent(new Event('input', { bubbles:true }));
        el.dispatchEvent(new Event('change', { bubbles:true }));
      } else {
        const alt = document.querySelector('[name="'+name+'"]');
        if (alt) { alt.value = value || ''; alt.dispatchEvent(new Event('input', { bubbles:true })); alt.dispatchEvent(new Event('change', { bubbles:true })); }
      }
    }
    setIfExists('billing_first_name', qs('#cust-name') ? qs('#cust-name').value : '');
    setIfExists('billing_phone', qs('#cust-phone') ? qs('#cust-phone').value : '');
    setIfExists('billing_email', qs('#cust-email') ? qs('#cust-email').value : '');
    setIfExists('billing_address_1', qs('#addr-line1') ? qs('#addr-line1').value : '');
    setIfExists('billing_address_2', qs('#addr-line2') ? qs('#addr-line2').value : '');
    setIfExists('billing_city', qs('#addr-city') ? qs('#addr-city').value : '');
    setIfExists('billing_postcode', qs('#addr-postal') ? qs('#addr-postal').value : '');
    setIfExists('billing_country', qs('#addr-country') ? qs('#addr-country').value : '');

    function setHidden(name, value) {
      let h = wcForm.querySelector('input[name="'+name+'"]');
      if (!h) {
        h = document.createElement('input'); h.type = 'hidden'; h.name = name; wcForm.appendChild(h);
      }
      h.value = value || '';
    }
    setHidden('pickup_date', qs('#pickup-date') ? qs('#pickup-date').value : '');
    setHidden('rush', qs('#rush-checkbox') && qs('#rush-checkbox').checked ? '1' : '0');
    setHidden('qp_notes', qs('#notes') ? qs('#notes').value : '');
  }

  function wire() {
    if (Array.isArray(pickupLocationRadios)) {
      pickupLocationRadios.forEach(function(r){
        r.addEventListener('change', function(){ if (productLocationLabel) productLocationLabel.textContent = r.value; updateAllTotals(); });
        const wrapper = r.closest('.option');
        if (wrapper) wrapper.addEventListener('click', function(ev){
          try { r.checked = true; } catch(e){}
          updateAllTotals();
        });
      });
    }

    if (stairsInput) stairsInput.addEventListener('input', updateAllTotals);
    if (rushCheckbox) rushCheckbox.addEventListener('change', function(){
      updateAllTotals();
      updatePickupDateMin();
    });

    if (pickupDateInput) {
      pickupDateInput.addEventListener('input', function(){
        if (!pickupDateInput.value) { clearPickupError(); return; }
        if (pickupDateInput.min && pickupDateInput.value < pickupDateInput.min) {
          showPickupError('Selected date is too early — adjusted to the minimum allowed.');
          pickupDateInput.value = pickupDateInput.min;
        } else {
          clearPickupError();
        }
      });
    }

    if (btnBack) {
      btnBack.addEventListener('click', function(){
        if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
          history.back();
        } else {
          window.location.assign(CART_URL);
        }
      });
    }
  }

  // ---------- Hide WooCommerce checkout sections after Stripe mounts ----------
  function hideCheckoutSectionsScoped() {
    const area = document.getElementById('qp-wc-checkout-area');
    if (!area) return;

    // Elements we want to hide to keep the custom UI focused:
    const selectorsToHide = [
      '.woocommerce-billing-fields',
      '.woocommerce-billing-fields__field-wrapper',
      '.woocommerce-checkout-review-order',
      '.woocommerce-checkout-review-order-table',
      '#order_review',
      '.order_review',
      '.woocommerce-additional-fields',
      '#order_comments_field',
      '#order_comments'
    ];

    selectorsToHide.forEach(sel => {
      Array.from(area.querySelectorAll(sel)).forEach(el => {
        // Hide visually but keep in DOM so form fields still submit.
        el.style.display = 'none';
        el.setAttribute('data-qp-hidden', '1');
        el.setAttribute('aria-hidden', 'true');
      });
    });

    // Ensure payment methods area is visible so Stripe Elements (mounted elsewhere) or payment boxes remain accessible
    Array.from(area.querySelectorAll('.woocommerce-checkout-payment, .wc_payment_method, .payment_box')).forEach(function(el){
      el.style.display = 'block';
      el.style.visibility = 'visible';
      el.style.height = 'auto';
      el.style.opacity = '1';
      el.removeAttribute('aria-hidden');
    });
  }

  function waitForStripeMountThenHide() {
    const area = document.getElementById('qp-wc-checkout-area');
    if (!area) return;

    let attempts = 0;
    const maxAttempts = 30;
    const intervalMs = 300;

    const interval = setInterval(function(){
      attempts++;

      const stripeElement = area.querySelector('.StripeElement') || area.querySelector('.__PrivateStripeElement') || document.getElementById('stripe-card-element');
      const stripeIframe = Array.from(area.querySelectorAll('iframe')).find(i => (i.src||'').includes('stripe'));

      if (stripeElement || stripeIframe || attempts >= maxAttempts) {
        clearInterval(interval);

        Array.from(area.querySelectorAll('.woocommerce-checkout-payment, .wc_payment_method, .payment_box')).forEach(function(el){
          el.style.display = 'block';
          el.style.visibility = 'visible';
          el.style.height = 'auto';
          el.style.opacity = '1';
        });

        hideCheckoutSectionsScoped();
      }
    }, intervalMs);
  }

  // Stripe Elements init and payment flow
  let stripe = null;
  let card = null;

  function initStripeElements() {
    const STRIPE_PUB = '<?php echo esc_js( ( defined( "QP_STRIPE_PUBLISHABLE_KEY" ) && QP_STRIPE_PUBLISHABLE_KEY ) ? QP_STRIPE_PUBLISHABLE_KEY : get_option( "qp_stripe_publishable_key", "pk_test_REPLACE_ME" ) ); ?>';
    if (!STRIPE_PUB || STRIPE_PUB.indexOf('pk_') !== 0) {
      console.warn('Stripe publishable key not set — configure qp_stripe_publishable_key in WP options or hardcode for testing.');
      return;
    }
    if (!window.Stripe) {
      console.error('Stripe.js failed to load.');
      return;
    }

    stripe = Stripe(STRIPE_PUB);
    const elements = stripe.elements();
    const style = {
      base: {
        color: '#0a2e3b',
        fontFamily: 'Inter, system-ui, Roboto, Arial, sans-serif',
        fontSize: '16px',
        '::placeholder': { color: '#6b7b8a' }
      }
    };

    const cardContainer = document.getElementById('stripe-card-element');
    if (!cardContainer) {
      console.warn('stripe-card-element not found in DOM');
      return;
    }
    card = elements.create('card', { style: style, hidePostalCode: true });
    card.mount(cardContainer);

    const stripeErrors = document.getElementById('stripe-card-errors');
    card.on('change', function(event){
      if (event.error) {
        stripeErrors.style.display = 'block';
        stripeErrors.textContent = event.error.message;
      } else {
        stripeErrors.style.display = 'none';
        stripeErrors.textContent = '';
      }
    });

    // After Stripe mounts, hide WooCommerce sections so custom UI is primary
    waitForStripeMountThenHide();
  }

  async function createPaymentIntentOnServer(payload) {
    try {
      const form = new URLSearchParams();
      form.append('action', 'qp_create_payment_intent');
      form.append('qp_nonce', typeof QP_NONCE !== 'undefined' ? QP_NONCE : '');
      form.append('payload', JSON.stringify(payload || {}));

      const resp = await fetch(AJAX_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString()
      });

      if (!resp.ok) {
        const txt = await resp.text();
        console.error('createPaymentIntentOnServer HTTP error', resp.status, txt);
        return { success: false, message: 'Network error: ' + resp.status, raw: txt };
      }

      const json = await resp.json().catch(async (e) => {
        const txt = await resp.text();
        console.error('createPaymentIntentOnServer invalid JSON response', txt);
        return { success: false, message: 'Invalid JSON response', raw: txt };
      });

      console.debug('createPaymentIntentOnServer response', json);
      return json;
    } catch (err) {
      console.error('createPaymentIntentOnServer exception', err);
      return { success: false, message: err && err.message ? err.message : 'Unknown error' };
    }
  }

  async function attachPayHandler() {
    if (!btnSubmit) return;
    btnSubmit.addEventListener('click', async function(e){
      e.preventDefault();

      const valid = validatePickupDate();
      if (!valid.valid) {
        if (pickupDateInput) {
          showPickupError(valid.message);
          try { pickupDateInput.focus(); } catch(e){}
        } else {
          alert(valid.message);
        }
        return;
      }

      if (!card || !stripe) {
        alert('Payment system not initialized. Please refresh the page.');
        return;
      }

      btnSubmit.disabled = true;
      const prevText = btnSubmit.textContent;
      btnSubmit.textContent = 'Processing…';

      try {
        // Ensure custom fields are copied into the WooCommerce form (hidden inputs) before server-side intent creation
        const wcForm = document.querySelector('form.checkout');
        if (wcForm) copyCustomToWooFields(wcForm);

        await applyPayloadToServerIfNeeded();

        const payloadForServer = {}; // optionally include identifiers
        const piResp = await createPaymentIntentOnServer(payloadForServer);
        if (!piResp || !piResp.success || !piResp.client_secret) {
          throw new Error((piResp && piResp.message) ? piResp.message : 'Unable to create payment intent on server');
        }
        const clientSecret = piResp.client_secret;

        const billingDetails = {
          name: (document.getElementById('cust-name') || {}).value || undefined,
          email: (document.getElementById('cust-email') || {}).value || undefined,
          phone: (document.getElementById('cust-phone') || {}).value || undefined,
          address: {
            line1: (document.getElementById('addr-line1') || {}).value || undefined,
            line2: (document.getElementById('addr-line2') || {}).value || undefined,
            city: (document.getElementById('addr-city') || {}).value || undefined,
            postal_code: (document.getElementById('addr-postal') || {}).value || undefined,
            country: (document.getElementById('addr-country') || {}).value || undefined
          }
        };

        const result = await stripe.confirmCardPayment(clientSecret, {
          payment_method: {
            card: card,
            billing_details: billingDetails
          }
        });

        if (result.error) {
          const stripeErrors = document.getElementById('stripe-card-errors');
          stripeErrors.style.display = 'block';
          stripeErrors.textContent = result.error.message || 'Payment failed';
          btnSubmit.disabled = false;
          btnSubmit.textContent = prevText;
          return;
        }

        if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
          // if server provided a redirect_url (recommended), use that
          if (piResp && piResp.redirect_url) {
            window.location.href = piResp.redirect_url;
            return;
          }
          // if server returned order_id/pay_url, use pay_url
          if (piResp && piResp.pay_url) {
            window.location.href = piResp.pay_url;
            return;
          }
          // fallback: go to checkout page with query param (not ideal, but kept for compatibility)
          window.location.href = CHECKOUT_URL + '?qp_paid=1';
          return;
        }

        alert('Payment did not complete.');
      } catch (err) {
        console.error('Payment error', err);
        const stripeErrors = document.getElementById('stripe-card-errors');
        if (stripeErrors) {
          stripeErrors.style.display = 'block';
          stripeErrors.textContent = err.message || String(err);
        } else {
          alert(err.message || String(err));
        }
      } finally {
        try { btnSubmit.disabled = false; btnSubmit.textContent = prevText; } catch(e){}
      }
    });
  }

  // Init on DOM ready
  document.addEventListener('DOMContentLoaded', function(){
    try {
      updatePickupDateMin();
      const pl = loadPayloadFromStorage();
      renderFromPayload(pl);
      wire();
    } catch (e) {
      console.warn('qp init error', e);
    }

    if ( window.jQuery ) {
      jQuery(function(){
        jQuery(document.body).trigger('updated_checkout');
        setTimeout(function(){
          jQuery(document.body).trigger('updated_checkout');
        }, 500);
      });
    }

    initStripeElements();
    attachPayHandler();
  });

})();
</script>

<!-- Auto-fix script: ensure ids/names/autocomplete and report broken assets -->
<script>
(function(){
  'use strict';

  function onReady(cb){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cb);
    else cb();
  }

  onReady(function(){
    function ensureIdName(el) {
      if (!el || !el.tagName) return;
      if (!/^(INPUT|SELECT|TEXTAREA)$/i.test(el.tagName)) return;
      const type = (el.getAttribute('type')||'').toLowerCase();
      if (!el.id) {
        if (el.name) el.id = el.name.replace(/\[|\]|[: ]/g,'_') || ('qp_auto_' + Math.random().toString(36).slice(2,8));
        else el.id = 'qp_auto_' + Math.random().toString(36).slice(2,8);
      }
      if (!el.name) el.name = el.id;
    }

    function setAutocompleteIfMissing(el) {
      if (!el || el.autocomplete) return;
      const id = (el.id || '').toLowerCase();
      const name = (el.name || '').toLowerCase();
      const placeholder = (el.placeholder || '').toLowerCase();
      const label = (document.querySelector('label[for="'+(el.id||'')+'"]') || {}).textContent || '';
      const hay = (id + ' ' + name + ' ' + placeholder + ' ' + label).toLowerCase();

      if (/\b(email|e-mail)\b/.test(hay)) el.setAttribute('autocomplete','email');
      else if (/\b(phone|tel|mobile)\b/.test(hay)) el.setAttribute('autocomplete','tel');
      else if (/\b(family[-_ ]?name|surname|last name)\b/.test(hay)) el.setAttribute('autocomplete','family-name');
      else if (/\b(given[-_ ]?name|first name)\b/.test(hay)) el.setAttribute('autocomplete','given-name');
      else if (/\b(address[-_ ]?line1|address1|street|addr_line1)\b/.test(hay)) el.setAttribute('autocomplete','address-line1');
      else if (/\b(address[-_ ]?line2|address2|apt|suite)\b/.test(hay)) el.setAttribute('autocomplete','address-line2');
      else if (/\b(city|town|locality|address-level2)\b/.test(hay)) el.setAttribute('autocomplete','address-level2');
      else if (/\b(state|region|province|address-level1)\b/.test(hay)) el.setAttribute('autocomplete','address-level1');
      else if (/\b(postal|zip|postcode|postal-code)\b/.test(hay)) el.setAttribute('autocomplete','postal-code');
      else if (/\b(country|country-code)\b/.test(hay)) el.setAttribute('autocomplete','country');
    }

    function fixLabelFors() {
      const labels = Array.from(document.querySelectorAll('label[for]'));
      labels.forEach(function(label){
        const targetId = label.getAttribute('for');
        if (!targetId) return;
        if (!document.getElementById(targetId)) {
          const candidate = document.querySelector('[name="'+targetId+'"]') || document.querySelector('[name="'+targetId.replace(/_+/g,' ')+'"]');
          if (candidate && candidate.id) {
            label.setAttribute('for', candidate.id);
          } else if (candidate && !candidate.id) {
            candidate.id = targetId + '_auto' ;
            label.setAttribute('for', candidate.id);
          } else {
            console.warn('Label for="' + targetId + '" has no matching id on page.', label);
          }
        }
      });
    }

    const scopeSelectors = ['#qp-checkout-form', '#qp-wc-checkout-area', 'form.checkout', 'main'];
    const seen = new Set();

    scopeSelectors.forEach(function(sel){
      const root = document.querySelector(sel) || document;
      if (!root) return;
      const controls = Array.from(root.querySelectorAll('input,select,textarea'));
      controls.forEach(function(el){
        if (seen.has(el)) return;
        seen.add(el);
        ensureIdName(el);
        setAutocompleteIfMissing(el);
      });
    });

    fixLabelFors();

    ['cust-name','cust-email','cust-phone','addr-line1','addr-city','pickup-date'].forEach(function(id){
      const el = document.getElementById(id);
      if (el && el.required) el.setAttribute('aria-required','true');
    });

    (function checkStylesheets(){
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l=>l.href).filter(Boolean);
      if (!links.length) return;
      Promise.all(links.map(href => fetch(href, { method:'HEAD', mode:'no-cors' }).then(r => ({ href, ok: r && (r.status>=200 && r.status<400), status: r.status })).catch(err => ({ href, ok:false, error: String(err) })) ))
      .then(results => {
        const failed = results.filter(r => !r.ok);
        if (failed.length) console.warn('QP: stylesheet fetch failures detected:', failed);
      }).catch(()=>{});
    })();

    (function findCaptchaScripts(){
      const scripts = Array.from(document.querySelectorAll('script[src]')).map(s=>s.src);
      const matches = scripts.filter(src => /api\.js.*captcha|captcha|recaptcha|protected/i.test(src));
      if (matches.length) console.info('QP: Found captcha/Protected-Audience related scripts:', matches);
    })();

    console.info('QP: autofill/id/name/label auto-fix passed.');
  });
})();
</script>

<style>
/* (styles unchanged from your template) */
:root{--brand-navy:#1c3557;--brand-orange:#ff7f32;--brand-teal:#12c9b4;--bg:#f7f9fb;--card-bg:#fff;--muted:#6b7b8a;--radius:10px;--shadow:0 6px 24px rgba(17,24,39,0.06)}
body{font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:#0a2e3b;margin:0;padding:24px}
.container{max-width:980px;margin:0 auto}
.header{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.brand{width:64px;height:64px;border-radius:10px;background:linear-gradient(135deg,var(--brand-teal),#0fb7a4);display:grid;place-items:center;color:#fff;font-weight:800}
.h1{font-size:1.4rem;margin:0}
.grid{display:grid;grid-template-columns:1fr 360px;gap:20px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
.card{background:var(--card-bg);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.product-row{display:flex;gap:12px;align-items:center}
.product-thumb{width:96px;height:96px;border-radius:12px;background:#fff;display:grid;place-items:center;border:1px solid rgba(0,0,0,.04)}
.product-thumb img{max-width:84%;max-height:84%;object-fit:contain}
.small{font-size:.92rem;color:var(--muted)}
.form-row{display:flex;gap:12px;margin-bottom:12px}
@media(max-width:640px){.form-row{flex-direction:column}}
input,textarea,select{width:100%;padding:10px;border:1px solid rgba(8,23,34,0.08);border-radius:8px;background:#fff;box-sizing:border-box}
textarea{min-height:80px}
.options{display:flex;flex-direction:column;gap:8px;margin-top:8px;align-items:flex-start}
.options .options-inner{width:100%;max-width:520px;box-sizing:border-box}
.option{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;border:1px solid rgba(0,0,0,0.08);background:#fff;width:100%;box-sizing:border-box;cursor:pointer}
.option-left{display:flex;align-items:center;gap:12px;flex:1 1 auto;min-width:0}
.option .label-text{flex:1 1 auto;line-height:1;min-width:0;word-break:break-word}
.option .inline-right{flex:0 0 80px;text-align:right;font-weight:800;color:var(--brand-navy)}
.row{display:flex;justify-content:space-between;align-items:center}
.total{font-weight:900;font-size:1.2rem}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
.btn{padding:10px 16px;border-radius:999px;border:0;cursor:pointer;font-weight:800}
.btn-primary{background:var(--brand-orange);color:var(--brand-navy)}
.btn-ghost{background:transparent;border:1px solid rgba(0,0,0,0.08)}
.note{font-size:.9rem;color:var(--muted);margin-top:6px}
.visually-hidden{position:absolute!important;height:1px;width:1px;overflow:hidden;clip:rect(1px,1px,1px,1px);white-space:nowrap;border:0;padding:0;margin:-1px}
.qp-field-error{color:#b00020;margin-top:6px;font-size:.95rem}
</style>

<?php
get_footer();
?>