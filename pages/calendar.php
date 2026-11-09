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

$user_id = (int) $_SESSION["user_id"];
$first_name = $_SESSION["first_name"] ?? "Bee";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function redirectCalendar($month, $date = null, $message = null) {
    $url = "calendar.php?month=" . urlencode($month);
    if ($date) {
        $url .= "&date=" . urlencode($date);
    }
    if ($message) {
        $url .= "&message=" . urlencode($message);
    }
    header("Location: " . $url);
    exit;
}

$today = new DateTimeImmutable("today");
$monthParam = $_GET["month"] ?? $today->format("Y-m");

if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = $today->format("Y-m");
}

$monthDate = DateTimeImmutable::createFromFormat("!Y-m-d", $monthParam . "-01");
if (!$monthDate) {
    $monthDate = $today->modify("first day of this month");
    $monthParam = $monthDate->format("Y-m");
}

$monthStart = $monthDate->modify("first day of this month");
$monthEnd = $monthDate->modify("last day of this month");
$calendarStart = $monthStart->modify("-" . ((int) $monthStart->format("w")) . " days");
$calendarEnd = $monthEnd->modify("+" . (6 - (int) $monthEnd->format("w")) . " days");

$selectedDate = $_GET["date"] ?? $today->format("Y-m-d");
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $today->format("Y-m-d");
}
$selectedDateObj = DateTimeImmutable::createFromFormat("!Y-m-d", $selectedDate);
if (!$selectedDateObj || $selectedDateObj->format("Y-m-d") !== $selectedDate) {
    $selectedDate = $today->format("Y-m-d");
    $selectedDateObj = $today;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        exit("Invalid request. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";
    $returnMonth = $_POST["return_month"] ?? $monthParam;
    $returnDate = $_POST["return_date"] ?? $selectedDate;

    if (!preg_match('/^\d{4}-\d{2}$/', $returnMonth)) {
        $returnMonth = $monthParam;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDate)) {
        $returnDate = $selectedDate;
    }

    try {
        if ($action === "save_event") {
            $eventId = (int) ($_POST["event_id"] ?? 0);
            $title = trim($_POST["title"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $eventDate = $_POST["event_date"] ?? "";
            $startTime = trim($_POST["start_time"] ?? "");
            $endTime = trim($_POST["end_time"] ?? "");
            $eventType = $_POST["event_type"] ?? "event";

            if (!in_array($eventType, ["event", "task"], true)) {
                $eventType = "event";
            }

            $dateObject = DateTimeImmutable::createFromFormat("!Y-m-d", $eventDate);
            $validDate = $dateObject && $dateObject->format("Y-m-d") === $eventDate;
            $validTime = static function ($value) {
                return $value === "" || (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
            };

            if ($title === "") {
                $error = "Please enter a title.";
            } elseif (mb_strlen($title) > 150) {
                $error = "Title must not exceed 150 characters.";
            } elseif (!$validDate) {
                $error = "Please choose a valid date.";
            } elseif (!$validTime($startTime) || !$validTime($endTime)) {
                $error = "Please enter a valid time.";
            } elseif ($startTime !== "" && $endTime !== "" && $endTime <= $startTime) {
                $error = "End time must be later than start time.";
            } else {
                $startTime = $startTime !== "" ? $startTime : null;
                $endTime = $endTime !== "" ? $endTime : null;

                if ($eventId > 0) {
                    $stmt = $pdo->prepare(
                        "UPDATE events
                         SET title = ?, description = ?, event_date = ?,
                             start_time = ?, end_time = ?, event_type = ?
                         WHERE event_id = ? AND user_id = ?"
                    );
                    $stmt->execute([
                        $title, $description, $eventDate,
                        $startTime, $endTime, $eventType,
                        $eventId, $user_id
                    ]);
                    redirectCalendar(substr($eventDate, 0, 7), $eventDate, "updated");
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO events
                         (user_id, title, description, event_date, start_time, end_time, event_type, is_completed)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0)"
                    );
                    $stmt->execute([
                        $user_id, $title, $description, $eventDate,
                        $startTime, $endTime, $eventType
                    ]);
                    redirectCalendar(substr($eventDate, 0, 7), $eventDate, "created");
                }
            }
        } elseif ($action === "delete_event") {
            $eventId = (int) ($_POST["event_id"] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $user_id]);
            redirectCalendar($returnMonth, $returnDate, "deleted");
        } elseif ($action === "toggle_task") {
            $eventId = (int) ($_POST["event_id"] ?? 0);
            $completed = (int) ($_POST["is_completed"] ?? 0) === 1 ? 1 : 0;
            $stmt = $pdo->prepare(
                "UPDATE events
                 SET is_completed = ?
                 WHERE event_id = ? AND user_id = ? AND event_type = 'task'"
            );
            $stmt->execute([$completed, $eventId, $user_id]);
            redirectCalendar($returnMonth, $returnDate);
        }
    } catch (PDOException $ex) {
        error_log("StudyHive Calendar Error: " . $ex->getMessage());
        $error = "Something went wrong. Please check that the calendar database update has been run.";
    }
}

