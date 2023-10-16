<?php

/**
 * Čte jednotlivé řádky iCal souboru; spojuje rozdělené řádky (mezera na začátku = pokračování předešlého řádku).
 * Pro načtený řádek rovnou dohledá příkaz a první jeho parametr.
 */

declare(strict_types=1);

namespace App\Services;

use Nette;

use \App\Services\Logger;
use \App\Services\LineReader;

class IcalStreamReader 
{
    use Nette\SmartObject;

    private $reader;

	public function __construct( $handle )
	{
        $this->reader = new \App\Services\LineReader($handle);
	}

    public function nextLineAvailable() {
        return $this->reader->nextLineAvailable();
    }

    public $command;
    public $firstParam;

    private function getCommand( $line ) {
        $keywords = preg_split("/[;: ]+/", $line, 3 );
        $this->command = $keywords[0];
        if( isset($keywords[1]) ) {
            $this->firstParam = $keywords[1];
        } else {
            $this->firstParam = '-';
        }
    }

    public function getLine() {
        $out = "";

        // nacteme jednu radku
        if( ! $this->reader->nextLineAvailable() ) return $out;
        $out = $this->reader->getLine();
        $out = trim($out);

        // a pak vsechny dalsi, pokud zacinaji mezerou
        while( true ) {
            if( ! $this->reader->nextLineAvailable() ) break;

            $ln = $this->reader->getLine();
            // pokud radka zacina mezerou, je to pokracovani radky predesle
            if( substr( $ln, 0, 1 ) == ' ' ) {
                $out = $out . trim(substr( $ln, 1 ));
            } else {
                // je tam normalni radka
                $this->reader->unwind();
                break;
            }
        }
        $this->getCommand( $out );
        //D/ Logger::log( 'app', Logger::TRACE, "   ... {$out}" );    
        return $out;
    }

}