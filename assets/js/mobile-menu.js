/**
 * Mobile Menu Toggle
 * Handles hamburger menu for mobile devices
 */

(function () {
  "use strict";

  // Only initialize on mobile devices
  function initMobileMenu() {
    if (window.innerWidth > 600) return;

    // Create mobile menu button if it doesn't exist
    let menuBtn = document.querySelector(".mobile-menu-btn");
    if (!menuBtn) {
      menuBtn = document.createElement("button");
      menuBtn.className = "mobile-menu-btn";
      menuBtn.setAttribute("aria-label", "Toggle navigation menu");
      menuBtn.innerHTML = "<span></span><span></span><span></span>";
      document.body.appendChild(menuBtn);
    }

    // Create overlay if it doesn't exist
    let overlay = document.querySelector(".mobile-menu-overlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.className = "mobile-menu-overlay";
      document.body.appendChild(overlay);
    }

    const sideNav = document.querySelector(".side-nav");
    if (!sideNav) return;

    // Toggle menu
    menuBtn.addEventListener("click", function () {
      menuBtn.classList.toggle("active");
      sideNav.classList.toggle("mobile-open");
      overlay.classList.toggle("active");

      // Prevent body scroll when menu is open
      if (sideNav.classList.contains("mobile-open")) {
        document.body.style.overflow = "hidden";
      } else {
        document.body.style.overflow = "";
      }
    });

    // Close menu when overlay is clicked
    overlay.addEventListener("click", function () {
      menuBtn.classList.remove("active");
      sideNav.classList.remove("mobile-open");
      overlay.classList.remove("active");
      document.body.style.overflow = "";
    });

    // Close menu when navigation link is clicked
    const navLinks = sideNav.querySelectorAll("a");
    navLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        menuBtn.classList.remove("active");
        sideNav.classList.remove("mobile-open");
        overlay.classList.remove("active");
        document.body.style.overflow = "";
      });
    });
  }

  // Initialize on page load
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMobileMenu);
  } else {
    initMobileMenu();
  }

  // Reinitialize on window resize
  let resizeTimer;
  window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      // Clean up mobile elements on desktop
      if (window.innerWidth > 600) {
        const menuBtn = document.querySelector(".mobile-menu-btn");
        const overlay = document.querySelector(".mobile-menu-overlay");
        const sideNav = document.querySelector(".side-nav");

        if (menuBtn) menuBtn.remove();
        if (overlay) overlay.remove();
        if (sideNav) {
          sideNav.classList.remove("mobile-open");
        }
        document.body.style.overflow = "";
      } else {
        initMobileMenu();
      }
    }, 250);
  });
})();
