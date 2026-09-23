// Idle session warning + auto-logout.
// Warns the user before the server-side inactivity timeout (session_init.php)
// would log them out, and lets them stay logged in with one click — which
// also pings the server so its own inactivity clock resets too.
// The idle limit comes from window.IDLE_TIMEOUT_MS (set inline in
// portalheader.php from SESSION_IDLE_LIMIT_SECONDS) so the two never drift.
(function () {
  "use strict";

  var IDLE_LIMIT_MS = window.IDLE_TIMEOUT_MS || 30 * 60 * 1000;
  var WARNING_LEAD_MS = 60 * 1000; // show the warning this long before logout
  var CHECK_INTERVAL_MS = 1000;

  var scriptEl = document.currentScript;
  var basePath = "";
  if (scriptEl && scriptEl.src) {
    basePath = scriptEl.src.replace(/\/assets\/js\/idle-timeout\.js(\?.*)?$/, "");
  }

  var lastActivity = Date.now();
  var warningShown = false;
  var modal, countdownEl, stayBtn, logoutBtn;

  function buildModal() {
    if (document.getElementById("idleWarningModal")) return;
    var html =
      '<div id="idleWarningModal" class="logout-modal-overlay">' +
      '<div class="logout-modal">' +
      '<div class="logout-modal-header">' +
      '<span class="logout-modal-icon" aria-hidden="true">⏰</span>' +
      "<h2>Still there?</h2>" +
      "</div>" +
      '<p>You’ve been inactive for a while. For your security, you’ll be logged out in <strong id="idleCountdown">60</strong> seconds.</p>' +
      '<div class="logout-modal-actions">' +
      '<button type="button" class="logout-modal-btn logout-modal-btn-confirm" id="idleLogoutNow">Log Out Now</button>' +
      '<button type="button" class="logout-modal-btn logout-modal-btn-cancel" id="idleStayLoggedIn">Stay Logged In</button>' +
      "</div></div></div>";
    document.body.insertAdjacentHTML("beforeend", html);
    modal = document.getElementById("idleWarningModal");
    countdownEl = document.getElementById("idleCountdown");
    stayBtn = document.getElementById("idleStayLoggedIn");
    logoutBtn = document.getElementById("idleLogoutNow");
    stayBtn.addEventListener("click", stayLoggedIn);
    logoutBtn.addEventListener("click", forceLogout);
  }

  function showWarning() {
    if (warningShown) return;
    warningShown = true;
    buildModal();
    modal.classList.add("active");
  }

  function hideWarning() {
    warningShown = false;
    if (modal) modal.classList.remove("active");
  }

  function pingServer() {
    fetch(basePath + "/api/session_ping.php", { credentials: "same-origin" }).catch(function () {
      /* best effort — a failed ping just means the next real request settles it */
    });
  }

  function forceLogout() {
    window.location.href = basePath + "/auth/logout.php";
  }

  function stayLoggedIn() {
    lastActivity = Date.now();
    hideWarning();
    pingServer();
  }

  function markActive() {
    lastActivity = Date.now();
    // Any activity while the warning is up counts as "I'm still here".
    if (warningShown) {
      hideWarning();
      pingServer();
    }
  }

  function tick() {
    var idleFor = Date.now() - lastActivity;
    if (idleFor >= IDLE_LIMIT_MS) {
      forceLogout();
      return;
    }
    if (idleFor >= IDLE_LIMIT_MS - WARNING_LEAD_MS) {
      showWarning();
      if (countdownEl) {
        countdownEl.textContent = String(Math.max(0, Math.ceil((IDLE_LIMIT_MS - idleFor) / 1000)));
      }
    }
  }

  ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "wheel"].forEach(function (evt) {
    document.addEventListener(evt, markActive, { passive: true });
  });

  setInterval(tick, CHECK_INTERVAL_MS);
})();
