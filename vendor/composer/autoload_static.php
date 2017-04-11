<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9c738592123eb9b26c1415eb1ce02155
{
    public static $prefixLengthsPsr4 = array (
        'V' => 
        array (
            'VidalService\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'VidalService\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9c738592123eb9b26c1415eb1ce02155::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9c738592123eb9b26c1415eb1ce02155::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
