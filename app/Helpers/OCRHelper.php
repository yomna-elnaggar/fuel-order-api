<?php

namespace App\Helpers;

use App\Models\Company\Vehicle;
use App\Models\Ordering\OrderMedia;
use App\Services\SMS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class OCRHelper
{
    public static function readOdometerHelper(
        UploadedFile $image,
        string $orderId,
        int $typeId,
        string $field
    ) {
        try {
            // 1️⃣ Save image
            $directory = public_path('uploads/odometer');

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = time() . '_' . $image->hashName();
            $image->move($directory, $filename);

            $relativePath = 'uploads/odometer/' . $filename;
            $absolutePath = public_path($relativePath);

            // 2️⃣ Save media record
            OrderMedia::create([
                'order_id' => $orderId,
                'type_id'  => $typeId,
                $field     => $relativePath,
            ]);

            // 3️⃣ OCR
            $ocrText = self::extractTextWithOcrSpace($absolutePath);

            if (empty($ocrText)) {
                return null;
            }

            return self::extractBiggestNumber($ocrText) ?: null;
        } catch (\Exception $e) {
            Log::error("readOdometerHelper failed: " . $e->getMessage());
            return null;
        }
    }

    private static function extractTextWithOcrSpace($imagePath)
    {
        try {
            $apiKey = env('OCR_SPACE_KEY', 'P8981SALS9CVX');

            $response = Http::timeout(9)->asMultipart()
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post('https://apipro1.ocr.space/parse/image', [
                    'apikey' => $apiKey,
                    'language' => 'eng',
                    'OCREngine' => '2',
                    'isOverlayRequired' => 'false',
                ]);

            $data = $response->json();

            // Return parsed text or empty string
            return $data['ParsedResults'][0]['ParsedText'] ?? '';
        } catch (\Exception $e) {
            Log::error("OCR Space API failed: " . $e->getMessage());
            return '';
        }
    }

    private static function extractBiggestNumber($text)
    {
        if (!$text) {
            return null;
        }

        // Normalize the text
        $text = strtolower($text);
        $text = preg_replace('/[^\d\s.,]/', ' ', $text); // keep only digits, spaces, dot, comma
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Find all numbers with 4+ digits (typical odometer length)
        preg_match_all('/\d{4,}/', $text, $matches);

        if (empty($matches[0])) {
            return null;
        }

        // Convert to integer and get the max
        $numbers = array_map(function ($num) {
            return (int) str_replace([',', '.'], '', $num);
        }, $matches[0]);

        if (empty($numbers)) {
            return null;
        }

        // Sort descending and return the largest
        rsort($numbers);
        return $numbers[0];
    }
}
