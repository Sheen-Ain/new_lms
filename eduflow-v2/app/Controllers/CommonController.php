<?php

/**
 * CommonController — profile management, theme, role switching,
 * notifications feed, online presence, file viewer and download.
 */
namespace App\Controllers;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Support\Uploader;

class CommonController extends Controller
{
    /** Profile page (tabbed: details, password, appearance). */
    public function profile()
    {
        $user = Auth::user();
        $this->view('portal/profile', [
            'pageTitle' => 'My profile',
            'user' => $user,
        ]);
    }

    /** Update personal details. */
    public function updateProfile()
    {
        $user = Auth::user();
        $name = $this->string('full_name');
        $phone = $this->string('phone');
        $bio = $this->string('bio');
        $gender = $this->string('gender');

        $validator = Validator::make(
            ['full_name' => $name, 'phone' => $phone],
            ['full_name' => 'required|min:3|max:150', 'phone' => 'phone']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage());
        }

        // Pure students cannot change gender once registered.
        $isPureStudent = count($user['roles']) === 1 && $user['roles'][0] === 'student';
        $finalGender = $isPureStudent ? $user['gender'] : ($gender !== '' ? $gender : $user['gender']);

        Database::write(
            'UPDATE users SET full_name = ?, phone = ?, bio = ?, gender = ? WHERE id = ?',
            [$name, $phone !== '' ? $phone : null, $bio !== '' ? $bio : null, $finalGender, $user['id']]
        );

        Activity::log('Updated profile information', 'profile');
        return $this->success('Profile updated.');
    }

    /** Upload avatar image. */
    public function uploadAvatar()
    {
        $file = $this->file('avatar');
        if (!$file) {
            return $this->error('No image selected.');
        }

        $user = Auth::user();
        $saved = Uploader::store($file, 'profiles', [
            'user_id' => $user['id'],
            'user_name' => $user['full_name'],
            'user_id_number' => $user['user_id_number'],
            'old_path' => $user['profile_picture'],
            'max_mb' => AVATAR_MAX_MB,
            'allowed' => ['jpg', 'jpeg', 'png', 'webp'],
        ]);

        if (!$saved) {
            return $this->error('Could not save avatar. Check file size and type.');
        }

        $relPath = preg_replace('#^profiles/#', '', $saved);
        Database::write('UPDATE users SET profile_picture = ? WHERE id = ?', [$relPath, $user['id']]);

        Activity::log('Updated profile avatar', 'profile');
        return $this->success('Avatar updated.', [
            'url' => upload_url('profiles/' . $relPath),
        ]);
    }

    /** Change password while signed in. */
    public function changePassword()
    {
        $current = (string) $this->input('current_password', '');
        $new = (string) $this->input('new_password', '');
        $confirm = (string) $this->input('confirm_password', '');

        $user = Database::first('SELECT password FROM users WHERE id = ?', [Auth::id()]);
        if (!$user || !password_verify($current, $user['password'])) {
            return $this->error('Current password is incorrect.');
        }

        $validator = Validator::make(
            ['new_password' => $new, 'confirm_password' => $confirm],
            ['new_password' => 'required|password', 'confirm_password' => 'required|same:new_password'],
            ['confirm_password' => 'Password confirmation']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage());
        }

        Database::write(
            'UPDATE users SET password = ? WHERE id = ?',
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), Auth::id()]
        );

        Activity::log('Changed password', 'profile');
        return $this->success('Password changed successfully.');
    }

    /** Update theme preference (light/dark/system). */
    public function updateTheme()
    {
        $theme = $this->string('theme', 'system');
        if (!in_array($theme, ['light', 'dark', 'system'], true)) {
            $theme = 'system';
        }

        if (Auth::check()) {
            Database::write('UPDATE users SET theme_preference = ? WHERE id = ?', [$theme, Auth::id()]);
        }
        Session::set('theme', $theme);

        return $this->success('Theme preference saved.', ['theme' => $theme]);
    }

    /** Switch active role for multi-role accounts. */
    public function switchRole()
    {
        $role = $this->string('role');
        if (!Auth::switchRole($role)) {
            return $this->error('You do not have access to that role.');
        }

        return $this->success('Switched to ' . ucfirst($role) . ' role.', [
            'role' => $role,
            'redirect' => url(Auth::homeFor($role)),
        ]);
    }

    /** View file inline (modal content). */
    public function viewFile()
    {
        $path = $this->string('path');
        $name = $this->string('name');

        $absolute = Uploader::resolve($path);
        if (!$absolute) {
            return $this->error('File not found.');
        }

        $ext = file_ext($absolute);
        $size = filesize($absolute);

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return $this->success('Image preview', [
                'type' => 'image',
                'url' => upload_url($path),
                'name' => $name !== '' ? $name : basename($absolute),
                'size' => file_size_human($size),
            ]);
        }

        if ($ext === 'pdf') {
            return $this->success('PDF preview', [
                'type' => 'pdf',
                'url' => upload_url($path),
                'name' => $name !== '' ? $name : basename($absolute),
            ]);
        }

        if (in_array($ext, CODE_PREVIEW_EXTENSIONS, true)) {
            if ($size > 2 * 1024 * 1024) {
                return $this->error('File is too large to preview inline. Use the download button.');
            }
            $content = @file_get_contents($absolute);
            return $this->success('Text preview', [
                'type' => 'text',
                'content' => $content,
                'name' => $name !== '' ? $name : basename($absolute),
                'size' => file_size_human($size),
            ]);
        }

        return $this->error('Inline preview is not supported for this file type. Please download the file.');
    }

    /** Download an uploaded file. */
    public function downloadFile()
    {
        $path = $this->string('path');
        $name = $this->string('name');

        $absolute = Uploader::resolve($path);
        if (!$absolute) {
            http_response_code(404);
            exit('File not found.');
        }

        Response::download($absolute, $name !== '' ? $name : basename($absolute), Uploader::mime($absolute, file_ext($absolute)));
    }

    /** Presence list (sidebar classmates / batch members). */
    public function presence()
    {
        $userId = Auth::id();
        $batchId = $this->int('batch_id');

        $query = "SELECT u.id, u.full_name, u.profile_picture, u.is_online, u.last_seen,
                         u.user_id_number,
                         (CASE WHEN u.last_seen >= NOW() - INTERVAL 2 MINUTE THEN 1 ELSE 0 END) AS is_active
                  FROM users u
                  WHERE u.id <> ?";
        $params = [$userId];

        if ($batchId > 0) {
            $query .= " AND u.id IN (SELECT student_id FROM batch_students WHERE batch_id = ?)";
            $params[] = $batchId;
        }

        $query .= " ORDER BY is_active DESC, u.full_name ASC LIMIT 50";
        $users = Database::all($query, $params);

        return $this->success('Presence loaded.', ['users' => $users]);
    }

    /** Notifications / activity feed for the top bar. */
    public function notifications()
    {
        $feed = Activity::feed(Auth::id(), 10);
        return $this->success('Feed loaded.', ['feed' => $feed]);
    }
}



