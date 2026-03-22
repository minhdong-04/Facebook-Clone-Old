<?php

/**
 * Bridge helper: PHP -> Node Socket server.
 *
 * Node side endpoint: POST http://127.0.0.1:3000/emit
 * Body: { token, event, room, payload }
 */

function fb_socket_emit(string $event, array $payload = [], string $room = 'feed'): bool
{
    // Default points to local socket server on the same VM.
    // If you reverse-proxy Socket.IO to 443, you can still keep this as localhost.
    $url = getenv('SOCKET_EMIT_URL') ?: 'http://127.0.0.1:3000/emit';
    $token = getenv('SOCKET_EMIT_TOKEN') ?: 'dev-token-change-me';

    $body = json_encode([
        'token' => $token,
        'event' => $event,
        'room' => $room,
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE);

    if ($body === false) {
        return false;
    }

    // Prefer cURL if available
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $resp !== false && $code >= 200 && $code < 300;
    }

    // Fallback
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 2,
        ],
    ]);

    $resp = @file_get_contents($url, false, $context);
    if ($resp === false) {
        return false;
    }

    // best-effort status code check
    if (!empty($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
                $code = (int)$m[1];
                return $code >= 200 && $code < 300;
            }
        }
    }

    return true;
}
