<?php

/**
 * Jedna iCal událost.
 * Vstupem jsou jednotlivé příkazy z iCal souboru; výstupem je jejich naparsování do objektu.
 * 
 * Odlišná funkce jednorázových a opakovaných událostí!
 * - Jednorázová událost se dá použít přímo.
 * - Opakovaná událost se musí pomocí fillEventsInDateRanges rozpadnout na jednotlivé události.
 */

declare(strict_types=1);

namespace App\Model;

use Nette;
use \App\Services\Logger;
use Nette\Utils\DateTime;

use DateTimeZone;
use Exception;

class IcalEvent
{
    use Nette\SmartObject;

    private static $dowMapperToText = array(
        'MO' => 'Monday',
        'TU' => 'Tuesday',
        'WE' => 'Wednesday',
        'TH' => 'Thursday',
        'FR' => 'Friday',
        'SA' => 'Saturday',
        'SU' => 'Sunday'
    );

    private static $orderToText = array(
        '-1' => 'Last',
        '1' => 'First',
        '2' => 'Second',
        '3' => 'Third',
        '4' => 'Fourth',
        '5' => 'Fifth'
    );

    private static $dowMapperToStr = array(
        1 => 'MO',
        2 => 'TU',
        3 => 'WE',
        4 => 'TH',
        5 => 'FR',
        6 => 'SA',
        7 => 'SU'
    );


    private $summary;
    private $description;
    private $location;
    private $rrule;

    private $exdates;
    private $dtend;
    private $dtstart;

    /** je potreba pro nahrazovani vyjimek pri recurrenci */
    private $uid;

    /** timestamp udalosti, kterou nahrazuje */
    private $recurrenceId;

    public function __toString() {
        return "IcalEvent from:[{$this->dtstart}] to:[{$this->dtend}] summary:[{$this->summary}] [{$this->uid}]";
    }

    private function init( $start, $end, $summary, $description, $location, $uid, $exdates )
    {
        $this->dtstart = $start;
        $this->dtend = $end;
        $this->summary = $summary;
        $this->description = $description;
        $this->location = $location;
        $this->uid = $uid;
        $this->exdates = $exdates;
    }

	public function __construct(  )
	{
        $this->rrule = array();
        $this->exdates = array();

        $this->dtend = null;
        $this->dtstart = null;
	}

    public function isValid() {
        if( $this->dtstart==null ) return false;
        return true;
    }

    public function getStart() {
        return $this->dtstart;
    }
    public function getEnd() {
        return $this->dtend!=null ? $this->dtend : $this->dtstart;
    }

    private function stripHtml( $text ) {
        $text = str_replace( '<br>', " \n", $text );
        $text = str_replace( '<BR>', " \n", $text );
        $text = str_replace( '<p>', " \n", $text );
        $text = str_replace( '<P>', " \n", $text );
        $text = str_replace( '&nbsp;', " ", $text );
        $text = strip_tags($text);
        return $text;
    }

    public function getDescription( $htmlAllowed=true ) {

        $text = $this->description;
        if( $text==null || strlen(trim($text))==0 ) { 
            $text = "";
        }

        if( ! $htmlAllowed ) {
            $text = $this->stripHtml( $text );
        }

        return $text;
    }

    public function getLocation( $htmlAllowed=true ) {
        $text = $this->location;
        if( $text==null || strlen(trim($text))==0 ) { 
            $text = "";
        }

        if( ! $htmlAllowed ) {
            $text = $this->stripHtml( $text );
        }

        return $text;
    }

    public function getSummary( $htmlAllowed=true ) {
        $text = $this->summary;
        if( $text==null || strlen(trim($text))==0 ) { 
            $text = "";
        }

        if( ! $htmlAllowed ) {
            $text = $this->stripHtml( $text );
        }

        return $text;
    }

    public function setUid( $uid ) {
        $this->uid = $uid;
    }

    public function getUid() {
        return $this->uid;
    }

    public function getRecurrenceId() {
        return $this->recurrenceId;
    }

    public function setRecurrenceId( $attributes, $parameter ) {
        $this->recurrenceId = $this->parseDate( $attributes, $parameter );
        if( $this->recurrenceId!=null ) {
            $tzone = new \DateTimeZone( date_default_timezone_get() );
            $this->recurrenceId->setTimezone( $tzone );
            //D/ Logger::log( 'app', Logger::DEBUG, "RECURRENCE-ID: {$this->recurrenceId->format('r')}" );    
        }
    }

    public function hasRecurrenceId() {
        return ( $this->recurrenceId!=null );    
    }

    /**
     * Jde o opakovanou udalost?
     */
    public function isRecurrent()
    {
        return ( count($this->rrule) > 0 );
    }

