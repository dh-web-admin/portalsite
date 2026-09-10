<?php
/**
 * Interactive hydraulic diagram — single unified view of every layer in
 * images/hydraulic components.svg (no more per-tab layer switching).
 *
 * Source structure, per top-level <g inkscape:groupmode="layer">:
 *   Each direct child of a layer is a per-component WRAPPER group, itself
 *   labeled with the plain component number (e.g. "7", "13"). Inside it:
 *     - sub-group(s) with NO inkscape:label  = that component's own artwork
 *       (the actual geometry — pipe, block, manifold, whatever it is).
 *     - sub-group(s) WITH any inkscape:label (e.g. "7a", "7b", or even "7"
 *       again) = that component's callout marker(s) — number + leader
 *       line. A component can have more than one.
 *   The component number comes from the WRAPPER's own label, not from its
 *   children's — that's what makes a stray/mislabeled child harmless; it's
 *   grouped correctly by nesting regardless of what its own label says. A
 *   wrapper not labeled yet (not yet organised in Inkscape) still renders
 *   its geometry — every component's artwork is always visible — it just
 *   stays inert (no hover/click) until the wrapper itself is labeled.
 *
 * Geometry pieces get class="hc-piece" data-id="<number>".
 * Callout pieces get class="hc-label-item" data-id="<number>" and start
 * hidden; a component number can have several (all revealed together).
 *
 *   - hover a component -> every OTHER component dims (opacity only) and
 *     ALL of the hovered component's callouts appear.
 *   - click a component / callout -> its callouts stay revealed and its
 *     number opens in the side panel, until you click blank space.
 */

$hcSvgFile = __DIR__ . '/images/hydraulic components.svg';
$hcInline = '';

if (!function_exists('hc_force_visible')) {
    /**
     * Neutralise Inkscape's inline per-layer visibility/lock state (toggling
     * a layer's "eye" writes style="display:none" straight onto its <g>,
     * which would otherwise always beat our CSS-based show/hide).
     */
    function hc_force_visible(DOMElement $el)
    {
        $style = $el->getAttribute('style');
        if ($style !== '') {
            $style = trim(preg_replace('/display\s*:\s*[^;]+;?/i', '', $style), "; \t\n\r\0\x0B");
            if ($style !== '') {
                $el->setAttribute('style', $style);
            } else {
                $el->removeAttribute('style');
            }
        }
        $el->removeAttributeNS('http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd', 'insensitive');
    }
}

