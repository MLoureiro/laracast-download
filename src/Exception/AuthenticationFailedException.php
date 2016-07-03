<?php namespace Exception;

class AuthenticationFailedException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Authentication failed, please check credentials');
    }
}
