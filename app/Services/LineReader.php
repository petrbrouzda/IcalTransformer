<?php

/**
 * Čte textový soubor řádek po řádku; umožňuje vrácení celého řádku do fronty (unwind)
 */

declare(strict_types=1);

namespace App\Services;

use Nette;

use \App\Services\Logger;

class LineReader 
{
    use Nette\SmartObject;

    private $handle;
    private $nextLine;
    private $currentLine;
    
	public function __construct( $handle )
	{
        $this->handle = $handle;
        $this->nextLine = false;
	}

    public function nextLineAvailable() {
        // je tam unwindnuta radka
        if( $this->nextLine !== false ) return true;

        $this->nextLine = fgets($this->handle);
        return ($this->nextLine !== false);
    }

    public function getLine() {
        $this->currentLine = $this->nextLine;
        $this->nextLine = false;
        return $this->currentLine;
    }

    public function unwind() {
        $this->nextLine = $this->currentLine;
    }
}