<?php
require_once("utilities.php");
session_start();

if (!isset($_POST['functionname']) || !isset($_POST['auction_id'])) {
  echo "error: invalid request";
  exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "error: not logged in";
    exit;
}

$functionname = $_POST['functionname'];
$auction_id = (int)$_POST['auction_id'];
$user_id = $_SESSION['user_id'];

$conn = get_database_connection();

if ($functionname == "add_to_watchlist") {
    $sql = "INSERT IGNORE INTO watchlist (user_id, auction_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $auction_id);
    
    if ($stmt->execute()) {
        $res = "success";
    } else {
        $res = "failed: " . $conn->error;
    }
    $stmt->close();
}
else if ($functionname == "remove_from_watchlist") {
    $sql = "DELETE FROM watchlist WHERE user_id = ? AND auction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $auction_id);
    
    if ($stmt->execute()) {
        $res = "success";
    } else {
        $res = "failed: " . $conn->error;
    }
    $stmt->close();
}
else {
    $res = "error: unknown function";
}

$conn->close();
echo $res;
?>