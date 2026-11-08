<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Redirect users who are already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: pages/notes.php");
    exit;
}

$error = "";
$success = "";

// Registration success message
if (isset($_GET["registered"]) && $_GET["registered"] == "1") {
    $success = "Your account has been created successfully! You can now log in.";
}

$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $plain_password = $_POST["password"] ?? "";

    if ($email === "" || $plain_password === "") {

        $error = "Please enter your email address and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        try {

            // Find the user by email
            $stmt = $pdo->prepare(
                "SELECT user_id, first_name, last_name, email, password
                 FROM users
                 WHERE email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                ":email" => $email
            ]);

            $user = $stmt->fetch();

            // Verify the password
            if ($user && password_verify($plain_password, $user["password"])) {

                // Generate a new session ID
                session_regenerate_id(true);

                // Store logged-in user information
                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["first_name"] = $user["first_name"];
                $_SESSION["last_name"] = $user["last_name"];
                $_SESSION["email"] = $user["email"];

                // Redirect to Notes dashboard
                header("Location: notes.php");
                exit;

            } else {

                $error = "Incorrect email address or password.";

            }

        } catch (PDOException $e) {

            error_log("Login error: " . $e->getMessage());
            $error = "Something went wrong. Please try again.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | StudyHive</title>

    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <main class="login-page">

        <!-- LEFT SIDE: LOGIN FORM -->
        <section class="login-section">

            <div class="login-container">

                <!-- STUDYHIVE LOGO -->
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="StudyHive Logo">
                </div>

                <h1>LOG IN</h1>

                <!-- SUCCESS MESSAGE -->
                <?php if ($success !== ""): ?>
                <div class="login-success">
                    <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?>
                </div>
                <?php endif; ?>

                <!-- ERROR MESSAGE -->
                <?php if ($error !== ""): ?>
                <div class="login-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
                <?php endif; ?>

                <!-- LOGIN FORM -->
                <form action="login.php" method="POST">

                    <!-- EMAIL ADDRESS -->
                    <div class="form-group">

                        <label for="email">EMAIL ADDRESS</label>

                        <input type="email" id="email" name="email" placeholder="bayBee@gmail.com"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" autocomplete="email" required>

                    </div>

                    <!-- PASSWORD -->
                    <div class="form-group">

                        <div class="password-heading">

                            <label for="password">PASSWORD</label>

                            <a href="#" class="forgot-password">
                                FORGOT PASSWORD?
                            </a>

                        </div>

                        <input type="password" id="password" name="password" placeholder="Enter your password"
                            autocomplete="current-password" required>

                    </div>

                    <!-- REMEMBER ME -->
                    <div class="remember-group">

                        <input type="checkbox" id="remember" name="remember" value="1">

                        <label for="remember">
                            REMEMBER ME!
                        </label>

                    </div>

                    <!-- LOGIN BUTTON -->
                    <button type="submit" class="login-btn">
                        LOGIN
                    </button>

                </form>

                <!-- SIGN UP LINK -->
                <p class="signup-text">
                    DOESN'T HAVE AN ACCOUNT YET?
                    <a href="signup.php">SIGN UP</a>
                </p>

            </div>

        </section>

        <!-- RIGHT SIDE: HONEY ILLUSTRATION -->
        <section class="login-illustration">

            <div class="illustration-background"></div>

            <img src="assets/images/honey.png" alt="StudyHive Honey Illustration" class="honey-illustration">

        </section>

    </main>

</body>

</html>