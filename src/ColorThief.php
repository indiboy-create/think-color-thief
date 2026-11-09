<?php

declare(strict_types=1);

namespace Youvision\ThinkColorThief;

use ColorThief\Color;
use ColorThief\ColorThief as BaseColorThief;
use think\facade\Cache;
use Youvision\ThinkColorThief\Exception\ColorThiefException;

/**
 * 图片主色调提取器，基于 ksubileau/color-thief-php 并适配 ThinkPHP 8。
 */
class ColorThief
{
    /**
     * 当前生效的配置。
     *
     * @var array
     */
    protected array $config;

    /**
     * @param array $config 配置参数，会与内置默认配置合并
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive($this->defaultConfig(), $config);
    }

    /**
     * 获取配置，传入 null 时返回全部配置。
     *
     * @param string|null $key
     * @param mixed       $default
     *
     * @return mixed
     */
    public function getConfig(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * 获取图片主色调。
     *
     * @param mixed $source 图片路径、URL、二进制内容、GdImage、Imagick 或 Gmagick 对象
     * @param array $options 可选参数：quality、format、area、adapter
     *
     * @return mixed
     */
    public function getColor($source, array $options = [])
    {
        $options  = $this->resolveOptions($options);
        $resolved = $this->resolveSource($source);
        $color    = $this->analyze($resolved, $options, false);

        return $color === null ? null : $this->formatColor($color, $options['format']);
    }

    /**
     * 获取图片调色板。
     *
     * @param mixed $source 图片路径、URL、二进制内容、GdImage、Imagick 或 Gmagick 对象
     * @param array $options 可选参数：color_count、quality、format、area、adapter
     *
     * @return array
     */
    public function getPalette($source, array $options = []): array
    {
        $options  = $this->resolveOptions($options);
        $resolved = $this->resolveSource($source);
        $palette  = $this->analyze($resolved, $options, true);

        return array_map(
            fn (array $color) => $this->formatColor($color, $options['format']),
            $palette ?? []
        );
    }

    /**
     * 从本地文件获取主色调。
     */
    public function getColorFromFile(string $file, array $options = [])
    {
        return $this->getColor($this->assertFile($file), $options);
    }

    /**
     * 从本地文件获取调色板。
     */
    public function getPaletteFromFile(string $file, array $options = []): array
    {
        return $this->getPalette($this->assertFile($file), $options);
    }

    /**
     * 从远程 URL 获取主色调。
     */
    public function getColorFromUrl(string $url, array $options = [])
    {
        return $this->getColor($this->assertUrl($url), $options);
    }

    /**
     * 从远程 URL 获取调色板。
     */
    public function getPaletteFromUrl(string $url, array $options = []): array
    {
        return $this->getPalette($this->assertUrl($url), $options);
    }

    /**
     * 从二进制图片内容获取主色调。
     */
    public function getColorFromBinary(string $binary, array $options = [])
    {
        return $this->getColor($this->assertBinary($binary), $options);
    }

    /**
     * 从二进制图片内容获取调色板。
     */
    public function getPaletteFromBinary(string $binary, array $options = []): array
    {
        return $this->getPalette($this->assertBinary($binary), $options);
    }

    /**
     * 内置默认配置。
     */
    protected function defaultConfig(): array
    {
        return [
            'quality'          => 10,
            'color_count'      => 8,
            'format'           => 'hex',
            'adapter'          => null,
            'cache'            => false,
            'cache_store'      => null,
            'cache_ttl'        => 3600,
            'cache_prefix'     => 'color_thief:',
            'url_enabled'      => true,
            'url_schemes'      => ['http', 'https'],
            'url_hosts'        => [],
            'url_denied_hosts' => [],
            'url_allow_private' => false,
            'url_timeout'      => 10.0,
            'url_max_size'     => 5242880,
            'url_max_redirects' => 3,
        ];
    }

    /**
     * 校验并合并调用参数。
     */
    protected function resolveOptions(array $options): array
    {
        $config = array_replace($this->config, $options);

        $quality = (int) $config['quality'];
        if ($quality < 1) {
            throw new ColorThiefException('quality 参数必须大于 0');
        }

        $colorCount = (int) $config['color_count'];
        if ($colorCount < 2 || $colorCount > 256) {
            throw new ColorThiefException('color_count 参数必须在 2 到 256 之间');
        }

        $format = strtolower((string) $config['format']);
        if (!in_array($format, ['array', 'hex', 'rgb', 'int', 'obj'], true)) {
            throw new ColorThiefException('format 参数只支持 array、hex、rgb、int、obj');
        }

        $adapter = $config['adapter'];
        if ($adapter === null || $adapter === '') {
            $adapter = null;
        } else {
            $adapter = ucfirst(strtolower((string) $adapter));
            if (!in_array($adapter, ['Gd', 'Imagick', 'Gmagick'], true)) {
                throw new ColorThiefException('adapter 参数只支持 gd、imagick、gmagick');
            }
        }

        $config['quality']     = $quality;
        $config['color_count'] = $colorCount;
        $config['format']      = $format;
        $config['adapter']     = $adapter;
        $config['area']        = $this->normalizeArea($config['area'] ?? null);

        return $config;
    }

    /**
     * 校验并整理区域参数。
     */
    protected function normalizeArea($area): ?array
    {
        if ($area === null || $area === []) {
            return null;
        }

        if (!is_array($area)) {
            throw new ColorThiefException('area 参数必须是数组');
        }

        $normalized = [];
        foreach (['x', 'y', 'w', 'h'] as $key) {
            if (!isset($area[$key])) {
                continue;
            }

            $value = (int) $area[$key];
            if ($value < 0) {
                throw new ColorThiefException("area.{$key} 不能小于 0");
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * 解析图片来源并生成缓存标识。
     *
     * @return array{source: mixed, key: string|null, loader: \Closure|null}
     */
    protected function resolveSource($source): array
    {
        if (is_string($source)) {
            if ($this->isUrl($source)) {
                $this->assertUrlAllowed($source);

                return [
                    'source' => null,
                    'key'    => 'url:' . md5($source),
                    'loader' => fn () => $this->fetchUrl($source),
                ];
            }

            if (is_file($source)) {
                $path = realpath($source) ?: $source;
                if (!is_readable($path)) {
                    throw new ColorThiefException("图片文件不可读：{$path}");
                }

                clearstatcache(true, $path);

                return [
                    'source' => $path,
                    'key'    => 'file:' . md5($path . ':' . filesize($path) . ':' . filemtime($path)),
                    'loader' => null,
                ];
            }

            if (@getimagesizefromstring($source) !== false) {
                return [
                    'source' => $source,
                    'key'    => 'binary:' . md5($source),
                    'loader' => null,
                ];
            }

            throw new ColorThiefException('图片源不存在、不可读或不是有效的图片内容');
        }

        if ($source instanceof \GdImage || is_a($source, 'Imagick') || is_a($source, 'Gmagick')) {
            return [
                'source' => $source,
                'key'    => null,
                'loader' => null,
            ];
        }

        throw new ColorThiefException('不支持的图片源类型');
    }

    /**
     * 调用底层 color-thief 执行颜色分析。
     */
    protected function analyze(array $resolved, array $options, bool $palette): mixed
    {
        $callback = function () use ($resolved, $options, $palette) {
            $source = $resolved['loader'] !== null ? ($resolved['loader'])() : $resolved['source'];

            try {
                if ($palette) {
                    return BaseColorThief::getPalette(
                        $source,
                        $options['color_count'],
                        $options['quality'],
                        $options['area'],
                        'array',
                        $options['adapter']
                    );
                }

                return BaseColorThief::getColor(
                    $source,
                    $options['quality'],
                    $options['area'],
                    'array',
                    $options['adapter']
                );
            } catch (ColorThiefException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new ColorThiefException('提取图片颜色失败：' . $e->getMessage(), 0, $e);
            }
        };

        if ($resolved['key'] === null) {
            return $callback();
        }

        $key = $resolved['key'] . ':' . ($palette ? 'palette:' . $options['color_count'] : 'color') . ':' . md5(serialize([
            $options['quality'],
            $options['area'],
            $options['adapter'],
        ]));

        return $this->remember($key, $callback);
    }

    /**
     * 使用 ThinkPHP 缓存保存分析结果，缓存不可用时直接执行回调。
     *
     * @return mixed
     */
    protected function remember(string $key, \Closure $callback)
    {
        if (empty($this->config['cache'])) {
            return $callback();
        }

        try {
            $store = Cache::store($this->config['cache_store'] ?: null);
        } catch (\Throwable $e) {
            return $callback();
        }

        $cacheKey = (string) $this->config['cache_prefix'] . $key;

        try {
            if ($store->has($cacheKey)) {
                $cached = $store->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            return $callback();
        }

        $result = $callback();

        try {
            $store->set($cacheKey, $result, $this->config['cache_ttl']);
        } catch (\Throwable $e) {
        }

        return $result;
    }

    /**
     * 按指定格式输出颜色。
     *
     * @param array $color [红, 绿, 蓝]
     *
     * @return mixed
     */
    protected function formatColor(array $color, string $format)
    {
        [$red, $green, $blue] = [(int) $color[0], (int) $color[1], (int) $color[2]];

        switch ($format) {
            case 'hex':
                return sprintf('#%02x%02x%02x', $red, $green, $blue);

            case 'rgb':
                return sprintf('rgb(%d, %d, %d)', $red, $green, $blue);

            case 'int':
                return ($red << 16) + ($green << 8) + $blue;

            case 'obj':
                return new Color($red, $green, $blue);

            default:
                return [$red, $green, $blue];
        }
    }

    /**
     * 判断字符串是否为 URL。
     */
    protected function isUrl(string $source): bool
    {
        return (bool) preg_match('#^[a-z][a-z0-9+.\-]*://#i', $source);
    }

    /**
     * 校验本地文件。
     */
    protected function assertFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new ColorThiefException("图片文件不存在或不可读：{$file}");
        }

        return $file;
    }

    /**
     * 校验 URL 合法性。
     */
    protected function assertUrl(string $url): string
    {
        if (!$this->isUrl($url)) {
            throw new ColorThiefException("无效的图片地址：{$url}");
        }

        $this->assertUrlAllowed($url);

        return $url;
    }

    /**
     * 校验二进制图片内容。
     */
    protected function assertBinary(string $binary): string
    {
        if ($binary === '' || @getimagesizefromstring($binary) === false) {
            throw new ColorThiefException('二进制内容不是有效的图片');
        }

        return $binary;
    }

    /**
     * URL 访问安全校验，防止 SSRF。
     */
    protected function assertUrlAllowed(string $url): void
    {
        if (!$this->config['url_enabled']) {
            throw new ColorThiefException('当前配置禁止从 URL 读取图片');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ColorThiefException("无效的图片地址：{$url}");
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new ColorThiefException("无效的图片地址：{$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, array_map('strtolower', (array) $this->config['url_schemes']), true)) {
            throw new ColorThiefException("不允许使用 {$scheme} 协议读取图片");
        }

        $host = strtolower(trim($parts['host'], '[]'));

        foreach ((array) $this->config['url_denied_hosts'] as $denied) {
            if ($this->hostMatches($host, (string) $denied)) {
                throw new ColorThiefException("禁止访问域名：{$host}");
            }
        }

        $allowedHosts = (array) $this->config['url_hosts'];
        if ($allowedHosts !== []) {
            $allowed = false;
            foreach ($allowedHosts as $item) {
                if ($this->hostMatches($host, (string) $item)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                throw new ColorThiefException("域名不在白名单中：{$host}");
            }
        }

        if (!$this->config['url_allow_private']) {
            foreach ($this->resolveHostIps($host) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    continue;
                }

                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new ColorThiefException("禁止访问内网或保留地址：{$host}");
                }
            }
        }
    }

    /**
     * 域名匹配，支持 *.example.com 通配形式。
     */
    protected function hostMatches(string $host, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));
        if ($pattern === '') {
            return false;
        }

        if ($pattern === $host) {
            return true;
        }

        if (str_starts_with($pattern, '*.')) {
            return str_ends_with($host, substr($pattern, 1));
        }

        return false;
    }

    /**
     * 解析域名对应的 IP 地址。
     */
    protected function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = @gethostbynamel($host);
        $ips = is_array($ips) ? $ips : [];

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * 下载远程图片，限制协议、域名、大小与重定向。
     */
    protected function fetchUrl(string $url): string
    {
        $current      = $url;
        $maxSize      = (int) $this->config['url_max_size'];
        $maxRedirects = max(0, (int) $this->config['url_max_redirects']);

        for ($redirect = 0; $redirect <= $maxRedirects; ++$redirect) {
            $this->assertUrlAllowed($current);

            $context = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'header'          => "User-Agent: Mozilla/5.0 (compatible; think-color-thief)\r\nAccept: image/*\r\n",
                    'timeout'         => (float) $this->config['url_timeout'],
                    'follow_location' => 0,
                    'ignore_errors'   => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $handle = @fopen($current, 'rb', false, $context);
            if ($handle === false) {
                throw new ColorThiefException("无法下载图片：{$current}");
            }

            $headers = $http_response_header ?? [];
            $content = $maxSize > 0
                ? stream_get_contents($handle, $maxSize + 1)
                : stream_get_contents($handle);
            fclose($handle);

            if (!is_string($content)) {
                throw new ColorThiefException("读取图片内容失败：{$current}");
            }

            if ($maxSize > 0 && strlen($content) > $maxSize) {
                throw new ColorThiefException("图片大小超过限制（{$maxSize} 字节）：{$current}");
            }

            [$status, $location, $contentLength] = $this->parseResponseHeaders($headers);

            if ($maxSize > 0 && $contentLength !== null && $contentLength > $maxSize) {
                throw new ColorThiefException("图片大小超过限制（{$maxSize} 字节）：{$current}");
            }

            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== null) {
                $current = $this->resolveRedirectUrl($current, $location);
                continue;
            }

            if ($status >= 400) {
                throw new ColorThiefException("下载图片失败，HTTP 状态码：{$status}");
            }

            if ($content === '' || @getimagesizefromstring($content) === false) {
                throw new ColorThiefException("URL 返回的内容不是有效图片：{$current}");
            }

            return $content;
        }

        throw new ColorThiefException('图片下载重定向次数过多');
    }

    /**
     * 从响应头中解析状态码、跳转地址和内容长度。
     *
     * @return array{0: int, 1: string|null, 2: int|null}
     */
    protected function parseResponseHeaders(array $headers): array
    {
        $status        = 0;
        $location      = null;
        $contentLength = null;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
                $status        = (int) $matches[1];
                $location      = null;
                $contentLength = null;
                continue;
            }

            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
                continue;
            }

            if (stripos($header, 'Content-Length:') === 0) {
                $contentLength = (int) trim(substr($header, 15));
            }
        }

        return [$status, $location, $contentLength];
    }

    /**
     * 处理相对地址跳转。
     */
    protected function resolveRedirectUrl(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $location)) {
            return $location;
        }

        $parts  = parse_url($base) ?: [];
        $scheme = $parts['scheme'] ?? 'http';
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $directory = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $scheme . '://' . $host . $port . $directory . '/' . $location;
    }
}