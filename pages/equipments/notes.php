<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

if (!can_access($role, 'equipments')) {
    header('Location: /pages/dashboard/');
    exit();
}

// Get equipment ID early for redirects
$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Create tables if not exists
$conn->query("CREATE TABLE IF NOT EXISTS equipment_notes (id INT AUTO_INCREMENT PRIMARY KEY, equipment_id INT NOT NULL, item_text TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS note_attachments (id INT AUTO_INCREMENT PRIMARY KEY, note_id INT NOT NULL, equipment_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL DEFAULT '', size_bytes INT NOT NULL DEFAULT 0, uploaded_by INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Upload directory (same pattern as equipment uploads)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$uploads_mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
if ($isProduction) {
    $noteUploadDir = rtrim($uploads_mount, '/') . '/equipment/';
} else {
    $noteUploadDir = __DIR__ . '/../../uploads/equipment/';
}

// Handle attachment delete BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attachment_id'])) {
    $attId = (int)$_POST['delete_attachment_id'];
    if ($attId > 0) {
        $stmt = $conn->prepare('SELECT filename FROM note_attachments WHERE id = ?');
        $stmt->bind_param('i', $attId);
        $stmt->execute();
        $attRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($attRow) {
            $filePath = rtrim($noteUploadDir, '/') . '/' . basename($attRow['filename']);
            if (file_exists($filePath)) @unlink($filePath);
            $stmt = $conn->prepare('DELETE FROM note_attachments WHERE id = ?');
            $stmt->bind_param('i', $attId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: notes.php?id=' . $equipmentId . '&deleted=1');
    exit();
}

// Handle note delete BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    if ($deleteId > 0) {
        // Delete associated attachments from disk first
        $aRes = $conn->prepare('SELECT filename FROM note_attachments WHERE note_id = ?');
        $aRes->bind_param('i', $deleteId);
        $aRes->execute();
        $aRows = $aRes->get_result();
        while ($aRow = $aRows->fetch_assoc()) {
            $fp = rtrim($noteUploadDir, '/') . '/' . basename($aRow['filename']);
            if (file_exists($fp)) @unlink($fp);
        }
        $aRes->close();
        $conn->prepare('DELETE FROM note_attachments WHERE note_id = ?')->execute();
        $stmt = $conn->prepare('DELETE FROM note_attachments WHERE note_id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM equipment_notes WHERE id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();
        header('Location: notes.php?id=' . $equipmentId . '&deleted=1');
        exit();
    }
}

// Handle note edit BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['edit_text'])) {
    $editId = (int)$_POST['edit_id'];
    $editText = trim($_POST['edit_text']);
    if ($editId > 0 && $editText !== '') {
        $stmt = $conn->prepare('UPDATE equipment_notes SET item_text = ? WHERE id = ?');
        $stmt->bind_param('si', $editText, $editId);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: notes.php?id=' . $equipmentId . '&updated=1');
    exit();
}

// Handle add BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_text'], $_POST['equipment_id'])) {
    $txt = trim($_POST['item_text']);
    $eid = (int)$_POST['equipment_id'];
    if ($txt !== '' && $eid > 0) {
        $stmt = $conn->prepare('INSERT INTO equipment_notes (equipment_id, item_text) VALUES (?, ?)');
        $stmt->bind_param('is', $eid, $txt);
        $stmt->execute();
        $noteId = (int)$conn->insert_id;
        $stmt->close();

        // Handle file uploads
        if ($noteId > 0 && isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            if (!is_dir($noteUploadDir)) @mkdir($noteUploadDir, 0777, true);
            $uploadedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $origName = basename($_FILES['attachments']['name'][$i]);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $safeBase = 'note_' . $noteId . '_' . uniqid() . '.' . $ext;
                $safeBase = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $safeBase);
                $targetPath = rtrim($noteUploadDir, '/') . '/' . $safeBase;
                if (is_uploaded_file($_FILES['attachments']['tmp_name'][$i]) && move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $targetPath)) {
                    @chmod($targetPath, 0644);
                    $mimeType = $_FILES['attachments']['type'][$i];
                    $sizeBytes = (int)$_FILES['attachments']['size'][$i];
                    $ins = $conn->prepare('INSERT INTO note_attachments (note_id, equipment_id, filename, original_name, mime_type, size_bytes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $ins->bind_param('iisssii', $noteId, $eid, $safeBase, $origName, $mimeType, $sizeBytes, $uploadedBy);
                    $ins->execute();
                    $ins->close();
                }
            }
        }

        header('Location: notes.php?id=' . $eid . '&added=1');
        exit();
    }
}

