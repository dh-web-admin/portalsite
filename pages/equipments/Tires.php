<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';

if ($role === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
}
$roleStmt->close();

$previewParam = isset($_GET['preview_role']) ? '?preview_role=' . urlencode($_GET['preview_role']) : '';

$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) {
    die("Invalid equipment ID.");
}

$fileStmt = $conn->prepare(
    "SELECT id, file_url, uploaded_at 
     FROM equipment_uploads 
     WHERE equipment_id=? AND field='tires' 
     ORDER BY uploaded_at DESC"
);
$fileStmt->bind_param('i', $equipment_id);
$fileStmt->execute();
$res = $fileStmt->get_result();
$uploads = $res->fetch_all(MYSQLI_ASSOC);
$fileStmt->close();

$fileList = [];
foreach ($uploads as $row) {
    if (!$row['file_url']) continue;

    $fileUrl = str_replace('\\', '/', $row['file_url']);
    $fileUrl = ltrim($fileUrl, '/');

    $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

    if ($isProduction) {
        $fileUrl = (strpos($fileUrl, 'uploads/equipment/') === 0)
            ? '/' . $fileUrl
            : '/uploads/equipment/' . $fileUrl;
    } else {
        if (strpos($fileUrl, 'PortalSite/uploads/equipment/') === 0) {
            $fileUrl = '/' . $fileUrl;
        } elseif (strpos($fileUrl, 'uploads/equipment/') === 0) {
            $fileUrl = '/PortalSite/' . $fileUrl;
        } else {
            $fileUrl = '/PortalSite/uploads/equipment/' . $fileUrl;
        }
    }

    $ext = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg']);

    $fileList[] = [
        'url' => $fileUrl,
        'name' => basename($fileUrl),
        'isImage' => $isImage,
        'uploaded_at' => $row['uploaded_at']
    ];
}

