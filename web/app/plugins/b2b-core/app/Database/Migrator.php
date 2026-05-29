<?php

namespace B2B\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Migrator
{
    private string $path;
    private string $optionKey;

    public function __construct(string $path, string $optionKey)
    {
        $this->path = rtrim($path, '/');
        $this->optionKey = $optionKey;
    }

    public function maybeRun(): void
    {
        $files = $this->getMigrationFiles();
        $fileNames = array_map('basename', $files);
        $stored = $this->normalizedApplied($fileNames);
        $pending = array_values(array_diff($fileNames, $stored));

        if (!empty($pending)) {
            $this->migrate();
        }
    }

    public function migrate(): void
    {
        error_log('--- MIGRATE START ---');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $files = $this->getMigrationFiles();
        error_log('FILES: ' . print_r($files, true));

        $fileNames = array_map('basename', $files);
        $applied = $this->normalizedApplied($fileNames);

        foreach ($files as $file) {
            $fileName = basename($file);

            if (in_array($fileName, $applied, true)) {
                continue;
            }

            $migration = (function ($file) {
                return require $file;
            })($file);

            if (!$migration instanceof Migration) {
                error_log("Invalid migration file: $file");
                continue;
            }

            $migration->up();

            $applied[] = $fileName;
            update_option($this->optionKey, array_values($applied), false);
        }
    }

    private function normalizedApplied(array $fileNames): array
    {
        $stored = get_option($this->optionKey, []);
        $stored = is_array($stored) ? $stored : [];
        $normalized = array_values(array_intersect($stored, $fileNames));

        if ($normalized !== $stored) {
            update_option($this->optionKey, $normalized, false);
        }

        return $normalized;
    }

    public function rollback(int $steps = 1): void
    {
        $applied = get_option($this->optionKey, []);
        if (empty($applied)) {
            return;
        }

        $toRollback = array_slice(array_reverse($applied), 0, $steps);

        foreach ($toRollback as $fileName) {
            $file = $this->path . '/' . $fileName;

            if (!file_exists($file)) {
                continue;
            }

            $migration = (function ($file) {
                return require $file;
            })($file);

            if ($migration instanceof Migration) {
                $migration->down();
            }

            $applied = array_values(array_diff($applied, [$fileName]));
        }

        update_option($this->optionKey, array_values($applied), false);
    }

    public function rollbackAll(): void
    {
        $applied = get_option($this->optionKey, []);
        if (empty($applied)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $applied = array_reverse($applied);

        foreach ($applied as $fileName) {
            $file = $this->path . '/' . $fileName;

            if (!file_exists($file)) {
                continue;
            }

            $migration = (function ($file) {
                return require $file;
            })($file);

            if ($migration instanceof Migration) {
                $migration->down();
            }
        }

        delete_option($this->optionKey);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }
}
