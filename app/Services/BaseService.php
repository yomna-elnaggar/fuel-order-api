<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    protected $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    protected function get($endpoint, $params = [])
    {
        $url = "{$this->baseUrl}/{$endpoint}";
        try {
            $response = Http::timeout(60)->get($url, $params);
            
            Log::info("API GET Request: $url", ['params' => $params, 'status' => $response->status()]);
            Log::info("API GET Response Body: " . $response->body());

            if ($response->successful()) {
                $json = $response->json();
                if (isset($json['success']) && $json['success'] === false) {
                    Log::warning("API GET returned success:false for $url", ['body' => $response->body()]);
                    return null;
                }
                return $json['data'] ?? $json;
            }
            
            Log::error("API GET Request Failed to $url", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("API GET Exception for $url: " . $e->getMessage());
            return null;
        }
    }

    protected function post($endpoint, $data = [])
    {
        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/{$endpoint}", $data);
            
            if ($response->successful()) {
                $json = $response->json();
                
                // Extra check: if success is explicitly false, treat as failure
                if (isset($json['success']) && $json['success'] === false) {
                    return null;
                }
                
                return $json['data'] ?? $json;
            }
            return null;
        } catch (\Exception $e) {
            Log::error("API POST Call failed to {$this->baseUrl}/{$endpoint}: " . $e->getMessage());
            return null;
        }
    }
}
