<?php
// DIC configuration
$container = $app->getContainer();

// -----------------------------------------------------------------------------
// Service providers
// -----------------------------------------------------------------------------

// Util function
$container['utils'] = function ($c)
{
    return new App\Classes\Utils($c['router'], $c['logger'], $c['request'], $c['setting']);
};

// Twig
$container['view'] = function ($c)
{
    $settings = $c->get('settings');
    $view     = new Slim\Views\Twig($settings['view']['template_path'], $settings['view']['twig']);

    // Add extensions
    $view->addExtension(new Slim\Views\TwigExtension($c->get('router'), $c->get('request')->getUri()));
    $view->addExtension(new Twig_Extension_Debug());
    $view->addExtension(new Knlv\Slim\Views\TwigMessages(
        $c['flash']
    ));

    $function = new Twig_SimpleFunction('var_dump', function ($v)
    {
        echo ("<pre>");
        var_dump($v);
        echo ("</pre>");
    });
    $view->getEnvironment()->addFunction($function);

    $function = new Twig_SimpleFunction('urlFor', function ($v, $d = [], $debug = false) use ($c)
    {

        return $c['utils']->urlFor($v, $d, $debug);
    });
    $view->getEnvironment()->addFunction($function);

    return $view;
};

// Flash messages
$container['flash'] = function ($c)
{
    return new Slim\Flash\Messages;
};

// -----------------------------------------------------------------------------
// Service factories
// -----------------------------------------------------------------------------

// monolog
$container['logger'] = function ($c)
{
    $settings = $c->get('settings');
    $logger   = new Monolog\Logger($settings['logger']['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['logger']['path'], Monolog\Logger::DEBUG));
    return $logger;
};

// -----------------------------------------------------------------------------
// Action factories
// -----------------------------------------------------------------------------

$container['HomeController'] = function ($c)
{
    return new App\Controllers\HomeController($c->get('view'), $c->get('logger'));
};
$container['AuthController'] = function ($c)
{
    return new App\Controllers\AuthController($c['view'], $c['auth'], $c['session'], $c['flash'], $c['router']);
};
$container['SettingController'] = function ($c)
{
    return new App\Controllers\SettingController($c['view'], $c['setting'], $c['flash'], $c['utils'], $c['db']);
};
$container['ProjectsController'] = function ($c)
{
    return new App\Controllers\ProjectsController($c['view'], $c['db'], $c['flash'], $c['router'], $c['paging'], $c['session'], $c['utils'], $c['auth']);
};
$container['GitController'] = function ($c)
{

    return new App\Controllers\GitController($c['db'], $c['utils'], $c['logger'], $c['git']);
};
$container['auth'] = function ($c)
{
    return new App\Services\AuthService($c['db'], $c['session']);
};
$container['paging'] = function ($c)
{
    return new App\Classes\Pagination($c['view'], $c['request'], $c['utils'], 5);
};
$container['git'] = function ($c)
{
    return new App\Services\RepoService($c['db'], $c['logger'], $c['setting'], $c['auth'], $c['utils'], $c->settings['binpaths'], [$c['github'], $c['bitbucket'], $c['gitlab']]);
};
$container['github'] = function ($c)
{
    return new \App\Services\GitHubService();
};
$container['bitbucket'] = function ($c)
{
    return new \App\Services\BitBucketService();
};
$container['gitlab'] = function ($c)
{
    return new \App\Services\GitLabService();
};

//PDO

$container['db'] = function ($c)
{
    try
    {

        $dsn = "mysql:host={$c->settings['db']['host']};dbname={$c->settings['db']['name']};charset=utf8";
        $pdo = new \Slim\PDO\Database($dsn, $c->settings['db']['user'], $c->settings['db']['pass'], array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="TRADITIONAL"'));

        return $pdo;

    }
    catch (\Exception $e)
    {
        die("Unable to connect to db:" . $e->getMessage());
    }
};

// Register globally to app
$container['session'] = function ($c)
{
    return new \SlimSession\Helper;
};
$container['flash'] = function ()
{
    return new \App\Classes\Message();
};
$container['setting'] = function ($c)
{
    return new \Scriptburn\Setting\Setting($c['db']);
};
