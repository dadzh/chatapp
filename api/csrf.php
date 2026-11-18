<?php
require_once __DIR__ . '/helpers.php';

json_response(['csrf_token' => csrf_token()]);