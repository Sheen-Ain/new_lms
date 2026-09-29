<?php

/**
 * AuthController — registration, email verification, sign in, OTP
 * password reset and sign out.
 */
namespace App\Controllers;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\MailService;

class AuthController extends Controller
{
    /* ── Sign in ─────────────────────────────────────────────── */

    public function showLogin()
    {
        $this->publicView('auth/login', [
            'pageTitle' => 'Sign in',
            'next' => $this->string('next'),
            'hideChrome' => true,
        ], 'layouts/auth');
    }

    public function login()
    {
        $identifier = $this->string('identifier');
        $password = (string) $this->input('password', '');
        $next = $this->string('next');

        $validator = Validator::make(
            ['identifier' => $identifier, 'password' => $password],
            ['identifier' => 'required', 'password' => 'required'],
            ['identifier' => 'Email or student ID']
        );

        if ($validator->fails()) {
            $this->flashWithInput('error', $validator->firstMessage(), ['identifier' => $identifier]);
            return $this->redirect('/login', $next !== '' ? ['next' => $next] : []);
        }

        if (Auth::tooManyAttempts($identifier)) {
            Activity::log('Blocked sign-in attempts for ' . $identifier, 'auth');
            $this->flash('error', 'Too many failed attempts. Please wait a few minutes and try again.');
            return $this->redirect('/login');
        }

        $user = Auth::attempt($identifier, $password);

        if (!$user) {
            Auth::recordFailure($identifier);
            $this->flashWithInput('error', 'Those credentials do not match our records.', ['identifier' => $identifier]);
            return $this->redirect('/login', $next !== '' ? ['next' => $next] : []);
        }

        if ((int) $user['is_verified'] !== 1) {
            $this->flash('warning', 'Please verify your email address before signing in.');
            return $this->redirect('/verify-email', ['email' => $user['email']]);
        }

        if ($user['status'] !== 'active') {
            $this->flash('error', 'Your account is inactive. Please contact the academic office.');
            return $this->redirect('/login');
        }

        Auth::clearFailures($identifier);
        Auth::login($user);
        $this->syncApprovedApplications((int) $user['id']);

        $this->flash('success', 'Welcome back, ' . explode(' ', $user['full_name'])[0] . '.');

        if ($next !== '' && strpos($next, '//') === false && strpos($next, 'index.php') !== false) {
            Response::redirect($next);
        }
        return $this->redirect(Auth::homeFor());
    }

    public function logout()
    {
        if (user()) {
            Activity::log('Signed out', 'auth');
        }
        Auth::logout();
        Session::start();
        Session::flash('info', 'You have been signed out.');
        return $this->redirect('/login');
    }

    /* ── Registration ────────────────────────────────────────── */

    public function showRegister()
    {
        $this->publicView('auth/register', [
            'pageTitle' => 'Create your account',
            'batches' => $this->openBatches(),
            'hideChrome' => true,
        ], 'layouts/auth');
    }

    public function register()
    {
        $data = [
            'full_name' => $this->string('full_name'),
            'email' => strtolower($this->string('email')),
            'cnic' => $this->string('cnic'),
            'gender' => $this->string('gender'),
            'phone' => $this->string('phone'),
            'password' => (string) $this->input('password', ''),
            'password_confirmation' => (string) $this->input('password_confirmation', ''),
            'batch_id' => $this->int('batch_id'),
        ];

        $validator = Validator::make($data, [
            'full_name' => 'required|min:3|max:150',
            'email' => 'required|email|unique:users,email',
            'cnic' => 'required|cnic|unique:users,cnic',
            'gender' => 'required|in:male,female,other',
            'phone' => 'phone',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
        ], [
            'full_name' => 'Full name',
            'password_confirmation' => 'Password confirmation',
        ]);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashInput($data);
            $this->flash('error', $validator->firstMessage());
            return $this->redirect('/register');
        }

        $studentId = $this->generateStudentId();
        if ($studentId === null) {
            $this->flash('error', 'Could not allocate a student ID. Please try again.');
            return $this->redirect('/register');
        }

        $userId = Database::insert(
            "INSERT INTO users (full_name, email, password, cnic, user_id_number, gender, phone,
                                status, is_verified, `current_role`, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive', 0, 'student', NOW())",
            [
                $data['full_name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
                format_cnic($data['cnic']),
                $studentId,
                $data['gender'],
                $data['phone'] !== '' ? $data['phone'] : null,
            ]
        );

        Database::write('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, 1, ?)', [$userId, $userId]);

        $token = bin2hex(random_bytes(32));
        Database::write('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
        Database::write(
            'INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)',
            [$userId, $token, date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_HOURS * 3600))]
        );

