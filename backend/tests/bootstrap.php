<?php

require __DIR__.'/../vendor/autoload.php';

// A fresh test-only key avoids depending on a developer's .env or storing a secret in Git.
$key = 'base64:'.base64_encode(random_bytes(32));
$_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = $key;
putenv('APP_KEY='.$key);
