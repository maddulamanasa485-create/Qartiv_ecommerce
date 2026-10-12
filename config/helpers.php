<?php
/**
 * QARTIV - Core Helper Functions
 * Robust file handling, image validation, formatting, and path resolution.
 * Compatible with localhost and live hosting (InfinityFree).
 */

if (!defined('QARTIV_HELPERS_LOADED')) {
    define('QARTIV_HELPERS_LOADED', true);

    /**
     * Allowed image extensions and MIME types.
     */
    define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
    define('ALLOWED_IMAGE_MIMES', [
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/webp'
    ]);
    define('MAX_IMAGE_SIZE_BYTES', 5 * 1024 * 1024); // 5 MB

    /**
     * Validate and process an uploaded product image.
     *
     * @param array $file $_FILES['image']
     * @param string &$errorMessage Error message output
     * @return string|null|false Returns filename on success, null if no file provided, false on validation error
     */
    function validateAndUploadProductImage($file, &$errorMessage = '')
    {
        if (!isset($file) || !is_array($file)) {
            return null;
        }

        // No file uploaded
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        // Upload error checks
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'The uploaded image exceeds the maximum allowed file size.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = 'The image was only partially uploaded. Please try again.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage = 'Missing temporary folder on server.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage = 'Failed to write image to disk.';
                    break;
                default:
                    $errorMessage = 'An error occurred while uploading the image (Code ' . (int)$file['error'] . ').';
            }
            return false;
        }

        // Check file size
        if ($file['size'] > MAX_IMAGE_SIZE_BYTES) {
            $errorMessage = 'Image size must be less than 5MB.';
            return false;
        }

        if ($file['size'] <= 0) {
            $errorMessage = 'Uploaded file is empty.';
            return false;
        }

        // Validate extension
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS, true)) {
            $errorMessage = 'Invalid file extension. Only JPG, JPEG, PNG, and WEBP formats are permitted.';
            return false;
        }

        // Validate MIME type safely
        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            $errorMessage = 'Security alert: Invalid file upload source.';
            return false;
        }

        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }

        if (empty($detectedMime) && function_exists('mime_content_type')) {
            $detectedMime = @mime_content_type($tmpPath);
        }

        // Fallback to getimagesize
        if (empty($detectedMime) && function_exists('getimagesize')) {
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo && isset($imageInfo['mime'])) {
                $detectedMime = $imageInfo['mime'];
            }
        }

        if (empty($detectedMime) || !in_array(strtolower($detectedMime), ALLOWED_IMAGE_MIMES, true)) {
            $errorMessage = 'Invalid file type. The file is not a valid JPG, JPEG, PNG, or WEBP image.';
            return false;
        }

        // Ensure file is actually a renderable image (disallows polyglot scripts)
        if (function_exists('getimagesize')) {
            $imgCheck = @getimagesize($tmpPath);
            // WEBP might not be recognized by older getimagesize implementations without GD webp support,
            // so if getimagesize fails and extension is webp, verify mime string.
            if ($imgCheck === false && $extension !== 'webp') {
                $errorMessage = 'Corrupted or fake image file detected.';
                return false;
            }
        }

        // Target directory: uploads/products/
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                $errorMessage = 'Server error: Unable to create upload directory.';
                return false;
            }
        }

        // Generate cryptographically unique safe filename
        try {
            $randomHex = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $randomHex = md5(uniqid((string)mt_rand(), true));
        }

        $uniqueFilename = 'prod_' . date('Ymd_His') . '_' . $randomHex . '.' . $extension;
        $destinationPath = $uploadDir . $uniqueFilename;

        if (move_uploaded_file($tmpPath, $destinationPath)) {
            // Set safe file permissions (read/write for owner, read for others)
            @chmod($destinationPath, 0644);
            return $uniqueFilename;
        } else {
            $errorMessage = 'Failed to move uploaded file. Please check folder permissions.';
            return false;
        }
    }

    /**
     * Safely delete a product image from the uploads folder.
     *
     * @param string|null $filename Image filename
     * @return bool True if deleted or did not exist, false if deletion failed
     */
    function deleteProductImage($filename)
    {
        if (empty($filename)) {
            return true;
        }

        // Sanitize: strip any directory components
        $safeName = basename(trim($filename));
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            return true;
        }

        $filePath = __DIR__ . '/../uploads/products/' . $safeName;

        if (file_exists($filePath) && is_file($filePath)) {
            return @unlink($filePath);
        }

        return true;
    }

    /**
     * Resolve product image URL with fallback to default placeholder.
     * Works on both localhost and live servers (InfinityFree).
     *
     * @param string|null $filename Image filename stored in DB
     * @param string $relativePrefix Relative path to project root ('../' or '')
     * @return string Valid image web URL
     */
    function getProductImageUrl($filename, $relativePrefix = '../')
    {
        $prefix = rtrim($relativePrefix, '/');
        if ($prefix !== '') {
            $prefix .= '/';
        }

        $defaultPlaceholder = $prefix . 'assets/images/default-product.svg';

        if (empty($filename)) {
            return $defaultPlaceholder;
        }

        $safeName = basename(trim($filename));
        $filesystemPath = __DIR__ . '/../uploads/products/' . $safeName;

        if (is_file($filesystemPath)) {
            return $prefix . 'uploads/products/' . rawurlencode($safeName);
        }

        return $defaultPlaceholder;
    }

    /**
     * Calculate discounted sale price.
     *
     * @param float $price Original price
     * @param float $discount Discount percentage
     * @return float Calculated final price
     */
    function calculateSalePrice($price, $discount)
    {
        $price = (float)$price;
        $discount = (float)$discount;

        if ($discount > 0 && $discount <= 100) {
            return max(0, $price - ($price * $discount / 100));
        }

        return max(0, $price);
    }
}
?>
