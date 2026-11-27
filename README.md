# University Org Chart Database

A classroom web application for browsing university staff and managing
organizational information.

Administrators manage buildings, jobs, staff, departments, colleges,
organizations, classifications, ranks, and user accounts.

Users can browse the Staff Directory, Divisions and Departments,
and Classifications and Ranks.

## Technology

The application uses PHP, MariaDB, Bootstrap 5.3, JavaScript, and CSS.
Apache and MariaDB run locally through XAMPP.

## Local Installation

1. Install XAMPP.
2. Place the project in C:\xampp\htdocs\org_chart.
3. Open the org_chart folder in VS Code.
4. Start Apache and MySQL in the XAMPP Control Panel.
5. Check that php/path.php sets $directory to "/org_chart".
6. Create php/config.local.php with the database settings required below.
7. Initialize a fresh local database using the setup instructions.
8. Open http://localhost/org_chart/ to log in.

### Local Configuration

The php/config.local.php file defines DB_HOST, DB_USER, DB_PASSWORD,
DB_NAME, and SALT.

Use your local database credentials. For an existing database, retain
the SALT value used by its authentication setup.

Keep php/config.local.php out of Git.

### Database Setup

Set DB_NAME in php/config.local.php to a fresh database name.
Then open http://localhost/org_chart/php/start.php.

The setup script creates the configured database if needed and loads
the database tables, sample records, and Staff Directory view.

WARNING: start.php resets existing tables in the configured database.
Back up any data you want to keep before using it.

For normal use, open http://localhost/org_chart/.
Run start.php only when intentionally initializing or resetting data.

Pulling code from GitHub does not automatically update a local database.
Schema changes may require additional setup.

### Sample Accounts

Sample login credentials are defined in php/database/credentials.php.
Administrators and regular users have different dashboards.

## Bonus Features

### Dark Mode and Light Mode Toggle

A button in the shared navigation switches between light and dark mode.
The browser remembers the selected theme using localStorage.

Implementation files include include/head.php, include/header.php,
include/footer.php, js/main.js, and css/style.css.

To test:

1. Open a page and activate the Dark mode button.
2. Confirm that the page colors change.
3. Open another page and refresh it.
4. Confirm that the selected theme remains active.
5. Activate the button again to return to light mode.

### Accessibility: Skip to Main Content

A keyboard accessible link allows users to bypass repeated navigation.
The link becomes visible when it receives keyboard focus.

The shared header contains the link. Updated pages provide a main
content destination with id="main-content" and tabindex="-1".

To test on an updated page:

1. Reload the page and press Tab until the skip link appears.
2. Press Enter to move focus to the main content.
3. Press Tab again and confirm navigation continues within that content.
4. Check that the link is readable in both light and dark mode.

## Project Scope

This application is intended for classroom learning and demonstration.
It uses sample data and is not intended for production deployment.