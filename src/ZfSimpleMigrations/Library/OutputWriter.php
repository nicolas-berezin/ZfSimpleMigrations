<?php

namespace ZfSimpleMigrations\Library;

class OutputWriter
{
    const GREEN = "\033[0;32m";
    const LIGHTGREEN = "\033[1;32m";
    const RED = "\033[0;31m";
    const LIGHTRED = "\033[1;31m";
    const CYAN = "\033[0;36m";
    const LIGHTGRAY = "\033[0;37m";
    const YELLOW = "\033[1;33m";
    const NO_COLOR = "\e[0m";

    private $closure;

    public function __construct(\Closure $closure = null)
    {
        if ($closure === null) {
            $closure = function ($message) {
            };
        }
        $this->closure = $closure;
    }

    /**
     * @param string $message message to write
     */
    public function write($message, $color = null)
    {
        call_user_func($this->closure, $message, $color);
    }

    /**
     * @param $line
     */
    public function writeLine($line)
    {
        $this->write($line . "\n");
    }
}
