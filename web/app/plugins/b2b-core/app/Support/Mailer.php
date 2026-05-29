<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {

    private static $config;

    private static function config() {
        if (!self::$config) {
            self::$config = require __DIR__ . '/../../config/mail.php';
        }
        return self::$config;
    }

    private static function validateEmail($email){
        $email = trim(strtolower($email));

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            throw new Exception("Email không hợp lệ");
        }

        return $email;
    }

    private static function configureMailer(PHPMailer $mail) {
        $config = self::config();
        $username = trim((string) ($config['username'] ?? ''));
        $fromName = trim((string) ($config['from_name'] ?? ''));
        $fromEmail = self::resolveFromEmail($config, $username);

        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;

        $mail->Username   = $username;
        $mail->Password   = $config['password'];

        $mail->SMTPSecure = $config['encryption'] ?? 'tls';
        $mail->Port       = $config['port'];

        $mail->CharSet  = $config['charset'] ?? 'UTF-8';
        $mail->Encoding = $config['encoding'] ?? 'base64';

        if ($fromName === '' && function_exists('get_bloginfo')) {
            $fromName = (string) get_bloginfo('name');
        }

        if ($fromName === '') {
            $fromName = 'B2B Marketplace';
        }

        $mail->setFrom($fromEmail, $fromName);
    }

    private static function resolveFromEmail(array $config, $username) {
        $candidates = [
            trim((string) ($config['from_email'] ?? '')),
            trim((string) $username),
        ];

        if (function_exists('get_option')) {
            $candidates[] = trim((string) get_option('admin_email'));
        }

        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        throw new Exception('Missing valid from email configuration');
    }

    private static function send($toEmail, $subject, $body) {

        $mail = new PHPMailer(true);

        $toEmail = self::validateEmail($toEmail);

        try {
            self::configureMailer($mail);

            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();

            error_log("MAIL SENT TO: " . $toEmail);

        } catch (Exception $e) {
            error_log("MAIL ERROR: " . $mail->ErrorInfo);
            throw new Exception("Không gửi được email");
        }
    }

    public static function sendContract($toEmails, $subject, $body, array $attachments = []) {
        $mail = new PHPMailer(true);
        $toEmails = is_array($toEmails) ? $toEmails : [$toEmails];
        $validEmails = [];
        $existingAttachments = [];

        foreach ($toEmails as $email) {
            try {
                $validEmails[] = self::validateEmail($email);
            } catch (Exception $e) {
                error_log('CONTRACT MAIL INVALID EMAIL: ' . $email);
            }
        }

        $validEmails = array_values(array_unique($validEmails));

        if (empty($validEmails)) {
            throw new Exception('Khong co email hop le de gui hop dong');
        }

        try {
            self::configureMailer($mail);

            foreach ($validEmails as $email) {
                $mail->addAddress($email);
            }

            foreach ($attachments as $attachment) {
                if (is_string($attachment) && file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                    $existingAttachments[] = $attachment;
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();

            error_log('CONTRACT MAIL SENT TO: ' . implode(', ', $validEmails));
        } catch (Exception $e) {
            error_log('CONTRACT MAIL ERROR: ' . $mail->ErrorInfo);

            if (function_exists('wp_mail')) {
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $sent = wp_mail($validEmails, $subject, $body, $headers, $existingAttachments);

                if ($sent) {
                    error_log('CONTRACT WP MAIL SENT TO: ' . implode(', ', $validEmails));
                    return;
                }
            }

            throw new Exception('Khong gui duoc email hop dong');
        }
    }
    public static function sendAdminNotification($toEmails, $subject, $body, array $attachments = []) {
    self::sendContract($toEmails, $subject, $body, $attachments);
}


    public static function sendResetPassword($toEmail, $otp) {

        $subject = "Yêu cầu đặt lại mật khẩu";

        $body = "
            <div style='font-family: Arial;'>
                <h2>Đặt lại mật khẩu</h2>
                <p>Mã OTP của bạn là:</p>
                <div style='font-size:32px; font-weight:bold; color:red; letter-spacing:5px;'>
                    $otp
                </div>
                <p>Mã có hiệu lực trong 15 phút.</p>
                <hr>
                <small>Nếu bạn không yêu cầu, hãy bỏ qua email này.</small>
            </div>
        ";

        self::send($toEmail, $subject, $body);
    }
}
