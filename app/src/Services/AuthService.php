<?php
namespace App\Services;

use JeremyKendall\Password\PasswordValidator;

class AuthService
{
    private $db, $session, $user;

    public function __construct($db, $session)
    {
        $this->db      = $db;
        $this->session = $session;

    }
    public function authenticate($username, $password)
    {
        try
        {
            $user = $this->db->select()->from("users")->where("username", "=", $username)->execute()->fetch();

            if ($user)
            {
                $validator = new PasswordValidator();
                $result    = $validator->isValid($password, $user['password']);

                if (!$result->isValid())
                {
                    throw new \Exception("Invalid username or password");

                }
                return new \App\Classes\Result(false, "", $user);

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

    public function user($key = null)
    {
        if (!($user_id = $this->loggedin()))
        {
            return null;
        }
        if (!$this->user)
        {
            $this->user = $this->db->select()->from("users")->where("id", "=", $user_id)->execute()->fetch();
        }
        return !is_null($key) ? (isset($this->user[$key]) ? $this->user[$key] : null) : $this->user;
    }
    public function loggedin()
    {
        return $this->session->exists('admin_loggedin') && $this->session->get('admin_loggedin') ? $this->session->get('admin_loggedin') : false;
    }
    public function changePassword($oldPassword, $newpassword1, $newpassword2)
    {
        $validator = new PasswordValidator();

        if ($newpassword1 !== $newpassword2)
        {
            throw new \Exception("Password and confirm password does not match");

        }
        elseif (!$validator->isValid($oldPassword, $this->user('password')))
        {
            throw new \Exception("Invalid old password");
        }
        else
        {
            $this->setPassword($newpassword1);
        }

    }
    public function setPassword($password)
    {

        $validator = new PasswordValidator();
        $fields    = ['password' => $validator->rehash($password)];
        $this->db->update($fields)
            ->table('users')
            ->set($fields)
            ->where('id', '=', $this->user('id'))
            ->execute(false);

    }
    public function setEmail($email, $uid)
    {

        $fields = ['email' => $email];
        $user   = $this->db
            ->select()
            ->from('users')
            ->where('email', '=', $email)
            ->where('id', '<>', $uid)
            ->limit(1, 0)
            ->execute()
            ->fetch();
        if ($user)
        {
            throw new \Exception("Email address already in use");
        }
        $this->db->update($fields)
            ->table('users')
            ->set($fields)
            ->where('id', '=', $uid)
            ->execute(false);

    }
    public function is_admin($uid = 0)
    {
        if (!$uid)
        {
            return $this->user('role') === 'admin';
        }
        else
        {
            $user = $this->db
                ->select()
                ->from('users')
                ->where('id', '=', $uid)
                ->execute()
                ->fetch();
            return $user && $user['role'] == 'admin';
        }
    }

}
