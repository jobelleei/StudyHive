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

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// =====================================================
// FILTERS
// =====================================================

$search = trim($_GET["search"] ?? "");
$folder_filter = (int)($_GET["folder"] ?? 0);
$sort = $_GET["sort"] ?? "newest";

$allowed_sorts = ["newest", "oldest", "title"];

if (!in_array($sort, $allowed_sorts, true)) {
    $sort = "newest";
}

// =====================================================
// LOAD USER'S FOLDERS
// =====================================================

$stmt = $pdo->prepare(
    "SELECT folder_id, folder_name, folder_color
     FROM folders
     WHERE user_id = ?
     ORDER BY folder_name ASC"
);

$stmt->execute([$user_id]);
$folders = $stmt->fetchAll();

// =====================================================
// VALIDATE SELECTED FOLDER
// =====================================================

if ($folder_filter > 0) {
    $validFolder = false;

    foreach ($folders as $folder) {
        if ((int)$folder["folder_id"] === $folder_filter) {
            $validFolder = true;
            break;
        }
    }

    if (!$validFolder) {
        $folder_filter = 0;
    }
}

// =====================================================
// LOAD NOTES
// =====================================================

$sql = "
    SELECT
        n.note_id,
        n.folder_id,
        n.title,
        n.content,
        n.created_at,
        n.updated_at,
        f.folder_name,
        f.folder_color
    FROM notes n
    INNER JOIN folders f
        ON f.folder_id = n.folder_id
    WHERE n.user_id = ?
      AND f.user_id = ?
";

$params = [$user_id, $user_id];

// Folder filter
if ($folder_filter > 0) {
    $sql .= " AND n.folder_id = ?";
    $params[] = $folder_filter;
}

// Search filter
if ($search !== "") {
    $sql .= " AND (
        n.title LIKE ?
        OR n.content LIKE ?
        OR f.folder_name LIKE ?
    )";

    $keyword = "%" . $search . "%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

