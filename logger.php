<?php

/**
 * @copyright  2015 CRS4
 * @author
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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