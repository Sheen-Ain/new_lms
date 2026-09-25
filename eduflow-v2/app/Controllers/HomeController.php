<?php
/**
 * HomeController — the public website.
 */
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class HomeController extends Controller
{
    /** Landing page. */
    public function index()
    {
        $stats = [
            'courses' => (int) Database::value("SELECT COUNT(*) FROM courses WHERE status = 'active'"),
            'batches' => (int) Database::value("SELECT COUNT(*) FROM batches WHERE status = 'active'"),
            'learners' => (int) Database::value(
                "SELECT COUNT(*) FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id
                 JOIN roles r ON r.id = ur.role_id
                 WHERE r.name = 'student'"
            ),
            'assessments' => (int) Database::value("SELECT COUNT(*) FROM tests"),
        ];

        $courses = Database::all(
            "SELECT c.id, c.title, c.description,
                    (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id) AS batch_count
             FROM courses c
             WHERE c.status = 'active'
             ORDER BY c.title ASC
             LIMIT 6"
        );

        $this->publicView('public/home', [
            'pageTitle' => 'Learning & assessment platform',
            'pageDescription' => 'EduFlow combines course delivery, assignments, live classes and online assessments for institutes.',
            'stats' => $stats,
            'courses' => $courses,
        ]);
    }

    /** Public course catalogue page. */
    public function courses()
    {
        $courses = Database::all(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id AND b.status = 'active') AS batch_count
             FROM courses c
             WHERE c.status = 'active'
             ORDER BY c.title ASC"
        );

        $this->publicView('public/courses', [
            'pageTitle' => 'Courses',
            'courses' => $courses,
        ]);
    }
}
