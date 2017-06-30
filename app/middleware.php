<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);
$app->add(new \Slim\Middleware\Session([
    'name'        => 'scb_session',
    'autorefresh' => true,
    'lifetime'    => '1 hour',
]));
$app->add(function (  $request,   $response,   $next)
{
    $uri  = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/')
    {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if ($request->getMethod() == 'GET')
        {
            return $response->withRedirect((string) $uri, 301);
        }
        else
        {
            return $next($request->withUri($uri), $response);
        }
    }
  $this->view->getEnvironment()->addGlobal('auth_user', $this->auth->user());
  $this->view->getEnvironment()->addGlobal('loggedin', $this->auth->loggedin());

    return $next($request, $response);
});
 
$app->add(new RKA\Middleware\IpAddress(false, []));
