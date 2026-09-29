<?php

/**
 * ApplicationController — public application submission endpoint.
 */
namespace App\Controllers;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;

class ApplicationController extends Controller
{
    /** Return all active batches that learners may apply for. */
    public function openBatches()
    {
        $batches = Database::all(
            "SELECT b.id, b.name, c.title AS course_title, b.start_date, b.end_date
             FROM batches b
             JOIN courses c ON b.course_id = c.id
             WHERE b.status = 'active'
             ORDER BY c.title ASC, b.name ASC"
        );

        return $this->success('Open batches loaded.', ['batches' => $batches]);
    }

    /** Apply for a batch with an identifier + CNIC. */
    public function apply()
    {
        $identifier = $this->string('identifier');
        $cnic = $this->string('cnic');
        $batchId = $this->int('batch_id');

        if ($identifier === '' || $cnic === '' || !$batchId) {
            return $this->error('All fields are required.');
        }

        $field = preg_match('/^\d{5}$/', $identifier) ? 'user_id_number' : 'email';
        $user = Database::first("SELECT * FROM users WHERE $field = ? LIMIT 1", [$identifier]);

        if (!$user) {
            return $this->error('No account found. Please register first.');
        }

        $formattedCnic = format_cnic($cnic);
        if ($user['cnic'] !== $formattedCnic) {
            return $this->error('The provided CNIC does not match the account records.');
        }

        $batch = Database::first("SELECT id, name FROM batches WHERE id = ? AND status = 'active'", [$batchId]);
        if (!$batch) {
            return $this->error('Selected batch is not available.');
        }

        $existing = Database::first(
            'SELECT id, status FROM course_applications WHERE user_id = ? AND batch_id = ? LIMIT 1',
            [$user['id'], $batchId]
        );

        if ($existing) {
            if ($existing['status'] === 'approved') {
                return $this->error('You are already enrolled in this batch.');
            }
            if ($existing['status'] === 'pending' || $existing['status'] === 'test_submitted') {
                return $this->success('Your application is already pending review.', [
                    'status' => $existing['status'],
                    'application_id' => (int) $existing['id'],
                ]);
            }
        }

        $appId = Database::insert(
            "INSERT INTO course_applications (user_id, batch_id, status, applied_at)
             VALUES (?, ?, 'pending', NOW())",
            [$user['id'], $batchId]
        );

        Activity::setActor((int) $user['id']);
        Activity::log('Submitted application for batch: ' . $batch['name'], 'applications');

        return $this->success('Application received. You can now take the entry test.', [
            'application_id' => $appId,
            'redirect' => url('/entry-test'),
        ]);
    }

    /** Check application status. */
    public function status()
    {
        $identifier = $this->string('identifier');
        $batchId = $this->int('batch_id');

        if ($identifier === '' || !$batchId) {
            return $this->error('Invalid request.');
        }

        $field = preg_match('/^\d{5}$/', $identifier) ? 'user_id_number' : 'email';
        $user = Database::first("SELECT id FROM users WHERE $field = ? LIMIT 1", [$identifier]);
        if (!$user) {
            return $this->error('Account not found.');
        }

        $app = Database::first(
            'SELECT * FROM course_applications WHERE user_id = ? AND batch_id = ? LIMIT 1',
            [$user['id'], $batchId]
        );

        return $this->success('Status loaded.', ['application' => $app]);
    }
}
