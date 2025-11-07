// Global Unsaved Changes Guard
// Tracks elements marked as data-track-unsaved (contentEditable, inputs, textareas)
// Prevents navigation (links + beforeunload) if modifications are detected.
// Exposes window.UnsavedGuard with API: markClean(), forceAllowNextNavigation(), hasChanges()

(function () {
  var trackedSelectors =
    "input[data-track-unsaved], textarea[data-track-unsaved], [contenteditable][data-track-unsaved]";
  var originalValues = new Map(); // element -> original value/text
  var dirtyElements = new Set();
  var modalId = "globalUnsavedModal";
  var pendingHref = null;
  var initialized = false;

  function readValue(el) {
    if (!el) return "";
    if (el.matches("[contenteditable]")) return (el.textContent || "").trim();
    return (el.value || "").trim();
  }

  function scanInitial() {
    document.querySelectorAll(trackedSelectors).forEach(function (el) {
      originalValues.set(el, readValue(el));
    });
  }

  function checkDirty(el) {
    var orig = originalValues.get(el) || "";
    var current = readValue(el);
    if (current !== orig) {
      dirtyElements.add(el);
    } else {
      dirtyElements.delete(el);
    }
  }

  function hasChanges() {
    return dirtyElements.size > 0;
  }

  function ensureModal() {
    if (document.getElementById(modalId)) return;
    var wrap = document.createElement("div");
    wrap.id = modalId;
    wrap.style.cssText =
      "display:none;position:fixed;inset:0;z-index:4000;background:rgba(2,6,23,0.55);align-items:center;justify-content:center";
    wrap.innerHTML =
      '\n      <div style="background:#ffffff;padding:18px 18px 14px 18px;border-radius:12px;max-width:480px;width:94%;box-shadow:0 10px 30px rgba(2,6,23,0.18);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica Neue,Arial">\n        <h3 style="margin:0 0 8px 0;font-size:19px;color:#0f172a">Unsaved changes</h3>\n        <p style="margin:0 0 16px 0;font-size:14px;color:#334155;line-height:1.4">You have unsaved edits. Save before leaving, discard, or stay on this page.</p>\n        <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">\n          <button type="button" class="btn" id="unsavedStayGlobal">Stay</button>\n          <button type="button" class="btn ghost" id="unsavedDiscardGlobal">Discard</button>\n          <button type="button" class="btn btn-primary" id="unsavedSaveGlobal">Save & Continue</button>\n        </div>\n      </div>';
    document.body.appendChild(wrap);
  }

  function showModal(href) {
    pendingHref = href;
    ensureModal();
    var m = document.getElementById(modalId);
    if (m) m.style.display = "flex";
  }
  function hideModal() {
    var m = document.getElementById(modalId);
    if (m) m.style.display = "none";
  }

  function interceptLinks() {
    document.addEventListener("click", function (e) {
      var a = e.target.closest("a");
      if (!a) return;
      var href = a.getAttribute("href");
      if (!href || /^javascript:/i.test(href) || href.charAt(0) === "#") return;
      // allow explicit bypass
      if (a.hasAttribute("data-unsaved-ignore")) return;
      // Allow logout to proceed unblocked
      var hLower = href.toLowerCase();
      if (hLower.indexOf("/auth/logout.php") !== -1) return;
      if (!hasChanges()) return;
      e.preventDefault();
      showModal(href);
    });
  }

  function bindInputs() {
    document.addEventListener("input", function (e) {
      var el = e.target;
      if (!el.matches(trackedSelectors)) return;
      checkDirty(el);
    });
    document.addEventListener(
      "blur",
      function (e) {
        var el = e.target;
        if (!el.matches(trackedSelectors)) return;
        checkDirty(el);
      },
      true
    );
  }

  function beforeUnloadHandler(e) {
    if (hasChanges()) {
      e.preventDefault();
      e.returnValue = "You have unsaved changes.";
      return e.returnValue;
    }
  }

  function wireModalButtons() {
    document.addEventListener("click", function (e) {
      if (e.target.id === "unsavedStayGlobal") {
        hideModal();
        pendingHref = null;
      } else if (e.target.id === "unsavedDiscardGlobal") {
        discardChanges();
        hideModal();
        navigatePending();
      } else if (e.target.id === "unsavedSaveGlobal") {
        saveChanges().then(function () {
          hideModal();
          navigatePending();
        });
      }
    });
  }

  function navigatePending() {
    if (pendingHref) {
      var h = pendingHref;
      pendingHref = null;
      window.location.href = h;
    }
  }

  // Default save implementation: dispatch a custom event so page-specific code can handle actual persistence.
  function saveChanges() {
    return new Promise(function (resolve) {
      var evt = new CustomEvent("unsaved:save", {
        detail: { resolve: resolve },
      });
      window.dispatchEvent(evt);
      // If page code does not resolve within 2s, auto-resolve (avoid stall)
      setTimeout(function () {
        resolve();
      }, 2000);
    }).then(function () {
      markClean();
    });
  }

  function discardChanges() {
    // Reset values to original
    dirtyElements.forEach(function (el) {
      var orig = originalValues.get(el) || "";
      if (el.matches("[contenteditable]")) el.textContent = orig;
      else el.value = orig;
    });
    markClean();
  }

  function markClean() {
    dirtyElements.clear();
  }

  function init() {
    if (initialized) return;
    initialized = true;
    scanInitial();
    bindInputs();
    interceptLinks();
    ensureModal();
    wireModalButtons();
    window.addEventListener("beforeunload", beforeUnloadHandler);
  }

  // Public API for page scripts
  window.UnsavedGuard = {
    init: init,
    hasChanges: hasChanges,
    markClean: markClean,
    forceAllowNextNavigation: function () {
      pendingHref = null;
    },
    registerElement: function (el) {
      if (!el) return;
      el.setAttribute("data-track-unsaved", "");
      originalValues.set(el, readValue(el));
    },
    syncSnapshot: function () {
      document.querySelectorAll(trackedSelectors).forEach(function (el) {
        originalValues.set(el, readValue(el));
        checkDirty(el);
      });
    },
  };

  // Auto-init after DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
