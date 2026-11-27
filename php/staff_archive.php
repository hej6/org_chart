<?php
/**
 * Staff archive function
 *
 * Used by admin/staff.php (the Archive button) and by tests/staff_archive_test.php.
 * MOVED from admin/staff.php for unit testing purposes.
 * Keeping it in one function means the unit test checks the same code the page runs.
 */

/**
 * Archive a staff member: copy them into ArchivedStaff, then remove them from Staff.
 *
 * Both steps run in one transaction. If either step fails, rollback() undoes
 * everything, so a staff member can never end up in both tables or in neither.
 *
 * @param mysqli $db_connection  An open connection
 * @param int    $staff_id       The staff member to archive
 * @param string $archived_by    Username of the admin doing the archiving
 * @return bool  true if archived, false if no staff member has that ID
 * @throws mysqli_sql_exception  e.g. code 1451 if they still supervise someone
 *                               (the transaction is rolled back first)
 */
function archiveStaff(mysqli $db_connection, int $staff_id, string $archived_by): bool
{
    $db_connection->begin_transaction();
    try {
        // Step 1: copy the staff member (with names AND IDs) into ArchivedStaff
        $stmt = $db_connection->prepare(
            'INSERT INTO ArchivedStaff
             (staff_id, first_name, last_name, email, job_title, ranks_title,
              class_name, dept_name, org_name, college_name, supervisor_name,
              job_id, rank_id, dept_id, supervisor_id, archived_by)
             SELECT v.staff_id, v.first_name, v.last_name, v.email, v.job_title,
                    v.ranks_title, v.class_name, v.dept_name, v.org_name,
                    v.college_name, v.supervisor_name,
                    v.job_id, v.ranks_id, v.dept_id, s.supervisor_id, ?
             FROM vw_staff_directory v
             JOIN Staff s ON s.staff_id = v.staff_id
             WHERE v.staff_id = ?'
        );
        $stmt->bind_param('si', $archived_by, $staff_id);
        $stmt->execute();
        $copied = $stmt->affected_rows;
        $stmt->close();

        if ($copied === 0) {
            // No staff member with that ID: nothing to archive
            $db_connection->rollback();
            return false;
        }

        // Step 2: remove them from Staff.
        // Fails with error 1451 if anyone still reports to them.
        $stmt = $db_connection->prepare('DELETE FROM Staff WHERE staff_id = ?');
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $stmt->close();

        $db_connection->commit();
        return true;
    } catch (mysqli_sql_exception $e) {
        $db_connection->rollback(); // undo step 1 so nothing is half-archived
        throw $e;
    }
}
