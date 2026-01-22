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

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS whishlists (id INT AUTO_INCREMENT PRIMARY KEY, equipment_id INT NOT NULL, item_text TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Handle delete BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare('DELETE FROM whishlists WHERE id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();
        // Redirect to prevent re-submission on refresh
        header('Location: whishlist.php?id=' . $equipmentId . '&deleted=1');
        exit();
    }
}

// Handle add BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_text'], $_POST['equipment_id'])) {
    $txt = trim($_POST['item_text']);
    $eid = (int)$_POST['equipment_id'];
    if ($txt !== '' && $eid > 0) {
        $stmt = $conn->prepare('INSERT INTO whishlists (equipment_id, item_text) VALUES (?, ?)');
        $stmt->bind_param('is', $eid, $txt);
        $stmt->execute();
        $stmt->close();
        // Redirect to prevent re-submission on refresh
        header('Location: whishlist.php?id=' . $eid . '&added=1');
        exit();
    }
}

// Check for success messages from redirect
$showSuccess = isset($_GET['added']) && $_GET['added'] == '1';
$showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Equipment Wishlist</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
        .wishlist-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
        }

        .wishlist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .wishlist-title {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .wishlist-subtitle {
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

        .wishlist-form {
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

        .wishlist-items {
            display: grid;
            gap: 16px;
        }

        .wishlist-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .wishlist-item:hover {
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
            .wishlist-container {
                padding: 16px;
            }

            .wishlist-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .wishlist-title {
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
                
                // Fetch items
                $items = [];
                if ($equipmentId > 0) {
                    $stmt = $conn->prepare('SELECT id, item_text FROM whishlists WHERE equipment_id = ? ORDER BY id ASC');
                    $stmt->bind_param('i', $equipmentId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) $items[] = $row;
                    $stmt->close();
                }
                ?>
                
                <div class="wishlist-container">
                    <div class="wishlist-header">
                        <div>
                            <h1 class="wishlist-title">Equipment Wishlist</h1>
                            <p class="wishlist-subtitle">Equipment #<?php echo htmlspecialchars($currentEqNum ?: $equipmentId); ?></p>
                        </div>
                        <button id="addWishlistBtn" class="add-item-btn">+ Add Item</button>
                    </div>

                    <?php if ($showSuccess) { ?>
                        <div class="success-message">✓ Wishlist item added successfully!</div>
                    <?php } ?>

                    <?php if ($showDeleted) { ?>
                        <div class="delete-message">✓ Wishlist item deleted successfully!</div>
                    <?php } ?>

                    <form id="wishlistForm" method="POST" class="wishlist-form" style="display:none;">
                        <label class="form-label">New Wishlist Item</label>
                        <textarea name="item_text" id="item_text" rows="3" class="form-textarea" placeholder="Describe the equipment or item you'd like to add..." required></textarea>
                        <input type="hidden" name="equipment_id" value="<?php echo (int)$equipmentId; ?>">
                        <div class="form-actions">
                            <button type="submit" class="btn-save">Save Item</button>
                            <button type="button" id="cancelWishlistBtn" class="btn-cancel">Cancel</button>
                        </div>
                    </form>

                    <div class="wishlist-items">
                        <?php if (count($items) === 0) { ?>
                            <div class="empty-state">
                                <div class="empty-icon"></div>
                                <div class="empty-title">No wishlist items yet</div>
                                <div class="empty-description">Click "Add Item" to start building your wishlist</div>
                            </div>
                        <?php } else { 
                            foreach ($items as $i => $row) { ?>
                                <div class="wishlist-item">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this wishlist item?');">
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn-delete-x" title="Delete item">×</button>
                                    </form>
                                    <div class="item-header">
                                        <div class="item-number"><?php echo $i + 1; ?></div>
                                        <div class="item-content">
                                            <div class="item-text"><?php echo htmlspecialchars($row['item_text']); ?></div>
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
                    var url = 'whishlist.php?id=' + eq.equipment_id;
                    window.location.href = url;
                });
                ribbon.appendChild(chip);
            });
            
            var currentChip = ribbon.querySelector('.equipment-chip[data-eid="' + CURRENT_EQUIPMENT_ID + '"]');
            if (currentChip) {
                currentChip.classList.add('is-selected');
            }
        }
        
        document.addEventListener('DOMContentLoaded', buildRibbon);
        
        // Wishlist add form logic
        var addBtn = document.getElementById('addWishlistBtn');
        var form = document.getElementById('wishlistForm');
        var cancelBtn = document.getElementById('cancelWishlistBtn');
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
            });
        }
    </script>
</body>
</html>