<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite7fa63e8afd210a9f61eb51e25ac220c
{
    public static $prefixLengthsPsr4 = array (
        'B' => 
        array (
            'BeycanPress\\BinancePay\\' => 23,
            'BeycanPress\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'BeycanPress\\BinancePay\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
        'BeycanPress\\' => 
        array (
            0 => __DIR__ . '/..' . '/beycanpress/currency-converter/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite7fa63e8afd210a9f61eb51e25ac220c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite7fa63e8afd210a9f61eb51e25ac220c::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite7fa63e8afd210a9f61eb51e25ac220c::$classMap;

        }, null, ClassLoader::class);
    }
}
