<?php

class BaseRepository
{
    protected $table;

    protected function db()
    {
        global $wpdb;
        return $wpdb;
    }
}