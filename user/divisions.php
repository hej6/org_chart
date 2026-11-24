<?php
/**
 * To show each division, its departments, and the staff in each department
 *
 * @author    Jackie Isabella <isabellaj2@southernct.edu>
 * @copyright 2026 
 * @date      2026-11-24
 * @version   1.0
 */
include_once (realpath(dirname(__FILE__).'/../php/path.php'));
include_once (ROOT_PATH . '/php/session.php');
include_once (ROOT_PATH . '/php/check_user.php');

$page_name = "Divisions and Departments"; // Set page name for active link in header

include_once (ROOT_PATH . '/php/config.php');

// Query 1: divisions (one tab each)
$divisions = $db_connection->query(
  "SELECT org_id, org_name FROM Organization ORDER BY org_id"
)->fetch_all(MYSQLI_ASSOC);

// Query 2: departments in one division, with building name and staff count
$dept_stmt = $db_connection->prepare(
  "SELECT d.dept_id, d.dept_name, b.building_name, COUNT(s.staff_id) AS staff_count
   FROM Department d
   LEFT JOIN Building b ON d.building_id = b.building_id
   LEFT JOIN Staff s    ON s.dept_id = d.dept_id
   WHERE d.org_id = ?
   GROUP BY d.dept_id, d.dept_name, b.building_name
   ORDER BY d.dept_name"
);

// Query 3: staff in one department, from the view
$staff_stmt = $db_connection->prepare(
  "SELECT last_name, first_name, job_title, college_name
   FROM vw_staff_directory
   WHERE dept_id = ?
   ORDER BY last_name, first_name"
);
?>
<!doctype html>
<html lang="en">
  <head>
  <?php include_once (ROOT_PATH . '/include/head.php'); // Include head.php ?>
  </head>
  <body class="<?php echo $page_name; ?>">

  <?php include_once (ROOT_PATH . '/include/header.php'); // Include header.php ?>
    <main role="main" class="container">

      <h1 class="mb-3">Divisions &amp; Departments</h1>

      <!-- Division tabs -->
      <ul class="nav nav-tabs" role="tablist">
        <?php foreach ($divisions as $i => $div): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $i === 0 ? 'active' : ''; ?>"
                    data-bs-toggle="tab"
                    data-bs-target="#div-<?php echo $div['org_id']; ?>"
                    type="button" role="tab">
              <?php echo htmlspecialchars($div['org_name']); ?>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Tab panes -->
      <div class="tab-content">
        <?php foreach ($divisions as $i => $div): ?>
          <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : ''; ?>"
               id="div-<?php echo $div['org_id']; ?>" role="tabpanel">

            <div class="card shadow-sm border-top-0">
              <div class="position-relative">
                <img src="/org_chart/img/division_<?php echo $div['org_id']; ?>.jpg"
                     class="card-img-top"
                     style="width: 100%; aspect-ratio: 3 / 1; object-fit: cover;"
                     alt="<?php echo htmlspecialchars($div['org_name']); ?>">
                <div class="card-img-overlay d-flex flex-column justify-content-end overlay-light">
                  <div class="text-band py-4 px-4">
                    <h2 class="card-title display-5 mb-0"><?php echo htmlspecialchars($div['org_name']); ?></h2>
                  </div>
                </div>
              </div>

              <div class="card-body">

                <?php
                  $dept_stmt->bind_param("i", $div['org_id']);
                  $dept_stmt->execute();
                  $depts = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>

                <?php if (count($depts) > 0): ?>
                  <!-- Department accordion -->
                  <div class="accordion" id="acc-<?php echo $div['org_id']; ?>">
                    <?php foreach ($depts as $dept): ?>
                      <div class="accordion-item">
                        <h3 class="accordion-header">
                          <button class="accordion-button collapsed" type="button"
                                  data-bs-toggle="collapse"
                                  data-bs-target="#dept-<?php echo $dept['dept_id']; ?>">
                            <span><?php echo htmlspecialchars($dept['dept_name']); ?></span>
                            <span class="flex-grow-1 d-flex justify-content-end align-items-center gap-2 me-3">
                              <span class="text-muted" style="font-variant: small-caps; font-size: 1rem;"><?php echo htmlspecialchars($dept['building_name'] ?? 'No building'); ?></span>
                              <span class="badge text-bg-secondary"><?php echo $dept['staff_count']; ?> staff</span>
                            </span>
                          </button>
                        </h3>
                        <div id="dept-<?php echo $dept['dept_id']; ?>" class="accordion-collapse collapse"
                             data-bs-parent="#acc-<?php echo $div['org_id']; ?>">
                          <div class="accordion-body">
                            <?php
                              $staff_stmt->bind_param("i", $dept['dept_id']);
                              $staff_stmt->execute();
                              $staff = $staff_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            ?>
                            <?php if (count($staff) > 0): ?>
                              <ul class="list-group list-group-flush">
                                <?php foreach ($staff as $person): ?>
                                  <li class="list-group-item">
                                    <?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name']); ?>
                                    <span class="text-muted">&ndash; <?php echo htmlspecialchars($person['job_title']); ?></span>
                                    <?php if ($person['college_name']): ?>
                                      <span class="badge text-bg-light"><?php echo htmlspecialchars($person['college_name']); ?></span>
                                    <?php endif; ?>
                                  </li>
                                <?php endforeach; ?>
                              </ul>
                            <?php else: ?>
                              <p class="mb-0">No staff in this department.</p>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="mb-0">No departments in this division.</p>
                <?php endif; ?>

              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </main>
    <?php
      $dept_stmt->close();
      $staff_stmt->close();
      $db_connection->close();
    ?>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>