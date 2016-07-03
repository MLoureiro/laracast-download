<?php

class Logger
{
    const VERBOSITY_SILENT = 0x00;
    const VERBOSITY_NORMAL = 0x01;
    const VERBOSITY_HIGHER = 0x02;

    static private $verbosityLevel = self::VERBOSITY_NORMAL;

    static public function setVerbosity($verbosity)
    {
        self::$verbosityLevel = $verbosity;
    }

    static public function info($message, $minimumVerbosityLevel = self::VERBOSITY_NORMAL)
    {
        self::display($message, $minimumVerbosityLevel);
    }

    static public function infoInline($message, $minimumVerbosityLevel = self::VERBOSITY_NORMAL)
    {
        self::display($message, $minimumVerbosityLevel, true);
    }

    static public function warn($message, $minimumVerbosityLevel = self::VERBOSITY_NORMAL)
    {
        self::display("WARNING: {$message}", $minimumVerbosityLevel);
    }

    static public function danger($message)
    {
        self::display("DANGER: {$message}", self::VERBOSITY_SILENT);
    }

    static private function display($message, $minimumVerbosityLevel, $inline = false)
    {
        if(static::$verbosityLevel >= $minimumVerbosityLevel) {
            echo $message . ($inline ? '' : PHP_EOL);
        }
    }
}
