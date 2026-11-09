<?php

// +----------------------------------------------------------------------
// | think-color-thief 配置文件
// | 可将本文件复制到项目 config/color-thief.php 后按需修改
// +----------------------------------------------------------------------

return [
    // 图片分析质量：每隔多少个像素采样一次。
    // 数值越小精度越高、速度越慢；数值越大速度越快、精度越低，推荐范围 5-20。
    'quality' => 10,

    // 调色板默认颜色数量，取值范围 2-256。
    'color_count' => 8,

    // 默认输出格式，支持 array、hex、rgb、int、obj。
    // array: [252, 4, 4]；hex: #fc0404；rgb: rgb(252, 4, 4)；
    // int: 16516100；obj: ColorThief\Color 对象。
    'format' => 'hex',

    // 图像适配器，null 表示自动选择。
    // 可选 gd、imagick、gmagick，需要 PHP 已安装对应扩展。
    'adapter' => null,

    // 是否开启颜色分析结果缓存。
    'cache' => false,

    // 缓存驱动名称，null 表示使用 config/cache.php 中的默认驱动。
    'cache_store' => null,

    // 缓存有效期（秒），null 表示永久有效。
    'cache_ttl' => 3600,

    // 缓存键前缀，用于避免与其他业务缓存冲突。
    'cache_prefix' => 'color_thief:',

    // 是否允许从 URL 读取远程图片。
    'url_enabled' => true,

    // 允许的 URL 协议，默认仅允许 HTTP 与 HTTPS。
    'url_schemes' => ['http', 'https'],

    // 允许访问的域名白名单，空数组表示不限制域名。
    // 支持 example.com 和 *.example.com 两种写法。
    'url_hosts' => [],

    // 禁止访问的域名黑名单，优先级高于白名单。
    // 支持 example.com 和 *.example.com 两种写法。
    'url_denied_hosts' => [],

    // 是否允许访问内网与保留地址。
    // 生产环境务必保持 false，避免 SSRF 风险。
    'url_allow_private' => false,

    // 远程图片连接超时时间（秒）。
    'url_timeout' => 10,

    // 远程图片最大字节数，0 表示不限制，默认 5 MB。
    'url_max_size' => 5242880,

    // 远程图片最大重定向次数。
    'url_max_redirects' => 3,
];