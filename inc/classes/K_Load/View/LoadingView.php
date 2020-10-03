<?php
/**
 * K-Load v2 (https://demo.maddela.org/k-load/).
 *
 * @link      https://www.maddela.org
 * @link      https://github.com/kanalumaddela/k-load-v2
 *
 * @author    kanalumaddela <git@maddela.org>
 * @copyright Copyright (c) 2018-2020 Maddela
 * @license   MIT
 */

namespace K_Load\View;

use K_Load\Facades\Config;
use function array_slice;
use function file_exists;
use function scandir;
use const K_Load\APP_ROOT;

class LoadingView extends View
{
    public static function getThemes(): array
    {
        $themePath = APP_ROOT.'/themes/';
        $list = array_slice(scandir(APP_ROOT.'/themes'), 2);
        $themes = [];

        foreach ($list as $theme) {
            if ($theme === '.template') {
                continue;
            }
            if (file_exists($themePath.$theme.'/pages/loading.twig')) {
                $themes[] = $theme;
            }
        }

        return $themes;
    }

    public static function themeExists(string $theme): bool
    {
        return parent::themeExists($theme) && file_exists(APP_ROOT.'/themes/'.$theme.'/pages/loading.twig');
    }

    public static function setDefaultPaths()
    {
        $theme = static::getTheme();

        static::$twigLoader->addPath(APP_ROOT.'/themes/'.$theme.'/pages');
        static::$twigLoader->addPath(APP_ROOT.'/themes/default/pages/loading', 'loading');
    }

    public static function getTheme(): string
    {
        if (empty(static::$theme)) {
            static::setTheme(Config::get('loading_theme', 'default'));
        }

        return static::$theme;
    }
}
// 008f68