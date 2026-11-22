<?php
// Database initialization component: run through php/start.php only.
if (!defined('DB_SETUP_RUNNING')) {
    http_response_code(403);
    exit('Run php/start.php to initialize the database.');
}

$db_connection->query(
    "CREATE TABLE `Staff` (
        `staff_id` INT(11) NOT NULL AUTO_INCREMENT,
        `first_name` VARCHAR(50) NOT NULL,
        `last_name` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `supervisor_id` INT(11) NULL,
        `job_id` INT(11) NOT NULL,
        `rank_id` INT(11) NOT NULL,
        `dept_id` INT(11) NOT NULL,
        PRIMARY KEY (`staff_id`),
        UNIQUE KEY `uq_staff_email` (`email`),
        KEY `idx_staff_name` (`last_name`, `first_name`),
        CONSTRAINT `fk_staff_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `Staff` (`staff_id`),
        CONSTRAINT `fk_staff_job` FOREIGN KEY (`job_id`) REFERENCES `Job` (`job_id`),
        CONSTRAINT `fk_staff_rank` FOREIGN KEY (`rank_id`) REFERENCES `Ranks` (`ranks_id`),
        CONSTRAINT `fk_staff_department` FOREIGN KEY (`dept_id`) REFERENCES `Department` (`dept_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Organization and college come from the staff member's Department (3NF).
// Dummy records. Insert managers BEFORE their reports, so every non-null supervisor_id already exists
$seed_stmt = $db_connection->prepare(
    "INSERT INTO `Staff` (`staff_id`, `first_name`, `last_name`, `email`, `supervisor_id`, `job_id`, `rank_id`, `dept_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$seed_rows = [
    // Office of the President (dept 1)
    [1, 'Leroy', 'Jenkins', 'leroy.jenkins@example.com', null, 1, 1, 1],
    // Office of the Provost (dept 2)
    [2, 'Tifa', 'Lockhart', 'tifa.lockhart@example.com', 1, 2, 1, 2],
    // Information Technology (dept 3)
    [3, 'Gordon', 'Freeman', 'gordon.freeman@example.com', 1, 3, 2, 3],
    [5, 'Samus', 'Aran', 'samus.aran@example.com', 3, 4, 2, 3],
    [6, 'Claptrap', 'Hyperion', 'claptrap.hyperion@example.com', 5, 5, 4, 3],
    [7, 'Tracer', 'Oxton', 'tracer.oxton@example.com', 5, 5, 4, 3],
    [29, 'Tom', 'Nook', 'tom.nook@example.com', 5, 5, 4, 3],
    // Computer Science (dept 4)
    [4, 'Aerith', 'Gainsborough', 'aerith.gainsborough@example.com', 2, 7, 2, 4],
    [8, 'Geralt', 'Rivia', 'geralt.rivia@example.com', 4, 8, 5, 4],
    [24, 'Yennefer', 'Vengerberg', 'yennefer.vengerberg@example.com', 4, 8, 5, 4],
    [25, 'Triss', 'Merigold', 'triss.merigold@example.com', 4, 8, 5, 4],
    [26, 'Zoltan', 'Chivay', 'zoltan.chivay@example.com', 4, 12, 6, 4],
    // Registrar (dept 5)
    [9, 'Lara', 'Croft', 'lara.croft@example.com', 2, 9, 3, 5],
    [10, 'Ezio', 'Auditore', 'ezio.auditore@example.com', 9, 10, 4, 5],
    // Human Resources (dept 6)
    [11, 'Sarah', 'Kerrigan', 'sarah.kerrigan@example.com', 1, 11, 2, 6],
    [12, 'Jim', 'Raynor', 'jim.raynor@example.com', 11, 12, 6, 6],
    [30, 'Tychus', 'Findlay', 'tychus.findlay@example.com', 11, 12, 6, 6],
    // Business Administration (dept 7)
    [13, 'Sid', 'Meier', 'sid.meier@example.com', 2, 7, 2, 7],
    [14, 'Wario', 'Ware', 'wario.ware@example.com', 13, 8, 5, 7],
    [27, 'Lucca', 'Ashtear', 'lucca.ashtear@example.com', 13, 8, 5, 7],
    [28, 'Crono', 'Truce', 'crono.truce@example.com', 13, 8, 5, 7],
    // Student Success / Library (dept 8)
    [15, 'Deckard', 'Cain', 'deckard.cain@example.com', 2, 13, 2, 8],
    [16, 'Alyx', 'Vance', 'alyx.vance@example.com', 15, 14, 4, 8],
    [31, 'Marsha', 'Johnson', 'marsha.johnson@example.com', 1, 18, 3, 8],
    [32, 'Harvey', 'Milk', 'harvey.milk@example.com', 31, 10, 4, 8],
    [33, 'Dolores', 'Huerta', 'dolores.huerta@example.com', 31, 10, 4, 8],
    // Career Center (dept 9)
    [34, 'Malala', 'Yousafzai', 'malala.yousafzai@example.com', 1, 19, 3, 9],
    [35, 'Fannie', 'Hamer', 'fannie.hamer@example.com', 34, 20, 4, 9],
    // Athletics (dept 10)
    [17, 'Billie Jean', 'King', 'billiejean.king@example.com', 1, 15, 2, 10],
    [18, 'Ryu', 'Hoshi', 'ryu.hoshi@example.com', 17, 16, 3, 10],
    [19, 'Ken', 'Masters', 'ken.masters@example.com', 17, 16, 3, 10],
    [20, 'Little', 'Mac', 'little.mac@example.com', 18, 6, 4, 10],
    [21, 'Chun-Li', 'Zhang', 'chunli.zhang@example.com', 19, 6, 4, 10],
    [22, 'Mercy', 'Ziegler', 'mercy.ziegler@example.com', 17, 17, 4, 10],
    [23, 'Toadette', 'Kinopio', 'toadette.kinopio@example.com', 17, 12, 6, 10],
    // Fine Arts (dept 11)
    [38, 'Frida', 'Kahlo', 'frida.kahlo@example.com', 4, 21, 3, 11],
    [39, 'Georgia', 'O\'Keeffe', 'georgia.okeeffe@example.com', 38, 8, 5, 11],
    [40, 'Claude', 'Monet', 'claude.monet@example.com', 38, 8, 5, 11],
    [72, 'Peppermint', 'Butler', 'peppermint.butler@example.com', 38, 12, 6, 11],
    // English (dept 12)
    [41, 'Marceline', 'Abadeer', 'marceline.abadeer@example.com', 4, 21, 3, 12],
    [42, 'Simon', 'Petrikov', 'simon.petrikov@example.com', 41, 8, 5, 12],
    [43, 'Bonnibel', 'Bubblegum', 'bonnibel.bubblegum@example.com', 41, 8, 5, 12],
    // History (dept 13)
    [44, 'Link', 'Hero', 'link.hero@example.com', 4, 21, 3, 13],
    [45, 'Zelda', 'Hyrule', 'zelda.hyrule@example.com', 44, 8, 5, 13],
    [46, 'Ganondorf', 'Dragmire', 'ganondorf.dragmire@example.com', 44, 8, 5, 13],
    // Mathematics (dept 14)
    [47, 'Andrew', 'Carnegie', 'andrew.carnegie@example.com', 4, 21, 3, 14],
    [48, 'Wirt', 'Pottsfield', 'wirt.pottsfield@example.com', 47, 8, 5, 14],
    [49, 'Greg', 'Tadpole', 'greg.tadpole@example.com', 47, 8, 5, 14],
    // Biology (dept 15)
    [50, 'Kermit', 'Frog', 'kermit.frog@example.com', 4, 21, 3, 15],
    [51, 'Fozzie', 'Bear', 'fozzie.bear@example.com', 50, 8, 5, 15],
    [52, 'Bunsen', 'Honeydew', 'bunsen.honeydew@example.com', 50, 8, 5, 15],
    // Chemistry (dept 16)
    [53, 'Sam', 'Eagle', 'sam.eagle@example.com', 4, 21, 3, 16],
    [54, 'Miss', 'Piggy', 'miss.piggy@example.com', 53, 8, 5, 16],
    [55, 'Rowlf', 'Dog', 'rowlf.dog@example.com', 53, 8, 5, 16],
    // Physics (dept 17)
    [56, 'Yayoi', 'Kusama', 'yayoi.kusama@example.com', 4, 21, 3, 17],
    [57, 'Pablo', 'Picasso', 'pablo.picasso@example.com', 56, 8, 5, 17],
    [58, 'Salvador', 'Dali', 'salvador.dali@example.com', 56, 8, 5, 17],
    // Psychology (dept 18)
    [59, 'Bob', 'Ross', 'bob.ross@example.com', 4, 21, 3, 18],
    [60, 'Andy', 'Warhol', 'andy.warhol@example.com', 59, 8, 5, 18],
    [61, 'Mary', 'Cassatt', 'mary.cassatt@example.com', 59, 8, 5, 18],
    // Music (dept 19)
    [62, 'Keith', 'Haring', 'keith.haring@example.com', 4, 21, 3, 19],
    [63, 'Jean-Michel', 'Basquiat', 'jeanmichel.basquiat@example.com', 62, 8, 5, 19],
    [64, 'Artemisia', 'Gentileschi', 'artemisia.gentileschi@example.com', 62, 8, 5, 19],
    // Theatre (dept 20)
    [65, 'Leonardo', 'da Vinci', 'leonardo.davinci@example.com', 4, 21, 3, 20],
    [66, 'Melinda', 'Gates', 'melinda.gates@example.com', 65, 8, 5, 20],
    [67, 'Mackenzie', 'Scott', 'mackenzie.scott@example.com', 65, 8, 5, 20],
    // Nursing (dept 21)
    [37, 'Dolly', 'Parton', 'dolly.parton@example.com', 2, 7, 2, 21],
    [68, 'Finn', 'Mertens', 'finn.mertens@example.com', 37, 8, 5, 21],
    [69, 'Beatrice', 'Bluebird', 'beatrice.bluebird@example.com', 37, 8, 5, 21],
    [73, 'Camilla', 'Chicken', 'camilla.chicken@example.com', 37, 12, 6, 21],
    // Education (dept 22)
    [36, 'Fred', 'Rogers', 'fred.rogers@example.com', 2, 7, 2, 22],
    [70, 'Paul', 'Newman', 'paul.newman@example.com', 36, 8, 5, 22],
    [71, 'Rizzo', 'Rat', 'rizzo.rat@example.com', 36, 8, 5, 22],
];
foreach ($seed_rows as $row) {
    $staff_id = $row[0];
    $first_name = $row[1];
    $last_name = $row[2];
    $email = $row[3];
    $supervisor_id = $row[4];
    $job_id = $row[5];
    $rank_id = $row[6];
    $dept_id = $row[7];
    $seed_stmt->bind_param("isssiiii", $staff_id, $first_name, $last_name, $email, $supervisor_id, $job_id, $rank_id, $dept_id);
    $seed_stmt->execute();
}
$seed_stmt->close();
