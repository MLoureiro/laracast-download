<?php

require 'bootstrap.php';

use Client\CurlClient;
use Logger as Log;

/*
 * NOTE: the customization variables come from config.php
 */

global $config;

switch ($config['logLevel']) {
    case 'silent':
        $verbosity = Log::VERBOSITY_SILENT;
        break;
    case 'high':
        $verbosity = Log::VERBOSITY_HIGHER;
        break;
    default:
        $verbosity = Log::VERBOSITY_NORMAL;
}

Log::setVerbosity($config['debug'] ? Log::VERBOSITY_HIGHER : $verbosity);

$client = new LaracastClient(
    [
        'email' => $config['credentials']['email'],
        'password' => $config['credentials']['password'],
        'debug' => $config['debug'],
    ],
    new CurlClient([], $config['debug'])
);

$seriesCatalog = new SeriesCatalog($config['seriesDirectory']);

$cache = new Cache();

Log::info('--- Starting ---');
Log::info('');
$startTime = microtime(true);


###
# Get existing series and their episodes data
###

Log::infoInline('Authenticating... ');
$client->authenticate();
Log::info('done!');

// get total series and their episodes
if(!$cache->has('series.episode_count')) {
    $seriesList = fetchRemoteSeriesList($client);
    $remoteEpisodeList = fetchRemoteSeriesTotalEpisodes($client, $seriesList);
    Log::info('Found ' . count($seriesList) . ' series and ' . array_sum($remoteEpisodeList) . ' episodes');
    $cache->put('series.episode_count', $remoteEpisodeList);
// @todo cache $seriesList and $remoteEpisodeList for 1 week or so
}
else {
    Log::info('');
    Log::info('-- Loading remote data from cache ---');
    $remoteEpisodeList = $cache->get('series.episode_count');
    $seriesList = array_keys($remoteEpisodeList);
    Log::info('Found ' . count($seriesList) . ' series and ' . array_sum($remoteEpisodeList) . ' episodes');
}


###
# Get list of what you already have
###
$localEpisodeList = fetchLocalSeriesTotalEpisodes($seriesCatalog, array_keys($remoteEpisodeList));
Log::info('Found ' . count(array_filter($localEpisodeList)) . ' series and ' . array_sum($localEpisodeList) . ' episodes');


###
# Finally get the missing series and episodes
###

Log::info('');
Log::info('--- Start to download missing series and episodes ---');
foreach ($seriesList as $series) {
    Log::infoInline(" - processing {$series}: ", Log::VERBOSITY_HIGHER);
    if ($localEpisodeList[$series] >= $remoteEpisodeList[$series]) {
        Log::info(" complete!!!", Log::VERBOSITY_HIGHER);
        continue;
    }

    $missingEpisodes = range($localEpisodeList[$series] + 1, $remoteEpisodeList[$series]);
    Log::info('missing (' . implode(', ', $missingEpisodes) . ')', Log::VERBOSITY_HIGHER);

    foreach ($missingEpisodes as $episodeNumber) {
        Log::info("   -- Processing {$series} #{$episodeNumber}");
        if(!processSeriesEpisode($client, $seriesCatalog, $series, $episodeNumber)) {
            break;
        }
        Log::info("   -- Finish {$series} #{$episodeNumber}");
    }
}
Log::info('--- Finish ---');
$endTime = microtime(true);
Log::info('Took: ' . ($endTime - $startTime) . ' seconds', Log::VERBOSITY_HIGHER);


function download($from, $to)
{
    $cmd = "/usr/bin/curl -L -o \"$to\" -A 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.117 Safari/537.36' '$from'";
    Log::info('Executing command ' . $cmd, Log::VERBOSITY_HIGHER);
    passthru($cmd);

    $successful = file_exists($to);
    if(! $successful) {
        Log::danger('Failed downloading: ' . $from);
    }

    return $successful;
}

/**
 * @param LaracastClient $client
 * @param SeriesCatalog $catalog
 * @param string $series
 * @param int $episodeNumber
 *
 * @return bool
 */
function processSeriesEpisode(LaracastClient $client, SeriesCatalog $catalog, $series, $episodeNumber)
{
    $extractor = $client->getSeriesEpisodePage($series, $episodeNumber);

    Log::infoInline('  - Fetching name:', Log::VERBOSITY_HIGHER);
    $fileName = $extractor->getEpisodeTitle();
    Log::info($fileName, Log::VERBOSITY_HIGHER);

    try {
        Log::infoInline('  - Fetching download url: ', Log::VERBOSITY_HIGHER);
        $downloadUrl = $extractor->getDownloadUrl();
        Log::info('found!', Log::VERBOSITY_HIGHER);
    }
    catch (\Exception\MatchNotFoundException $e) {
        Log::danger("Could not find series '{$series}' #{$episodeNumber} download URL");
        return false;
    }

    $filePath = $catalog->buildPathToEpisode($series, $episodeNumber, $fileName);
    Log::info('  - Starting download url: ' . $downloadUrl, Log::VERBOSITY_HIGHER);
    if(!download($downloadUrl, $filePath)) {
        return false;
    }
    Log::info('  - Finished!!!', Log::VERBOSITY_HIGHER);

    return true;
}


/**
 * @param $client
 *
 * @return array
 */
function fetchRemoteSeriesList(LaracastClient $client)
{
    Log::info('');
    Log::info('--- Fetching series list ---');
    $firstPageExtractor = $client->getSeriesListPage(1);
    $totalPages = $firstPageExtractor->getHighestPage();
    $seriesList = [];
    for ($currentPage = 1; $currentPage <= $totalPages; $currentPage++) {
        Log::infoInline("- lesson page {$currentPage}: ", Log::VERBOSITY_HIGHER);
        $extractor = 1 !== $currentPage // 1 less request
            ? $client->getSeriesListPage($currentPage)
            : $firstPageExtractor;
        $batch = $extractor->getSeriesList();
        Log::info(count($batch) . ' found!', Log::VERBOSITY_HIGHER);

        $seriesList = array_merge($seriesList, $batch);
    }
    return array_unique($seriesList);
}

/**
 * @param $seriesList
 * @param $client
 *
 * @return array
 */
function fetchRemoteSeriesTotalEpisodes(LaracastClient $client, $seriesList)
{
    Log::info('');
    Log::info('--- Fetching series episode count ---');
    $remoteEpisodeList = [];
    foreach ($seriesList as $series) {
        Log::infoInline(" - {$series} have: ", Log::VERBOSITY_HIGHER);
        $remoteEpisodeList[$series] = $client->getSeriesPage($series)
            ->getTotalEpisodes();
        Log::info($remoteEpisodeList[$series], Log::VERBOSITY_HIGHER);
    }
    return $remoteEpisodeList;
}

/**
 * @param SeriesCatalog $seriesCatalog
 * @param array $seriesList
 *
 * @return array
 */
function fetchLocalSeriesTotalEpisodes(SeriesCatalog $seriesCatalog, array $seriesList)
{
    Log::info('');
    Log::info('--- Starting to check which series and episodes exist locally ---');
    $localEpisodeList = [];
    foreach ($seriesList as $series) {
        Log::infoInline(" - {$series}: ", Log::VERBOSITY_HIGHER);
        $localEpisodeList[$series] = $seriesCatalog->getSeriesHighestEpisode($series);
        Log::info("{$localEpisodeList[$series]} episodes", Log::VERBOSITY_HIGHER);
    }
    return $localEpisodeList;
}
