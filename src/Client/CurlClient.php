<?php namespace Client;

class CurlClient
{
    /** @var bool */
    private $debug = false;

    /** @var array */
    private $defaultOptions = [
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => false,
        CURLOPT_TIMEOUT => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ];

    public function __construct(array $defaultOptions = [], $debug = false)
    {
        $this->defaultOptions = $defaultOptions + $this->defaultOptions;
        $this->debug = $debug;
    }

    public function get($url, array $options = [])
    {
        return $this->send(
            $this->buildCurlUrlOption($url) + $this->buildOptions($options)
        );
    }

    public function post($url, array $parameters, array $options = [])
    {
        return $this->send(
            $this->buildPostOptions($url, $parameters) + $this->buildOptions($options)
        );
    }

    public function useCookie($cookiePath)
    {
        $this->defaultOptions += [
            CURLOPT_COOKIEJAR => $cookiePath,
            CURLOPT_COOKIEFILE => $cookiePath,
        ];
    }

    private function buildCurlUrlOption($url)
    {
        return [CURLOPT_URL => $url];
    }

    private function buildPostOptions($url, array $parameters)
    {
        return $this->buildCurlUrlOption($url) + [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters),
        ];
    }

    private function buildOptions(array $optionList)
    {
        $curlOptions = $this->defaultOptions;

        if ($this->debug) {
            $curlOptions += [CURLOPT_VERBOSE => true];
        }

        foreach($optionList as $option => $value) {
            switch ($option) {
                case 'followRedirects':
                    $curlOptions[CURLOPT_FOLLOWLOCATION] = $value;
                    break;

                default:
                    throw new \RuntimeException("Option '{$option}' is not supported.");
            }
        }

        return $curlOptions;
    }

    /**
     * @param array $curlOptions
     *
     * @return Response
     */
    private function send(array $curlOptions)
    {
        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = new Response(
            curl_exec($ch),
            curl_getinfo($ch),
            curl_error($ch)
         );
        curl_close($ch);

        return $response;
    }
}
