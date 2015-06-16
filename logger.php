<?php

/**
 * Created by PhpStorm.
 * User: kikkomep
 * Date: 27/01/15
 * Time: 11:11
 */
class Logger
{

    private $name = null;

    function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return null
     */
    public function getName()
    {
        return $this->name;
    }

    public function debug($message)
    {
        error_log("$this->name: $message");
    }

    public function info($message)
    {
        error_log("$this->name: $message");
    }

    public function error($message)
    {
        error_log("$this->name: $message");
    }
}