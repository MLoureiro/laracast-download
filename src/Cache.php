<?php

class Cache
{
    private $filePath;
    private $isFileLoaded = false;
    private $data = [];
    private $extraTime = '+1 day';

    public function __construct()
    {
        $this->filePath = __DIR__ . '/.cache';

        $this->load();
    }

    public function has($key)
    {
        return $this->isCacheValid($this->fetch($key));
    }

    public function get($key)
    {
        $cache = $this->fetch($key);
        return array_key_exists('data', $cache)
            ? $cache['data']
            : false;
    }

    public function put($key, $data, $till = null)
    {
        if(null === $till) {
            $till = (new DateTime())->modify($this->extraTime)->getTimestamp();
        }
        $this->data[$key] = [
            'expirationDate' => $till,
            'data' => $data,
        ];

        $this->store();
    }

    private function fetch($key)
    {
        return array_key_exists($key, $this->data)
            ? $this->data[$key]
            : [];
    }

    private function isCacheValid(array $cache)
    {
        return time() < $cache['expirationDate'];
    }

    private function load()
    {
        if($this->isFileLoaded) {
            return;
        }

        if(!file_exists($this->filePath)) {
            touch($this->filePath);
        }

        $this->data = require $this->filePath;
        if(!is_array($this->data)) {
            $this->data = [];
        }
    }

    private function store()
    {
        file_put_contents($this->filePath, $this->buildCacheFile());
    }

    /**
     * @return string
     */
    private function buildCacheFile()
    {
        $data = var_export($this->data, true);
        return <<<PHP
<?php
return {$data};
PHP;

    }

}
