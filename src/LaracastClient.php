<?php

use Client\CurlClient;
use Exception\AuthenticationFailedException;
use Client\Response;

class LaracastClient
{
    /** @var CurlClient */
    private $client;

    /** @var bool */
    private $isAuthenticated = null;

    /** @var array */
    private $settings = [
        'baseUrl' => 'https://laracasts.com/',
        'cookie' => '',
        'email' => null,
        'password' => null,
        'debug' => false,
    ];

    private $paths = [
        'privatePage' => '/settings/account',
        'loginForm' => '/login',
        'login' => '/sessions',
        'seriesList' => '/lessons',
        'seriesListPage' => '/lessons?page=%d',
        'seriesPage' => '/series/%s',
        'seriesEpisodePage' => '/series/%s/episodes/%d',

    ];

    /**
     * @param array $settings
     * @param CurlClient $client
     */
    public function __construct(array $settings = [], CurlClient $client)
    {
        $this->settings['cookie'] = __DIR__ . '/cookie.jar';
        $this->settings = $settings + $this->settings;
        $this->client = $client;

        // this is being required for the authentication process
        $this->addCookieToClient();
    }

    /**
     * @param bool $refresh
     *
     * @throws AuthenticationFailedException
     */
    public function authenticate($refresh = false)
    {
        if($this->isAuthenticated() && !$refresh) {
            return;
        }

        $extractor = $this->getLoginPage();
        $response = $this->client->post($this->buildUrl('login'), [
            'email' => $this->settings['email'],
            'password' => $this->settings['password'],
            '_token' => $extractor->getLoginToken(),
        ]);

        if($this->buildExtractor($response)->isAuthenticated()) {
            throw new AuthenticationFailedException();
        }
    }

    /**
     * @param $page
     *
     * @return LaracastResponseExtractor
     */
    public function getSeriesListPage($page)
    {
        return $this->buildExtractor(
            $this->client->get($this->buildUrl('seriesListPage', [$page]))
        );
    }

    /**
     * @param $series
     *
     * @return LaracastResponseExtractor
     */
    public function getSeriesPage($series)
    {
        return $this->buildExtractor(
            $this->client->get($this->buildUrl('seriesPage', [$series]))
        );
    }

    /**
     * @param string $series
     * @param int $episodeNumber
     *
     * @return LaracastResponseExtractor
     */
    public function getSeriesEpisodePage($series, $episodeNumber)
    {
        return $this->buildExtractor(
            $this->client->get($this->buildUrl('seriesEpisodePage', [$series, $episodeNumber]))
        );
    }

    /**
     * @return bool
     */
    private function isAuthenticated()
    {
        if($this->isAuthenticated === null) {
            $this->isAuthenticated = $this->checkIsAuthenticated();
        }

        return $this->isAuthenticated;
    }

    /**
     * @return bool
     */
    private function checkIsAuthenticated()
    {
        $response = $this->client->get(
            $this->buildUrl('privatePage'),
            ['followRedirects' => false]
        );
        return $response->getStatusCode() < 300;
    }

    /**
     * @return LaracastResponseExtractor
     */
    private function getLoginPage()
    {
        return $this->buildExtractor($this->client->get($this->buildUrl('loginForm')));
    }

    /**
     * @param string $type
     * @param array $parameters
     *
     * @return mixed
     */
    private function buildUrl($type, array $parameters = [])
    {
        if(!array_key_exists($type, $this->paths)) {
            $error = "Path type '%s' not found, only available: %s";
            throw new RuntimeException(sprintf($error, $type, implode(', ', array_keys($this->paths))));
        }

        $path = call_user_func_array('sprintf', array_merge([$this->paths[$type]], $parameters));
        return preg_replace('/([^\:])\/+/', '$1/', "{$this->settings['baseUrl']}/{$path}");
    }

    private function addCookieToClient()
    {
        $this->client->useCookie($this->settings['cookie']);
    }

    /**
     * @param \Client\Response $response
     *
     * @return LaracastResponseExtractor
     */
    private function buildExtractor(Response $response)
    {
        return new LaracastResponseExtractor($response);
    }
}
