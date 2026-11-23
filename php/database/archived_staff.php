<?php
// Database initialization component: run through php/start.php only.
if (!defined('DB_SETUP_RUNNING')) {
    http_response_code(403);
    exit('Run php/start.php to initialize the database.');
}

/*
 * ArchivedStaff: staff members removed from the org chart with "Archive".
 * Intentionally has NO foreign keys for simplicity. Can restore archived users.
 */
$db_connection->query(
    "CREATE TABLE `ArchivedStaff` (
        `archive_id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `first_name` VARCHAR(50) NOT NULL,
        `last_name` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `job_title` VARCHAR(255) NOT NULL,
        `ranks_title` VARCHAR(100) NOT NULL,
        `class_name` VARCHAR(100) NOT NULL,
        `dept_name` VARCHAR(255) NOT NULL,
        `org_name` VARCHAR(50) NOT NULL,
        `college_name` VARCHAR(50) NULL,
        `supervisor_name` VARCHAR(101) NULL,
        `job_id` INT(11) NULL,
        `rank_id` INT(11) NULL,
        `dept_id` INT(11) NULL,
        `supervisor_id` INT(11) NULL,
        `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `archived_by` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`archive_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