// Sorting
switch ($sort) {
    case "oldest":
        $sql .= " ORDER BY n.created_at ASC, n.note_id ASC";
        break;

    case "title":
        $sql .= " ORDER BY n.title ASC";
        break;

    default:
        $sql .= " ORDER BY n.updated_at DESC, n.note_id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$notes = $stmt->fetchAll();

// =====================================================
// NOTE PREVIEW
// =====================================================

function notePreview($content, $limit = 180) {
    $text = html_entity_decode(
        strip_tags((string)$content),
        ENT_QUOTES,
        "UTF-8"
    );

    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if ($text === "") {
        return "No content available for this note.";
    }

    if (mb_strlen($text) > $limit) {
        return mb_substr($text, 0, $limit) . "...";
    }

    return $text;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Feed | StudyHive</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <script>
    (function() {
        try {
            let theme = localStorage.getItem("studyhiveTheme");

            if (theme) {
                try {
                    theme = JSON.parse(theme);
                } catch (error) {
                    // Settings stores the theme as plain text.
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

    <link rel="stylesheet" href="../assets/css/feed.css?v=4">
    <link rel="stylesheet" href="../assets/css/settings-modal.css?v=3">
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
            <a href="feed.php" class="active">FEED</a>
            <a href="#" data-open-settings>SETTINGS</a>

        </nav>

        <div class="nav-user">
            <span>Hi, <span data-user-name><?= e($first_name) ?></span>!</span>
            <a href="../logout.php">LOG OUT</a>
        </div>

    </header>


    <main class="feed-container">

        <!-- WELCOME SECTION -->

        <section class="feed-hero">

            <h1>WELCOME!</h1>

            <p>
                Keep all your study materials organized in one place.
            </p>

            <div class="feed-hero-bottom">

                <div class="feed-intro">
                    <h2>MY NOTES</h2>
                    <p>Browse and manage all your saved notes.</p>
                </div>

                <div class="feed-summary">

                    <div class="summary-item">
                        <span class="summary-number">
                            <?= count($notes) ?>
                        </span>

                        <span class="summary-label">
                            NOTES FOUND
                        </span>

                    </div>

                    <div class="summary-item">
                        <span class="summary-number">
                            <?= count($folders) ?>
                        </span>

                        <span class="summary-label">
                            MY FOLDERS
                        </span>
                    </div>

                </div>

            </div>

        </section>


        <!-- SEARCH AND FILTER SECTION -->

        <section class="feed-content">

            <form method="GET" action="feed.php" class="feed-filter-form">

                <div class="feed-search">

                    <svg viewBox="0 0 24 24">
                        <circle cx="10.8" cy="10.8" r="7"></circle>
                        <path d="m16 16 5 5"></path>
                    </svg>

                    <input type="search" name="search" placeholder="Search your notes..." value="<?= e($search) ?>">

                    <?php if ($folder_filter > 0): ?>
                    <input type="hidden" name="folder" value="<?= $folder_filter ?>">
                    <?php endif; ?>

                    <button type="submit" aria-label="Search notes">
                        SEARCH
                    </button>

                </div>


                <div class="feed-filter-row">

                    <div class="filter-label">
                        FILTER BY:
                    </div>

                    <div class="filter-controls">

                        <select name="folder" class="feed-select" onchange="this.form.submit()">

                            <option value="0">All Folders</option>

                            <?php foreach ($folders as $folder): ?>

                            <option value="<?= (int)$folder["folder_id"] ?>"
                                <?= $folder_filter === (int)$folder["folder_id"] ? "selected" : "" ?>>
                                <?= e($folder["folder_name"]) ?>
                            </option>

                            <?php endforeach; ?>

                        </select>


                        <select name="sort" class="feed-select" onchange="this.form.submit()">

                            <option value="newest" <?= $sort === "newest" ? "selected" : "" ?>>
                                Recently Added
                            </option>

                            <option value="oldest" <?= $sort === "oldest" ? "selected" : "" ?>>
                                Oldest First
                            </option>

                            <option value="title" <?= $sort === "title" ? "selected" : "" ?>>
                                Title A-Z
                            </option>

                        </select>

                    </div>

                </div>

            </form>


            <!-- NOTES HEADING -->

            <div class="latest-heading">

                <div>
                    <h2>LATEST NOTES</h2>

                    <p>
                        <?= count($notes) ?>
                        <?= count($notes) === 1 ? "note" : "notes" ?> found
                    </p>
                </div>

                <a href="notes.php" class="view-notes-link">
                    GO TO MY NOTES
                </a>

            </div>


            <!-- NOTES GRID -->

            <div class="feed-notes-grid">

                <?php if (empty($notes)): ?>

                <div class="feed-empty-state">

                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M6 3h9l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path>
                            <path d="M14 3v6h6"></path>
                            <path d="M8 13h8M8 17h6"></path>
                        </svg>
                    </div>

                    <h3>No notes found</h3>

                    <p>
                        <?php if ($search !== "" || $folder_filter > 0): ?>
                        Try changing your search or filters.
                        <?php else: ?>
                        You haven't created any notes yet.
                        Start by creating your first note.
                        <?php endif; ?>
                    </p>

                    <a href="notes.php" class="create-note-link">
                        GO TO MY NOTES
                    </a>

                </div>

                <?php else: ?>

                <?php
                    $card_colors = [
                        "#FFE27A",
                        "#FADADD",
                        "#DDEBFF",
                        "#DDF5E3",
                        "#E9DDFB",
                        "#FFE5CC"
                    ];
                    ?>

                <?php foreach ($notes as $index => $note): ?>

                <article class="feed-note-card"
                    style="--card-color: <?= e($card_colors[$index % count($card_colors)]) ?>">

                    <div class="note-card-top">

                        <span class="note-folder">
                            <?= e($note["folder_name"]) ?>
                        </span>

                        <svg class="note-file-icon" viewBox="0 0 24 24">
                            <path d="M6 3h9l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path>
                            <path d="M14 3v6h6"></path>
                        </svg>

                    </div>


                    <h3>
                        <?= e($note["title"]) ?>
                    </h3>


                    <p class="note-preview">
                        <?= e(notePreview($note["content"])) ?>
                    </p>


                    <div class="note-card-footer">

                        <div class="note-date">

                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                                <path d="M16 3v4M8 3v4M3 10h18"></path>
                            </svg>

                            <span>
                                <?= e(date(
                                            "M d, Y",
                                            strtotime($note["created_at"])
                                        )) ?>
                            </span>

                        </div>


                        <span class="note-updated">
                            Updated
                            <?= e(date(
                                        "M d",
                                        strtotime($note["updated_at"])
                                    )) ?>
                        </span>

                    </div>


                    <a href="notes.php?edit=<?= (int)$note["note_id"] ?>" class="open-note-button">
                        OPEN NOTE

                        <svg viewBox="0 0 24 24">
                            <path d="M5 12h14M13 6l6 6-6 6"></path>
                        </svg>

                    </a>

                </article>

                <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    </main>


    <?php include __DIR__ . "/includes/settings_modal.php"; ?>
    <script src="../assets/js/settings.js?v=3"></script>
</body>

</html>