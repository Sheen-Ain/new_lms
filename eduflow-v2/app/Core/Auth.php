<?php

namespace App\Core;

/**
 * Auth — authentication, session identity, role checks and role switching.
 */
class Auth
{
    /** @var array|null cached user row for the current request */
    private static $user = null;
    private static $loaded = false;

    /* ── Identity ──────────────────────────────────────────────── */

    public static function check()
    {
        return self::user() !== null;
    }

    public static function id()
    {
        $user = self::user();
        return $user ? (int) $user['id'] : 0;
    }

    /** Current user with roles[] attached, or null. */
    public static function user()
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if (!$id) {
            self::$user = null;
            return null;
        }

        $user = Database::first(
            'SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id) AS all_roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.id = ? GROUP BY u.id',
            [$id]
        );

        if (!$user || $user['status'] !== 'active') {
            self::logout(false);
            self::$user = null;
            return null;
        }

        $user['roles'] = array_values(array_filter(explode(',', (string) $user['all_roles'])));
        // current_role may be stale after a role change; keep it truthful.
        if (!in_array($user['current_role'], $user['roles'], true)) {
            $user['current_role'] = $user['roles'] ? $user['roles'][0] : 'student';
            Database::write('UPDATE users SET current_role = ? WHERE id = ?', [$user['current_role'], $id]);
        }
        $_SESSION['role'] = $user['current_role'];

        self::$user = $user;
        self::touch($user);
        return self::$user;
    }

    public static function role()
    {
        $user = self::user();
        return $user ? $user['current_role'] : null;
    }

    public static function hasRole($role)
    {
        $user = self::user();
        return $user && in_array($role, $user['roles'], true);
    }

    public static function isAdmin()
    {
        return self::role() === 'admin';
    }

    public static function isTeacher()
    {
        return self::role() === 'teacher';
    }

    public static function isStudent()
    {
        return self::role() === 'student';
    }

    /** Home route for the active role. */
    public static function homeFor($role = null)
    {
        $role = $role ?: self::role();
        if ($role === 'admin') {
            return '/admin';
        }
        if ($role === 'teacher') {
            return '/teacher';
        }
        return '/student';
    }

    /* ── Attempt / login / logout ──────────────────────────────── */

    /**
     * Look up a user by email or 5-digit student id while verifying the
     * password. Returns the raw user row (with roles) or null.
     */
    public static function attempt($identifier, $password)
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        $field = preg_match('/^\d{5}$/', $identifier) ? 'user_id_number' : 'email';
        $user = Database::first(
            'SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id) AS all_roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.' . $field . ' = ? GROUP BY u.id',
            [$identifier]
        );

        if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
            return null;
        }

        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
            Database::write('UPDATE users SET password = ? WHERE id = ?', [
                password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
                $user['id'],
            ]);
        }

        $user['roles'] = array_values(array_filter(explode(',', (string) $user['all_roles'])));
        return $user;
    }

    public static function login(array $user)
    {
        Session::regenerate();

        $activeRole = in_array($user['current_role'], $user['roles'], true)
            ? $user['current_role']
            : (isset($user['roles'][0]) ? $user['roles'][0] : 'student');

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $activeRole;
        $_SESSION['login_at'] = time();
        $_SESSION['_last_seen'] = time();
        $_SESSION['theme'] = isset($user['theme_preference']) ? $user['theme_preference'] : 'system';

        self::$loaded = false;
        self::$user = null;

        Database::write('UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?', [(int) $user['id']]);
        Activity::log('Signed in', 'auth');
    }

    public static function logout($flush = true)
    {
        $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($id) {
            Database::write('UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?', [$id]);
        }
        if ($flush) {
            Session::destroy();
        } else {
            Session::forget('user_id');
            Session::forget('role');
        }
        self::$loaded = true;
        self::$user = null;
    }

    /** Switch the active role for multi-role accounts. */
    public static function switchRole($role)
    {
        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            return false;
        }
        $user = self::user();
        if (!$user || !in_array($role, $user['roles'], true)) {
            return false;
        }
        Database::write('UPDATE users SET current_role = ? WHERE id = ?', [$role, $user['id']]);
        $_SESSION['role'] = $role;
        self::$loaded = false;
        self::$user = null;
        Activity::log('Switched role to ' . $role, 'profile');
        return true;
    }

    /** Keep presence fresh without writing on every request. */
    private static function touch(array $user)
    {
        $last = isset($_SESSION['_presence_at']) ? (int) $_SESSION['_presence_at'] : 0;
        if (time() - $last < 60) {
            return;
        }
        $_SESSION['_presence_at'] = time();
        Database::write('UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?', [(int) $user['id']]);
    }

    /* ── Login throttling ──────────────────────────────────────── */

    public static function throttleKey($identifier)
    {
        return 'login_throttle_' . md5(strtolower(trim($identifier)) . '|' . Request::ip());
    }

    public static function tooManyAttempts($identifier)
    {
        $bucket = Session::get(self::throttleKey($identifier), ['count' => 0, 'first' => time()]);
        if (time() - $bucket['first'] > LOGIN_WINDOW) {
            return false;
        }
        return $bucket['count'] >= LOGIN_MAX_ATTEMPTS;
    }

    public static function recordFailure($identifier)
    {
        $key = self::throttleKey($identifier);
        $bucket = Session::get($key, ['count' => 0, 'first' => time()]);
        if (time() - $bucket['first'] > LOGIN_WINDOW) {
            $bucket = ['count' => 0, 'first' => time()];
        }
        $bucket['count']++;
        Session::set($key, $bucket);
    }

    public static function clearFailures($identifier)
    {
        Session::forget(self::throttleKey($identifier));
    }
}

