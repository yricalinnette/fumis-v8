<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DTrackService
{
    // The shared secret provided by your teammate
    protected $secret = '@m0b1l3DTr@ckID';
    protected $baseUrl = 'http://192.168.2.211/mdtrackapi/index.php'; 

    public function getDTrackStatus($dtrackNo, $timeout = 2) // Default to 2 seconds for reports
    {
        try {
            $key = hash('sha256', $this->secret, true); 
            $iv = str_repeat("\0", 16); 
            $token = base64_encode(openssl_encrypt($this->secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));

            $url = "http://192.168.2.211/mdtrackapi/index.php/specificOutBox/{$dtrackNo}";

            // Reduce timeout. 10s is way too long for a loop.
            $response = Http::timeout($timeout)->post($url, ['token' => $token]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            // Log minimal info to avoid bloating logs during downtime
            Log::warning("DTrack unreachable for {$dtrackNo}: " . $e->getMessage());
            return null;
        }
    }
}