<?php
/**
 * Template Name: Checkout Page (Custom)
 * Description: Custom checkout layout. Edit the page in WP Admin / Elementor; place the Elementor WooCommerce Checkout widget into the content or in the placeholder below.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="qp-checkout-root" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; padding:24px; background:#f7f9fb;">
  <div class="container" style="max-width:980px;margin:0 auto;">
    <div class="header" style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
      <div class="brand" style="width:64px;height:64px;border-radius:10px;background:linear-gradient(135deg,#12c9b4,#0fb7a4);display:grid;place-items:center;color:#fff;font-weight:800">QP</div>
      <h1 class="h1" style="font-size:1.4rem;margin:0"><?php the_title(); ?></h1>
    </div>

    <div class="grid" style="display:grid;grid-template-columns:1fr 360px;gap:20px">
      <main class="card" style="background:#fff;border-radius:10px;padding:18px;box-shadow:0 6px 24px rgba(17,24,39,0.06)">
        <!-- If the page has content (Elementor or editor), output it so you can replace placeholder with widget.
             Otherwise, render fallback markup with a visible placeholder. -->
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                // This outputs what you edit in the page editor or Elementor.
                the_content();
            endwhile;
        else :
            // Fallback static markup (only shown if the page has no content)
            ?>
            <form id="checkout-form" novalidate>
              <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
                <div class="product-thumb" style="width:96px;height:96px;border-radius:12px;background:#fff;display:grid;place-items:center;border:1px solid rgba(0,0,0,.04)">
                  <img id="product-img" src="" alt="" style="max-width:84%;max-height:84%;object-fit:contain">
                </div>
                <div>
                  <div style="font-weight:700">Item</div>
                  <div class="small" style="color:#6b7b8a">Pickup location: curb</div>
                  <div>Base price: <strong id="product-base-amt">$0.00</strong></div>
                </div>
              </div>

              <hr style="margin:14px 0"/>
              <h3>Customer information</h3>
              <label for="cust-name">Full name</label>
              <input id="cust-name" name="name" type="text" required placeholder="Jane Doe" style="width:100%;padding:10px;border:1px solid rgba(8,23,34,0.08);border-radius:8px;background:#fff;box-sizing:border-box;margin-bottom:8px">
              <label for="cust-phone">Phone</label>
              <input id="cust-phone" name="phone" type="tel" required placeholder="(555) 555-5555" style="width:100%;padding:10px;border:1px solid rgba(8,23,34,0.08);border-radius:8px;background:#fff;box-sizing:border-box;margin-bottom:8px">

              <hr style="margin:14px 0"/>
              <h3>Payment</h3>
              <div class="note" style="font-size:.95rem;color:#6b7b8a;margin-bottom:8px">We use Stripe (secure). In Elementor, drop the WooCommerce Checkout widget into the placeholder below.</div>

              <div id="elementor-checkout-placeholder" style="border:2px dashed rgba(0,0,0,0.06);padding:18px;border-radius:12px;text-align:center;color:#6b7b8a;">
                Open this page in Elementor and replace this block with the Elementor WooCommerce Checkout widget (or use the Checkout block).
              </div>

              <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
                <button type="button" id="btn-back" class="btn btn-ghost" style="padding:10px 16px;border-radius:999px;border:1px solid rgba(0,0,0,0.08);background:transparent">Back</button>
                <button type="submit" id="btn-submit" class="btn btn-primary" style="padding:10px 16px;border-radius:999px;background:#ff7f32;color:#0a2e3b;border:0;font-weight:800">Pay & Book</button>
              </div>
            </form>
        <?php
        endif;
        ?>
      </main>

      <aside class="card" style="background:#fff;border-radius:10px;padding:18px;box-shadow:0 6px 24px rgba(17,24,39,0.06)">
        <h3 style="margin:0 0 8px">Order summary</h3>
        <div>Item — <span id="summary-base">$0.00</span></div>
        <div>Location fee — <span id="summary-location">$0.00</span></div>
        <div>Stairs fee — <span id="summary-stairs">$0.00</span></div>
        <div>Rush fee — <span id="summary-rush">$0.00</span></div>
        <hr style="margin:12px 0"/>
        <div class="total" style="font-weight:900;font-size:1.2rem">Total <span id="summary-total">$0.00</span></div>
        <div class="note" style="font-size:.9rem;color:#6b7b8a;margin-top:6px">Taxes and fees may apply at payment.</div>
      </aside>
    </div>
  </div>
</div>

<?php
get_footer();