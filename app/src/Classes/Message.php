<?php
namespace App\Classes;

class Message extends \Slim\Flash\Messages
{
    public function addMessage($key, $message)
    {
        // Create Array for this key
        if (!isset($this->storage[$this->storageKey][$key]))
        {
            $this->storage[$this->storageKey][$key] = [];
        }

        // Push onto the array
        $this->storage[$this->storageKey][$key] = $message;
    }

}
