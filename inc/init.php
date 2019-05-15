<?php
/**
 * K-Load v2 (https://demo.maddela.org/k-load/).
 *
 * @link      https://www.maddela.org
 * @link      https://github.com/kanalumaddela/k-load-v2
 *
 * @author    kanalumaddela <git@maddela.org>
 * @copyright Copyright (c) 2018-2019 Maddela
 * @license   MIT
 */

// important constants
define('IS_FORWARDED', isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']));
define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' : false));
define('APP_HOST', (IS_HTTPS ? 'https://' : 'http://').$_SERVER['HTTP_HOST']);

define('APP_DOMAIN', strtok($_SERVER['HTTP_HOST'], ':'));
define('APP_PORT', !IS_FORWARDED ? (int) $_SERVER['SERVER_PORT'] : 80);

$dir_self = dirname($_SERVER['PHP_SELF']);
$dir_request = dirname($_SERVER['REQUEST_URI']);

if ($dir_request == '\\' || $dir_request == '/') {
    $dir_request = '';
}
if ($dir_self == '\\' || $dir_self == '/') {
    $dir_self = '';
}

define('APP_PATH', str_replace('/index.php'.$dir_request, '', $dir_self));
define('APP_URL', APP_HOST.APP_PATH);
define('APP_URL_CURRENT', APP_HOST.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/***  E P I C  C O D E  ***/

// sessions
if (strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false || strpos($_SERVER['REQUEST_URI'], '/install') !== false) {
    session_name('K-Load');
    /*
        sessions on actual loading screen increase load times, dumb fix
    */
    session_set_cookie_params(0, APP_PATH.(strpos($_SERVER['REQUEST_URI'], '/install') !== false ? '/install' : '/dashboard'), APP_DOMAIN, IS_HTTPS, true);
    session_start();
}

// autoloading
function autoload_classes($class_name)
{
    $class_name = (strpos($class_name, '\\') !== false ? str_replace('\\', DIRECTORY_SEPARATOR, $class_name).'.php' : $class_name.'.class.php');

    $file = __DIR__.'/classes/'.$class_name;
    $file_mod = __DIR__.'/classes/mods/'.$class_name;

    if (file_exists($file_mod) || file_exists($file)) {
        $load_file = $file;
        if (file_exists($file_mod)) {
            $load_file = (!file_exists($file) ? $file_mod : $file);
            if (file_exists($file)) {
                $load_file = filemtime($file) < filemtime($file_mod) ? $file_mod : $file;
            }
        }
        require_once $load_file;
    }
}

spl_autoload_register('autoload_classes');
require_once APP_ROOT.'/vendor/autoload.php';

// make it easier to call classes
use J0sh0nat0r\SimpleCache\Drivers\File;
use J0sh0nat0r\SimpleCache\StaticFacade as Cache;
use K_Load\Constants;
use K_Load\Lang;
use K_Load\Routes;
use K_Load\User;
use K_Load\Util;
use kanalumaddela\SteamLogin\SteamLogin;
use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\Exception\HttpMethodNotAllowedException;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Symfony\Component\HttpFoundation\Response;

Constants::init();

// sentry meme
//Sentry\init(['dsn' => 'https://0bdc6629de78435f807c56358e3cdbae@sentry.io/1455550']);

// make some new directions
Util::mkDir(APP_ROOT.'/data/logs', true);
Util::mkDir(APP_ROOT.'/data/music');
Util::mkDir(APP_ROOT.'/data/users');

// steam auth and api stuff
$steamLogin_options = [
    'debug'   => DEBUG,
    'return'  => APP_URL_CURRENT,
    'method'  => 'xml',
    'session' => [
        'enable' => false,
    ],
];

$steamLogin = new SteamLogin($steamLogin_options);

if (SteamLogin::validRequest()) {
    $player = $steamLogin->getPlayer();
    Steam::Session($player->steamid);
}

// logging
if (ENABLE_LOG) {
    Util::log();
}

// caching
if (!ENABLE_CACHE || isset($_GET[CLEAR_CACHE])) {
    if (file_exists(APP_ROOT.'/data/cache')) {
        Util::rmDir(APP_ROOT.'/data/cache');
    }
}
$cache = new \J0sh0nat0r\SimpleCache\Cache(File::class, ['dir' => APP_ROOT.'/data/cache']);
Cache::bind($cache);

// get config if exists
if (file_exists(APP_ROOT.'/data/config.php')) {
    $config = include APP_ROOT.'/data/config.php';
    Steam::Key($config['apikeys']['steam']);
    Database::connect($config['mysql']);
}

// check if installed
if (!Util::installed() && strpos($_SERVER['REQUEST_URI'], '/test/') === false) {
    if (strpos($_SERVER['REQUEST_URI'], '/install') === false) {
        if (isset($_SESSION['id'])) {
            session_destroy();
        }

        if (substr(get_headers(APP_URL.'/install')[0], 9, 3) != 200) {
            echo '<h1>Your webserver isn\'t properly setup to use friendly urls e.g. \'/dashboard\'</h1>';
            echo '<a href="https://www.gmodstore.com/help/addon/5000/errors/404-not-found">https://www.gmodstore.com/help/addon/5000/errors/404-not-found</a>';
            die();
        }

        Util::redirect('/install');
    }
    include __DIR__.'/install.php';
    die();
}

if (isset($_SESSION['steamid']) && Util::installed()) {
    if (!User::validateCSRF($_SESSION['steamid'], User::getCSRF($_SESSION['steamid']))) {
        User::refreshCSRF($_SESSION['steamid']);
    }

    if (User::isSuper($_SESSION['steamid']) || ENABLE_REGISTRATION) {
        if (!isset($_SESSION['id'])) {
            $user = User::get($_SESSION['steamid']);

            if (empty($user)) {
                User::add($_SESSION['steamid']);
            }

            User::add($_SESSION['steamid']);
            User::session($_SESSION['steamid']);
        }
    } else {
        echo 'Registration is not allowed.';
        die();
    }
    if (strpos($_SERVER['REQUEST_URI'], 'api') === false) {
        if (User::isBanned($_SESSION['steamid'])) {
            echo "You're banned";
            die();
        }
    }

    $_SESSION = array_merge($_SESSION, User::get($_SESSION['steamid'], 'admin', 'perms'));
}

Lang::init(APP_LANGUAGE);

require_once __DIR__.'/helpers.php';

// routing
$routes = (ENABLE_CACHE ? Cache::remember('routes', 86400, [Routes::class, 'get']) : Routes::get());
$dispatcher = new Dispatcher($routes);

$exception = null;

try {
    $response = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    if ($response instanceof Response) {
        $response->send();
    } elseif (!empty($response)) {
        echo $response;
    }
} catch (HttpRouteNotFoundException $e) {
    if (!empty(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION))) {
        header('HTTP/1.0 404 Not Found');
        die();
    }
    $exception = $e;
} catch (Exception $e) {
    $exception = $e;
} finally {
    $headers_sent = headers_sent();

    if ($headers_sent) {
        die();
    }

    if ($exception instanceof Exception) {
        $sentry_id = Sentry\captureException($exception);

        $errorData = [
            'sentry_id' => Sentry\captureException($exception),
            'exception' => $exception,
        ];

        if ($exception instanceof HttpRouteNotFoundException) {
            $errorData['code'] = 404;
        }
        if ($exception instanceof HttpMethodNotAllowedException) {
            $errorData['code'] = 405;
        }

        require_once __DIR__.'/error.php';
    }
}
