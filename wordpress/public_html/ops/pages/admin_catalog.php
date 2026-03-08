<?php
require __DIR__ . '/../auth.php';
require_login('admin');

require __DIR__ . '/../bootstrap.php';

$root = realpath(__DIR__ . '/..'); if (!$root) die('Path error');
$cfg  = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');

require $root . '/db.php'; 
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function opts($rows, $value='id', $label='name'){
  return implode('', array_map(fn($r)=>'<option value="'.(int)$r[$value].'">'.h($r[$label]).'</option>', $rows));
}

$notice = '';
$do = $_GET['do'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_or_die();
}

try {
  if ($do === 'add_product' && !empty($_POST['name'])) {
    $st=$pdo->prepare("INSERT INTO products(name,sku,active) VALUES(?,?,1)");
    $st->execute([trim($_POST['name']), trim($_POST['sku'] ?? '') ?: null]);
    $notice = "Product added.";
  }

  if ($do === 'add_color' && !empty($_POST['name'])) {
    $st=$pdo->prepare("INSERT INTO colors(name,hex) VALUES(?,?)");
    $st->execute([trim($_POST['name']), trim($_POST['hex'] ?? '') ?: null]);
    $notice = "Color added.";
  }

  if ($do === 'add_size' && !empty($_POST['code']) && !empty($_POST['label'])) {
    $st=$pdo->prepare("INSERT INTO sizes(code,label,sort_order) VALUES(?,?,?)");
    $st->execute([trim($_POST['code']), trim($_POST['label']), (int)($_POST['sort_order'] ?? 0)]);
    $notice = "Size added.";
  }

  // Assign products to client (multi)
  if ($do === 'link_products' && !empty($_POST['client_id']) && !empty($_POST['product_ids'])) {
    $cid = (int)$_POST['client_id'];
    $ids = array_map('intval', (array)$_POST['product_ids']);
    $ins = $pdo->prepare("REPLACE INTO client_products(client_id,product_id,active) VALUES(?,?,1)");
    foreach ($ids as $pid) $ins->execute([$cid,$pid]);
    $notice = "Linked ".count($ids)." product(s) to client.";
  }

  // Unlink selected client-products
  if ($do === 'unlink_products' && !empty($_POST['client_id']) && !empty($_POST['unlink_ids'])) {
    $cid = (int)$_POST['client_id'];
    $ids = array_map('intval', (array)$_POST['unlink_ids']);
    $del = $pdo->prepare("DELETE FROM client_products WHERE client_id=? AND product_id=?");
    foreach ($ids as $pid) $del->execute([$cid,$pid]);
    $notice = "Unlinked ".count($ids)." product(s) from client.";
  }

  // Allowed Colors for (client + multiple products)
  if ($do === 'link_colors' && !empty($_POST['client_id']) && !empty($_POST['product_ids'])) {
    $cid = (int)$_POST['client_id'];
    $pids = array_map('intval', (array)$_POST['product_ids']);
    $cids = array_map('intval', (array)($_POST['color_ids'] ?? []));
    $pdo->beginTransaction();
    foreach ($pids as $pid) {
      $pdo->prepare("DELETE FROM client_product_colors WHERE client_id=? AND product_id=?")->execute([$cid,$pid]);
      if ($cids) {
        $ins = $pdo->prepare("INSERT INTO client_product_colors(client_id,product_id,color_id) VALUES(?,?,?)");
        foreach ($cids as $col) $ins->execute([$cid,$pid,$col]);
      }
    }
    $pdo->commit();
    $notice = "Saved colors for ".count($pids)." product(s).";
  }

  // Allowed Sizes for (client + multiple products)
  if ($do === 'link_sizes' && !empty($_POST['client_id']) && !empty($_POST['product_ids'])) {
    $cid = (int)$_POST['client_id'];
    $pids = array_map('intval', (array)$_POST['product_ids']);
    $sids = array_map('intval', (array)($_POST['size_ids'] ?? []));
    $pdo->beginTransaction();
    foreach ($pids as $pid) {
      $pdo->prepare("DELETE FROM client_product_sizes WHERE client_id=? AND product_id=?")->execute([$cid,$pid]);
      if ($sids) {
        $ins = $pdo->prepare("INSERT INTO client_product_sizes(client_id,product_id,size_id) VALUES(?,?,?)");
        foreach ($sids as $sid) $ins->execute([$cid,$pid,$sid]);
      }
    }
    $pdo->commit();
    $notice = "Saved sizes for ".count($pids)." product(s).";
  }

  // Add design (per client)
  if ($do === 'add_design' && !empty($_POST['client_id']) && !empty($_POST['name'])) {
    $st=$pdo->prepare("INSERT INTO designs(client_id,name) VALUES(?,?)");
    $st->execute([(int)$_POST['client_id'], trim($_POST['name'])]);
    $notice = "Design added for client.";
  }

  // Link designs (client + product)
  if ($do === 'link_designs' && !empty($_POST['client_id']) && !empty($_POST['product_id']) && !empty($_POST['design_ids'])) {
    $cid=(int)$_POST['client_id']; $pid=(int)$_POST['product_id'];
    $pdo->prepare("DELETE FROM client_product_designs WHERE client_id=? AND product_id=?")->execute([$cid,$pid]);

    $ids = array_map('intval', (array)$_POST['design_ids']);
    if ($ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $check = $pdo->prepare("SELECT id FROM designs WHERE client_id=? AND id IN ($in)");
      $check->execute(array_merge([$cid], $ids));
      $valid = $check->fetchAll(PDO::FETCH_COLUMN);

      $ins = $pdo->prepare("INSERT INTO client_product_designs(client_id,product_id,design_id) VALUES(?,?,?)");
      foreach ($valid as $did) $ins->execute([$cid,$pid,(int)$did]);
    }
    $notice = "Design links saved.";
  }

  /* ---- Duplicate Catalog handlers (unchanged from previous drop-in) ---- */

  if ($do === 'clone_all' && !empty($_POST['src_client_id']) && !empty($_POST['tgt_client_id'])) {
    $src=(int)$_POST['src_client_id']; $tgt=(int)$_POST['tgt_client_id'];
    if ($src === $tgt) throw new Exception("Source and target cannot be the same.");
    $carryColors = !empty($_POST['with_colors']);
    $carrySizes  = !empty($_POST['with_sizes']);

    $pps = $pdo->prepare("
      SELECT cp.product_id
      FROM client_products cp
      JOIN products p ON p.id=cp.product_id AND p.active=1
      WHERE cp.client_id=? AND cp.active=1
    ");
    $pps->execute([$src]); $pids = $pps->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    $insCP = $pdo->prepare("REPLACE INTO client_products(client_id,product_id,active) VALUES(?,?,1)");
    foreach ($pids as $pid) {
      $pid = (int)$pid;
      $insCP->execute([$tgt,$pid]);

      if ($carryColors) {
        $pdo->prepare("DELETE FROM client_product_colors WHERE client_id=? AND product_id=?")->execute([$tgt,$pid]);
        $colors = $pdo->prepare("SELECT color_id FROM client_product_colors WHERE client_id=? AND product_id=?");
        $colors->execute([$src,$pid]); $cids=$colors->fetchAll(PDO::FETCH_COLUMN);
        if ($cids) { $ins=$pdo->prepare("INSERT INTO client_product_colors(client_id,product_id,color_id) VALUES(?,?,?)");
          foreach ($cids as $cid) $ins->execute([$tgt,$pid,(int)$cid]); }
      }

      if ($carrySizes) {
        $pdo->prepare("DELETE FROM client_product_sizes WHERE client_id=? AND product_id=?")->execute([$tgt,$pid]);
        $sizes = $pdo->prepare("SELECT size_id FROM client_product_sizes WHERE client_id=? AND product_id=?");
        $sizes->execute([$src,$pid]); $sids=$sizes->fetchAll(PDO::FETCH_COLUMN);
        if ($sids) { $ins=$pdo->prepare("INSERT INTO client_product_sizes(client_id,product_id,size_id) VALUES(?,?,?)");
          foreach ($sids as $sid) $ins->execute([$tgt,$pid,(int)$sid]); }
      }
    }
    $pdo->commit();
    $notice = "Cloned ".count($pids)." product(s) from source to target".($carryColors||$carrySizes?" (with ".($carryColors?'colors':'').($carryColors&&$carrySizes?', ':'').($carrySizes?'sizes':'').")":'').".";
  }

  if ($do === 'duplicate_product' && !empty($_POST['src_client_id']) && !empty($_POST['tgt_client_id']) && !empty($_POST['product_id'])) {
    $src=(int)$_POST['src_client_id']; $tgt=(int)$_POST['tgt_client_id']; $pid=(int)$_POST['product_id'];
    if ($src === $tgt) throw new Exception("Source and target cannot be the same.");
    $selColors = array_map('intval', (array)($_POST['color_ids'] ?? []));
    $selSizes  = array_map('intval', (array)($_POST['size_ids'] ?? []));

    $pdo->beginTransaction();
    $pdo->prepare("REPLACE INTO client_products(client_id,product_id,active) VALUES(?,?,1)")->execute([$tgt,$pid]);

    $pdo->prepare("DELETE FROM client_product_colors WHERE client_id=? AND product_id=?")->execute([$tgt,$pid]);
    if ($selColors) { $ins=$pdo->prepare("INSERT INTO client_product_colors(client_id,product_id,color_id) VALUES(?,?,?)");
      foreach ($selColors as $cid) $ins->execute([$tgt,$pid,$cid]); }

    $pdo->prepare("DELETE FROM client_product_sizes WHERE client_id=? AND product_id=?")->execute([$tgt,$pid]);
    if ($selSizes) { $ins=$pdo->prepare("INSERT INTO client_product_sizes(client_id,product_id,size_id) VALUES(?,?,?)");
      foreach ($selSizes as $sid) $ins->execute([$tgt,$pid,$sid]); }

    $pdo->commit();
    $notice = "Cloned product to target with ".count($selColors)." color(s) and ".count($selSizes)." size(s).";
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $notice = "Error: " . h($e->getMessage());
}

/* ---------- DATA ---------- */
$clients  = $pdo->query("SELECT id,name FROM clients WHERE active=1 ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id,name,COALESCE(sku,'') AS sku FROM products WHERE active=1 ORDER BY name")->fetchAll();
$colors   = $pdo->query("SELECT id,name FROM colors ORDER BY name")->fetchAll();
$sizes    = $pdo->query("SELECT id,code,label,sort_order FROM sizes ORDER BY sort_order,label")->fetchAll();
$designsAll = $pdo->query("
  SELECT d.id, d.name, c.name AS client_name, d.client_id
  FROM designs d
  JOIN clients c ON c.id = d.client_id
  ORDER BY c.name, d.name
")->fetchAll(PDO::FETCH_ASSOC);

$clientIdForList = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$currentProducts = [];
if ($clientIdForList) {
  $st = $pdo->prepare("
    SELECT p.id, p.name, COALESCE(p.sku,'') AS sku
    FROM client_products cp
    JOIN products p ON p.id=cp.product_id
    WHERE cp.client_id=? AND cp.active=1
    ORDER BY p.name
  ");
  $st->execute([$clientIdForList]);
  $currentProducts = $st->fetchAll(PDO::FETCH_ASSOC);
}

include $root . '/partials/header.php';
?>
<style>
.grid { display:grid; grid-template-columns: repeat(2, minmax(340px, 1fr)); gap:16px; }
@media (max-width: 1100px){ .grid { grid-template-columns: 1fr; } }
.card { background:#161616; border:1px solid #262626; border-radius:12px; padding:14px; }
.card h3 { margin:0 0 10px 0; font-size:16px; }
label { display:block; font-size:12px; color:#aaa; margin:8px 0 4px; }
input[type="text"], input[type="number"], input[type="date"], input[type="email"], select, textarea {
  width:100%; padding:8px 10px; border:1px solid #2a2a2a; border-radius:8px; background:#111; color:#eee;
}
select[multiple]{ min-height: 160px; }
button { margin-top:10px; padding:8px 12px; border-radius:8px; border:1px solid #2a2a2a; background:#1b1b1b; color:#eee; cursor:pointer; }
button:hover { background:#232323; }
.row { display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
.small { font-size:12px; opacity:.85; }
.notice { background:#0f2a12; color:#c7f8cf; border:1px solid #214a26; padding:8px 10px; border-radius:8px; margin-bottom:12px; }
.notice.error { background:#311010; color:#ffd6d6; border-color:#5a2323; }
.kit { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
.kit .chip { background:#0f0f0f; border:1px solid #2a2a2a; padding:4px 8px; border-radius:999px; font-size:12px; }
hr.sep { border:none; border-top:1px solid #242424; margin:12px 0; }
.col2 { display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
.box { border:1px solid #2a2a2a; border-radius:8px; padding:8px; background:#111; max-height:240px; overflow:auto; }
  .card-sm { max-width: 760px; }
  .grid3 { 
    display: grid; 
    grid-template-columns: 1fr 2fr 140px; 
    gap: 12px; 
    align-items: end; 
    max-width: 760px;
  }
    .content-narrow { max-width: 760px; }
  .row-3 {
    display: grid;
    grid-template-columns: 1fr 2fr 140px;
    gap: 12px;
    align-items: end;
  }
</style>

<h2>Admin: Catalog</h2>

<?php if ($notice): ?>
  <div class="notice<?= str_starts_with($notice,'Error')?' error':'' ?>"><?= $notice ?></div>
<?php endif; ?>

<div class="grid">
<div class="card">
  <h3>Add Product</h3>
  <form method="post" action="?do=add_product">
    <?= csrf_input() ?>
    <div class="content-narrow">
      <label>Name</label><input name="name" required>
      <label>SKU</label>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input name="sku" placeholder="Optional" style="flex:1; min-width:220px;">
        <button type="submit">Add Product</button>
      </div>
    </div>
  </form>
</div>


  <div class="card">
    <h3>Add Color</h3>
    <form method="post" action="?do=add_color">
      <?= csrf_input() ?>
      <label>Name</label><input name="name" required>
      <label>Hex (#RRGGBB)</label><input name="hex" placeholder="#000000">
      <button type="submit">Add Color</button>
    </form>
  </div>

<div class="card">
  <h3>Add Size</h3>
  <form method="post" action="?do=add_size">
    <?= csrf_input() ?>
    <div class="content-narrow">
      <div class="row-3">
        <div>
          <label>Code</label>
          <input name="code" required placeholder="XL">
        </div>
        <div>
          <label>Label</label>
          <input name="label" required placeholder="Adult XL">
        </div>
        <div>
          <label>Sort Order</label>
          <input name="sort_order" type="number" value="0">
        </div>
      </div>
      <button type="submit" style="margin-top:10px;">Add Size</button>
    </div>
  </form>
</div>

  <!-- Assign Products to Client (multi) -->
  <div class="card">
    <h3>Assign Products to Client</h3>
    <form method="post" action="?do=link_products">
      <?= csrf_input() ?>
      <label>Client</label>
      <select name="client_id" required>
        <option value="">Select client…</option>
        <?= opts($clients) ?>
      </select>

      <label>Products (multi-select)</label>
      <select name="product_ids[]" multiple required>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>">
            <?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Link Selected Products</button>
    </form>

    <hr class="sep">

    <form method="get" action="">
      <label>View Current Assignments</label>
      <div class="row">
        <input type="hidden" name="page" value="admin_catalog">
        <select name="client_id" onchange="this.form.submit()">
          <option value="">Pick client to view…</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $clientIdForList===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <?php if ($clientIdForList): ?>
      <div class="small" style="margin-top:8px;">Current products for this client:</div>
      <?php if (!$currentProducts): ?>
        <div class="kit small">— none linked —</div>
      <?php else: ?>
        <form method="post" action="?do=unlink_products">
          <?= csrf_input() ?>
          <input type="hidden" name="client_id" value="<?= (int)$clientIdForList ?>">
          <div class="kit">
            <?php foreach ($currentProducts as $p): ?>
              <label class="chip">
                <input type="checkbox" name="unlink_ids[]" value="<?= (int)$p['id'] ?>"> 
                <?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')' : '' ?>
              </label>
            <?php endforeach; ?>
          </div>
          <button type="submit">Unlink Selected</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Allowed Colors (Client + MULTIPLE Products) -->
  <div class="card">
    <h3>Allowed Colors (Client + Products)</h3>
    <form method="post" action="?do=link_colors" id="formColors">
      <?= csrf_input() ?>
      <label>Client</label>
      <select name="client_id" id="clientForColors" required>
        <option value="">Select client…</option>
        <?= opts($clients) ?>
      </select>

      <label>Products (multi-select; only shows products linked to client)</label>
      <select name="product_ids[]" id="prodForColors" multiple size="8" required disabled>
        <option value="">Select client first…</option>
      </select>

      <label>Colors (multi-select)</label>
      <select name="color_ids[]" multiple size="8" required><?= opts($colors) ?></select>
      <button type="submit">Save Colors</button>
    </form>
  </div>

  <!-- Allowed Sizes (Client + MULTIPLE Products) -->
  <div class="card">
    <h3>Allowed Sizes (Client + Products)</h3>
    <form method="post" action="?do=link_sizes" id="formSizes">
      <?= csrf_input() ?>
      <label>Client</label>
      <select name="client_id" id="clientForSizes" required>
        <option value="">Select client…</option>
        <?= opts($clients) ?>
      </select>

      <label>Products (multi-select; only shows products linked to client)</label>
      <select name="product_ids[]" id="prodForSizes" multiple size="8" required disabled>
        <option value="">Select client first…</option>
      </select>

      <label>Sizes (multi-select)</label>
      <select name="size_ids[]" multiple size="8" required>
        <?php foreach ($sizes as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= h($s['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Save Sizes</button>
    </form>
  </div>

  <div class="card">
    <h3>Add Design (per Client)</h3>
    <form method="post" action="?do=add_design">
      <?= csrf_input() ?>
      <label>Client</label>
      <select name="client_id" required>
        <option value="">Select client…</option>
        <?= opts($clients) ?>
      </select>
      <label>Design Name</label>
      <input name="name" required placeholder="Front Logo, Alt Jersey, etc.">
      <button type="submit">Add Design</button>
    </form>
  </div>

  <div class="card">
    <h3>Link Designs (Client + Product)</h3>
    <form method="post" action="?do=link_designs">
      <?= csrf_input() ?>
      <label>Client</label>
      <select name="client_id" id="clientForDesign" required>
        <option value="">Select client…</option>
        <?= opts($clients) ?>
      </select>

      <label>Product</label>
      <select name="product_id" required>
        <option value="">Select product…</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')' : '' ?></option>
        <?php endforeach; ?>
      </select>

      <label>Designs (multi-select; filtered by client below)</label>
      <select name="design_ids[]" id="designsSelect" multiple size="8" required>
        <?php foreach ($designsAll as $d): ?>
          <option value="<?= (int)$d['id'] ?>" data-client="<?= (int)$d['client_id'] ?>">
            <?= h($d['client_name'].' — '.$d['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="small">Tip: Choose client first to filter the designs list.</div>
      <button type="submit">Save Design Links</button>
    </form>
  </div>

  <!-- Duplicate Catalog (same as before) -->
  <div class="card">
    <h3>Duplicate Catalog (Clone from Client)</h3>

    <form method="post" action="?do=clone_all">
      <?= csrf_input() ?>
      <div class="col2">
        <div>
          <label>Source Client</label>
          <select name="src_client_id" id="srcClientAll" required>
            <option value="">Select source…</option>
            <?= opts($clients) ?>
          </select>
        </div>
        <div>
          <label>Target Client</label>
          <select name="tgt_client_id" required>
            <option value="">Select target…</option>
            <?= opts($clients) ?>
          </select>
        </div>
      </div>
      <div class="row">
        <label style="display:flex;gap:6px;align-items:center;">
          <input type="checkbox" name="with_colors" value="1"> Include Colors
        </label>
        <label style="display:flex;gap:6px;align-items:center;">
          <input type="checkbox" name="with_sizes" value="1"> Include Sizes
        </label>
      </div>
      <button type="submit">Clone ALL Products</button>
    </form>

    <hr class="sep">

    <form method="post" action="?do=duplicate_product" id="dupForm">
      <?= csrf_input() ?>
      <div class="col2">
        <div>
          <label>Source Client</label>
          <select name="src_client_id" id="srcClient" required>
            <option value="">Select source…</option>
            <?= opts($clients) ?>
          </select>
        </div>
        <div>
          <label>Target Client</label>
          <select name="tgt_client_id" id="tgtClient" required>
            <option value="">Select target…</option>
            <?= opts($clients) ?>
          </select>
        </div>
      </div>

      <label>Product (from Source)</label>
      <select name="product_id" id="srcProduct" required disabled>
        <option value="">Select product…</option>
      </select>

      <div class="col2">
        <div>
          <label>Colors to carry over</label>
          <div class="box" id="colorsBox">Select a source & product</div>
        </div>
        <div>
          <label>Sizes to carry over</label>
          <div class="box" id="sizesBox">Select a source & product</div>
        </div>
      </div>

      <button type="submit">Clone This Product to Target</button>
      <div class="small" style="margin-top:6px;">Tip: uncheck any colors/sizes you don’t want copied.</div>
    </form>
  </div>
</div>

<script>
// Filter designs list to the chosen client
const clientForDesign = document.getElementById('clientForDesign');
const designsSelect = document.getElementById('designsSelect');
if (clientForDesign && designsSelect) {
  const allOpts = Array.from(designsSelect.options);
  function refilter() {
    const cid = parseInt(clientForDesign.value || '0', 10);
    designsSelect.innerHTML = '';
    const subset = allOpts.filter(o => parseInt(o.dataset.client||'0',10) === cid);
    subset.forEach(o => designsSelect.appendChild(o));
  }
  clientForDesign.addEventListener('change', refilter);
}

// Populate "Allowed Colors/Sizes" product lists based on chosen client
const base = "<?= $base ?>";
async function loadLinkedProducts(clientId, selectEl){
  selectEl.innerHTML = '<option value="">Loading…</option>';
  selectEl.disabled = true;
  if (!clientId) { selectEl.innerHTML = '<option value="">Select client first…</option>'; return; }
  try {
    const rows = await fetch(`${base}/api/options_products.php?client_id=${clientId}`).then(r=>r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      selectEl.innerHTML = '<option value="">No products linked to this client yet</option>';
      return;
    }
    selectEl.innerHTML = rows.map(p => `<option value="${p.id}">${p.name}${p.sku?` (${p.sku})`:''}</option>`).join('');
    selectEl.disabled = false;
  } catch(e){
    selectEl.innerHTML = '<option value="">Error loading products</option>';
  }
}

const clientForColors = document.getElementById('clientForColors');
const prodForColors   = document.getElementById('prodForColors');
if (clientForColors && prodForColors) {
  clientForColors.addEventListener('change', () => loadLinkedProducts(clientForColors.value, prodForColors));
}

const clientForSizes = document.getElementById('clientForSizes');
const prodForSizes   = document.getElementById('prodForSizes');
if (clientForSizes && prodForSizes) {
  clientForSizes.addEventListener('change', () => loadLinkedProducts(clientForSizes.value, prodForSizes));
}

// Duplicate Catalog helpers
const srcClient   = document.getElementById('srcClient');
const srcProduct  = document.getElementById('srcProduct');
const colorsBox   = document.getElementById('colorsBox');
const sizesBox    = document.getElementById('sizesBox');

function buildCheckbox(name, id, label){
  const safe = String(label).replace(/</g,'&lt;').replace(/>/g,'&gt;');
  return `<label style="display:block;margin:4px 0;">
    <input type="checkbox" name="${name}[]" value="${id}" checked> ${safe}
  </label>`;
}

async function loadSourceProducts(){
  const cid = srcClient.value;
  srcProduct.innerHTML = '<option value="">Select product…</option>';
  srcProduct.disabled = true;
  colorsBox.innerHTML = 'Select a source & product';
  sizesBox.innerHTML = 'Select a source & product';
  if (!cid) return;
  const rows = await fetch(`${base}/api/options_products.php?client_id=${cid}`).then(r=>r.json()).catch(()=>[]);
  rows.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.id; opt.textContent = p.name + (p.sku ? ` (${p.sku})` : '');
    srcProduct.appendChild(opt);
  });
  srcProduct.disabled = rows.length === 0;
}

async function loadAttributes(){
  const cid = srcClient.value;
  const pid = srcProduct.value;
  colorsBox.innerHTML = '—';
  sizesBox.innerHTML = '—';
  if (!cid || !pid) return;

  const [colors, sizes] = await Promise.all([
    fetch(`${base}/api/options_colors.php?client_id=${cid}&product_id=${pid}`).then(r=>r.json()).catch(()=>[]),
    fetch(`${base}/api/options_sizes.php?client_id=${cid}&product_id=${pid}`).then(r=>r.json()).catch(()=>[]),
  ]);

  colorsBox.innerHTML = colors.length ? colors.map(c=>buildCheckbox('color_ids', c.id, c.name)).join('') : '<div class="small">No colors set on source.</div>';
  sizesBox.innerHTML  = sizes.length  ? sizes.map(s=>buildCheckbox('size_ids',  s.id, s.label)).join('') : '<div class="small">No sizes set on source.</div>';
}

if (srcClient)  srcClient.addEventListener('change', loadSourceProducts);
if (srcProduct) srcProduct.addEventListener('change', loadAttributes);
</script>

<?php include $root . '/partials/footer.php'; ?>
