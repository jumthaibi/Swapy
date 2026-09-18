<?php
/**
 * Serves images stored in the durable image table, legacy data URLs, or the
 * old uploads path. The browser never needs to know which storage was used.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/image_storage.php';

$entity = $_GET['entity'] ?? '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Chat images are private: only one of the two participants may retrieve
// them. Listing and profile images remain available through the public branch
// below for backwards compatibility with the existing application.
if ($entity === 'chat') {
    require_once __DIR__ . '/auth.php';
    protect_page(0);

    if ($id <= 0) {
        http_response_code(400);
        exit('Invalid chat image request.');
    }

    $viewer = $_SESSION['swapy_session'];
    $chatStmt = mysqli_prepare($conn, "
        SELECT id
        FROM chats
        WHERE id = ?
          AND (sender = ? OR receiver = ?)
        LIMIT 1
    ");

    if (!$chatStmt) {
        http_response_code(500);
        exit('Chat image lookup failed.');
    }

    mysqli_stmt_bind_param($chatStmt, 'iss', $id, $viewer, $viewer);
    mysqli_stmt_execute($chatStmt);
    $chatResult = mysqli_stmt_get_result($chatStmt);
    $chatExists = $chatResult && mysqli_fetch_assoc($chatResult);
    mysqli_stmt_close($chatStmt);

    if (!$chatExists || !ensure_image_blob_table($conn)) {
        http_response_code(404);
        exit('Chat image not found.');
    }

    $blobStmt = mysqli_prepare($conn, "
        SELECT mime_type, image_data
        FROM swapy_image_blobs
        WHERE entity_type = 'chat' AND entity_id = ?
        LIMIT 1
    ");

    if (!$blobStmt) {
        http_response_code(500);
        exit('Chat image lookup failed.');
    }

    mysqli_stmt_bind_param($blobStmt, 'i', $id);
    mysqli_stmt_execute($blobStmt);
    $blobResult = mysqli_stmt_get_result($blobStmt);
    $blob = $blobResult ? mysqli_fetch_assoc($blobResult) : null;
    mysqli_stmt_close($blobStmt);

    if (!$blob || $blob['image_data'] === '') {
        http_response_code(404);
        exit('Chat image not found.');
    }

    header('Content-Type: ' . ($blob['mime_type'] ?: 'application/octet-stream'));
    header('Cache-Control: private, max-age=3600');
    echo $blob['image_data'];
    exit();
}

$allowedEntities = [
    'post' => ['table' => 'posts', 'column' => 'img'],
    'user' => ['table' => 'users', 'column' => 'pic']
];

if (!isset($allowedEntities[$entity]) || $id <= 0) {
    http_response_code(400);
    exit('Invalid image request.');
}

$definition = $allowedEntities[$entity];
$table = $definition['table'];
$column = $definition['column'];
$stmt = mysqli_prepare($conn, "SELECT `$column` FROM `$table` WHERE id = ? LIMIT 1");

if (!$stmt) {
    http_response_code(500);
    exit('Image lookup failed.');
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    http_response_code(404);
    exit('Image not found.');
}

// An empty users.pic/posts.img means the image was intentionally removed.
// Do not serve an older durable blob in that case.
$storedValue = $row[$column];

// Prefer the durable database copy. If table creation is unavailable on an
// older MySQL account, the legacy value below remains the fallback.
if ($storedValue !== null && $storedValue !== '' && ensure_image_blob_table($conn)) {
    $blobStmt = @mysqli_prepare($conn, "
        SELECT mime_type, image_data
        FROM swapy_image_blobs
        WHERE entity_type = ? AND entity_id = ?
        LIMIT 1
    ");

    if ($blobStmt) {
        mysqli_stmt_bind_param($blobStmt, 'si', $entity, $id);
        mysqli_stmt_execute($blobStmt);
        $blobResult = mysqli_stmt_get_result($blobStmt);
        $blob = $blobResult ? mysqli_fetch_assoc($blobResult) : null;
        mysqli_stmt_close($blobStmt);

        if ($blob && $blob['image_data'] !== '') {
            header('Content-Type: ' . ($blob['mime_type'] ?: 'application/octet-stream'));
            header('Cache-Control: no-cache, must-revalidate');
            echo $blob['image_data'];
            exit();
        }
    }
}

if ($storedValue === null || $storedValue === '') {
    http_response_code(404);
    exit('Image not found.');
}

// Legacy base64/data-URI values are still supported.
if (is_string($storedValue)) {
    $dataUri = image_data_uri_parts($storedValue);
    if ($dataUri !== null) {
        // Backfill older data-URL records while they are still available.
        save_image_blob(
            $conn,
            $entity,
            $id,
            $dataUri['mime_type'],
            $dataUri['image_data']
        );
        header('Content-Type: ' . $dataUri['mime_type']);
        header('Cache-Control: no-cache, must-revalidate');
        echo $dataUri['image_data'];
        exit();
    }

    // A stored external URL is safe to preserve as a redirect.
    if (preg_match('#^https?://#i', $storedValue)) {
        header('Location: ' . $storedValue, true, 302);
        exit();
    }
}

// Raw BLOBs from older schemas can be emitted directly.
if (is_string($storedValue)) {
    $rawInfo = @getimagesizefromstring($storedValue);
    if ($rawInfo && !empty($rawInfo['mime'])) {
        save_image_blob($conn, $entity, $id, $rawInfo['mime'], $storedValue);
        header('Content-Type: ' . $rawInfo['mime']);
        header('Cache-Control: no-cache, must-revalidate');
        echo $storedValue;
        exit();
    }
}

// Final fallback for the original customer/uploads/<file> values.
$relativePath = ltrim((string) $storedValue, '/');
$candidatePaths = [
    __DIR__ . '/customer/' . $relativePath,
    __DIR__ . '/' . $relativePath,
    __DIR__ . '/' . ltrim($relativePath, './')
];

foreach ($candidatePaths as $candidate) {
    $realPath = realpath($candidate);
    if ($realPath === false || !is_file($realPath)) {
        continue;
    }

    $root = realpath(__DIR__);
    if ($root === false || !str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $imageData = file_get_contents($realPath);
    if ($imageData === false) {
        continue;
    }

    $mimeType = image_mime_type($imageData);
    // Backfill old uploads so they become durable the first time they are
    // requested after this fix.
    save_image_blob($conn, $entity, $id, $mimeType, $imageData);
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: no-cache, must-revalidate');
    echo $imageData;
    exit();
}

http_response_code(404);
exit('Image file not found.');