<?php
// Database initialization component: run through php/start.php only.
if (!defined('DB_SETUP_RUNNING')) {
    http_response_code(403);
    exit('Run php/start.php to initialize the database.');
}

/*
 * ArchivedUsers: login accounts removed with "Archive".
 * Intentionally has NO foreign keys: the user no longer exists, and the
 * admin who archived them may be removed later too.
 * Keeps who the account belonged to, but not the password hash, phone
 * number or home address.
 */
$db_connection->query(
    "CREATE TABLE `ArchivedUsers` (
        `archive_id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `username` VARCHAR(255) NOT NULL,
        `first_name` VARCHAR(255) NOT NULL,
        `last_name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `role_type` VARCHAR(255) NOT NULL,
        `account_created` TIMESTAMP NULL,
        `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `archived_by` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`archive_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
