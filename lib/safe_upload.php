<?php
/**
 * Safe file upload helper.
 * - extension whitelist + real MIME check (finfo)
 * - random server-chosen filename (original name is never used on disk)
 * - size limit, 0644 permissions, directory 0755
 * Returns the public path (e.g. /uploads/logos/ab12.png) or NULL.
 */
function av_safe_upload(array $file, string $folder, string $kind = 'image', int $maxBytes = 5242880) {
    if (empty($file['name']) || !isset($file['tmp_name'])) return NULL;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return NULL;
    if (!is_uploaded_file($file['tmp_name'])) return NULL;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) return NULL;

    $rules = [
        'image' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif']],
        'document' => [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        ],
    ];
    $allowed = $rules[$kind] ?? $rules['image'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return NULL;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed[$ext], true)) return NULL;
    if ($kind === 'image' && @getimagesize($file['tmp_name']) === false) return NULL;

    $folder = trim($folder, '/') . '/';
    $target_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $folder;
    if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true)) return NULL;

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $target_dir . $name)) return NULL;
    @chmod($target_dir . $name, 0644);
    return '/' . $folder . $name;
}
