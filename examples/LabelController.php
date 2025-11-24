<?php

namespace App\Http\Controllers;

use AbdullahLife\ZebraImagePrinter\Facades\ZebraPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Example Laravel controller for printing labels
 */
class LabelController extends Controller
{
    /**
     * Print a shipping label
     */
    public function printShippingLabel(Request $request): JsonResponse
    {
        $request->validate([
            'image_path' => 'required|string',
            'margin' => 'nullable|numeric|min:0|max:5'
        ]);

        $imagePath = $request->input('image_path');
        $margin = $request->input('margin', 1);

        try {
            // Check if printer is online
            if (!ZebraPrinter::isOnline()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer is offline'
                ], 503);
            }

            // Print the label
            ZebraPrinter::print($imagePath, $margin);

            return response()->json([
                'success' => true,
                'message' => 'Label printed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Printing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print multiple labels
     */
    public function printBatch(Request $request): JsonResponse
    {
        $request->validate([
            'labels' => 'required|array',
            'labels.*.image_path' => 'required|string',
            'margin' => 'nullable|numeric|min:0|max:5'
        ]);

        $labels = $request->input('labels');
        $margin = $request->input('margin', 1);
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($labels as $label) {
            try {
                ZebraPrinter::print($label['image_path'], $margin);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'file' => $label['image_path'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }

    /**
     * Generate ZPL preview
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'image_path' => 'required|string',
            'margin' => 'nullable|numeric|min:0|max:5'
        ]);

        $imagePath = $request->input('image_path');
        $margin = $request->input('margin', 1);

        try {
            // Convert to ZPL without printing
            $zpl = ZebraPrinter::convert($imagePath, $margin);

            return response()->json([
                'success' => true,
                'zpl' => $zpl,
                'size' => strlen($zpl)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check printer status
     */
    public function status(): JsonResponse
    {
        $isOnline = ZebraPrinter::isOnline();

        return response()->json([
            'online' => $isOnline,
            'ip' => config('zebra-printer.printer_ip'),
            'port' => config('zebra-printer.printer_port')
        ]);
    }
}
