<?php
// Routes
//ngrok http -host-header=rewrite deploy.tools2.com:80



$is_loggedin = function ($request, $response, $next)
{
    if (!$this->auth->loggedin())
    {
        $this->flash->addMessage('error', 'Please lgin');
        return $response->withRedirect($this->router->pathFor('login'));
    }
    return $next($request, $response);

};
$is_guest = function ($request, $response, $next)
{
    if ($this->auth->loggedin())
    {
        return $response->withRedirect($this->router->pathFor('home'));
    }
    return $next($request, $response);
};

$app->map(['GET', 'POST'], '/login', 'AuthController:login')
    ->setName('login')->add($is_guest);

$app->get('/logout', 'AuthController:logout')
    ->setName('logout');
$app->get('/', 'ProjectsController:listAll')
    ->setName('projects')->add($is_loggedin);

$app->get('/projects', 'ProjectsController:listAll')
    ->setName('project_list')->add($is_loggedin);

$app->map(['GET', 'POST'], '/project[/{id}]', 'ProjectsController:form')
    ->setName('project_form')->add($is_loggedin);
$app->map(['POST'], '/project_delete', 'ProjectsController:delete')
    ->setName('project_delete')->add($is_loggedin);

$app->map(['GET', 'POST'], '/projects/search', 'ProjectsController:search')
    ->setName('project_search')->add($is_loggedin);

$app->map(['GET', 'POST'], '/webhook', 'GitController:webhook')
    ->setName('webhook');

   $app->map(['GET', 'POST'],'/profile', 'AuthController:profile')
    ->setName('profile')->add($is_loggedin);
  $app->map(['GET', 'POST'],'/settings', 'SettingController:settings')
    ->setName('settings')->add($is_loggedin);