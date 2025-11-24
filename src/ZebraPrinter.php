<?php

namespace AbdullahLife\ZebraImagePrinter;

use Exception;

class ZebraPrinter
{
    private int $dpi;
    private bool $directThermal;
    private int $darkness;
    private int $pageWidthDots;
    private string $printerIp;
    private int $printerPort;

    public function __construct()
    {
        $this->dpi = config('zebra-printer.dpi', 203);
        $this->directThermal = config('zebra-printer.direct_thermal', true);
        $this->darkness = config('zebra-printer.darkness', 30);
        $this->pageWidthDots = config('zebra-printer.page_width_dots', 832);
        $this->printerIp = config('zebra-printer.printer_ip', '172.21.54.45');
        $this->printerPort = config('zebra-printer.printer_port', 9100);
    }

    /**
     * Print PNG image to Zebra printer
     */
    public function print(string $imagePath, float $marginCm = 1): bool
    {
        $zpl = $this->convert($imagePath, $marginCm);
        return $this->sendToPrinter($zpl);
    }

    /**
     * Convert PNG to ZPL format
     */
    public function convert(string $imagePath, float $marginCm = 0): string
    {
        $localPath = $imagePath;
        $isRemote = false;
        $tempDownload = null;

        // Check if it's a remote URL
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $isRemote = true;
            $tempDownload = sys_get_temp_dir() . '/zpl_download_' . date('YmdHis') . '_' . uniqid() . '.png';

            $imageData = @file_get_contents($imagePath);
            if ($imageData === false) {
                throw new Exception("Failed to download image from URL: $imagePath");
            }

            file_put_contents($tempDownload, $imageData);
            $localPath = $tempDownload;
        }

        if (!file_exists($localPath)) {
            throw new Exception("Image file not found: $imagePath");
        }

        $imageInfo = getimagesize($localPath);
        if ($imageInfo === false) {
            if ($tempDownload && file_exists($tempDownload)) {
                @unlink($tempDownload);
            }
            throw new Exception("Failed to read image: $imagePath");
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];

        $marginDots = (int)($marginCm * 10 * $this->dpi / 25.4);
        $targetWidth = $this->pageWidthDots - ($marginDots * 2);
        $targetHeight = (int)($targetWidth * ($originalHeight / $originalWidth));

        $timestamp = date('YmdHis') . '_' . uniqid();
        $tempDir = sys_get_temp_dir();
        $tempBmp = $tempDir . '/zpl_' . $timestamp . '.bmp';

        // Use ImageMagick to convert to 1-bit monochrome BMP
        $convertCmd = sprintf(
            'convert %s -resize %dx%d\! -colorspace Gray -dither FloydSteinberg -colors 2 -monochrome -type bilevel BMP3:%s 2>&1',
            escapeshellarg($localPath),
            $targetWidth,
            $targetHeight,
            escapeshellarg($tempBmp)
        );

        exec($convertCmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempBmp)) {
            @unlink($tempBmp);
            if ($tempDownload && file_exists($tempDownload)) {
                @unlink($tempDownload);
            }
            throw new Exception("Image conversion failed. Install ImageMagick: sudo apt-get install imagemagick");
        }

        $hexData = $this->bmpToHex($tempBmp, $targetWidth, $targetHeight);
        @unlink($tempBmp);

        // Cleanup downloaded file
        if ($tempDownload && file_exists($tempDownload)) {
            @unlink($tempDownload);
        }

        $bytesPerRow = (int)(($targetWidth + 7) / 8);
        $totalBytes = $bytesPerRow * $targetHeight;

        return $this->buildZpl($targetWidth, $targetHeight, $marginDots, $totalBytes, $bytesPerRow, $hexData);
    }

    /**
     * Convert BMP to hex for ZPL
     */
    private function bmpToHex(string $bmpPath, int $width, int $height): string
    {
        $data = file_get_contents($bmpPath);
        if ($data === false) {
            throw new Exception("Failed to read BMP file");
        }

        $headerSize = 54;
        $rowSize = (int)((($width + 31) / 32) * 4);
        $hexData = '';

        for ($y = $height - 1; $y >= 0; $y--) {
            $rowOffset = $headerSize + ($y * $rowSize);

            for ($x = 0; $x < $width; $x += 8) {
                $byte = 0;

                for ($bit = 0; $bit < 8; $bit++) {
                    if ($x + $bit < $width) {
                        $bytePos = $rowOffset + (int)(($x + $bit) / 8);
                        $bitPos = 7 - (($x + $bit) % 8);

                        if (isset($data[$bytePos])) {
                            $pixelByte = ord($data[$bytePos]);
                            $pixelBit = ($pixelByte >> $bitPos) & 1;

                            if ($pixelBit == 0) {
                                $byte |= (1 << (7 - $bit));
                            }
                        }
                    }
                }

                $hexData .= sprintf('%02X', $byte);
            }
        }

        return $hexData;
    }

    /**
     * Build ZPL command
     */
    private function buildZpl(int $width, int $height, int $marginDots, int $totalBytes, int $bytesPerRow, string $hexData): string
    {
        // Label length should include image height + both top and bottom margins
        $labelLength = $height + ($marginDots * 2);

        $zpl = "^XA\n";
        $zpl .= ($this->directThermal ? "^MTD\n" : "^MTT\n");
        $zpl .= "^MD{$this->darkness}\n";
        $zpl .= "^PW{$this->pageWidthDots}\n";
        $zpl .= "^LL{$labelLength}\n";
        $zpl .= "^FO{$marginDots},{$marginDots}\n";
        $zpl .= "^GFA,{$totalBytes},{$totalBytes},{$bytesPerRow},{$hexData}\n";
        $zpl .= "^FS\n";
        $zpl .= "^XZ\n";

        return $zpl;
    }

    /**
     * Send ZPL to printer
     */
    public function sendToPrinter(string $zpl, ?string $printerIp = null, ?int $printerPort = null): bool
    {
        $ip = $printerIp ?? $this->printerIp;
        $port = $printerPort ?? $this->printerPort;

        // Reset printer to ZPL mode first (separate connection)
        $resetSocket = @fsockopen($ip, $port, $errno, $errstr, 5);
        if ($resetSocket) {
            fwrite($resetSocket, "^XA^JUS^XZ\n");
            fclose($resetSocket);
            usleep(200000); // Wait 200ms for reset
        }

        // Now send the actual ZPL
        $socket = @fsockopen($ip, $port, $errno, $errstr, 5);

        if (!$socket) {
            throw new Exception("Failed to connect to printer at {$ip}:{$port} - {$errstr} ({$errno})");
        }

        $bytesWritten = fwrite($socket, $zpl);
        fclose($socket);

        return $bytesWritten !== false && $bytesWritten > 0;
    }

    /**
     * Check if printer is online
     */
    public function isOnline(?string $printerIp = null, ?int $printerPort = null): bool
    {
        $ip = $printerIp ?? $this->printerIp;
        $port = $printerPort ?? $this->printerPort;

        $socket = @fsockopen($ip, $port, $errno, $errstr, 2);

        if ($socket) {
            fclose($socket);
            return true;
        }

        return false;
    }
}
