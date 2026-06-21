<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/football-stars-idle');
}

if (!function_exists('url')) {
    function url(string $path): string {
        return BASE_URL . $path;
    }
}