<?php
require_once __DIR__ . '/config.php';

$conn = get_database_connection();

$auctionQuery = sprintf(
    "SELECT `id`, `title`, `reserve_price` FROM `auction` WHERE `end_date` <= NOW() AND `status` = '%s'",
    AuctionStatus::ACTIVE
);
$endedAuctions = $conn->query($auctionQuery);

if (!$endedAuctions) {
    error_log('check_auctions: ' . $conn->error);
    close_database_connection($conn);
    exit;
}

while ($auction = $endedAuctions->fetch_assoc()) {
    $auctionId = (int)$auction['id'];
    $auctionTitle = $auction['title'] ?? 'Auction #' . $auctionId;
    $reservePrice = (float)$auction['reserve_price'];

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

        if ($winningAmount >= $reservePrice) {
            $update = $conn->prepare("
                UPDATE `auction`
                SET `status` = ?,
                    `winner_id` = ?,
                    `final_price` = ?
                WHERE `id` = ? AND `status` = ?
            ");
            $update->bind_param(
                'sidis',
                AuctionStatus::SOLD,
                $winnerId,
                $winningAmount,
                $auctionId,
                AuctionStatus::ACTIVE
            );
            $update->execute();
            $update->close();
        } else {
            $update = $conn->prepare("
                UPDATE `auction`
                SET `status` = ?
                WHERE `id` = ? AND `status` = ?
            ");
            $update->bind_param(
                'sis',
                AuctionStatus::UNSOLD,
                $auctionId,
                AuctionStatus::ACTIVE
            );
            $update->execute();
            $update->close();
        }
    } else {
        $unsoldStmt = $conn->prepare("
            UPDATE `auction`
            SET `status` = ?
            WHERE `id` = ? AND `status` = ?
        ");
        $unsoldStmt->bind_param(
            'sis',
            AuctionStatus::UNSOLD,
            $auctionId,
            AuctionStatus::ACTIVE
        );
        $unsoldStmt->execute();
        $unsoldStmt->close();

        error_log(sprintf(
            'check_auctions: no bids for auction %d ("%s"), marking unsold.',
            $auctionId,
            $auctionTitle
        ));
    }
}

$endedAuctions->free();
close_database_connection($conn);
?> 