    /*
    RRULE:FREQ=WEEKLY;WKST=MO;UNTIL=20231101T225959Z;BYDAY=WE
    RRULE:FREQ=WEEKLY;WKST=MO;COUNT=14;BYDAY=TU,TH
    RRULE:FREQ=WEEKLY;UNTIL=20110626T130000Z;INTERVAL=2;BYDAY=WE
    RRULE:FREQ=WEEKLY;WKST=MO;UNTIL=20231224;INTERVAL=4;BYDAY=MO
    RRULE:FREQ=WEEKLY;INTERVAL=1
    
    RRULE:FREQ=MONTHLY;COUNT=3;BYMONTHDAY=18
    RRULE:FREQ=MONTHLY;BYDAY=3MO
    RRULE:FREQ=MONTHLY;BYDAY=-1TH

    RRULE:FREQ=YEARLY
    RRULE:FREQ=YEARLY;BYMONTHDAY=28;BYMONTH=10

    RRULE:FREQ=DAILY;COUNT=5
    */
    /**
     * Pouze pro opakovane udalosti
     */
    public function isRecurrentInDateRange( $from, $toExclusive )
    {
        // zacina v budoucnu = nezajima
        if( $this->getStart() >= $toExclusive ) {
            //D/ Logger::log( 'app', Logger::TRACE, " isrd: v budoucnu" );    
            return false;
        }

        $until = KeyValueAttribute::findValue( $this->rrule, 'UNTIL' );
        if( $until !== '' ) {
            //D/ Logger::log( 'app', Logger::TRACE, " isrd: kontroluji until '{$until}'" );    

            // 20231101T225959Z nebo 20231224
            if( strlen($until)==8 ) {
                $untilDate = DateTime::createFromFormat('Ymd', $until, 'Z'  );
            } else {
                $untilDate = DateTime::createFromFormat('Ymd\THis\Z', $until, 'Z'  );
            }
            
            //D/ Logger::log( 'app', Logger::TRACE, " isrd: until {$untilDate}" );    
            if( $untilDate < $from  ) {
                //D/ Logger::log( 'app', Logger::TRACE, " isrd: v minulosti" );    
                return false;
            } else {
                //D/ Logger::log( 'app', Logger::TRACE, " isrd: ANO (pres until)" );    
                return true;
            }
        }

        $interval = 1;
        $intervalS = KeyValueAttribute::findValue( $this->rrule, 'INTERVAL' );
        if( $intervalS !== '' ) {
            $interval = intval($intervalS);
        }

        $count = KeyValueAttribute::findValue( $this->rrule, 'COUNT' );
        if( $count !== '' ) {
            $countI = intval($count);
            $freq = KeyValueAttribute::findValue( $this->rrule, 'FREQ' );
            if( $freq === 'WEEKLY') {
                //D/ Logger::log( 'app', Logger::TRACE, " isrd: kontroluji WEEKLY count {$countI}" );    
                // RRULE:FREQ=WEEKLY;WKST=MO;COUNT=14;BYDAY=TU,TH
                // kolikrat tydne ma nastat?
                $days = KeyValueAttribute::findValue( $this->rrule, 'BYDAY' );
                if( $days==='' ) {
                    // pokud neni mapovane na konkretni dny, opakuje se 1x tydne
                    $pocetTydne = 1;
                } else {
                    $pocetTydne = count( explode( ',', $days ) );
                }
                $tydnuDopredu = (($countI / $pocetTydne)*$interval) + 2;
                $maxEndDate =  $this->getStart()->modifyClone("+{$tydnuDopredu} week");
                if( $maxEndDate < $from ) {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: v minulosti" );    
                    return false;
                } else {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: ANO (pres COUNT WEEKLY)" );    
                    return true;
                }
            } else if( $freq === 'DAILY') {
                //D/ Logger::log( 'app', Logger::TRACE, " isrd: kontroluji DAILY count {$countI}" );    

                $days = KeyValueAttribute::findValue( $this->rrule, 'BYDAY' );
                if( $days!=='' ) {
                    Logger::log( 'app', Logger::WARNING, "neimplementovano: pro RRULE:FREQ=DAILY nalezeno BYDAY" );   
                    return false;    
                }

                $dniDopredu = $countI*$interval + 2;
                $maxEndDate =  $this->getStart()->modifyClone("+{$dniDopredu} day");
                if( $maxEndDate < $from ) {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: v minulosti" );    
                    return false;
                } else {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: ANO (pres COUNT MOTHLY)" );    
                    return true;
                }
            } else if( $freq === 'MONTHLY') {
                //D/ Logger::log( 'app', Logger::TRACE, " isrd: kontroluji MONTHLY count {$countI}" );    
                $mesicuDopredu = $countI*$interval + 2;
                $maxEndDate =  $this->getStart()->modifyClone("+{$mesicuDopredu} month");
                if( $maxEndDate < $from ) {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: v minulosti" );    
                    return false;
                } else {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: ANO (pres COUNT MOTHLY)" );    
                    return true;
                }
            } else if( $freq === 'YEARLY') {
                $letDopredu = $countI*$interval + 2;
                $maxEndDate =  $this->getStart()->modifyClone("+{$letDopredu} year");
                if( $maxEndDate < $from ) {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: v minulosti" );    
                    return false;
                } else {
                    //D/ Logger::log( 'app', Logger::TRACE, " isrd: ANO (pres COUNT YEARLY)" );    
                    return true;
                }
                return false;
            } else {
                Logger::log( 'app', Logger::WARNING, "neimplementovano: RRULE:FREQ={$freq}" );   
                return false; 
            }
        } 

        // nema ani until, ani count = do nekonecna
        //D/ Logger::log( 'app', Logger::TRACE, " isrd: recurrent bez konce" );    
        return true;
    }


