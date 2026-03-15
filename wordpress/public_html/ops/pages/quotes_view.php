<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth.php';
require_login('admin');

$root = realpath(__DIR__ . '/..');
if (!$root) {
    die('Path error');
}

$cfg = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '/ops', '/');

if (!defined('BWQT_OPS_CONTEXT')) {
    define('BWQT_OPS_CONTEXT', true);
}

require_once realpath($root . '/../wp-load.php');

if (!class_exists('BW_Project_Quote_Tool') || !method_exists('BW_Project_Quote_Tool', 'get_instance')) {
    http_response_code(500);
    die('Quote tool plugin is not available.');
}

$tool = BW_Project_Quote_Tool::get_instance();
if (!$tool) {
    http_response_code(500);
    die('Quote tool plugin failed to initialize.');
}

$postId = (int) ($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(400);
    die('Missing quote post_id.');
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $saved = $tool->save_review_adjustments($postId, $_POST, $_SESSION['name'] ?? ($_SESSION['user_id'] ?? ''));
    if (is_wp_error($saved)) {
        $error = $saved->get_error_message();
    } else {
        $notice = 'Review adjustments saved.';
    }
}

$quote = $tool->get_review_quote($postId);
if (!$quote) {
    http_response_code(404);
    die('Quote not found.');
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include $root . '/partials/header.php';
?>
<style>
.stack{display:grid;gap:14px}
.card{background:#161616;border:1px solid #262626;border-radius:12px;padding:14px}
.grid2{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.kv{display:grid;grid-template-columns:160px 1fr;gap:8px;font-size:14px}
.line{border:1px solid #242424;border-radius:10px;padding:12px;margin-top:10px}
.line-meta{margin-top:4px;color:#aaa}
.math summary{cursor:pointer;color:#a9c7ff}
.math-table{width:100%;border-collapse:collapse;margin-top:8px}
.math-table td{padding:6px 4px;border-bottom:1px solid #242424}
.math-table td:last-child{text-align:right}
label{display:block;font-size:12px;color:#aaa;margin:6px 0 4px}
input,select,textarea{width:100%;padding:8px 10px;background:#111;color:#eee;border:1px solid #2a2a2a;border-radius:8px;box-sizing:border-box}
textarea{min-height:120px}
.btn{padding:8px 12px;border:1px solid #2a2a2a;background:#1b1b1b;border-radius:8px;color:#eee;text-decoration:none;cursor:pointer}
.pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#0f1720;border:1px solid #2b3a4f;font-size:12px}
.notice{background:#102d19;border:1px solid #1e6a33;color:#d7ffe3;padding:10px 12px;border-radius:10px}
.error{background:#311010;border:1px solid #5a2323;color:#ffd6d6;padding:10px 12px;border-radius:10px}
.totals{display:grid;gap:8px}
.totals div{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #242424}
.totals div:last-child{border-bottom:0}
@media (max-width:1100px){.grid2,.grid3,.grid4{grid-template-columns:1fr}}
</style>

<h2>Quote Review: <?= h($quote['client_name']) ?> / <?= h($quote['project_name']) ?></h2>

<?php if ($notice): ?><div class="notice" style="margin-bottom:12px;"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error" style="margin-bottom:12px;"><?= h($error) ?></div><?php endif; ?>

<div class="stack">
  <div class="card">
    <div class="grid2">
      <div class="kv">
        <div>Client</div><div><?= h($quote['client_name']) ?></div>
        <div>Project</div><div><?= h($quote['project_name']) ?></div>
        <div>Quoted By</div><div><?= h($quote['quoted_by']) ?></div>
        <div>Email</div><div><?= h($quote['contact_email'] ?: '—') ?></div>
        <div>Phone</div><div><?= h($quote['contact_phone'] ?: '—') ?></div>
        <div>Need By</div><div><?= h($quote['need_by_date'] ?: '—') ?></div>
        <div>Status</div><div><span class="pill"><?= h($quote['quote_status']) ?></span></div>
      </div>
      <div class="kv">
        <div>Entry Estimate</div><div>$<?= number_format((float) $quote['total'], 2) ?></div>
        <div>Reviewed Total</div><div>$<?= number_format((float) $quote['review_totals']['final_total'], 2) ?></div>
        <div>Reviewed By</div><div><?= h($quote['review']['reviewed_by'] ?: '—') ?></div>
        <div>Reviewed At</div><div><?= h($quote['review']['reviewed_at'] ?: '—') ?></div>
        <div>Admin</div><div><a href="<?= h(admin_url('post.php?post=' . (int) $postId . '&action=edit')) ?>" style="color:#a9c7ff;">Open in wp-admin</a></div>
      </div>
    </div>
  </div>

  <div class="grid2">
    <div class="card">
      <h3 style="margin-top:0;">Line Pricing</h3>
      <?php foreach ($quote['lines'] as $line): ?>
        <div
          class="line"
          data-line-qty="<?= h((string) $line['total_qty']) ?>"
          data-line-regular-qty="<?= h((string) $line['regular_qty']) ?>"
          data-line-extended-qty="<?= h((string) $line['extended_qty']) ?>"
          data-line-base-unit="<?= h((string) $line['base_unit_cost']) ?>"
          data-line-extended-surcharge="<?= h((string) $line['extended_size_surcharge']) ?>"
          data-line-print-total="<?= h((string) $line['print_total']) ?>"
          data-line-base-total="<?= h((string) $line['line_total']) ?>"
        >
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
              <strong><?= h($line['product_code']) ?> | <?= h($line['product_name']) ?></strong>
              <div class="line-meta">
                Qty <?= (int) $line['total_qty'] ?><?php if ($line['extended_qty']): ?> · 2XL+ <?= (int) $line['extended_qty'] ?><?php endif; ?>
                · Cost Unit $<?= number_format((float) $line['unit_price'], 2) ?>
                · Customer Unit $<span data-line-final-unit><?= number_format((float) $line['final_customer_unit_price'], 2) ?></span>
                <?php if ($line['regular_qty']): ?>
                  · Regular $<span data-line-regular-unit><?= number_format((float) $line['regular_customer_unit_price'], 2) ?></span>
                <?php endif; ?>
                <?php if ($line['extended_qty']): ?>
                  · 2XL+ $<span data-line-extended-unit><?= number_format((float) $line['extended_customer_unit_price'], 2) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div style="font-weight:700;">
              Cost $<?= number_format((float) $line['line_total'], 2) ?><br>
              Customer $<span data-line-final-total><?= number_format((float) $line['final_customer_line_total'], 2) ?></span>
            </div>
          </div>
          <details class="math" style="margin-top:10px;">
            <summary>Show math</summary>
            <table class="math-table">
              <tr><td>Material</td><td>$<?= number_format((float) $line['material_total'], 2) ?></td></tr>
              <tr><td>Print</td><td>$<?= number_format((float) $line['print_total'], 2) ?></td></tr>
              <tr><td>Cost Per Unit</td><td>$<?= number_format((float) $line['unit_price'], 2) ?></td></tr>
              <tr><td>Margin Share</td><td>$<span data-line-margin-amount><?= number_format((float) $line['margin_amount'], 2) ?></span></td></tr>
              <tr><td>Shared Fees/Discount Share</td><td>$<span data-line-shared-amount><?= number_format((float) $line['shared_adjustment_amount'], 2) ?></span></td></tr>
              <tr><td>Tax Share</td><td>$<span data-line-tax-amount><?= number_format((float) $line['tax_share_amount'], 2) ?></span></td></tr>
              <?php if ($line['regular_qty']): ?>
                <tr><td>Regular Unit Price</td><td>$<span data-line-regular-unit-math><?= number_format((float) $line['regular_customer_unit_price'], 2) ?></span></td></tr>
              <?php endif; ?>
              <?php if ($line['extended_qty']): ?>
                <tr><td>2XL+ Unit Price</td><td>$<span data-line-extended-unit-math><?= number_format((float) $line['extended_customer_unit_price'], 2) ?></span></td></tr>
              <?php endif; ?>
              <tr><td>Customer Line Total</td><td>$<span data-line-final-total-math><?= number_format((float) $line['final_customer_line_total'], 2) ?></span></td></tr>
            </table>
            <?php if (!empty($line['screen_locations'])): ?>
              <div style="margin-top:8px;color:#aaa;">
                <?php foreach ($line['screen_locations'] as $location): ?>
                  <div><?= h($location['label']) ?> · <?= (int) $location['colors'] ?> colors</div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </details>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <h3 style="margin-top:0;">Manager Adjustments</h3>
      <form method="post">
        <?= csrf_input() ?>
        <div class="grid3">
          <div>
            <label>Margin % (Suggested: <?= h(number_format((float) $quote['suggested_margin_percent'], 2)) ?>%)</label>
            <input type="number" step="0.1" name="manager_margin_percent" value="<?= h($quote['review']['manager_margin_percent']) ?>" data-review-margin>
          </div>
          <div>
            <label>Labor Fee</label>
            <input type="number" step="0.1" name="labor_fee" value="<?= h($quote['review']['labor_fee']) ?>" data-review-labor>
          </div>
          <div>
            <label>Delivery Fee</label>
            <input type="number" step="0.1" name="delivery_fee" value="<?= h($quote['review']['delivery_fee']) ?>" data-review-delivery>
          </div>
        </div>
        <div class="grid3">
          <div>
            <label>Design Fee</label>
            <input type="number" step="0.1" name="design_fee" value="<?= h($quote['review']['design_fee']) ?>" data-review-design>
          </div>
          <div>
            <label>Discount</label>
            <input type="number" step="0.1" name="discount_amount" value="<?= h($quote['review']['discount_amount']) ?>" data-review-discount>
          </div>
          <div>
            <label>Tax Rate %</label>
            <input type="number" step="0.1" name="tax_rate" value="<?= h($quote['review']['tax_rate']) ?>" data-review-tax-rate>
          </div>
        </div>
        <div class="grid3">
          <div>
            <label>Status</label>
            <select name="quote_status">
              <?php foreach (['Pending Review','Reviewed','Ready to Send'] as $status): ?>
                <option value="<?= h($status) ?>" <?= $quote['quote_status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid3">
          <div>
            <label>Artwork Requested</label>
            <input type="text" value="<?= !empty($quote['quote_options']['artwork_needed']) ? 'Yes' : 'No' ?>" readonly>
          </div>
          <div>
            <label>Artwork Hours</label>
            <input type="text" value="<?= h((string) ($quote['quote_options']['artwork_hours'] ?? 0)) ?>" readonly>
          </div>
          <div>
            <label>Barebones Logo</label>
            <input type="text" value="<?= !empty($quote['quote_options']['barebones_logo_discount']) ? 'Yes' : 'No' ?>" readonly>
          </div>
        </div>
        <label>Review Notes</label>
        <textarea name="review_notes"><?= h($quote['review']['review_notes']) ?></textarea>
        <div class="totals" style="margin-top:14px;">
          <div><span>Base Subtotal</span><strong data-review-base-subtotal>$<?= number_format((float) $quote['review_totals']['base_subtotal'], 2) ?></strong></div>
          <div><span>Margin</span><strong data-review-margin-amount>$<?= number_format((float) $quote['review_totals']['margin_amount'], 2) ?></strong></div>
          <div><span>Labor</span><strong data-review-labor-amount>$<?= number_format((float) $quote['review_totals']['labor_fee'], 2) ?></strong></div>
          <div><span>Delivery</span><strong data-review-delivery-amount>$<?= number_format((float) $quote['review_totals']['delivery_fee'], 2) ?></strong></div>
          <div><span>Design</span><strong data-review-design-amount>$<?= number_format((float) $quote['review_totals']['design_fee'], 2) ?></strong></div>
          <div><span>Discount</span><strong data-review-discount-amount>-$<?= number_format((float) $quote['review_totals']['discount_amount'], 2) ?></strong></div>
          <div><span>Tax</span><strong data-review-tax-amount>$<?= number_format((float) $quote['review_totals']['tax_amount'], 2) ?></strong></div>
          <div><span>Final Total</span><strong data-review-final-total>$<?= number_format((float) $quote['review_totals']['final_total'], 2) ?></strong></div>
        </div>
        <div style="margin-top:14px;">
          <button class="btn" type="submit">Save Review Adjustments</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    var form = document.querySelector('.card form');
    if (!form) return;

    function numberValue(selector) {
      var input = form.querySelector(selector);
      if (!input) return 0;
      var parsed = parseFloat(input.value || '0');
      return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
      return '$' + value.toFixed(2);
    }

    function round(value) {
      return Math.round(value * 100) / 100;
    }

    function updateTotals() {
      var baseSubtotal = <?= json_encode((float) $quote['review_totals']['base_subtotal']) ?>;
      var marginBaseSubtotal = <?= json_encode((float) $quote['review_totals']['margin_base_subtotal']) ?>;
      var marginPercent = numberValue('[data-review-margin]');
      var laborFee = numberValue('[data-review-labor]');
      var deliveryFee = numberValue('[data-review-delivery]');
      var designFee = numberValue('[data-review-design]');
      var discountAmount = numberValue('[data-review-discount]');
      var taxRate = numberValue('[data-review-tax-rate]');

      var marginAmount = round(marginBaseSubtotal * (marginPercent / 100));
      var preTaxTotal = round(Math.max(0, baseSubtotal + marginAmount + laborFee + deliveryFee + designFee - discountAmount));
      var taxAmount = round(preTaxTotal * (taxRate / 100));
      var finalTotal = round(preTaxTotal + taxAmount);
      var totalQty = <?= json_encode((int) array_sum(array_map(static function ($line) { return (int) ($line['total_qty'] ?? 0); }, $quote['lines']))) ?>;
      var sharedAdjustmentPerPiece = totalQty > 0 ? (laborFee + deliveryFee + designFee - discountAmount) / totalQty : 0;
      var taxPerPiece = totalQty > 0 ? taxAmount / totalQty : 0;
      var marginRate = marginPercent / 100;

      var setText = function (selector, value) {
        var node = form.querySelector(selector);
        if (node) node.textContent = value;
      };

      setText('[data-review-base-subtotal]', money(baseSubtotal));
      setText('[data-review-margin-amount]', money(marginAmount));
      setText('[data-review-labor-amount]', money(laborFee));
      setText('[data-review-delivery-amount]', money(deliveryFee));
      setText('[data-review-design-amount]', money(designFee));
      setText('[data-review-discount-amount]', '-' + money(discountAmount));
      setText('[data-review-tax-amount]', money(taxAmount));
      setText('[data-review-final-total]', money(finalTotal));

      document.querySelectorAll('.line').forEach(function (line) {
        var qty = parseFloat(line.getAttribute('data-line-qty') || '0') || 0;
        var regularQty = parseFloat(line.getAttribute('data-line-regular-qty') || '0') || 0;
        var extendedQty = parseFloat(line.getAttribute('data-line-extended-qty') || '0') || 0;
        var baseUnit = parseFloat(line.getAttribute('data-line-base-unit') || '0') || 0;
        var extendedSurcharge = parseFloat(line.getAttribute('data-line-extended-surcharge') || '0') || 0;
        var printTotal = parseFloat(line.getAttribute('data-line-print-total') || '0') || 0;
        var baseTotal = parseFloat(line.getAttribute('data-line-base-total') || '0') || 0;
        var printUnit = qty > 0 ? printTotal / qty : 0;
        var rawRegularUnit = baseUnit + printUnit;
        var regularCustomerUnit = rawRegularUnit + (rawRegularUnit * marginRate) + sharedAdjustmentPerPiece + taxPerPiece;
        var extendedCustomerUnit = regularCustomerUnit + extendedSurcharge;
        var lineMarginBase = Math.max(0, baseTotal - (extendedQty * extendedSurcharge));
        var lineMarginAmount = lineMarginBase * marginRate;
        var lineSharedAmount = qty * sharedAdjustmentPerPiece;
        var lineTaxAmount = qty * taxPerPiece;
        var lineFinalTotal = (regularQty * regularCustomerUnit) + (extendedQty * extendedCustomerUnit);
        var lineFinalUnit = qty > 0 ? lineFinalTotal / qty : 0;

        var setLineText = function (selector, value) {
          var node = line.querySelector(selector);
          if (node) node.textContent = value;
        };

        setLineText('[data-line-final-unit]', lineFinalUnit.toFixed(2));
        setLineText('[data-line-regular-unit]', regularCustomerUnit.toFixed(2));
        setLineText('[data-line-extended-unit]', extendedCustomerUnit.toFixed(2));
        setLineText('[data-line-final-total]', lineFinalTotal.toFixed(2));
        setLineText('[data-line-margin-amount]', lineMarginAmount.toFixed(2));
        setLineText('[data-line-shared-amount]', lineSharedAmount.toFixed(2));
        setLineText('[data-line-tax-amount]', lineTaxAmount.toFixed(2));
        setLineText('[data-line-regular-unit-math]', regularCustomerUnit.toFixed(2));
        setLineText('[data-line-extended-unit-math]', extendedCustomerUnit.toFixed(2));
        setLineText('[data-line-final-total-math]', lineFinalTotal.toFixed(2));
      });
    }

    form.addEventListener('input', function (event) {
      if (event.target.matches('[data-review-margin],[data-review-labor],[data-review-delivery],[data-review-design],[data-review-discount],[data-review-tax-rate]')) {
        updateTotals();
      }
    });

    updateTotals();
  })();
</script>

<?php include $root . '/partials/footer.php'; ?>
