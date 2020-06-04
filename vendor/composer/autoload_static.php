<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit0ac7cd5b2cda0031cee9c92b2dc93c50
{
    public static $files = array (
        '841780ea2e1d6545ea3a253239d59c05' => __DIR__ . '/..' . '/qiniu/php-sdk/src/Qiniu/functions.php',
        '8dafcc6956460bc297e00381fed53e11' => __DIR__ . '/..' . '/zoujingli/think-library/src/common.php',
    );

    public static $prefixLengthsPsr4 = array (
        't' => 
        array (
            'think\\composer\\' => 15,
        ),
        'l' => 
        array (
            'library\\' => 8,
        ),
        'W' => 
        array (
            'WePay\\' => 6,
            'WeOpen\\' => 7,
            'WeMini\\' => 7,
            'WeChat\\' => 7,
        ),
        'S' => 
        array (
            'Symfony\\Component\\OptionsResolver\\' => 34,
        ),
        'Q' => 
        array (
            'Qiniu\\' => 6,
        ),
        'O' => 
        array (
            'OSS\\' => 4,
        ),
        'E' => 
        array (
            'Endroid\\QrCode\\' => 15,
        ),
        'A' => 
        array (
            'AliPay\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'think\\composer\\' => 
        array (
            0 => __DIR__ . '/..' . '/topthink/think-installer/src',
        ),
        'library\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/think-library/src',
        ),
        'WePay\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/wechat-developer/WePay',
        ),
        'WeOpen\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/weopen-developer/WeOpen',
        ),
        'WeMini\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/wechat-developer/WeMini',
            1 => __DIR__ . '/..' . '/zoujingli/weopen-developer/WeMini',
        ),
        'WeChat\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/wechat-developer/WeChat',
            1 => __DIR__ . '/..' . '/zoujingli/weopen-developer/WeChat',
        ),
        'Symfony\\Component\\OptionsResolver\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/options-resolver',
        ),
        'Qiniu\\' => 
        array (
            0 => __DIR__ . '/..' . '/qiniu/php-sdk/src/Qiniu',
        ),
        'OSS\\' => 
        array (
            0 => __DIR__ . '/..' . '/aliyuncs/oss-sdk-php/src/OSS',
        ),
        'Endroid\\QrCode\\' => 
        array (
            0 => __DIR__ . '/..' . '/endroid/qr-code/src',
        ),
        'AliPay\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/wechat-developer/AliPay',
        ),
    );

    public static $classMap = array (
        'Ip2Region' => __DIR__ . '/..' . '/zoujingli/ip2region/Ip2Region.php',
        'We' => __DIR__ . '/..' . '/zoujingli/wechat-developer/We.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit0ac7cd5b2cda0031cee9c92b2dc93c50::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit0ac7cd5b2cda0031cee9c92b2dc93c50::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit0ac7cd5b2cda0031cee9c92b2dc93c50::$classMap;

        }, null, ClassLoader::class);
    }
}
