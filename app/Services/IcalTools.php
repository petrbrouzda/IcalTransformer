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


    private static $dny = [ ' ', 'po', 'út', 'st', 'čt', 'pá', 'so', 'ne' ];
    private static $dnyDlouhe = [ ' ', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle' ];

    public static function hezkeDatum( $date, $today )
    {
        $dateT = $date->format('Y-m-d');
        $cas = $date->format('H:i');

        if( strcmp( $today->format('Y-m-d') , $dateT)==0 ) {
            if( $cas==='00:00' ) {
                return "dnes";
            } else {
                return "" . $date->format('H:i');
            }
        }

        if( strcmp( $today->modifyClone('+1 day')->format('Y-m-d') , $dateT)==0 ) {
            if( $cas==='00:00' ) {
                return 'zítra';
            } else {
                return "zítra " . $date->format('H:i');
            }
        }

        $datum = '';

        if( $today->getTimestamp() >= $date->getTimestamp() ) {
            // v minulosti
            $datum = self::$dny[$date->format('N')] . ' ' . $date->format( 'j.n.' );
        } else {
            // v budoucnosti
            $interval = $today->diff( $date );
            $days = $interval->y * 365 + $interval->m * 31 + $interval->d + 1;
            if( $days < 6 ) {
                $datum = self::$dnyDlouhe[$date->format('N')];
            } else {
                $datum = self::$dny[$date->format('N')] . ' ' . $date->format( 'j.n.' );
            }
        }
        
        if( $cas==='00:00' ) {
            // zacina o pulnoci, nebudeme cas udavat
        } else {
            $datum = $datum . ' ' . $cas;
        }
        return $datum;
    }    


    public static function convertEventsToJson( $events, $mode, $htmlAllowed, $today )  {
        $today->setTime( 0, 0, 0, 0 );

        $result = array();
        foreach( $events as $event ) {
            $rc = array();
            $rc['description'] = $event->getDescription( $htmlAllowed );
            $rc['location'] = $event->getLocation( $htmlAllowed );
            $rc['summary'] = $event->getSummary( $htmlAllowed );
            $rc['time_start_i'] = $event->getStart()->format('c');
            $rc['time_start_e'] = $event->getStart()->getTimestamp();

            if( $mode==="todayplus" ) {
                // pokud se ptáme ode dneška a začátek je v minulosti, speciální formátování
                if( $today->getTimestamp() > $event->getStart()->getTimestamp() ) {
                    $interval = $event->getStart()->diff( new Nette\Utils\DateTime() );
                    // zakladni vypocet, o presnost nejde
                    $days = $interval->y * 365 + $interval->m * 31 + $interval->d + 1;
                    $rc['time_start_t'] = "({$days}. den)";
                } else {
                    $rc['time_start_t'] = self::hezkeDatum( $event->getStart(), $today );
                }
            } else {
                $rc['time_start_t'] = self::hezkeDatum( $event->getStart(), $today );
            }

            $rc['time_end_i'] = $event->getEnd()->format('c');
            $rc['time_end_e'] = $event->getEnd()->getTimestamp();
            $rc['time_end_t'] = self::hezkeDatum( $event->getEnd(), $today );

            // speciální formátování pro celodenní události, kterém končí o půlnoci - takové je potřeba zobrazit jako končící o den dříve
            //TODO: není řešena situace v těch dvou dnech, které nemají 24 hodin
            if( $rc['time_end_e']-$rc['time_start_e']>=86400) {
                if( $event->getEnd()->format('H:i') === '00:00' ) {
                    $tmpDatum = $event->getEnd()->modifyClone('-1 day');
                    $rc['time_end_t'] = self::hezkeDatum( $tmpDatum, $today );
                }
            }

            $result[] = $rc;
        }
        return $result;
    }
}