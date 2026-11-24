<?php
/**
 * To show each classification, its ranks, and the staff in each rank
 *
 * @author    Jackie Isabella <isabellaj2@southernct.edu>
 * @copyright 2026 
 * @date      2026-11-24
 * @version   1.0
 */
include_once (realpath(dirname(__FILE__).'/../php/path.php'));
include_once (ROOT_PATH . '/php/session.php');
include_once (ROOT_PATH . '/php/check_user.php');

$page_name = "Classifications and Ranks"; // Set page name for active link in header

include_once (ROOT_PATH . '/php/config.php');

// Query 1: classifications and their ranks
$result = $db_connection->query(
  "SELECT cl.class_id, cl.class_name,
          r.ranks_id, r.ranks_title, r.min_salary, r.max_salary
   FROM Classification cl
   LEFT JOIN Ranks r ON r.class_id = cl.class_id
   ORDER BY cl.class_id, r.ranks_id"
);

// Group the rows by classification
$classes = [];
while ($row = $result->fetch_assoc()) {
  $id = $row['class_id'];
  if (!isset($classes[$id])) {
    $classes[$id] = ['name' => $row['class_name'], 'ranks' => []];
  }
  if ($row['ranks_id'] !== null) {
    $classes[$id]['ranks'][] = $row;
  }
}

// Query 2: staff in one rank, from the view
$staff_stmt = $db_connection->prepare(
  "SELECT last_name, first_name, job_title, dept_name
   FROM vw_staff_directory
   WHERE ranks_id = ?
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

      <h1 class="mb-3">Classifications &amp; Ranks</h1>

      <div class="row g-4">
        <?php foreach ($classes as $class_id => $class): ?>
          <div class="col-md-6">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h2 class="h5 mb-0"><?php echo htmlspecialchars($class['name']); ?></h2>
              </div>
              <div class="card-body">
                <?php if (count($class['ranks']) > 0): ?>
                  <div class="accordion" id="acc-class-<?php echo $class_id; ?>">
                    <?php foreach ($class['ranks'] as $rank): ?>
                      <div class="accordion-item">
                        <h3 class="accordion-header">
                          <button class="accordion-button collapsed" type="button"
                                  data-bs-toggle="collapse"
                                  data-bs-target="#rank-<?php echo $rank['ranks_id']; ?>">
                            Rank <?php echo htmlspecialchars($rank['ranks_title']); ?>
                            &nbsp;<small class="text-muted">$<?php echo number_format($rank['min_salary']); ?> &ndash; $<?php echo number_format($rank['max_salary']); ?></small>
                          </button>
                        </h3>
                        <div id="rank-<?php echo $rank['ranks_id']; ?>" class="accordion-collapse collapse"
                             data-bs-parent="#acc-class-<?php echo $class_id; ?>">
                          <div class="accordion-body">
                            <?php
                              $staff_stmt->bind_param("i", $rank['ranks_id']);
                              $staff_stmt->execute();
                              $staff = $staff_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            ?>
                            <?php if (count($staff) > 0): ?>
                              <ul class="list-group list-group-flush">
                                <?php foreach ($staff as $person): ?>
                                  <li class="list-group-item">
                                    <?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name']); ?>
                                    <span class="text-muted">&ndash; <?php echo htmlspecialchars($person['job_title']); ?></span>
                                    <?php if ($person['dept_name']): ?>
                                      <span class="badge text-bg-light"><?php echo htmlspecialchars($person['dept_name']); ?></span>
                                    <?php endif; ?>
                                  </li>
                                <?php endforeach; ?>
                              </ul>
                            <?php else: ?>
                              <p class="mb-0">No staff in this rank.</p>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="mb-0">No ranks in this classification.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </main>
    <?php
      $staff_stmt->close();
      $db_connection->close();
    ?>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>