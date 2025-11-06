/**
 * Logout Confirmation Modal
 * Shows a custom confirmation dialog before logging out
 */

(function () {
  "use strict";

  // Create modal HTML if it doesn't exist
  function createLogoutModal() {
    if (document.getElementById("logoutModal")) return;

    const modalHTML = `
      <div id="logoutModal" class="logout-modal-overlay">
        <div class="logout-modal">
          <div class="logout-modal-header">
            <h2>Logout Confirmation</h2>
          </div>
          <p>Are you sure you want to log out? Please make sure everything is saved before you logout. Unsaved changes will be lost.</p>
          <div class="logout-modal-actions">
            <button class="logout-modal-btn logout-modal-btn-cancel" id="logoutCancel">
              Cancel
            </button>
            <button class="logout-modal-btn logout-modal-btn-confirm" id="logoutConfirm">
              Yes, Logout
            </button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHTML);
  }

  // Initialize logout confirmation
  function initLogoutConfirm() {
    createLogoutModal();

    const logoutButtons = document.querySelectorAll(".logout-btn");
    const modal = document.getElementById("logoutModal");
    const cancelBtn = document.getElementById("logoutCancel");
    const confirmBtn = document.getElementById("logoutConfirm");

    let logoutUrl = null;

    // Handle logout button clicks
    logoutButtons.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        logoutUrl = btn.getAttribute("href");
        modal.classList.add("active");
        document.body.style.overflow = "hidden";
      });
    });

    // Handle cancel
    cancelBtn.addEventListener("click", function () {
      modal.classList.remove("active");
      document.body.style.overflow = "";
      logoutUrl = null;
    });

    // Handle confirm
    confirmBtn.addEventListener("click", function () {
      if (logoutUrl) {
        window.location.href = logoutUrl;
      }
    });

    // Close modal on overlay click
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        modal.classList.remove("active");
        document.body.style.overflow = "";
        logoutUrl = null;
      }
    });

    // Close modal on ESC key
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.classList.contains("active")) {
        modal.classList.remove("active");
        document.body.style.overflow = "";
        logoutUrl = null;
      }
    });
  }

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initLogoutConfirm);
  } else {
    initLogoutConfirm();
  }
})();
