<?php
namespace App\Classes;

class Result
{
    private $error, $message, $data;

    public function __construct($error, $message="", $data = null)
    {
        $this->error   = $error;
        $this->message = $message;
        $this->data    = $data;
    }
    public static function  create($error, $message="", $data = null)
    {
         return new self($error, $message , $data );
    }
    public function isValid()
    {
        return !$this->error;
    }
    public function getMessages()
    {
        return $this->message;
    }
    public function getData($key=null)
    {
        if(is_null($key))
        {
            return $this->data;
        }
        elseif(is_array($this->data))
        {
            if(isset($this->data[$key]))
            {
                return $this->data[$key];
            }
            else
            {
                return null;
            }
        }
        else
        {
            return $this->data;
        }
     }
}