    /*
    RRULE:FREQ=WEEKLY;WKST=MO;UNTIL=20231101T225959Z;BYDAY=WE
    RRULE:FREQ=WEEKLY;WKST=MO;COUNT=14;BYDAY=TU,TH
    RRULE:FREQ=WEEKLY;WKST=MO;COUNT=13;INTERVAL=2;BYDAY=TU,FR,TH
    RRULE:FREQ=WEEKLY;BYDAY=FR,MO,TH,TU,WE
    RRULE:FREQ=WEEKLY;INTERVAL=1
    */
    private function fillEventsInDateRangesWeekly( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $interval ) {

        $days = KeyValueAttribute::findValue( $this->rrule, 'BYDAY' );
        if( $days ===  '' ) {
            $daysArray[] = self::$dowMapperToStr[ intval($this->dtstart->format('N')) ];
            //D/ Logger::log( 'app', Logger::TRACE, "WEEKLY bez BYDAY, nastavuji {$daysArray[0]}" );        
        } else {
            $daysArray = explode( ',', $days );
        }
        //D/ Logger::log( 'app', Logger::TRACE, $daysArray );    

        // kontrola, zda tam neni neco neznameho
        foreach( $daysArray as $dx ) {
            if( ! in_array( $dx, self::$dowMapperToStr, true ) ) {
                Logger::log( 'app', Logger::WARNING, "neimplementovane RRULE:BYDAY={$dx}" );   
                return;
            }
        }

        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        $weekNo = 0;
        while( true ) {
            $dow =  self::$dowMapperToStr[ $date->format('N') ];

            // ma se generovat tento tyden?
            $thisWeekOK = (($weekNo % $interval) == 0);
            
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola dow={$dow} weekno={$weekNo} weekOK=". ($thisWeekOK?"Y":"N") ." - {$date}" );    

            if( in_array($dow, $daysArray, true ) ) {
                // tento den je v seznamu opakovacich dni
                $ctEvents++;

                $end = $date->modifyClone( "+ {$delkaSec} sec" );

                // zapisujeme jen pokud se to prekryva s pozadovanym oknem
                if( $thisWeekOK && $this->isInRange($date, $end, $from, $to ) ) {
                    // ale mohli jsme prejit koncove datum
                    if($untilDate!=null && $date>=$untilDate) {
                        //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano {$ctEvents} udalosti do [{$to}] resp [{$untilDate}], koncim" );            
                        break;
                    }
                    $oneEvent = new \App\Model\IcalEvent();
                    $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location, $this->uid, $this->exdates );
                    //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                    $oneEvent->writeIntoArray( $events );
                }
            }

            $date->modify( '+1 day' );
            $date->setTime( intval($this->dtstart->format('G')), intval($this->dtstart->format('i')), 0, 0 );

            if( $date->format('N') == '1' ) {
                // presli jsme do dalsiho tydne
                $weekNo++;
            }

            if( $ctEvents >= $maxCount ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano maximum {$ctEvents} udalosti, koncim" );   
                break; 
            }
            if( $date > $to || ($untilDate!=null && $date>=$untilDate) ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano {$ctEvents} udalosti do [{$to}] resp [{$untilDate}], koncim" );    
                break;
            }
        }
    }


    /*
    RRULE:FREQ=MONTHLY;BYDAY=3MO
    RRULE:FREQ=MONTHLY;BYDAY=-1TH

    <cislo tydne v mesici><den>
    kde cislo tydne v mesici muze byt -1 = posledni tyden
    */
    private function fillEventsInDateRangesMonthlyByday( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $byDay, $interval ) {

        preg_match('/^([\-0-9]+)([A-Z]+)$/', $byDay, $output_array);
        if( !isset($output_array[2]) ) {
            Logger::log( 'app', Logger::WARNING, "neimplementovane RRULE:BYDAY {$byDay}" );   
            return;
        }
        $dayPos = $output_array[1];
        $dayAbbrev = $output_array[2];
        //D/ Logger::log( 'app', Logger::TRACE, "RRULE:BYDAY {$dayPos} / {$dayAbbrev}" );   
        if( !isset(self::$orderToText[$dayPos]) || !isset(self::$dowMapperToText[$dayAbbrev]) ) {
            Logger::log( 'app', Logger::WARNING, "neznama varianta RRULE:BYDAY {$dayPos} / {$dayAbbrev}" );   
        }
        $command = self::$orderToText[$dayPos] . ' ' . self::$dowMapperToText[$dayAbbrev]. ' of ';
        //D/ Logger::log( 'app', Logger::TRACE, "RRULE:BYDAY {$dayPos} / {$dayAbbrev} -> [{$command}]" );   

        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        while( true ) {
            $end = $date->modifyClone( "+ {$delkaSec} sec" );
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date} - {$end}" );    

            $ctEvents++;

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( $this->isInRange($date, $end, $from, $to ) ) {
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location, $this->uid, $this->exdates );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $oneEvent->writeIntoArray( $events );
            }

            $newMonth = intval($date->format('n'));
            $newYear = intval($date->format('Y'));
            $newMonth+=$interval;
            while( $newMonth > 12 ) {
                $newMonth = $newMonth - 12;
                $newYear++;
            }
            $date->setDate( $newYear, $newMonth, 1 );
            $cmd = $command . $date->format('F Y');

            $timestamp = strtotime($cmd);
            if( $timestamp===false ) {
                // nejde zkompilovat
                Logger::log( 'app', Logger::WARNING, "nedokazu zpracovat RRULE:BYDAY={$byDay} -> [{$cmd}]" );   
                return;
            }
            $str = date('l dS \o\f F Y h:i:s A', $timestamp);
            //D/ Logger::log( 'app', Logger::DEBUG, "output = {$str}" );   
            $tmpDate = new \Datetime();
            $tmpDate->setTimestamp( $timestamp );
            $date->setDate( intval($tmpDate->format('Y')), intval($tmpDate->format('n')), intval($tmpDate->format('j')) );
            $date->setTime( intval($this->dtstart->format('G')), intval($this->dtstart->format('i')), 0, 0 );

            if( $ctEvents >= $maxCount ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano maximum {$ctEvents} udalosti, koncim" );   
                break; 
            }
            if( $date > $to || ($untilDate!=null && $date>$untilDate) ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano {$ctEvents} udalosti do [{$to}] resp [{$untilDate}], koncim" );    
                break;
            }
        }
    }

    /*
    RRULE:FREQ=MONTHLY;COUNT=3;BYMONTHDAY=18
    RRULE:FREQ=MONTHLY;WKST=MO;BYMONTHDAY=-1
    */
    private function fillEventsInDateRangesMonthlyBymonthday( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $monthDay, $interval ) {
        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        while( true ) {
            $end = $date->modifyClone( "+ {$delkaSec} sec" );
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date} - {$end}" );    
            $ctEvents++;

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( $this->isInRange($date, $end, $from, $to ) ) {
                
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location, $this->uid, $this->exdates );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $oneEvent->writeIntoArray( $events );
            }

            $newMonth = intval($date->format('n'));
            $newYear = intval($date->format('Y'));
            while(true) {
                $newMonth+=$interval;
                while( $newMonth > 12 ) {
                    $newMonth = $newMonth - 12;
                    $newYear++;
                }

                // kladny den = den v mesici
                if( $monthDay>0 ) {
                    $date->setDate( $newYear, $newMonth, $monthDay);
                    if( intval($date->format('j')) == $monthDay ) {
                        break;
                    }
                    // v tomto mesici tento den neni, musime jit dal
                } else {
                    // zaporny den = pocitano od konce mesice zpetne
                    // najdeme posledni den mesice
                    $den = 31;
                    while( $den>27 ) {
                        $date->setDate( $newYear, $newMonth, $den);
                        if( intval($date->format('j')) == $den ) {
                            // mesic ma $den dni
                            $den = $den + $monthDay + 1;
                            $date->setDate( $newYear, $newMonth, $den);
                            //D/ Logger::log( 'app', Logger::TRACE, "byday={$monthDay} datum={$newYear}/{$newMonth}/{$den}" );    
                            break;
                        }
                        $den--;
                    }
                    // vyskocit z cyklu generovani
                    break;
                }

                // kontrola na koncove datum!
                if( $date > $to ) {
                    Logger::log( 'app', Logger::DEBUG, "zacykleno v generovani data, vyskakuji" );   
                    return;
                }
            }
            $date->setTime( intval($this->dtstart->format('G')), intval($this->dtstart->format('i')), 0, 0 );

            if( $ctEvents >= $maxCount ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano maximum {$ctEvents} udalosti, koncim" );   
                break; 
            }
            if( $date > $to || ($untilDate!=null && $date>$untilDate) ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano {$ctEvents} udalosti do [{$to}] resp [{$untilDate}], koncim" );    
                break;
            }
        }
    }

    /*
    RRULE:FREQ=MONTHLY;COUNT=3;BYMONTHDAY=18
    RRULE:FREQ=MONTHLY;BYDAY=3MO
    RRULE:FREQ=MONTHLY;BYDAY=-1TH    
    */
    private function fillEventsInDateRangesMonthly( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $interval ) {

        // zpracovani byday
        $byDay = KeyValueAttribute::findValue( $this->rrule, 'BYDAY' );
        if( $byDay !== '' ) {
            $this->fillEventsInDateRangesMonthlyByday( $from, $to, $events, $untilDate, $maxCount, $delkaSec, $byDay, $interval );
            return;
        }

        $monthDayS = KeyValueAttribute::findValue( $this->rrule, 'BYMONTHDAY' );
        if( $monthDayS!=='' ) {
            $monthDay = intval( $monthDayS );
            $this->fillEventsInDateRangesMonthlyBymonthday( $from, $to, $events, $untilDate, $maxCount, $delkaSec, $monthDay, $interval );
            return;
        }

        Logger::log( 'app', Logger::WARNING, "nejake neimplementovane RRULE" );   
        foreach ($this->rrule as $attr) {
            Logger::log( 'app', Logger::WARNING, " - {$attr->key} = {$attr->value}" );    
        }
    }


    private function fillEventsInDateRangesDaily( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $interval ) {

        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        while( true ) {
            $end = $date->modifyClone( "+ {$delkaSec} sec" );
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date} - {$end}" );    
            $ctEvents++;

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( $this->isInRange($date, $end, $from, $to ) ) {
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location, $this->uid, $this->exdates );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $oneEvent->writeIntoArray( $events );
            }

            $date->modify( "+{$interval} day" );
            $date->setTime( intval($this->dtstart->format('G')), intval($this->dtstart->format('i')), 0, 0 );

            if( $ctEvents >= $maxCount ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano maximum {$ctEvents} udalosti, koncim" );   
                break; 
            }
            if( $date > $to || ($untilDate!=null && $date>$untilDate) ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano {$ctEvents} udalosti do [{$to}] resp [{$untilDate}], koncim" );    
                break;
            }
        }
    }


    /*
    * RRULE:FREQ=YEARLY
    */
    private function fillEventsInDateRangesYearly( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $interval ) {

        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        while( true ) {
            $end = $date->modifyClone( "+ {$delkaSec} sec" );

            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date} - $end" );    
            $ctEvents++;

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( $this->isInRange($date, $end, $from, $to ) ) {
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location, $this->uid, $this->exdates );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $oneEvent->writeIntoArray( $events );
            }

            $newMonth = intval($date->format('n'));
            $newYear = intval($date->format('Y'));
            $monthDay = intval($date->format('j'));
            while(true) {
                $newYear+=$interval;
                $date->setDate( $newYear, $newMonth, $monthDay);
                if( intval($date->format('j')) == $monthDay ) {
                    break;
                }
                // v tomto mesici tento den neni, musime jit dal
            }
            $date->setTime( intval($this->dtstart->format('G')), intval($this->dtstart->format('i')), 0, 0 );

            if( $ctEvents >= $maxCount ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano maximum {$ctEvents} udalosti, koncim" );   
                break; 
            }
            if( $date > $to || ($untilDate!=null && $date>$untilDate) ) {
                //D/ Logger::log( 'app', Logger::TRACE, "vygenerovano {$ctEvents} udalosti do [{$to}] resp [{$untilDate}], koncim" );    
                break;
            }
        }
        
    }


    /**
     * Musi se delat pres tuhle funkci a ne pomoci prosteho vlozeni do pole, 
     * protoze je potreba smazat z pole pripadne prepsane udalosti 
     */
    public function writeIntoArray( &$events ) {

        //D/ Logger::log( 'app', Logger::DEBUG, "  * udalost: {$this}" );   

        // zkontrolovat proti EXDATE
        foreach( $this->exdates as $exdate ) {
            //D/ Logger::log( 'app', Logger::TRACE, "    kontroluji exdate: {$exdate}" );    
            if( $exdate == $this->getStart() ) {
                //D/ Logger::log( 'app', Logger::TRACE, "-- excluded: {$exdate}" );    
                return;
            }
        }

        //D/ Logger::log( 'app', Logger::DEBUG, "= zapsano: {$this}" );   
        $events[] = $this;
    }


    /**
     * Pouze pro opakovane udalosti. Zaplni udalosti v urcenem casovem okne do pole &events.
     */
    public function fillEventsInDateRanges( $from, $to, &$events )
    {
        $untilDate = null;
        $until = KeyValueAttribute::findValue( $this->rrule, 'UNTIL' );
        if( $until !== '' ) {
            // 20231101T225959Z
            // 20231224
            if( strlen($until)==8 ) {
                $untilDate = DateTime::createFromFormat('Ymd', $until, 'Z'  );
            } else {
                $untilDate = DateTime::createFromFormat('Ymd\THis\Z', $until, 'Z'  );
            }
        }

        $maxCount = 10000;
        $maxCountS = KeyValueAttribute::findValue( $this->rrule, 'COUNT' );
        if( $maxCountS !== '' ) {
            $maxCount = intval($maxCountS);
        }

        $interval = 1;
        $intervalS = KeyValueAttribute::findValue( $this->rrule, 'INTERVAL' );
        if( $intervalS !== '' ) {
            $interval = intval($intervalS);
        }

        // delka udalosti, $this->getEnd() nikdy nevraci null
        $delkaSec = $this->getEnd()->getTimestamp() - $this->getStart()->getTimestamp();

        $freq = KeyValueAttribute::findValue( $this->rrule, 'FREQ' );
        if( $freq === 'WEEKLY') {
            $this->fillEventsInDateRangesWeekly( $from, $to, $events, $untilDate, $maxCount, $delkaSec, $interval );
        } else if( $freq === 'MONTHLY') {
            $this->fillEventsInDateRangesMonthly( $from, $to, $events, $untilDate, $maxCount, $delkaSec, $interval );
        } else if( $freq === 'YEARLY') {
            $this->fillEventsInDateRangesYearly( $from, $to, $events, $untilDate, $maxCount, $delkaSec, $interval );
        } else if( $freq === 'DAILY') {
            $this->fillEventsInDateRangesDaily( $from, $to, $events, $untilDate, $maxCount, $delkaSec, $interval );
        }else {
            Logger::log( 'app', Logger::WARNING, "neimplementovane RRULE FREQ={$freq}" ); 
        }
    }

    private function isInRange( $eventFrom, $eventTo, $rangeFrom, $rangeTo ) {
        if( $eventTo<=$rangeFrom ) {
            // cela v minulosti
            return false;
        }
        if( $eventFrom>$rangeTo ) {
            // cela v budoucnosti
            return false;
        }
        // nejak se prekryva s oknem
        return ( ($eventFrom < $rangeTo) && ($eventTo>=$rangeFrom ) );
    }

    public function isInDateRange( $from, $toExclusive )
    {
        return $this->isInRange( $this->getStart(), $this->getEnd(), $from, $toExclusive );
    }


    /**
     * Tabulka překladů timezon pro MS kalendáře.
     * PHP umí jen ISO časové zóny ve tvaru Kontinent/Město, ale zejména v MS kalendářích se objevují i jiné.
     */
    private static $timeZoneFix = array (
        'Central Europe Standard Time' => 'Europe/Prague',
        'Central European Standard Time' => 'Europe/Prague',
        'Romance Standard Time'  => 'Europe/Prague',
        'W. Europe Standard Time' => 'Europe/Paris',
        
        // z tabulky
        'Afghanistan Time' => 'Asia/Kabul',
		'Alaska Standard Time' => 'America/Anchorage',
		'Alma-Ata Time' => 'Asia/Almaty',
		'Amazon Time' => 'America/Boa_Vista',
		'Anadyr Time' => 'Asia/Anadyr',
		'Aqtau Time' => 'Asia/Aqtau',
		'Aqtobe Time' => 'Asia/Aqtobe',
		'Arabia Standard Time' => 'Asia/Aden',
		'Argentine Time' => 'AGT',
		'Armenia Time' => 'Asia/Yerevan',
		'Atlantic Standard Time' => 'America/Anguilla',
		'Azerbaijan Time' => 'Asia/Baku',
		'Azores Time' => 'Atlantic/Azores',
		'Bangladesh Time' => 'Asia/Dacca',
		'Bhutan Time' => 'Asia/Thimbu',
		'Bolivia Time' => 'America/La_Paz',
		'Brasilia Time' => 'America/Araguaina',
		'Brunei Time' => 'Asia/Brunei',
		'Cape Verde Time' => 'Atlantic/Cape_Verde',
		'Central African Time' => 'Africa/Blantyre',
		'Central European Time' => 'Europe/Prague',
		'Central Indonesia Time' => 'Asia/Makassar',
		'Central Standard Time' => 'America/Belize',
		'Central Standard Time (Northern Territory)' => 'ACT',
		'Central Standard Time (South Australia)' => 'Australia/Adelaide',
		'Central Standard Time (South Australia/New South Wales)' => 'Australia/Broken_Hill',
		'Central Western Standard Time (Australia)' => 'Australia/Eucla',
		'Cocos Islands Time' => 'Indian/Cocos',
		'Colombia Time' => 'America/Bogota',
		'Cook Is. Time' => 'Pacific/Rarotonga',
		'Coordinated Universal Time' => 'Etc/UCT',
		'Cuba Standard Time' => 'America/Havana',
		'Davis Time' => 'Antarctica/Davis',
		"Dumont-d\'Urville Time" => 'Antarctica/DumontDUrville',
		'East Indonesia Time' => 'Asia/Jayapura',
		'Easter Is. Time' => 'Chile/EasterIsland',
		'Eastern African Time' => 'Africa/Addis_Ababa',
		'Eastern European Time' => 'Europe/Bucharest',
		'Eastern Greenland Time' => 'America/Scoresbysund',
		'Eastern Standard Time' => 'America/Atikokan',
		'Eastern Standard Time (New South Wales)' => 'AET',
		'Eastern Standard Time (Queensland)' => 'Australia/Brisbane',
		'Eastern Standard Time (Tasmania)' => 'Australia/Hobart',
		'Eastern Standard Time (Victoria)' => 'Australia/Melbourne',
		'Ecuador Time' => 'America/Guayaquil',
		'Falkland Is. Time' => 'Atlantic/Stanley',
		'Fernando de Noronha Time' => 'America/Noronha',
		'Fiji Time' => 'Pacific/Fiji',
		'French Guiana Time' => 'America/Cayenne',
		'French Southern and Antarctic Lands Time' => 'Indian/Kerguelen',
		'Galapagos Time' => 'Pacific/Galapagos',
		'Gambier Time' => 'Pacific/Gambier',
		'Georgia Time' => 'Asia/Tbilisi',
		'Ghana Mean Time' => 'Africa/Accra',
		'Gilbert Is. Time' => 'Pacific/Tarawa',
		'GMT+00:00' => 'Etc/GMT',
		'GMT+01:00' => 'Etc/GMT-1',
		'GMT+02:00' => 'Etc/GMT-2',
		'GMT+03:00' => 'Etc/GMT-3',
		'GMT+04:00' => 'Etc/GMT-4',
		'GMT+05:00' => 'Etc/GMT-5',
		'GMT+06:00' => 'Etc/GMT-6',
		'GMT+07:00' => 'Etc/GMT-7',
		'GMT+08:00' => 'Etc/GMT-8',
		'GMT+09:00' => 'Etc/GMT-9',
		'GMT+10:00' => 'Etc/GMT-10',
		'GMT+11:00' => 'Etc/GMT-11',
		'GMT+12:00' => 'Etc/GMT-12',
		'GMT+13:00' => 'Etc/GMT-13',
		'GMT+14:00' => 'Etc/GMT-14',
		'GMT-01:00' => 'Etc/GMT+1',
		'GMT-02:00' => 'Etc/GMT+2',
		'GMT-03:00' => 'Etc/GMT+3',
		'GMT-04:00' => 'Etc/GMT+4',
		'GMT-05:00' => 'Etc/GMT+5',
		'GMT-06:00' => 'Etc/GMT+6',
		'GMT-07:00' => 'Etc/GMT+7',
		'GMT-08:00' => 'Etc/GMT+8',
		'GMT-09:00' => 'Etc/GMT+9',
		'GMT-10:00' => 'Etc/GMT+10',
		'GMT-11:00' => 'Etc/GMT+11',
		'GMT-12:00' => 'Etc/GMT+12',
		'Greenwich Mean Time' => 'Africa/Abidjan',
		'Gulf Standard Time' => 'Asia/Dubai',
		'Guyana Time' => 'America/Guyana',
		'Hawaii Standard Time' => 'HST',
		'Hawaii-Aleutian Standard Time' => 'America/Adak',
		'Hong Kong Time' => 'Asia/Hong_Kong',
		'Hovd Time' => 'Asia/Hovd',
		'Chamorro Standard Time' => 'Pacific/Guam',
		'Chatham Standard Time' => 'NZ-CHAT',
		'Chile Time' => 'America/Santiago',
		'China Standard Time' => 'Asia/Chongqing',
		'Choibalsan Time' => 'Asia/Choibalsan',
		'Christmas Island Time' => 'Indian/Christmas',
		'India Standard Time' => 'Asia/Kolkata',
		'Indian Ocean Territory Time' => 'Indian/Chagos',
		'Indochina Time' => 'Asia/Bangkok',
		'Iran Standard Time' => 'Asia/Tehran',
		'Irkutsk Time' => 'Asia/Irkutsk',
		'Israel Standard Time' => 'Asia/Jerusalem',
		'Japan Standard Time' => 'Asia/Tokyo',
		'Kirgizstan Time' => 'Asia/Bishkek',
		'Korea Standard Time' => 'Asia/Pyongyang',
		'Kosrae Time' => 'Pacific/Kosrae',
		'Krasnoyarsk Time' => 'Asia/Krasnoyarsk',
		'Line Is. Time' => 'Pacific/Kiritimati',
		'Lord Howe Standard Time' => 'Australia/LHI',
		'Magadan Time' => 'Asia/Magadan',
		'Malaysia Time' => 'Asia/Kuala_Lumpur',
		'Maldives Time' => 'Indian/Maldives',
		'Marquesas Time' => 'Pacific/Marquesas',
		'Marshall Islands Time' => 'Kwajalein',
		'Mauritius Time' => 'Indian/Mauritius',
		'Mawson Time' => 'Antarctica/Mawson',
		'Middle Europe Time' => 'MET',
		'Moscow Standard Time' => 'Europe/Moscow',
		'Mountain Standard Time' => 'America/Boise',
		'Myanmar Time' => 'Asia/Rangoon',
		'Nauru Time' => 'Pacific/Nauru',
		'Nepal Time' => 'Asia/Kathmandu',
		'New Caledonia Time' => 'Pacific/Noumea',
		'New Zealand Standard Time' => 'Antarctica/McMurdo',
		'Newfoundland Standard Time' => 'America/St_Johns',
		'Niue Time' => 'Pacific/Niue',
		'Norfolk Time' => 'Pacific/Norfolk',
		'Novosibirsk Time' => 'Asia/Novosibirsk',
		'Omsk Time' => 'Asia/Omsk',
		'Oral Time' => 'Asia/Oral',
		'Pacific Standard Time' => 'America/Dawson',
		'Pakistan Time' => 'Asia/Karachi',
		'Palau Time' => 'Pacific/Palau',
		'Papua New Guinea Time' => 'Pacific/Port_Moresby',
		'Paraguay Time' => 'America/Asuncion',
		'Peru Time' => 'America/Lima',
		'Petropavlovsk-Kamchatski Time' => 'Asia/Kamchatka',
		'Philippines Time' => 'Asia/Manila',
		'Phoenix Is. Time' => 'Pacific/Enderbury',
		'Pierre & Miquelon Standard Time' => 'America/Miquelon',
		'Pitcairn Standard Time' => 'Pacific/Pitcairn',
		'Ponape Time' => 'Pacific/Ponape',
		'Qyzylorda Time' => 'Asia/Qyzylorda',
		'Reunion Time' => 'Indian/Reunion',
		'Rothera Time' => 'Antarctica/Rothera',
		'Sakhalin Time' => 'Asia/Sakhalin',
		'Samara Time' => 'Europe/Samara',
		'Samoa Standard Time' => 'Pacific/Midway',
		'Seychelles Time' => 'Indian/Mahe',
		'Singapore Time' => 'Asia/Singapore',
		'Solomon Is. Time' => 'Pacific/Guadalcanal',
		'South Africa Standard Time' => 'Africa/Johannesburg',
		'South Georgia Standard Time' => 'Atlantic/South_Georgia',
		'Suriname Time' => 'America/Paramaribo',
		'Syowa Time' => 'Antarctica/Syowa',
		'Tahiti Time' => 'Pacific/Tahiti',
		'Tajikistan Time' => 'Asia/Dushanbe',
		'Timor-Leste Time' => 'Asia/Dili',
		'Tokelau Time' => 'Pacific/Fakaofo',
		'Tonga Time' => 'Pacific/Tongatapu',
		'Truk Time' => 'Pacific/Truk',
		'Turkmenistan Time' => 'Asia/Ashgabat',
		'Tuvalu Time' => 'Pacific/Funafuti',
		'Ulaanbaatar Time' => 'Asia/Ulaanbaatar',
		'Uruguay Time' => 'America/Montevideo',
		'Uzbekistan Time' => 'Asia/Samarkand',
		'Vanuatu Time' => 'Pacific/Efate',
		'Venezuela Time' => 'America/Caracas',
		'Vladivostok Time' => 'Asia/Vladivostok',
		'Volgograd Time' => 'Europe/Volgograd',
		'Vostok Time' => 'Antarctica/Vostok',
		'Wake Time' => 'Pacific/Wake',
		'Wallis & Futuna Time' => 'Pacific/Wallis',
		'West Indonesia Time' => 'Asia/Jakarta',
		'West Samoa Time' => 'MIT',
		'Western African Time' => 'Africa/Bangui',
		'Western Argentine Time' => 'America/Argentina/San_Luis',
		'Western European Time' => 'Africa/Casablanca',
		'Western Greenland Time' => 'America/Godthab',
		'Western Standard Time (Australia)' => 'Antarctica/Casey',
		'Yakutsk Time' => 'Asia/Yakutsk',
		'Yekaterinburg Time' => 'Asia/Yekaterinburg'
    );

    /**
     * Microsoft kalendáře si definují vlastní timezony pojmenované jinak než systémové. 
     * Zatím nechci parsovat sekce VTIMEZONE, takže ošklivý hack.
     * Pokud najdeme zónu v tabulce $timeZoneFix, použijeme jí.
     */
    public static function fixTimeZone( $timeZone ) {
        
        if( $timeZone==='' ) return '';

        $out = $timeZone;
        
        if( isset(self::$timeZoneFix[$timeZone]) ) {
			$out = self::$timeZoneFix[$timeZone];
		}  

        try {
            $test = new DateTimeZone($out);
        } catch (Exception $e) {
            Logger::log( 'app', Logger::DEBUG, "  neznama timezona [{$out}]" );    
            $out = '';
        }

        return $out;
    }

    private function parseDate( $attributes, $parameter ) {
        $unknown = KeyValueAttribute::findUnknownKeys( $attributes, 'VALUE,TZID' );
        if( count($unknown)>0 ) {
            foreach( $unknown as $key ) {
                Logger::log( 'app', Logger::WARNING, "datum/cas: neznamy atribut {$key}" );    
            }
        }

        // DTSTART;VALUE=DATE:20230731
        if( "DATE"===KeyValueAttribute::findValue( $attributes, 'VALUE' ) ) {
            //D/ Logger::log( 'app', Logger::TRACE, "varianta VALUE=DATE" );    
            return DateTime::createFromFormat('Ymd H:i:s', $parameter . ' 00:00:00' );
        }

        // 20230922T220000Z
        $regexTZ = '/^[0-9]{8}T[0-9]{6}Z$/';
        if (preg_match($regexTZ, $parameter )) {
            //D/ Logger::log( 'app', Logger::TRACE, "varianta s Z timezonou" );    
            return DateTime::createFromFormat('Ymd\THis\Z', $parameter, 'Z'  );
        }

        // DTSTART;TZID=Europe/Prague:20230906T100000
        $regexTZ = '/^[0-9]{8}T[0-9]{6}$/';
        if (preg_match($regexTZ, $parameter )) {
            $tzone = KeyValueAttribute::findValue( $attributes, 'TZID' );
            $tzone2 = self::fixTimeZone($tzone);
            //D/ Logger::log( 'app', Logger::TRACE, "varianta bez timezony ve val; explicitni timezona=[{$tzone}]->[{$tzone2}]" );    
            return DateTime::createFromFormat('Ymd\THis', $parameter, $tzone2==="" ? null : $tzone2 );
        }

        Logger::log( 'app', Logger::WARNING, "datum/cas: nezpracovano {$parameter}" );    

        return null;
    }

    
    /*
        DTSTART;VALUE=DATE:20230731
        DTSTART;TZID=Europe/Prague:20230906T100000
        DTSTART:20230922T220000Z
    */
    public function setDtStart( $attributes, $parameter ) 
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "dtstart: {$parameter}" );    
        //D/ foreach ($attributes as $attr) {
        //D/     Logger::log( 'app', Logger::DEBUG, " - {$attr->key} = {$attr->value}" );    
        //D/ }

        $this->dtstart = $this->parseDate( $attributes, $parameter );
        if( $this->dtstart!=null ) {
            $tzone = new \DateTimeZone( date_default_timezone_get() );
            $this->dtstart->setTimezone( $tzone );
            //D/ Logger::log( 'app', Logger::DEBUG, "DTSTART: {$this->dtstart->format('r')}" );    
        }
    }

    /*
        DTEND;VALUE=DATE:20230731
        DTEND;TZID=Europe/Prague:20230906T100000
        DTEND:20230922T220000Z
    */
    public function setDtEnd( $attributes, $parameter ) 
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "dtend: {$parameter}" );    
        //D/ foreach ($attributes as $attr) {
        //D/     Logger::log( 'app', Logger::DEBUG, " - {$attr->key} = {$attr->value}" );    
        //D/ }
        
        $this->dtend = $this->parseDate( $attributes, $parameter );
        if( $this->dtend!=null ) {
            $tzone = new \DateTimeZone( date_default_timezone_get() );
            $this->dtend->setTimezone( $tzone );
            //D/ Logger::log( 'app', Logger::DEBUG, "DTEND: {$this->dtend->format('r')}" );    
        }

    }

    /*
    EXDATE;TZID=Europe/Prague:20231018T100000
    EXDATE;TZID=Europe/Prague:20231004T100000
    EXDATE;TZID=Central Europe Standard Time:20230316T113000,20230706T113000,20230720T113000,20230727T113000,20230803T113000,20230824T113000,20230928T113000,20231026T113000,20231228T113000

    */
    public function setExdate( $attributes, $parameter ) 
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "exdate: {$parameter}" );    
        //D/ foreach ($attributes as $attr) {
        //D/     Logger::log( 'app', Logger::DEBUG, " - {$attr->key} = {$attr->value}" );    
        //D/ }

        $parameters = explode( ',', $parameter );
        foreach( $parameters as $oneDate ) {
            $tst = $this->parseDate( $attributes, $oneDate );
            if( $tst!=null ) {
                $tzone = new \DateTimeZone( date_default_timezone_get() );
                $tst->setTimezone( $tzone );
                //D/ Logger::log( 'app', Logger::DEBUG, "EXDATE: {$tst->format('r')}" );    
                $this->exdates[] = $tst;
            }
        }
    }

    /*
    RRULE:FREQ=WEEKLY;WKST=MO;UNTIL=20231101T225959Z;BYDAY=WE
    RRULE:FREQ=WEEKLY;WKST=MO;COUNT=14;BYDAY=TU,TH
    RRULE:FREQ=MONTHLY;COUNT=3;BYMONTHDAY=18
    RRULE:FREQ=MONTHLY;BYDAY=3MO
    RRULE:FREQ=WEEKLY;UNTIL=20110626T130000Z;INTERVAL=2;BYDAY=WE
    */
    public function setRrule( $attributes ) 
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "rrule:" );    
        //D/ foreach ($attributes as $attr) {
        //D/     Logger::log( 'app', Logger::DEBUG, " - {$attr->key} = {$attr->value}" );    
        //D/ }

        $unknown = KeyValueAttribute::findUnknownKeys( $attributes, 'FREQ,WKST,UNTIL,COUNT,BYMONTHDAY,BYDAY,BYMONTH,INTERVAL' );
        if( count($unknown)>0 ) {
            foreach( $unknown as $key ) {
                Logger::log( 'app', Logger::WARNING, "rrule: neznamy atribut {$key}" );    
            }
            Logger::log( 'app', Logger::WARNING, "rrule:" );    
                foreach ($attributes as $attr) {
                Logger::log( 'app', Logger::WARNING, " - {$attr->key} = {$attr->value}" );    
            }
        }

        $this->rrule = $attributes;
    }

    public function setDescription( $text )
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "desc: {$text}" );    
        $this->description = $text;
    }

    public function setSummary( $text )
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "sum: {$text}" );    
        $this->summary = $text;
    }

    public function setLocation( $text )
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "loc: {$text}" );    
        $this->location = $text;
    }



}
