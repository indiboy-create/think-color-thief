<?php

declare(strict_types=1);

use think\Container;
use Youvision\ThinkColorThief\ColorThief;

if (!function_exists('color_thief')) {
    /**
     * 获取颜色提取器实例。
     */
    function color_thief(?array $config = null): ColorThief
    {
        if ($config !== null) {
            return new ColorThief($config);
        }

        return Container::getInstance()->make(ColorThief::class);
    }
}