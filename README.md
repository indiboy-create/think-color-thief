## ThinkPHP-PanGu - 盘古ThinkPHP开源索引!
面向 ThinkPHP 的开源工程索引：组件、框架、服务与发布工具，按能力组织，持续整理。
仓库地址：**<https://github.com/indiboy-create/ThinkPHP-PanGu/>**
在线站点：**<https://indiboy-create.github.io/ThinkPHP-PanGu/>**

# think-color-thief

ThinkPHP 8 图片主色调提取扩展，基于 [ksubileau/color-thief-php](https://github.com/ksubileau/color-thief-php) 封装，支持从本地文件、远程 URL、二进制内容以及 GD/Imagick/Gmagick 对象提取主色调与调色板。

## 环境要求

- PHP >= 8.0
- ThinkPHP 8
- `ext-fileinfo`
- `ext-gd`、`ext-imagick`、`ext-gmagick` 至少安装一个

## 安装

```bash
composer require youvision/think-color-thief
```

安装完成后执行服务发现：

```bash
php think service:discover
```

服务类为 `Youvision\ThinkColorThief\ColorThiefService`，注册后容器中可使用 `color-thief` 标识或 `Youvision\ThinkColorThief\ColorThief` 类名获取单例。

## 配置

将默认配置复制到项目配置目录：

```bash
cp vendor/youvision/think-color-thief/config/color-thief.php config/color-thief.php
```

不复制时也会使用包内默认配置。

| 配置项 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `quality` | int | `10` | 每隔多少个像素采样一次，越小越精确，推荐 5-20 |
| `color_count` | int | `8` | 调色板颜色数量，范围 2-256 |
| `format` | string | `hex` | 输出格式：`array`、`hex`、`rgb`、`int`、`obj` |
| `adapter` | string/null | `null` | 图像适配器：`gd`、`imagick`、`gmagick`，`null` 自动选择 |
| `cache` | bool | `false` | 是否缓存分析结果 |
| `cache_store` | string/null | `null` | 缓存驱动名，`null` 使用框架默认驱动 |
| `cache_ttl` | int/null | `3600` | 缓存有效期（秒），`null` 永久有效 |
| `cache_prefix` | string | `color_thief:` | 缓存键前缀 |
| `url_enabled` | bool | `true` | 是否允许从 URL 读取图片 |
| `url_schemes` | array | `['http', 'https']` | 允许的 URL 协议 |
| `url_hosts` | array | `[]` | 域名白名单，空数组不限制，支持 `*.example.com` |
| `url_denied_hosts` | array | `[]` | 域名黑名单，优先级高于白名单 |
| `url_allow_private` | bool | `false` | 是否允许访问内网和保留地址，生产环境保持 `false` |
| `url_timeout` | int | `10` | 远程图片连接超时（秒） |
| `url_max_size` | int | `5242880` | 远程图片最大字节数，`0` 不限制 |
| `url_max_redirects` | int | `3` | 远程图片最大重定向次数 |

## 基本用法

### 门面

```php
use Youvision\ThinkColorThief\Facades\ColorThief;

$color = ColorThief::getColor('upload/cover.jpg');
$palette = ColorThief::getPalette('upload/cover.jpg', ['color_count' => 6]);
```

### 助手函数

```php
$color = color_thief()->getColor('upload/cover.jpg');

$palette = color_thief(['quality' => 5, 'format' => 'array'])
    ->getPalette('upload/cover.jpg', ['color_count' => 8]);
```

### 依赖注入

```php
use Youvision\ThinkColorThief\ColorThief;

class Cover
{
    public function __construct(protected ColorThief $colorThief)
    {
    }

    public function color(string $path)
    {
        return $this->colorThief->getColor($path);
    }
}
```

## 常用方法

```php
$thief = color_thief();

$thief->getColor($source, $options);
$thief->getPalette($source, $options);

$thief->getColorFromFile('upload/cover.jpg');
$thief->getPaletteFromFile('upload/cover.jpg');

$thief->getColorFromUrl('https://example.com/cover.jpg');
$thief->getPaletteFromUrl('https://example.com/cover.jpg');

$thief->getColorFromBinary($binary);
$thief->getPaletteFromBinary($binary);
```

`$source` 支持本地文件路径、HTTP/HTTPS 地址、二进制图片内容、`GdImage`、`Imagick`、`Gmagick` 对象。

### 调用参数

| 参数 | 类型 | 说明 |
| --- | --- | --- |
| `quality` | int | 采样间隔，覆盖配置中的 `quality` |
| `color_count` | int | 调色板颜色数量，仅 `getPalette` 使用 |
| `format` | string | 输出格式：`array`、`hex`、`rgb`、`int`、`obj` |
| `area` | array | 分析区域，支持 `x`、`y`、`w`、`h` |
| `adapter` | string | 指定图像适配器：`gd`、`imagick`、`gmagick` |

```php
$color = $thief->getColor('upload/cover.jpg', [
    'quality' => 5,
    'format'  => 'array',
    'area'    => ['x' => 0, 'y' => 0, 'w' => 200, 'h' => 200],
    'adapter' => 'gd',
]);
```

### 输出格式示例

```php
'array' => [252, 4, 4]
'hex'   => '#fc0404'
'rgb'   => 'rgb(252, 4, 4)'
'int'   => 16516100
'obj'   => ColorThief\Color 对象
```

## 缓存

开启缓存后会根据图片路径、文件大小、修改时间、分析参数自动生成缓存键；远程 URL 根据地址和分析参数生成缓存键。图片文件内容变化后缓存键会随之变化。

```php
// config/color-thief.php
'cache'        => true,
'cache_store'  => null,
'cache_ttl'    => 3600,
'cache_prefix' => 'color_thief:',
```

缓存读写异常时会自动降级为直接分析，不影响主流程。

## URL 安全

远程图片读取默认开启 SSRF 防护：

- 仅允许 `url_schemes` 中配置的协议
- 支持域名白名单、域名黑名单
- 默认拒绝内网与保留地址
- 限制下载大小、超时时间和重定向次数
- 重定向目标会重新执行安全校验
- 下载完成后校验内容是否为有效图片

如需读取局域网图片，可显式配置：

```php
'url_allow_private' => true,
```

## 测试

```bash
composer test
```

## License

MIT