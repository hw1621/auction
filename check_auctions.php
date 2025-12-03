<?php
set_time_limit(0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utilities.php';

echo "--------------------------------------------------\n";
echo "Cron run at: " . date('Y-m-d H:i:s') . "\n";

$conn = get_database_connection();

$auctionQuery = sprintf(
    "SELECT a.id, a.title, a.reserve_price, u.email AS seller_email, u.username AS seller_name
     FROM auction a 
     JOIN item i ON a.item_id = i.id
     JOIN users u ON i.seller_id = u.user_id
     WHERE a.end_date <= NOW() AND a.status = '%s'",
    AuctionStatus::ACTIVE
);
$endedAuctions = $conn->query($auctionQuery);

if (!$endedAuctions) {
    error_log('check_auctions query error: ' . $conn->error);
    exit;
}

echo "Found {$endedAuctions->num_rows} auctions to process.\n";

$stSold = AuctionStatus::SOLD;
$stUnsold = AuctionStatus::UNSOLD;
$stActive = AuctionStatus::ACTIVE;

$baseUrl = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000'); 

while ($auction = $endedAuctions->fetch_assoc()) {
    $id = (int)$auction['id'];
    $title = htmlspecialchars($auction['title']);
    $reserve = (float)$auction['reserve_price'];
    $sEmail = $auction['seller_email'];
    
    $emailData = [
        'title' => $title,
        'link'  => "$baseUrl/mylistings.php",
        'price' => '0.00',
        'reserve' => number_format($reserve, 2)
    ];

    echo "Processing Auction ID: {$id}...\n";

    $bidSql = "
        SELECT b.bidder_id, b.amount, u.email AS bidder_email
        FROM bid b
        JOIN users u ON b.bidder_id = u.user_id
        WHERE b.auction_id = ?
        ORDER BY b.amount DESC, b.bid_time ASC LIMIT 1
    ";
    $stmt = $conn->prepare($bidSql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($row = $res->fetch_assoc()) {
        $wId = (int)$row['bidder_id'];
        $wAmt = (float)$row['amount'];
        $wEmail = $row['bidder_email'];
        
        $emailData['price'] = number_format($wAmt, 2);

        if ($wAmt >= $reserve) {
            $upd = $conn->prepare("UPDATE auction SET status=?, winner_id=?, final_price=? WHERE id=? AND status=?");
            $upd->bind_param('sidis', $stSold, $wId, $wAmt, $id, $stActive);
            $upd->execute();
            $upd->close();

            echo "  -> SOLD (£{$wAmt})\n";

            @send_email($sEmail, "Item Sold: $title", get_email_body('seller_sold', $emailData));
            @send_email($wEmail, "You Won: $title", get_email_body('winner_won', $emailData));
            notify_losers_rich($conn, $id, $wId, $emailData);

        } else {
            $upd = $conn->prepare("UPDATE auction SET status=? WHERE id=? AND status=?");
            $upd->bind_param('sis', $stUnsold, $id, $stActive);
            $upd->execute();
            $upd->close();

            echo "  -> UNSOLD (Reserve not met)\n";

            @send_email($sEmail, "Unsold: $title", get_email_body('seller_unsold_reserve', $emailData));
            @send_email($wEmail, "Auction Ended: $title", get_email_body('bidder_unsold_reserve', $emailData));
            notify_losers_rich($conn, $id, $wId, $emailData);
        }

    } else {
        $upd = $conn->prepare("UPDATE auction SET status=? WHERE id=? AND status=?");
        $upd->bind_param('sis', $stUnsold, $id, $stActive);
        $upd->execute();
        $upd->close();

        echo "  -> UNSOLD (No bids)\n";

        @send_email($sEmail, "Unsold: $title", get_email_body('seller_unsold_nobids', $emailData));
    }
}

$endedAuctions->free();
$conn->close();
echo "Done.\n";


function notify_losers_rich($conn, $auctionId, $winnerId, $data) {
    $sql = "SELECT DISTINCT u.email FROM bid b JOIN users u ON b.bidder_id = u.user_id WHERE b.auction_id = ? AND b.bidder_id != ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $auctionId, $winnerId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $body = get_email_body('loser_notification', $data);
        
        while ($row = $res->fetch_assoc()) {
            @send_email($row['email'], "Auction Ended: " . $data['title'], $body);
        }
        $stmt->close();
    }
}

// =================================================================
// Template Factory for Email Bodies
// =================================================================
function get_email_body($type, $data) {
    $title = $data['title'];
    $price = $data['price'];
    $link  = $data['link'];
    $reserve = $data['reserve'] ?? '0.00';

    // 通用 CSS 样式
    $style_container = "font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; background-color: #f9f9f9;";
    $style_header = "background-color: #333; color: #fff; padding: 15px; text-align: center; font-size: 18px; font-weight: bold;";
    $style_body = "background-color: #fff; padding: 20px; border: 1px solid #ddd; border-top: none;";
    $style_btn = "display: inline-block; background-color: #007bff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px;";
    $style_price = "color: #28a745; font-weight: bold; font-size: 16px;";
    $style_footer = "font-size: 12px; color: #999; text-align: center; margin-top: 20px;";

    // 定义不同场景的内容
    switch ($type) {
        case 'seller_sold':
            $header = "Item Sold Successfully";
            $content = "
                <p>Congratulations! Your auction for <strong>$title</strong> has ended successfully.</p>
                <p>Final Selling Price: <span style='$style_price'>£$price</span></p>
                <p>The winner has been notified. Please prepare the item for shipping.</p>
                <center><a href='$link' style='$style_btn'>View Auction Details</a></center>
            ";
            break;

        case 'winner_won':
            $header = "You Won!";
            $content = "
                <p>Great news! You submitted the highest bid for <strong>$title</strong>.</p>
                <p>Your Winning Bid: <span style='$style_price'>£$price</span></p>
                <p>Please proceed to payment to claim your item.</p>
                <center><a href='$link' style='$style_btn'>Pay Now / View Item</a></center>
            ";
            break;

        case 'loser_notification':
            $header = "Auction Ended";
            $content = "
                <p>The auction for <strong>$title</strong> has ended.</p>
                <p>Unfortunately, you did not win this time. The item sold for <strong>£$price</strong>.</p>
                <p>Don't worry, there are plenty more items waiting for you!</p>
                <center><a href='$link' style='$style_btn; background-color: #6c757d;'>Browse Similar Items</a></center>
            ";
            break;

        case 'seller_unsold_reserve':
            $header = "Auction Ended - Unsold";
            $content = "
                <p>Your auction for <strong>$title</strong> has ended.</p>
                <p>The highest bid was <strong>£$price</strong>, which did not meet your reserve price of <strong>£$reserve</strong>.</p>
                <p>You can choose to relist the item at a lower price.</p>
                <center><a href='$link' style='$style_btn'>Manage Listing</a></center>
            ";
            break;

        case 'bidder_unsold_reserve':
            $header = "Auction Ended";
            $content = "
                <p>The auction for <strong>$title</strong> has ended.</p>
                <p>Although you had the highest bid (£$price), it did not meet the seller's reserve price.</p>
                <p>The item was not sold.</p>
                <center><a href='$link' style='$style_btn; background-color: #6c757d;'>View Listing</a></center>
            ";
            break;

        case 'seller_unsold_nobids':
            $header = "Auction Ended - No Bids";
            $content = "
                <p>Your auction for <strong>$title</strong> has ended.</p>
                <p>Unfortunately, there were no bids placed on this item.</p>
                <p>Consider improving the description or lowering the starting price.</p>
                <center><a href='$link' style='$style_btn'>Relist Item</a></center>
            ";
            break;

        default:
            $header = "Notification";
            $content = "<p>Update regarding auction: $title</p>";
    }

    return "
        <div style='$style_container'>
            <div style='$style_header'>$header</div>
            <div style='$style_body'>
                $content
            </div>
            <div style='$style_footer'>
                &copy; " . date('Y') . " Auction System. All rights reserved.
            </div>
        </div>
    ";
}
?>