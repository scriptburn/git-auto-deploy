<?php
namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class SettingController
{
    private $setting, $session, $flash, $router, $view, $db;

    public function __construct($view, $setting, $flash, $utils, $db)
    {
        $this->view    = $view;
        $this->setting = $setting;
        $this->flash   = $flash;
        $this->utils   = $utils;
        $this->db      = $db;

        $this->send_methods     = ['mail' => 'PHP Mail', 'smtp' => 'SMTP'];
        $this->smtp_enc_methods = ['' => 'No Encryption', 'tls' => 'TLS', 'ssl' => 'SSL'];

    }

    public function settings(Request $request, Response $response, $args)
    {
        if ($request->isPost())
        {


            $params = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);

             try
            {
                $this->setting->set([
                    'send_method'   => @$params['send_method'],
                    'smtp_host'     => @$params['smtp_host'],
                    'smtp_user'     => @$params['smtp_user'],
                    'smtp_password' => @$params['smtp_password'],
                    'smtp_port'     => @$params['smtp_port'],
                    'smtp_enc'      => @$params['smtp_enc'],
                    'notify_deploy'=>@$params['notify_deploy'],
                ]);

                $this->flash->addMessage('success', 'Settings saved');
                return $response->withRedirect($this->utils->urlFor('settings'));
            }
             catch (\Exception $e)
            {
                $this->flash->addMessage('error', $e->getMessage());
                $this->flash->addMessage('form', $params);
                return $response->withRedirect($this->utils->urlFor('settings'));
            }
        }
        else
        {
            $params['form']             = $this->setting->get(['send_method', 'smtp_host', 'smtp_user', 'smtp_password', 'smtp_port', 'smtp_enc','notify_deploy']);
            $params['send_methods']     = $this->send_methods;
            $params['smtp_enc_methods'] = $this->smtp_enc_methods;

            $this->view->render($response, 'setting_form.twig', $params);
        }

    }

}
