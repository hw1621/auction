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

function print_listing_li($auction_id, $title, $desc, $price, $num_bids, $end_time, $category)
{
  if (strlen($desc) > 200) {
    $desc_shortened = substr($desc, 0, 200) . '...';
  } else {
    $desc_shortened = $desc;
  }

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

  $price_formatted = number_format($price, 2);

  echo '
  <li class="list-group-item d-flex justify-content-between align-items-center p-3 hover-effect">
    <div class="d-flex flex-row align-items-center w-100">
        
        <div class="bg-light d-flex align-items-center justify-content-center rounded mr-4" style="width: 100px; height: 100px; flex-shrink: 0;">
            <i class="fa fa-image fa-2x text-black-50"></i>
        </div>

        <div style="flex-grow: 1; min-width: 0;"> <h5 class="mb-1 text-truncate">
                <a href="listing.php?auction_id=' . $auction_id . '" class="text-dark text-decoration-none">' . htmlspecialchars($title) . '</a>
            </h5>
            
            <div class="mb-2">
                <span class="badge badge-pill badge-info"><i class="fa fa-tag"></i> ' . htmlspecialchars($category) . '</span>
            </div>

            <p class="mb-1 text-muted small" style="line-height: 1.4;">' . htmlspecialchars($desc_shortened) . '</p>
        </div>
    </div>

    <div class="text-right ml-4" style="min-width: 140px;">
        <div class="mb-2">
            <h4 class="font-weight-bold mb-0 text-primary">£' . $price_formatted . '</h4>
            <small class="text-muted">' . $bid_text . '</small>
        </div>
        
        <div class="mb-2">
            <small class="' . $time_class . '"><i class="fa fa-clock-o"></i> ' . $time_remaining . '</small>
        </div>

        <a href="listing.php?auction_id=' . $auction_id . '" class="btn btn-outline-primary btn-sm btn-block">View</a>
    </div>
  </li>';
}