// Check for success messages from redirect
$showSuccess = isset($_GET['added']) && $_GET['added'] == '1';
$showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';
$showUpdated = isset($_GET['updated']) && $_GET['updated'] == '1';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Equipment Notes</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
        .notes-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
        }

        .notes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .notes-title {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .notes-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        .add-item-btn {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .add-item-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }

        .add-item-btn:active {
            transform: translateY(0);
        }

        .equipment-back-btn-wrapper--top-left { margin-top: 18px; margin-bottom: 18px; }
        .equipment-back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: background 0.2s ease, transform 0.1s ease; }
        .equipment-back-btn:hover { background: #1d4ed8; }
        .equipment-back-btn:active { transform: scale(0.98); }

        .notes-form {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            resize: vertical;
            transition: all 0.2s ease;
            background: white;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .btn-save {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-save:hover {
            background: #1d4ed8;
        }

        .btn-cancel {
            background: white;
            color: #64748b;
            border: 2px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            border-left: 4px solid #10b981;
            font-size: 14px;
            font-weight: 500;
        }

        .delete-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            border-left: 4px solid #ef4444;
            font-size: 14px;
            font-weight: 500;
        }

        .notes-items {
            display: grid;
            gap: 16px;
        }

        .notes-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .notes-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .item-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding-right: 30px;
        }

        .item-number {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.2);
        }

        .item-content {
            flex: 1;
            min-width: 0;
        }

        .item-text {
            color: #1e293b;
            font-size: 15px;
            line-height: 1.6;
            white-space: pre-line;
            word-wrap: break-word;
        }

        .btn-delete-x {
            position: absolute;
            top: 16px;
            right: 16px;
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            transition: all 0.2s ease;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .btn-delete-x:hover {
            color: #ef4444;
            background: #fef2f2;
        }

        .btn-edit-pencil {
            position: absolute;
            top: 16px;
            right: 44px;
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 15px;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            transition: all 0.2s ease;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .btn-edit-pencil:hover {
            color: #2563eb;
            background: #eff6ff;
        }

        .inline-edit-form {
            display: none;
            margin-top: 10px;
        }

        .inline-edit-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #2563eb;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            resize: vertical;
            background: #fff;
            box-sizing: border-box;
        }

        .inline-edit-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .attachments-list {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .attachment-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            background: #f0f4ff;
            border: 1px solid #c7d7fc;
            border-radius: 6px;
            font-size: 13px;
            color: #1e40af;
            text-decoration: none;
            transition: background 0.15s;
        }

        .attachment-chip:hover {
            background: #dbeafe;
        }

        .attachment-chip .att-icon {
            font-size: 15px;
            flex-shrink: 0;
        }

        .attachment-chip .att-name {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-delete-att {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 14px;
            cursor: pointer;
            padding: 0 2px;
            line-height: 1;
            transition: color 0.15s;
            flex-shrink: 0;
        }

        .btn-delete-att:hover {
            color: #ef4444;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #f8fafc;
            border: 1.5px dashed #94a3b8;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #475569;
            transition: border-color 0.15s, background 0.15s;
            margin-top: 10px;
        }

        .file-input-label:hover {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }

        .file-input-label input[type=file] {
            display: none;
        }

        .selected-files-preview {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .selected-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 5px;
            font-size: 12px;
            color: #166534;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px dashed #cbd5e1;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        .empty-description {
            font-size: 14px;
            color: #94a3b8;
        }

        .equipment-chip {
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            background: #f8fafc;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 6px 18px rgba(2, 6, 23, 0.05);
            color: #0f172a;
            transition: all 0.15s ease;
        }

        .equipment-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(2, 6, 23, 0.08);
        }

        .equipment-chip.is-selected {
            background: #2563eb;
            color: #fff;
            border-color: #1e40af;
            transform: translateY(-6px);
            box-shadow: 0 14px 34px rgba(37, 99, 235, 0.22);
        }

        #equipmentRibbon {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            bottom: 18px;
            z-index: 999;
            background: rgba(255, 255, 255, 0.96);
            padding: 8px 12px;
            border-radius: 999px;
            box-shadow: 0 6px 20px rgba(2, 6, 23, 0.08);
            display: flex;
            gap: 8px;
            align-items: center;
            max-width: 95%;
            overflow: auto;
            backdrop-filter: blur(10px);
        }

        @media (max-width: 768px) {
            .notes-container {
                padding: 16px;
            }

            .notes-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .notes-title {
                font-size: 24px;
            }

            .item-number {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <?php
                // Fetch all equipments for the ribbon selector
                $allEquipments = [];
                $eqRes = $conn->query("SELECT equipment_id, COALESCE(dhss_equipment_number, '') AS number FROM equipments ORDER BY equipment_id ASC");
                if ($eqRes) {
                    while ($row = $eqRes->fetch_assoc()) {
                        $allEquipments[] = $row;
                    }
                    $eqRes->free();
                }
                // If no id, pick first
                if ($equipmentId <= 0 && count($allEquipments) > 0) {
                    $equipmentId = (int)$allEquipments[0]['equipment_id'];
                }
                $currentEqNum = '';
                foreach ($allEquipments as $eq) {
                    if ((int)$eq['equipment_id'] === $equipmentId) $currentEqNum = $eq['number'];
                }

                // Fetch notes
                $items = [];
                if ($equipmentId > 0) {
                    $stmt = $conn->prepare('SELECT id, item_text FROM equipment_notes WHERE equipment_id = ? ORDER BY id ASC');
                    $stmt->bind_param('i', $equipmentId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) $items[] = $row;
                    $stmt->close();
                }
                ?>

                <div class="notes-container">
                    <div class="equipment-back-btn-wrapper equipment-back-btn-wrapper--top-left" style="text-align:left;">
                        <a id="backBtn" href="equipment.php?id=<?php echo $equipmentId; ?>" class="equipment-back-btn"><span>Back ← </span></a>
                    </div>
                    <div class="notes-header">
                        <div>
                            <h1 class="notes-title">Equipment Notes</h1>
                            <p class="notes-subtitle">Equipment #<?php echo htmlspecialchars($currentEqNum ?: $equipmentId); ?></p>
                        </div>
                        <button id="addNoteBtn" class="add-item-btn">+ Add Note</button>
                    </div>

                    <?php if ($showSuccess) { ?>
                        <div class="success-message">✓ Note added successfully!</div>
                    <?php } ?>

                    <?php if ($showDeleted) { ?>
                        <div class="delete-message">✓ Note deleted successfully!</div>
                    <?php } ?>

                    <?php if ($showUpdated) { ?>
                        <div class="success-message">✓ Note updated successfully!</div>
                    <?php } ?>

                    <form id="notesForm" method="POST" enctype="multipart/form-data" class="notes-form" style="display:none;">
                        <label class="form-label">New Note</label>
                        <textarea name="item_text" id="item_text" rows="3" class="form-textarea" placeholder="Enter your note here..." required></textarea>
                        <input type="hidden" name="equipment_id" value="<?php echo (int)$equipmentId; ?>">
                        <label class="file-input-label">
                            📎 Attach files
                            <input type="file" name="attachments[]" id="noteAttachments" multiple />
                        </label>
                        <div class="selected-files-preview" id="selectedFilesPreview"></div>
                        <div class="form-actions">
                            <button type="submit" class="btn-save">Save Note</button>
                            <button type="button" id="cancelNoteBtn" class="btn-cancel">Cancel</button>
                        </div>
                    </form>

                    <div class="notes-items">
                        <?php
                        // Fetch all attachments for this equipment's notes in one query
                        $attachmentsByNote = [];
                        if ($equipmentId > 0) {
                            $aStmt = $conn->prepare('SELECT id, note_id, filename, original_name, mime_type FROM note_attachments WHERE equipment_id = ? ORDER BY id ASC');
                            $aStmt->bind_param('i', $equipmentId);
                            $aStmt->execute();
                            $aResult = $aStmt->get_result();
                            while ($aRow = $aResult->fetch_assoc()) {
                                $attachmentsByNote[$aRow['note_id']][] = $aRow;
                            }
                            $aStmt->close();
                        }
                        ?>
                        <?php if (count($items) === 0) { ?>
                            <div class="empty-state">
                                <div class="empty-icon">📝</div>
                                <div class="empty-title">No notes yet</div>
                                <div class="empty-description">Click "Add Note" to start adding notes for this equipment</div>
                            </div>
                        <?php } else {
                            foreach ($items as $i => $row) {
                                $noteAtts = $attachmentsByNote[$row['id']] ?? [];
                            ?>
                                <div class="notes-item">
                                    <button type="button" class="btn-edit-pencil" title="Edit note" data-id="<?php echo (int)$row['id']; ?>">✏</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this note?');">
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn-delete-x" title="Delete note">×</button>
                                    </form>
                                    <div class="item-header">
                                        <div class="item-number"><?php echo $i + 1; ?></div>
                                        <div class="item-content">
                                            <div class="item-text"><?php echo htmlspecialchars($row['item_text']); ?></div>
                                            <form method="POST" class="inline-edit-form" id="editForm_<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="edit_id" value="<?php echo (int)$row['id']; ?>">
                                                <textarea name="edit_text" class="inline-edit-textarea" rows="3"><?php echo htmlspecialchars($row['item_text']); ?></textarea>
                                                <div class="inline-edit-actions">
                                                    <button type="submit" class="btn-save" style="padding:7px 16px;font-size:13px;">Save</button>
                                                    <button type="button" class="btn-cancel btn-edit-cancel" style="padding:7px 16px;font-size:13px;">Cancel</button>
                                                </div>
                                            </form>
                                            <?php if (!empty($noteAtts)) { ?>
                                            <div class="attachments-list">
                                                <?php foreach ($noteAtts as $att) {
                                                    $isImage = strpos($att['mime_type'], 'image/') === 0;
                                                    $isPdf = $att['mime_type'] === 'application/pdf';
                                                    $icon = $isImage ? '🖼️' : ($isPdf ? '📄' : '📎');
                                                    $fileUrl = '/uploads/equipment/' . htmlspecialchars(basename($att['filename']));
                                                ?>
                                                <div style="display:inline-flex;align-items:center;gap:0;">
                                                    <a href="<?php echo $fileUrl; ?>" class="attachment-chip" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($att['original_name']); ?>">
                                                        <span class="att-icon"><?php echo $icon; ?></span>
                                                        <span class="att-name"><?php echo htmlspecialchars($att['original_name']); ?></span>
                                                    </a>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this attachment?');">
                                                        <input type="hidden" name="delete_attachment_id" value="<?php echo (int)$att['id']; ?>">
                                                        <button type="submit" class="btn-delete-att" title="Remove attachment">×</button>
                                                    </form>
                                                </div>
                                                <?php } ?>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php }
                        } ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="equipmentRibbon"></div>

    <script src="../../assets/js/mobile-menu.js"></script>
    <script>
        var INITIAL_EQUIPMENTS = <?php echo json_encode($allEquipments ?: []); ?>;
        var CURRENT_EQUIPMENT_ID = <?php echo $equipmentId; ?>;

        function buildRibbon() {
            var ribbon = document.getElementById('equipmentRibbon');
            if (!ribbon) return;
            ribbon.innerHTML = '';

            if (!INITIAL_EQUIPMENTS || !INITIAL_EQUIPMENTS.length) {
                var note = document.createElement('div');
                note.style.color = '#64748b';
                note.textContent = 'No equipments found';
                ribbon.appendChild(note);
                return;
            }

            INITIAL_EQUIPMENTS.forEach(function(eq) {
                var chip = document.createElement('button');
                chip.className = 'equipment-chip';
                chip.type = 'button';
                chip.style.whiteSpace = 'nowrap';
                chip.dataset.eid = eq.equipment_id;
                chip.textContent = (eq.number && eq.number !== '') ? eq.number : ('#' + eq.equipment_id);
                chip.addEventListener('click', function() {
                    window.location.href = 'notes.php?id=' + eq.equipment_id;
                });
                ribbon.appendChild(chip);
            });

            var currentChip = ribbon.querySelector('.equipment-chip[data-eid="' + CURRENT_EQUIPMENT_ID + '"]');
            if (currentChip) {
                currentChip.classList.add('is-selected');
            }
        }

        document.addEventListener('DOMContentLoaded', buildRibbon);

        // Add note form logic
        var addBtn = document.getElementById('addNoteBtn');
        var form = document.getElementById('notesForm');
        var cancelBtn = document.getElementById('cancelNoteBtn');
        var textarea = document.getElementById('item_text');

        if (addBtn && form) {
            addBtn.addEventListener('click', function() {
                form.style.display = 'block';
                addBtn.style.display = 'none';
                if (textarea) textarea.focus();
            });
        }

        if (cancelBtn && form && addBtn) {
            cancelBtn.addEventListener('click', function() {
                form.style.display = 'none';
                addBtn.style.display = 'inline-block';
                if (textarea) textarea.value = '';
                var preview = document.getElementById('selectedFilesPreview');
                if (preview) preview.innerHTML = '';
                var fileInput = document.getElementById('noteAttachments');
                if (fileInput) fileInput.value = '';
            });
        }

        // Show selected file names preview
        var fileInput = document.getElementById('noteAttachments');
        var preview = document.getElementById('selectedFilesPreview');
        if (fileInput && preview) {
            fileInput.addEventListener('change', function() {
                preview.innerHTML = '';
                Array.from(fileInput.files).forEach(function(f) {
                    var chip = document.createElement('span');
                    chip.className = 'selected-file-chip';
                    chip.textContent = '📎 ' + f.name;
                    preview.appendChild(chip);
                });
            });
        }

        // Edit note logic
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-edit-pencil').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = btn.dataset.id;
                    var editForm = document.getElementById('editForm_' + id);
                    var notesItem = btn.closest('.notes-item');
                    var itemText = notesItem ? notesItem.querySelector('.item-text') : null;
                    if (!editForm) return;
                    if (itemText) itemText.style.display = 'none';
                    editForm.style.display = 'block';
                    btn.style.display = 'none';
                    editForm.querySelector('.inline-edit-textarea').focus();
                });
            });
            document.querySelectorAll('.btn-edit-cancel').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var editForm = btn.closest('.inline-edit-form');
                    var notesItem = btn.closest('.notes-item');
                    var itemText = notesItem ? notesItem.querySelector('.item-text') : null;
                    var pencilBtn = notesItem ? notesItem.querySelector('.btn-edit-pencil') : null;
                    if (editForm) editForm.style.display = 'none';
                    if (itemText) itemText.style.display = '';
                    if (pencilBtn) pencilBtn.style.display = '';
                });
            });
        });
    </script>
</body>
</html>
