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

        $pdo          = new \Slim\PDO\Database($dsn, $c->settings['db']['user'], $c->settings['db']['pass']);
        $user_table[] = "CREATE TABLE `users` (
  `id` bigint(10) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $user_table[] = "ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);";
        $user_table[] = " ALTER TABLE `users`
  MODIFY `id` bigint(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;";

        $projects_table[] = "CREATE TABLE `projects` (
  `id` bigint(20) NOT NULL,
  `type` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `branch` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `status` int(1) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `pre_hook` varchar(255) NOT NULL,
  `post_hook` varchar(255) NOT NULL,
  `email_result` varchar(255) NOT NULL,
  `uid` bigint(10) NOT NULL,
    `last_hook_status` int(1) NOT NULL,

  `last_hook_time` datetime NOT NULL,
  `last_hook_duration` int(5) NOT NULL,
  `last_hook_log` text NOT NULL,
    `composer_update` int(1) NOT NULL


) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $projects_table[] = "ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);";
        $projects_table[] = "ALTER TABLE `projects`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;";

        $settings_table[] = "
CREATE TABLE `settings` (
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  `expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $settings_table[] = "ALTER TABLE `settings`
  ADD PRIMARY KEY (`name`);";

        $table_exists = function ($table) use ($pdo)
        {
            try
            {

                $result = $pdo->query("select 1 from $table limit 1")->execute();

                return true;
            }
            catch (\Exception $e)
            {
                return false;
            }
        };
        $create_table = function ($table) use ($pdo)
        {

            $table = is_array($table) ? $table : [$table];
            foreach ($table as $tbl)
            {
                $pdo->query($tbl);

            }
        };
        if (!$table_exists('users'))
        {
            $create_table($user_table);

            $validator = new JeremyKendall\Password\PasswordValidator();
            $pdo->insert(['username', 'password', 'role'])
                ->into('users')
                ->values(['admin', $validator->rehash('admin'), 'admin'])
                ->execute(false);
        }
        if (!$table_exists('projects'))
        {
            $create_table($projects_table);
        }
        if (!$table_exists('settings'))
        {
            $create_table($settings_table);
        }
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
    return new App\Services\SettingService($c['db']);
};
