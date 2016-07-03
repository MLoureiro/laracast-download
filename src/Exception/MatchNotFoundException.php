<?php namespace Exception;

class MatchNotFoundException extends \Exception
{
    public function __construct($what, $from)
    {
        parent::__construct("Could not find the '{$what}' in: '{$from}'");
    }
}
