<?php

require_once __DIR__ . '/auth_bootstrap.php';
require_once __DIR__ . '/../../utils/query_helpers.php';
require_once __DIR__ . '/sticky_menu.php';

get_csrf_token();
enforce_csrf_protection('redirect', 'login.php');