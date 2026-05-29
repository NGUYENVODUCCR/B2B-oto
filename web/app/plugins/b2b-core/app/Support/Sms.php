<?php

class Sms {
    private static $config;

    private static function config() {
        if (!self::$config) {
            self::$config = require __DIR__ . '/../../config/sms.php';
        }
        return self::$config;
    }

    private static function formatPhone($phone) {
        $phone = preg_replace('/\D/', '', $phone); 
        if (strpos($phone, '0') === 0) {
            return $phone; 
        }
        if (strpos($phone, '84') === 0) {
            return '0' . substr($phone, 2); 
        }
        return $phone;
    }

    public static function sendOtpByPhone($toPhone, $otp) {
        $config = self::config();
        $formattedPhone = self::formatPhone($toPhone);
        $content = "[B2B-MARKETPLACE] Ma OTP cua ban la $otp. Ma co hieu luc trong 15 phut.";
    
        
        $url = "http://api.speedsms.vn/index.php/sms/send";
    
        
        $data = [
            'to' => [$formattedPhone],
            'content' => $content,
            'type' => 5, 
            'sender' => $config['sender'] 
        ];
    
        $accessToken = $config['access_token'];
    
        error_log("=== SPEEDSMS SENDING DEBUG ===");
        error_log("URL: " . $url);
        error_log("DEVICE ID: " . $config['sender']);
    
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($accessToken . ':')
        ]);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    
        if ($error) {
            error_log("CURL ERROR: " . $error);
            throw new Exception("Lỗi kết nối API: " . $error);
        }
    
        error_log("FULL RESPONSE: " . $response);
    
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] == 'success') {
            error_log("SMS SENT SUCCESS thông qua App Android.");
        } else {
            $msg = $result['message'] ?? 'Unknown Error từ Server';
            error_log("SPEEDSMS REJECTED: " . $msg);
            throw new Exception("Lỗi SpeedSMS: " . $msg);
        }
    }    
}