<?php

namespace App\Http\Controllers\Api\Ocr;

use App\Helpers\Image;
use App\Helpers\OCRHelper;
use App\Models\Ordering\FuelOrder;
use App\Models\Ordering\OrderMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Company\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OCRController extends Controller
{
    private function cleanOcrText($text)
    {
        // Remove line breaks, form feeds, and multiple spaces
        $text = preg_replace('/[\r\n\f]+/', '', $text);
        $text = preg_replace('/\s+/', '', $text);
        return trim($text);
    }

    private function extractTextWithOcrSpace($imagePath)
    {
        try {
            $apiKey = env('OCR_SPACE_KEY', 'P8981SALS9CVX');

            $response = Http::timeout(8) // ⏳ wait up to 8 seconds
                ->asMultipart()
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post('https://apipro1.ocr.space/parse/image', [
                    'apikey' => $apiKey,
                    'language' => 'eng',
                    'OCREngine' => '2',
                    'isOverlayRequired' => 'false',
                ]);

            $data = $response->json();
            return $data['ParsedResults'][0]['ParsedText'] ?? '';
        } catch (\Exception $e) {
            Log::error("OCR Space API failed: " . $e->getMessage());
            return '';
        }
    }

    private function extractTextWithPlateRecognizer($imagePath)
    {
        try {
            $apiKey = '181397a420d861f94d1b32823a541ac4f993c069';

            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
            ])->asMultipart()
                ->attach('upload', file_get_contents($imagePath), basename($imagePath))
                ->post('https://api.platerecognizer.com/v1/plate-reader/');

            $data = $response->json();

            // Return the first detected plate text, or empty string if none
            return $data['results'][0]['plate'] ?? '';
        } catch (\Exception $e) {
            Log::error("Plate Recognizer API failed: " . $e->getMessage());
            return '';
        }
    }

    public function readPlate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,bmp,tiff|max:2048',
            'vehicle_id' => 'required|integer',
            'order_id' => 'required|integer|exists:fuel_orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = FuelOrder::find($request->order_id);
        
        // Mocking vehicle check - in microservice this would be an API call
        // $vehicle = Vehicle::findOrFail($request->vehicle_id);

        $relativePath = Image::uploadToPublic($request->file('image'), 'uploads/vehicles');
        $absolutePath = public_path($relativePath);

        $orderMedia = OrderMedia::firstOrCreate(['order_id' => $order->id]);
        $orderMedia->update([
            'vehicle_plate' => $relativePath,
            'type_id' => 1,
        ]);

        // ---------- Try OCR.Space first ----------
        $ocrSpaceFull = $this->cleanOcrText($this->extractTextWithOcrSpace($absolutePath));
        $plateText = !empty($ocrSpaceFull) ? $ocrSpaceFull : $this->extractTextWithPlateRecognizer($absolutePath);

        // $plateMatch = $this->comparePlateWithOcrResults($vehicle, [$plateText]);

        return response()->json([
            'plate_match' => true, // Placeholder logic
            'plate' => 'ABC-123', // Placeholder
            'image_path' => $relativePath,
            'original' => [
                'final_plate' => $plateText,
                'ocrSpace_full' => $ocrSpaceFull,
                'image_url' => asset($relativePath),
            ]
        ]);
    }
}
