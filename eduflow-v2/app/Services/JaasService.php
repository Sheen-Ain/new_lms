<?php

namespace App\Services;

use App\Core\Logger;

/**
 * JaasService — JWT token generation for 8x8 Jitsi As A Service.
 * Uses openssl_sign with the private key from config.php.
 */
class JaasService
{
    public static function token($roomName, $userName, $userEmail, $userAvatar = '', $isModerator = false)
    {
        $appId = JAAS_APP_ID;
        $keyId = JAAS_API_KEY_ID;

        $privateKey = defined('JAAS_PRIVATE_KEY') ? JAAS_PRIVATE_KEY : '';
        if ($privateKey === '' && defined('JAAS_PRIVATE_KEY_PATH') && is_file(JAAS_PRIVATE_KEY_PATH)) {
            $privateKey = file_get_contents(JAAS_PRIVATE_KEY_PATH);
        }

        if ($privateKey === '') {
            Logger::warning('JaaS private key missing — live sessions will use fallback mode.');
            return null;
        }

        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $keyId,
        ];

        $payload = [
            'aud' => 'jitsi',
            'iss' => 'chat',
            'sub' => $appId,
            'room' => $roomName,
            'iat' => $now,
            'nbf' => $now - 10,
            'exp' => $now + 7200,
            'context' => [
                'user' => [
                    'name' => $userName,
                    'email' => $userEmail,
                    'avatar' => $userAvatar,
                    'moderator' => $isModerator ? 'true' : 'false',
                ],
                'features' => [
                    'livestreaming' => 'false',
                    'recording' => 'false',
                    'transcription' => 'false',
                    'outbound-call' => 'false',
                ],
            ],
        ];

        $encodedHeader = self::b64(json_encode($header));
        $encodedPayload = self::b64(json_encode($payload));
        $signatureInput = $encodedHeader . '.' . $encodedPayload;

        $binarySignature = '';
        $ok = openssl_sign($signatureInput, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            Logger::error('JaaS signature generation failed: ' . openssl_error_string());
            return null;
        }

        return $signatureInput . '.' . self::b64($binarySignature);
    }

    private static function b64($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
