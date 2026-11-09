<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$user_id = (int) $_SESSION["user_id"];
$first_name = $_SESSION["first_name"] ?? "Bee";
$error = "";
$success = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Update the logged-in user's account name.
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "update_account_name") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page.");
    }

    $new_name = trim($_POST["account_name"] ?? "");

    if ($new_name === "") {
        $error = "Account name cannot be empty.";
    } elseif (mb_strlen($new_name) > 100) {
        $error = "Account name must not exceed 100 characters.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "UPDATE users SET first_name = ? WHERE user_id = ?"
            );
            $stmt->execute([$new_name, $user_id]);

            $_SESSION["first_name"] = $new_name;

            header("Location: settings.php?account_updated=1");
            exit;
        } catch (PDOException $ex) {
            error_log("StudyHive account name update failed: " . $ex->getMessage());
            $error = "Unable to update your account name. Please check your users table.";
        }
    }
}

if (isset($_GET["account_updated"]) && $_GET["account_updated"] === "1") {
    $success = "Account name updated successfully!";
}

$first_name = $_SESSION["first_name"] ?? $first_name;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings | StudyHive</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/settings.css">
</head>

<body>

    <!-- NAVIGATION -->

    <header class="navbar">

        <a href="notes.php" class="brand">
            <img src="../assets/images/logo.png" alt="StudyHive Logo">
        </a>

        <nav class="nav-links">

            <a href="notes.php">NOTES</a>
            <a href="calendar.php">CALENDAR</a>
            <a href="achievements.php">ACHIEVEMENTS</a>
            <a href="feed.php">FEED</a>
            <a href="settings.php" class="active">SETTINGS</a>

        </nav>

        <div class="nav-user">
            <span>Hi, <?= e($first_name) ?>!</span>
            <a href="../logout.php">LOG OUT</a>
        </div>

    </header>


    <!-- PAGE BACKGROUND -->

    <main class="settings-background">

        <section class="background-content">

            <span class="background-eyebrow">STUDYHIVE PERSONAL SPACE</span>

            <h1>Make your study space work for you.</h1>

            <p>
                Manage your account, customize your study preferences,
                and personalize your StudyHive experience.
            </p>

            <div class="background-decoration">
                <div class="decoration-circle circle-one"></div>
                <div class="decoration-circle circle-two"></div>
                <div class="decoration-circle circle-three"></div>
            </div>

        </section>

    </main>


    <!-- SETTINGS MODAL -->

    <div class="settings-overlay" id="settingsOverlay">

        <section class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">

            <div class="settings-header">

                <div>
                    <span class="settings-eyebrow">STUDYHIVE</span>
                    <h2 id="settingsTitle">Settings</h2>
                    <p>Manage your preferences and account.</p>
                </div>

                <button type="button" class="close-settings" id="closeSettings" aria-label="Close settings">
                    &times;
                </button>

            </div>


            <div class="settings-layout">

                <!-- SETTINGS SIDEBAR -->

                <aside class="settings-sidebar">

                    <button type="button" class="settings-tab active" data-section="account">

                        <span class="tab-icon">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21a8 8 0 0 1 16 0"></path>
                            </svg>
                        </span>

                        <span>Account</span>

                    </button>


                    <button type="button" class="settings-tab" data-section="study">

                        <span class="tab-icon">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 7v5l3 2"></path>
                            </svg>
                        </span>

                        <span>Study Preferences</span>

                    </button>


                    <button type="button" class="settings-tab" data-section="appearance">

                        <span class="tab-icon">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 3a9 9 0 0 0 0 18Z"></path>
                            </svg>
                        </span>

                        <span>Appearance</span>

                    </button>


                    <button type="button" class="settings-tab" data-section="notifications">

                        <span class="tab-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                <path d="M10 21h4"></path>
                            </svg>
                        </span>

                        <span>Notifications</span>

                    </button>


                    <div class="sidebar-divider"></div>

                    <a href="../logout.php" class="logout-tab">

                        <svg viewBox="0 0 24 24">
                            <path d="M10 17l5-5-5-5"></path>
                            <path d="M15 12H3"></path>
                            <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path>
                        </svg>

                        Log Out

                    </a>

                </aside>


                <!-- SETTINGS CONTENT -->

                <div class="settings-content">

                    <?php if ($error): ?>
                    <div class="settings-alert error-alert"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="settings-alert success-alert"><?= e($success) ?></div>
                    <?php endif; ?>


                    <!-- ACCOUNT -->

                    <section class="settings-panel active" id="section-account">

                        <div class="panel-heading">
                            <h3>Account Information</h3>
                            <p>Manage the name displayed on your StudyHive account.</p>
                        </div>


                        <div class="profile-card">

                            <div class="profile-avatar">
                                <?= strtoupper(substr($first_name, 0, 1)) ?>
                            </div>

                            <div>
                                <h4><?= e($first_name) ?></h4>
                                <span>StudyHive Member</span>
                            </div>

                        </div>


                        <form method="POST" class="account-form">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                            <input type="hidden" name="action" value="update_account_name">

                            <div class="account-edit-field">
                                <label for="accountName">Account Name</label>
                                <input type="text" id="accountName" name="account_name" value="<?= e($first_name) ?>"
                                    maxlength="100" autocomplete="name" required>
                            </div>

                            <div class="settings-actions">
                                <button type="submit" class="save-settings">
                                    SAVE ACCOUNT NAME
                                </button>
                            </div>
                        </form>

                    </section>


                    <!-- STUDY PREFERENCES -->

                    <section class="settings-panel" id="section-study">

                        <div class="panel-heading">
                            <h3>Study Preferences</h3>
                            <p>Customize your default Pomodoro timer.</p>
                        </div>


                        <div class="setting-item">

                            <div class="setting-description">
                                <h4>Focus Duration</h4>
                                <p>Default length of your focus sessions.</p>
                            </div>

                            <div class="duration-control">

                                <input type="number" id="defaultFocus" min="1" max="180" value="25">

                                <span>minutes</span>

                            </div>

                        </div>


                        <div class="setting-item">

                            <div class="setting-description">
                                <h4>Break Duration</h4>
                                <p>Default length of your break sessions.</p>
                            </div>

                            <div class="duration-control">

                                <input type="number" id="defaultBreak" min="1" max="60" value="5">

                                <span>minutes</span>

                            </div>

                        </div>


                        <div class="preset-section">

                            <h4>Quick Focus Presets</h4>

                            <div class="preset-buttons">

                                <button type="button" data-focus="25">
                                    25 min
                                </button>

                                <button type="button" data-focus="30">
                                    30 min
                                </button>

                                <button type="button" data-focus="45">
                                    45 min
                                </button>

                                <button type="button" data-focus="50">
                                    50 min
                                </button>

                            </div>

                        </div>


                        <div class="settings-actions">

                            <button type="button" class="save-settings" id="saveStudySettings">
                                SAVE PREFERENCES
                            </button>

                        </div>

                    </section>


                    <!-- APPEARANCE -->

                    <section class="settings-panel" id="section-appearance">

                        <div class="panel-heading">
                            <h3>Appearance</h3>
                            <p>Personalize the look of StudyHive.</p>
                        </div>


                        <div class="setting-item">

                            <div class="setting-description">
                                <h4>Light Mode</h4>
                                <p>Use the classic bright StudyHive appearance.</p>
                            </div>

                            <label class="switch">

                                <input type="radio" name="theme" value="light" id="themeLight">

                                <span class="switch-track"></span>

                            </label>

                        </div>


                        <div class="setting-item">

                            <div class="setting-description">
                                <h4>Dark Mode</h4>
                                <p>Use a darker appearance for studying at night.</p>
                            </div>

                            <label class="switch">

                                <input type="radio" name="theme" value="dark" id="themeDark">

                                <span class="switch-track"></span>

                            </label>

                        </div>


                        <div class="theme-preview" id="themePreview">

                            <div class="preview-top">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>

                            <div class="preview-content">
                                <div class="preview-line"></div>
                                <div class="preview-line short"></div>
                                <div class="preview-card"></div>
                            </div>

                            <p id="themePreviewLabel">Light Mode Preview</p>

                        </div>


                        <div class="settings-actions">

                            <button type="button" class="save-settings" id="saveThemeSettings">
                                SAVE APPEARANCE
                            </button>

                        </div>

                    </section>


                    <!-- NOTIFICATIONS -->

                    <section class="settings-panel" id="section-notifications">

                        <div class="panel-heading">
                            <h3>Notifications</h3>
                            <p>Manage your Pomodoro alerts and timer sound.</p>
                        </div>


                        <div class="setting-item">

                            <div class="setting-description">
                                <h4>Pomodoro Notifications</h4>
                                <p>
                                    Get notified when your focus or break
                                    session is completed.
                                </p>
                            </div>

                            <label class="switch">

                                <input type="checkbox" id="pomodoroNotifications">

                                <span class="switch-track"></span>

                            </label>

                        </div>


                        <div class="setting-item">

                            <div class="setting-description">
                                <h4>Notification Sound</h4>
                                <p>
                                    Play a sound when your Pomodoro timer ends.
                                </p>
                            </div>

                            <label class="switch">

                                <input type="checkbox" id="notificationSound">

                                <span class="switch-track"></span>

                            </label>

                        </div>


                        <div class="settings-actions">

                            <button type="button" class="save-settings" id="saveNotificationSettings">
                                SAVE NOTIFICATIONS
                            </button>

                        </div>

                    </section>


                    <div class="settings-message" id="settingsMessage" role="status" aria-live="polite"></div>

                </div>

            </div>


            <div class="settings-footer">

                <span>STUDYHIVE SETTINGS</span>

                <button type="button" id="settingsDone">
                    DONE
                </button>

            </div>

        </section>

    </div>


    <script src="../assets/js/settings.js"></script>

</body>

</html>