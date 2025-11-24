# Laravel Zebra Image Printer

A minimal Laravel package for printing PNG images to Zebra thermal printers using ZPL.

## Features

- Print PNG images directly to Zebra thermal printers
- Configurable margins, DPI, and darkness
- Full page width with automatic scaling
- Direct thermal and thermal transfer support
- Simple facade interface

## Requirements

- PHP 8.0+
- Laravel 9.x, 10.x, or 11.x
- ImageMagick (`convert` command)

## Installation

1. Install via Composer:

```bash
composer require abdullah-life/laravel-zebra-image-printer
```

2. Publish configuration:

```bash
php artisan vendor:publish --tag=zebra-printer-config
```

3. Configure your printer in `.env`:

```env
ZEBRA_PRINTER_IP=172.21.54.45
ZEBRA_PRINTER_PORT=9100
ZEBRA_PRINTER_DPI=203
ZEBRA_PRINTER_DIRECT_THERMAL=true
ZEBRA_PRINTER_DARKNESS=30
ZEBRA_PRINTER_PAGE_WIDTH=832
```

4. Install ImageMagick:

```bash
sudo apt-get install imagemagick
```

## Usage

### Using Facade

```php
use AbdullahLife\ZebraImagePrinter\Facades\ZebraPrinter;

// Print with 1cm margin
ZebraPrinter::print('/path/to/label.png', 1);

// Print with custom margin
ZebraPrinter::print('/path/to/label.png', 0.5);

// Check if printer is online
if (ZebraPrinter::isOnline()) {
    ZebraPrinter::print('/path/to/label.png');
}
```

### Using Dependency Injection

```php
use AbdullahLife\ZebraImagePrinter\ZebraPrinter;

class LabelController extends Controller
{
    public function print(ZebraPrinter $printer)
    {
        $printer->print(storage_path('labels/shipping-label.png'), 1);

        return response()->json(['status' => 'printed']);
    }
}
```

### Advanced Usage

```php
use AbdullahLife\ZebraImagePrinter\Facades\ZebraPrinter;

// Convert to ZPL without printing
$zpl = ZebraPrinter::convert('/path/to/label.png', 1);

// Send ZPL to different printer
ZebraPrinter::sendToPrinter($zpl, '192.168.1.100', 9100);

// Check specific printer
$isOnline = ZebraPrinter::isOnline('192.168.1.100', 9100);
```

## Configuration

Edit `config/zebra-printer.php`:

```php
return [
    'printer_ip' => env('ZEBRA_PRINTER_IP', '172.21.54.45'),
    'printer_port' => env('ZEBRA_PRINTER_PORT', 9100),
    'dpi' => env('ZEBRA_PRINTER_DPI', 203),
    'direct_thermal' => env('ZEBRA_PRINTER_DIRECT_THERMAL', true),
    'darkness' => env('ZEBRA_PRINTER_DARKNESS', 30),
    'page_width_dots' => env('ZEBRA_PRINTER_PAGE_WIDTH', 832),
];
```

## API Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `print()` | `string $imagePath, float $marginCm = 1` | `bool` | Print image with margin |
| `convert()` | `string $imagePath, float $marginCm = 0` | `string` | Convert PNG to ZPL |
| `sendToPrinter()` | `string $zpl, ?string $ip = null, ?int $port = null` | `bool` | Send ZPL to printer |
| `isOnline()` | `?string $ip = null, ?int $port = null` | `bool` | Check if printer is online |

## Margin Behavior

When you specify a margin (e.g., `1` cm):
- Image uses 100% of page width minus margins on both sides
- Height automatically scales to maintain aspect ratio
- Content starts from top with specified margin

Example with 1cm margin on 4" printer (203 DPI):
- Page width: 832 dots (4 inches)
- Margin: 79 dots (1cm) on each side
- Image width: 674 dots (832 - 158)
- Height: Scaled proportionally

## Troubleshooting

### Printer Not Printing

```php
// Check connection
if (!ZebraPrinter::isOnline()) {
    throw new Exception('Printer offline');
}
```

### ImageMagick Not Found

```bash
sudo apt-get update
sudo apt-get install imagemagick
```

### Blank Labels

- Verify printer mode (direct thermal vs thermal transfer)
- Check if ribbon is installed (for thermal transfer)
- Increase darkness in config

## License

MIT

## Author

Abdullah (@abdullah-life)
