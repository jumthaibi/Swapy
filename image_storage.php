<?php
/**
 * Persistent image storage helpers.
 *
 * Listings still keep their original img value for backwards compatibility,
 * but a copy of every new upload is also kept in MySQL.  This means an image
 * continues to work when the uploads directory is not included in a deploy.
 */

function ensure_image_blob_table(mysqli $conn): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    // Some existing deployments use a restricted DB account. Suppress the
    // optional-table warning so it cannot corrupt an image response header;
    // the original uploads path remains a valid fallback in that case.
    $ready = (bool) @mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS swapy_image_blobs (
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            image_data LONGBLOB NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    return $ready;
}

/**
 * Store raw image bytes without putting base64 into a VARCHAR/TEXT column.
 */
function save_image_blob(
    mysqli $conn,
    string $entityType,
    int $entityId,
    string $mimeType,
    string $imageData
): bool {
    if ($entityId <= 0 || $imageData === '' || !ensure_image_blob_table($conn)) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "
        INSERT INTO swapy_image_blobs
            (entity_type, entity_id, mime_type, image_data)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            mime_type = VALUES(mime_type),
            image_data = VALUES(image_data)
    ");

    if (!$stmt) {
        return false;
    }

    // Bind an empty value first, then stream the binary payload. This avoids
    // truncation when the image is larger than the driver's packet buffer.
    $emptyData = '';
    mysqli_stmt_bind_param(
        $stmt,
        'sisb',
        $entityType,
        $entityId,
        $mimeType,
        $emptyData
    );
    mysqli_stmt_send_long_data($stmt, 3, $imageData);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $saved;
}

/**
 * Read and validate an uploaded image without trusting the browser-provided
 * MIME type. Chat images are kept in the same durable blob table as listing
 * and profile images, but use the chat message ID as their entity ID.
 *
 * Returns:
 *   null  when no file was uploaded
 *   false when an uploaded file is invalid
 *   array with MIME type and bytes when valid
 */
function read_uploaded_image($file, int $maxBytes = 8388608)
{
    if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    if (!isset($file['size']) || (int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
        return false;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!$imageInfo || empty($imageInfo['mime']) || !in_array($imageInfo['mime'], $allowedMimes, true)) {
        return false;
    }

    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false || $imageData === '') {
        return false;
    }

    return [
        'mime_type' => $imageInfo['mime'],
        'image_data' => $imageData
    ];
}

function save_chat_image_blob(
    mysqli $conn,
    int $messageId,
    string $mimeType,
    string $imageData
): bool {
    return save_image_blob($conn, 'chat', $messageId, $mimeType, $imageData);
}

function image_data_uri_parts(string $value): ?array
{
    if (!preg_match(
        '/^data:(image\/[a-z0-9.+-]+);base64,(.*)$/is',
        $value,
        $matches
    )) {
        return null;
    }

    $decoded = base64_decode($matches[2], true);
    if ($decoded === false || $decoded === '') {
        return null;
    }

    return [
        'mime_type' => strtolower($matches[1]),
        'image_data' => $decoded
    ];
}

function image_mime_type(string $imageData, string $fallback = 'image/jpeg'): string
{
    $info = @getimagesizefromstring($imageData);
    if (!empty($info['mime'])) {
        return $info['mime'];
    }

    return $fallback;
}

/**
 * Convert a legacy data URL to a local fallback file.
 */
function save_data_uri_fallback(
    string $dataUri,
    string $uploadDir,
    string $relativePrefix = 'uploads/'
): ?array {
    $parts = image_data_uri_parts($dataUri);
    if ($parts === null) {
        return null;
    }

    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $extensionMap[$parts['mime_type']] ?? 'jpg';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (file_put_contents($destination, $parts['image_data'], LOCK_EX) === false) {
        return null;
    }

    return [
        'path' => rtrim($relativePrefix, '/') . '/' . $filename,
        'mime_type' => $parts['mime_type'],
        'image_data' => $parts['image_data']
    ];
}