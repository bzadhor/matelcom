<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf_or_fail('admin_logout'); }
logoutAdmin();
