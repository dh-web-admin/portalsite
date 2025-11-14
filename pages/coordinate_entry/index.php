<?php
// Coordinate Entry redirect with portal header (includes developer notch)
// Shows the portal header/dev notch briefly before client-side redirecting to maps.php
require_once __DIR__ . '/../../session_init.php';

// Preserve developer preview param if present
$preview = isset($_GET['preview_role']) ? '?preview_role=' . urlencode($_GET['preview_role']) : '';
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />
		<title>Coordinate Entry — Redirecting</title>
		<style>body{font-family:Inter,system-ui,Arial;margin:18px;background:#f6f8fb;color:#0f172a} .note{padding:12px;background:#fff;border:1px solid #e6eef4;border-radius:8px;max-width:760px}</style>
	</head>
	<body>
		<?php include __DIR__ . '/../../partials/portalheader.php'; ?>
		<main style="padding:18px;">
			<div class="note">
				<h2>Coordinate Entry</h2>
				<p>Redirecting you to the Coordinate Entry table — if you are not redirected automatically, <a href="../maps/maps.php<?php echo $preview; ?>">click here</a>.</p>
			</div>
		</main>
		<script>
			// short client-side redirect to allow portal header (and dev notch) to render
			setTimeout(function(){ window.location.href = '../maps/maps.php<?php echo $preview; ?>'; }, 400);
		</script>
	</body>
</html>
