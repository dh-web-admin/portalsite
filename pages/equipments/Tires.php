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
$fileStmt = $conn->prepare("SELECT id, file_url, uploaded_at FROM equipment_uploads WHERE equipment_id=? AND field='tires' ORDER BY uploaded_at DESC");
$fileStmt->bind_param('i', $equipment_id);
if (!$fileStmt->execute()) {
    die("SQL execute error: " . $fileStmt->error);
}
$res = $fileStmt->get_result();
if (!$res) {
    die("SQL get_result error: " . $fileStmt->error);
}
$uploads = $res->fetch_all(MYSQLI_ASSOC);
$fileStmt->close();
$fileList = [];
$fileList = [];
foreach ($uploads as $row) {
    $fileUrl = $row['file_url'];
    if (!$fileUrl) continue;
    $fileUrl = str_replace('\\', '/', $fileUrl);
    $fileUrl = ltrim($fileUrl, '/');
    $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
    if ($isProduction) {
        if (strpos($fileUrl, 'uploads/equipment/') === 0) {
            $fileUrl = '/' . $fileUrl;
        } else {
            $fileUrl = '/uploads/equipment/' . $fileUrl;
        }
    } else {
        if (strpos($fileUrl, 'PortalSite/uploads/equipment/') === 0) {
            $fileUrl = '/' . $fileUrl;
        } else if (strpos($fileUrl, 'uploads/equipment/') === 0) {
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
        'uploaded_at' => $row['uploaded_at'],
        'id' => $row['id']
    ];
}
$fileCount = count($fileList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Tires Uploads</title>
<link rel="stylesheet" href="../../assets/css/base.css" />
<link rel="stylesheet" href="../../assets/css/admin-layout.css" />
<link rel="stylesheet" href="../../assets/css/dashboard.css" />
<style>
.file-list li.selected { background:#eef2ff; }
</style>
<!-- Button and preview stylings from Airfilters.php -->
<style>
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
                <div style="flex:2 1 0;min-width:400px;max-width:60vw;">
                    <a href="index.php<?php echo $previewParam; ?>" class="equipment-btn equipment-btn--secondary" style="padding: 10px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; background: #f3f4f6; color: #6b7280; border: none; text-decoration: none; display: inline-block; margin-bottom:18px; transition: background 0.2s;">&larr; Back to Equipments</a>
                    <div style="background:#e0e7ff;padding:16px 24px;border-radius:12px;font-weight:600;font-size:1.2rem;color:#374151;margin-bottom:18px;">
                        <?php echo $fileCount; ?> item<?php echo $fileCount !== 1 ? 's' : ''; ?> available for equipment #<?php echo $equipment_id; ?>
                    </div>
                    <button id="uploadFilterBtn" class="download-print-btn" style="margin-bottom:12px;">
                        <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                        </span>
                        <span>Upload</span>
                    </button>
                    <input type="file" id="filterFileInput" multiple style="display:none;" />
                    <ul id="filterFileList" class="file-list" style="list-style:none;padding:0;margin:0;min-height:40px;">
                        <?php if ($fileCount > 0): ?>
                            <?php foreach ($fileList as $file): ?>
                                <li class="filter-file-item" data-file-url="<?php echo htmlspecialchars($file['url']); ?>" data-file-id="<?php echo $file['id']; ?>" style="padding:12px 0;border-bottom:1px solid #f1f1f1;cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
                                    <span>📄 <?php echo htmlspecialchars($file['name']); ?></span>
                                    <button class="delete-upload-btn" style="margin-left:18px;padding:4px 12px;border-radius:6px;background:#f87171;color:#fff;border:none;font-size:13px;cursor:pointer;">Delete</button>
                                </li>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="color:#94a3b8;font-style:italic;">No tire files uploaded yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
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
                    <div id="filterPreviewWindow" style="width:100%;min-height:320px;max-height:480px;overflow-y:auto;display:flex;flex-direction:column;gap:18px;align-items:center;justify-content:flex-start;background:#fff;border-radius:10px;box-shadow:0 1px 4px #0001;margin-bottom:18px;padding:18px 0;">
                        <span class="no-image">No file selected</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileList = document.getElementById('filterFileList');
    var previewWindow = document.getElementById('filterPreviewWindow');
    var previewCountMsg = document.getElementById('filterPreviewCountMsg');
    var uploadBtn = document.getElementById('uploadFilterBtn');
    var fileInput = document.getElementById('filterFileInput');
    var selectedFileUrl = null;
    var selectedFileName = null;
    var equipmentId = <?php echo json_encode($equipment_id); ?>;
    function showPreview(url, name) {
        previewWindow.innerHTML = '';
        if (!url) {
            previewWindow.innerHTML = '<span class="no-image">No file selected</span>';
            previewCountMsg.textContent = '';
            return;
        }
        var ext = url.split('.').pop().toLowerCase();
        var isImage = ['jpg','jpeg','png','gif','bmp','webp','svg'].includes(ext);
        previewCountMsg.textContent = name;
        if (isImage) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = name;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '420px';
            img.style.objectFit = 'contain';
            img.style.borderRadius = '12px';
            img.style.display = 'block';
            img.onerror = function() { previewWindow.innerHTML += '<span style="color:#b91c1c;">Image could not be loaded.</span>'; };
            previewWindow.appendChild(img);
        } else {
            var link = document.createElement('a');
            link.href = url;
            link.textContent = 'Download/View File';
            link.target = '_blank';
            previewWindow.appendChild(link);
        }
    }
    fileList.addEventListener('click', function(e) {
        var item = e.target.closest('.filter-file-item');
        if (!item) return;
        // Handle delete button
        if (e.target.classList.contains('delete-upload-btn')) {
            var fileId = item.getAttribute('data-file-id');
            var fileName = item.querySelector('span').textContent.trim();
            if (confirm('Are you sure you want to delete "' + fileName + '"? This cannot be undone.')) {
                fetch('/PortalSite/api/delete_equipment_upload.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(fileId)
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        item.remove();
                        previewWindow.innerHTML = '<span class="no-image">No file selected</span>';
                        previewCountMsg.textContent = '';
                    } else {
                        alert('Delete failed: ' + (res.error || 'Unknown error'));
                    }
                });
            }
            return;
        }
        // Preview logic
        fileList.querySelectorAll('.filter-file-item').forEach(i => i.classList.remove('selected'));
        item.classList.add('selected');
        selectedFileUrl = item.getAttribute('data-file-url');
        selectedFileName = item.textContent.trim();
        showPreview(selectedFileUrl, selectedFileName);
    });
    uploadBtn.addEventListener('click', function() { fileInput.click(); });
    fileInput.addEventListener('change', function() {
        if (!equipmentId || !fileInput.files.length) return;
        var files = Array.from(fileInput.files);
        var uploads = files.map(file => {
            var formData = new FormData();
            formData.append('equipment_id', equipmentId);
            formData.append('file', file);
            formData.append('field', 'tires');
            return fetch('/PortalSite/api/add_equipment_upload.php', { method:'POST', body:formData }).then(r => r.json());
        });
        Promise.all(uploads).then(results => {
            var success = results.filter(r => r && r.success).length;
            var fail = results.length - success;
            alert(success + ' uploaded.' + (fail > 0 ? ' ' + fail + ' failed.' : ''));
            location.reload();
        });
    });
    document.getElementById('downloadFilterBtn').addEventListener('click', function() {
        if (!selectedFileUrl) return;
        var a = document.createElement('a');
        a.href = selectedFileUrl;
        a.download = selectedFileName || 'file';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
    document.getElementById('printFilterBtn').addEventListener('click', function() {
        if (!selectedFileUrl) return;
        var ext = selectedFileUrl.split('.').pop().toLowerCase();
        var isImage = ['jpg','jpeg','png','gif','bmp','webp','svg'].includes(ext);
        var isDoc = ['pdf','txt','doc','docx','xls','xlsx','csv'].includes(ext);
        var printWindow = window.open('', '', 'width=900,height=700');
        if (isImage) {
            printWindow.document.write('<html><head><title>Print</title></head><body style="margin:0;padding:0;"><img src="' + selectedFileUrl + '" style="max-width:100vw;max-height:100vh;display:block;margin:auto;" /></body></html>');
        } else if (isDoc) {
            if (ext === 'pdf') {
                printWindow.document.write('<html><head><title>Print</title></head><body style="margin:0;padding:0;"><embed src="' + selectedFileUrl + '" type="application/pdf" width="100%" height="100%"></embed></body></html>');
            } else {
                printWindow.document.write('<html><head><title>Print</title></head><body style="margin:0;padding:0;"><iframe src="' + selectedFileUrl + '" style="width:100vw;height:100vh;border:none;"></iframe></body></html>');
            }
        } else {
            printWindow.document.write('<html><head><title>Print</title></head><body><p>Cannot print this file type.</p></body></html>');
        }
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function(){ printWindow.print(); printWindow.close(); }, 600);
    });
});
</script>
<script src="../../assets/js/mobile-menu.js"></script>
<script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
