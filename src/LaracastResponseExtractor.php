<?php

use Exception\MatchNotFoundException;
use Client\Response;

class LaracastResponseExtractor
{
    /** @var Response */
    private $response;

    /** @var array */
    private $extractorTypes = [
        'loginForm' => [
            'pattern' => '/(?P<form><form .*?action="\/sessions".*?>.+<\/form>)/smi',
            'name' => 'form',
        ],
        'loginTokenInput' => [
            'pattern' => '/(?P<input><input.*?name="_token".*?>)/',
            'name' => 'input',
        ],
        'loginToken' => [
            'pattern' => '/value="(?P<value>[^"]+)"/',
            'name' => 'value',
        ],
        'lessonPages' => [
            'pattern' => '/\/lessons\?page=(?P<pageNumbers>\d+)/',
            'name' => 'pageNumbers',
            'multiple' => true
        ],
        'authenticated' => [
            'pattern' =>  '/(?p<text>my laracasts)/i',
            'name' => 'text',
        ],
        'seriesList' => [
            'pattern' => '/\/series\/(?P<name>[^"\/\.]+)/',
            'name' => 'name',
            'multiple' => true,
        ],
        'seriesEpisodeNumbers' => [
            'pattern' => '/\/episodes\/(?P<episodeNumber>\d+)/',
            'name' => 'episodeNumber',
            'multiple' => true,
        ],
        'episodeTitle' => [
            'pattern' => '/<title>(?P<name>.+?)<\/title>/',
            'name' => 'name',
        ],
        'vimeoUrl' => [
            'pattern' => '/(?P<url>player\.vimeo\.com[^"\']+\.hd.mp4\?[^"\']+)/',
            'name' => 'url',
        ],
    ];

    /**
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function isAuthenticated()
    {
        try {
            return (bool) $this->extract('authenticated');
        }
        catch (MatchNotFoundException $e) {
            return false;
        }
    }

    /**
     * @return string
     *
     * @throws MatchNotFoundException
     */
    public function getLoginToken()
    {
        $form = $this->extract('loginForm');
        $input = $this->extract('loginTokenInput', $form);
        return $this->extract('loginToken', $input);
    }

    /**
     * @return int
     */
    public function getHighestPage()
    {
        $pages = $this->extract('lessonPages');
        sort($pages);
        return (int) array_pop($pages);
    }

    /**
     * @return array
     */
    public function getSeriesList()
    {
        return array_unique($this->extract('seriesList'));
    }

    /**
     * @return int
     */
    public function getTotalEpisodes()
    {
        $episodes = $this->extract('seriesEpisodeNumbers');
        sort($episodes);
        return (int) array_pop($episodes);
    }

    /**
     * @return string
     */
    public function getEpisodeTitle()
    {
        return $this->extract('episodeTitle');
    }

    /**
     * @return mixed
     */
    public function getDownloadUrl()
    {
        return $this->extract('vimeoUrl');
    }

    /**
     * @param string $what
     * @param string $from
     *
     * @return mixed
     *
     * @throws MatchNotFoundException
     */
    private function extract($what, $from = null)
    {
        if(null === $from) {
            $from = $this->response->getContents();
        }

        $extractor = $this->getExtractor($what);

        return $this->isExtractorMultiple($extractor)
            ? $this->extractMultiple($what, $from, $extractor)
            : $this->extractSingle($what, $from, $extractor);
    }

    /**
     * @param string $what
     *
     * @return array
     */
    private function getExtractor($what)
    {
        if (!array_key_exists($what, $this->extractorTypes)) {
            $error = "Extractor type '%s' not found in: %s";
            throw new RuntimeException(sprintf($error, $what,implode(', ', array_keys($this->extractorTypes))));
        }

        return $this->extractorTypes[$what];
    }

    /**
     * @param string $what
     * @param string $from
     * @param array $extractor
     *
     * @return mixed
     * @throws MatchNotFoundException
     */
    private function extractSingle($what, $from, array $extractor)
    {
        $this->blowUpIfItDoesNotFindMatches(
            $what,
            $from,
            preg_match($extractor['pattern'], $from, $match)
        );

        return $match[$extractor['name']];
    }

    /**
     * @param string $what
     * @param string $from
     * @param array $extractor
     *
     * @return mixed
     * @throws MatchNotFoundException
     */
    private function extractMultiple($what, $from, array $extractor)
    {
        $this->blowUpIfItDoesNotFindMatches(
            $what,
            $from,
            preg_match_all($extractor['pattern'], $from, $matches)
        );
        return $matches[$extractor['name']];
    }

    /**
     * @param array $extractor
     *
     * @return bool
     */
    private function isExtractorMultiple(array $extractor)
    {
        return array_key_exists('multiple', $extractor) && $extractor['multiple'];
    }

    /**
     * @param string $what
     * @param string $from
     * @param int|false $matches
     *
     * @throws MatchNotFoundException
     */
    private function blowUpIfItDoesNotFindMatches($what, $from, $matches)
    {
        if(0 == $matches) {
            throw new MatchNotFoundException($what, $from);
        }
    }
}