        if ($data['batch_id'] > 0) {
            $this->createApplication($userId, $data['batch_id']);
        }

        Activity::log('New registration: ' . $data['full_name'] . ' (ID ' . $studentId . ')', 'auth');

        $mail = MailService::send(
            $data['email'],
            $data['full_name'],
            'Confirm your ' . APP_NAME . ' account',
            MailService::verificationEmail($data['full_name'], $token, $studentId)
        );

        Session::set('pending_email', $data['email']);
        $this->flash(
            $mail['ok'] ? 'success' : 'warning',
            $mail['ok']
                ? 'Account created. Please check your inbox to verify your email address.'
                : 'Account created, but the verification email could not be sent. Use the button on the next screen to continue.'
        );

        $query = ['email' => $data['email'], 'student_id' => $studentId];
        if (!$mail['ok']) {
            $query['token'] = $token;
        }
        return $this->redirect('/verify-email', $query);
    }

    /* ── Email verification ──────────────────────────────────── */

    public function verifyEmail()
    {
        $token = $this->string('token');
        $email = $this->string('email');

        if ($token !== '') {
            $record = Database::first(
                'SELECT ev.user_id, u.full_name, u.email
                 FROM email_verifications ev
                 JOIN users u ON u.id = ev.user_id
                 WHERE ev.token = ? AND ev.expires_at > NOW()
                 LIMIT 1',
                [$token]
            );

            if ($record) {
                Database::write("UPDATE users SET is_verified = 1, status = 'active' WHERE id = ?", [$record['user_id']]);
                Database::write('DELETE FROM email_verifications WHERE user_id = ?', [$record['user_id']]);
                Activity::setActor((int) $record['user_id']);
                Activity::log('Email address verified', 'auth');

                $this->flash('success', 'Your email address is verified. You can sign in now.');
                return $this->redirect('/login');
            }

            $this->flash('error', 'That verification link is invalid or has expired. Request a new one below.');
        }

        $this->publicView('auth/verify-email', [
            'pageTitle' => 'Verify your email',
            'email' => $email,
            'studentId' => $this->string('student_id'),
            'devToken' => $this->string('token'),
            'hideChrome' => true,
        ], 'layouts/auth');
    }

    public function resendVerification()
    {
        $email = strtolower($this->string('email'));
        $user = $email !== ''
            ? Database::first('SELECT id, full_name, user_id_number, is_verified FROM users WHERE email = ? LIMIT 1', [$email])
            : null;

        if ($user && (int) $user['is_verified'] !== 1) {
            $token = bin2hex(random_bytes(32));
            Database::write('DELETE FROM email_verifications WHERE user_id = ?', [$user['id']]);
            Database::write(
                'INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)',
                [$user['id'], $token, date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_HOURS * 3600))]
            );

            $mail = MailService::send(
                $email,
                $user['full_name'],
                'Confirm your ' . APP_NAME . ' account',
                MailService::verificationEmail($user['full_name'], $token, $user['user_id_number'])
            );

            if (!$mail['ok']) {
                $this->flash('warning', 'The email could not be sent. Use the direct verification link below.');
                return $this->redirect('/verify-email', ['email' => $email, 'token' => $token]);
            }
        }

        $this->flash('success', 'If that address needs verification, a new link is on its way.');
        return $this->redirect('/verify-email', ['email' => $email]);
    }

    /* ── Password reset (OTP flow) ───────────────────────────── */

    public function showForgot()
    {
        $this->publicView('auth/forgot-password', [
            'pageTitle' => 'Reset your password',
            'hideChrome' => true,
        ], 'layouts/auth');
    }

    public function sendOtp()
    {
        $email = strtolower($this->string('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Please enter a valid email address.');
        }

        $user = Database::first(
            "SELECT id, full_name FROM users WHERE email = ? AND status = 'active' AND is_verified = 1 LIMIT 1",
            [$email]
        );

        if ($user) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Database::write('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
            Database::write(
                'INSERT INTO password_resets (user_id, token, expires_at, is_used) VALUES (?, ?, ?, 0)',
                [$user['id'], $otp, date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60))]
            );

            Activity::log('Password reset code requested', 'auth');

            $mail = MailService::send(
                $email,
                $user['full_name'],
                'Your ' . APP_NAME . ' password reset code',
                MailService::passwordOtp($user['full_name'], $otp)
            );

            $payload = ['email' => $email];
            if (!$mail['ok'] && APP_ENV !== 'production') {
                $payload['dev_otp'] = $otp;
            }
            return $this->success('If that address is registered, a reset code is on its way.', $payload);
        }

        return $this->success('If that address is registered, a reset code is on its way.', ['email' => $email]);
    }

    public function verifyOtp()
    {
        $email = strtolower($this->string('email'));
        $otp = $this->string('otp');

        if ($email === '' || !preg_match('/^\d{6}$/', $otp)) {
            return $this->error('Enter the 6-digit code sent to your email address.');
        }

        $key = 'otp_attempts_' . md5($email);
        $attempts = (int) Session::get($key, 0) + 1;
        Session::set($key, $attempts);
        if ($attempts > 5) {
            Session::forget($key);
            return $this->error('Too many incorrect attempts. Please request a new code.');
        }

        $row = Database::first(
            'SELECT pr.user_id FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE u.email = ? AND pr.token = ? AND pr.expires_at > NOW() AND pr.is_used = 0
             LIMIT 1',
            [$email, $otp]
        );

        if (!$row) {
            return $this->error('That code is incorrect or has expired.');
        }

        $resetToken = bin2hex(random_bytes(16));
        Session::set('password_reset', [
            'token' => $resetToken,
            'user_id' => (int) $row['user_id'],
            'expires' => time() + 900,
        ]);
        Session::forget($key);

        return $this->success('Code verified. Choose a new password.', ['reset_token' => $resetToken]);
    }

    public function resetPassword()
    {
        $pending = Session::get('password_reset');
        $token = $this->string('reset_token');
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');

        if (!$pending || $pending['token'] !== $token || $pending['expires'] < time()) {
            return $this->error('Your reset session has expired. Please start again.');
        }

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => 'required|password', 'password_confirmation' => 'required|same:password'],
            ['password_confirmation' => 'Password confirmation']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage());
        }

        $userId = (int) $pending['user_id'];
        Database::write(
            'UPDATE users SET password = ? WHERE id = ?',
            [password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $userId]
        );
        Database::write('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        Session::forget('password_reset');

        Activity::setActor($userId);
        Activity::log('Password reset completed', 'auth');

        return $this->success('Password updated. You can sign in with your new password.');
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    /** Allocate an unused 5-digit student identifier. */
    private function generateStudentId()
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            $exists = (int) Database::value('SELECT COUNT(*) FROM users WHERE user_id_number = ?', [$candidate]);
            if ($exists === 0) {
                return $candidate;
            }
        }
        return null;
    }

    /** Batches that are currently accepting applications. */
    private function openBatches()
    {
        return Database::all(
            "SELECT b.id, b.name, c.title AS course_title, b.start_date
             FROM batches b
             JOIN courses c ON b.course_id = c.id
             WHERE b.status = 'active'
             ORDER BY c.title ASC, b.name ASC"
        );
    }

    /** Create (or refresh) a pending application for an open batch. */
    private function createApplication($userId, $batchId)
    {
        $batch = Database::first("SELECT id, name, course_id FROM batches WHERE id = ? AND status = 'active'", [$batchId]);
        if (!$batch) {
            return false;
        }

        $existing = Database::first(
            'SELECT id, status FROM course_applications WHERE user_id = ? AND batch_id = ?',
            [$userId, $batchId]
        );

        if ($existing) {
            if ($existing['status'] === 'rejected' || $existing['status'] === 'test_submitted') {
                Database::write(
                    "UPDATE course_applications SET status = 'pending', applied_at = NOW(), rejection_reason = NULL WHERE id = ?",
                    [$existing['id']]
                );
            }
            return (int) $existing['id'];
        }

        return Database::insert(
            "INSERT INTO course_applications (user_id, batch_id, status, applied_at) VALUES (?, ?, 'pending', NOW())",
            [$userId, $batchId]
        );
    }

    /** On sign in, enrol students whose applications were approved. */
    private function syncApprovedApplications($userId)
    {
        try {
            $approved = Database::all(
                "SELECT ca.batch_id
                 FROM course_applications ca
                 WHERE ca.user_id = ? AND ca.status = 'approved'
                   AND ca.batch_id NOT IN (SELECT batch_id FROM batch_students WHERE student_id = ?)",
                [$userId, $userId]
            );

            foreach ($approved as $row) {
                Database::write(
                    'INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by) VALUES (?, ?, ?)',
                    [(int) $row['batch_id'], $userId, $userId]
                );
                Database::write("UPDATE users SET status = 'active', is_verified = 1 WHERE id = ?", [$userId]);
            }

            // Verified applicants are also activated.
            if ($approved) {
                Database::write("UPDATE users SET status = 'active' WHERE id = ? AND status = 'inactive'", [$userId]);
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Application sync failed: ' . $e->getMessage());
        }
    }

    /** Flash an error while keeping the submitted identifier visible. */
    private function flashWithInput($type, $message, array $values)
    {
        $this->flash($type, $message);
        Session::set('_old_input', $values);
    }
}




