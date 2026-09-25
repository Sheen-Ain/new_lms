<?php
/**
 * Route table.
 *
 * Every request is dispatched through public/index.php; no .htaccess
 * rewriting is required. Entries are [METHOD, path, "Controller@action",
 * middleware[]].
 */

return [
    /* ── Public website ──────────────────────────────────────── */
    ['GET', '/', 'HomeController@index'],
    ['GET', '/courses', 'HomeController@courses'],

    /* ── Authentication ──────────────────────────────────────── */
    ['GET', '/login', 'AuthController@showLogin', ['guest']],
    ['POST', '/login', 'AuthController@login', ['guest', 'csrf']],
    ['GET', '/register', 'AuthController@showRegister', ['guest']],
    ['POST', '/register', 'AuthController@register', ['guest', 'csrf']],
    ['GET', '/verify-email', 'AuthController@verifyEmail'],
    ['POST', '/verify-email/resend', 'AuthController@resendVerification', ['guest', 'csrf']],
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

    /* ── Applications (public + shared) ──────────────────────── */
    ['POST', '/applications/batches', 'ApplicationController@openBatches', ['csrf']],
    ['POST', '/applications/apply', 'ApplicationController@apply', ['csrf']],
    ['POST', '/applications/status', 'ApplicationController@status', ['csrf']],
];
