<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePlaceholderIcon extends Command
{
    protected $signature = 'pwa:generate-icon';
    protected $description = 'Generate a placeholder PWA icon';

    public function handle()
    {
        $size = 512;
        $image = imagecreatetruecolor($size, $size);

        if (!$image) {
            $this->error('Failed to create image resource');
            return 1;
        }

        // Create gradient background (blue to lighter blue)
        for ($y = 0; $y < $size; $y++) {
            $ratio = $y / $size;
            $r = (int)(25 + (100 * $ratio));
            $g = (int)(118 + (80 * $ratio));
            $b = (int)(210 + (20 * $ratio));
            $color = imagecolorallocate($image, $r, $g, $b);
            imagefilledrectangle($image, 0, $y, $size, $y, $color);
        }

        // Add white circle background for the letter
        $white = imagecolorallocate($image, 255, 255, 255);
        $circleRadius = 180;
        $centerX = $size / 2;
        $centerY = $size / 2;
        imagefilledellipse($image, $centerX, $centerY, $circleRadius * 2, $circleRadius * 2, $white);

        // Add blue text 'F' for Fellowship
        $textColor = imagecolorallocate($image, 25, 118, 210);
        $fontSize = 200;
        $text = 'F';

        // Try to use a TrueType font if available
        $fontPath = 'C:/Windows/Fonts/arial.ttf';
        if (file_exists($fontPath)) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];
            $x = ($size - $textWidth) / 2;
            $y = ($size + $textHeight) / 2;
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);
        } else {
            // Fallback: use built-in font (much smaller)
            $font = 5; // Largest built-in font
            $textWidth = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);
            $x = ($size - $textWidth) / 2;
            $y = ($size - $textHeight) / 2;
            imagestring($image, $font, $x, $y, $text, $textColor);
        }

        // Create directory if it doesn't exist
        $directory = public_path('images');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Save the image
        $outputPath = public_path('images/app-icon-512.png');
        $success = imagepng($image, $outputPath);
        imagedestroy($image);

        if ($success) {
            $this->info('✓ Created app-icon-512.png successfully at: ' . $outputPath);

            // Also create a 192x192 version
            $this->info('Creating 192x192 version...');
            $sourceImage = imagecreatefrompng($outputPath);
            $smallImage = imagecreatetruecolor(192, 192);
            imagecopyresampled($smallImage, $sourceImage, 0, 0, 0, 0, 192, 192, 512, 512);
            $smallPath = public_path('images/app-icon-192.png');
            imagepng($smallImage, $smallPath);
            imagedestroy($sourceImage);
            imagedestroy($smallImage);
            $this->info('✓ Created app-icon-192.png successfully at: ' . $smallPath);

            return 0;
        } else {
            $this->error('Failed to save PNG image');
            return 1;
        }
    }
}
