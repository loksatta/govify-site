<?php

define('API_USERNAME', 'username');
define('API_KEY', 'password');

define('API_REGION', 'IAD');

define('API_FILES_IN', 'govify-in');
define('API_FILE_LIFETIME', 60 * 60 * 24); // 1 Day

define('API_QUEUE', 'govify-processing');
define('API_QUEUE_LIFETIME', 60 * 60 * 24); // 1 Day

// Set max upload size (in kb);
define('MAX_SIZE', 50);

// Set max upload size (in bytes)
define('MAX_FILE_SIZE', MAX_SIZE * 1024);

ini_set('upload_max_filesize', MAX_SIZE . 'k');
ini_set('post_max_size', MAX_SIZE . 'k');
