<?php
session_start();

// Prevent protected pages from being stored in browser cache.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

date_default_timezone_set("Asia/Manila");

require_once __DIR__ . "/../config/database.php";

// Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}


$user_id = (int)$_SESSION["user_id"];
$first_name = $_SESSION["first_name"] ?? "Bee";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function cleanNoteHtml($html) {
    $allowed = "<p><br><div><b><strong><i><em><u><ul><ol><li><blockquote><h1><h2><h3><span>";
    $html = strip_tags((string)$html, $allowed);

    $html = preg_replace(
        '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i',
        '',
        $html
    );

    $html = preg_replace(
        '/\s+(href|src)\s*=\s*("[^"]*"|\'[^\']*\')/i',
        '',
        $html
    );

    $html = preg_replace(
        '/\s+style\s*=\s*("[^"]*(?:url|expression)[^"]*"|\'[^\']*(?:url|expression)[^"]*\')/i',
        '',
        $html
    );

    return $html;
}

function redirectTo($url) {
    header("Location: " . $url);
    exit;
}

$period = $_GET["period"] ?? "week";

if (!in_array($period, ["today", "week", "month"], true)) {
    $period = "week";
}

$error = "";
$success = "";


// =====================================================
// HANDLE FORM SUBMISSIONS
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        exit("Invalid request. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";

    try {

        // SAVE A COMPLETED POMODORO FOCUS SESSION
        if ($action === "save_pomodoro_session") {

            header("Content-Type: application/json; charset=utf-8");

            $focus_minutes = (int)($_POST["focus_minutes"] ?? 0);
            $break_minutes = (int)($_POST["break_minutes"] ?? 0);
            $folder_id = (int)($_POST["folder_id"] ?? 0);
            $note_id = (int)($_POST["note_id"] ?? 0);

            if (
                $focus_minutes < 1 || $focus_minutes > 180 ||
                $break_minutes < 1 || $break_minutes > 60 ||
                $folder_id < 1
            ) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Invalid session information."]);
                exit;
            }

            // Ensure the selected folder belongs to this user.
            $stmt = $pdo->prepare("SELECT folder_id FROM folders WHERE folder_id = ? AND user_id = ?");
            $stmt->execute([$folder_id, $user_id]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Invalid folder."]);
                exit;
            }

            // A note is optional, but if supplied it must belong to this folder and user.
            if ($note_id > 0) {
                $stmt = $pdo->prepare(
                    "SELECT note_id FROM notes WHERE note_id = ? AND folder_id = ? AND user_id = ?"
                );
                $stmt->execute([$note_id, $folder_id, $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(["success" => false, "message" => "Invalid note."]);
                    exit;
                }
            } else {
                $note_id = null;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO pomodoro_sessions
                    (user_id, folder_id, note_id, focus_minutes, break_minutes)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $folder_id, $note_id, $focus_minutes, $break_minutes]);

            echo json_encode(["success" => true, "message" => "Focus session recorded."]);
            exit;
        }

        // CREATE FOLDER
        if ($action === "create_folder") {

            $folder_name = trim($_POST["folder_name"] ?? "");
            $folder_color = $_POST["folder_color"] ?? "#5298E8";

            $allowed_colors = [
                "#5298E8",
                "#F15B5B",
                "#F6AD55",
                "#9B83D5",
                "#48B99A"
            ];

            if ($folder_name === "") {
                $error = "Please enter a folder name.";
            } elseif (mb_strlen($folder_name) > 100) {
                $error = "Folder name must not exceed 100 characters.";
            } else {

                if (!in_array($folder_color, $allowed_colors, true)) {
                    $folder_color = "#5298E8";
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO folders (user_id, folder_name, folder_color)
                     VALUES (?, ?, ?)"
                );

                $stmt->execute([
                    $user_id,
                    $folder_name,
                    $folder_color
                ]);

                redirectTo("notes.php?created=folder");
            }
        }

        // CREATE NOTE
        elseif ($action === "create_note") {

            $folder_id = (int)($_POST["folder_id"] ?? 0);
            $title = trim($_POST["title"] ?? "");
            $content = cleanNoteHtml($_POST["content"] ?? "");
            $note_color = "#FFF2CC";

            $check = $pdo->prepare(
                "SELECT folder_id
                 FROM folders
                 WHERE folder_id = ? AND user_id = ?"
            );

            $check->execute([$folder_id, $user_id]);

            if (!$check->fetch()) {
                $error = "Folder not found.";
            } elseif ($title === "") {
                $error = "Please enter a note title.";
            } elseif (mb_strlen($title) > 255) {
                $error = "Note title must not exceed 255 characters.";
            } else {

                $stmt = $pdo->prepare(
                    "INSERT INTO notes
                     (user_id, folder_id, title, content, note_color)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $user_id,
                    $folder_id,
                    $title,
                    $content,
                    $note_color
                ]);

                $new_note_id = (int)$pdo->lastInsertId();

                redirectTo("notes.php?edit=" . $new_note_id . "&created=note");
            }
        }

        // UPDATE NOTE
        elseif ($action === "update_note") {

            $note_id = (int)($_POST["note_id"] ?? 0);
            $folder_id = (int)($_POST["folder_id"] ?? 0);
            $title = trim($_POST["title"] ?? "");
            $content = cleanNoteHtml($_POST["content"] ?? "");

            if ($title === "") {
                $error = "Please enter a note title.";
            } elseif (mb_strlen($title) > 255) {
                $error = "Note title must not exceed 255 characters.";
            } else {

                $stmt = $pdo->prepare(
                    "UPDATE notes
                     SET title = ?, content = ?
                     WHERE note_id = ?
                       AND user_id = ?
                       AND folder_id = ?"
                );

                $stmt->execute([
                    $title,
                    $content,
                    $note_id,
                    $user_id,
                    $folder_id
                ]);

                redirectTo("notes.php?edit=" . $note_id . "&updated=1");
            }
        }

        // DELETE NOTE
        elseif ($action === "delete_note") {

            $note_id = (int)($_POST["note_id"] ?? 0);
            $folder_id = (int)($_POST["folder_id"] ?? 0);

            $stmt = $pdo->prepare(
                "DELETE FROM notes
                 WHERE note_id = ? AND user_id = ?"
            );

            $stmt->execute([$note_id, $user_id]);

            redirectTo("notes.php?folder=" . $folder_id . "&deleted=note");
        }

        // DELETE FOLDER
        elseif ($action === "delete_folder") {

            $folder_id = (int)($_POST["folder_id"] ?? 0);

            $stmt = $pdo->prepare(
                "DELETE FROM folders
                 WHERE folder_id = ? AND user_id = ?"
            );

            $stmt->execute([$folder_id, $user_id]);

            redirectTo("notes.php?deleted=folder");
        }

    } catch (PDOException $ex) {
        error_log("StudyHive Notes Error: " . $ex->getMessage());

        if ($action === "save_pomodoro_session") {
            http_response_code(500);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode([
                "success" => false,
                "message" => "Unable to save the focus session."
            ]);
            exit;
        }

        $error = "Something went wrong while saving. Please check your database structure.";
    }
}

