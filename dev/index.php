<?php require_once __DIR__ . '/auth_check.php'; ?>
<?php require_once __DIR__ . '/../partials/url.php'; ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dev Dashboard</title>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at 25% 25%, #1e293b 0%, #0f172a 70%);
        min-height: 100vh;
        padding: 0 20px 60px;
        color: #e2e8f0;
      }

      .layout-shell { max-width: 1300px; margin: 0 auto; }

      .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 28px;
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        margin: 25px auto 35px;
      }
      .top-bar h1 { font-size: 1.4rem; font-weight: 600; letter-spacing: .5px; color: #f1f5f9; }
      .actions { display: flex; gap: 12px; }
      .actions a {
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: .9rem;
        font-weight: 600;
        letter-spacing: .4px;
        background: #1d4ed8;
        color: #fff;
        border: 1px solid #1d4ed8;
        transition: background .25s ease, transform .25s ease, box-shadow .25s ease;
      }
      .actions a.secondary {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.15);
        color: #f1f5f9;
      }
      .actions a:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(29,78,216,0.35); }
      .actions a.secondary:hover { background: rgba(255,255,255,0.12); box-shadow: 0 6px 18px rgba(0,0,0,0.35); }

      header { text-align: center; color: #f1f5f9; margin-bottom: 38px; }

      header h2 { font-size: 2.4rem; margin-bottom: 12px; font-weight: 600; }

      header p { font-size: 1.05rem; opacity: .75; max-width: 780px; margin: 0 auto; line-height: 1.55; }

      .search-bar {
        max-width: 640px;
        margin: 0 auto 42px;
        position: relative;
      }

      .search-bar input {
        width: 100%; padding: 16px 22px; font-size: .95rem; border: 1px solid rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.06); color: #f8fafc; border-radius: 12px; outline: none;
        backdrop-filter: blur(6px); transition: border-color .25s ease, background .25s ease;
      }

      .search-bar input:focus { border-color: #3b82f6; background: rgba(255,255,255,0.1); }

      .docs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 28px;
        margin-bottom: 46px;
      }

      .doc-card {
        background: rgba(255,255,255,0.035);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 16px;
        padding: 26px 26px 24px;
        transition: border-color .3s ease, background .3s ease, transform .3s ease;
        text-decoration: none; color: #cbd5e1; display: block;
        backdrop-filter: blur(8px);
      }

      .doc-card:hover { border-color: #3b82f6; background: rgba(255,255,255,0.07); transform: translateY(-4px); }

      .doc-card h2 { font-size: 1.35rem; margin-bottom: 10px; color: #f1f5f9; font-weight: 600; letter-spacing: .3px; }

      .doc-card p { color: #94a3b8; line-height: 1.55; margin-bottom: 16px; font-size: .9rem; }

      .doc-card-meta {
        display: flex; justify-content: space-between; font-size: .7rem; color: #64748b; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08);
      }

      .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
      }

      .badge-essential {
        background: #fef3c7;
        color: #92400e;
      }

      .badge-setup {
        background: #dbeafe;
        color: #1e40af;
      }

      .badge-reference {
        background: #f3e8ff;
        color: #6b21a8;
      }

      .badge-guide {
        background: #dcfce7;
        color: #166534;
      }

      .category-filter { display: flex; justify-content: center; gap: 14px; margin-bottom: 34px; flex-wrap: wrap; }

      .filter-btn { padding: 10px 22px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: #f8fafc; border-radius: 9px; cursor: pointer; font-size: .8rem; font-weight: 600; letter-spacing: .5px; transition: all .25s ease; }
      .filter-btn:hover, .filter-btn.active { background: #1d4ed8; border-color: #1d4ed8; }

      .hidden {
        display: none;
      }

      footer { text-align: center; color: #64748b; margin-top: 60px; padding: 24px 0 10px; font-size: .75rem; letter-spacing: .4px; }

      @media (max-width: 820px) {
        .top-bar { flex-direction: column; align-items: flex-start; gap: 16px; }
        .actions { width: 100%; }
        .actions a { flex: 1; text-align: center; }
        header h2 { font-size: 1.9rem; }
        .docs-grid { grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <div class="layout-shell">
      <div class="top-bar">
        <h1>Developer Dashboard</h1>
        <div class="actions">
          <a class="secondary" href="<?php echo htmlspecialchars(base_url('/pages/dashboard/')); ?>">Employee Portal</a>
          <a id="logoutLink" href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>">Logout</a>
        </div>
      </div>
      <header>
        <h2>Developer Dashboard</h2>
        <p>Access internal technical references, environment setup guides, architectural notes, and operational workflows. Use search or filters to quickly locate what you need.</p>
      </header>

      <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search documentation..." />
      </div>

      <div class="category-filter">
        <button class="filter-btn active" data-filter="all">All Docs</button>
        <button class="filter-btn" data-filter="essential">Essential</button>
        <button class="filter-btn" data-filter="setup">Setup</button>
        <button class="filter-btn" data-filter="reference">Reference</button>
        <button class="filter-btn" data-filter="guide">Guide</button>
      </div>

      <div class="docs-grid" id="docsGrid">
        <!-- Get Started (Onboarding) - ESSENTIAL (FIRST) -->
        <a href="get-started.html" class="doc-card" data-category="essential">
          <h2>Get Started (Developer Onboarding)</h2>
          <p>
            Step-by-step instructions to set up the project locally (XAMPP,
            database, config), clone the repo, and the basic developer workflow.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-essential">Essential</span>
            <span>Updated: Nov 6, 2025</span>
          </div>
        </a>

        <!-- Daily Workflow - ESSENTIAL -->
        <a href="daily-workflow.html" class="doc-card" data-category="essential">
          <h2>Daily Workflow</h2>
          <p>
            Step-by-step guide for daily development tasks, Git commands, and
            best practices.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-essential">Essential</span>
            <span>Updated: Nov 6, 2025</span>
          </div>
        </a>

        <!-- Setup: GitHub, Railway -->
        <a href="github-setup.html" class="doc-card" data-category="setup">
          <h2>GitHub Setup</h2>
          <p>
            Initial Git configuration, repository setup, and GitHub integration
            instructions.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-setup">Setup</span>
            <span>Initial Setup</span>
          </div>
        </a>

        <a href="railway-deployment.html" class="doc-card" data-category="setup">
          <h2>Railway Deployment</h2>
          <p>
            Deploy PortalSite to Railway.app with database configuration and
            environment setup.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-setup">Setup</span>
            <span>Production Deploy</span>
          </div>
        </a>

        <!-- Reference: Portal Organization, Pages Reorganization -->
        <a href="portal-organization.html" class="doc-card" data-category="reference">
          <h2>Portal Organization</h2>
          <p>
            Complete file structure, dependencies, and architecture overview of
            the PortalSite application.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-reference">Reference</span>
            <span>Updated: Nov 6, 2025</span>
          </div>
        </a>

        <a href="pages-reorganization.html" class="doc-card" data-category="reference">
          <h2>Pages Reorganization</h2>
          <p>
            New folder structure for pages, path changes, and migration guide
            from flat to organized structure.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-reference">Reference</span>
            <span>Updated: Nov 6, 2025</span>
          </div>
        </a>

        <!-- Recent Maps/UI changes -->
        <a href="changes.html" class="doc-card" data-category="reference">
          <h2>Maps & UI Changes (Nov 14, 2025)</h2>
          <p>
            Changelog and verification steps for recent Maps page improvements: combined coordinates input, parsing fallbacks, updated supplier details layout, and actions bar placement.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-reference">Reference</span>
            <span>Updated: Nov 14, 2025</span>
          </div>
        </a>

        <!-- Guide: Responsive Design (kept last) -->
        <a href="responsive-design.html" class="doc-card" data-category="guide">
          <h2>Responsive Design</h2>
          <p>
            Mobile-first design principles, breakpoints, and responsive
            implementation guide.
          </p>
          <div class="doc-card-meta">
            <span class="badge badge-guide">Guide</span>
            <span>Design System</span>
          </div>
        </a>
      </div>

      <footer>
        <p>Developer Documentation • Internal Use Only</p>
        <p>PortalSite v2.0 • Maintained by dh-web-admin</p>
      </footer>
    </div>

    <script>
      // Logout confirmation modal
      (function(){
        const logoutLink = document.getElementById('logoutLink');
        if (!logoutLink) return;

        // Create modal elements
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.inset = '0';
        overlay.style.background = 'rgba(2,6,23,0.65)';
        overlay.style.backdropFilter = 'blur(4px)';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '1000';

        const modal = document.createElement('div');
        modal.style.width = 'min(520px, 92vw)';
        modal.style.background = 'rgba(255,255,255,0.06)';
        modal.style.border = '1px solid rgba(255,255,255,0.12)';
        modal.style.borderRadius = '14px';
        modal.style.padding = '22px 22px 18px';
        modal.style.color = '#e2e8f0';
        modal.style.boxShadow = '0 20px 60px rgba(0,0,0,0.45)';
        modal.style.backdropFilter = 'blur(8px)';

        const title = document.createElement('h3');
        title.textContent = 'Confirm Logout';
        title.style.margin = '0 0 8px';
        title.style.fontSize = '1.2rem';
        title.style.fontWeight = '600';

        const desc = document.createElement('p');
        desc.textContent = 'Are you sure you want to log out? You’ll need to sign in again to access developer resources.';
        desc.style.margin = '0 0 18px';
        desc.style.color = '#94a3b8';
        desc.style.fontSize = '.95rem';
        desc.style.lineHeight = '1.55';

        const btnRow = document.createElement('div');
        btnRow.style.display = 'flex';
        btnRow.style.justifyContent = 'flex-end';
        btnRow.style.gap = '10px';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.style.padding = '10px 18px';
        cancelBtn.style.borderRadius = '8px';
        cancelBtn.style.fontWeight = '600';
        cancelBtn.style.letterSpacing = '.3px';
        cancelBtn.style.border = '1px solid rgba(255,255,255,0.15)';
        cancelBtn.style.background = 'rgba(255,255,255,0.06)';
        cancelBtn.style.color = '#f1f5f9';
        cancelBtn.style.cursor = 'pointer';

        const confirmBtn = document.createElement('a');
        confirmBtn.textContent = 'Logout';
        confirmBtn.href = logoutLink.getAttribute('href');
        confirmBtn.style.textDecoration = 'none';
        confirmBtn.style.padding = '10px 18px';
        confirmBtn.style.borderRadius = '8px';
        confirmBtn.style.fontWeight = '700';
        confirmBtn.style.letterSpacing = '.3px';
        confirmBtn.style.background = '#1d4ed8';
        confirmBtn.style.border = '1px solid #1d4ed8';
        confirmBtn.style.color = '#fff';

        btnRow.appendChild(cancelBtn);
        btnRow.appendChild(confirmBtn);
        modal.appendChild(title);
        modal.appendChild(desc);
        modal.appendChild(btnRow);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        function openModal(){ overlay.style.display = 'flex'; }
        function closeModal(){ overlay.style.display = 'none'; }
        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

        logoutLink.addEventListener('click', (e) => {
          e.preventDefault();
          openModal();
        });
      })();
      // Search functionality
      const searchInput = document.getElementById("searchInput");
      const docCards = document.querySelectorAll(".doc-card");

      searchInput.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();

        docCards.forEach((card) => {
          const title = card.querySelector("h2").textContent.toLowerCase();
          const description = card.querySelector("p").textContent.toLowerCase();

          if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = "block";
          } else {
            card.style.display = "none";
          }
        });
      });

      // Category filter
      const filterBtns = document.querySelectorAll(".filter-btn");

      filterBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
          // Update active button
          filterBtns.forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");

          const filter = btn.getAttribute("data-filter");

          docCards.forEach((card) => {
            const category = card.getAttribute("data-category");

            if (filter === "all" || category === filter) {
              card.style.display = "block";
            } else {
              card.style.display = "none";
            }
          });
        });
      });

      // Subtle interaction refinement (remove previous border-left effect)
    </script>
  </body>
</html>
