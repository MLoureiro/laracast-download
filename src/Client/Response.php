<?php namespace Client;

class Response
{
    private $rawResponse;
    private $info;
    private $clientError;

    public function __construct($rawResponse, $info, $clientError ='')
    {
        $this->rawResponse = $rawResponse;
        $this->info = $info;
        $this->clientError = $clientError;
    }

    public function getStatusCode()
    {
        return $this->info['http_code'];
    }

    public function getContents()
    {
        // @todo remove connection headers
        return $this->rawResponse;
    }
}