// =====================================================
// SUCCESS MESSAGES
// =====================================================

if (isset($_GET["created"])) {
    $success = $_GET["created"] === "folder"
        ? "Folder created successfully!"
        : "Note created successfully!";
}

if (isset($_GET["updated"])) {
    $success = "Note updated successfully!";
}

if (isset($_GET["deleted"])) {
    $success = $_GET["deleted"] === "folder"
        ? "Folder deleted successfully!"
        : "Note deleted successfully!";
}

// =====================================================
// LOAD CURRENT NOTE AND FOLDER
// =====================================================

$edit_note = null;
$selected_folder = null;
$folder_notes = [];
$folder_id = null;

if (isset($_GET["edit"])) {

    $note_id = (int)$_GET["edit"];

    $stmt = $pdo->prepare(
        "SELECT n.*, f.folder_name, f.folder_color
         FROM notes n
         INNER JOIN folders f ON f.folder_id = n.folder_id
         WHERE n.note_id = ?
           AND n.user_id = ?
           AND f.user_id = ?"
    );

    $stmt->execute([$note_id, $user_id, $user_id]);
    $edit_note = $stmt->fetch();

    if ($edit_note) {
        $folder_id = (int)$edit_note["folder_id"];
    }
}

if ($folder_id === null && isset($_GET["folder"])) {
    $folder_id = (int)$_GET["folder"];
}

