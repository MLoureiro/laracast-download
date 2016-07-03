<?php

require_once 'config.php';
require_once 'src/Logger.php';
require_once 'src/Cache.php';
require_once 'src/SeriesCatalog.php';
require_once 'src/Exception/MatchNotFoundException.php';
require_once 'src/Exception/AuthenticationFailedException.php';
require_once 'src/Client/Response.php';
require_once 'src/LaracastResponseExtractor.php';
require_once 'src/Client/CurlClient.php';
require_once 'src/LaracastClient.php';
