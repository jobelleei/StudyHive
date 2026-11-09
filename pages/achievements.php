<?php
session_start();

require_once __DIR__ . "/../config/database.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$first_name = $_SESSION["first_name"] ?? "Bee";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Date filter for the progress summary and subject chart.
$period = $_GET["period"] ?? "month";
if (!in_array($period, ["today", "week", "month", "all"], true)) {
    $period = "month";
}
$dateCondition = "";
switch ($period) {
    case "today": $dateCondition = "AND DATE(ps.completed_at) = CURDATE()"; break;
    case "week": $dateCondition = "AND YEARWEEK(ps.completed_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case "month": $dateCondition = "AND YEAR(ps.completed_at) = YEAR(CURDATE()) AND MONTH(ps.completed_at) = MONTH(CURDATE())"; break;
    case "all": $dateCondition = ""; break;
}

// Every user's folder appears, with time calculated from completed sessions only.
$stmt = $pdo->prepare(
    "SELECT f.folder_id, f.folder_name, f.folder_color,
            COALESCE(SUM(ps.focus_minutes), 0) AS total_minutes,
            COUNT(ps.session_id) AS total_sessions
     FROM folders f
     LEFT JOIN pomodoro_sessions ps
       ON ps.folder_id = f.folder_id AND ps.user_id = f.user_id $dateCondition
     WHERE f.user_id = ?
     GROUP BY f.folder_id, f.folder_name, f.folder_color
     ORDER BY total_minutes DESC, f.folder_name ASC"
);
$stmt->execute([$user_id]);
$folder_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's Achievabee: group today's completed focus sessions by NOTE TITLE.
$stmt = $pdo->prepare(
    "SELECT
        ps.note_id,
        COALESCE(NULLIF(n.title, ''), 'Study session (no note linked)') AS note_title,
        SUM(ps.focus_minutes) AS total_focus_minutes,
        COUNT(ps.session_id) AS session_count,
        MAX(ps.completed_at) AS last_completed_at
     FROM pomodoro_sessions ps
     LEFT JOIN notes n
        ON n.note_id = ps.note_id
        AND n.user_id = ps.user_id
     WHERE ps.user_id = ?
       AND DATE(ps.completed_at) = CURDATE()
     GROUP BY ps.note_id, n.title
     ORDER BY last_completed_at DESC"
);
$stmt->execute([$user_id]);
$todays_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats follow the selected period.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_sessions,
            COALESCE(SUM(ps.focus_minutes), 0) AS total_focus_minutes
     FROM pomodoro_sessions ps
     WHERE ps.user_id = ? $dateCondition"
);
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_sessions = (int)($stats["total_sessions"] ?? 0);
$total_minutes = (int)($stats["total_focus_minutes"] ?? 0);
$total_hours = intdiv($total_minutes, 60);
$remaining_minutes = $total_minutes % 60;
$subjects_studied = count(array_filter($folder_stats, fn($f) => (int)$f["total_sessions"] > 0));
$max_minutes = 1;
foreach ($folder_stats as $folder) $max_minutes = max($max_minutes, (int)$folder["total_minutes"]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements | StudyHive</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <script>
    (function() {
        try {
            let theme = localStorage.getItem("studyhiveTheme");

            if (theme) {
                try {
                    theme = JSON.parse(theme);
                } catch (error) {
                    // The Settings page stores the theme as plain text.
                }
            }

            if (theme !== "dark" && theme !== "light") {
                theme = "light";
            }

            document.documentElement.setAttribute("data-theme", theme);
        } catch (error) {
            document.documentElement.setAttribute("data-theme", "light");
        }
    })();
    </script>

    <link rel="stylesheet" href="../assets/css/achievements.css?v=4">
    <link rel="stylesheet" href="../assets/css/settings-modal.css?v=3">
</head>

<body>
    <header class="navbar">
        <a href="notes.php" class="brand"><img src="../assets/images/logo.png" alt="StudyHive Logo"></a>
        <nav class="nav-links">
            <a href="notes.php">NOTES</a>
            <a href="calendar.php">CALENDAR</a>
            <a href="achievements.php" class="active">ACHIEVEMENTS</a>
            <a href="feed.php">FEED</a>
            <a href="#" data-open-settings>SETTINGS</a>
        </nav>
        <div class="nav-user"><span>Hi, <span data-user-name><?= e($first_name) ?></span>!</span><a
                href="../logout.php">LOG OUT</a></div>
    </header>

    <main class="achievement-container">
        <h1 class="page-title"><span>BEE</span> PRODUCTIVITY PATROL</h1>

        <section class="progress-section">
            <div class="section-heading-row">
                <div>
                    <h2>PROGRESS</h2>
                    <p>Focus time from your completed Pomodoro sessions</p>
                </div>
                <form method="GET" class="period-filter">
                    <select name="period" onchange="this.form.submit()" aria-label="Select progress period">
                        <option value="today" <?= $period === "today" ? "selected" : "" ?>>Today</option>
                        <option value="week" <?= $period === "week" ? "selected" : "" ?>>This week</option>
                        <option value="month" <?= $period === "month" ? "selected" : "" ?>>This month</option>
                        <option value="all" <?= $period === "all" ? "selected" : "" ?>>All time</option>
                    </select>
                </form>
            </div>

            <div class="stats-grid">
                <article class="stat-card">
                    <span>TOTAL FOCUS TIME</span>
                    <strong><?= $total_hours ?>h <?= $remaining_minutes ?>m</strong>
                </article>
                <article class="stat-card">
                    <span>COMPLETED SESSIONS</span>
                    <strong><?= $total_sessions ?></strong>
                </article>
                <article class="stat-card">
                    <span>SUBJECTS STUDIED</span>
                    <strong><?= $subjects_studied ?></strong>
                </article>
            </div>
        </section>

        <section class="chart-section">
            <div class="section-heading-row">
                <div>
                    <h2>TOTAL TIME</h2>
                    <p>Focus minutes by subject / folder</p>
                </div>
                <span class="chart-unit">MINUTES</span>
            </div>

            <?php if (!$folder_stats): ?>
            <div class="empty-state">
                <h3>No folders yet</h3>
                <p>Create a folder and complete a Pomodoro session to see your study time here.</p>
            </div>
            <?php else: ?>
            <div class="chart-list" role="group" aria-label="Focus time by subject or folder">
                <?php foreach ($folder_stats as $i => $folder):
                    $minutes = (int)$folder["total_minutes"];
                    $height = $minutes > 0 ? max(3, ($minutes / $max_minutes) * 100) : 0;
                    $palette = ["#ffc928", "#ff999f", "#85f77e", "#ffad4d", "#60a5fa", "#d879f5", "#8d9cf5", "#55c7b5"];
                    $color = $folder["folder_color"] ?: $palette[$i % count($palette)];
                ?>
                <div class="chart-row" title="<?= e($folder["folder_name"]) ?>: <?= $minutes ?> minutes">
                    <div class="chart-value"><?= $minutes ?> min</div>
                    <div class="chart-track">
                        <div class="chart-bar" style="height:<?= $height ?>%;background:<?= e($color) ?>"></div>
                    </div>
                    <div class="chart-folder-name"><?= e($folder["folder_name"]) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="achievabee-section">
            <div class="section-heading-row">
                <div>
                    <h2>TODAY'S ACHIEVABEE</h2>
                    <p>Your completed focus sessions today</p>
                </div>
                <span class="today-date"><?= date("F d, Y") ?></span>
            </div>

            <?php if (!$todays_sessions): ?>
            <div class="empty-state">
                <h3>No Achievabee yet today!</h3>
                <p>Complete a focus session to see your achievement here.</p>
                <a class="start-button" href="notes.php">START STUDYING</a>
            </div>
            <?php else: ?>
            <div class="achievement-grid">
                <?php foreach ($todays_sessions as $i => $session):
                    $cardColor = $i % 2 === 0 ? "gold" : "pink";
                    $focusTotal = (int)$session["total_focus_minutes"];
                    $focusHours = intdiv($focusTotal, 60);
                    $focusRemainder = $focusTotal % 60;
                    $durationLabel = $focusHours > 0
                        ? $focusHours . "h " . $focusRemainder . "m"
                        : $focusTotal . " mins";
                    $sessionCount = (int)$session["session_count"];
                ?>
                <article class="achievement-card <?= $cardColor ?>">
                    <div class="trophy-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" focusable="false">
                            <path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z" />
                            <path d="M7 6H4v2a4 4 0 0 0 4 4M17 6h3v2a4 4 0 0 1-4 4" />
                            <path d="M9 4V2h6v2" />
                        </svg>
                    </div>
                    <div class="achievement-info">
                        <h3><?= e($session["note_title"]) ?></h3>
                        <div class="achievement-meta">
                            <span>◷ <?= e($durationLabel) ?> total focus time</span>
                            <span><?= $sessionCount ?> <?= $sessionCount === 1 ? "session" : "sessions" ?></span>
                            <span>▦ Last: <?= date("h:i A", strtotime($session["last_completed_at"])) ?></span>
                        </div>
                    </div>
                    <span class="completed-label">COMPLETED</span>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . "/includes/settings_modal.php"; ?>
    <script src="../assets/js/settings.js?v=3"></script>
</body>

</html>