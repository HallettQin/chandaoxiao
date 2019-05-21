<?php
return [
    'HTML_CACHE_ON' => false,
    'HTML_CACHE_TIME' => 3600,
    'HTML_FILE_SUFFIX' => '.shtml',
    'HTML_CACHE_RULES' => [
        '*' => ['{$_SERVER.REQUEST_URI|md5}'],
    ]
];
