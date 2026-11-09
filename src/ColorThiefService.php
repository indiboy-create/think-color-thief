<?php

declare(strict_types=1);

namespace Youvision\ThinkColorThief;

use think\Service;

class ColorThiefService extends Service
{
    /**
     * 注册颜色提取器单例，并加载项目中的 color-thief 配置。
     */
    public function register(): void
    {
        $defaults  = require dirname(__DIR__) . '/config/color-thief.php';
        $config    = [];
        $configFile = $this->app->getConfigPath() . 'color-thief' . $this->app->getConfigExt();

        if (is_file($configFile)) {
            $config = $this->app->config->load($configFile, 'color-thief');
        }

        $instance = new ColorThief(array_replace_recursive($defaults, $config));

        $this->app->instance(ColorThief::class, $instance);
        $this->app->instance('color-thief', $instance);
    }
}