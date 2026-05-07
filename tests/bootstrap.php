<?php

declare(strict_types=1);

if (!getenv('LOVEMATCH_TEST_BASE_URL')) {
    putenv('LOVEMATCH_TEST_BASE_URL=http://127.0.0.1:8888/api');
}

require dirname(__DIR__) . '/vendor/autoload.php';
