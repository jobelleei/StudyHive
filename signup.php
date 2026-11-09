<?php
session_start();

require_once __DIR__ . "/config/database.php";

$error = "";

$first_name = "";
$last_name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $plain_password = $_POST["password"] ?? "";

    // Validate required fields
    if (
        $first_name === "" ||
        $last_name === "" ||
        $email === "" ||
        $plain_password === ""
    ) {
        $error = "Please complete all required fields.";

    } elseif (mb_strlen($first_name) > 100 || mb_strlen($last_name) > 100) {
        $error = "First name and last name must not exceed 100 characters.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($email) > 150) {
        $error = "Email address must not exceed 150 characters.";

    } elseif (mb_strlen($plain_password) < 6) {
        $error = "Your password must contain at least 6 characters.";

    } else {

        try {
            // Check whether the email already exists
            $checkEmail = $pdo->prepare(
                "SELECT user_id FROM users WHERE email = :email LIMIT 1"
            );

            $checkEmail->execute([
                ":email" => $email
            ]);

            if ($checkEmail->fetch()) {

                $error = "This email address is already registered.";

            } else {

                // Securely hash the password
                $hashed_password = password_hash(
                    $plain_password,
                    PASSWORD_DEFAULT
                );

                // Insert the new account
                $insertUser = $pdo->prepare(
                    "INSERT INTO users
                    (first_name, last_name, email, password)
                    VALUES
                    (:first_name, :last_name, :email, :password)"
                );

                $insertUser->execute([
                    ":first_name" => $first_name,
                    ":last_name" => $last_name,
                    ":email" => $email,
                    ":password" => $hashed_password
                ]);

                // Redirect to login after successful registration
                header("Location: login.php?registered=1");
                exit;
            }

        } catch (PDOException $e) {

            error_log("Registration error: " . $e->getMessage());

            // Handle duplicate email if a UNIQUE index exists
            if ($e->getCode() === "23000") {
                $error = "This email address is already registered.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up | StudyHive</title>

    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/signup.css">
</head>

<body>

    <main class="signup-page">

        <section class="signup-section">

            <div class="signup-container">

                <div class="signup-logo">
                    <img src="assets/images/logo.png" alt="StudyHive Logo">
                </div>

                <h1>SIGN UP</h1>

                <?php if ($error !== ""): ?>
                <div class="signup-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
                <?php endif; ?>

                <form action="signup.php" method="POST">

                    <div class="form-group">
                        <label for="first_name">FIRST NAME</label>
                        <input type="text" id="first_name" name="first_name" placeholder="Enter your first name"
                            maxlength="100" value="<?= htmlspecialchars($first_name, ENT_QUOTES, "UTF-8") ?>"
                            autocomplete="given-name" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">LAST NAME</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Enter your last name"
                            maxlength="100" value="<?= htmlspecialchars($last_name, ENT_QUOTES, "UTF-8") ?>"
                            autocomplete="family-name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">EMAIL ADDRESS</label>
                        <input type="email" id="email" name="email" placeholder="bayBee@gmail.com" maxlength="150"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">PASSWORD</label>
                        <input type="password" id="password" name="password" placeholder="Enter at least 6 characters"
                            minlength="6" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="signup-btn">
                        SIGN UP
                    </button>

                </form>

                <p class="login-text">
                    ALREADY HAVE AN ACCOUNT?
                    <a href="login.php">LOG IN</a>
                </p>

            </div>

        </section>

        <section class="signup-illustration">

            <div class="illustration-background"></div>

            <img src="assets/images/honey.png" alt="StudyHive Honey Illustration" class="honey-illustration">

        </section>

    </main>

</body>

</html>