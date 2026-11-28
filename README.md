# University Org Chart Database

A classroom web application for browsing university staff and managing
organizational information.

Administrators manage buildings, jobs, staff, departments, colleges,
organizations, classifications, ranks, and user accounts.

Users can browse the Staff Directory, Divisions and Departments, and
Classifications and Ranks.

## Features

- Everyone: log in, sign up, and send forgot password requests to admins
- Users: Staff Directory search, viewing divisions/classifications, account
  management
- Admins: table management, user management, archive/restore staff entries

## Technology

The application uses PHP, MariaDB, Bootstrap 5.3, JavaScript, and CSS. Apache
and MariaDB run locally through XAMPP.

## Local Installation

1. Install XAMPP.
2. Place the project in C:\xampp\htdocs\org_chart.
3. Open the org_chart folder in VS Code.
4. Start Apache and MySQL in the XAMPP Control Panel.
5. Check that php/path.php sets $directory to "/org_chart".
6. Create php/config.local.php with the database settings required below.
7. Initialize a fresh local database using the setup instructions.
8. Open http://localhost/org_chart/ to log in.
9. Perform a hard refresh (CTRL + SHIFT + R) after every pull.

### Local Configuration

The php/config.local.php file defines DB_HOST, DB_USER, DB_PASSWORD, and
DB_NAME. Use your local database credentials.

Keep php/config.local.php out of Git. Use this template for it:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'org_chart');
```

### Database Setup

Set DB_NAME in php/config.local.php to a fresh database name. Then open
http://localhost/org_chart/php/start.php.

The setup script creates the configured database if needed and loads the
database tables, sample records, and Staff Directory view.

WARNING: start.php resets existing tables in the configured database. Back up
any data you want to keep before using it.

For normal use, open http://localhost/org_chart/. Run start.php only when
intentionally initializing or resetting data.

Pulling code from GitHub does not automatically update a local database. Schema
changes may require additional setup.

### Sample Accounts

Sample login credentials are defined in php/database/credentials.php.
Administrators and regular users have different dashboards.

## Database Design

- Login: Roles, Contacts, Users, Credentials, PasswordResetRequests
- Org chart: Organization, Department, Building, College, Staff, Job, Ranks,
  Classification
- Archives: ArchivedStaff, ArchivedUsers
- View: vw_staff_directory (staff joined with every lookup table, used by the
  Staff Directory)

Design choices:

- Third normal form: a staff member's organization and college come from their
  department, so they're stored once (on Department) instead of on every staff
  row. This was changed from our initial ERD.
- Archive tables have no foreign keys, on purpose: they keep history even after
  a job or department is deleted.
- Constraints enforce the rules: UNIQUE names and emails, CHECK min salary ≤ max
  salary, cascading deletes for credentials and reset requests.
- Passwords are hashed with password_hash(), each with its own salt.

## Bonus Features

### Search Feature

Users can search the staff directory using relevant keywords. They can also
filter by various dropdowns to narrow search results. Search and filter can be
used at the same time.

Column headers can sort the list from A-Z or Z-A (as denoted by the direction of
the arrow). The sort feature works with current search and filters. Reset clears
everything.

The search function begins when the page queries the vw_staff_directory view,
which joins Staff with every lookup table. The search box and each chosen filter
add conditions to the query's WHERE clause (LIKE '%keyword%' for the search, =
id for filters), joined with AND. Everything goes through prepared statements,
and only known column names can be used for filtering or sorting, so the search
can't be used for SQL injection.

### Dark Mode and Light Mode Toggle

A button in the shared navigation switches between light and dark mode. The
browser remembers the selected theme using localStorage.

Implementation files include include/head.php, include/header.php,
include/footer.php, js/main.js, and css/style.css.

To test:

1. Open a page and activate the Dark mode button.
2. Confirm that the page colors change.
3. Open another page and refresh it.
4. Confirm that the selected theme remains active.
5. Activate the button again to return to light mode.

Note: if the dark mode button is not available, you may be viewing a cached
version of the page. Please perform a hard refresh with CTRL + SHIFT + R.

### Accessibility: Skip to Main Content

A keyboard accessible link allows users to bypass repeated navigation. The link
becomes visible when it receives keyboard focus.

The shared header contains the link. Updated pages provide a main content
destination with id="main-content" and tabindex="-1".

To test on an updated page:

1. Reload the page and press Tab until the skip link appears.
2. Press Enter to move focus to the main content.
3. Press Tab again and confirm navigation continues within that content.
4. Check that the link is readable in both light and dark mode.

### mysqli Rollback

This feature is used many times throughout the app for error-handling purposes.
It is used for the following actions:

| Action                 | File                  | Steps that succeed or fail together               |
| ---------------------- | --------------------- | ------------------------------------------------- |
| Archive staff          | php/staff_archive.php | Copy into ArchivedStaff → delete from Staff       |
| Restore staff          | admin/staff.php       | Insert into Staff → delete from ArchivedStaff     |
| Add user               | admin/users.php       | Insert Contacts → Users → Credentials             |
| Archive/delete user    | admin/users.php       | Copy into ArchivedUsers → delete Users → Contacts |
| Admin password reset   | admin/users.php       | Update password → resolve the reset request       |
| Deactivate own account | user/account.php      | Copy into ArchivedUsers → delete Users → Contacts |

### Unit Testing

The project uses PHPUnit 11, the standard testing framework for PHP. PHPUnit is
included in the repository as a single file (tools/phpunit.phar), so nothing
needs to be installed. XAMPP's PHP runs it directly.

Before running the tests, start MySQL in the XAMPP Control Panel and run
php/start.php so the database tables exist. Then, from the project folder:

```
C:\xampp\php\php.exe tools\phpunit.phar
```

All 13 tests should pass, ending with `OK (13 tests, 61 assertions)`.

The tests are in the tests folder. phpunit.xml tells PHPUnit where to find them,
and tests/bootstrap.php loads the database settings before they run.

DatabaseConnectionTest (8 tests) checks connecting to the database:

- The connection settings in php/config.local.php are defined
- The app connects to the database server and uses the database in DB_NAME
- A simple query runs and returns the correct result
- Every table and the vw_staff_directory view exist
- The connection uses the utf8mb4 character set
- php/config.php, the connection every page uses, works
- A wrong password is rejected with "Access denied" (MySQL error 1045)

StaffArchiveTest (5 tests) checks archiving a staff member with archiveStaff():

- Archiving a staff ID that does not exist changes nothing
- Archiving a supervisor who still has direct reports fails, and rollback()
  leaves both tables exactly as they were (this also proves the mysqli Rollback
  bonus feature)
- Archiving a staff member moves them from Staff to ArchivedStaff
- The archived record keeps their name, job, department, supervisor, and IDs
- A supervisor can be archived once nobody reports to them

Before each archive test, setUp() creates two temporary staff members, and
tearDown() removes them afterwards, so the real data is never changed.

The archive code was moved out of admin/staff.php into its own function in
php/staff_archive.php. A test cannot run part of a page, so the function lives
in its own file. The Manage Staff page and the test both call the same function,
so the test checks the exact code behind the Archive button.

To see the tests catch a problem, stop MySQL in the XAMPP Control Panel and run
them again. Every test reports an error (E) instead of passing. Start MySQL
again and they pass.

## Project Scope

This application is intended for classroom learning and demonstration. It uses
sample data and is not intended for production deployment.
