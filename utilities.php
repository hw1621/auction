<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include configuration constants
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Send email via PHPMailer and SMTP.
 *
 * @param string $recipient_email The email address of the recipient.
 * @param string $subject         Subject line of the message.
 * @param string $html_body       HTML content of the message.
 * @param string $alt_body        Optional plain-text fallback.
 * @return bool                   True on success, false on failure.
 */
function send_email($recipient_email, $subject, $html_body, $alt_body = '')
{
    try {
        $mail = new PHPMailer(true);

        // SMTP settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        // Sender
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Recipient
        $mail->addAddress($recipient_email);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $alt_body !== '' ? $alt_body : strip_tags($html_body);

        // Send
        return $mail->send();
    } catch (Exception $e) {
        error_log('Unable to send email: ' . $e->getMessage());
        return false;
    }
}


// display_time_remaining:
// Helper function to help figure out what time to display
function display_time_remaining($interval) {

    if ($interval->days == 0 && $interval->h == 0) {
      // Less than one hour remaining: print mins + seconds:
      $time_remaining = $interval->format('%im %Ss');
    }
    else if ($interval->days == 0) {
      // Less than one day remaining: print hrs + mins:
      $time_remaining = $interval->format('%hh %im');
    }
    else {
      // At least one day remaining: print days + hrs:
      $time_remaining = $interval->format('%ad %hh');
    }

  return $time_remaining;

}

/**
 * 打印商品列表项 (大图、多文字、宽间距版)
 */
function print_listing_li($auction_id, $title, $desc, $price, $num_bids, $end_time, $category, $image_path = null)
{
  $desc_shortened = mb_strimwidth($desc, 0, 400, "...");
  $bid_text = ($num_bids == 1) ? '1 bid' : $num_bids . ' bids';

  $now = new DateTime();
  if ($now > $end_time) {
    $time_remaining = 'Ended';
    $time_class = 'text-secondary';
  } else {
    $interval = $now->diff($end_time);
    if ($interval->days == 0 && $interval->h < 12) {
        $time_remaining = $interval->format('%h h %i m left');
        $time_class = 'text-danger font-weight-bold';
    } else {
        $time_remaining = $interval->format('%a d %h h left');
        $time_class = 'text-muted';
    }
  }

  $price_formatted = number_format($price, 2);
  $image_html = '<div class="bg-light d-flex align-items-center justify-content-center rounded mr-4" style="width: 150px; height: 150px; flex-shrink: 0;">
                    <i class="fa fa-image fa-3x text-black-50"></i>
                 </div>';
  
  if (!empty($image_path) && file_exists(__DIR__ . '/uploads/' . $image_path)) {
      $image_html = '<div class="mr-4" style="width: 150px; height: 150px; flex-shrink: 0; overflow: hidden; border-radius: 6px; border: 1px solid #f0f0f0;">
                        <img src="uploads/' . htmlspecialchars($image_path) . '" alt="Item Image" style="width: 100%; height: 100%; object-fit: cover;">
                     </div>';
  }

  echo '
  <li class="list-group-item d-flex justify-content-between align-items-start p-4 hover-effect" style="border-left:0; border-right:0; margin-bottom: 10px; background: #fff;">
    
    <div class="d-flex flex-row w-100">
        
        ' . $image_html . '

        <div style="flex-grow: 1; min-width: 0; padding-right: 20px;"> 
            <div class="d-flex align-items-center mb-2">
                <h5 class="mb-0 text-truncate mr-2" style="font-size: 1.25rem; font-weight: bold;">
                    <a href="listing.php?auction_id=' . $auction_id . '" class="text-dark text-decoration-none">' . htmlspecialchars($title) . '</a>
                </h5>
                <span class="badge badge-pill badge-light border text-secondary">' . htmlspecialchars($category) . '</span>
            </div>

            <p class="text-muted" style="font-size: 0.95rem; line-height: 1.6; margin-bottom: 0;">' . htmlspecialchars($desc_shortened) . '</p>
        </div>
    </div>

    <div class="text-right ml-4 d-flex flex-column justify-content-between" style="min-width: 160px; height: 150px;">
        
        <div>
            <h4 class="font-weight-bold mb-0 text-primary" style="font-family: serif; font-size: 1.8rem;">£' . $price_formatted . '</h4>
            <small class="text-muted">' . $bid_text . '</small>
        </div>
        
        <div>
            <div class="mb-2">
                <small class="' . $time_class . '"><i class="fa fa-clock-o"></i> ' . $time_remaining . '</small>
            </div>
            <a href="listing.php?item_id=' . $auction_id . '" class="btn btn-outline-primary btn-block">View Lot</a>
        </div>
    </div>
  </li>';
}

/**
 * @param array $fileInput $_FILES['key']
 * @param string $uploadDir
 * @return array ['filename' => string|null, 'error' => string|null]
 */
function uploadImage($fileInput, $uploadDir) {
  $result = [
      'filename' => null,
      'error' => null
  ];

  if (!isset($fileInput) || $fileInput['error'] === UPLOAD_ERR_NO_FILE) {
      return $result; 
  }

  if ($fileInput['error'] !== UPLOAD_ERR_OK) {
      $errorCode = $fileInput['error'];
      if ($errorCode == 1 || $errorCode == 2) {
          $result['error'] = 'Image is too large (Check server config).';
      } else {
          $result['error'] = 'Image upload failed with error code: ' . $errorCode;
      }
      return $result;
  }

  $tmpName = $fileInput['tmp_name'];
  $originalName = $fileInput['name'];
  $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'gif'];

  if (!in_array($ext, $allowed)) {
      $result['error'] = 'Invalid file type. Only JPG, PNG, GIF allowed.';
      return $result;
  }

  if (!is_dir($uploadDir)) {
      if (!mkdir($uploadDir, 0755, true)) {
          $result['error'] = 'Failed to create upload directory.';
          return $result;
      }
  }

  $newFileName = uniqid('img_') . '.' . $ext;
  $destPath = $uploadDir . $newFileName;

  if (move_uploaded_file($tmpName, $destPath)) {
      $result['filename'] = $newFileName;
  } else {
      $result['error'] = 'Failed to save image. Check server permissions.';
  }

  return $result;
}
