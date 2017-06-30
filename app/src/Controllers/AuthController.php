<?php
namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class AuthController
{
    private $auth, $session, $flash, $router, $view;

    public function __construct($view, $auth, $session, $flash, $router)
    {
        $this->view    = $view;
        $this->auth    = $auth;
        $this->session = $session;
        $this->flash   = $flash;
        $this->router  = $router;

    }

    public function login(Request $request, Response $response, $args)
    {

        if ($request->isPost())
        {
            $params = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);

            $result = $this->auth->authenticate(@$params['username'], @$params['password']);

            if ($result->isValid())
            {
                $this->session->set('admin_loggedin', $result->getData('id'));
                return $response->withRedirect($this->router->pathFor('projects'));
            }
            else
            {
                $messages = $result->getMessages();
                $this->flash->addMessage('error', $messages);
                $this->flash->addMessage('form', ['username' => @$params['username'], 'password' => @$params['password']]);
                return $response->withRedirect($this->router->pathFor('login'));
            }
        }
        else
        {
            $this->view->render($response, 'login.twig');
        }

    }
    public function logout(Request $request, Response $response, $args)
    {
        $this->session->delete('admin_loggedin');
        return $response->withRedirect($this->router->pathFor('login'));

    }
    public function profile(Request $request, Response $response, $args)
    {

        if ($request->isPost())
        {
            $params = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);
            try
            {
                $this->auth->setEmail($params['email'],$this->auth->user('id'));
                if (!empty($params['password1']) || !empty($params['password2']))
                {
                    $this->auth->changePassword(@$params['current_password'], @$params['password1'], @$params['password2']);
                }
                $this->flash->addMessage('success', 'Profile updated');
                return $response->withRedirect($this->router->pathFor('profile'));

            }
            catch (\Exception $e)
            {
                $this->flash->addMessage('error', $e->getMessage());
                $this->flash->addMessage('form', ['password' => @$params['password']]);
                return $response->withRedirect($this->router->pathFor('profile'));
            }
        }
        else
        {
            $params['form']['email']=$this->auth->user('email');

            $this->view->render($response, 'profile_form.twig',$params);
        }

    }
}
