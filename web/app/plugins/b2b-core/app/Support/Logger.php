<?php

class Logger {

    public static function info($message) {
        $time = current_time('mysql');
        error_log("[$time] $message");
    }
}