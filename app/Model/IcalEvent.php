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

class IcalEvent
{
    use Nette\SmartObject;

    private $dowMapperToText = array(
        'MO' => 'Monday',
        'TU' => 'Tuesday',
        'WE' => 'Wednesday',
        'TH' => 'Thursday',
        'FR' => 'Friday',
        'SA' => 'Saturday',
        'SU' => 'Sunday'
    );

    private $orderToText = array(
        '-1' => 'Last',
        '1' => 'First',
        '2' => 'Second',
        '3' => 'Third',
        '4' => 'Fourth',
        '5' => 'Fifth'
    );

    private $dowMapperToStr = array(
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

    private function __toString() {
        return "IcalEvent from:[{$this->dtstart}] to:[{$this->dtend}] summary:[{$this->summary}]";
    }

    private function init( $start, $end, $summary, $description, $location )
    {
        $this->dtstart = $start;
        $this->dtend = $end;
        $this->summary = $summary;
        $this->description = $description;
        $this->location = $location;
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
        $text = str_replace( '<p>', " \n", $text );
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
            // 20231101T225959Z
            $untilDate = DateTime::createFromFormat('Ymd\THis\Z', $until, 'Z'  );
            //D/ Logger::log( 'app', Logger::TRACE, " isrd: kontroluji until {$untilDate}" );    
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
                    Logger::log( 'app', Logger::WARNING, "neimplementovano: pro RRULE:FREQ=WEEKLY nenalezeno BYDAY" );   
                    return false;    
                }
                $pocetTydne = count( explode( ',', $days ) );
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
    */
    private function fillEventsInDateRangesWeekly( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $interval ) {

        // nemusime resit neexistenci polozky, to zkontrolovala isRecurrentInDateRange()
        $days = KeyValueAttribute::findValue( $this->rrule, 'BYDAY' );
        $daysArray = explode( ',', $days );
        //D/ Logger::log( 'app', Logger::TRACE, $daysArray );    

        // kontrola, zda tam neni neco neznameho
        foreach( $daysArray as $dx ) {
            if( ! in_array( $dx, $this->dowMapperToStr, true ) ) {
                Logger::log( 'app', Logger::WARNING, "neimplementovane RRULE:BYDAY={$dx}" );   
                return;
            }
        }

        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        $weekNo = 0;
        while( true ) {
            $dow =  $this->dowMapperToStr[ $date->format('N') ];

            // ma se generovat tento tyden?
            $thisWeekOK = (($weekNo % $interval) == 0);
            
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola dow={$dow} weekno={$weekNo} weekOK=". ($thisWeekOK?"Y":"N") ." - {$date}" );    

            if( in_array($dow, $daysArray, true ) ) {
                // tento den je v seznamu opakovacich dni
                $ctEvents++;

                // zkontrolovat proti EXDATE
                $excluded = false;
                foreach( $this->exdates as $exdate ) {
                    if( $exdate == $date ) {
                        //D/ Logger::log( 'app', Logger::TRACE, "-- excluded: {$exdate}" );    
                        $excluded = true;
                        break;
                    }
                }

                // zapisujeme jen pokud se to prekryva s pozadovanym oknem
                if( $thisWeekOK && !$excluded && ($date >= $from) ) {
                    $end = $date->modifyClone( "+ {$delkaSec} sec" );
                    $oneEvent = new \App\Model\IcalEvent();
                    $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location );
                    //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                    $events[] = $oneEvent;               
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
            if( $date > $to || ($untilDate!=null && $date>$untilDate) ) {
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
        if( !isset($this->orderToText[$dayPos]) || !isset($this->dowMapperToText[$dayAbbrev]) ) {
            Logger::log( 'app', Logger::WARNING, "neznama varianta RRULE:BYDAY {$dayPos} / {$dayAbbrev}" );   
        }
        $command = "{$this->orderToText[$dayPos]} {$this->dowMapperToText[$dayAbbrev]} of ";
        //D/ Logger::log( 'app', Logger::TRACE, "RRULE:BYDAY {$dayPos} / {$dayAbbrev} -> [{$command}]" );   

        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        while( true ) {
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date}" );    
            $ctEvents++;

            // zkontrolovat proti EXDATE
            $excluded = false;
            foreach( $this->exdates as $exdate ) {
                if( $exdate == $date ) {
                    //D/ Logger::log( 'app', Logger::TRACE, "-- excluded: {$exdate}" );    
                    $excluded = true;
                    break;
                }
            }

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( !$excluded && ($date >= $from) ) {
                $end = $date->modifyClone( "+ {$delkaSec} sec" );
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $events[] = $oneEvent;               
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
    */
    private function fillEventsInDateRangesMonthlyBymonthday( $from, $to, &$events, $untilDate, $maxCount, $delkaSec, $monthDay, $interval ) {
        $date = $this->dtstart->modifyClone();
        $ctEvents = 0;
        while( true ) {
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date}" );    
            $ctEvents++;

            // zkontrolovat proti EXDATE
            $excluded = false;
            foreach( $this->exdates as $exdate ) {
                if( $exdate == $date ) {
                    //D/ Logger::log( 'app', Logger::TRACE, "-- excluded: {$exdate}" );    
                    $excluded = true;
                    break;
                }
            }

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( !$excluded && ($date >= $from) ) {
                $end = $date->modifyClone( "+ {$delkaSec} sec" );
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $events[] = $oneEvent;               
            }


            $newMonth = intval($date->format('n'));
            $newYear = intval($date->format('Y'));
            while(true) {
                $newMonth+=$interval;
                while( $newMonth > 12 ) {
                    $newMonth = $newMonth - 12;
                    $newYear++;
                }
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
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date}" );    
            $ctEvents++;

            // zkontrolovat proti EXDATE
            $excluded = false;
            foreach( $this->exdates as $exdate ) {
                if( $exdate == $date ) {
                    //D/ Logger::log( 'app', Logger::TRACE, "-- excluded: {$exdate}" );    
                    $excluded = true;
                    break;
                }
            }

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( !$excluded && ($date >= $from) ) {
                $end = $date->modifyClone( "+ {$delkaSec} sec" );
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $events[] = $oneEvent;               
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
            //D/ Logger::log( 'app', Logger::TRACE, "    ? kontrola {$date}" );    
            $ctEvents++;

            // zkontrolovat proti EXDATE
            $excluded = false;
            foreach( $this->exdates as $exdate ) {
                if( $exdate == $date ) {
                    //D/ Logger::log( 'app', Logger::TRACE, "-- excluded: {$exdate}" );    
                    $excluded = true;
                    break;
                }
            }

            // zapisujeme jen pokud se to prekryva s pozadovanym oknem
            if( !$excluded && ($date >= $from) ) {
                $end = $date->modifyClone( "+ {$delkaSec} sec" );
                $oneEvent = new \App\Model\IcalEvent();
                $oneEvent->init( $date->modifyClone(), $end, $this->summary, $this->description, $this->location );
                //D/ Logger::log( 'app', Logger::TRACE, "zapisuji udalost: {$oneEvent}" );    
                $events[] = $oneEvent;               
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
     * Pouze pro opakovane udalosti. Zaplni udalosti v urcenem casovem okne do pole &events.
     */
    public function fillEventsInDateRanges( $from, $to, &$events )
    {
        $untilDate = null;
        $until = KeyValueAttribute::findValue( $this->rrule, 'UNTIL' );
        if( $until !== '' ) {
            // 20231101T225959Z
            $untilDate = DateTime::createFromFormat('Ymd\THis\Z', $until, 'Z'  );
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


    public function isInDateRange( $from, $toExclusive )
    {
        return ( ($this->getStart() < $toExclusive) && ($this->getEnd())>=$from );
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
            //D/ Logger::log( 'app', Logger::TRACE, "varianta bez timezony ve val; explicitni timezona={$tzone}" );    
            return DateTime::createFromFormat('Ymd\THis', $parameter, $tzone==="" ? null : $tzone );
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
    */
    public function setExdate( $attributes, $parameter ) 
    {
        //D/ Logger::log( 'app', Logger::DEBUG, "exdate: {$parameter}" );    
        //D/ foreach ($attributes as $attr) {
        //D/     Logger::log( 'app', Logger::DEBUG, " - {$attr->key} = {$attr->value}" );    
        //D/ }

        $tst = $this->parseDate( $attributes, $parameter );
        if( $tst!=null ) {
            $tzone = new \DateTimeZone( date_default_timezone_get() );
            $tst->setTimezone( $tzone );
            //D/ Logger::log( 'app', Logger::DEBUG, "EXDATE: {$tst->format('r')}" );    
            $this->exdates[] = $tst;
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
