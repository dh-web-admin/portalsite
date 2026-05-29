<?php require_once __DIR__ . '/auth_check.php'; ?>
<?php require_once __DIR__ . '/../partials/url.php'; ?>
<?php $subTitle = 'Dummy Accounts'; ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dummy Accounts</title>
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at 25% 25%, #1e293b 0%, #0f172a 70%);
        min-height:100vh; padding:0 20px 60px; color:#e2e8f0;
      }
      .layout-shell { max-width:1300px; margin:0 auto; }
      .top-bar { display:flex; align-items:center; justify-content:space-between; padding:18px 28px; background:rgba(255,255,255,0.04); backdrop-filter: blur(10px); border:1px solid rgba(255,255,255,0.08); border-radius:14px; margin:25px auto 35px; }
      .btn-back { padding:6px 10px; border-radius:8px; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); color:#e6eef8; font-weight:600; cursor:pointer; margin-right:12px; }
      .btn-back:hover { background: rgba(255,255,255,0.04); }
      .top-bar h1 { font-size:1.2rem; font-weight:600; color:#f1f5f9; }
      .top-bar-title { display:flex; flex-direction:column; gap:6px; }
      .top-subtitle { color:#94a3b8; font-size:0.9rem; }
      .card { background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:22px; color:#e6eef8; box-shadow: 0 8px 30px rgba(2,6,23,0.6); }
      /* Make native select dropdown options more visible on dark theme */
      select { color: #0f172a; background: #ffffff; padding:8px 10px; }
      select option { color: #0f172a; background: #ffffff; }
      .card h3 { font-size:1.25rem; margin-bottom:10px; }
      .card p { color:#9aa8b8; margin-bottom:12px; }
      .actions { display:flex; gap:12px; }
      .actions a { text-decoration:none; padding:10px 20px; border-radius:8px; font-weight:600; background:#1d4ed8; color:#fff; border:1px solid #1d4ed8; }
      .actions a.secondary { background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#f1f5f9; }
      header { text-align:center; color:#f1f5f9; margin-bottom:28px; }
      header h2 { font-size:2rem; margin-bottom:8px; }
      footer { text-align:center; color:#64748b; margin-top:40px; padding:24px 0 10px; font-size:.75rem; }
      @media (max-width:820px) { .top-bar { flex-direction:column; align-items:flex-start; gap:16px; } .actions { width:100%; } .actions a { flex:1; text-align:center; } }
    </style>
  </head>
  <body>
    <div class="layout-shell">
      <div class="top-bar">
        <div style="display:flex; align-items:center; gap:12px;">
          <button class="btn-back" onclick="location.href='index.php'" title="Back to developer dashboard">← Back</button>
          <div class="top-bar-title">
          <h1>Developer Dashboard</h1>
          <?php if (!empty($subTitle)): ?>
            <span class="top-subtitle"><?php echo htmlspecialchars($subTitle); ?></span>
          <?php endif; ?>
          </div>
        </div>
        <div class="actions">
          <a class="secondary" href="<?php echo htmlspecialchars(base_url('/pages/dashboard/')); ?>">Employee Portal</a>
          <a id="logoutLink" href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>">Logout</a>
        </div>
      </div>

      <main style="padding:24px; max-width:980px; margin:0 auto;">
        <?php
        // Load dummy users (prefer explicit is_dummy marker, fallback to +dev@example.test tag)
        $useIsDummy = false;
        $hasCreatedAt = false;
        $createdCol = 'created_at';
        try {
          $r = $conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'");
          if ($r && $r->num_rows > 0) $useIsDummy = true;
          $r2 = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
          if ($r2 && $r2->num_rows > 0) {
            $hasCreatedAt = true; $createdCol = 'created_at';
          } else {
            $r3 = $conn->query("SHOW COLUMNS FROM users LIKE 'created'");
            if ($r3 && $r3->num_rows > 0) { $hasCreatedAt = true; $createdCol = 'created'; }
          }
        } catch (Throwable $e) { $useIsDummy = false; $hasCreatedAt = false; }

        // Build safe select: if created column missing, alias an empty string so SQL doesn't fail
        $createdSelect = $hasCreatedAt ? $createdCol : "'' AS created_at";
        $orderBy = $hasCreatedAt ? "ORDER BY {$createdCol} DESC" : "ORDER BY id DESC";

        if ($useIsDummy) {
          $sql = "SELECT id,name,email,role,{$createdSelect} FROM users WHERE is_dummy = 1 {$orderBy} LIMIT 200";
          $stmt = $conn->prepare($sql);
          $stmt->execute();
          $res = $stmt->get_result();
        } else {
          $like = '%+dev@example.test';
          $sql = "SELECT id,name,email,role,{$createdSelect} FROM users WHERE email LIKE ? {$orderBy} LIMIT 200";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param('s', $like);
          $stmt->execute();
          $res = $stmt->get_result();
        }
        ?>

        <section class="card">
          <h3>Dummy Accounts</h3>
          <p>This page lists or seeds dummy accounts for development and testing. Use these accounts to exercise UI flows without real user data.</p>

          <div style="margin-top:14px; display:flex; gap:12px; flex-wrap:wrap;">
            <!-- Seed and bulk-delete removed; creation is per-role only -->
          </div>

          <div style="margin-top:18px; display:flex; gap:18px; flex-wrap:wrap; align-items:center;">
            <form id="createForm" style="display:flex; gap:8px; align-items:center;">
              <select name="role" style="padding:8px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.06); background:rgba(255,255,255,0.02); color:#e6eef8;">
                <option value="laborer">laborer</option>
                <option value="operator">operator</option>
                <option value="foreman">foreman</option>
                <option value="developer">developer</option>
              </select>
              <button type="submit" style="background:#10b981;color:#fff;padding:8px 12px;border-radius:8px;border:1px solid #10b981;font-weight:700;">Create Dummy</button>
            </form>
          </div>

          <div id="resultArea" style="margin-top:18px; color:#9aa8b8;"></div>

          <h4 style="margin-top:18px; color:#e6eef8;">Existing Dummy Accounts</h4>
          <div style="overflow:auto; max-height:320px; margin-top:10px;">
            <table style="width:100%; border-collapse:collapse; font-size:0.95rem;">
              <thead style="color:#94a3b8; text-align:left; font-size:0.9rem;">
                <tr><th style="padding:8px 6px;">Name</th><th style="padding:8px 6px;">Email</th><th style="padding:8px 6px;">Role</th><th style="padding:8px 6px;">Created</th><th style="padding:8px 6px;">Actions</th></tr>
              </thead>
              <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                  <tr style="border-top:1px solid rgba(255,255,255,0.04);">
                    <td style="padding:8px 6px; color:#e6eef8;"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td style="padding:8px 6px; color:#94a3b8;"><?php echo htmlspecialchars($row['email']); ?></td>
                    <td style="padding:8px 6px; color:#94a3b8;"><?php echo htmlspecialchars($row['role']); ?></td>
                    <td style="padding:8px 6px; color:#94a3b8;"><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td style="padding:8px 6px; color:#94a3b8;">
                      <button class="removeBtn" data-id="<?php echo htmlspecialchars($row['id']); ?>" style="background:#ef4444;color:#fff;padding:6px 10px;border-radius:6px;border:1px solid rgba(255,255,255,0.06);cursor:pointer;">Remove</button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>

      <footer>
    
        <p>• Maintained by Samip Kafle</p>
      </footer>
    </div>

    <script>
      (function(){
        const logoutLink = document.getElementById('logoutLink');
        if (!logoutLink) return;
        const overlay = document.createElement('div'); overlay.style.position='fixed'; overlay.style.inset='0'; overlay.style.background='rgba(2,6,23,0.65)'; overlay.style.backdropFilter='blur(4px)'; overlay.style.display='none'; overlay.style.alignItems='center'; overlay.style.justifyContent='center'; overlay.style.zIndex='1000';
        const modal = document.createElement('div'); modal.style.width='min(520px,92vw)'; modal.style.background='rgba(255,255,255,0.06)'; modal.style.border='1px solid rgba(255,255,255,0.12)'; modal.style.borderRadius='14px'; modal.style.padding='22px'; modal.style.color='#e2e8f0'; modal.style.boxShadow='0 20px 60px rgba(0,0,0,0.45)'; modal.style.backdropFilter='blur(8px)';
        const title = document.createElement('h3'); title.textContent='Confirm Logout'; title.style.margin='0 0 8px'; title.style.fontSize='1.2rem'; title.style.fontWeight='600';
        const desc = document.createElement('p'); desc.textContent='Are you sure you want to log out? You’ll need to sign in again to access developer resources.'; desc.style.margin='0 0 18px'; desc.style.color='#94a3b8'; desc.style.fontSize='.95rem';
        const btnRow = document.createElement('div'); btnRow.style.display='flex'; btnRow.style.justifyContent='flex-end'; btnRow.style.gap='10px';
        const cancelBtn = document.createElement('button'); cancelBtn.type='button'; cancelBtn.textContent='Cancel'; cancelBtn.style.padding='10px 18px'; cancelBtn.style.borderRadius='8px'; cancelBtn.style.border='1px solid rgba(255,255,255,0.15)'; cancelBtn.style.background='rgba(255,255,255,0.06)'; cancelBtn.style.color='#f1f5f9'; cancelBtn.style.cursor='pointer';
        const confirmBtn = document.createElement('a'); confirmBtn.textContent='Logout'; confirmBtn.href = logoutLink.getAttribute('href'); confirmBtn.style.textDecoration='none'; confirmBtn.style.padding='10px 18px'; confirmBtn.style.borderRadius='8px'; confirmBtn.style.fontWeight='700'; confirmBtn.style.background='#1d4ed8'; confirmBtn.style.border='1px solid #1d4ed8'; confirmBtn.style.color='#fff';
        btnRow.appendChild(cancelBtn); btnRow.appendChild(confirmBtn); modal.appendChild(title); modal.appendChild(desc); modal.appendChild(btnRow); overlay.appendChild(modal); document.body.appendChild(overlay);
        function openModal(){ overlay.style.display='flex'; }
        function closeModal(){ overlay.style.display='none'; }
        cancelBtn.addEventListener('click', closeModal); overlay.addEventListener('click', (e)=>{ if (e.target===overlay) closeModal(); });
        logoutLink.addEventListener('click', (e)=>{ e.preventDefault(); openModal(); });
      })();
    </script>
    <script>
      (function(){
        const createForm = document.getElementById('createForm');
        const resultArea = document.getElementById('resultArea');

        function show(msg, isError) {
          resultArea.style.color = isError ? '#fca5a5' : '#9aa8b8';
          resultArea.textContent = msg;
        }

        if (createForm) createForm.addEventListener('submit', function(e){
          e.preventDefault();
          const role = (createForm.querySelector('select[name="role"]')||{}).value || 'laborer';
          show('Creating...');
          fetch('create_dummy_account.php', { method: 'POST', body: new URLSearchParams({ role: role }) })
            .then(async r=>{
              const text = await r.text();
              try {
                const js = JSON.parse(text);
                return js;
              } catch (err) {
                console.error('Invalid JSON response from create_dummy_account.php', text);
                throw new Error('Invalid JSON: ' + text);
              }
            })
            .then(js=>{
              if (js.success) {
                show('Created: ' + js.email);
                createForm.reset();
                setTimeout(()=> location.reload(), 700);
              } else {
                show('Error: '+(js.error||'unknown'), true);
              }
            }).catch(e=> {
              console.error('Create request failed', e);
              show('Request failed — check console for server response', true);
            });
        });

        // attach per-row remove handlers
        document.querySelectorAll('.removeBtn').forEach(btn=>{
          btn.addEventListener('click', function(){
            const id = this.dataset.id;
            if (!id) return;
            if (!confirm('Remove this dummy account?')) return;
            show('Removing...');
            fetch('delete_dummy_account.php', { method: 'POST', body: new URLSearchParams({ id: id }) })
              .then(async r=>{
                const text = await r.text();
                try { return JSON.parse(text); } catch(err) { console.error('Invalid JSON from delete_dummy_account.php', text); throw new Error('Invalid JSON'); }
              })
              .then(js=>{
                if (js.success) {
                  show('Removed');
                  setTimeout(()=> location.reload(), 400);
                } else {
                  show('Error: '+(js.error||'unknown'), true);
                }
              }).catch(e=> {
                console.error('Delete request failed', e);
                show('Request failed — check console for server response', true);
              });
          });
        });
      })();
    </script>
  </body>
</html>
