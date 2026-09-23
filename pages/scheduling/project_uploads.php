<?php
/**
 * Shared helpers for images attached to a scheduled project's office notes.
 *
 * Mirrors the equipment upload mechanism (api/add_equipment_upload.php) but
 * stores rows in `scheduled_project_uploads` and files under uploads/scheduling/.
 *
 * Images added from the "Add Project" modal are staged against a random
 * draft_key (the project row does not exist yet) and are bound to the new
 * project_id once the project is created.
 */

require_once __DIR__ . '/../../partials/upload_guard.php';

const PROJECT_UPLOAD_ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

/** Absolute directory that holds the files (with trailing slash). */
function project_uploads_dir(): string
{
    $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
    if ($isProduction) {
        $mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
        return rtrim($mount, '/') . '/scheduling/';
    }
    return __DIR__ . '/../../uploads/scheduling/';
}

/** Canonical public URL for a stored filename. */
function project_uploads_url(string $filename): string
{
    return '/uploads/scheduling/' . $filename;
}

/** Create the upload directory on demand. Returns false when unusable. */
function project_uploads_ensure_dir(): bool
{
    $dir = project_uploads_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return false;
    }
    return is_writable($dir);
}

/**
 * Create the metadata table and the denormalized column on demand.
 *
 * scheduled_projects.note_image_urls carries the same URLs as a JSON array so a
 * project row shows its images without joining. The upload table stays the
 * source of truth; project_uploads_sync_project_urls() rewrites the column.
 */
function project_uploads_ensure_schema($conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS scheduled_project_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NULL,
        draft_key VARCHAR(48) NULL,
        file_url VARCHAR(1024) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NULL,
        mime_type VARCHAR(255) NULL,
        size_bytes INT UNSIGNED NULL DEFAULT 0,
        uploaded_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_spu_project (project_id),
        KEY idx_spu_draft (draft_key),
        CONSTRAINT fk_spu_project FOREIGN KEY (project_id)
            REFERENCES scheduled_projects(project_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $col = $conn->query("SHOW COLUMNS FROM scheduled_projects LIKE 'note_image_urls'");
    if ($col && $col->num_rows === 0) {
        $conn->query('ALTER TABLE scheduled_projects ADD COLUMN note_image_urls TEXT NULL');

        // Backfill once, so images uploaded before the column existed are covered.
        $res = $conn->query('SELECT DISTINCT project_id FROM scheduled_project_uploads WHERE project_id IS NOT NULL');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                project_uploads_sync_project_urls($conn, (int)$row['project_id']);
            }
        }
    }
}

/**
 * Rewrite scheduled_projects.note_image_urls from the project's upload rows.
 * Call after anything that adds, replaces or removes an image.
 */
function project_uploads_sync_project_urls($conn, int $projectId): void
{
    if ($projectId <= 0) return;

    $urls = [];
    $stmt = $conn->prepare('SELECT file_url, filename FROM scheduled_project_uploads WHERE project_id = ? ORDER BY id ASC');
    if (!$stmt) return;
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $filename = basename((string)($row['filename'] ?: $row['file_url']));
        if ($filename !== '') {
            $urls[] = project_uploads_url($filename);
        }
    }
    $stmt->close();

    // NULL rather than "[]" so an image-less project reads as empty, not as data.
    $json = $urls ? json_encode($urls, JSON_UNESCAPED_SLASHES) : null;
    $up = $conn->prepare('UPDATE scheduled_projects SET note_image_urls = ? WHERE project_id = ? LIMIT 1');
    if (!$up) return;
    $up->bind_param('si', $json, $projectId);
    $up->execute();
    $up->close();
}

/**
 * JSON guard for the notes-image endpoints. Matches the authorization the
 * scheduling page itself applies: signed in + access to the scheduling module.
 */
function project_uploads_require_access($conn): void
{
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['email'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $role = get_current_role();
    if ($role === null || !can_access((string)$role, 'scheduling')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}

/** Delete one row's file from disk. */
function project_uploads_unlink(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') return;
    $path = project_uploads_dir() . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Bind images staged under a draft key to a freshly created project.
 * Returns the number of rows adopted.
 */
function project_uploads_claim_draft($conn, string $draftKey, int $projectId): int
{
    $draftKey = trim($draftKey);
    if ($draftKey === '' || $projectId <= 0) return 0;

    $stmt = $conn->prepare('UPDATE scheduled_project_uploads SET project_id = ?, draft_key = NULL WHERE draft_key = ? AND project_id IS NULL');
    if (!$stmt) return 0;
    $stmt->bind_param('is', $projectId, $draftKey);
    $stmt->execute();
    $claimed = $stmt->affected_rows;
    $stmt->close();

    if ($claimed > 0) {
        project_uploads_sync_project_urls($conn, $projectId);
    }
    return max(0, (int)$claimed);
}

/** Remove staged images that were never attached to a project. */
function project_uploads_purge_stale_drafts($conn, int $olderThanHours = 24): void
{
    $res = $conn->query('SELECT id, filename FROM scheduled_project_uploads WHERE project_id IS NULL AND created_at < (NOW() - INTERVAL ' . (int)$olderThanHours . ' HOUR)');
    if (!$res) return;

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['id'];
        project_uploads_unlink((string)$row['filename']);
    }
    if ($ids) {
        $conn->query('DELETE FROM scheduled_project_uploads WHERE id IN (' . implode(',', $ids) . ')');
    }
}

/** Rows for a project (or a draft), newest last, skipping files that vanished. */
function project_uploads_list($conn, int $projectId, string $draftKey = ''): array
{
    if ($projectId > 0) {
        $stmt = $conn->prepare('SELECT id, file_url, filename, original_name, created_at FROM scheduled_project_uploads WHERE project_id = ? ORDER BY id ASC');
        if (!$stmt) return [];
        $stmt->bind_param('i', $projectId);
    } elseif ($draftKey !== '') {
        $stmt = $conn->prepare('SELECT id, file_url, filename, original_name, created_at FROM scheduled_project_uploads WHERE draft_key = ? AND project_id IS NULL ORDER BY id ASC');
        if (!$stmt) return [];
        $stmt->bind_param('s', $draftKey);
    } else {
        return [];
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $dir = project_uploads_dir();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $filename = basename((string)($row['filename'] ?: $row['file_url']));
        if ($filename === '' || !is_file($dir . $filename)) {
            continue;
        }
        // Always hand back the canonical public URL, whatever is stored.
        $row['file_url'] = project_uploads_url($filename);
        $row['id'] = (int)$row['id'];
        $out[] = $row;
    }
    $stmt->close();
    return $out;
}
