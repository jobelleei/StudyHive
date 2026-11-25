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
    <link rel="stylesheet" href="assets/css/login.css?v=signup1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Coiny&display=swap">
    <link rel="stylesheet" href="assets/css/fonts.css?v=4">
</head>

<body>

    <main class="login-page signup-page">

        <!-- LEFT SIDE: SIGN UP FORM -->
        <section class="login-section">

            <div class="login-container">

                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="StudyHive Logo">
                </div>

                <h1>SIGN UP</h1>

                <p class="login-subtitle">Join the hive! Create your account to start studying.</p>

                <?php if ($error !== ""): ?>
                <div class="login-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
                <?php endif; ?>

                <form action="signup.php" method="POST">

                    <div class="name-row">

                        <div class="form-group">
                            <label for="first_name">FIRST NAME</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21a8 8 0 0 1 16 0" />
                                </svg>
                                <input type="text" id="first_name" name="first_name" placeholder="First name"
                                    maxlength="100" value="<?= htmlspecialchars($first_name, ENT_QUOTES, "UTF-8") ?>"
                                    autocomplete="given-name" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="last_name">LAST NAME</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21a8 8 0 0 1 16 0" />
                                </svg>
                                <input type="text" id="last_name" name="last_name" placeholder="Last name"
                                    maxlength="100" value="<?= htmlspecialchars($last_name, ENT_QUOTES, "UTF-8") ?>"
                                    autocomplete="family-name" required>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="email">EMAIL ADDRESS</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" />
                            </svg>
                            <input type="email" id="email" name="email" placeholder="bayBee@gmail.com" maxlength="150"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">PASSWORD</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="4" y="11" width="16" height="10" rx="2" />
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                            </svg>
                            <input type="password" id="password" name="password" placeholder="At least 6 characters"
                                minlength="6" autocomplete="new-password" required>
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

                    <button type="submit" class="login-btn">
                        <span>SIGN UP</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </button>

                </form>

                <p class="signup-text">
                    ALREADY HAVE AN ACCOUNT?
                    <a href="login.php">LOG IN</a>
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