<?php
/**
 * Unit tests: archiving a staff member (archiveStaff() in php/staff_archive.php).
 *
 * Run all tests from the project folder:   php tools/phpunit.phar
 * Run only this file:                      php tools/phpunit.phar tests/StaffArchiveTest.php
 *
 * Before each test, setUp() creates two temporary staff members: a supervisor
 * and someone who reports to them. After each test, tearDown() removes them
 * (and anything archived), so the real data is never changed.
 * Run php/start.php first so the tables exist.
 */

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/php/staff_archive.php';

final class StaffArchiveTest extends TestCase
{
    private mysqli $db;
    private string $supervisorEmail;
    private string $reportEmail;
    private int $supervisorId;
    private int $reportId;
    private int $jobId;
    private int $rankId;
    private int $deptId;

    protected function setUp(): void
    {
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        $this->db->set_charset('utf8mb4');

        // Unique emails, so leftovers from an interrupted run can never collide
        $tag = 'unittest.' . bin2hex(random_bytes(4));
        $this->supervisorEmail = "$tag.supervisor@example.com";
        $this->reportEmail = "$tag.report@example.com";

        // Use an existing job, rank and department
        $this->jobId = (int) $this->scalar('SELECT MIN(job_id) FROM Job');
        $this->rankId = (int) $this->scalar('SELECT MIN(ranks_id) FROM Ranks');
        $this->deptId = (int) $this->scalar('SELECT MIN(dept_id) FROM Department');

        $this->supervisorId = $this->addStaff('Test', 'Supervisor', $this->supervisorEmail, null);
        $this->reportId = $this->addStaff('Test', 'Report', $this->reportEmail, $this->supervisorId);
    }

    protected function tearDown(): void
    {
        // Remove the report first: the supervisor cannot be deleted while someone reports to them
        foreach ([$this->reportId ?? 0, $this->supervisorId ?? 0] as $id) {
            $stmt = $this->db->prepare('DELETE FROM Staff WHERE staff_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $this->db->prepare('DELETE FROM ArchivedStaff WHERE email IN (?, ?)');
        $stmt->bind_param('ss', $this->reportEmail, $this->supervisorEmail);
        $stmt->execute();
        $stmt->close();
        $this->db->close();
    }

    // -----------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------

    public function testArchivingAMissingStaffIdReturnsFalseAndChangesNothing(): void
    {
        $staffBefore = $this->countRows('Staff');
        $archiveBefore = $this->countRows('ArchivedStaff');
        $missingId = (int) $this->scalar('SELECT MAX(staff_id) + 1000 FROM Staff');

        $this->assertFalse(archiveStaff($this->db, $missingId, 'unit_test'));
        $this->assertSame($staffBefore, $this->countRows('Staff'));
        $this->assertSame($archiveBefore, $this->countRows('ArchivedStaff'));
    }

    public function testArchivingASupervisorWithDirectReportsIsRolledBack(): void
    {
        $staffBefore = $this->countRows('Staff');
        $archiveBefore = $this->countRows('ArchivedStaff');

        try {
            archiveStaff($this->db, $this->supervisorId, 'unit_test');
            $this->fail('The archive succeeded, but it should have been blocked by the direct report');
        } catch (mysqli_sql_exception $e) {
            // 1451 = cannot delete a row that another row still references
            $this->assertSame(1451, $e->getCode());
        }

        // rollback() must undo the copy into ArchivedStaff, leaving both tables as they were
        $this->assertSame(1, $this->countStaff($this->supervisorId), 'supervisor should still be in Staff');
        $this->assertSame(0, $this->countArchived($this->supervisorId),
            'rollback should have removed the copy in ArchivedStaff');
        $this->assertSame($staffBefore, $this->countRows('Staff'));
        $this->assertSame($archiveBefore, $this->countRows('ArchivedStaff'));
    }

    public function testArchivingAStaffMemberMovesThemToArchivedStaff(): void
    {
        $staffBefore = $this->countRows('Staff');
        $archiveBefore = $this->countRows('ArchivedStaff');

        $this->assertTrue(archiveStaff($this->db, $this->reportId, 'unit_test'));

        $this->assertSame(0, $this->countStaff($this->reportId), 'should be removed from Staff');
        $this->assertSame(1, $this->countArchived($this->reportId), 'should be added to ArchivedStaff once');
        $this->assertSame($staffBefore - 1, $this->countRows('Staff'));
        $this->assertSame($archiveBefore + 1, $this->countRows('ArchivedStaff'));
    }

    public function testTheArchivedRecordKeepsTheirDetails(): void
    {
        archiveStaff($this->db, $this->reportId, 'unit_test');

        $stmt = $this->db->prepare('SELECT * FROM ArchivedStaff WHERE staff_id = ?');
        $stmt->bind_param('i', $this->reportId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Names, as shown in the archive list
        $this->assertSame('Test', $row['first_name']);
        $this->assertSame('Report', $row['last_name']);
        $this->assertSame($this->reportEmail, $row['email']);
        $this->assertSame($this->scalar('SELECT job_title FROM Job WHERE job_id = ?', 'i', [$this->jobId]), $row['job_title']);
        $this->assertSame($this->scalar('SELECT dept_name FROM Department WHERE dept_id = ?', 'i', [$this->deptId]), $row['dept_name']);
        $this->assertSame('Test Supervisor', $row['supervisor_name']);

        // IDs, used by Restore to match them back to current records
        $this->assertSame($this->jobId, (int) $row['job_id']);
        $this->assertSame($this->rankId, (int) $row['rank_id']);
        $this->assertSame($this->deptId, (int) $row['dept_id']);
        $this->assertSame($this->supervisorId, (int) $row['supervisor_id']);

        // Who archived it, and when
        $this->assertSame('unit_test', $row['archived_by']);
        $this->assertNotNull($row['archived_at']);
    }

    public function testASupervisorCanBeArchivedOnceNobodyReportsToThem(): void
    {
        $this->assertTrue(archiveStaff($this->db, $this->reportId, 'unit_test'));
        $this->assertTrue(archiveStaff($this->db, $this->supervisorId, 'unit_test'));

        $this->assertSame(0, $this->countStaff($this->supervisorId));
        $this->assertSame(1, $this->countArchived($this->supervisorId));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function addStaff(string $first, string $last, string $email, ?int $supervisorId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Staff (first_name, last_name, email, supervisor_id, job_id, rank_id, dept_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssiiii', $first, $last, $email, $supervisorId, $this->jobId, $this->rankId, $this->deptId);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /** Run a query and return the first column of the first row. */
    private function scalar(string $sql, string $types = '', array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row[0] ?? null;
    }

    private function countRows(string $table): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM $table");
    }

    private function countStaff(int $staffId): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM Staff WHERE staff_id = ?', 'i', [$staffId]);
    }

    private function countArchived(int $staffId): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM ArchivedStaff WHERE staff_id = ?', 'i', [$staffId]);
    }
}
