<?php

namespace AbdullahLife\ZebraImagePrinter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool print(string $imagePath, float $marginCm = 1)
 * @method static string convert(string $imagePath, float $marginCm = 0)
 * @method static bool sendToPrinter(string $zpl, ?string $printerIp = null, ?int $printerPort = null)
 * @method static bool isOnline(?string $printerIp = null, ?int $printerPort = null)
 */
class ZebraPrinter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zebra-printer';
    }
}
