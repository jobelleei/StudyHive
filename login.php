<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Redirect users who are already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: pages/feed.php");
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

                // Redirect to the Feed (home page after login)
                header("Location: pages/feed.php");
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
    <link rel="stylesheet" href="assets/css/login.css?v=blob2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Coiny&display=swap">
    <link rel="stylesheet" href="assets/css/fonts.css?v=4">
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

                <p class="login-subtitle">Welcome back, busy bee! Sign in to keep studying.</p>

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

                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" />
                            </svg>
                            <input type="email" id="email" name="email" placeholder="bayBee@gmail.com"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" autocomplete="email" required>
                        </div>

                    </div>

                    <!-- PASSWORD -->
                    <div class="form-group">

                        <div class="password-heading">

                            <label for="password">PASSWORD</label>

                            <a href="#" class="forgot-password">
                                FORGOT PASSWORD?
                            </a>

                        </div>

                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="4" y="11" width="16" height="10" rx="2" />
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                            </svg>
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                autocomplete="current-password" required>
                            <button type="button" class="toggle-password" id="togglePassword"
                                aria-label="Show password" aria-pressed="false">
                                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 3l18 18" />
                                    <path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6" />
                                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                                </svg>
                            </button>
                        </div>

                    </div>

                    <!-- LOGIN BUTTON -->
                    <button type="submit" class="login-btn">
                        <span>LOGIN</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </button>

                </form>

                <!-- SIGN UP LINK -->
                <p class="signup-text">
                    DON'T HAVE AN ACCOUNT YET?
                    <a href="signup.php">SIGN UP</a>
                </p>

            </div>

        </section>

        <!-- RIGHT SIDE: HONEY ILLUSTRATION -->
        <section class="login-illustration">

            <div class="illustration-background"></div>

            <div class="honey-glow" aria-hidden="true"></div>

            <img src="assets/images/honey.png" alt="StudyHive Honey Illustration" class="honey-illustration">

        </section>

    </main>

    <script>
    (function () {
        var toggle = document.getElementById("togglePassword");
        var input = document.getElementById("password");
        if (!toggle || !input) return;
        toggle.addEventListener("click", function () {
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            toggle.setAttribute("aria-pressed", String(show));
            toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
            toggle.classList.toggle("is-visible", show);
            input.focus();
        });
    })();
    </script>

</body>

</html>