<?php
set_time_limit(0);
require_once __DIR__ . '/config.php';

echo "--------------------------------------------------\n";
echo "Cron run at: " . date('Y-m-d H:i:s') . "\n";

$conn = get_database_connection();

$auctionQuery = sprintf(
    "SELECT `id`, `title`, `reserve_price` FROM `auction` WHERE `end_date` <= NOW() AND `status` = '%s'",
    AuctionStatus::ACTIVE
);
$endedAuctions = $conn->query($auctionQuery);

if (!$endedAuctions) {
    echo "Error: Query failed - " . $conn->error . "\n";
    error_log('check_auctions: ' . $conn->error);
    close_database_connection($conn);
    exit;
}

$count = $endedAuctions->num_rows;
echo "Found {$count} active auctions that have ended.\n";

if ($count === 0) {
    echo "Nothing to process. Exiting.\n";
    $endedAuctions->free();
    close_database_connection($conn);
    exit;
}

$statusSold   = AuctionStatus::SOLD;
$statusUnsold = AuctionStatus::UNSOLD;
$statusActive = AuctionStatus::ACTIVE;

while ($auction = $endedAuctions->fetch_assoc()) {
    $auctionId = (int)$auction['id'];
    $auctionTitle = $auction['title'] ?? 'Auction #' . $auctionId;
    $reservePrice = (float)$auction['reserve_price'];

    echo "Processing Auction ID: {$auctionId} ('{$auctionTitle}')...\n";

    $highestBidSql = "
        SELECT `bidder_id`, `amount`
        FROM `bid`
        WHERE `auction_id` = ?
        ORDER BY `amount` DESC, `bid_time` ASC
        LIMIT 1
    ";
    $bidStmt = $conn->prepare($highestBidSql);
    $bidStmt->bind_param('i', $auctionId);
    $bidStmt->execute();
    $bidResult = $bidStmt->get_result();
    $bidStmt->close();

    if ($bidResult && $bidRow = $bidResult->fetch_assoc()) {
        $winnerId = (int) $bidRow['bidder_id'];
        $winningAmount = (float) $bidRow['amount'];

        echo "  -> Highest bid found: £{$winningAmount} by User ID {$winnerId}.\n";
        echo "  -> Reserve price is: £{$reservePrice}.\n";

        if ($winningAmount >= $reservePrice) {
            echo "  -> Result: SOLD! (Bid >= Reserve)\n";

            $update = $conn->prepare("
                UPDATE `auction`
                SET `status` = ?,
                    `winner_id` = ?,
                    `final_price` = ?
                WHERE `id` = ? AND `status` = ?
            ");
            $update->bind_param(
                'sidis',
                $statusSold,
                $winnerId,
                $winningAmount,
                $auctionId,
                $statusActive
            );
            $update->execute();
            $update->close();
        } else {
            echo "  -> Result: UNSOLD. (Bid < Reserve)\n";

            $update = $conn->prepare("
                UPDATE `auction`
                SET `status` = ?
                WHERE `id` = ? AND `status` = ?
            ");
            $update->bind_param(
                'sis',
                $statusUnsold,
                $auctionId,
                $statusActive
            );
            $update->execute();
            $update->close();
        }
    } else {
        echo "  -> No bids found.\n";
        echo "  -> Result: UNSOLD.\n";

        $unsoldStmt = $conn->prepare("
            UPDATE `auction`
            SET `status` = ?
            WHERE `id` = ? AND `status` = ?
        ");
        $unsoldStmt->bind_param(
            'sis',
            $statusUnsold,
            $auctionId,
            $statusActive
        );
        $unsoldStmt->execute();
        $unsoldStmt->close();

        error_log(sprintf(
            'check_auctions: no bids for auction %d ("%s"), marking unsold.',
            $auctionId,
            $auctionTitle
        ));
    }
    echo "Done processing Auction ID: {$auctionId}.\n\n";
}

$endedAuctions->free();
close_database_connection($conn);
echo "All tasks completed.\n";
?>