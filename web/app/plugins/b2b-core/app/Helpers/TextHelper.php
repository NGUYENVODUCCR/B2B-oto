<?php

if (!defined('ABSPATH')) {
    exit;
}

class TextHelper
{
    public static function clean($value): string
    {
        return function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $value)
            : trim((string) $value);
    }

    public static function textarea($value): string
    {
        return function_exists('sanitize_textarea_field')
            ? sanitize_textarea_field((string) $value)
            : trim((string) $value);
    }

    public static function email($value): string
    {
        return function_exists('sanitize_email')
            ? sanitize_email((string) $value)
            : trim((string) $value);
    }

    public static function url($value): string
    {
        if (function_exists('sanitize_url')) {
            return sanitize_url((string) $value);
        }

        if (function_exists('esc_url_raw')) {
            return esc_url_raw((string) $value);
        }

        return trim((string) $value);
    }

    public static function first(...$values): string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
