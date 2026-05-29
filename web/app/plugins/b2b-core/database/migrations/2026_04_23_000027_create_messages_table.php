<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateMessagesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

            $sql = "CREATE TABLE {$prefix}messages (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    conversation_id BIGINT UNSIGNED NOT NULL,
                    sender_id BIGINT UNSIGNED NOT NULL,
                    receiver_id BIGINT UNSIGNED NULL,
                    message TEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY idx_messages_conversation_id (conversation_id),
                    KEY idx_messages_sender_id (sender_id),
                    KEY idx_messages_receiver_id (receiver_id),
                    KEY idx_messages_created_at (created_at)
            ) {$charset_collate};";;

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}messages;");
    }
}

return new CreateMessagesTable();