if (is_readable($hcSvgFile)) {
    $doc = new DOMDocument();
    // Inkscape files are well-formed XML; silence any noise into libxml.
    $prev = libxml_use_internal_errors(true);
    if ($doc->load($hcSvgFile)) {
        $svgNs = 'http://www.w3.org/2000/svg';
        $inkNs = 'http://www.inkscape.org/namespaces/inkscape';

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('svg', $svgNs);
        $xp->registerNamespace('inkscape', $inkNs);

        $layers = $xp->query('//svg:g[@inkscape:groupmode="layer"]');
        foreach ($layers as $layerNode) {
            if (!($layerNode instanceof DOMElement)) {
                continue;
            }
            hc_force_visible($layerNode);

            foreach ($layerNode->childNodes as $wrapper) {
                if (!($wrapper instanceof DOMElement)) {
                    continue;
                }

                // This wrapper's own label IS the component number — trust
                // the grouping, not each child's individual label text.
                $id = null;
                $wrapperLabel = $wrapper->getAttributeNS($inkNs, 'label');
                if ($wrapperLabel !== '' && preg_match('/^(\d+)/', $wrapperLabel, $m)) {
                    $id = $m[1];
                }

                foreach ($wrapper->childNodes as $sub) {
                    if (!($sub instanceof DOMElement)) {
                        continue;
                    }
                    $isCallout = ($sub->getAttributeNS($inkNs, 'label') !== '');
                    $cls = trim($sub->getAttribute('class') . ($isCallout ? ' hc-label-item' : ' hc-piece'));
                    $sub->setAttribute('class', $cls);
                    if ($id !== null) {
                        $sub->setAttribute('data-id', $id);
                    }
                }
            }
        }

        // Prepare the root <svg> for inline embedding.
        $svg = $doc->documentElement;
        if ($svg instanceof DOMElement) {
            $svg->removeAttribute('width');
            $svg->removeAttribute('height');
            $svg->setAttribute('class', 'hc-svg');
            $svg->setAttribute('role', 'group');
            $svg->setAttribute('aria-label', 'Hydraulic systems diagram');
            $svg->setAttribute('preserveAspectRatio', 'xMidYMin meet');
            $hcInline = $doc->saveXML($svg);
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
}
?>
<div class="hc-layout">
  <div class="hc-stage">
    <?php if ($hcInline !== ''): ?>
      <button type="button" id="hcResetView" class="hc-reset-view-btn" hidden>Reset view</button>
      <?php echo $hcInline; ?>
    <?php else: ?>
      <p class="eng-panel-placeholder">Diagram source could not be loaded.</p>
    <?php endif; ?>
  </div>

  <aside class="hc-detail" aria-live="polite">
    <div class="hc-detail-empty">Hover a component to highlight it. Click one to open its number here.</div>
  </aside>
</div>

<script>
  (function () {
    var stage = document.querySelector('.hc-stage');
    var svg = stage ? stage.querySelector('.hc-svg') : null;
    var layout = stage ? stage.parentElement : null;
    var detail = layout ? layout.querySelector('.hc-detail') : null;
    var resetBtn = stage ? stage.querySelector('.hc-reset-view-btn') : null;
    if (!svg || !detail) { return; }

    var pieces = Array.prototype.slice.call(svg.querySelectorAll('.hc-piece'));
    if (!pieces.length) { return; }

    var byId = {};
    pieces.forEach(function (el) {
      var id = el.getAttribute('data-id');
      if (id === null) { return; }
      (byId[id] = byId[id] || []).push(el);
    });

    // Callouts keyed by component number — a number can have more than one
    // (multiple leader lines to the same part). Only components that HAVE
    // at least one labeled callout are interactive; everything else just
    // renders its artwork and ignores hover/click.
    var labelsById = {};
    var labelItems = Array.prototype.slice.call(svg.querySelectorAll('.hc-label-item'));
    labelItems.forEach(function (item) {
      var id = item.getAttribute('data-id');
      if (id === null) { return; }
      (labelsById[id] = labelsById[id] || []).push(item);
    });
    function isActive(id) { return id !== null && !!labelsById[id]; }

    // make the first piece of each interactive component keyboard-focusable
    Object.keys(byId).forEach(function (id) {
      if (!isActive(id)) { return; }
      var first = byId[id][0];
      first.setAttribute('tabindex', '0');
      first.setAttribute('role', 'button');
      first.setAttribute('aria-label', 'Component ' + id);
    });

    var vbW = 160;          // fitted viewBox width, refreshed by fit()
    var pinnedId = null;    // component whose callouts are pinned open

    function dimTo(id) { pieces.forEach(function (p) { p.classList.toggle('hc-dim', p.getAttribute('data-id') !== id); }); }
    function undim() { pieces.forEach(function (p) { p.classList.remove('hc-dim'); }); }

    function setLabelVisible(id, visible) {
      (labelsById[id] || []).forEach(function (it) { it.style.visibility = visible ? 'visible' : ''; });
    }
    function showLabel(id) { setLabelVisible(id, true); }
    function hideLabel(id) { if (id !== pinnedId) { setLabelVisible(id, false); } }

    // hover a component -> dim the rest and reveal all of its callouts.
    // Leaving falls back to the PINNED component's dim state (if any)
    // instead of clearing it outright — a click's opacity change persists
    // until blank space is clicked, hovering elsewhere is just a preview.
    function restDim() { if (pinnedId) { dimTo(pinnedId); } else { undim(); } }
    function enter(id) { dimTo(id); showLabel(id); }
    function leave(id) { restDim(); hideLabel(id); }

    // click -> keep that component's callouts open and fill the side panel
    var detailRequestSeq = 0;
    function escapeHtml(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function renderGroup(id, parts) {
      var html = '<div class="hc-detail-head">Component ' + escapeHtml(id) + '</div>';
      if (!parts.length) {
        html += '<div class="hc-detail-body hc-detail-empty-msg">No parts recorded for this component yet.</div>';
      } else {
        // Rows sharing the same Number (e.g. three separate "9a" entries)
        // are grouped under one card with one number header.
        var byNumber = [];
        parts.forEach(function (p) {
          var last = byNumber[byNumber.length - 1];
          if (last && last.number === p.number) { last.rows.push(p); }
          else { byNumber.push({ number: p.number, rows: [p] }); }
        });

        html += '<div class="hc-group-list">';
        byNumber.forEach(function (group) {
          var href = 'hydraulic_components_list.php?highlight=' + encodeURIComponent(group.number);
          html += '<a class="hc-group-card" href="' + href + '" title="View ' + escapeHtml(group.number) + ' in All Components">' +
            '<div class="hc-group-card-top">' +
              '<span class="hc-group-card-number">' + escapeHtml(group.number) + '</span>' +
            '</div>' +
            '<div class="hc-group-card-items">' +
            group.rows.map(function (p) {
              return (p.description ? '<div class="hc-group-card-desc">' + escapeHtml(p.description) + '</div>' : '') +
                (p.manufacturer_description ?
                  '<div class="hc-group-card-mfrdesc">' + escapeHtml(p.manufacturer_description) + '</div>' : '');
            }).join('<hr class="hc-group-card-sep">') +
            '</div>' +
          '</a>';
        });
        html += '</div>';
      }
      detail.innerHTML = html;
    }

    function select(id) {
      var previous = pinnedId;
      pinnedId = id;
      if (previous && previous !== id) { setLabelVisible(previous, false); }
      showLabel(id);
      dimTo(id);

      var seq = ++detailRequestSeq;
      detail.innerHTML = '<div class="hc-detail-head">Component ' + escapeHtml(id) + '</div>' +
        '<div class="hc-detail-body hc-detail-empty-msg">Loading…</div>';
      fetch('../../../api/get_hydraulic_component_group.php?id=' + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (seq !== detailRequestSeq) { return; } // a newer selection superseded this request
          renderGroup(id, (data && data.success && data.components) || []);
        })
        .catch(function () {
          if (seq !== detailRequestSeq) { return; }
          detail.innerHTML = '<div class="hc-detail-head">Component ' + escapeHtml(id) + '</div>' +
            '<div class="hc-detail-body hc-detail-empty-msg">Could not load parts for this component.</div>';
        });
    }

    function clearPinned() {
      if (pinnedId != null) {
        setLabelVisible(pinnedId, false);
        pinnedId = null;
        detailRequestSeq++; // discard any in-flight detail fetch
      }
    }

    pieces.forEach(function (p) {
      var id = p.getAttribute('data-id');
      if (!isActive(id)) { return; }   // no callout yet -> inert (no hover/click)
      p.style.cursor = 'pointer';
      p.addEventListener('mouseenter', function () { enter(id); });
      p.addEventListener('mouseleave', function () { leave(id); });
      p.addEventListener('focus', function () { enter(id); });
      p.addEventListener('blur', function () { leave(id); });
      p.addEventListener('click', function (e) { e.stopPropagation(); select(id); });
      p.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(id); }
      });
    });

    // callouts themselves are clickable / highlight their component on hover
    labelItems.forEach(function (item) {
      var id = item.getAttribute('data-id');
      if (id === null) { return; }
      item.style.cursor = 'pointer';
      item.addEventListener('mouseenter', function () { dimTo(id); });
      item.addEventListener('mouseleave', function () { restDim(); });
      item.addEventListener('click', function (e) { e.stopPropagation(); select(id); });
    });

    // clicking blank space clears any pinned callouts + resets the panel
    document.addEventListener('click', function (e) {
      if (!e.target.closest || (!e.target.closest('.hc-piece') && !e.target.closest('.hc-label-item'))) {
        clearPinned();
        undim();
        detail.innerHTML = '<div class="hc-detail-empty">Hover a component to highlight it. Click one to open its number here.</div>';
      }
    });

    // Bounding box of a top-level layer IN ROOT/SVG COORDINATES — i.e. with
    // that layer's own transform (if any) applied. getBBox() alone returns
    // the box in the element's pre-transform local space, which is wrong to
    // feed straight into viewBox when the layer itself carries a translate/
    // scale (Inkscape adds one when you resize the page to fit content).
    // Some of this file's layers have such a transform and some don't, so
    // this has to handle both rather than assume either.
    function bboxOf(node) {
      if (!node) { return null; }
      var b;
      try { b = node.getBBox(); } catch (e) { return null; }
      if (!b || (b.width === 0 && b.height === 0)) { return null; }
      var matrix = null;
      try {
        var tl = node.transform && node.transform.baseVal;
        if (tl && tl.numberOfItems > 0) { matrix = tl.consolidate().matrix; }
      } catch (e) { matrix = null; }
      if (!matrix) { return { x: b.x, y: b.y, width: b.width, height: b.height }; }
      var xs = [], ys = [];
      [[b.x, b.y], [b.x + b.width, b.y], [b.x, b.y + b.height], [b.x + b.width, b.y + b.height]].forEach(function (c) {
        var pt = svg.createSVGPoint();
        pt.x = c[0]; pt.y = c[1];
        var tp = pt.matrixTransform(matrix);
        xs.push(tp.x); ys.push(tp.y);
      });
      return {
        x: Math.min.apply(null, xs), y: Math.min.apply(null, ys),
        width: Math.max.apply(null, xs) - Math.min.apply(null, xs),
        height: Math.max.apply(null, ys) - Math.min.apply(null, ys)
      };
    }

    // auto-fit the viewBox to the union of every layer's content, with
    // headroom on top for callouts near the edge so they never clip.
    // homeVB is the FULL content extent — the outer limit pan/zoom can
    // never go past. defaultVB is a zoomed-in slice of it (same center)
    // used as the actual starting view and what Reset/double-click return
    // to; zooming OUT can still reach all the way to homeVB.
    var homeVB = null;    // { x, y, width, height }
    var defaultVB = null;
    var curVB = null;
    var DEFAULT_ZOOM = 0.88; // < 1 = zoomed in a bit from the full fitted content
    var DEFAULT_V_BIAS = 1;  // how the cropped vertical space is distributed:
                              // 1 = taken entirely off the top (nothing lost
                              // off the bottom), 0.5 = centered, 0 = off the
                              // bottom only. There's more headroom above the
                              // drawing than below, so bias toward the top.
    var layerEls = Array.prototype.slice.call(svg.children).filter(function (el) { return el.tagName === 'g'; });

    function zoomedBox(box, factor, vBias) {
      var w = box.width * factor, h = box.height * factor;
      var removedH = box.height - h;
      return {
        x: box.x + (box.width - w) / 2,
        y: box.y + removedH * (vBias == null ? 0.5 : vBias),
        width: w, height: h
      };
    }

    // Keep the visible box inside the actual drawing's full extent —
    // panning (or the zoom-out clamp below) can never drag the content off
    // past its own edges into blank space beyond what fit() measured.
    function clampToHome(vb) {
      if (!homeVB) { return vb; }
      var x, y;
      var maxX = homeVB.x + homeVB.width - vb.width;
      var maxY = homeVB.y + homeVB.height - vb.height;
      x = maxX < homeVB.x ? homeVB.x + (homeVB.width - vb.width) / 2 : Math.min(Math.max(vb.x, homeVB.x), maxX);
      y = maxY < homeVB.y ? homeVB.y + (homeVB.height - vb.height) / 2 : Math.min(Math.max(vb.y, homeVB.y), maxY);
      return { x: x, y: y, width: vb.width, height: vb.height };
    }

    function isDefaultView(vb) {
      if (!defaultVB || !vb) { return true; }
      var eps = defaultVB.width * 0.001;
      return Math.abs(vb.x - defaultVB.x) < eps && Math.abs(vb.y - defaultVB.y) < eps && Math.abs(vb.width - defaultVB.width) < eps;
    }

    function setViewBox(vb) {
      curVB = clampToHome(vb);
      svg.setAttribute('viewBox', curVB.x + ' ' + curVB.y + ' ' + curVB.width + ' ' + curVB.height);
      if (resetBtn) { resetBtn.hidden = isDefaultView(curVB); }
    }

    function fit() {
      var boxes = layerEls.map(bboxOf).filter(Boolean);
      if (!boxes.length) { return; }
      var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
      boxes.forEach(function (b) {
        x1 = Math.min(x1, b.x); y1 = Math.min(y1, b.y);
        x2 = Math.max(x2, b.x + b.width); y2 = Math.max(y2, b.y + b.height);
      });
      var b = { x: x1, y: y1, width: x2 - x1, height: y2 - y1 };
      var padX = b.width * 0.03;
      var padTop = Math.max(b.height * 0.05, b.width * 0.025);
      var padBot = b.height * 0.03;
      homeVB = {
        x: b.x - padX, y: b.y - padTop,
        width: b.width + padX * 2, height: b.height + padTop + padBot
      };
      defaultVB = zoomedBox(homeVB, DEFAULT_ZOOM, DEFAULT_V_BIAS);
      vbW = homeVB.width;
      setViewBox(defaultVB);
    }

    // Keep the drawing's height within whatever room is actually left on
    // screen below the sticky topbar, so it never runs past the viewport
    // bottom (measured, not guessed — correct at any breakpoint).
    function applyMaxHeight() {
      var top = stage.getBoundingClientRect().top;
      if (top <= 0) { return; }
      var avail = window.innerHeight - top - 16;
      if (avail < 180) { avail = 180; }
      svg.style.maxHeight = avail + 'px';
    }

    function refresh() { fit(); applyMaxHeight(); }
    refresh();
    requestAnimationFrame(refresh);

    var resizeTicking = false;
    window.addEventListener('resize', function () {
      if (resizeTicking) { return; }
      resizeTicking = true;
      requestAnimationFrame(function () { applyMaxHeight(); resizeTicking = false; });
    });

    // ---------- Zoom (wheel) + pan (drag) ----------
    // The stage box's own size never changes — only the SVG's viewBox, so
    // zooming/panning the drawing never reflows the rest of the page.
    var MIN_SCALE = 1;    // 1x = zoomed out to "home" (can't zoom out further)
    var MAX_SCALE = 12;   // 12x = zoomed in

    function clientToSvgPoint(clientX, clientY) {
      var pt = svg.createSVGPoint();
      pt.x = clientX; pt.y = clientY;
      var ctm = svg.getScreenCTM();
      if (!ctm) { return null; }
      return pt.matrixTransform(ctm.inverse());
    }

    svg.addEventListener('wheel', function (e) {
      if (!curVB || !homeVB) { return; }
      e.preventDefault();
      var anchor = clientToSvgPoint(e.clientX, e.clientY);
      if (!anchor) { return; }

      var factor = e.deltaY < 0 ? 0.88 : 1 / 0.88; // wheel up = zoom in
      var newWidth = curVB.width * factor;
      var minWidth = homeVB.width / MAX_SCALE;
      var maxWidth = homeVB.width / MIN_SCALE;
      newWidth = Math.min(maxWidth, Math.max(minWidth, newWidth));
      var ratio = newWidth / curVB.width;
      var newHeight = curVB.height * ratio;

      setViewBox({
        x: anchor.x - (anchor.x - curVB.x) * ratio,
        y: anchor.y - (anchor.y - curVB.y) * ratio,
        width: newWidth,
        height: newHeight
      });
    }, { passive: false });

    var panning = false;
    var lastClientX = 0, lastClientY = 0;

    svg.addEventListener('mousedown', function (e) {
      if (e.button !== 0 || !curVB) { return; }
      e.preventDefault(); // no native image/text drag ghost while panning
      panning = true;
      lastClientX = e.clientX;
      lastClientY = e.clientY;
      stage.classList.add('hc-panning');
    });

    window.addEventListener('mousemove', function (e) {
      if (!panning || !curVB) { return; }
      var p1 = clientToSvgPoint(lastClientX, lastClientY);
      var p2 = clientToSvgPoint(e.clientX, e.clientY);
      lastClientX = e.clientX;
      lastClientY = e.clientY;
      if (!p1 || !p2) { return; }
      var dx = p2.x - p1.x, dy = p2.y - p1.y;
      if (dx === 0 && dy === 0) { return; }
      setViewBox({ x: curVB.x - dx, y: curVB.y - dy, width: curVB.width, height: curVB.height });
    });

    window.addEventListener('mouseup', function () {
      if (!panning) { return; }
      panning = false;
      stage.classList.remove('hc-panning');
    });

    // double-click blank space resets the view; on a component it still
    // selects normally via the existing click handler
    svg.addEventListener('dblclick', function (e) {
      if (!defaultVB) { return; }
      if (e.target.closest && (e.target.closest('.hc-piece') || e.target.closest('.hc-label-item'))) { return; }
      setViewBox(defaultVB);
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (defaultVB) { setViewBox(defaultVB); }
      });
    }
  })();
</script>
