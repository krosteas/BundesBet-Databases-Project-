<?php
require_once "_db.php";

/*
  GET api/geo.php

  Response:
  {
    ip: "...",
    city: "...",
    region: "...",
    country: "...",
    lat: 53.1,
    lon: 8.85
  }
*/

function get_client_ip() {
    // Prefer X-Forwarded-For if behind proxy
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // First IP in the list
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

$ip = get_client_ip();
if (!$ip) {
    send_json(["error" => "Could not determine client IP"], 500);
}

// ---- Call ipinfo.io ----
// If you got a personal token from ipinfo, put it here; otherwise leave empty.
// (Free tier often works anonymously for a few calls/day.)
$token = "";  // e.g. "your_token_here" or "" for no token

$url = "https://ipinfo.io/{$ip}/json";
if ($token !== "") {
    $url .= "?token=" . urlencode($token);
}

$ctx = stream_context_create([
    "http" => [
        "timeout" => 4,
    ]
]);

$json = @file_get_contents($url, false, $ctx);
if ($json === false) {
    send_json([
        "ip"    => $ip,
        "error" => "Geo lookup failed"
    ], 500);
}

$data = json_decode($json, true);
$loc = isset($data["loc"]) ? $data["loc"] : null;
$lat = null;
$lon = null;
if ($loc && strpos($loc, ',') !== false) {
    [$latStr, $lonStr] = explode(',', $loc, 2);
    $lat = (float)$latStr;
    $lon = (float)$lonStr;
}

send_json([
    "ip"      => $ip,
    "city"    => $data["city"]    ?? null,
    "region"  => $data["region"]  ?? null,
    "country" => $data["country"] ?? null,
    "lat"     => $lat,
    "lon"     => $lon
]);
