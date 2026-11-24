<?php
//======================================================================
// Create Account
//======================================================================

include_once (realpath(dirname(__FILE__).'/path.php'));
include_once (realpath(dirname(__FILE__).'/config.php'));
include_once (realpath(dirname(__FILE__).'/config.local.php'));


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // collect value of input field
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $street = $_POST['street'];
    $street_additional = $_POST['street_additional'];
    $city = $_POST['city'];
    $state_code = strtoupper(trim($_POST['state']));
    $post_code = trim($_POST['zip']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($username) || empty($password) ) {
        echo "All fields are required.";
        exit();
    }

    // Optional address fields: store NULL when left blank
    $state_code = $state_code === '' ? null : $state_code;
    $post_code = $post_code === '' ? null : $post_code;

    // Match the Contacts column sizes: phone VARCHAR(20), state CHAR(2), post_code CHAR(5)
    // password_hash() ignores anything past 72 bytes
    if (strlen($password) > 72) {
        echo "Password must be 72 characters or fewer.";
        exit();
    }
    if (strlen($phone) > 20) {
        echo "Phone number must be 20 characters or fewer.";
        exit();
    }
    if ($state_code !== null && !preg_match('/^[A-Z]{2}$/', $state_code)) {
        echo "State must be a 2-letter code, for example CT.";
        exit();
    }
    if ($post_code !== null && !preg_match('/^[0-9]{5}$/', $post_code)) {
        echo "Zip code must be 5 digits.";
        exit();
    }

    // Check if username already exists
    $check_user = $db_connection->prepare(
        "SELECT u.user_id 
        FROM Users u 
        INNER JOIN Credentials c 
        ON u.user_id = c.user_id 
        WHERE c.username = ?");
    $check_user->bind_param("s", $username);
    $check_user->execute();
    $check_user->store_result();

    if ($check_user->num_rows > 0) {
        echo "Username already exists. Please choose a different username.";
        $check_user->close();
        exit();
    }
    $check_user->close();

    // Insert into Contacts table
    $insert_contact = $db_connection->prepare("INSERT INTO Contacts (first_name, last_name, email, phone, street_1, street_2, city, state_code, post_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_contact->bind_param("sssssssss", $first_name, $last_name, $email, $phone, $street, $street_additional, $city, $state_code, $post_code);
    
    if ($insert_contact->execute()) {
        // Get the contact_id of the newly inserted contact
        $contact_id = $insert_contact->insert_id;
        $insert_contact->close();

        // Insert into Users table with default role_id = 2 (user)
        $role_id = 2; // Default role_id for regular users
        $insert_user = $db_connection->prepare("INSERT INTO Users (contact_id, role_id) VALUES (?, ?)");
        $insert_user->bind_param("ii", $contact_id, $role_id);
        
        if ($insert_user->execute()) {
            // Get the user_id of the newly inserted user
            $user_id = $insert_user->insert_id;
            $insert_user->close();

            // Insert into Credentials table with hashed password
            // password_hash() generates a unique salt for each password
            $password_salted = password_hash($password, PASSWORD_DEFAULT);
            $insert_credential = $db_connection->prepare("INSERT INTO Credentials (user_id, username, password_salted) VALUES (?, ?, ?)");
            $insert_credential->bind_param("iss", $user_id, $username, $password_salted);

            if ($insert_credential->execute()) {
                $insert_credential->close();
                echo "Account created successfully! You will be redirected to the login page in <span id='countdown'>5</span> seconds...";
                echo "<br><a href='" . BASE_URL . "/index.php'>Click here to login now</a>";
                echo "<script>
                        let timeLeft = 5;
                        const countdown = document.getElementById('countdown');
                        
                        const timer = setInterval(function() {
                            timeLeft--;
                            countdown.textContent = timeLeft;
                            
                            if (timeLeft <= 0) {
                                clearInterval(timer);
                                window.location.href = '" . BASE_URL . "/index.php';
                            }
                        }, 1000);
                      </script>";
            } else {
                echo "Error creating credentials: " . $db_connection->error;
            }
        } else {
            echo "Error creating user: " . $db_connection->error;
        }
    } else {
        echo "Error creating contact: " . $db_connection->error;
    }   
    $db_connection->close();
} else {
    echo "Invalid request method.";
}

exit();


?>