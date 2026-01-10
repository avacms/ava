<?php

declare(strict_types=1);

namespace Ava\Admin;

use Ava\Application;

/**
 * Secure Media Uploader
 *
 * Provides comprehensive security for image uploads:
 * - File extension allowlisting
 * - MIME type validation (multiple methods)
 * - Magic bytes verification
 * - ImageMagick reprocessing to strip malicious payloads
 * - Filename sanitization against path traversal
 * - Unicode direction override protection
 * - Windows reserved name protection
 * - Duplicate filename handling
 * - Size validation
 * - Non-executable permissions
 */
final class MediaUploader
{
    private Application $app;
    private string $mediaPath;
    private bool $organizeByDate;
    private int $maxFileSize;
    private array $allowedTypes;

    /** 
     * Mapping of MIME types to canonical file extensions.
     * Only these extensions will be used, regardless of what the user uploads.
     */
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/avif' => 'avif',
    ];

    /**
     * Magic bytes (file signatures) for each image type.
     * Used as additional verification layer.
     */
    private const MAGIC_BYTES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1A\n"],
        'image/gif' => ["GIF87a", "GIF89a"],
        'image/webp' => ["RIFF"],  // Full check: RIFF....WEBP
        'image/avif' => ["\x00\x00\x00"], // ftyp box, more complex check needed
    ];

    /**
     * Windows reserved filenames (case-insensitive).
     */
    private const WINDOWS_RESERVED = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /**
     * Dangerous patterns that could indicate attacks.
     */
    private const DANGEROUS_PATTERNS = [
        '/\.php/i',
        '/\.phtml/i',
        '/\.phar/i',
        '/\.htaccess/i',
        '/\.htpasswd/i',
        '/\.user\.ini/i',
        '/\.inc/i',
        '/\.asp/i',
        '/\.aspx/i',
        '/\.jsp/i',
        '/\.cgi/i',
        '/\.pl/i',
        '/\.py/i',
        '/\.rb/i',
        '/\.sh/i',
        '/\.bash/i',
        '/\.exe/i',
        '/\.dll/i',
        '/\.bat/i',
        '/\.cmd/i',
        '/web\.config/i',
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->mediaPath = $app->path($app->config('admin.media.path', 'public/media'));
        $this->organizeByDate = (bool) $app->config('admin.media.organize_by_date', true);
        $this->maxFileSize = (int) $app->config('admin.media.max_file_size', 10 * 1024 * 1024);
        $this->allowedTypes = $app->config('admin.media.allowed_types', array_keys(self::MIME_TO_EXTENSION));
    }

    /**
     * Get the base media path.
     */
    public function getMediaPath(): string
    {
        return $this->mediaPath;
    }

    /**
     * Check if media uploads are enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->app->config('media.enabled', true);
    }

    /**
     * Check if the media directory is writable.
     */
    public function isWritable(): bool
    {
        if (!is_dir($this->mediaPath)) {
            return is_writable(dirname($this->mediaPath));
        }
        return is_writable($this->mediaPath);
    }

    /**
     * Get list of existing subdirectories in the media folder.
     * Only returns direct subdirectories (not nested), excluding hidden dirs.
     * Excludes date-based folders (YYYY or YYYY/MM patterns).
     * 
     * @param bool $excludeDateFolders Whether to exclude year/month date folders
     * @return array<string> List of folder names (relative to media root)
     */
    public function getExistingFolders(bool $excludeDateFolders = true): array
    {
        if (!is_dir($this->mediaPath)) {
            return [];
        }

        $folders = [];
        $this->scanFoldersRecursive($this->mediaPath, '', $folders, 3); // Max depth of 3
        
        // Filter out date-based folders if requested
        if ($excludeDateFolders) {
            $folders = array_filter($folders, function($folder) {
                // Match YYYY or YYYY/MM patterns
                return !preg_match('/^\d{4}(\/\d{2})?$/', $folder);
            });
            $folders = array_values($folders); // Re-index
        }
        
        sort($folders);
        return $folders;
    }

    /**
     * Recursively scan for folders up to a certain depth.
     */
    private function scanFoldersRecursive(string $basePath, string $prefix, array &$folders, int $maxDepth): void
    {
        if ($maxDepth <= 0) {
            return;
        }

        $items = @scandir($basePath);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            // Skip hidden directories and special entries
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
                continue;
            }

            $fullPath = $basePath . '/' . $item;
            if (is_dir($fullPath)) {
                $relativePath = $prefix === '' ? $item : $prefix . '/' . $item;
                $folders[] = $relativePath;
                $this->scanFoldersRecursive($fullPath, $relativePath, $folders, $maxDepth - 1);
            }
        }
    }

    /**
     * Check if organize by date is enabled.
     */
    public function isOrganizeByDateEnabled(): bool
    {
        return $this->organizeByDate;
    }

    /**
     * Get the current date-based folder path (e.g., "2024/01").
     */
    public function getDateFolder(): string
    {
        return date('Y') . '/' . date('m');
    }

    /**
     * Upload a file securely.
     *
     * @param array $uploadedFile The $_FILES array entry for a single file
     * @param string|null $targetFolder Subfolder within media directory (null = root or date folder)
     * @param bool $useDate Override organize_by_date setting for this upload
     * @return array{success: bool, path?: string, url?: string, filename?: string, error?: string}
     */
    public function upload(array $uploadedFile, ?string $targetFolder = null, ?bool $useDate = null): array
    {
        // Validate upload array structure
        if (!isset($uploadedFile['tmp_name'], $uploadedFile['name'], $uploadedFile['error'], $uploadedFile['size'])) {
            return $this->error('Invalid upload data');
        }

        // Check for upload errors
        $uploadError = $this->checkUploadError($uploadedFile['error']);
        if ($uploadError !== null) {
            return $this->error($uploadError);
        }

        // Validate it's an actual uploaded file (prevents tmp file attacks)
        if (!is_uploaded_file($uploadedFile['tmp_name'])) {
            return $this->error('Invalid upload: not a POST uploaded file');
        }

        // Check file size
        if ($uploadedFile['size'] > $this->maxFileSize) {
            $maxMb = round($this->maxFileSize / 1024 / 1024, 1);
            return $this->error("File exceeds maximum size of {$maxMb}MB");
        }

        if ($uploadedFile['size'] === 0) {
            return $this->error('Empty file uploaded');
        }

        // Detect and validate MIME type using multiple methods
        $mimeResult = $this->detectAndValidateMimeType($uploadedFile['tmp_name']);
        if (!$mimeResult['valid']) {
            return $this->error($mimeResult['error']);
        }
        $mimeType = $mimeResult['mime'];

        // Get canonical extension for this MIME type
        $extension = self::MIME_TO_EXTENSION[$mimeType] ?? null;
        if ($extension === null) {
            return $this->error('Unsupported image type');
        }

        // Verify magic bytes for binary image formats
        if ($mimeType !== 'image/svg+xml') {
            $magicValid = $this->verifyMagicBytes($uploadedFile['tmp_name'], $mimeType);
            if (!$magicValid) {
                return $this->error('File signature does not match declared type');
            }
        }

        // Sanitize the original filename
        $safeFilename = $this->sanitizeFilename($uploadedFile['name'], $extension);

        // Determine target directory
        $useDate = $useDate ?? $this->organizeByDate;
        $subFolder = '';

        if ($targetFolder !== null && $targetFolder !== '') {
            // Validate and sanitize the target folder
            $subFolder = $this->sanitizePath($targetFolder);
            if ($subFolder === null) {
                return $this->error('Invalid target folder');
            }
        } elseif ($useDate) {
            $subFolder = $this->getDateFolder();
        }

        $finalDir = $this->mediaPath;
        if ($subFolder !== '') {
            $finalDir .= '/' . $subFolder;
        }

        // Create directory if it doesn't exist
        if (!is_dir($finalDir)) {
            if (!@mkdir($finalDir, 0755, true)) {
                return $this->error('Failed to create upload directory');
            }
            // Ensure directory is not executable
            @chmod($finalDir, 0755);
        }

        // Generate unique filename if duplicate exists
        $finalFilename = $this->getUniqueFilename($finalDir, $safeFilename);
        $finalPath = $finalDir . '/' . $finalFilename;

        // Verify path is within media directory (paranoid check)
        $realMediaPath = realpath($this->mediaPath);
        if ($realMediaPath === false) {
            return $this->error('Media directory does not exist');
        }

        // For new directories, check parent
        $checkDir = is_dir($finalDir) ? $finalDir : dirname($finalDir);
        $realFinalDir = realpath($checkDir);
        if ($realFinalDir === false || !str_starts_with($realFinalDir, $realMediaPath)) {
            return $this->error('Path traversal detected');
        }

        // Process the image to strip any embedded malicious content
        $processResult = $this->processImage($uploadedFile['tmp_name'], $finalPath, $mimeType);
        if (!$processResult['success']) {
            return $this->error($processResult['error']);
        }

        // Set secure file permissions (readable, not executable)
        @chmod($finalPath, 0644);

        // Calculate relative path and URL
        $relativePath = $subFolder !== '' ? $subFolder . '/' . $finalFilename : $finalFilename;
        
        // Build public URL
        $mediaUrlBase = $this->getMediaUrlBase();
        $url = $mediaUrlBase . '/' . $relativePath;

        return [
            'success' => true,
            'path' => $finalPath,
            'relative_path' => $relativePath,
            'url' => $url,
            'filename' => $finalFilename,
            'mime_type' => $mimeType,
            'size' => filesize($finalPath),
        ];
    }

    /**
     * Get the public URL base for media files.
     */
    private function getMediaUrlBase(): string
    {
        $mediaPath = $this->app->config('media.path', 'public/media');
        
        // If media is in public/, strip 'public' prefix for URL
        if (str_starts_with($mediaPath, 'public/')) {
            return '/' . substr($mediaPath, 7); // Remove 'public/' prefix
        }
        
        // Otherwise use full path as URL (may need web server config)
        return '/' . $mediaPath;
    }

    /**
     * Process image to strip malicious content.
     * Uses ImageMagick (if available) to reprocess the image, which strips
     * any non-image data that could be embedded for attacks.
     */
    private function processImage(string $sourcePath, string $destPath, string $mimeType): array
    {
        // SVG requires special handling
        if ($mimeType === 'image/svg+xml') {
            return $this->processSvg($sourcePath, $destPath);
        }

        // Try ImageMagick first (most secure - rewrites entire file)
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                
                // Set resource limits to prevent DoS
                $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
                $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
                $imagick->setResourceLimit(\Imagick::RESOURCETYPE_TIME, 30);
                
                $imagick->readImage($sourcePath);
                
                // Strip ALL metadata and profiles (EXIF, IPTC, XMP, ICC, etc.)
                // This removes any potentially malicious embedded data
                $imagick->stripImage();
                
                // Remove any embedded thumbnails
                $imagick->setImageProperty('exif:*', '');
                
                // Set appropriate compression
                switch ($mimeType) {
                    case 'image/jpeg':
                        $imagick->setImageFormat('jpeg');
                        $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                        $imagick->setImageCompressionQuality(90);
                        $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
                        break;
                    case 'image/png':
                        $imagick->setImageFormat('png');
                        $imagick->setImageCompression(\Imagick::COMPRESSION_ZIP);
                        break;
                    case 'image/gif':
                        $imagick->setImageFormat('gif');
                        break;
                    case 'image/webp':
                        $imagick->setImageFormat('webp');
                        break;
                    case 'image/avif':
                        $imagick->setImageFormat('avif');
                        break;
                }
                
                // Write the clean image
                $imagick->writeImage($destPath);
                $imagick->destroy();
                
                return ['success' => true];
            } catch (\ImagickException $e) {
                // If ImageMagick fails, the file is likely not a valid image
                return ['success' => false, 'error' => 'Invalid or corrupted image file'];
            }
        }

        // Fallback to GD (less thorough but still rewrites the image)
        if (extension_loaded('gd')) {
            return $this->processWithGd($sourcePath, $destPath, $mimeType);
        }

        // No image processing available - REJECT for security
        // We don't allow uploads without image processing as this would be insecure
        return [
            'success' => false,
            'error' => 'Server requires ImageMagick or GD extension for secure uploads'
        ];
    }

    /**
     * Process image using GD library as fallback.
     */
    private function processWithGd(string $sourcePath, string $destPath, string $mimeType): array
    {
        // Load the image based on type
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourcePath) : false,
            default => false,
        };

        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to read image file'];
        }

        // Create a new clean image (strips all metadata)
        $width = imagesx($image);
        $height = imagesy($image);

        // For PNG/GIF, preserve transparency
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagesavealpha($image, true);
        }

        // Save the reprocessed image
        $result = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $destPath, 90),
            'image/png' => imagepng($image, $destPath, 6),
            'image/gif' => imagegif($image, $destPath),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $destPath, 90) : false,
            'image/avif' => function_exists('imageavif') ? imageavif($image, $destPath, 90) : false,
            default => false,
        };

        imagedestroy($image);

        if (!$result) {
            return ['success' => false, 'error' => 'Failed to save processed image'];
        }

        return ['success' => true];
    }

    /**
     * Process SVG files with strict sanitization.
     * SVGs are XML and can contain scripts - we must sanitize carefully.
     */
    private function processSvg(string $sourcePath, string $destPath): array
    {
        $content = @file_get_contents($sourcePath);
        if ($content === false) {
            return ['success' => false, 'error' => 'Failed to read SVG file'];
        }

        // Check for maximum size (SVGs can be decompression bombs)
        if (strlen($content) > 2 * 1024 * 1024) { // 2MB limit for SVG
            return ['success' => false, 'error' => 'SVG file too large'];
        }

        // Attempt to parse as XML to verify structure
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;
        
        // Disable external entities and network access
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        
        if (!$dom->loadXML($content, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOCDATA)) {
            libxml_clear_errors();
            return ['success' => false, 'error' => 'Invalid SVG XML structure'];
        }
        libxml_clear_errors();

        // Verify root element is svg
        $root = $dom->documentElement;
        if ($root === null || strtolower($root->localName) !== 'svg') {
            return ['success' => false, 'error' => 'File is not a valid SVG'];
        }

        // Remove dangerous elements and attributes
        $this->sanitizeSvgNode($dom, $dom->documentElement);

        // Save sanitized SVG
        $sanitized = $dom->saveXML();
        if ($sanitized === false) {
            return ['success' => false, 'error' => 'Failed to serialize sanitized SVG'];
        }

        if (@file_put_contents($destPath, $sanitized) === false) {
            return ['success' => false, 'error' => 'Failed to write sanitized SVG'];
        }

        return ['success' => true];
    }

    /**
     * Recursively sanitize SVG DOM nodes.
     */
    private function sanitizeSvgNode(\DOMDocument $dom, \DOMNode $node): void
    {
        // Dangerous elements that can execute code or load external resources
        $dangerousElements = [
            'script', 'iframe', 'object', 'embed', 'applet',
            'foreignobject', 'use', // use can reference external files
            'animate', 'animatemotion', 'animatetransform', 'set', // animation can trigger scripts
        ];

        // Dangerous attributes
        $dangerousAttrs = [
            'onload', 'onerror', 'onclick', 'onmouseover', 'onmouseout',
            'onmouseenter', 'onmouseleave', 'onmousemove', 'onmousedown', 'onmouseup',
            'onfocus', 'onblur', 'onchange', 'onsubmit', 'onreset',
            'onkeydown', 'onkeyup', 'onkeypress',
            'onabort', 'oncanplay', 'oncanplaythrough', 'ondurationchange',
            'onemptied', 'onended', 'oninput', 'oninvalid', 'onloadeddata',
            'onloadedmetadata', 'onloadstart', 'onpause', 'onplay', 'onplaying',
            'onprogress', 'onratechange', 'onseeked', 'onseeking', 'onstalled',
            'onsuspend', 'ontimeupdate', 'onvolumechange', 'onwaiting',
            'onbegin', 'onend', 'onrepeat', // SMIL animation events
        ];

        // Remove dangerous child elements
        $toRemove = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tagName = strtolower($child->localName);
                if (in_array($tagName, $dangerousElements, true)) {
                    $toRemove[] = $child;
                } else {
                    $this->sanitizeSvgNode($dom, $child);
                }
            }
        }
        foreach ($toRemove as $child) {
            $node->removeChild($child);
        }

        // Remove dangerous attributes
        if ($node instanceof \DOMElement) {
            $attrsToRemove = [];
            foreach ($node->attributes as $attr) {
                $attrName = strtolower($attr->nodeName);
                $attrValue = strtolower(trim($attr->nodeValue));

                // Remove event handlers
                if (in_array($attrName, $dangerousAttrs, true) || str_starts_with($attrName, 'on')) {
                    $attrsToRemove[] = $attr->nodeName;
                    continue;
                }

                // Check href/xlink:href for javascript:
                if (in_array($attrName, ['href', 'xlink:href'], true)) {
                    if (str_starts_with($attrValue, 'javascript:') || 
                        str_starts_with($attrValue, 'data:') ||
                        str_starts_with($attrValue, 'vbscript:')) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                }

                // Check style for dangerous values
                if ($attrName === 'style') {
                    if (preg_match('/expression\s*\(|javascript:|url\s*\(\s*["\']?\s*data:/i', $attrValue)) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                }
            }
            foreach ($attrsToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }
        }
    }

    /**
     * Detect and validate MIME type using multiple methods.
     */
    private function detectAndValidateMimeType(string $filePath): array
    {
        $detectedTypes = [];

        // Method 1: PHP's fileinfo extension (most reliable)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime !== false) {
                $detectedTypes['finfo'] = $mime;
            }
        }

        // Method 2: getimagesize() for images (validates actual image data)
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo !== false && isset($imageInfo['mime'])) {
            $detectedTypes['getimagesize'] = $imageInfo['mime'];
        }

        // Method 3: mime_content_type() as fallback
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
            if ($mime !== false) {
                $detectedTypes['mime_content_type'] = $mime;
            }
        }

        // We need at least one detection method to work
        if (empty($detectedTypes)) {
            return ['valid' => false, 'error' => 'Unable to determine file type'];
        }

        // For images (except SVG), getimagesize should succeed
        $isImage = false;
        $finalMime = null;

        foreach ($detectedTypes as $method => $mime) {
            if (in_array($mime, $this->allowedTypes, true)) {
                $isImage = true;
                $finalMime = $mime;
                break;
            }
        }

        if (!$isImage) {
            $detected = implode(', ', array_unique($detectedTypes));
            return [
                'valid' => false,
                'error' => "File type not allowed. Detected: {$detected}"
            ];
        }

        // For non-SVG images, getimagesize MUST succeed (ensures actual image data)
        if ($finalMime !== 'image/svg+xml' && !isset($detectedTypes['getimagesize'])) {
            return [
                'valid' => false,
                'error' => 'File does not contain valid image data'
            ];
        }

        // All detection methods should agree (if multiple are available)
        $uniqueTypes = array_unique($detectedTypes);
        if (count($uniqueTypes) > 1) {
            // Allow some flexibility for closely related types
            $normalized = array_map(fn($t) => $this->normalizeMime($t), $uniqueTypes);
            if (count(array_unique($normalized)) > 1) {
                return [
                    'valid' => false,
                    'error' => 'Inconsistent file type detection - possible manipulation'
                ];
            }
        }

        return ['valid' => true, 'mime' => $finalMime];
    }

    /**
     * Normalize MIME type for comparison (handle variations).
     */
    private function normalizeMime(string $mime): string
    {
        // Some variations that are equivalent
        return match ($mime) {
            'image/jpg' => 'image/jpeg',
            'image/svg' => 'image/svg+xml',
            default => $mime,
        };
    }

    /**
     * Verify file magic bytes match the declared MIME type.
     */
    private function verifyMagicBytes(string $filePath, string $mimeType): bool
    {
        if (!isset(self::MAGIC_BYTES[$mimeType])) {
            // SVG and some types don't have simple magic bytes
            return true;
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            return false;
        }

        // Special case for WebP (RIFF....WEBP)
        if ($mimeType === 'image/webp') {
            return str_starts_with($header, 'RIFF') && 
                   (strlen($header) >= 12 && substr($header, 8, 4) === 'WEBP');
        }

        // Check against known signatures
        foreach (self::MAGIC_BYTES[$mimeType] as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize filename for safe storage.
     *
     * This function implements comprehensive filename security:
     * - Strips path components (prevents path traversal)
     * - Removes control characters
     * - Removes Unicode direction override characters
     * - Limits to safe ASCII characters
     * - Handles Windows reserved names
     * - Enforces length limits
     * - Forces the correct extension based on detected MIME type
     */
    public function sanitizeFilename(string $original, string $forcedExtension): string
    {
        // 1) Extract basename only (drop any directory path)
        $name = basename($original);

        // 2) Strip control characters (0x00-0x1F, 0x7F)
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        // 3) Remove Unicode bidirectional override characters (used in "RLO" attacks)
        // These can make malicious.exe appear as malicious.jpg in some contexts
        $name = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $name);

        // 4) Remove null bytes (belt and suspenders)
        $name = str_replace("\0", '', $name);

        // 5) Extract base name without extension (we'll force the correct extension)
        $base = pathinfo($name, PATHINFO_FILENAME);

        // 6) Check for dangerous patterns in the original name (before sanitization)
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                // If dangerous pattern found, use generic name
                $base = 'image';
                break;
            }
        }

        // 7) Allowlist characters: only alphanumeric, spaces, underscores, hyphens, periods
        $base = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $base);

        // 8) Normalize consecutive spaces, dots, and dashes
        $base = preg_replace('/[ ]+/', ' ', $base);
        $base = preg_replace('/[.-]{2,}/', '-', $base);

        // 9) Trim whitespace and special chars from edges
        $base = trim($base, " .-_");

        // 10) Avoid hidden files (starting with .)
        $base = ltrim($base, '.');

        // 11) Length limit (leave room for extension and dedup number)
        $base = mb_substr($base, 0, 80);

        // 12) Fallback for empty names
        if ($base === '') {
            $base = 'image';
        }

        // 13) Avoid Windows reserved names
        if (in_array(strtoupper($base), self::WINDOWS_RESERVED, true)) {
            $base = 'image-' . $base;
        }

        // 14) Convert to lowercase for consistency
        $base = strtolower($base);

        // 15) Convert spaces to hyphens
        $base = str_replace(' ', '-', $base);

        // 16) Apply the forced extension (based on MIME type, not user input)
        return $base . '.' . $forcedExtension;
    }

    /**
     * Sanitize and validate a subfolder path.
     *
     * @param string $path The requested path
     * @return string|null The sanitized path, or null if invalid
     */
    private function sanitizePath(string $path): ?string
    {
        // Reject empty paths
        if (trim($path) === '') {
            return null;
        }

        // Normalize separators
        $path = str_replace('\\', '/', $path);

        // Split into segments
        $segments = explode('/', $path);
        $cleanSegments = [];

        foreach ($segments as $segment) {
            // Skip empty segments
            if ($segment === '' || $segment === '.') {
                continue;
            }

            // Reject parent directory traversal
            if ($segment === '..') {
                return null;
            }

            // Reject hidden directories
            if (str_starts_with($segment, '.')) {
                return null;
            }

            // Reject control characters
            if (preg_match('/[\x00-\x1F\x7F]/', $segment)) {
                return null;
            }

            // Reject dangerous patterns
            foreach (self::DANGEROUS_PATTERNS as $pattern) {
                if (preg_match($pattern, $segment)) {
                    return null;
                }
            }

            // Sanitize segment: only allow safe characters
            $segment = preg_replace('/[^A-Za-z0-9_-]/', '', $segment);

            if ($segment !== '') {
                $cleanSegments[] = $segment;
            }
        }

        if (empty($cleanSegments)) {
            return null;
        }

        $cleanPath = implode('/', $cleanSegments);

        // Final validation: ensure the resulting path doesn't escape media directory
        // by checking for any remaining traversal attempts
        if (preg_match('/\.\./', $cleanPath)) {
            return null;
        }

        return $cleanPath;
    }

    /**
     * Generate a unique filename in the target directory.
     * Appends numbers if file already exists: image.jpg -> image-1.jpg -> image-2.jpg
     */
    private function getUniqueFilename(string $directory, string $filename): string
    {
        $pathInfo = pathinfo($filename);
        $base = $pathInfo['filename'];
        $ext = $pathInfo['extension'] ?? '';

        $candidate = $filename;
        $counter = 0;
        $maxAttempts = 1000; // Prevent infinite loop

        while (file_exists($directory . '/' . $candidate) && $counter < $maxAttempts) {
            $counter++;
            $candidate = $base . '-' . $counter . ($ext !== '' ? '.' . $ext : '');
        }

        if ($counter >= $maxAttempts) {
            // Use timestamp as fallback
            $candidate = $base . '-' . time() . ($ext !== '' ? '.' . $ext : '');
        }

        return $candidate;
    }

    /**
     * Check for PHP upload errors.
     */
    private function checkUploadError(int $errorCode): ?string
    {
        return match ($errorCode) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE form directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension',
            default => 'Unknown upload error',
        };
    }

    /**
     * Create error response array.
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    /**
     * Get upload statistics and limits.
     */
    public function getUploadLimits(): array
    {
        $phpMaxUpload = $this->parseIniSize(ini_get('upload_max_filesize') ?: '2M');
        $phpMaxPost = $this->parseIniSize(ini_get('post_max_size') ?: '8M');
        $configuredMax = $this->maxFileSize;

        // Effective limit is the minimum of all limits
        $effectiveMax = min($phpMaxUpload, $phpMaxPost, $configuredMax);

        return [
            'max_file_size' => $effectiveMax,
            'max_file_size_formatted' => $this->formatBytes($effectiveMax),
            'php_upload_max' => $phpMaxUpload,
            'php_post_max' => $phpMaxPost,
            'configured_max' => $configuredMax,
            'allowed_types' => $this->allowedTypes,
            'allowed_extensions' => array_values(array_filter(
                self::MIME_TO_EXTENSION,
                fn($mime) => in_array($mime, $this->allowedTypes, true),
                ARRAY_FILTER_USE_KEY
            )),
            'has_imagick' => extension_loaded('imagick'),
            'has_gd' => extension_loaded('gd'),
        ];
    }

    /**
     * Parse PHP ini size value to bytes.
     */
    private function parseIniSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1] ?? '');
        $value = (int) $size;

        return match ($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Format bytes to human readable string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
     * List files in a directory with metadata.
     * 
     * @param string|null $subfolder Optional subfolder within media directory
     * @return array List of files with metadata
     */
    public function listFiles(?string $subfolder = null): array
    {
        $targetDir = $this->mediaPath;
        
        if ($subfolder !== null && $subfolder !== '') {
            $cleanPath = $this->sanitizePath($subfolder);
            if ($cleanPath === null) {
                return [];
            }
            $targetDir .= '/' . $cleanPath;
        }

        if (!is_dir($targetDir)) {
            return [];
        }

        $files = [];
        $items = @scandir($targetDir);
        
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
                continue;
            }

            $fullPath = $targetDir . '/' . $item;
            
            if (is_file($fullPath)) {
                $relativePath = $subfolder ? $subfolder . '/' . $item : $item;
                
                // Get file info
                $stat = @stat($fullPath);
                $imageInfo = @getimagesize($fullPath);
                
                $files[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'url' => $this->getMediaUrlBase() . '/' . $relativePath,
                    'size' => $stat['size'] ?? 0,
                    'modified' => $stat['mtime'] ?? 0,
                    'width' => $imageInfo[0] ?? null,
                    'height' => $imageInfo[1] ?? null,
                    'mime' => $imageInfo['mime'] ?? mime_content_type($fullPath),
                ];
            }
        }

        // Sort by modified time descending
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);

        return $files;
    }
}
