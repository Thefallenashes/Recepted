<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../utils/auth.php';

attempt_remember_login();
