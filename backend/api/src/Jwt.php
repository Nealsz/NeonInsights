<?php

class Jwt
{

    public function __construct(private string $key)
    {
    }

    //BASE64 ENCODE & DECODE
    private function base64URLEncode(string $text): string
    {

        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }


    private function base64URLDecode(string $text): string
    {
        return base64_decode(str_replace(["-", "_"], ["+", "/"], $text));
    }

    //ENCODE BLOCK
    public function encode(array $payload): string
    {

        $header = json_encode([
            "alg" => "HS256",
            "typ" => "JWT"
        ]);

        $header = $this->base64URLEncode($header);
        $payload = json_encode($payload);
        $payload = $this->base64URLEncode($payload);

        $signature = hash_hmac("sha256", $header . "." . $payload, $this->key, true);
        $signature = $this->base64URLEncode($signature);
        return $header . "." . $payload . "." . $signature;
    }

    //DECODE BLOCK
    public function decode(string $token): array
    {
        if (
            preg_match(
                "/^(?<header>.+)\.(?<payload>.+)\.(?<signature>.+)$/",
                $token,
                $matches
            ) !== 1
        ) {

            throw new InvalidArgumentException("invalid token format");
        }

        $signature = hash_hmac(
            "sha256",                                       //algorithm
            $matches["header"] . "." . $matches["payload"], //data to hash
            $this->key,                                     //key used for hmac
            true                                            //binary if true, hexadecimal if false
        );

        $signature_from_token = $this->base64URLDecode($matches["signature"]);

        if (!hash_equals($signature, $signature_from_token)) {

            // throw new Exception("signature doesn't match");
            throw new InvalidSignatureException;
        }

        $payload = json_decode($this->base64URLDecode($matches["payload"]), true);

        return $payload;
    }
}