if (isset($_GET["message"])) {
    $messages = [
        "created" => "Event or task added successfully!",
        "updated" => "Event or task updated successfully!",
        "deleted" => "Event or task deleted successfully!"
    ];
    $success = $messages[$_GET["message"]] ?? "";
}

try {
    $stmt = $pdo->prepare(
        "SELECT event_id, title, description, event_date, start_time, end_time,
                event_type, is_completed
         FROM events
         WHERE user_id = ? AND event_date BETWEEN ? AND ?
         ORDER BY (start_time IS NULL), start_time ASC, event_id ASC"
    );
    $stmt->execute([
        $user_id,
        $calendarStart->format("Y-m-d"),
        $calendarEnd->format("Y-m-d")
    ]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log("StudyHive Calendar Load Error: " . $ex->getMessage());
    $events = [];
    $error = "Calendar database setup is incomplete. Run the SQL update provided with these files.";
}

$eventsByDate = [];
foreach ($events as $event) {
    $eventsByDate[$event["event_date"]][] = $event;
}

$selectedEvents = $eventsByDate[$selectedDate] ?? [];
$selectedLabel = $selectedDateObj->format("j | D");
$previousMonth = $monthStart->modify("-1 month")->format("Y-m");
$nextMonth = $monthStart->modify("+1 month")->format("Y-m");
$calendarDays = [];
$dayCursor = $calendarStart;
while ($dayCursor <= $calendarEnd) {
    $calendarDays[] = $dayCursor;
    $dayCursor = $dayCursor->modify("+1 day");
}

$eventsJson = json_encode(
    $eventsByDate,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | StudyHive</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <script>
    (function() {
        try {
            let theme = localStorage.getItem("studyhiveTheme");

            if (theme) {
                try {
                    theme = JSON.parse(theme);
                } catch (error) {
                    // Supports plain-text theme values.
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

    <link rel="stylesheet" href="../assets/css/calendar.css?v=calendar-dark-layout-2">
    <link rel="stylesheet" href="../assets/css/settings-modal.css?v=3">
</head>

<body>
    <header class="navbar">
        <a href="notes.php" class="brand">
            <img src="../assets/images/logo.png" alt="StudyHive Logo">
        </a>

        <nav class="nav-links">
            <a href="notes.php">NOTES</a>
            <a href="calendar.php" class="active">CALENDAR</a>
            <a href="achievements.php">ACHIEVEMENTS</a>
            <a href="feed.php">FEED</a>
            <a href="#" data-open-settings>SETTINGS</a>
        </nav>

        <div class="nav-user">
            <span>Hi, <span data-user-name><?= e($first_name) ?></span>!</span>
            <a href="../logout.php">LOG OUT</a>
        </div>
    </header>

    <main class="calendar-page">
        <?php if ($error): ?>
        <div class="notice notice-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="notice notice-success"><?= e($success) ?></div>
        <?php endif; ?>

        <section class="calendar-layout">
            <div class="calendar-main">
                <div class="calendar-heading">
                    <div class="month-title-wrap">
                        <span class="month-accent"></span>
                        <h1 id="monthTitle"><?= e(strtoupper($monthDate->format("F Y"))) ?></h1>
                        <button type="button" class="month-picker-button" id="monthPickerButton"
                            aria-label="Choose month">
                            <span class="chevron-down"></span>
                        </button>
                        <input type="month" id="monthPicker" value="<?= e($monthParam) ?>" aria-label="Choose month">
                    </div>
                    <a class="today-button"
                        href="calendar.php?month=<?= e($today->format("Y-m")) ?>&date=<?= e($today->format("Y-m-d")) ?>">TODAY</a>
                </div>

                <div class="calendar-controls">
                    <a href="calendar.php?month=<?= e($previousMonth) ?>&date=<?= e($monthStart->modify("-1 month")->format("Y-m-d")) ?>"
                        aria-label="Previous month" class="month-arrow">&lsaquo;</a>
                    <span><?= e($monthDate->format("F Y")) ?></span>
                    <a href="calendar.php?month=<?= e($nextMonth) ?>&date=<?= e($monthStart->modify("+1 month")->format("Y-m-d")) ?>"
                        aria-label="Next month" class="month-arrow">&rsaquo;</a>
                </div>

                <div class="calendar-weekdays">
                    <?php foreach (["S", "M", "T", "W", "T", "F", "S"] as $weekday): ?>
                    <div><?= $weekday ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="calendar-grid" id="calendarGrid">
                    <?php foreach ($calendarDays as $day):
                    $dateKey = $day->format("Y-m-d");
                    $dayEvents = $eventsByDate[$dateKey] ?? [];
                    $hasItems = count($dayEvents) > 0;
                    $classes = ["calendar-day"];
                    if ($day->format("m") !== $monthDate->format("m")) $classes[] = "other-month";
                    if ($dateKey === $today->format("Y-m-d")) $classes[] = "is-today";
                    if ($dateKey === $selectedDate) $classes[] = "is-selected";
                ?>
                    <button type="button" class="<?= e(implode(" ", $classes)) ?>" data-date="<?= e($dateKey) ?>"
                        aria-label="<?= e($day->format("F j, Y")) ?><?= $hasItems ? ", has " . count($dayEvents) . " item(s)" : "" ?>">
                        <span class="day-number"><?= e($day->format("j")) ?></span>
                        <?php if ($hasItems): ?>
                        <span class="day-markers">
                            <?php
                                $hasEvent = false;
                                $hasTask = false;
                                foreach ($dayEvents as $item) {
                                    if (($item["event_type"] ?? "event") === "task") $hasTask = true;
                                    else $hasEvent = true;
                                }
                                ?>
                            <?php if ($hasEvent): ?><i class="day-marker event-marker"></i><?php endif; ?>
                            <?php if ($hasTask): ?><i class="day-marker task-marker"></i><?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="calendar-legend">
                    <span><i class="legend-dot event-dot"></i> Events</span>
                    <span><i class="legend-dot task-dot"></i> Tasks</span>
                </div>
            </div>

            <aside class="day-panel">
                <div class="day-panel-header">
                    <div>
                        <h2 id="selectedDateLabel"><?= e($selectedLabel) ?></h2>
                        <p><?= e($selectedDateObj->format("F Y")) ?></p>
                    </div>
                    <button type="button" class="add-event-button" id="openAddModal"
                        aria-label="Add event or task">+</button>
                </div>

                <div class="day-panel-content" id="dayPanelContent">
                    <?php if (!$selectedEvents): ?>
                    <p class="empty-day">No events or tasks for this day!</p>
                    <?php else: ?>
                    <?php foreach ($selectedEvents as $item): ?>
                    <article
                        class="agenda-item <?= ($item["event_type"] ?? "event") === "task" ? "agenda-task" : "agenda-event" ?> <?= !empty($item["is_completed"]) ? "is-completed" : "" ?>">
                        <?php if (($item["event_type"] ?? "event") === "task"): ?>
                        <form method="POST" class="task-check-form">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                            <input type="hidden" name="action" value="toggle_task">
                            <input type="hidden" name="event_id" value="<?= (int) $item["event_id"] ?>">
                            <input type="hidden" name="is_completed"
                                value="<?= empty($item["is_completed"]) ? 1 : 0 ?>">
                            <input type="hidden" name="return_month" value="<?= e($monthParam) ?>">
                            <input type="hidden" name="return_date" value="<?= e($selectedDate) ?>">
                            <button type="submit"
                                class="task-checkbox <?= !empty($item["is_completed"]) ? "checked" : "" ?>"
                                aria-label="Toggle task completion">
                                <?= !empty($item["is_completed"]) ? "✓" : "" ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="agenda-type-dot"></span>
                        <?php endif; ?>

                        <div class="agenda-copy">
                            <span
                                class="agenda-type"><?= ($item["event_type"] ?? "event") === "task" ? "TASK" : "EVENT" ?></span>
                            <h3><?= e($item["title"]) ?></h3>
                            <?php if (!empty($item["start_time"])): ?>
                            <p class="agenda-time">
                                <?= e(date("g:i A", strtotime($item["start_time"]))) ?>
                                <?php if (!empty($item["end_time"])): ?>
                                – <?= e(date("g:i A", strtotime($item["end_time"]))) ?>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($item["description"])): ?>
                            <p class="agenda-description"><?= nl2br(e($item["description"])) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="agenda-actions">
                            <button type="button" class="icon-action edit-item-button"
                                data-id="<?= (int) $item["event_id"] ?>" data-title="<?= e($item["title"]) ?>"
                                data-description="<?= e($item["description"]) ?>"
                                data-date="<?= e($item["event_date"]) ?>"
                                data-start="<?= e($item["start_time"] ? substr($item["start_time"], 0, 5) : "") ?>"
                                data-end="<?= e($item["end_time"] ? substr($item["end_time"], 0, 5) : "") ?>"
                                data-type="<?= e($item["event_type"] ?? "event") ?>" aria-label="Edit item">✎</button>

                            <form method="POST" onsubmit="return confirm('Delete this item?');">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?= (int) $item["event_id"] ?>">
                                <input type="hidden" name="return_month" value="<?= e($monthParam) ?>">
                                <input type="hidden" name="return_date" value="<?= e($selectedDate) ?>">
                                <button type="submit" class="icon-action delete-item-button"
                                    aria-label="Delete item">×</button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" class="panel-add-button" id="openAddModalBottom">+ ADD EVENT OR TASK</button>
            </aside>
        </section>
    </main>

    <div class="modal-backdrop" id="eventModal" aria-hidden="true">
        <div class="event-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <div>
                    <span class="modal-eyebrow">STUDYHIVE CALENDAR</span>
                    <h2 id="modalTitle">Add to your day</h2>
                </div>
                <button type="button" class="modal-close" id="closeModal" aria-label="Close">×</button>
            </div>

            <form method="POST" id="eventForm">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="action" value="save_event">
                <input type="hidden" name="event_id" id="eventId" value="0">
                <input type="hidden" name="return_month" value="<?= e($monthParam) ?>">
                <input type="hidden" name="return_date" value="<?= e($selectedDate) ?>">

                <label for="eventType">TYPE</label>
                <select name="event_type" id="eventType">
                    <option value="event">Event</option>
                    <option value="task">Task</option>
                </select>

                <label for="eventTitle">TITLE</label>
                <input type="text" name="title" id="eventTitle" maxlength="150" placeholder="What do you need to do?"
                    required>

                <label for="eventDescription">DESCRIPTION (OPTIONAL)</label>
                <textarea name="description" id="eventDescription" rows="3"
                    placeholder="Add details or notes..."></textarea>

                <label for="eventDate">DATE</label>
                <input type="date" name="event_date" id="eventDate" value="<?= e($selectedDate) ?>" required>

                <div class="time-fields">
                    <div>
                        <label for="startTime">START TIME (OPTIONAL)</label>
                        <input type="time" name="start_time" id="startTime">
                    </div>
                    <div>
                        <label for="endTime">END TIME (OPTIONAL)</label>
                        <input type="time" name="end_time" id="endTime">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="cancel-button" id="cancelModal">CANCEL</button>
                    <button type="submit" class="save-button" id="saveButton">SAVE</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.studyHiveCalendar = {
        eventsByDate: <?= $eventsJson ?: "{}" ?>,
        selectedDate: <?= json_encode($selectedDate) ?>,
        currentMonth: <?= json_encode($monthParam) ?>
    };
    </script>
    <script src="../assets/js/calendar.js"></script>

    <?php include __DIR__ . "/includes/settings_modal.php"; ?>
    <script src="../assets/js/settings.js?v=3"></script>
</body>

</html>