$fileCount = count($fileList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tires Uploads</title>

<link rel="stylesheet" href="../../assets/css/base.css">
<link rel="stylesheet" href="../../assets/css/admin-layout.css">
<link rel="stylesheet" href="../../assets/css/dashboard.css">

<style>
.file-list li.selected { background:#eef2ff; }
.equipment-btn--secondary {
    padding: 10px 28px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    background: #f3f4f6;
    color: #6b7280;
    border: none;
    text-decoration: none;
    display: inline-block;
    margin: 18px 0 0 0;
    transition: background 0.2s;
}
.equipment-btn--secondary:hover {
    background: #e5e7eb !important;
    color: #374151 !important;
    text-decoration: none !important;
}
.download-print-btn {
    padding: 10px 22px 10px 16px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    border: none;
    background: #667eea;
    color: #fff;
    margin-right: 18px;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.1s;
    box-shadow: 0 2px 8px #0001;
    outline: none;
    text-align: center;
    min-width: 140px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.download-print-btn:last-child {
    margin-right: 0;
}
.download-print-btn .icon {
    font-size: 20px;
    display: inline-block;
    vertical-align: middle;
    transition: color 0.18s;
}
.download-print-btn:active, .download-print-btn.active {
    background: #667eea !important;
    color: #fff !important;
    box-shadow: 0 2px 8px #0001;
}
.download-print-btn:active .icon, .download-print-btn.active .icon {
    color: #fff !important;
    stroke: #fff !important;
}
.download-print-btn:hover, .download-print-btn:focus {
    background: #f3f4f6 !important;
    color: #3b4cca !important;
    box-shadow: 0 4px 16px #0002;
    transform: translateY(-2px) scale(1.04);
    text-decoration: none;
}
.download-print-btn:hover .icon, .download-print-btn:focus .icon {
    color: #3b4cca !important;
    stroke: #3b4cca !important;
}
</style>
</head>

<body class="admin-page">
<div class="admin-container">
<?php include __DIR__ . '/../../partials/portalheader.php'; ?>

<div class="admin-layout">
<?php include __DIR__ . '/../../partials/sidebar.php'; ?>

<main class="content-area">
<div style="display:flex;flex-direction:row;gap:40px;align-items:flex-start;min-height:480px;width:100%;">

<!-- LEFT -->
<div style="flex:2 1 0;min-width:400px;max-width:60vw;">
<a href="index.php<?= $previewParam ?>" class="equipment-btn equipment-btn--secondary">&larr; Back to Equipments</a>

<div style="background:#e0e7ff;padding:16px 24px;border-radius:12px;font-weight:600;font-size:1.2rem;color:#374151;margin-bottom:18px;">
<?= $fileCount ?> item<?= $fileCount !== 1 ? 's' : '' ?> available for equipment #<?= $equipment_id ?>
</div>

<button id="uploadFilterBtn" class="download-print-btn" style="margin-bottom:12px;width:180px;">
    <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
    </span>
    <span>Upload</span>
</button>
<input type="file" id="filterFileInput" multiple style="display:none;" />

<ul id="filterFileList" class="file-list" style="list-style:none;padding:0;">
<?php if ($fileCount): foreach ($fileList as $file): ?>
<li class="filter-file-item" data-file-url="<?= htmlspecialchars($file['url']) ?>">
📄 <?= htmlspecialchars($file['name']) ?>
</li>
<?php endforeach; else: ?>
<li style="color:#94a3b8;font-style:italic;">No tire files uploaded yet.</li>
<?php endif; ?>
</ul>
</div>

<!-- RIGHT -->
<div id="filterPreviewPanel" style="flex:3 1 0;min-width:320px;max-width:75vw;background:#f8fafc;border-radius:14px;box-shadow:0 2px 8px #0001;padding:32px 18px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;min-height:520px;">
    <div style="width:100%;text-align:center;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
        <span id="filterPreviewCountMsg" style="color:#374151;font-weight:600;font-size:1.1rem;"></span>
        <div>
            <button id="downloadFilterBtn" class="download-print-btn" style="margin-right:18px;">
                <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                </span>
                <span>Download Original File</span>
            </button>
            <button id="printFilterBtn" class="download-print-btn">
                <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="9" width="12" height="7" rx="2"/><path d="M6 17v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2"/><polyline points="6 9 6 4 18 4 18 9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
                </span>
                <span>Print</span>
            </button>
        </div>
    </div>
    <div id="filterPreviewWindow" style="width:100%;min-height:320px;max-height:480px;overflow-y:auto;display:flex;flex-direction:column;gap:18px;align-items:center;justify-content:flex-start;background:#fff;border-radius:10px;box-shadow:0 1px 4px #0001;margin-bottom:18px;padding:18px 0;text-align:center;">
        <span class="no-image">No file selected</span>
    </div>
</div>

</div>
</main>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
const list = document.getElementById('filterFileList');
const preview = document.getElementById('filterPreviewWindow');
const title = document.getElementById('filterPreviewCountMsg');
let selectedUrl = null;
let selectedName = null;

function showPreview(url, name) {
    preview.innerHTML = '';
    title.textContent = name || '';
    if (!url) return;

    const ext = url.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif','bmp','webp','svg'].includes(ext)) {
        const img = new Image();
        img.src = url;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '420px';
        img.onerror = () => preview.textContent = 'Image could not be loaded.';
        preview.appendChild(img);
    } else {
        preview.innerHTML = `<a href="${url}" target="_blank">Open file</a>`;
    }
}

list.addEventListener('click', e => {
    const li = e.target.closest('.filter-file-item');
    if (!li) return;
    list.querySelectorAll('li').forEach(l => l.classList.remove('selected'));
    li.classList.add('selected');
    selectedUrl = li.dataset.fileUrl;
    selectedName = li.textContent.trim();
    showPreview(selectedUrl, selectedName);
});

document.getElementById('downloadFilterBtn').onclick = () => {
    if (!selectedUrl) return;
    const a = document.createElement('a');
    a.href = selectedUrl;
    a.download = selectedName;
    a.click();
};

document.getElementById('printFilterBtn').onclick = () => {
    if (!selectedUrl) return;
    const w = window.open(selectedUrl);
    w.onload = () => w.print();
};
});
</script>

<script src="../../assets/js/mobile-menu.js"></script>
<script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
