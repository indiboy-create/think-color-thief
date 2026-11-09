<?php

declare(strict_types=1);

namespace Youvision\ThinkColorThief\Tests;

use ColorThief\Color;
use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkColorThief\ColorThief;
use Youvision\ThinkColorThief\Exception\ColorThiefException;

final class ColorThiefTest extends TestCase
{
    private string $image;

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-color-thief-tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $this->image = $directory . DIRECTORY_SEPARATOR . 'red.png';

        $image = imagecreatetruecolor(40, 40);
        $red   = imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 0, 0, 39, 39, $red);
        imagepng($image, $this->image);
        imagedestroy($image);
    }

    public function testGetColorFromFileReturnsHex(): void
    {
        $thief = new ColorThief();

        $color = $thief->getColor($this->image);

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        $this->assertSame('#fc0404', $color);
    }

    public function testGetColorSupportsArrayFormat(): void
    {
        $thief = new ColorThief();

        $color = $thief->getColorFromFile($this->image, ['format' => 'array', 'quality' => 1]);

        $this->assertIsArray($color);
        $this->assertCount(3, $color);
        $this->assertGreaterThan(240, $color[0]);
        $this->assertLessThan(10, $color[1]);
        $this->assertLessThan(10, $color[2]);
    }

    public function testGetColorSupportsRgbIntAndObjectFormats(): void
    {
        $thief = new ColorThief();

        $rgb = $thief->getColor($this->image, ['format' => 'rgb']);
        $int = $thief->getColor($this->image, ['format' => 'int']);
        $obj = $thief->getColor($this->image, ['format' => 'obj']);

        $this->assertMatchesRegularExpression('/^rgb\(\d+, \d+, \d+\)$/', $rgb);
        $this->assertIsInt($int);
        $this->assertInstanceOf(Color::class, $obj);
        $this->assertGreaterThan(240, $obj->getRed());
    }

    public function testGetPaletteReturnsRequestedColorCount(): void
    {
        $thief = new ColorThief();

        $palette = $thief->getPalette($this->image, ['color_count' => 5, 'quality' => 1]);

        $this->assertIsArray($palette);
        $this->assertNotEmpty($palette);
        $this->assertLessThanOrEqual(5, count($palette));
    }

    public function testGetColorFromBinary(): void
    {
        $thief = new ColorThief();

        $color = $thief->getColorFromBinary((string) file_get_contents($this->image));

        $this->assertSame('#fc0404', $color);
    }

    public function testGetColorFromArea(): void
    {
        $thief = new ColorThief();

        $color = $thief->getColorFromFile($this->image, [
            'area' => ['x' => 0, 'y' => 0, 'w' => 20, 'h' => 20],
        ]);

        $this->assertSame('#fc0404', $color);
    }

    public function testInvalidFileThrowsException(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getColorFromFile('/not/exists/image.png');
    }

    public function testInvalidBinaryThrowsException(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getColorFromBinary('not an image');
    }

    public function testInvalidQualityThrowsException(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getColor($this->image, ['quality' => 0]);
    }

    public function testInvalidColorCountThrowsException(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getPalette($this->image, ['color_count' => 1]);
    }

    public function testInvalidFormatThrowsException(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getColor($this->image, ['format' => 'cmyk']);
    }

    public function testPrivateNetworkUrlIsRejected(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getColorFromUrl('http://127.0.0.1/image.png');
    }

    public function testDisallowedSchemeIsRejected(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief())->getColorFromUrl('ftp://example.com/image.png');
    }

    public function testHostAllowListIsEnforced(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief(['url_hosts' => ['example.com']]))
            ->getColorFromUrl('https://other.com/image.png');
    }

    public function testDisabledUrlReadingIsRejected(): void
    {
        $this->expectException(ColorThiefException::class);

        (new ColorThief(['url_enabled' => false]))
            ->getColorFromUrl('https://example.com/image.png');
    }

    public function testCacheReturnsCachedResultWhenFileContentChanges(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-color-thief-cache-' . uniqid();
        mkdir($root . DIRECTORY_SEPARATOR . 'runtime', 0777, true);

        $app = new App($root);
        $app->config->set([
            'default' => 'file',
            'stores'  => [
                'file' => [
                    'type' => 'file',
                    'path' => $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR,
                ],
            ],
        ], 'cache');

        $image = $root . DIRECTORY_SEPARATOR . 'cover.jpg';
        $this->createJpeg($image, [255, 0, 0]);

        $mtime = filemtime($image);
        $size  = filesize($image);

        $thief = new ColorThief(['cache' => true, 'cache_ttl' => 3600]);
        $first = $thief->getColor($image);
        $this->assertNotNull($first);

        $this->createJpeg($image, [0, 0, 255]);
        touch($image, $mtime);
        clearstatcache(true, $image);

        $this->assertSame($size, filesize($image));
        $this->assertSame($mtime, filemtime($image));

        $this->assertSame($first, $thief->getColor($image));

        $fresh = (new ColorThief())->getColor($image);
        $this->assertNotSame($first, $fresh);
    }

    private function createJpeg(string $path, array $rgb): void
    {
        $image = imagecreatetruecolor(40, 40);
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($image, 0, 0, 39, 39, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
    }
}