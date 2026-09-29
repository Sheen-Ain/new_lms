<?php
/**
 * EduFlow V2 — route table.
 *
 * Every request is dispatched through public/index.php; no .htaccess
 * rewriting is required. Entries are:
 *
 *   [METHOD, path, 'Controller@action', middleware[]]
 *
 * Middleware names: guest, auth, csrf, role:admin,teacher
 */

return [
    /* ── Public website ──────────────────────────────────────── */
    ['GET', '/', 'HomeController@index'],
    ['GET', '/courses', 'HomeController@courses'],
    ['GET', '/entry-test/start', 'EntryTestController@start'],

    /* ── Authentication ──────────────────────────────────────── */
    ['GET', '/login', 'AuthController@showLogin', ['guest']],
    ['POST', '/login', 'AuthController@login', ['guest', 'csrf']],
    ['GET', '/register', 'AuthController@showRegister', ['guest']],
    ['POST', '/register', 'AuthController@register', ['guest', 'csrf']],
    ['GET', '/verify-email', 'AuthController@verifyEmail'],
    ['POST', '/verify-email/resend', 'AuthController@resendVerification', ['csrf']],
    ['GET', '/forgot-password', 'AuthController@showForgot', ['guest']],
    ['POST', '/forgot-password/send', 'AuthController@sendOtp', ['guest', 'csrf']],
    ['POST', '/forgot-password/verify', 'AuthController@verifyOtp', ['guest', 'csrf']],
    ['POST', '/forgot-password/reset', 'AuthController@resetPassword', ['guest', 'csrf']],
    ['GET', '/logout', 'AuthController@logout'],
    ['POST', '/logout', 'AuthController@logout', ['csrf']],

    /* ── Entry test (pre-authentication assessment) ──────────── */
    ['GET', '/entry-test', 'EntryTestController@index'],
    ['POST', '/entry-test/auth', 'EntryTestController@authenticate', ['csrf']],
    ['POST', '/entry-test/questions', 'EntryTestController@questions', ['csrf']],
    ['POST', '/entry-test/answer', 'EntryTestController@saveAnswer', ['csrf']],
    ['POST', '/entry-test/submit', 'EntryTestController@submit', ['csrf']],
    ['GET', '/entry-test/exit', 'EntryTestController@exitTest'],

    /* ── Applications (public) ───────────────────────────────── */
    ['POST', '/applications/batches', 'ApplicationController@openBatches', ['csrf']],
    ['POST', '/applications/apply', 'ApplicationController@apply', ['csrf']],
    ['POST', '/applications/status', 'ApplicationController@status', ['csrf']],

    /* ── Profile & account ───────────────────────────────────── */
    ['GET', '/profile', 'CommonController@profile', ['auth']],
    ['POST', '/profile/update', 'CommonController@updateProfile', ['auth', 'csrf']],
    ['POST', '/profile/password', 'CommonController@changePassword', ['auth', 'csrf']],
    ['POST', '/profile/avatar', 'CommonController@uploadAvatar', ['auth', 'csrf']],
    ['POST', '/profile/theme', 'CommonController@updateTheme', ['csrf']],
    ['POST', '/profile/switch-role', 'CommonController@switchRole', ['auth', 'csrf']],

    /* ── Shared AJAX services ────────────────────────────────── */
    ['POST', '/api/presence', 'CommonController@presence', ['auth', 'csrf']],
    ['POST', '/api/notifications', 'CommonController@notifications', ['auth']],
    ['GET', '/files/view', 'CommonController@viewFile', ['auth']],
    ['GET', '/files/download', 'CommonController@downloadFile', ['auth']],

    /* ── Student portal ──────────────────────────────────────── */
    ['GET', '/student', 'Student\Portal@index', ['auth', 'role:student,teacher,admin']],
    ['GET', '/student/courses', 'Student\Portal@courses', ['auth']],
    ['GET', '/student/topics', 'Student\Portal@topics', ['auth']],
    ['GET', '/student/assignments', 'Student\Portal@assignments', ['auth']],
    ['GET', '/student/tests', 'Student\Portal@tests', ['auth']],
    ['GET', '/student/tests/take', 'Student\Portal@takeTest', ['auth']],
    ['GET', '/student/results', 'Student\Portal@results', ['auth']],
    ['GET', '/student/announcements', 'Student\Portal@announcements', ['auth']],
    ['GET', '/student/live', 'Student\Portal@live', ['auth']],
    ['GET', '/student/live/room', 'Student\Portal@room', ['auth']],
    ['GET', '/student/feedback', 'Student\Portal@feedback', ['auth']],

    /* ── Teacher portal ──────────────────────────────────────── */
    ['GET', '/teacher', 'Teacher\Portal@index', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/batches', 'Teacher\Portal@batches', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/students', 'Teacher\Portal@students', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/topics', 'Teacher\Portal@topics', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/assignments', 'Teacher\Portal@assignments', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/submissions', 'Teacher\Portal@submissions', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/tests', 'Teacher\Portal@tests', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/announcements', 'Teacher\Portal@announcements', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/live', 'Teacher\Portal@live', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/live/room', 'Teacher\Portal@room', ['auth', 'role:teacher,admin']],
    ['GET', '/teacher/feedback', 'Teacher\Portal@feedback', ['auth', 'role:teacher,admin']],

    /* ── Admin portal ────────────────────────────────────────── */
    ['GET', '/admin', 'Admin\Portal@index', ['auth', 'role:admin']],
    ['GET', '/admin/users', 'Admin\Portal@users', ['auth', 'role:admin']],
    ['GET', '/admin/courses', 'Admin\Portal@courses', ['auth', 'role:admin']],
    ['GET', '/admin/batches', 'Admin\Portal@batches', ['auth', 'role:admin']],
    ['GET', '/admin/topics', 'Admin\Portal@topics', ['auth', 'role:admin']],
    ['GET', '/admin/assignments', 'Admin\Portal@assignments', ['auth', 'role:admin']],
    ['GET', '/admin/submissions', 'Admin\Portal@submissions', ['auth', 'role:admin']],
    ['GET', '/admin/tests', 'Admin\Portal@tests', ['auth', 'role:admin']],
    ['GET', '/admin/applications', 'Admin\Portal@applications', ['auth', 'role:admin']],
    ['GET', '/admin/announcements', 'Admin\Portal@announcements', ['auth', 'role:admin']],
    ['GET', '/admin/live', 'Admin\Portal@live', ['auth', 'role:admin']],
    ['GET', '/admin/feedback', 'Admin\Portal@feedback', ['auth', 'role:admin']],
    ['GET', '/admin/activity-logs', 'Admin\Portal@activityLogs', ['auth', 'role:admin']],
    ['GET', '/admin/system', 'Admin\Portal@system', ['auth', 'role:admin']],

    /* ── AJAX: assignments & submissions ─────────────────────── */
    ['POST', '/api/assignments/list', 'Api\AssignmentApi@list', ['auth', 'csrf']],
    ['POST', '/api/assignments/get', 'Api\AssignmentApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/assignments/create', 'Api\AssignmentApi@create', ['auth', 'csrf']],
    ['POST', '/api/assignments/update', 'Api\AssignmentApi@update', ['auth', 'csrf']],
    ['POST', '/api/assignments/delete', 'Api\AssignmentApi@delete', ['auth', 'csrf']],
    ['POST', '/api/assignments/submit', 'Api\AssignmentApi@submit', ['auth', 'csrf']],
    ['POST', '/api/assignments/upload', 'Api\AssignmentApi@upload', ['auth', 'csrf']],
    ['POST', '/api/assignments/files', 'Api\AssignmentApi@files', ['auth', 'csrf']],
    ['POST', '/api/assignments/delete-file', 'Api\AssignmentApi@deleteFile', ['auth', 'csrf']],

    ['POST', '/api/submissions/list', 'Api\SubmissionApi@list', ['auth', 'csrf']],
    ['POST', '/api/submissions/get', 'Api\SubmissionApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/submissions/grade', 'Api\SubmissionApi@grade', ['auth', 'csrf']],
    ['POST', '/api/submissions/delete', 'Api\SubmissionApi@delete', ['auth', 'csrf']],

    /* ── AJAX: tests & attempts ──────────────────────────────── */
    ['POST', '/api/tests/list', 'Api\TestApi@list', ['auth', 'csrf']],
    ['POST', '/api/tests/get', 'Api\TestApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/tests/create', 'Api\TestApi@create', ['auth', 'csrf']],
    ['POST', '/api/tests/update', 'Api\TestApi@update', ['auth', 'csrf']],
    ['POST', '/api/tests/status', 'Api\TestApi@status', ['auth', 'csrf']],
    ['POST', '/api/tests/delete', 'Api\TestApi@delete', ['auth', 'csrf']],
    ['POST', '/api/tests/start', 'Api\TestApi@start', ['auth', 'csrf']],
    ['POST', '/api/tests/answer', 'Api\TestApi@saveAnswer', ['auth', 'csrf']],
    ['POST', '/api/tests/submit', 'Api\TestApi@submit', ['auth', 'csrf']],
    ['POST', '/api/tests/attempts', 'Api\TestApi@attempts', ['auth', 'csrf']],
    ['POST', '/api/tests/reattempt', 'Api\TestApi@reattempt', ['auth', 'csrf']],

    /* ── AJAX: people & academics ────────────────────────────── */
    ['POST', '/api/users/list', 'Api\UserApi@list', ['auth', 'csrf']],
    ['POST', '/api/users/get', 'Api\UserApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/users/create', 'Api\UserApi@create', ['auth', 'csrf']],
    ['POST', '/api/users/update', 'Api\UserApi@update', ['auth', 'csrf']],
    ['POST', '/api/users/delete', 'Api\UserApi@delete', ['auth', 'csrf']],
    ['POST', '/api/users/status', 'Api\UserApi@status', ['auth', 'csrf']],
    ['POST', '/api/users/bypass', 'Api\UserApi@bypass', ['auth', 'csrf']],

    ['POST', '/api/courses/list', 'Api\CourseApi@list', ['auth', 'csrf']],
    ['POST', '/api/courses/get', 'Api\CourseApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/courses/create', 'Api\CourseApi@create', ['auth', 'csrf']],
    ['POST', '/api/courses/update', 'Api\CourseApi@update', ['auth', 'csrf']],
    ['POST', '/api/courses/delete', 'Api\CourseApi@delete', ['auth', 'csrf']],

    ['POST', '/api/batches/list', 'Api\BatchApi@list', ['auth', 'csrf']],
    ['POST', '/api/batches/get', 'Api\BatchApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/batches/create', 'Api\BatchApi@create', ['auth', 'csrf']],
    ['POST', '/api/batches/update', 'Api\BatchApi@update', ['auth', 'csrf']],
    ['POST', '/api/batches/delete', 'Api\BatchApi@delete', ['auth', 'csrf']],
    ['POST', '/api/batches/members', 'Api\BatchApi@members', ['auth', 'csrf']],
    ['POST', '/api/batches/enroll', 'Api\BatchApi@enroll', ['auth', 'csrf']],
    ['POST', '/api/batches/unenroll', 'Api\BatchApi@unenroll', ['auth', 'csrf']],
    ['POST', '/api/batches/assign', 'Api\BatchApi@assign', ['auth', 'csrf']],
    ['POST', '/api/batches/unassign', 'Api\BatchApi@unassign', ['auth', 'csrf']],
    ['POST', '/api/batches/available', 'Api\BatchApi@available', ['auth', 'csrf']],

    /* ── AJAX: topics & material ─────────────────────────────── */
    ['POST', '/api/topics/list', 'Api\TopicApi@list', ['auth', 'csrf']],
    ['POST', '/api/topics/simple', 'Api\TopicApi@listSimple', ['auth', 'csrf']],
    ['POST', '/api/topics/get', 'Api\TopicApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/topics/create', 'Api\TopicApi@create', ['auth', 'csrf']],
    ['POST', '/api/topics/update', 'Api\TopicApi@update', ['auth', 'csrf']],
    ['POST', '/api/topics/delete', 'Api\TopicApi@delete', ['auth', 'csrf']],
    ['POST', '/api/topics/reorder', 'Api\TopicApi@reorder', ['auth', 'csrf']],
    ['POST', '/api/topics/files', 'Api\TopicApi@files', ['auth', 'csrf']],
    ['POST', '/api/topics/assignments', 'Api\TopicApi@assignments', ['auth', 'csrf']],
    ['POST', '/api/topics/upload', 'Api\TopicApi@upload', ['auth', 'csrf']],
    ['POST', '/api/topics/delete-file', 'Api\TopicApi@deleteFile', ['auth', 'csrf']],
    ['POST', '/api/topics/toggle-file', 'Api\TopicApi@toggleFile', ['auth', 'csrf']],

    /* ── AJAX: announcements ─────────────────────────────────── */
    ['POST', '/api/announcements/list', 'Api\AnnouncementApi@list', ['auth', 'csrf']],
    ['POST', '/api/announcements/get', 'Api\AnnouncementApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/announcements/create', 'Api\AnnouncementApi@create', ['auth', 'csrf']],
    ['POST', '/api/announcements/update', 'Api\AnnouncementApi@update', ['auth', 'csrf']],
    ['POST', '/api/announcements/delete', 'Api\AnnouncementApi@delete', ['auth', 'csrf']],
    ['POST', '/api/announcements/pin', 'Api\AnnouncementApi@pin', ['auth', 'csrf']],

    /* ── AJAX: live sessions ─────────────────────────────────── */
    ['POST', '/api/live/list', 'Api\LiveApi@list', ['auth', 'csrf']],
    ['POST', '/api/live/start', 'Api\LiveApi@start', ['auth', 'csrf']],
    ['POST', '/api/live/open', 'Api\LiveApi@open', ['auth', 'csrf']],
    ['POST', '/api/live/end', 'Api\LiveApi@end', ['auth', 'csrf']],
    ['POST', '/api/live/join', 'Api\LiveApi@join', ['auth', 'csrf']],
    ['POST', '/api/live/leave', 'Api\LiveApi@leave', ['auth', 'csrf']],
    ['POST', '/api/live/status', 'Api\LiveApi@status', ['auth', 'csrf']],
    ['POST', '/api/live/participants', 'Api\LiveApi@participants', ['auth', 'csrf']],
    ['POST', '/api/live/delete', 'Api\LiveApi@delete', ['auth', 'csrf']],

    /* ── AJAX: applications & enrolment ──────────────────────── */
    ['POST', '/api/applications/list', 'Api\ApplicationApi@list', ['auth', 'csrf']],
    ['POST', '/api/applications/get', 'Api\ApplicationApi@getOne', ['auth', 'csrf']],
    ['POST', '/api/applications/approve', 'Api\ApplicationApi@approve', ['auth', 'csrf']],
    ['POST', '/api/applications/reject', 'Api\ApplicationApi@reject', ['auth', 'csrf']],
    ['POST', '/api/applications/eligible', 'Api\ApplicationApi@eligible', ['auth', 'csrf']],
    ['POST', '/api/applications/enroll', 'Api\ApplicationApi@enroll', ['auth', 'csrf']],

    /* ── AJAX: feedback ──────────────────────────────────────── */
    ['POST', '/api/feedback/list', 'Api\FeedbackApi@list', ['auth', 'csrf']],
    ['POST', '/api/feedback/sessions', 'Api\FeedbackApi@sessions', ['auth', 'csrf']],
    ['POST', '/api/feedback/create-session', 'Api\FeedbackApi@createSession', ['auth', 'csrf']],
    ['POST', '/api/feedback/toggle-session', 'Api\FeedbackApi@toggleSession', ['auth', 'csrf']],
    ['POST', '/api/feedback/delete-session', 'Api\FeedbackApi@deleteSession', ['auth', 'csrf']],
    ['POST', '/api/feedback/submit', 'Api\FeedbackApi@submit', ['auth', 'csrf']],
    ['POST', '/api/feedback/my-status', 'Api\FeedbackApi@myStatus', ['auth', 'csrf']],
    ['POST', '/api/feedback/mark-reviewed', 'Api\FeedbackApi@markReviewed', ['auth', 'csrf']],
    ['POST', '/api/feedback/delete-entry', 'Api\FeedbackApi@deleteEntry', ['auth', 'csrf']],
    ['POST', '/api/feedback/stats', 'Api\FeedbackApi@stats', ['auth', 'csrf']],

    /* ── AJAX: activity log ──────────────────────────────────── */
    ['POST', '/api/activity/list', 'Api\ActivityApi@list', ['auth', 'csrf']],
    ['POST', '/api/activity/users', 'Api\ActivityApi@users', ['auth', 'csrf']],
    ['POST', '/api/activity/clear', 'Api\ActivityApi@clear', ['auth', 'csrf']],
];
