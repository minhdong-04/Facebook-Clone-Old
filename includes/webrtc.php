<?php

// WebRTC ICE config helper.
// Supports:
// - STUN (default: Google)
// - TURN with static username/password OR TURN REST (coturn static-auth-secret)

function fb_env(string $key, string $default = ''): string
{
  $v = getenv($key);
  if ($v === false || $v === null) return $default;
  $v = trim((string)$v);
  return $v !== '' ? $v : $default;
}

function fb_parse_urls(string $raw): array
{
  $raw = trim($raw);
  if ($raw === '') return [];
  $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
  $out = [];
  foreach ($parts as $p) {
    $p = trim((string)$p);
    if ($p !== '') $out[] = $p;
  }
  return $out;
}

function fb_turn_rest_credential(string $secret, string $username): string
{
  // TURN REST: credential = base64(HMAC-SHA1(secret, username))
  $hmac = hash_hmac('sha1', $username, $secret, true);
  return base64_encode($hmac);
}

function fb_webrtc_ice_servers(int $userId): array
{
  $iceServers = [];

  $stunUrls = fb_parse_urls(fb_env('WEBRTC_STUN_URLS', 'stun:stun.l.google.com:19302'));
  if (!empty($stunUrls)) {
    $iceServers[] = ['urls' => (count($stunUrls) === 1 ? $stunUrls[0] : $stunUrls)];
  }

  $turnUrls = fb_parse_urls(fb_env('WEBRTC_TURN_URLS', ''));
  if (!empty($turnUrls)) {
    $turnSecret = fb_env('WEBRTC_TURN_SECRET', '');
    $turnUsername = fb_env('WEBRTC_TURN_USERNAME', '');
    $turnCredential = fb_env('WEBRTC_TURN_CREDENTIAL', '');
    $turnTtl = (int)fb_env('WEBRTC_TURN_TTL', '3600');
    if ($turnTtl <= 0) $turnTtl = 3600;

    // Prefer TURN REST (ephemeral) if secret is provided.
    if ($turnSecret !== '') {
      $exp = time() + $turnTtl;
      $username = $exp . ':' . $userId;
      $credential = fb_turn_rest_credential($turnSecret, $username);
      $iceServers[] = [
        'urls' => (count($turnUrls) === 1 ? $turnUrls[0] : $turnUrls),
        'username' => $username,
        'credential' => $credential
      ];
    } elseif ($turnUsername !== '' && $turnCredential !== '') {
      $iceServers[] = [
        'urls' => (count($turnUrls) === 1 ? $turnUrls[0] : $turnUrls),
        'username' => $turnUsername,
        'credential' => $turnCredential
      ];
    }
  }

  return $iceServers;
}
