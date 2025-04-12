<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer dependencies
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form values
    $name    = $_POST['name'] ?? '';
    $phone   = $_POST['phone'] ?? '';
    $email   = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? 'New Inquiry from Website';
    $message = $_POST['message'] ?? '';

    // Save to CSV file with header if it's the first entry
    $file = 'enquire.csv';
    $isNew = !file_exists($file) || filesize($file) === 0;
    $fileHandle = fopen($file, 'a');
    if ($isNew) {
        fputcsv($fileHandle, ['Timestamp', 'Name', 'Phone', 'Email']);
    }
    fputcsv($fileHandle, [date("Y-m-d H:i:s"), $name, $phone, $email]);
    fclose($fileHandle);

    // Send email using PHPMailer
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.greenbins.co.ke';        // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'enquiry@greenbins.co.ke';    // Your SMTP username
        $mail->Password   = 'Nyamwakaf@o1';              // Your SMTP password
        $mail->SMTPSecure = 'tls';                        // Use 'tls' or 'ssl' as supported
        $mail->Port       = 587;                          // Use 465 for 'ssl'

        // Recipients
        $mail->setFrom('enquiry@greenbins.co.ke', 'Green Bins');
        $mail->addAddress($email, $name);                 // Send confirmation to user
        $mail->addAddress('enquiry@greenbins.co.ke');     // Notify company

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "
            <h3>New Contact Inquiry</h3>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Phone:</strong> $phone</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Message:</strong><br>$message</p>
        ";

        $mail->send();

        // Show JS popup after submission
        echo "<script>
            alert('Thank you for contacting us. Your message has been received and we shall communicate through the provided email address.');
            window.location.href = 'index.html'; // Change this to your landing page
        </script>";

    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
