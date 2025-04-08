<?php

declare(strict_types=1);

namespace App\Services;

use Nette;

use \App\Services\Logger;

class IcalTools
{
    use Nette\SmartObject;

    public static function sortEvents( $events ) 
    {
        usort( $events, function($first,$second){
                if( $first->getStart() < $second->getStart() ) return -1;
                if( $first->getStart() > $second->getStart() ) return 1;
                if( $first->getEnd() < $second->getEnd() ) return -1;
                if( $first->getEnd() > $second->getEnd() ) return 1;
                return 0;
            });
        return $events;
    }


    public static function readEventsFromFile( $fileName, $dateFrom, $dateTo ) 
    {
        $handle = fopen($fileName,'r+');
		$parser = new \App\Services\IcalParser($handle);
		$events = $parser->parse( $dateFrom, $dateTo );
		fclose($handle);
        return $events;
    }
}