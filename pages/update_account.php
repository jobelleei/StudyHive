<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Your session has expired. Please log in again."]);
    exit;
}

require_once __DIR__ . "/../config/database.php";

if (
    !isset($_POST["csrf_token"], $_SESSION["csrf_token"]) ||
    !hash_equals((string)$_SESSION["csrf_token"], (string)$_POST["csrf_token"])
) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid request. Refresh the page and try again."]);
    exit;
}

$name = trim((string)($_POST["account_name"] ?? ""));
if ($name === "") {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Account name cannot be empty."]);
    exit;
}
if (mb_strlen($name) > 100) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Account name must not exceed 100 characters."]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET first_name = ? WHERE user_id = ?");
    $stmt->execute([$name, (int)$_SESSION["user_id"]]);
    $_SESSION["first_name"] = $name;
    echo json_encode(["success" => true, "name" => $name]);
} catch (PDOException $error) {
    error_log("StudyHive account name update failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to update account name."]);
}