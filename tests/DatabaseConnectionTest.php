<?php
/**
 * Unit tests: connecting to the database.
 *
 * Run all tests from the project folder:   php tools/phpunit.phar
 * Run only this file:                      php tools/phpunit.phar tests/DatabaseConnectionTest.php
 *
 * Run php/start.php first so the tables exist.
 */

use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    /** A fresh connection for each test, closed again in tearDown(). */
    private ?mysqli $db = null;

    protected function setUp(): void
    {
        if (!defined('DB_HOST')) {
            $this->fail('php/config.local.php is missing or does not define the database settings');
        }
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    }

    protected function tearDown(): void
    {
        if ($this->db instanceof mysqli) {
            $this->db->close();
        }
        $this->db = null;
    }

    public function testConnectionSettingsAreDefined(): void
    {
        foreach (['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'] as $name) {
            $this->assertTrue(defined($name), "$name is not defined in php/config.local.php");
        }
        $this->assertNotSame('', DB_HOST, 'DB_HOST is empty');
        $this->assertNotSame('', DB_USER, 'DB_USER is empty');
        $this->assertNotSame('', DB_NAME, 'DB_NAME is empty');
    }

    public function testConnectsToTheDatabaseServer(): void
    {
        $this->assertSame(0, $this->db->connect_errno, 'connect error: ' . $this->db->connect_error);
        $this->assertNotEmpty($this->db->server_info, 'no server version reported');
    }

    public function testUsesTheDatabaseNamedInSettings(): void
    {
        $current = $this->db->query('SELECT DATABASE()')->fetch_row()[0];
        // Windows reports names in lowercase, so compare without case
        $this->assertSame(strtolower(DB_NAME), strtolower((string) $current));
    }

    public function testRunsASimpleQuery(): void
    {
        $this->assertSame(2, (int) $this->db->query('SELECT 1 + 1')->fetch_row()[0]);
    }

    public function testAllTablesAndTheStaffDirectoryViewExist(): void
    {
        $expected = [
            'Roles', 'Contacts', 'Users', 'Credentials', 'PasswordResetRequests',
            'Classification', 'Organization', 'Ranks', 'Job', 'College', 'Building',
            'Department', 'Staff', 'ArchivedStaff', 'ArchivedUsers', 'vw_staff_directory',
        ];
        $found = array_map(
            fn($row) => strtolower($row[0]), // Windows reports table names in lowercase
            $this->db->query('SHOW TABLES')->fetch_all()
        );
        foreach ($expected as $table) {
            $this->assertContains(strtolower($table), $found, "table or view $table is missing (run php/start.php)");
        }
    }

    public function testConnectionUsesTheUnicodeCharacterSet(): void
    {
        $this->db->set_charset('utf8mb4');
        $this->assertSame('utf8mb4', $this->db->character_set_name());
    }

    public function testThePagesConnectionFileWorks(): void
    {
        // php/config.php is the connection every page uses
        require_once PROJECT_ROOT . '/php/path.php';
        ob_start(); // config.php prints its own message if connecting fails
        require PROJECT_ROOT . '/php/config.php';
        $output = trim(ob_get_clean());

        $this->assertTrue(isset($db_connection) && $db_connection instanceof mysqli,
            'php/config.php did not create $db_connection' . ($output !== '' ? ": $output" : ''));
        $roles = (int) $db_connection->query('SELECT COUNT(*) FROM Roles')->fetch_row()[0];
        $db_connection->close();
        $this->assertGreaterThan(0, $roles, 'the Roles table is empty (run php/start.php)');
    }

    public function testRejectsAWrongPassword(): void
    {
        // 1045 = "Access denied". Any other error (e.g. server not running) fails this test.
        $this->expectException(mysqli_sql_exception::class);
        $this->expectExceptionCode(1045);
        new mysqli(DB_HOST, DB_USER, DB_PASSWORD . '_definitely_wrong', DB_NAME);
    }
}
