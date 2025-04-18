<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer classes from the 'src' folder
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize user inputs
    $name    = htmlspecialchars($_POST['name']);
    $phone   = htmlspecialchars($_POST['phone']);
    $email   = htmlspecialchars($_POST['email']);
    $service = htmlspecialchars($_POST['service']);
    $county  = htmlspecialchars($_POST['county']);
    $comment = htmlspecialchars($_POST['comment']);

    // Save to quotes.csv (append mode)
    $csvFile = fopen("quotes.csv", "a");
    if (filesize("quotes.csv") == 0) {
        // Write headers only if the file is empty
        fputcsv($csvFile, ['Timestamp', 'Name', 'Phone', 'Email', 'Service', 'county', 'Comment']);
    }
    fputcsv($csvFile, [date("Y-m-d H:i:s"), $name, $phone, $email, $service, $county, $comment]);
    fclose($csvFile);

    // Send email to admin and user using PHPMailer
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'da12.host-ww.net';         
        $mail->SMTPAuth   = true;
        $mail->Username   = 'quotes@greenbins.co.ke';    
        $mail->Password   = 'Nyamwakaf@o1';             
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;                         // Usually 587 for TLS

        // Admin email (notification of the quote request)
        $mail->setFrom('quotes@greenbins.co.ke', 'Green Bins');
        $mail->addAddress('quotes@greenbins.co.ke');     // Admin address to receive the quote
        $mail->addReplyTo($email, $name);                // Reply to user’s email address

        // Admin message body
        $mail->isHTML(true);
        $mail->Subject = "New Quote Request from $name of $county County";
        $mail->Body    = "
            <strong>Name:</strong> $name<br>
            <strong>Phone:</strong> $phone<br>
            <strong>Email:</strong> $email<br>
            <strong>Service:</strong> $service<br>
            <strong>County:</strong> $county<br>
            <strong>Comment:</strong> $comment
        ";
        $mail->send();

        // User confirmation email
        $mail->clearAddresses();
        $mail->addAddress($email); // User's email
        $mail->Subject = "We received your quote request";
        $mail->Body    = "
            Hi $name,<br><br>
            We’ve received your request for <strong>$service</strong>.<br>
            We’ll contact you soon through the email provided. Thank you.<br><br>
            – Green Bins Team
        ";
        $mail->send();

        // Redirect back to the form with success message
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?success=true");
        exit(); // Ensure script execution stops after redirect

    } catch (Exception $e) {
        // In case of an error, display a message
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
