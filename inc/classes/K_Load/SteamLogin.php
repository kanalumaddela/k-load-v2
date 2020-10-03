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

namespace K_Load;

class SteamLogin extends \kanalumaddela\SteamLogin\SteamLogin
{
//    protected static $instance;

    protected static $forceInvalid = false;

//    public static function bind(SteamLogin $steamLogin)
//    {
//        static::$instance = $steamLogin;
//    }

//    public static function i()
//    {
//        return static::$instance;
//    }

    public static function validRequest()
    {
        if (static::$forceInvalid) {
            return false;
        }

        return parent::validRequest(); // TODO: Change the autogenerated stub
    }

    protected function validate()
    {
        if (static::$forceInvalid) {
            return false;
        }

        return parent::validate(); // TODO: Change the autogenerated stub
    }
}