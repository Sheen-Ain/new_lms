<?php

namespace App\Support;

/**
 * Uploader — one place for every file that enters the system.
 *
 * Directory layout mirrors V1 so existing database paths keep resolving:
 *   uploads/topics/{topic_slug}/{file}
 *   uploads/assignments/{assignment_slug}/{file}
 *   uploads/submissions/{assignment_slug}/{00012_first_file}
 *   uploads/profiles/{00012_full_name}/profile.ext
 */
class Uploader
{
    /**
     * Validate an uploaded file.
     *
     * @return array{ok:bool,error?:string,ext?:string,size?:int,name?:string,mime?:string}
     */
    public static function validate($file, array $allowed = null, $maxMb = null)
    {
        $allowed = $allowed === null ? ALLOWED_FILE_EXTENSIONS : $allowed;
        $maxMb = $maxMb === null ? UPLOAD_MAX_MB : $maxMb;

        if (!is_array($file) || !isset($file['error'])) {
            return ['ok' => false, 'error' => 'No file was received.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['ok' => false, 'error' => 'No file was selected.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['ok' => false, 'error' => 'The file exceeds the allowed upload size.'];
            case UPLOAD_ERR_PARTIAL:
                return ['ok' => false, 'error' => 'The file was only partially uploaded. Please retry.'];
            default:
                return ['ok' => false, 'error' => 'Upload failed due to a server error.'];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload source.'];
        }

        $size = (int) $file['size'];
        if ($size <= 0) {
            return ['ok' => false, 'error' => 'The uploaded file is empty.'];
        }
        if ($size > $maxMb * 1024 * 1024) {
            return ['ok' => false, 'error' => 'File is too large. Maximum allowed size is ' . $maxMb . ' MB.'];
        }

        $name = (string) $file['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, array_map('strtolower', $allowed), true)) {
            return ['ok' => false, 'error' => 'This file type is not allowed (.' . ($ext !== '' ? $ext : 'unknown') . ').'];
        }

        // Block dangerous double extensions such as shell.php.jpg
        if (preg_match('/\.(php\d?|phtml|phar|cgi|pl|asp|aspx|jsp|exe|dll|bat|cmd|sh)\./i', $name)) {
            return ['ok' => false, 'error' => 'This filename is not permitted.'];
        }

        // Reject images that are not really images.
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'], true)
            && @getimagesize($file['tmp_name']) === false) {
            return ['ok' => false, 'error' => 'The uploaded image appears to be corrupted.'];
        }

        return [
            'ok' => true,
            'ext' => $ext,
            'size' => $size,
            'name' => $name,
            'mime' => self::mime($file['tmp_name'], $ext),
        ];
    }

    /**
     * Move an uploaded file into the uploads tree.
     *
     * @return string|null stored relative path (e.g. "python_first_week/class2nd.py")
     */
    public static function store($file, $context, array $meta = [])
    {
        $validation = self::validate(
            $file,
            isset($meta['allowed']) ? $meta['allowed'] : ALLOWED_FILE_EXTENSIONS,
            isset($meta['max_mb']) ? $meta['max_mb'] : UPLOAD_MAX_MB
        );
        if (!$validation['ok']) {
            return null;
        }

        $target = self::buildTarget($file['name'], $context, $meta);
        $directory = UPLOAD_PATH . '/' . $target['directory'];

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        if (!empty($meta['old_path'])) {
            self::deleteFile(UPLOAD_PATH . '/' . $context . '/' . ltrim($meta['old_path'], '/'));
        }

        $destination = $directory . '/' . $target['filename'];
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return null;
        }
        @chmod($destination, 0644);

        return $target['directory'] . '/' . $target['filename'];
    }

    /** Compute the folder + filename for a context. */
    private static function buildTarget($originalName, $context, array $meta)
    {
        $original = $originalName;
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        switch ($context) {
            case 'submissions':
                $idLabel = !empty($meta['student_id_number'])
                    ? $meta['student_id_number']
                    : padded_id(isset($meta['student_id']) ? $meta['student_id'] : 0);
                $nameParts = isset($meta['student_name']) ? explode(' ', trim($meta['student_name'])) : ['student'];
                $firstName = isset($nameParts[0]) && $nameParts[0] !== '' ? $nameParts[0] : 'student';
                $slug = slugify(isset($meta['assignment_title']) ? $meta['assignment_title'] : 'assignment', 'assignment');
                $folder = 'submissions/' . $slug;
                $filename = $idLabel . '_' . slugify($firstName, 'student')
                    . '_' . slugify(pathinfo($original, PATHINFO_FILENAME), 'file') . '.' . $ext;
                return ['directory' => $folder, 'filename' => self::unique($folder, $filename)];

            case 'profiles':
                $idLabel = !empty($meta['user_id_number'])
                    ? $meta['user_id_number']
                    : padded_id(isset($meta['user_id']) ? $meta['user_id'] : 0);
                $folder = 'profiles/' . $idLabel . '_' . slugify(isset($meta['user_name']) ? $meta['user_name'] : 'user', 'user');
                return ['directory' => $folder, 'filename' => 'profile.' . $ext];

            case 'assignments':
                $folder = 'assignments/' . slugify(isset($meta['assignment_title']) ? $meta['assignment_title'] : 'assignment', 'assignment');
                return ['directory' => $folder, 'filename' => self::unique($folder, self::cleanName($original))];

            case 'topics':
            default:
                $folder = 'topics/' . slugify(isset($meta['topic_title']) ? $meta['topic_title'] : 'topic', 'topic');
                return ['directory' => $folder, 'filename' => self::unique($folder, self::cleanName($original))];
        }
    }

    /** Sanitise a user supplied filename while keeping it recognisable. */
    private static function cleanName($original)
    {
        $name = preg_replace('/[^A-Za-z0-9._\- ]/', '_', (string) $original);
        $name = str_replace(' ', '_', trim($name));
        $name = ltrim($name, '.');
        return $name !== '' ? substr($name, 0, 120) : 'file';
    }

    private static function unique($directory, $filename)
    {
        $path = UPLOAD_PATH . '/' . $directory . '/' . $filename;
        if (!is_file($path)) {
            return $filename;
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;
        do {
            $candidate = $base . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
            $counter++;
        } while (is_file(UPLOAD_PATH . '/' . $directory . '/' . $candidate));

        return $candidate;
    }

    /** Best-effort MIME detection. */
    public static function mime($path, $ext = '')
    {
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if ($detected) {
                return $detected;
            }
        }

        $map = [
            'pdf' => 'application/pdf', 'zip' => 'application/zip',
            'txt' => 'text/plain', 'csv' => 'text/csv', 'json' => 'application/json',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg', 'webm' => 'video/webm',
        ];

        return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
    }

    /** Resolve a stored relative path to an absolute path (traversal safe). */
    public static function resolve($relative)
    {
        $relative = str_replace(['\\', '..'], ['/', ''], (string) $relative);
        $relative = ltrim($relative, '/');
        if ($relative === '') {
            return null;
        }

        $candidates = [UPLOAD_PATH . '/' . $relative];
        foreach (['topics', 'assignments', 'submissions', 'profiles'] as $context) {
            $candidates[] = UPLOAD_PATH . '/' . $context . '/' . $relative;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /** Delete a stored file (only inside the uploads tree). */
    public static function deleteFile($absolutePath)
    {
        if ($absolutePath && is_file($absolutePath)) {
            $real = realpath($absolutePath);
            $root = realpath(UPLOAD_PATH);
            if ($real && $root && strpos($real, $root) === 0) {
                return @unlink($real);
            }
        }
        return false;
    }
}

