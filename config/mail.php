<?php
// garage_management_system/config/mail.php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'info@sericsoft.com');
define('MAIL_PASSWORD', 'Sericsoft@123');
define('MAIL_FROM_EMAIL', 'noreply@sericsoft.com');
define('MAIL_FROM_NAME', 'Garage Master');
define('MAIL_SMTP_AUTH', true);
define('MAIL_SMTP_SECURE', 'tls');
define('MAIL_DEBUG', 0);

class Mailer {
    private $to;
    private $subject;
    private $message;
    private $headers;
    
    public function __construct($to, $subject, $message, $isHTML = true) {
        $this->to = $to;
        $this->subject = $subject;
        
        if ($isHTML) {
            $this->headers = "MIME-Version: 1.0\r\n";
            $this->headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $this->headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
            $this->headers .= "Reply-To: " . MAIL_FROM_EMAIL . "\r\n";
            $this->headers .= "X-Mailer: PHP/" . phpversion();
            $this->message = $this->wrapHTML($message);
        } else {
            $this->headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
            $this->message = $message;
        }
    }
    
    private function wrapHTML($content) {
        return '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($this->subject) . '</title>
            <style>
                body { font-family: \'Montserrat\', \'Poppins\', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: var(--brand-primary); padding: 20px; text-align: center; }
                .content { padding: 30px; background-color: #f9f9f9; }
                .footer { background-color: var(--brand-dark); color: white; padding: 20px; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="' . BASE_URL . BRAND_LOGO . '" alt="' . BRAND_NAME . '" style="max-height: 50px;">
                </div>
                <div class="content">' . $content . '</div>
                <div class="footer">
                    <p>' . BRAND_NAME . ' | ' . BRAND_SLOGAN . '</p>
                    <p>Contact: ' . SUPPORT_EMAIL . ' | ' . SUPPORT_PHONE . '</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    public function send() {
        return mail($this->to, $this->subject, $this->message, $this->headers);
    }
}
?>