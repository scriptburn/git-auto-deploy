<?php
namespace App\Classes;

class Utils
{
    private $router, $logger, $request, $setting;

    public function __construct($router, $logger, $request, $setting)
    {
        $this->router  = $router;
        $this->logger  = $logger;
        $this->request = $request;
        $this->setting = $setting;

    }

    public function p_l($msg, $dump = false)
    {
        $bt = debug_backtrace();

        $caller1 = $bt[0];
        $caller2 = @$bt[1];

        $caller1['file'] = str_replace(__DIR__, "", $caller1['file']);
        $str             = microtime(true) . "-" . $caller1['file'] . "@" . @$caller2['function'] . "():$caller1[line]" . "-->";
        if ($dump !== 1)
        {
            error_log($str);
        }

        if ($dump === true)
        {
            ob_start();
            var_dump($msg);
            $rr = ob_get_clean();
        }
        elseif ($dump)
        {
            $this->logger->info($msg);
        }
        else
        {
            $rr = print_r($msg, 1);
            $this->logger->info($rr);
        }

    }

    public function urlFor($v, $d = [], $debug = false)
    {
        $v = trim($v);

        if (strtolower(substr($v, 0, 1)) == '/' || strtolower(substr($v, 0, 7)) == 'http://' || strtolower(substr($v, 0, 8)) == 'https://')
        {
            return $this->makeUrl($v, $d);
        }
        else
        {
            return $this->router->pathFor($v, $d);
        }
    }
    public function makeUrl($url, $args = [])
    {
        $query = [];

        $parsed_url = parse_url($url);
        if (isset($parsed_url['query']) && $parsed_url['query'])
        {
            parse_str($parsed_url['query'], $query);
        }
        $query = array_merge($query, $args);
        $url   = [];

        if (isset($parsed_url['scheme']))
        {
            $url[] = $parsed_url['scheme'] . ":/";
        }
        if (isset($parsed_url['host']))
        {
            $url[] = $parsed_url['host'];
        }
        if (isset($parsed_url['path']))
        {
            $url[] = rtrim(ltrim($parsed_url['path'], "/"), "");
        }
        $url = (empty($parsed_url['scheme']) && empty($parsed_url['host']) ? "/" : "") . implode("/", $url);
        if (count($query))
        {
            $url .= '?' . http_build_query($query);
        }
        if (isset($parsed_url['fragment']))
        {
            $url .= $parsed_url['fragment'];
        }
        return $url;
    }
    public function baseUrl()
    {
        return $this->request->getUri()->getBasePath();
    }
    public function currentUrl($withQueryString = true)
    {
        $uri = ($this->baseUrl() ? rtrim($this->baseUrl(), "/") . "/" : '') . $this->request->getUri()->getPath();

        if ($withQueryString)
        {

            if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'])
            {
                $uri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
        return $uri;
    }

    public function mail($to, $message, $options = [])
    {
        $default_options = ['html' => false];
        $options         = array_merge($default_options, is_array($options)?$options:[] );
        $mailSetting     = isset($options['mail']) && is_array($options['mail']) ? $options['mail'] : $this->setting->get(['send_method', 'smtp_host', 'smtp_user', 'smtp_password', 'smtp_port', 'smtp_enc']);
        if (@in_array(['mail', 'smtp'], @$mailSetting['send_method']))
        {
            throw new Exception("Invalid mail send method {$mailSetting['send_method']}");

        }

        if ($mailSetting['send_method'] == 'mail')
        {

            $headers = (@$message['from_email'] ? 'From: ' . $message['from_email'] . "\r\n" : '') .
                'Reply-To: ' . $to . "\r\n" .
                'X-Mailer: Git Auto Deploy';

            if (!mail($to, $message['subject'], $message['body'], $headers))
            {
                throw new \Exception("Message could not be sent.");
            }
            else
            {
                return true;
            }
        }
        elseif ($mailSetting['send_method'] == 'smtp')
        {
            if(empty($mailSetting['smtp_host']) || empty($mailSetting['smtp_user']) || empty($mailSetting['smtp_password']))
            {
                throw new \Exception("Invalid smtp details");
            }
            $mailSetting['smtp_port']=(int)$mailSetting['smtp_port']?$mailSetting['smtp_port']:587;
            $mail = new \PHPMailer;
            // $mail->SMTPDebug = 3;
            $mail->isSMTP(); // Set mailer to use SMTP
            $mail->Host       = $mailSetting['smtp_host'];
            $mail->SMTPAuth   = true; // Enable SMTP authentication
            $mail->Username   = $mailSetting['smtp_user'];
            $mail->Password   = $mailSetting['smtp_password'];
            $mail->SMTPSecure = $mailSetting['smtp_enc'];
            $mail->Port       = $mailSetting['smtp_port'];
            if (@$message['from_email'])
            {
                $mail->setFrom(@$message['from_email'], @$message['from_name']);
            }
            $mail->addReplyTo($to);

            $mail->addAddress($to); // Add a recipient


            $mail->Subject = @$message['subject'];
            if (!$options['html'])
            {
                $mail->ContentType = 'text/plain';
            }

            $mail->Body = @$message['body'];

            $mail->AltBody = @$message['body'];
            $mail->isHTML($options['html']);

            if (!$mail->send())
            {
                throw new \Exception('Mailer Error: ' . $mail->ErrorInfo);
            }
            else
            {
                return true;
            }
        }

    }

}
