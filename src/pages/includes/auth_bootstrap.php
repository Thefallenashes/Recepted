<?php

require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../utils/auth.php';

safe_session_start();

attempt_remember_login();
