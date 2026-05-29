<?php require_once __DIR__ . '/auth_check.php'; ?>
<?php require_once __DIR__ . '/../partials/url.php'; ?>
<?php $subTitle = 'Getting Started'; ?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>Getting Started</title>
		<style>
			* { margin: 0; padding: 0; box-sizing: border-box; }
			body {
				font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
				background: radial-gradient(circle at 25% 25%, #1e293b 0%, #0f172a 70%);
				min-height: 100vh; padding: 0 20px 60px; color: #e2e8f0;
			}
			.layout-shell { max-width: 1300px; margin: 0 auto; }
			.top-bar { display: flex; align-items: center; justify-content: space-between; padding: 18px 28px; background: rgba(255,255,255,0.04); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; margin: 25px auto 35px; }
			.top-bar h1 { font-size: 1.4rem; font-weight: 600; letter-spacing: .5px; color: #f1f5f9; }
			.top-bar-title { display:flex; flex-direction:column; gap:6px; }
			.top-subtitle { color: #94a3b8; font-size: 0.9rem; }
				.btn-back { padding:6px 10px; border-radius:8px; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); color:#e6eef8; font-weight:600; cursor:pointer; margin-right:12px; }
				.btn-back:hover { background: rgba(255,255,255,0.04); }
			/* Getting Started content styles */
			.card {
				background: rgba(255,255,255,0.03);
				border: 1px solid rgba(255,255,255,0.06);
				border-radius: 12px;
				padding: 22px;
				color: #e6eef8;
				box-shadow: 0 8px 30px rgba(2,6,23,0.6);
			}
			.card h3 { font-size: 1.25rem; margin-bottom: 10px; }
			.card p { color: #9aa8b8; margin-bottom: 12px; }
			/* Use plain bullets instead of icon boxes */
			.checklist { list-style: disc; padding-left: 20px; margin: 0; }
			.checklist li { padding: 8px 0; border-bottom: 1px dashed rgba(255,255,255,0.03); display:block; }
			/* Styled numbered steps */
			.steps { list-style: none; padding-left: 0; margin: 0; }
			.steps li { counter-increment: step; position: relative; padding: 18px 0 18px 72px; border-bottom: 1px dashed rgba(255,255,255,0.03); }
			.steps li:last-child { border-bottom: none; }
			.steps li::before { content: counter(step); counter-reset: none; position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)); display:flex; align-items:center; justify-content:center; color:#e6eef8; font-weight:700; font-size:1rem; border:1px solid rgba(255,255,255,0.04); box-shadow: 0 6px 20px rgba(2,6,23,0.45); }
			.steps .note { color:#9aa8b8; margin-top:8px; }
			.note { color:#9aa8b8; margin-top:8px; }
			code { background: rgba(255,255,255,0.02); padding:4px 8px; border-radius:6px; color:#cbd5e1; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, 'Roboto Mono', 'Courier New', monospace; }
			.check-icon { display: none; }
			.sub-items { margin:6px 0 0 0; padding-left: 18px; color:#9aa8b8; }
			.card a { color: #60a5fa; text-decoration: underline; }
			.card a:hover { color: #93c5fd; }
			.actions { display: flex; gap: 12px; }
			.actions a { text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: .9rem; font-weight: 600; letter-spacing: .4px; background: #1d4ed8; color: #fff; border: 1px solid #1d4ed8; }
			.actions a.secondary { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #f1f5f9; }
			header { text-align: center; color: #f1f5f9; margin-bottom: 38px; }
			header h2 { font-size: 2.4rem; margin-bottom: 12px; font-weight: 600; }
			header p { font-size: 1.05rem; opacity: .75; max-width: 780px; margin: 0 auto; line-height: 1.55; }
			footer { text-align: center; color: #64748b; margin-top: 60px; padding: 24px 0 10px; font-size: .75rem; }
			@media (max-width: 820px) { .top-bar { flex-direction: column; align-items: flex-start; gap: 16px; } .actions { width:100%; } .actions a { flex:1; text-align:center; } header h2 { font-size:1.9rem; } }
		</style>
	</head>
	<body>
		<div class="layout-shell">
			<div class="top-bar">
				<div style="display:flex; align-items:center; gap:12px;">
					<button class="btn-back" onclick="location.href='index.php'" title="Back to developer dashboard">← Back</button>
					<div class="top-bar-title">
					<h3>Developer Dashboard</h3>
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
			<?php if (!empty($_SESSION['flash'] ?? '')): ?>
				<div style="max-width:980px; margin:12px auto 0; padding:10px 16px; background:rgba(16,185,129,0.07); border:1px solid rgba(16,185,129,0.12); color:#a7f3d0; border-radius:8px;"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
			<?php endif; ?>
        
			<main style="padding:24px; max-width:980px; margin:0 auto;">
				<section class="card">
					<h3>Pre-req — download necessary software and languages</h3>
					<ul class="checklist">
						<li><span class="check-icon" aria-hidden="true"></span><div><strong>XAMPP</strong><div class="sub-items">Install XAMPP (Apache, MySQL) and ensure services run. <br> <a href="https://www.apachefriends.org/download.html" target="_blank" rel="noopener noreferrer">Click to download XAMPP</a></div></div></li>
						<li><span class="check-icon" aria-hidden="true"></span><div><strong>Code editor</strong><div class="sub-items">Recommended: VS Code </div></div></li>
					<li><span class="check-icon" aria-hidden="true"></span><div><strong>Github and its Tools</strong><div class="sub-items">Recommended: Install and login to Github Desktop and Github Credential Manager  </div></div></li>
					</ul>
				</section>
					<section class="card" style="margin-top:18px;">
						<h3>Code and Production Access</h3>
						<p class="muted">Request repository access.</p>
						<form id="githubAccessForm" method="post" action="<?php echo htmlspecialchars(base_url('/dev/request_github_access.php')); ?>" style="margin-top:8px;">
							<label for="github_username" style="display:block; font-weight:600; margin-bottom:6px;">GitHub username</label>
							<input id="github_username" name="github_username" type="text" placeholder="your-github-username" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.06); background:rgba(255,255,255,0.02); color:#e6eef8; margin-bottom:12px;"> 
							<div style="margin-top:14px;">
								<strong style="display:block; margin-bottom:8px;">Railway access</strong>
								<p class="muted" style="margin-bottom:10px;">DH uses Railway to deploy our projects online. If you don't have a Railway account already, go to <a href="https://railway.app" target="_blank" rel="noopener noreferrer">railway.com</a> and create an account. Once you've done that enter your email address below:</p>
								<input id="railway_email" name="railway_email" type="email" placeholder="your@email.com" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.06); background:rgba(255,255,255,0.02); color:#e6eef8;">
							</div>
							<div style="text-align:left; margin-top:20px;">
								<button type="submit" style="background:#1d4ed8; color:#fff; padding:10px 16px; border-radius:8px; border:1px solid #1d4ed8; font-weight:700; cursor:pointer;">Submit Request</button>
							</div>
						</form>
					</section>
					<section class="card" style="margin-top:18px;">
							<h3>Setting up project folder</h3>
							<p class="muted">Steps to create and prepare your local project folder</p>
							<ol class="steps">
								<li><div>Open File Explorer and navigate to the directory where you installed XAMPP. Then go to <code>htdocs</code> and create a new folder named <strong>PortalSite</strong>.</div></li>
								<li><div>Open GitHub Desktop and click <em>Clone a repository on your local drive</em>, then select <strong>dh-web-admin/portalsite</strong>.</div></li>
								<li><div>Set the local path to the <strong>PortalSite</strong> folder. Eg: <code>C:\xampp\htdocs\PortalSite</code></div></li>
								<li><div>Click <em>Clone</em>.</div></li>
							</ol>
							<div class="note"><div>You can also clone from the command line: <a href="https://github.com/dh-web-admin/portalsite" target="_blank" rel="noopener noreferrer">https://github.com/dh-web-admin/portalsite</a></div></div>
						</section>
						<section class="card" style="margin-top:18px;">
							<h3>Task Queue</h3>
							<p class="muted">Overview of background tasks and the current processing queue for development. Use this to inspect pending jobs and retry or cancel tasks while testing integrations.</p>
							<ul style="margin-top:12px; color:#9aa8b8;">
								<li>View pending tasks and their payloads</li>
								<li>Retry or cancel individual jobs</li>
								<li>Filter by task type (emails, imports, exports)</li>
							</ul>
							<div style="margin-top:12px;">
								<a class="actions" href="task_queue.php" style="text-decoration:none;"><span class="secondary">Open Task Queue</span></a>
							</div>
						</section>

			</main>

			<footer>
				<p>Developer Documentation • Internal Use Only</p>
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
	</body>
</html>

