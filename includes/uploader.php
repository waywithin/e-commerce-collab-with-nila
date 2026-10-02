<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Secure Image Upload Handler
 * Location: /includes/uploader.php
 */

require_once __DIR__ . '/../config/config.php';

class ImageUploader {

    /**
     * Upload and sanitize an image file
     *
     * @param array $file The $_FILES['input_name'] array
     * @param string $destinationFolder Subfolder: 'products' or 'providers'
     * @return array ['success' => bool, 'filename' => string|null, 'path' => string|null, 'error' => string|null]
     */
    public static function upload(array $file, string $destinationFolder = 'products'): array {
        // 1. Verify file was provided and no PHP upload errors occurred
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file parameters provided.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No image was selected for upload.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'The uploaded file exceeds the allowed server limit (3 MB).'];
            default:
                return ['success' => false, 'error' => 'An unknown error occurred during image upload (Code ' . $file['error'] . ').'];
        }

        // 2. Validate File Size
        if ($file['size'] > MAX_FILE_SIZE_BYTES) {
            return ['success' => false, 'error' => 'File size exceeds maximum allowed limit of 3MB.'];
        }

        // 3. Validate File Extension
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS, true)) {
            return [
                'success' => false, 
                'error' => 'Invalid file extension (.' . htmlspecialchars($extension) . '). Only JPG, JPEG, PNG, and WEBP images are allowed.'
            ];
        }

        // 4. Validate True MIME Type via Fileinfo
        $tmpPath = $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_IMAGE_MIMES, true)) {
            return [
                'success' => false, 
                'error' => 'Security rejection: The uploaded file content does not match a valid image type (' . htmlspecialchars($mimeType) . ').'
            ];
        }

        // 5. Verify image dimensions using getimagesize()
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'The uploaded file is corrupted or not a readable image.'];
        }

        // 6. Ensure target directory exists
        $targetDir = ($destinationFolder === 'providers') ? PROVIDER_UPLOAD_DIR : PRODUCT_UPLOAD_DIR;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return ['success' => false, 'error' => 'Failed to initialize server upload directory.'];
            }
        }

        // 7. Generate a cryptographically secure unique filename
        $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destinationPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;

        // 8. Move file securely
        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            return ['success' => false, 'error' => 'Failed to save the image to server storage. Check directory permissions.'];
        }

        // Set safe file permissions (read-only for web server)
        @chmod($destinationPath, 0644);

        // Store relative path in database (e.g. 'uploads/products/xyz.jpg')
        $relativePath = 'uploads/' . $destinationFolder . '/' . $safeName;

        return [
            'success'  => true,
            'filename' => $safeName,
            'path'     => $relativePath,
            'url'      => BASE_URL . '/' . $relativePath
        ];
    }

    /**
     * Delete an existing image file safely if it's not a default placeholder
     * @param string|null $relativePath e.g., 'uploads/products/xyz.jpg'
     */
    public static function deleteOldImage(?string $relativePath): void {
        if (empty($relativePath)) {
            return;
        }

        // Do not delete default fallback images
        if (str_contains($relativePath, 'default_') || str_contains($relativePath, 'prod_') || str_contains($relativePath, 'prov_')) {
            return;
        }

        $fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

