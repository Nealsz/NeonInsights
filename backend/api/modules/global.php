<?php

require_once __DIR__ . '/../src/Jwt.php';

class GlobalMethods {
    private static $jwt_key = "your_secret_key";
    
    public static function sendPayload($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
    }

    public static function generateJWT($user_id, $email, $username) {
        $jwt = new Jwt(self::$jwt_key);
        $payload = array(
            "iat" => time(),
            "exp" => time() + (60 * 60), // 1 hour expiration
            "data" => array(
                "id" => $user_id,
                "email" => $email,
                "username" => $username
            )
        );
        return $jwt->encode($payload);
    }

    public static function decodeJWT($token) {
        $jwt = new Jwt(self::$jwt_key);
        return $jwt->decode($token);
    }
}
?>