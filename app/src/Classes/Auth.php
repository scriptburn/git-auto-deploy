<?php
namespace App\Classes;

use JeremyKendall\Password\PasswordValidator;

class Auth
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }
    public function authenticate($username, $password)
    {
        try
        {
            $user = $this->container->db->select()->from("users")->where("username", "=", $username)->execute()->fetch();

            if ($user)
            {
                $validator = new PasswordValidator();
                if (!$validator->isValid($password, $user['password']))
                {
                    throw new \Exception("Invalid username or password");

                }
                 return new \App\Classes\Result(false,"",$user);

            }
            else
            {
                throw new \Exception("Invalid username or password");
            }
        }
        catch (\Exception $e)
        {
            return new \App\Classes\Result(true, $e->getMessage());
        }
    }

}
