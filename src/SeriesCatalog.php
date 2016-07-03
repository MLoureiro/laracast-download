<?php

use Exception\MatchNotFoundException;

class SeriesCatalog
{
    private $rootDirectory;

    public function __construct($rootDirectory)
    {
        $this->rootDirectory = $rootDirectory;
    }

    /**
     * @param string $series
     *
     * @return int
     *
     * @throws MatchNotFoundException
     */
    public function getSeriesHighestEpisode($series)
    {
        if(!$this->seriesFolderExist($series)) {
            return 0;
        }

        $episodeList = $this->getEpisodesInFolder($this->buildSeriesFolderPath($series));
        if(!count($episodeList)) {
            return 0;
        }

        sort($episodeList);
        $lastEpisode = array_pop($episodeList);
        return $this->extractEpisodeNumber($lastEpisode);
    }

    /**
     * @param string $series
     *
     * @return bool
     */
    public function seriesFolderExist($series)
    {
        return is_dir($this->buildSeriesFolderPath($series));
    }

    /**
     * @param string $series
     * @param int $episodeNumber
     * @param string $episodeTitle
     *
     * @return string
     */
    public function buildPathToEpisode($series, $episodeNumber, $episodeTitle = '')
    {
        $file = $this->buildEpisodeName($episodeNumber, $episodeTitle);
        $folder = $this->buildSeriesFolderPath($series);
        return "{$folder}/{$file}";
    }

    /**
     * @param int $number
     * @param string $title
     *
     * @return string
     */
    private function buildEpisodeName($number, $title)
    {
        $name = $number < 10 ? '0' . $number : $number;
        if($title) {
            $name .= " - {$title}";
        }

        return "{$name}.mp4";
    }

    /**
     * @param string $series
     *
     * @return mixed
     */
    private function buildSeriesFolderPath($series)
    {
        return str_replace('//', '/', "{$this->rootDirectory}/{$series}");
    }

    /**
     * @param string $folder
     *
     * @return string[]
     */
    private function getEpisodesInFolder($folder)
    {
        $fileList = scandir($folder);
        $episodeList = [];
        foreach ($fileList as $file) {
            if($this->isEpisode($file)) {
                $episodeList[] = $file;
            }
        }

        return $episodeList;
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    private function isEpisode($file)
    {
        return (bool) preg_match('/^\d+.+\.mp4$/', $file);
    }

    /**
     * @param string $lastEpisode
     *
     * @return int
     *
     * @throws MatchNotFoundException
     */
    private function extractEpisodeNumber($lastEpisode)
    {
        if(! preg_match('/^(?P<number>\d+).+/', $lastEpisode, $match)) {
            throw new MatchNotFoundException('episodeNumber', $lastEpisode);
        }
        return (int) $match['number'];
    }
}
