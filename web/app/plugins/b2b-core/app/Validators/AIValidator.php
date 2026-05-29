<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIValidator
{
    public static function message(array $data): string
    {
        $question = trim((string) ($data['message'] ?? ''));

        if ($question === '') {
            throw new Exception('Câu hỏi không được để trống');
        }

        return $question;
    }
}
