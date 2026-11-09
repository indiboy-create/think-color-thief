<?php

declare(strict_types=1);

namespace Youvision\ThinkColorThief\Facades;

use think\Facade;

/**
 * @method static mixed getColor($source, array $options = [])
 * @method static array getPalette($source, array $options = [])
 * @method static mixed getColorFromFile(string $file, array $options = [])
 * @method static array getPaletteFromFile(string $file, array $options = [])
 * @method static mixed getColorFromUrl(string $url, array $options = [])
 * @method static array getPaletteFromUrl(string $url, array $options = [])
 * @method static mixed getColorFromBinary(string $binary, array $options = [])
 * @method static array getPaletteFromBinary(string $binary, array $options = [])
 * @method static mixed getConfig(?string $key = null, $default = null)
 *
 * @see \Youvision\ThinkColorThief\ColorThief
 */
class ColorThief extends Facade
{
    protected static function getFacadeClass()
    {
        return \Youvision\ThinkColorThief\ColorThief::class;
    }
}