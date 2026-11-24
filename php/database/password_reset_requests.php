<?php
// Database initialization component. Execute through php/start.php only
if (!defined('DB_SETUP_RUNNING')) {
    http_response_code(403);
    exit('Run php/start.php to initialize the database.');
}

/*
 * PasswordResetRequests: "Forgot Password" requests waiting for an admin.
 * Since emails are just dummy seed data, this integrates with our current features.
 * resolved_at is NULL while a request is pending.
 * Deleting or archiving a user removes their requests (ON DELETE CASCADE)
 */
$db_connection->query(
    "CREATE TABLE `PasswordResetRequests` (
        `request_id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` TIMESTAMP NULL DEFAULT NULL,
        `resolution` ENUM('reset', 'dismissed') NULL DEFAULT NULL,
        `resolved_by` VARCHAR(255) NULL DEFAULT NULL,
        PRIMARY KEY (`request_id`),
        KEY `idx_reset_user` (`user_id`),
        CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
