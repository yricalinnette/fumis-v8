<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DTrackService
{
    // The shared secret provided by your teammate
    protected $secret = '@m0b1l3DTr@ckID';
    protected $baseUrl = 'http://192.168.2.211/mdtrackapi/index.php'; // Added index.php if it's CodeIgniter/PHP

    public function getDTrackStatus($dtrackNo)
    {
        try {
            $key = hash('sha256', $this->secret, true); 
            $iv = str_repeat("\0", 16); 
            $token = base64_encode(openssl_encrypt($this->secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));

            // Using the updated route provided
            $url = "http://192.168.2.211/mdtrackapi/index.php/specificOutBox/{$dtrackNo}";

            $response = Http::timeout(10)->post($url, ['token' => $token]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            \Log::error("DTrack Connection Failed: " . $e->getMessage());
            return null;
        }
    }
}