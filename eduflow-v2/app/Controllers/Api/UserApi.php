<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * UserApi — admin user management.
 */
class UserApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId() || !$this->isAdmin()) {
            $this->error('Access denied.', [], 403);
        }
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 15)));
        $search = $this->string('search');
        $role = $this->string('role');
        $status = $this->string('status');
        $verified = $this->string('verified');
        $sortCol = in_array($this->string('sort_col'), ['full_name', 'email', 'status', 'last_seen', 'created_at'], true)
            ? $this->string('sort_col') : 'created_at';
        $sortDir = $this->string('sort_dir') === 'asc' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.user_id_number LIKE ?)';
            $params[] = Database::like($search);
            $params[] = Database::like($search);
            $params[] = Database::like($search);
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($verified === '0' || $verified === '1') {
            $where[] = 'u.is_verified = ?';
            $params[] = (int) $verified;
        }
        if ($role !== '') {
            $where[] = 'u.id IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.name = ?)';
            $params[] = $role;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(DISTINCT u.id) FROM users u $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT u.id, u.full_name, u.email, u.gender, u.status, u.is_verified, u.user_id_number,
                    u.phone, u.last_seen, u.is_online, u.created_at, u.`current_role`, u.bypass_gate,
                    u.profile_picture, GROUP_CONCAT(r.name ORDER BY r.id) AS all_roles
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             $whereSql
             GROUP BY u.id
             ORDER BY u.$sortCol $sortDir
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('user_id');
        $row = Database::first(
            "SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id) AS all_roles
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ? GROUP BY u.id",
            [$id]
        );
        if (!$row) {
            return $this->error('User not found.');
        }
        return $this->success('OK', ['user' => $row]);
    }

    public function create()
    {
        $roles = $this->input('roles', []);
        if (is_string($roles)) {
            $roles = array_filter(array_map('trim', explode(',', $roles)));
        }
        $roles = array_values(array_intersect(['student', 'teacher', 'admin'], array_unique((array) $roles)));
        if (!$roles) {
            $roles = [$this->string('role', 'student')];
        }
        $data = [
            'full_name' => $this->string('full_name'),
            'email' => strtolower($this->string('email')),
            'password' => (string) $this->input('password', ''),
            'gender' => $this->string('gender'),
            'phone' => $this->string('phone'),
            'cnic' => $this->string('cnic'),
            'role' => $roles[0],
            'status' => $this->string('status', 'active'),
            'is_verified' => $this->bool('is_verified') ? 1 : 0,
        ];

        $validator = Validator::make($data, [
            'full_name' => 'required|min:3|max:150',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|password',
            'gender' => 'required|in:male,female,other',
            'role' => 'required|in:student,teacher,admin',
            'status' => 'in:active,inactive',
        ], ['full_name' => 'Full name', 'role' => 'Role']);

        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        $userId = Database::insert(
            "INSERT INTO users (full_name, email, password, cnic, gender, phone, status, is_verified, `current_role`, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['full_name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
                $data['cnic'] !== '' ? format_cnic($data['cnic']) : null,
                $data['gender'],
                $data['phone'] !== '' ? $data['phone'] : null,
                $data['status'],
                $data['is_verified'],
                $data['role'],
            ]
        );

        foreach ($roles as $role) {
            $roleId = (int) Database::value('SELECT id FROM roles WHERE name = ?', [$role]);
            Database::write('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)', [$userId, $roleId, $this->userId()]);
        }

        Activity::log('Created user: ' . $data['full_name'], 'users');
        return $this->success('User created.', ['user_id' => $userId]);
    }

    public function update()
    {
        $id = $this->int('user_id');
        if (!$id) {
            return $this->error('Invalid user.');
        }

        $name = $this->string('full_name');
        $email = strtolower($this->string('email'));
        $gender = $this->string('gender');
        $phone = $this->string('phone');
        $cnic = $this->string('cnic');
        $status = $this->string('status');
        $isVerified = $this->bool('is_verified') ? 1 : 0;
        $password = (string) $this->input('password', '');
        $rolesInput = $this->input('roles', null);

        $validator = Validator::make(
            ['full_name' => $name, 'email' => $email, 'phone' => $phone, 'status' => $status],
            ['full_name' => 'required|min:3|max:150', 'email' => 'required|email|unique:users,email,' . $id, 'phone' => 'phone', 'status' => 'in:active,inactive'],
            ['full_name' => 'Full name']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }
        if ($password !== '') {
            $passwordValidator = Validator::make(['password' => $password], ['password' => 'password']);
            if ($passwordValidator->fails()) {
                return $this->error($passwordValidator->firstMessage(), ['errors' => $passwordValidator->errors()], 422);
            }
        }

        Database::write(
            'UPDATE users SET full_name = ?, email = ?, gender = ?, phone = ?, cnic = ?, status = ?, is_verified = ? WHERE id = ?',
            [$name, $email, $gender !== '' ? $gender : null, $phone !== '' ? $phone : null, $cnic !== '' ? format_cnic($cnic) : null, $status !== '' ? $status : 'active', $isVerified, $id]
        );
        if ($password !== '') {
            Database::write('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $id]);
        }

        if ($rolesInput !== null) {
            if (is_string($rolesInput)) {
                $rolesInput = array_filter(array_map('trim', explode(',', $rolesInput)));
            }
            $roles = array_values(array_intersect(['student', 'teacher', 'admin'], array_unique((array) $rolesInput)));
            if (!$roles) {
                return $this->error('Assign at least one role.');
            }
            if ($id === (int) $this->userId() && !in_array('admin', $roles, true)) {
                return $this->error('You cannot remove your own administrator role.');
            }

            $roleIds = [];
            foreach ($roles as $role) {
                $roleId = (int) Database::value('SELECT id FROM roles WHERE name = ?', [$role]);
                if ($roleId) $roleIds[] = $roleId;
            }
            Database::begin();
            try {
                Database::write('DELETE FROM user_roles WHERE user_id = ?', [$id]);
                foreach ($roleIds as $roleId) {
                    Database::write('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)', [$id, $roleId, $this->userId()]);
                }
                $currentRole = Auth::role();
                $primaryRole = in_array($currentRole, $roles, true) ? $currentRole : $roles[0];
                Database::write('UPDATE users SET `current_role` = ? WHERE id = ?', [$primaryRole, $id]);
                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                return $this->error('Could not update user roles.');
            }
        } else {
            $primaryRole = $this->string('role');
            if ($primaryRole === '') return $this->success('User updated.');
            $roleId = (int) Database::value('SELECT id FROM roles WHERE name = ?', [$primaryRole]);
            if ($roleId) {
                Database::write('INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)', [$id, $roleId, $this->userId()]);
                Database::write('UPDATE users SET `current_role` = ? WHERE id = ?', [$primaryRole, $id]);
            }
        }

        Activity::log('Updated user: ' . $name, 'users');
        return $this->success('User updated.');
    }

    public function delete()
    {
        $id = $this->int('user_id');
        if ($id === (int) $this->userId()) {
            return $this->error('You cannot delete your own account.');
        }
        if ($id === 1) {
            return $this->error('The primary administrator account cannot be deleted.');
        }

        $user = Database::first('SELECT id, full_name FROM users WHERE id = ?', [$id]);
        if (!$user) {
            return $this->error('User not found.');
        }

        Database::write('DELETE FROM user_roles WHERE user_id = ?', [$id]);
        Database::write('DELETE FROM users WHERE id = ?', [$id]);

        Activity::log('Deleted user: ' . $user['full_name'], 'users');
        return $this->success('User deleted.');
    }

    public function status()
    {
        $id = $this->int('user_id');
        $status = $this->string('status');
        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->error('Invalid status.');
        }
        if ($id === (int) $this->userId()) {
            return $this->error('You cannot change your own status.');
        }

        Database::write('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
        Activity::log('Changed user #' . $id . ' status to ' . $status, 'users');
        return $this->success('Status updated.', ['status' => $status]);
    }

    public function bypass()
    {
        $id = $this->int('user_id');
        $enabled = $this->bool('bypass_gate') ? 1 : 0;
        $user = Database::first(
            "SELECT u.id, u.full_name FROM users u
             WHERE u.id = ? AND EXISTS (
                 SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = u.id AND r.name = 'student'
             )",
            [$id]
        );
        if (!$user) {
            return $this->error('Student account not found.');
        }

        Database::write('UPDATE users SET bypass_gate = ? WHERE id = ?', [$enabled, $id]);
        Activity::log(($enabled ? 'Enabled' : 'Disabled') . ' dashboard bypass for ' . $user['full_name'], 'users');
        return $this->success('Dashboard access updated.', ['bypass_gate' => $enabled]);
    }
}

