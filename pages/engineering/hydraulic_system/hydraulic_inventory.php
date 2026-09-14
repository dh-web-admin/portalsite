<?php
// Inventory — reachable via the "View Inventory" button on hydraulic_systems.php.
// Not a hub-level section (not in _sections.php); builds its own chrome like
// its sibling hydraulic_components_list.php.
// This is a live rollup, not its own data set: every row here is derived
// from hydraulic_component_specs (the "All Components" list) by collapsing
// rows that are the SAME physical part — identical part_no, manufacturer
// description, and description — into one line and summing their `qty`,
// since the same fitting is often reused across several components (e.g.
// "6400-10-10" appears on 3c/3d/4e/4f/6a/6b). That sum is the constant
// "total needed" baked into the Needed column's "x | y" (see below).
// Two things are actually stored here, both keyed by that same rollup hash
// (part_key) so they stay matched to the rollup as components change:
//   - hydraulic_inventory_stock  — one row per part: how many are on hand.
//   - hydraulic_inventory_suppliers — MANY rows per part: each supplier's
//     name + unit price, so several can be compared side by side. Total
//     price is never stored — it's always unit_price * (currently still
//     needed), recalculated live as the Inventory count changes.
require_once __DIR__ . '/../../../session_init.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';

$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

require_once __DIR__ . '/../../../partials/permissions.php';
$hasEditPermission = can_edit_page('engineering');

function hc_inv_fmt($v)
{
    return $v === null ? '' : htmlspecialchars((string) $v);
}

function hc_inv_part_key($partNo, $mfrDesc, $desc)
{
    return sha1(
        mb_strtolower(trim((string) $partNo)) . '|' .
        mb_strtolower(trim((string) $mfrDesc)) . '|' .
        mb_strtolower(trim((string) $desc))
    );
}

function hc_inv_money($v)
{
    return $v === null || $v === '' ? null : number_format((float) $v, 2, '.', '');
}

$parts = [];       // part_key => ['part_no'=>, 'display_desc'=>, 'needed'=>, 'on_hand'=>]
$tableMissing = false;

$check = $conn->query("SHOW TABLES LIKE 'hydraulic_component_specs'");
if ($check && $check->num_rows > 0) {
    $result = $conn->query('SELECT part_no, description, manufacturer_description, qty FROM hydraulic_component_specs');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $partNo   = trim((string) $row['part_no']);
            $mfrDesc  = trim((string) $row['manufacturer_description']);
            $desc     = trim((string) $row['description']);
            $key      = hc_inv_part_key($partNo, $mfrDesc, $desc);
            $qty      = (int) $row['qty'];

            if (!isset($parts[$key])) {
                $parts[$key] = [
                    'part_key'     => $key,
                    'part_no'      => $partNo,
                    // Description and Manufacturer's Description are their
                    // own columns here now, same as the All Components
                    // page — no more falling one back into the other.
                    'description'  => $desc,
                    'display_desc' => $mfrDesc,
                    'needed'       => 0,
                    'on_hand'      => 0,
                ];
            }
            $parts[$key]['needed'] += $qty;
        }
    }
} else {
    $tableMissing = true;
}

if ($parts) {
    $stockCheck = $conn->query("SHOW TABLES LIKE 'hydraulic_inventory_stock'");
    if ($stockCheck && $stockCheck->num_rows > 0) {
        $stockResult = $conn->query('SELECT part_key, on_hand FROM hydraulic_inventory_stock');
        if ($stockResult) {
            while ($row = $stockResult->fetch_assoc()) {
                if (isset($parts[$row['part_key']])) {
                    $parts[$row['part_key']]['on_hand'] = (int) $row['on_hand'];
                }
            }
        }
    }
}

$suppliersByPart = []; // part_key => [ ['id'=>, 'supplier_name'=>, 'unit_price'=>], ... ]
$knownSupplierNames = []; // distinct names across every part, for the "Supplier" field's autosuggest
if ($parts) {
    $supCheck = $conn->query("SHOW TABLES LIKE 'hydraulic_inventory_suppliers'");
    if ($supCheck && $supCheck->num_rows > 0) {
        $supResult = $conn->query('SELECT id, part_key, supplier_name, unit_price FROM hydraulic_inventory_suppliers ORDER BY part_key, id ASC');
        if ($supResult) {
            while ($row = $supResult->fetch_assoc()) {
                $suppliersByPart[$row['part_key']][] = $row;
                $name = trim((string) $row['supplier_name']);
                if ($name !== '') { $knownSupplierNames[$name] = true; }
            }
        }
    }
    $knownSupplierNames = array_keys($knownSupplierNames);
    sort($knownSupplierNames, SORT_NATURAL | SORT_FLAG_CASE);
}

