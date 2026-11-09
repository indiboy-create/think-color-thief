<?php

declare(strict_types=1);

namespace Youvision\ThinkColorThief\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkColorThief\ColorThief;
use Youvision\ThinkColorThief\ColorThiefService;
use Youvision\ThinkColorThief\Facades\ColorThief as ColorThiefFacade;

final class ColorThiefServiceTest extends TestCase
{
    public function testServiceRegistersSingletonAndMergesConfig(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-color-thief-app-' . uniqid();
        mkdir($root . DIRECTORY_SEPARATOR . 'config', 0777, true);

        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'color-thief.php',
            '<?php return ["quality" => 20, "format" => "rgb"];'
        );

        $app = new App($root);
        (new ColorThiefService($app))->register();

        $instance = $app->make(ColorThief::class);

        $this->assertInstanceOf(ColorThief::class, $instance);
        $this->assertSame($instance, $app->make('color-thief'));
        $this->assertSame(20, $instance->getConfig('quality'));
        $this->assertSame('rgb', $instance->getConfig('format'));
        $this->assertSame(8, $instance->getConfig('color_count'));
        $this->assertSame($instance, color_thief());
        $this->assertSame(20, ColorThiefFacade::getConfig('quality'));
    }
}