if ($folder_id === null && isset($_GET["create"])) {
    $folder_id = (int)$_GET["create"];
}

if ($folder_id !== null) {

    $stmt = $pdo->prepare(
        "SELECT *
         FROM folders
         WHERE folder_id = ? AND user_id = ?"
    );

    $stmt->execute([$folder_id, $user_id]);
    $selected_folder = $stmt->fetch();

    if ($selected_folder) {

        $stmt = $pdo->prepare(
            "SELECT note_id, folder_id, title, content, created_at, updated_at
             FROM notes
             WHERE folder_id = ? AND user_id = ?
             ORDER BY updated_at DESC, note_id DESC"
        );

        $stmt->execute([$folder_id, $user_id]);
        $folder_notes = $stmt->fetchAll();

    } else {
        $edit_note = null;
    }
}

$editor_mode = $selected_folder !== null
    && (isset($_GET["create"]) || $edit_note !== null);

$is_editing = $edit_note !== null;

$editor_title = $is_editing ? $edit_note["title"] : "";
$editor_content = $is_editing ? $edit_note["content"] : "";

// =====================================================
// DASHBOARD FILTERS
// =====================================================

switch ($period) {

    case "today":
        $folderDateCondition = "DATE(f.created_at) = CURDATE()";
        $noteDateCondition = "DATE(n.updated_at) = CURDATE()";
        break;

    case "month":
        $folderDateCondition = "
            YEAR(f.created_at) = YEAR(CURDATE())
            AND MONTH(f.created_at) = MONTH(CURDATE())
        ";

        $noteDateCondition = "
            YEAR(n.updated_at) = YEAR(CURDATE())
            AND MONTH(n.updated_at) = MONTH(CURDATE())
        ";
        break;

    default:
        $folderDateCondition = "YEARWEEK(f.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $noteDateCondition = "YEARWEEK(n.updated_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
}

// =====================================================
// DASHBOARD DATA
// =====================================================

$folders = [];
$recent_notes = [];

if (!$editor_mode && !$selected_folder) {

    $stmt = $pdo->prepare(
        "SELECT
            f.folder_id,
            f.folder_name,
            f.folder_color,
            f.created_at,
            COUNT(n.note_id) AS note_count
         FROM folders f
         LEFT JOIN notes n
            ON n.folder_id = f.folder_id
            AND n.user_id = f.user_id
         WHERE f.user_id = ?
           AND $folderDateCondition
         GROUP BY
            f.folder_id,
            f.folder_name,
            f.folder_color,
            f.created_at
         ORDER BY f.created_at DESC"
    );

    $stmt->execute([$user_id]);
    $folders = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT
            n.note_id,
            n.folder_id,
            n.title,
            n.content,
            n.created_at,
            n.updated_at,
            f.folder_name,
            f.folder_color
         FROM notes n
         INNER JOIN folders f ON f.folder_id = n.folder_id
         WHERE n.user_id = ?
           AND f.user_id = ?
           AND $noteDateCondition
         ORDER BY n.updated_at DESC
         LIMIT 8"
    );

    $stmt->execute([$user_id, $user_id]);
    $recent_notes = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Notes | StudyHive</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <script>
    (function() {
        try {
            let theme = localStorage.getItem("studyhiveTheme");

            if (theme) {
                try {
                    theme = JSON.parse(theme);
                } catch (error) {
                    // Supports older plain-text theme values.
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
    <link rel="stylesheet" href="../assets/css/notes.css?v=achievements1">
    <link rel="stylesheet" href="../assets/css/settings-modal.css?v=3">
</head>

<body>

    <header class="navbar">

        <a href="notes.php" class="brand">
            <img src="../assets/images/logo.png" alt="StudyHive Logo">
        </a>

        <nav class="nav-links">
            <a href="notes.php" class="active">NOTES</a>
            <a href="calendar.php">CALENDAR</a>
            <a href="achievements.php">ACHIEVEMENTS</a>
            <a href="feed.php">FEED</a>
            <a href="#" data-open-settings>SETTINGS</a>
        </nav>

        <div class="nav-user">
            <span>Hi, <span data-user-name><?= e($first_name) ?></span>!</span>
            <a href="../logout.php">LOG OUT</a>
        </div>

    </header>

    <main class="main-content">

        <?php if ($error): ?>
        <div class="message error-message"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="message success-message"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($editor_mode): ?>

        <a href="notes.php?folder=<?= (int)$selected_folder["folder_id"] ?>" class="back-link">
            &larr; BACK TO FOLDER
        </a>

        <div class="editor-workspace">

            <form method="POST" id="noteForm">

                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="action" value="<?= $is_editing ? "update_note" : "create_note" ?>">
                <input type="hidden" name="folder_id" value="<?= (int)$selected_folder["folder_id"] ?>">

                <?php if ($is_editing): ?>
                <input type="hidden" name="note_id" value="<?= (int)$edit_note["note_id"] ?>">
                <?php endif; ?>

                <input type="hidden" name="content" id="savedContent">

                <!-- TOOLBAR -->

                <div class="editor-toolbar">

                    <div class="toolbar-group">

                        <button type="button" class="toolbar-button" data-command="undo" title="Undo">
                            <svg viewBox="0 0 24 24">
                                <path d="M9 14 4 9l5-5" />
                                <path d="M4 9h10a6 6 0 0 1 0 12h-2" />
                            </svg>
                        </button>

                        <button type="button" class="toolbar-button" data-command="redo" title="Redo">
                            <svg viewBox="0 0 24 24">
                                <path d="m15 14 5-5-5-5" />
                                <path d="M20 9H10a6 6 0 0 0 0 12h2" />
                            </svg>
                        </button>

                    </div>

                    <div class="toolbar-divider"></div>

                    <div class="toolbar-group">

                        <select class="toolbar-select" id="fontFamily" title="Font family">
                            <option value="Arial">Arial</option>
                            <option value="Times New Roman" selected>Times New Roman</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Verdana">Verdana</option>
                            <option value="Tahoma">Tahoma</option>
                            <option value="Courier New">Courier New</option>
                        </select>

                        <button type="button" class="toolbar-button size-button" id="fontDecrease"
                            title="Decrease font size">−</button>

                        <input type="number" id="fontSize" class="font-size-input" value="12" min="1" max="200" step="1"
                            title="Enter font size in pixels" aria-label="Font size in pixels">

                        <span class="font-size-unit">px</span>

                        <button type="button" class="toolbar-button size-button" id="fontIncrease"
                            title="Increase font size">+</button>

                    </div>

                    <div class="toolbar-divider"></div>

                    <div class="toolbar-group">

                        <button type="button" class="toolbar-button" data-command="bold" title="Bold">
                            <b>B</b>
                        </button>

                        <button type="button" class="toolbar-button italic-button" data-command="italic"
                            title="Italic">I</button>

                        <button type="button" class="toolbar-button underline-button" data-command="underline"
                            title="Underline">U</button>

                        <label class="color-control" title="Text color">
                            A
                            <input type="color" id="textColor" value="#222222">
                        </label>

                        <label class="color-control highlight-control" title="Highlight color">
                            <svg viewBox="0 0 24 24">
                                <path d="m14 4 6 6-9 9H5v-6l9-9Z" />
                                <path d="m12 6 6 6M4 21h16" />
                            </svg>
                            <input type="color" id="highlightColor" value="#ffe58a">
                        </label>

                    </div>

                    <div class="toolbar-divider"></div>

                    <div class="toolbar-group">

                        <button type="button" class="toolbar-button" data-command="justifyLeft" title="Align left">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 6h16M4 11h11M4 16h16M4 21h11" />
                            </svg>
                        </button>

                        <button type="button" class="toolbar-button" data-command="justifyCenter" title="Align center">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 6h16M7 11h10M4 16h16M7 21h10" />
                            </svg>
                        </button>

                        <button type="button" class="toolbar-button bullet-button" data-command="insertUnorderedList"
                            title="Bulleted list">
                            <svg viewBox="0 0 24 24">
                                <path d="M9 6h12M9 12h12M9 18h12" />
                                <circle cx="4" cy="6" r="1" />
                                <circle cx="4" cy="12" r="1" />
                                <circle cx="4" cy="18" r="1" />
                            </svg>
                        </button>

                        <button type="button" class="toolbar-button numbered-button" data-command="insertOrderedList"
                            title="Numbered list">
                            <svg viewBox="0 0 24 24">
                                <path d="M10 6h11M10 12h11M10 18h11" />
                                <path d="M3 5h2v3M3 8h2M3 11h2l-2 2h2M3 17h2a1 1 0 0 1 0 2H3" />
                            </svg>
                        </button>

                    </div>

                </div>

                <!-- EDITOR BODY -->

                <div class="editor-body">

                    <div class="editor-tabs">

                        <?php foreach ($folder_notes as $index => $tab_note): ?>

                        <a href="notes.php?edit=<?= (int)$tab_note["note_id"] ?>"
                            class="editor-tab tab-color-<?= $index % 5 ?> <?= ($is_editing && (int)$edit_note["note_id"] === (int)$tab_note["note_id"]) ? "active" : "" ?>"
                            title="<?= e($tab_note["title"]) ?>">

                            <span><?= e($tab_note["title"]) ?></span>

                        </a>

                        <?php endforeach; ?>

                        <a href="notes.php?create=<?= (int)$selected_folder["folder_id"] ?>"
                            class="editor-tab add-note-tab" title="Create another note">
                            <span>+</span>
                        </a>

                    </div>

                    <div class="editor-main">
                        <input type="text" class="editor-title" name="title" maxlength="255" placeholder="Title..."
                            value="<?= e($editor_title) ?>" required>

                        <div class="editor-writing-area" id="noteEditor" contenteditable="true" spellcheck="true"
                            data-placeholder="Write here..."><?= cleanNoteHtml($editor_content) ?></div>

                    </div>

                    <!-- RIGHT SIDEBAR -->

                    <aside class="editor-sidebar">

                        <div class="editor-search">

                            <div class="search-input-wrapper">

                                <svg viewBox="0 0 24 24" class="sidebar-search-icon">
                                    <circle cx="10.8" cy="10.8" r="7" />
                                    <path d="m16 16 5 5" />
                                </svg>

                                <input type="search" id="editorSearch" placeholder="Search in note...">

                            </div>

                            <p class="search-hint">Press Enter to find text</p>

                        </div>

                        <div class="todo-panel">

                            <div class="todo-heading">

                                <div>
                                    <h3>To do's</h3>
                                    <p>Keep track of your tasks</p>
                                </div>

                                <svg viewBox="0 0 24 24" class="todo-heading-icon">
                                    <path d="M9 5h11M9 12h11M9 19h11" />
                                    <path d="m3 5 1.5 1.5L7 4" />
                                    <path d="m3 12 1.5 1.5L7 11" />
                                    <path d="m3 19 1.5 1.5L7 18" />
                                </svg>

                            </div>

                            <div class="todo-progress">
                                <div class="todo-progress-bar" id="todoProgressBar"></div>
                            </div>

                            <div class="todo-progress-label" id="todoProgressLabel">
                                0 tasks completed
                            </div>

                            <div class="todo-list" id="todoList"></div>

                            <div class="todo-add">
                                <input type="text" id="todoInput" placeholder="Add a task...">
                                <button type="button" id="addTodoButton" aria-label="Add task">+</button>
                            </div>

                        </div>

                    </aside>

                </div>

                <!-- EDITOR ACTIONS -->

                <div class="editor-actions">

                    <a href="notes.php?folder=<?= (int)$selected_folder["folder_id"] ?>"
                        class="cancel-button">CANCEL</a>

                    <button type="submit" class="create-button">
                        <?= $is_editing ? "SAVE CHANGES" : "SAVE NOTE" ?>
                    </button>

                </div>

            </form>

            <?php if ($is_editing): ?>

            <form method="POST" class="delete-current-note"
                onsubmit="return confirm('Are you sure you want to delete this note?');">

                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="action" value="delete_note">
                <input type="hidden" name="note_id" value="<?= (int)$edit_note["note_id"] ?>">
                <input type="hidden" name="folder_id" value="<?= (int)$selected_folder["folder_id"] ?>">

                <button type="submit" class="delete-button">DELETE THIS NOTE</button>

            </form>

            <?php endif; ?>

        </div>

        <?php elseif ($selected_folder): ?>

        <a href="notes.php" class="back-link">&larr; BACK TO MY NOTES</a>

        <div class="folder-header">

            <div>
                <h1><?= e($selected_folder["folder_name"]) ?></h1>
                <p class="muted"><?= count($folder_notes) ?> note(s) in this folder</p>
            </div>

            <a class="create-button" href="notes.php?create=<?= (int)$selected_folder["folder_id"] ?>">
                + CREATE NOTE
            </a>

        </div>

        <div class="folder-note-list">

            <?php if (empty($folder_notes)): ?>

            <div class="empty-state">
                <h3>No notes in this folder yet</h3>
                <p>Click Create Note to add your first note.</p>
            </div>

            <?php else: ?>

            <?php foreach ($folder_notes as $note): ?>

            <article class="folder-note-item">

                <h3><?= e($note["title"]) ?></h3>

                <div class="note-list-preview">
                    <?= cleanNoteHtml($note["content"] ?? "") ?>
                </div>

                <div class="note-item-actions">
                    <a href="notes.php?edit=<?= (int)$note["note_id"] ?>">
                        OPEN / EDIT
                    </a>
                </div>

            </article>

            <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <form method="POST" class="delete-folder-form"
            onsubmit="return confirm('Delete this folder and all its notes?');">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
            <input type="hidden" name="action" value="delete_folder">
            <input type="hidden" name="folder_id" value="<?= (int)$selected_folder["folder_id"] ?>">

            <button type="submit" class="delete-button">DELETE FOLDER</button>

        </form>

        <?php else: ?>
        <!-- NOTES DASHBOARD -->

        <div class="section-heading">

            <h1>MY NOTES</h1>

            <div class="search-box">

                <svg viewBox="0 0 24 24" class="search-icon">
                    <circle cx="10.8" cy="10.8" r="7" />
                    <path d="m16 16 5 5" />
                </svg>

                <input type="search" id="dashboardSearch" placeholder="Search folders and notes...">

            </div>

        </div>

        <div class="filters">

            <a href="notes.php?period=today" class="<?= $period === "today" ? "filter-active" : "" ?>">Today</a>

            <a href="notes.php?period=week" class="<?= $period === "week" ? "filter-active" : "" ?>">This week</a>

            <a href="notes.php?period=month" class="<?= $period === "month" ? "filter-active" : "" ?>">This month</a>

        </div>

        <div class="folder-header">

            <h2>MY FOLDERS</h2>

            <button type="button" class="create-button" id="showFolderForm">
                + CREATE FOLDER
            </button>

        </div>

        <form method="POST" class="form-panel" id="folderForm" hidden>

            <h3>Create a new folder</h3>

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
            <input type="hidden" name="action" value="create_folder">

            <div class="form-group">

                <label for="folder_name">FOLDER NAME</label>

                <input type="text" id="folder_name" name="folder_name" maxlength="100" placeholder="Example: PLF NOTES"
                    required>

            </div>

            <div class="form-group">

                <label>CHOOSE FOLDER COLOR</label>

                <div class="color-options">

                    <?php
                        $colors = [
                            "#5298E8",
                            "#F15B5B",
                            "#F6AD55",
                            "#9B83D5",
                            "#48B99A"
                        ];

                        foreach ($colors as $index => $color):
                        ?>

                    <label>
                        <input type="radio" name="folder_color" value="<?= e($color) ?>"
                            <?= $index === 0 ? "checked" : "" ?>>
                        <span class="color-dot" style="background:<?= e($color) ?>"></span>
                    </label>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="create-button">SAVE FOLDER</button>

                <button type="button" class="cancel-button" id="cancelFolderForm">
                    CANCEL
                </button>

            </div>

        </form>

        <div class="folder-grid" id="folderGrid">

            <?php if (empty($folders)): ?>

            <div class="empty-state">
                <h3>No folders yet</h3>
                <p>Create your first folder to organize your notes.</p>
            </div>

            <?php else: ?>

            <?php foreach ($folders as $folder): ?>

            <a href="notes.php?folder=<?= (int)$folder["folder_id"] ?>" class="folder-card searchable"
                style="background:<?= e($folder["folder_color"]) ?>">

                <svg viewBox="0 0 24 24" class="folder-icon">
                    <path
                        d="M3 6.5A2.5 2.5 0 0 1 5.5 4H10l2 2h6.5A2.5 2.5 0 0 1 21 8.5V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6.5Z" />
                </svg>

                <span class="folder-dots">•••</span>

                <div>
                    <h3><?= e($folder["folder_name"]) ?></h3>

                    <p>
                        <?= (int)$folder["note_count"] ?>
                        <?= (int)$folder["note_count"] === 1 ? "NOTE" : "NOTES" ?>
                    </p>
                </div>

            </a>

            <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <!-- RECENT WORKS -->

        <section class="recent-section">

            <div class="recent-heading">

                <h2>RECENT WORKS</h2>

                <div class="recent-top">

                    <div class="recent-filters">

                        <a href="notes.php?period=today"
                            class="<?= $period === "today" ? "filter-active" : "" ?>">Today</a>

                        <a href="notes.php?period=week" class="<?= $period === "week" ? "filter-active" : "" ?>">This
                            week</a>

                        <a href="notes.php?period=month" class="<?= $period === "month" ? "filter-active" : "" ?>">This
                            month</a>

                    </div>

                    <span class="recent-date"><?= date("F Y") ?></span>

                </div>

            </div>

            <div class="notes-grid" id="notesGrid">

                <?php if (empty($recent_notes)): ?>

                <div class="empty-state">
                    <h3>No recent works yet</h3>
                    <p>Create a folder and add your first note.</p>
                </div>

                <?php else: ?>

                <?php foreach ($recent_notes as $note): ?>

                <a href="notes.php?edit=<?= (int)$note["note_id"] ?>" class="note-card searchable"
                    style="background:<?= e($note["folder_color"]) ?>">

                    <h3 class="note-title"><?= e($note["title"]) ?></h3>

                    <div class="note-preview">
                        <?= cleanNoteHtml($note["content"] ?? "") ?>
                    </div>

                    <div class="note-footer">
                        <span><?= date("m/d/y", strtotime($note["updated_at"])) ?></span>
                        <span><?= e($note["folder_name"]) ?></span>
                    </div>

                </a>

                <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

        <?php endif; ?>


        <!-- =================================================
             FLOATING POMODORO TIMER
        ================================================== -->

        <?php if ($editor_mode): ?>

        <button type="button" id="pomodoroToggle" class="pomodoro-toggle" aria-expanded="false"
            aria-controls="pomodoroPanel" data-csrf="<?= e($_SESSION["csrf_token"]) ?>"
            data-folder-id="<?= (int)$selected_folder["folder_id"] ?>"
            data-note-id="<?= $is_editing ? (int)$edit_note["note_id"] : 0 ?>">

            <span class="timer-icon" aria-hidden="true">◷</span>
            <span>FOCUS TIMER</span>

        </button>

        <section class="pomodoro-panel" id="pomodoroPanel" aria-label="Pomodoro timer" hidden>

            <div class="pomodoro-header">

                <div>
                    <span class="pomodoro-eyebrow">STUDYHIVE PRODUCTIVITY</span>
                    <h2>Pomodoro Timer</h2>
                </div>

                <button type="button" id="pomodoroClose" class="pomodoro-close"
                    aria-label="Close timer">&times;</button>

            </div>

            <div class="pomodoro-tabs">

                <button type="button" class="pomodoro-tab active" data-mode="focus">
                    FOCUS
                </button>

                <button type="button" class="pomodoro-tab" data-mode="break">
                    BREAK
                </button>

            </div>

            <div class="pomodoro-circle" id="pomodoroCircle">

                <div class="pomodoro-time-content">

                    <span class="pomodoro-mode-label" id="pomodoroModeLabel">
                        FOCUS TIME
                    </span>

                    <h1 id="pomodoroDisplay">25:00</h1>

                    <span class="pomodoro-status" id="pomodoroStatus">
                        Ready to focus?
                    </span>

                </div>

            </div>

            <div class="pomodoro-controls">

                <button type="button" id="pomodoroReset" class="pomodoro-secondary">
                    RESET
                </button>

                <button type="button" id="pomodoroStart" class="pomodoro-start">
                    START
                </button>

                <button type="button" id="pomodoroSkip" class="pomodoro-secondary">
                    SKIP
                </button>

            </div>

            <div class="pomodoro-settings">

                <div class="pomodoro-settings-heading">
                    <h3>Timer Settings</h3>
                    <span>Customize your sessions</span>
                </div>

                <div class="pomodoro-setting-fields">

                    <div class="pomodoro-setting">

                        <label for="focusDuration">Focus length</label>

                        <div class="pomodoro-number-input">
                            <input type="number" id="focusDuration" min="1" max="180" value="25">
                            <span>min</span>
                        </div>

                    </div>

                    <div class="pomodoro-setting">

                        <label for="breakDuration">Break length</label>

                        <div class="pomodoro-number-input">
                            <input type="number" id="breakDuration" min="1" max="60" value="5">
                            <span>min</span>
                        </div>

                    </div>

                </div>

                <button type="button" id="pomodoroSaveSettings" class="pomodoro-save-settings">
                    SAVE SETTINGS
                </button>

            </div>

            <p class="pomodoro-session-count">
                Completed focus sessions:
                <strong id="pomodoroSessions">0</strong>
            </p>

        </section>

        <?php endif; ?>

    </main>

    <script src="../assets/js/notes.js?v=pomodoro3"></script>


    <?php include __DIR__ . "/includes/settings_modal.php"; ?>
    <script src="../assets/js/settings.js?v=3"></script>
</body>

</html>