$parts = array_values($parts);
usort($parts, function ($a, $b) {
    $aBlank = ($a['part_no'] === '' || $a['part_no'] === '-');
    $bBlank = ($b['part_no'] === '' || $b['part_no'] === '-');
    if ($aBlank !== $bBlank) { return $aBlank <=> $bBlank; }
    if ($aBlank) {
        $aKey = $a['display_desc'] !== '' ? $a['display_desc'] : $a['description'];
        $bKey = $b['display_desc'] !== '' ? $b['display_desc'] : $b['description'];
        return strnatcasecmp($aKey, $bKey);
    }
    return strnatcasecmp($a['part_no'], $b['part_no']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title>Inventory — Hydraulic Systems</title>
  <link rel="stylesheet" href="../../../assets/css/base.css" />
  <link rel="stylesheet" href="../../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../style.css?v=<?php echo @filemtime(__DIR__ . '/../style.css'); ?>" />
  <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css'); ?>" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <div class="eng-topbar">
            <a href="hydraulic_systems.php" class="eng-back-btn">&larr; Back</a>
            <nav class="eng-breadcrumb">
              <a href="../index.php">Engineering</a>
              <span aria-hidden="true">/</span>
              <a href="hydraulic_systems.php">Hydraulic Systems</a>
              <span aria-hidden="true">/</span>
              <span class="current">Inventory</span>
            </nav>
          </div>

          <?php if ($tableMissing): ?>
            <p class="eng-panel-placeholder">
              The <code>hydraulic_component_specs</code> table doesn't exist yet — add components on the
              All Components page first.
            </p>
          <?php endif; ?>

          <datalist id="hcSupplierNames">
            <?php foreach ($knownSupplierNames as $sn): ?>
              <option value="<?php echo hc_inv_fmt($sn); ?>"></option>
            <?php endforeach; ?>
          </datalist>

          <div class="hc-list-wrap hc-inv-wrap">
            <table class="hc-list-table hc-inv-table">
              <colgroup>
                <col style="width: 170px">
                <col style="width: 180px">
                <col style="width: 260px">
                <col style="width: 72px">
                <col style="width: 82px">
                <col style="width: 180px">
                <col style="width: 104px">
                <col style="width: 44px">
                <col style="width: 104px">
                <col style="width: 60px">
              </colgroup>
              <thead>
                <tr>
                  <th rowspan="2">Part Number</th>
                  <th rowspan="2" class="hc-col-desc">Description</th>
                  <th rowspan="2" class="hc-col-mfrdesc">Manufacturer's Description</th>
                  <th rowspan="2" class="hc-inv-cell">Inventory</th>
                  <th rowspan="2" class="hc-inv-needed">Needed</th>
                  <th colspan="5" class="hc-supplier-group-header">Suppliers</th>
                </tr>
                <tr>
                  <th class="hc-inv-subhead hc-supplier-divider">Supplier</th>
                  <th class="hc-inv-subhead">Unit Price</th>
                  <th class="hc-inv-subhead hc-supplier-qty-cell"></th>
                  <th class="hc-inv-subhead hc-supplier-total-cell">Total Price</th>
                  <th class="hc-inv-subhead"></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($parts)): ?>
                  <tr><td colspan="10" class="hc-list-empty">No components added yet.</td></tr>
                <?php else: foreach ($parts as $p): ?>
                  <?php
                    $total         = (int) $p['needed'];
                    $remaining     = max(0, $total - (int) $p['on_hand']);
                    $short         = $remaining > 0;
                    $suppliers     = $suppliersByPart[$p['part_key']] ?? [];
                    // Cheapest first (nulls last) — it's the one row shown
                    // by default when there's more than one; see the
                    // "N more" chevron collapsing the rest, below.
                    usort($suppliers, function ($a, $b) {
                        $ap = $a['unit_price'];
                        $bp = $b['unit_price'];
                        if ($ap === null && $bp === null) { return 0; }
                        if ($ap === null) { return 1; }
                        if ($bp === null) { return -1; }
                        return ((float) $ap) <=> ((float) $bp);
                    });
                    $supplierCount = count($suppliers);
                    // One row per real supplier — no separate trailing
                    // "add" row anymore. The "+" to add another lives right
                    // on the first (cheapest) row, next to its "×"; a part
                    // with zero suppliers yet gets a single placeholder row
                    // whose "+" reveals fields in place.
                    $rowCount = max(1, $supplierCount);
                    $labelFor = $p['part_no'] !== '' ? $p['part_no'] : ($p['display_desc'] !== '' ? $p['display_desc'] : $p['description']);
                  ?>
                  <?php for ($i = 0; $i < $rowCount; $i++): ?>
                    <?php $supplier = $i < $supplierCount ? $suppliers[$i] : null; ?>
                    <tr class="hc-inv-row" data-part-key="<?php echo hc_inv_fmt($p['part_key']); ?>">
                      <?php if ($i === 0): ?>
                        <td rowspan="<?php echo $rowCount; ?>"><?php echo hc_inv_fmt($p['part_no']); ?></td>
                        <td rowspan="<?php echo $rowCount; ?>" class="hc-col-desc"><span class="hc-clamp-2"><?php echo hc_inv_fmt($p['description']); ?></span></td>
                        <td rowspan="<?php echo $rowCount; ?>" class="hc-col-mfrdesc"><span class="hc-clamp-2"><?php echo hc_inv_fmt($p['display_desc']); ?></span></td>
                        <td rowspan="<?php echo $rowCount; ?>" class="hc-inv-cell">
                          <?php if ($hasEditPermission): ?>
                            <input type="number" class="hc-inv-input" min="0" step="1"
                                   value="<?php echo (int) $p['on_hand']; ?>"
                                   data-part-key="<?php echo hc_inv_fmt($p['part_key']); ?>"
                                   data-total="<?php echo $total; ?>"
                                   aria-label="Inventory on hand for <?php echo hc_inv_fmt($labelFor); ?>">
                          <?php else: ?>
                            <?php echo (int) $p['on_hand']; ?>
                          <?php endif; ?>
                        </td>
                        <td rowspan="<?php echo $rowCount; ?>" class="hc-inv-needed <?php echo $short ? 'hc-inv-short' : ''; ?>"><span class="hc-inv-remaining"><?php echo $remaining; ?></span> | <?php echo $total; ?></td>
                      <?php endif; ?>

                      <?php if ($supplier): ?>
                        <?php $unitPrice = hc_inv_money($supplier['unit_price']); ?>
                        <td class="hc-supplier-name-cell">
                          <?php if ($hasEditPermission): ?>
                            <input type="text" class="hc-supplier-name-input" value="<?php echo hc_inv_fmt($supplier['supplier_name']); ?>"
                                   data-supplier-id="<?php echo (int) $supplier['id']; ?>" placeholder="Supplier name" list="hcSupplierNames">
                          <?php else: ?>
                            <?php echo hc_inv_fmt($supplier['supplier_name']); ?>
                          <?php endif; ?>
                        </td>
                        <td class="hc-supplier-price-cell">
                          <?php if ($hasEditPermission): ?>
                            <input type="number" class="hc-supplier-price-input" min="0" step="0.01"
                                   value="<?php echo $unitPrice === null ? '' : $unitPrice; ?>"
                                   data-supplier-id="<?php echo (int) $supplier['id']; ?>"
                                   data-part-key="<?php echo hc_inv_fmt($p['part_key']); ?>"
                                   placeholder="0.00">
                          <?php else: ?>
                            <?php echo $unitPrice === null ? '—' : '$' . $unitPrice; ?>
                          <?php endif; ?>
                        </td>
                        <td class="hc-supplier-qty-cell" data-supplier-id="<?php echo (int) $supplier['id']; ?>">&times; <?php echo $remaining; ?></td>
                        <td class="hc-supplier-total-cell" data-supplier-id="<?php echo (int) $supplier['id']; ?>">
                          <?php echo $unitPrice === null ? '—' : '$' . number_format($unitPrice * $remaining, 2); ?>
                        </td>
                        <td class="hc-supplier-action-cell">
                          <?php if ($hasEditPermission): ?>
                            <button type="button" class="hc-supplier-remove-btn" data-supplier-id="<?php echo (int) $supplier['id']; ?>" title="Remove supplier" aria-label="Remove supplier">&times;</button>
                            <?php if ($i === 0): ?>
                              <button type="button" class="hc-supplier-add-btn" data-part-key="<?php echo hc_inv_fmt($p['part_key']); ?>" title="Add another supplier" aria-label="Add another supplier">+</button>
                            <?php endif; ?>
                          <?php endif; ?>
                        </td>
                      <?php elseif ($hasEditPermission): ?>
                        <?php /* supplierCount === 0: collapsed by default — no input fields
                                 until "+" is clicked, so a part with no suppliers yet doesn't
                                 show a permanent empty text box. */ ?>
                        <td class="hc-supplier-name-cell"></td>
                        <td class="hc-supplier-price-cell"></td>
                        <td class="hc-supplier-qty-cell hc-supplier-qty-placeholder">&times; <?php echo $remaining; ?></td>
                        <td class="hc-supplier-total-cell hc-supplier-total-placeholder">—</td>
                        <td class="hc-supplier-action-cell">
                          <button type="button" class="hc-supplier-add-btn" data-part-key="<?php echo hc_inv_fmt($p['part_key']); ?>" title="Add supplier" aria-label="Add supplier">+</button>
                        </td>
                      <?php else: ?>
                        <td colspan="5" class="hc-supplier-empty">No suppliers added yet.</td>
                      <?php endif; ?>
                    </tr>
                  <?php endfor; ?>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var pairs = [['usersToggle', 'usersGroup'], ['devToggle', 'devGroup'], ['maintenanceToggle', 'maintenanceGroup']];
      pairs.forEach(function (p) {
        var btn = document.getElementById(p[0]);
        var grp = document.getElementById(p[1]);
        if (btn && grp) {
          btn.addEventListener('click', function () { grp.classList.toggle('open'); });
        }
      });
    })();
  </script>
  <script>
    // The 2-row header's second row (Supplier/Unit Price/Total Price) is
    // sticky right below the first — its exact pixel offset depends on real
    // rendered text/padding, so it's measured rather than guessed in CSS.
    (function () {
      var firstRow = document.querySelector('.hc-inv-table thead tr:first-child');
      var subheads = document.querySelectorAll('.hc-inv-table th.hc-inv-subhead');
      if (!firstRow || !subheads.length) { return; }
      function sync() {
        var h = firstRow.getBoundingClientRect().height;
        subheads.forEach(function (th) { th.style.top = h + 'px'; });
      }
      sync();
      window.addEventListener('resize', sync);
    })();
  </script>
  <script>
    // Bounds .hc-inv-wrap to the real remaining viewport space below it, so
    // it scrolls internally (with its sticky header staying put) instead of
    // scrolling away with the whole page — measured, not guessed, same as
    // the header-row offset above.
    (function () {
      var wrap = document.querySelector('.hc-inv-wrap');
      if (!wrap) { return; }
      function sync() {
        var top = wrap.getBoundingClientRect().top;
        if (top <= 0) { return; }
        var avail = window.innerHeight - top - 16;
        if (avail < 200) { avail = 200; }
        wrap.style.maxHeight = avail + 'px';
      }
      sync();
      requestAnimationFrame(sync);
      window.addEventListener('resize', sync);
    })();
  </script>
  <script>
    // Small "Saved" confirmation toast, bottom-right, reused by every
    // auto-save on this page (Inventory count, supplier name/price edits).
    (function () {
      var toast = document.createElement('div');
      toast.className = 'hc-toast';
      document.body.appendChild(toast);
      var hideTimer = null;
      window.hcShowToast = function (message) {
        toast.textContent = message;
        toast.classList.remove('hc-toast-visible');
        void toast.offsetWidth; // restart the transition if it's already showing
        toast.classList.add('hc-toast-visible');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function () { toast.classList.remove('hc-toast-visible'); }, 1500);
      };
    })();
  </script>
  <script>
    (function () {
      // Keeps the Supplier field's autosuggest list current within this
      // page load — a name just typed for one part is immediately
      // suggested for the next one, without waiting for a reload.
      function addKnownSupplierName(name) {
        name = (name || '').trim();
        if (name === '') { return; }
        var datalist = document.getElementById('hcSupplierNames');
        if (!datalist) { return; }
        var exists = Array.prototype.some.call(datalist.options, function (opt) {
          return opt.value.toLowerCase() === name.toLowerCase();
        });
        if (exists) { return; }
        var opt = document.createElement('option');
        opt.value = name;
        datalist.appendChild(opt);
      }

      function fmtMoney(n) { return '$' + n.toFixed(2); }

      function partRemaining(partKey) {
        var invInput = document.querySelector('.hc-inv-input[data-part-key="' + partKey + '"]');
        if (!invInput) { return 0; }
        var total = parseInt(invInput.getAttribute('data-total'), 10) || 0;
        var onHand = Math.max(0, parseInt(invInput.value, 10) || 0);
        return Math.max(0, total - onHand);
      }

      // Recompute every supplier's Total Price for one part — called after
      // either the Inventory count changes (remaining shifts) or a Unit
      // Price field is edited (that one supplier's total shifts). Also
      // covers the not-yet-saved "+ Add supplier" row's placeholder, so
      // typing (or spinner-clicking) a unit price there shows a live total
      // immediately, not just after it's been saved.
      function updateSupplierTotals(partKey) {
        var remaining = partRemaining(partKey);
        document.querySelectorAll('.hc-supplier-price-input[data-part-key="' + partKey + '"]').forEach(function (priceInput) {
          var unitPrice = parseFloat(priceInput.value);
          var supplierId = priceInput.getAttribute('data-supplier-id');
          var qtyCell = document.querySelector('.hc-supplier-qty-cell[data-supplier-id="' + supplierId + '"]');
          var totalCell = document.querySelector('.hc-supplier-total-cell[data-supplier-id="' + supplierId + '"]');
          if (qtyCell) { qtyCell.textContent = '× ' + remaining; }
          if (totalCell) { totalCell.textContent = isNaN(unitPrice) ? '—' : fmtMoney(unitPrice * remaining); }
        });
        document.querySelectorAll('.hc-supplier-add-price[data-part-key="' + partKey + '"]').forEach(function (priceInput) {
          var unitPrice = parseFloat(priceInput.value);
          var row = priceInput.closest('tr');
          var qtyCell = row.querySelector('.hc-supplier-qty-placeholder');
          var totalCell = row.querySelector('.hc-supplier-total-placeholder');
          if (qtyCell) { qtyCell.textContent = '× ' + remaining; }
          if (totalCell) { totalCell.textContent = isNaN(unitPrice) ? '—' : fmtMoney(unitPrice * remaining); }
        });
      }

      // ---------- Inventory (on-hand) ----------
      // Needed's "x | y" updates live on every keystroke so it's never
      // stale while typing; saving to the server still only happens on
      // blur/Enter, same as before, so it's not hitting the API per key.
      document.querySelectorAll('.hc-inv-input').forEach(function (input) {
        var lastSaved = input.value;
        var partKey = input.getAttribute('data-part-key');
        var row = input.closest('.hc-inv-row');
        var neededCell = row ? row.querySelector('.hc-inv-needed') : null;
        var remainingEl = neededCell ? neededCell.querySelector('.hc-inv-remaining') : null;
        var total = parseInt(input.getAttribute('data-total'), 10) || 0;

        function updateNeededDisplay() {
          var onHand = Math.max(0, parseInt(input.value, 10) || 0);
          var remaining = Math.max(0, total - onHand);
          if (remainingEl) { remainingEl.textContent = remaining; }
          if (neededCell) { neededCell.classList.toggle('hc-inv-short', remaining > 0); }
          updateSupplierTotals(partKey);
        }

        input.addEventListener('input', updateNeededDisplay);

        function save() {
          var value = String(Math.max(0, parseInt(input.value, 10) || 0));
          input.value = value;
          updateNeededDisplay();

          if (value === lastSaved) { return; }

          var body = new URLSearchParams();
          body.append('part_key', partKey);
          body.append('on_hand', value);

          input.classList.add('hc-inv-saving');
          fetch('../../../api/update_hydraulic_inventory_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              input.classList.remove('hc-inv-saving');
              if (data && data.success) {
                lastSaved = value;
                window.hcShowToast('Saved');
              } else {
                input.classList.add('hc-inv-error');
                setTimeout(function () { input.classList.remove('hc-inv-error'); }, 1600);
              }
            })
            .catch(function () {
              input.classList.remove('hc-inv-saving');
              input.classList.add('hc-inv-error');
              setTimeout(function () { input.classList.remove('hc-inv-error'); }, 1600);
            });
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        });
      });

      // ---------- Supplier rows: name / unit price, editable in place.
      // Every wiring step below is a small function rather than a one-time
      // querySelectorAll pass, because adding a supplier converts a draft
      // row into a real one (or inserts/removes a row) without a page
      // reload — those elements need the exact same listeners on demand. ----------
      function wireSupplierNameInput(input) {
        var id = input.getAttribute('data-supplier-id');
        input.addEventListener('blur', function () { saveSupplierRow(id); });
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        });
      }

      function wireSupplierPriceInput(input) {
        var id = input.getAttribute('data-supplier-id');
        input.addEventListener('input', function () {
          updateSupplierTotals(input.getAttribute('data-part-key'));
        });
        input.addEventListener('blur', function () { saveSupplierRow(id); });
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        });
      }

      function wireSupplierRemoveBtn(btn) {
        btn.addEventListener('click', function () {
          if (!window.confirm('Remove this supplier?')) { return; }
          var id = btn.getAttribute('data-supplier-id');
          btn.disabled = true;
          fetch('../../../api/delete_hydraulic_inventory_supplier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (data && data.success) { window.location.reload(); }
              else { btn.disabled = false; }
            })
            .catch(function () { btn.disabled = false; });
        });
      }

      // Small ✓ shown next to a saved supplier row's "×" only while one of
      // its two fields is actively focused — purely a "you're editing
      // this" indicator; the field's own blur listener (wired above) is
      // what actually saves. Clicking elsewhere (including the ✓ itself)
      // blurs the field naturally, which is all that's needed.
      function wireEditIndicator(row, inputs) {
        var actionCell = row.querySelector('.hc-supplier-action-cell');
        var indicator = null;
        function show() {
          if (indicator) { return; }
          indicator = document.createElement('span');
          indicator.className = 'hc-supplier-confirm-btn';
          indicator.textContent = '✓';
          indicator.title = 'Editing';
          actionCell.insertBefore(indicator, actionCell.firstChild);
        }
        function hide() {
          if (indicator) { indicator.remove(); indicator = null; }
        }
        inputs.forEach(function (input) {
          input.addEventListener('focus', show);
          input.addEventListener('blur', function () {
            setTimeout(function () {
              if (inputs.indexOf(document.activeElement) === -1) { hide(); }
            }, 0);
          });
        });
      }

      // Builds blank Supplier/Unit Price fields plus explicit ✓ (save) / ×
      // (cancel) buttons into `row` — used both for the lone placeholder
      // row a 0-supplier part starts with, and for a freshly inserted row
      // when adding another supplier to a part that already has one.
      function setupDraftFields(row, partKey, onCancel) {
        var nameCell = row.querySelector('.hc-supplier-name-cell');
        var priceCell = row.querySelector('.hc-supplier-price-cell');
        var actionCell = row.querySelector('.hc-supplier-action-cell');

        nameCell.innerHTML = '<input type="text" class="hc-supplier-add-name" placeholder="Supplier name" data-part-key="' + partKey + '" list="hcSupplierNames">';
        priceCell.innerHTML = '<input type="number" class="hc-supplier-add-price" min="0" step="0.01" placeholder="0.00" data-part-key="' + partKey + '">';
        actionCell.innerHTML = '';

        var nameInput = nameCell.querySelector('.hc-supplier-add-name');
        var priceInput = priceCell.querySelector('.hc-supplier-add-price');

        var confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'hc-supplier-confirm-btn';
        confirmBtn.title = 'Save';
        confirmBtn.setAttribute('aria-label', 'Save');
        confirmBtn.textContent = '✓';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'hc-supplier-remove-btn';
        cancelBtn.title = 'Cancel';
        cancelBtn.setAttribute('aria-label', 'Cancel');
        cancelBtn.textContent = '×';

        actionCell.appendChild(confirmBtn);
        actionCell.appendChild(cancelBtn);

        function doSubmit() {
          var name = nameInput.value.trim();
          if (name === '') {
            nameInput.classList.add('hc-inv-error');
            nameInput.focus();
            setTimeout(function () { nameInput.classList.remove('hc-inv-error'); }, 1600);
            return;
          }
          submitNewSupplier(row, partKey, nameInput, priceInput);
        }

        confirmBtn.addEventListener('click', doSubmit);
        cancelBtn.addEventListener('click', onCancel);

        priceInput.addEventListener('input', function () { updateSupplierTotals(partKey); });
        [nameInput, priceInput].forEach(function (input) {
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); doSubmit(); }
            if (e.key === 'Escape') { e.preventDefault(); onCancel(); }
          });
        });

        nameInput.focus();
      }

      // ---------- "+" on the lone placeholder row of a 0-supplier part:
      // reveals fields in that same row; canceling reverts it back. ----------
      function wireAddRow(row) {
        var btn = row.querySelector('.hc-supplier-add-btn');
        if (!btn) { return; }
        btn.addEventListener('click', function () { expandAddRow(row); });
      }

      function collapseAddRow(row) {
        var partKey = row.getAttribute('data-part-key');
        row.querySelector('.hc-supplier-name-cell').innerHTML = '';
        row.querySelector('.hc-supplier-price-cell').innerHTML = '';
        row.querySelector('.hc-supplier-action-cell').innerHTML =
          '<button type="button" class="hc-supplier-add-btn" data-part-key="' + partKey + '" title="Add supplier" aria-label="Add supplier">+</button>';
        wireAddRow(row);
      }

      function expandAddRow(row) {
        var partKey = row.getAttribute('data-part-key');
        setupDraftFields(row, partKey, function () { collapseAddRow(row); });
      }

      // ---------- "+" next to "×" on an existing primary supplier row:
      // inserts a new blank row right after it (bumping the group's
      // rowspan by one); canceling removes that row and un-bumps it. ----------
      function wireAddMoreBtn(btn) {
        btn.addEventListener('click', function () {
          var partKey = btn.getAttribute('data-part-key');
          var primaryRow = btn.closest('tr');
          btn.disabled = true;

          var newRow = document.createElement('tr');
          newRow.className = 'hc-inv-row';
          newRow.setAttribute('data-part-key', partKey);
          newRow.innerHTML =
            '<td class="hc-supplier-name-cell"></td>' +
            '<td class="hc-supplier-price-cell"></td>' +
            '<td class="hc-supplier-qty-cell hc-supplier-qty-placeholder">× ' + partRemaining(partKey) + '</td>' +
            '<td class="hc-supplier-total-cell hc-supplier-total-placeholder">—</td>' +
            '<td class="hc-supplier-action-cell"></td>';
          primaryRow.parentNode.insertBefore(newRow, primaryRow.nextSibling);

          function adjustPrimaryRowspan(delta) {
            primaryRow.querySelectorAll('[rowspan]').forEach(function (cell) {
              cell.setAttribute('rowspan', Math.max(1, (parseInt(cell.getAttribute('rowspan'), 10) || 1) + delta));
            });
          }
          adjustPrimaryRowspan(1);

          setupDraftFields(newRow, partKey, function () {
            newRow.remove();
            adjustPrimaryRowspan(-1);
            btn.disabled = false;
          });
        });
      }

      function saveSupplierRow(id) {
        var nameInput = document.querySelector('.hc-supplier-name-input[data-supplier-id="' + id + '"]');
        var priceInput = document.querySelector('.hc-supplier-price-input[data-supplier-id="' + id + '"]');
        if (!nameInput || !priceInput) { return; }

        var body = new URLSearchParams();
        body.append('id', id);
        body.append('supplier_name', nameInput.value.trim());
        body.append('unit_price', priceInput.value.trim());

        [nameInput, priceInput].forEach(function (el) { el.classList.add('hc-inv-saving'); });
        fetch('../../../api/update_hydraulic_inventory_supplier.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            [nameInput, priceInput].forEach(function (el) { el.classList.remove('hc-inv-saving'); });
            if (data && data.success) {
              addKnownSupplierName(nameInput.value);
              window.hcShowToast('Saved');
            } else {
              [nameInput, priceInput].forEach(function (el) {
                el.classList.add('hc-inv-error');
                setTimeout(function () { el.classList.remove('hc-inv-error'); }, 1600);
              });
            }
          })
          .catch(function () {
            [nameInput, priceInput].forEach(function (el) {
              el.classList.remove('hc-inv-saving');
              el.classList.add('hc-inv-error');
              setTimeout(function () { el.classList.remove('hc-inv-error'); }, 1600);
            });
          });
      }

      document.querySelectorAll('.hc-supplier-name-input').forEach(wireSupplierNameInput);
      document.querySelectorAll('.hc-supplier-price-input').forEach(wireSupplierPriceInput);
      document.querySelectorAll('.hc-supplier-remove-btn').forEach(wireSupplierRemoveBtn);

      // ---------- Submits a draft row's fields: saves them, then converts
      // this same row into a real (editable, removable) supplier row in
      // place — no page reload. ----------
      function submitNewSupplier(row, partKey, nameInput, priceInput) {
        var name = nameInput.value.trim();
        [nameInput, priceInput].forEach(function (el) { el.disabled = true; });

        var body = new URLSearchParams();
        body.append('part_key', partKey);
        body.append('supplier_name', name);
        body.append('unit_price', priceInput.value.trim());

        fetch('../../../api/add_hydraulic_inventory_supplier.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data && data.success) {
              convertAddRowToSaved(row, data.id, partKey);
              addKnownSupplierName(name);
              window.hcShowToast('Supplier added');
            } else {
              [nameInput, priceInput].forEach(function (el) {
                el.disabled = false;
                el.classList.add('hc-inv-error');
                setTimeout(function () { el.classList.remove('hc-inv-error'); }, 1600);
              });
              nameInput.focus();
            }
          })
          .catch(function () {
            [nameInput, priceInput].forEach(function (el) { el.disabled = false; });
          });
      }

      // Converts a draft row's fields (built by setupDraftFields) into a
      // real, saved supplier row. Row count/rowspan is NOT touched here —
      // that's handled once, at the moment a row is inserted or removed
      // (wireAddMoreBtn), not at conversion, since converting never
      // changes how many rows the group has.
      function convertAddRowToSaved(row, id, partKey) {
        var nameInput = row.querySelector('.hc-supplier-add-name');
        var priceInput = row.querySelector('.hc-supplier-add-price');
        var qtyCell = row.querySelector('.hc-supplier-qty-placeholder');
        var totalCell = row.querySelector('.hc-supplier-total-placeholder');
        var actionCell = row.querySelector('.hc-supplier-action-cell');

        nameInput.classList.remove('hc-supplier-add-name');
        nameInput.classList.add('hc-supplier-name-input');
        nameInput.setAttribute('data-supplier-id', id);
        nameInput.disabled = false;
        wireSupplierNameInput(nameInput);

        priceInput.classList.remove('hc-supplier-add-price');
        priceInput.classList.add('hc-supplier-price-input');
        priceInput.setAttribute('data-supplier-id', id);
        priceInput.disabled = false;
        wireSupplierPriceInput(priceInput);

        qtyCell.classList.remove('hc-supplier-qty-placeholder');
        qtyCell.setAttribute('data-supplier-id', id);

        totalCell.classList.remove('hc-supplier-total-placeholder');
        totalCell.setAttribute('data-supplier-id', id);

        actionCell.innerHTML = '';
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'hc-supplier-remove-btn';
        removeBtn.setAttribute('data-supplier-id', id);
        removeBtn.setAttribute('title', 'Remove supplier');
        removeBtn.setAttribute('aria-label', 'Remove supplier');
        removeBtn.textContent = '×';
        actionCell.appendChild(removeBtn);
        wireSupplierRemoveBtn(removeBtn);
        wireEditIndicator(row, [nameInput, priceInput]);

        // Every row belonging to this part carries the same data-part-key,
        // including the group's first (rowspan-holding) row.
        var firstRow = document.querySelector('tr.hc-inv-row[data-part-key="' + partKey + '"]');

        if (row === firstRow) {
          // 0 suppliers -> this becomes the first one: it needs its own
          // "+ add another" trigger now (it had none while empty).
          var addBtn = document.createElement('button');
          addBtn.type = 'button';
          addBtn.className = 'hc-supplier-add-btn';
          addBtn.setAttribute('data-part-key', partKey);
          addBtn.setAttribute('title', 'Add another supplier');
          addBtn.setAttribute('aria-label', 'Add another supplier');
          addBtn.textContent = '+';
          actionCell.appendChild(addBtn);
          wireAddMoreBtn(addBtn);
        } else if (firstRow) {
          // This was an inserted extra row — re-enable the primary row's
          // "+ add another" trigger (disabled while this draft was open).
          var existingAddBtn = firstRow.querySelector('.hc-supplier-add-btn');
          if (existingAddBtn) { existingAddBtn.disabled = false; }
        }

        updateSupplierTotals(partKey);
      }

      document.querySelectorAll('tr.hc-inv-row').forEach(function (row) {
        var nameInput = row.querySelector('.hc-supplier-name-input');
        var priceInput = row.querySelector('.hc-supplier-price-input');
        if (nameInput && priceInput) { wireEditIndicator(row, [nameInput, priceInput]); }
      });

      document.querySelectorAll('.hc-supplier-add-btn').forEach(function (btn) {
        // The lone placeholder row (0 suppliers) has no name input next to
        // its "+"; an existing primary row's "+ add another" does.
        var row = btn.closest('tr');
        if (row.querySelector('.hc-supplier-name-input')) { wireAddMoreBtn(btn); }
        else { wireAddRow(row); }
      });

      // ---------- Collapse extra suppliers behind a chevron ----------
      // PHP already sorts each part's suppliers cheapest-first (nulls
      // last), so the row with the rowspan'd Part Number/Description/
      // Inventory/Needed cells is always the cheapest one. Detaching (not
      // just hiding) the rest and shrinking the rowspan to match is what
      // keeps the table's rowspan math correct — a hidden-but-still-in-DOM
      // <tr> would still count toward the row's rowspan.
      (function () {
        var groups = {};
        document.querySelectorAll('tr.hc-inv-row').forEach(function (tr) {
          if (!tr.querySelector('.hc-supplier-remove-btn')) { return; } // only real, saved supplier rows
          var key = tr.getAttribute('data-part-key');
          (groups[key] = groups[key] || []).push(tr);
        });

        Object.keys(groups).forEach(function (key) {
          var rows = groups[key];
          if (rows.length < 2) { return; }
          var primary = rows[0];
          var extras = rows.slice(1);
          var rowspanCells = primary.querySelectorAll('[rowspan]');

          function adjustRowspan(delta) {
            rowspanCells.forEach(function (cell) {
              cell.setAttribute('rowspan', Math.max(1, (parseInt(cell.getAttribute('rowspan'), 10) || 1) + delta));
            });
          }

          extras.forEach(function (tr) { tr.remove(); });
          adjustRowspan(-extras.length);

          var toggleBtn = document.createElement('button');
          toggleBtn.type = 'button';
          toggleBtn.className = 'hc-supplier-toggle-btn';
          toggleBtn.textContent = '▾ ' + extras.length + ' more';
          var expanded = false;

          toggleBtn.addEventListener('click', function () {
            if (!expanded) {
              var ref = primary;
              extras.forEach(function (tr) {
                ref.parentNode.insertBefore(tr, ref.nextSibling);
                ref = tr;
              });
              adjustRowspan(extras.length);
              toggleBtn.textContent = '▴ Hide';
            } else {
              extras.forEach(function (tr) { tr.remove(); });
              adjustRowspan(-extras.length);
              toggleBtn.textContent = '▾ ' + extras.length + ' more';
            }
            expanded = !expanded;
          });

          primary.querySelector('.hc-supplier-name-cell').appendChild(toggleBtn);
        });
      })();
    })();
  </script>
  <script src="../../../assets/js/mobile-menu.js"></script>
  <script src="../../../assets/js/logout-confirm.js"></script>
</body>
</html>
