<?php

if (!defined('ABSPATH')) {
    exit;
}

class TimeHelper
{
    public static function mysql(): string
    {
        return function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
    }

    public static function timestamp(): int
    {
        return function_exists('current_time') ? (int) current_time('timestamp') : time();
    }

    public static function date(string $format = 'Y-m-d'): string
    {
        return function_exists('current_time') ? current_time($format) : date($format);
    }

    public static function afterSeconds(int $seconds): string
    {
        return date('Y-m-d H:i:s', self::timestamp() + $seconds);
    }
}
