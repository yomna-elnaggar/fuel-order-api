<?php

namespace App\Http\Controllers\Api\Ocr;

use App\Models\FuelPump;
use App\Models\Ordering\FuelOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class PumpOCRController extends Controller
{
    private function cleanOcrText($text)
    {
        $text = preg_replace('/[\r\n\f]+/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return trim($text);
    }

    private function extractTextWithOcrSpace($imagePath)
    {
        try {
            $apiKey = env('OCR_SPACE_KEY', 'P8981SALS9CVX');

            $response = Http::timeout(10000)
                ->asMultipart()
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post('https://apipro.ocr.space/parse/image', [
                    'apikey' => $apiKey,
                    'language' => 'eng',
                    'OCREngine' => '3',
                    'isOverlayRequired' => 'false',
                ]);

            $data = $response->json();
            return $data['ParsedResults'][0]['ParsedText'] ?? '';
        } catch (\Exception $e) {
            Log::error("OCR Space API failed: " . $e->getMessage());
            return '';
        }
    }

    private function extractPriceAndQuantityFromOcr($ocrText)
    {
        // Normalize commas to dots for decimals
        $ocrText = str_replace(',', '.', $ocrText);

        // Split by lines (top to bottom matters)
        $lines = preg_split("/\r\n|\n|\r/", $ocrText);

        $numbers = [];

        foreach ($lines as $line) {
            // Find numbers like: 109005, 109.005, 48.75
            preg_match_all('/\d+(?:\.\d+)?/', $line, $matches);

            foreach ($matches[0] as $num) {
                $num = trim($num);

                // Skip very small junk numbers (like 2, 5, 10, etc)
                if ((float)$num < 5) {
                    continue;
                }

                $numbers[] = $num;
            }
        }

        if (count($numbers) < 2) {
            return null;
        }

        // First = price, second = quantity (top → bottom)
        $rawPrice = $numbers[0];
        $rawQty   = $numbers[1];

        // If price has no decimal and is long, assume last 3 digits are decimals
        // Example: 109005 → 109.005
        if (!str_contains($rawPrice, '.') && strlen($rawPrice) >= 5) {
            $rawPrice = substr($rawPrice, 0, -3) . '.' . substr($rawPrice, -3);
        }

        $price = number_format((float)$rawPrice, 2, '.', '');
        $quantity = number_format((float)$rawQty, 2, '.', '');

        return [
            'price' => $price,
            'quantity' => $quantity
        ];
    }

    private function compareOrderWithOcr(FuelOrder $order, array $ocrResults)
    {
        $orderPrice = (float)$order->total_price;
        $tolerance = 1.00;

        $lastOcr = [
            'price' => null,
            'quantity' => null
        ];

        foreach ($ocrResults as $ocrText) {
            if (empty($ocrText)) {
                continue;
            }

            $extracted = $this->extractPriceAndQuantityFromOcr($ocrText);

            if (!$extracted) {
                continue;
            }

            $ocrPrice = (float)$extracted['price'];
            $ocrQty   = (float)$extracted['quantity'];

            // Save OCR values
            $lastOcr = [
                'price' => number_format($ocrPrice, 2, '.', ''),
                'quantity' => number_format($ocrQty, 2, '.', '')
            ];

            $priceDiff = abs($ocrPrice - $orderPrice);

            // MATCH ONLY ON PRICE
            if ($priceDiff <= $tolerance) {
                return [
                    'match' => true,
                    'ocr_price' => $lastOcr['price'],
                    'ocr_quantity' => $lastOcr['quantity'],
                    'price_difference' => number_format($priceDiff, 2, '.', '')
                ];
            }
        }

        // No match found, return last OCR values
        return [
            'match' => false,
            'ocr_price' => $lastOcr['price'],
            'ocr_quantity' => $lastOcr['quantity'],
            'price_difference' => $lastOcr['price'] !== null
                ? number_format(abs((float)$lastOcr['price'] - $orderPrice), 2, '.', '')
                : null
        ];
    }

    public function readPump(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,bmp,tiff|max:2048',
            'order_id' => 'required|integer|exists:fuel_orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = FuelOrder::findOrFail($request->order_id);

        $filename = time() . '_' . $request->file('image')->getClientOriginalName();
        $request->file('image')->move(public_path('uploads/orders/'), $filename);

        $absolutePath = public_path('uploads/orders/' . $filename);

        // ---------- OCR ----------
        $ocrRaw = $this->extractTextWithOcrSpace($absolutePath);
        $ocrClean = $this->cleanOcrText($ocrRaw);

        $ocrResults = [$ocrClean];

        // ---------- Compare ----------
        $result = $this->compareOrderWithOcr($order, $ocrResults);

        if ($result['ocr_price'] === null || $result['ocr_quantity'] === null) {
            FuelPump::updateOrCreate(
                ['fuel_order_id' => $order->id], 
                [
                    'price' => null,
                    'quantity' => null,
                    'image' => 'uploads/orders/' . $filename,
                ]
            );
            return response()->json([
                'success' => false,
                'message' => 'OCR failed to detect price and quantity',
                'order_price' => number_format((float)$order->total_price, 2, '.', ''),
                'order_quantity' => number_format((float)$order->quantity, 2, '.', ''),
                'ocr_raw_text' => $ocrRaw
            ], 422);
        }

        FuelPump::updateOrCreate(
            ['fuel_order_id' => $order->id], 
            [
                'price' => $result['ocr_price'],
                'quantity' => $result['ocr_quantity'],
                'image' => 'uploads/orders/' . $filename,
            ]
        );

        $order->pump_match_price = ($order->total_price == $result['ocr_price']);
        $order->save();

        return response()->json([
            'match' => $result['match'],
            'order_price' => number_format((float)$order->total_price, 2, '.', ''),
            'order_quantity' => number_format((float)$order->quantity, 2, '.', ''),
            'ocr_price' => $result['ocr_price'],
            'ocr_quantity' => $result['ocr_quantity'],
            'ocr_raw_text' => $ocrRaw,
        ]);
    }
}
