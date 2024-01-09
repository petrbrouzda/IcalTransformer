<?php

/**
 * Parsuje iCal soubor.
 * Ignoruje vše, co nezná.
 * Podporuje jen kalendářové události (VEVENT) a používá dialekt, který používá Google Calendar.
 */

declare(strict_types=1);

namespace App\Services;

use Nette;

use \App\Services\Logger;

class IcalParser
{
    use Nette\SmartObject;

    private $reader;
    private $events;

	public function __construct( $handle )
	{
        $this->reader = new \App\Services\IcalStreamReader($handle);
	}


    private function ignoreObject( $name )
    {
        //D/ Logger::log( 'app', Logger::TRACE, "    skipping object {$name}" );    
        while( $this->reader->nextLineAvailable() ) {
            $line = $this->reader->getLine();
            //D/ Logger::log( 'app', Logger::TRACE, "ignoreObject: {$this->reader->command} {$this->reader->firstParam}" );    
            if( $this->reader->command==="END" ) {
                //D/ Logger::log( 'app', Logger::TRACE, "    end of object {$this->reader->firstParam}" );    
                return;
            }
            if( $this->reader->command==="BEGIN" ) {
                $this->ignoreObject( $this->reader>firstParam );
            }
        }
    }


    private $commandAttributes;
    private $parameter;

    /*
    DTSTART;VALUE=DATE:20230731
    DTSTART;TZID=Europe/Prague:20230906T100000
    DTSTART:20230922T220000Z
    */
    /**
     * vytvori: $this->commandAttributes, $this->parameter
     */
    public function parse1a( $line ) {
        $this->commandAttributes = array();
        $this->parameter = "";

        $blocks = explode( ':', $line );
        if( isset($blocks[1]) ) $this->parameter = $blocks[1];

        $attributes = explode( ';', $blocks[0] );
        $i=1; // preskocime prvni, protoze to je zakladni prikaz
        while( true ) {
            if( !isset($attributes[$i]) ) break;

            $parts = explode( '=', $attributes[$i], 2 );
            $attr = new \App\Model\KeyValueAttribute( $parts[0], $parts[1] );
            $this->commandAttributes[] = $attr;

            $i++;
        }
    }

    /*
    RRULE:FREQ=WEEKLY;WKST=MO;UNTIL=20231101T225959Z;BYDAY=WE
    RRULE:FREQ=WEEKLY;UNTIL=20231224;BYDAY=FR,MO,TH,TU,WE
    */
    /**
     * vytvori: $this->commandAttributes
     */
    public function parse1b( $line ) {
        $this->commandAttributes = array();
        $this->parameter = "";

        $l = strlen($this->reader->command) + 1;
        $attributes = explode( ';', substr($line,$l) );
        $i=0; 
        while( true ) {
            if( !isset($attributes[$i]) ) break;

            $parts = explode( '=', $attributes[$i], 2 );
            $attr = new \App\Model\KeyValueAttribute( $parts[0], $parts[1] );
            $this->commandAttributes[] = $attr;

            $i++;
        }

    }

    /*
    DESCRIPTION:Chcete-li\, aby se vám zobrazovaly ... https://g.co/calendar\n\nTato událost byla vytvořena 
    */
    /**
     * vraci text
     */
    private function parseText( $line ) {
        $out = "";
        $i = strlen($this->reader->command) + 1;
        $isEscape = false;
        while($i<strlen($line)) {
            $c = substr( $line, $i, 1 );
            if( $isEscape ) {
                if( $c=='n' ) {
                    $out .= "\n";
                } else if( $c=='r' ) {
                    $out .= "\r"; 
                } else if( $c=='t' ) {
                    $out .= "\t"; 
                } else {
                    $out .= $c;
                }
                $isEscape = false;
            } else {
                if( $c == "\\" ) {
                    $isEscape = true;
                } else {
                    $out .= $c;
                }
            }
            $i++;
        }
        return $out;
    }

    /*
    class 1A 
    DTSTART;VALUE=DATE:20230731
    DTSTART:20230922T220000Z
    DTSTART;TZID=Europe/Prague:20230906T100000
    DTEND ekvivalentne
    EXDATE;TZID=Europe/Prague:20231018T100000
    EXDATE;TZID=Europe/Prague:20231004T100000
    EXDATE;VALUE=DATE:20231227
    RECURRENCE-ID;TZID=Europe/Prague:20231210T120000   

    class 1B 
    RRULE:FREQ=WEEKLY;WKST=MO;UNTIL=20231101T225959Z;BYDAY=WE
    RRULE:FREQ=WEEKLY;WKST=MO;COUNT=14;BYDAY=TU,TH

    class 2 (text)
    DESCRIPTION:popis<br><b>tučný popis</b><br>6.9. až 1.11.<br>týdně ve středu
    DESCRIPTION:Chcete-li\, aby se vám zobrazovaly ... https://g.co/calendar\n\nTato událost byla vytvořena 
    LOCATION:test místo
    SUMMARY:Vstupenka na stezky KPN\, Jednodenní jízdenka - normální\, 23.09.20 23 (DROP/TID/0E813F8FA3)
    UID:6147n7tlnj2scsk78hb2hp2mk4@google.com
    */

    private $dateFrom;
    private $dateToExclusive;

    private function readEventData()
    {
        $event = new \App\Model\IcalEvent();
        //D/ Logger::log( 'app', Logger::TRACE, "--- nova VEVENT ---" );    

        while( $this->reader->nextLineAvailable() ) {
            $line = $this->reader->getLine();
            //D/ Logger::log( 'app', Logger::TRACE, "   revda: {$this->reader->command} {$this->reader->firstParam}" );    
            if( $this->reader->command==="END" && $this->reader->firstParam==="VEVENT" ) {
                break;
            }
            if( $this->reader->command==="BEGIN" ) {
                $this->ignoreObject( $this->reader->firstParam );
            }
            if( $this->reader->command==="DTSTART" ) {
                $this->parse1a( $line );
                $event->setDtStart( $this->commandAttributes, $this->parameter );
            }
            if( $this->reader->command==="DTEND" ) {
                $this->parse1a( $line );
                $event->setDtEnd( $this->commandAttributes, $this->parameter );
            }
            if( $this->reader->command==="EXDATE" ) {
                $this->parse1a( $line );
                $event->setExdate( $this->commandAttributes, $this->parameter );
            }
            if( $this->reader->command==="RRULE" ) {
                $this->parse1b( $line );
                $event->setRrule( $this->commandAttributes );
            }
            if( $this->reader->command==="DESCRIPTION" ) {
                $event->setDescription( $this->parseText( $line ) );
            }
            if( $this->reader->command==="SUMMARY" ) {
                $event->setSummary( $this->parseText( $line ) );
            }
            if( $this->reader->command==="LOCATION" ) {
                $event->setLocation( $this->parseText( $line ) );
            }
            if( $this->reader->command==="UID" ) {
                $event->setUid( $this->parseText( $line ) );
            }
            if( $this->reader->command==="RECURRENCE-ID" ) {
                $this->parse1a( $line );
                $event->setRecurrenceId( $this->commandAttributes, $this->parameter );
            }
        }

        // overit, ze je v danem case, zapsat do vystupniho pole
        if( $event->isValid() ) {
            if( $event->isRecurrent() ) {
                // pro opakovane udalosti je vse slozitejsi
                if( $event->isRecurrentInDateRange( $this->dateFrom, $this->dateToExclusive ) ) {
                    //D/ Logger::log( 'app', Logger::TRACE, "recurrent udalost in range: {$event->getSummary()}" );  
                    // tuto udalost primo do vystupu nevkladame, ale nechame ji udelat jeji jednotlive vyskyty
                    $event->fillEventsInDateRanges( $this->dateFrom, $this->dateToExclusive, $this->events );
                } else {
                    //D/ Logger::log( 'app', Logger::TRACE, "recurrent udalost mimo range: {$event->getSummary()}" );    
                }
            } else {
                // jednorazove udalosti jsou trivialni
                if( $event->isInDateRange( $this->dateFrom, $this->dateToExclusive ) ) {
                    //D/ Logger::log( 'app', Logger::TRACE, "udalost in range: {$event->getSummary()}" );    
                    // nezapisuju primo do pole, aby se vyhodnotilo pripadne prepsani drivejsi udalosti
                    $event->writeIntoArray( $this->events );
                } else {
                    //D/ Logger::log( 'app', Logger::TRACE, "udalost mimo range: {$event->getSummary()}" );    
                }
            }
        }
    }

    public $name = '';
    public $description = '';
    public $timezone = '';

    private function readCalendarData()
    {
        while( $this->reader->nextLineAvailable() ) {
            $line = $this->reader->getLine();
            //D/ Logger::log( 'app', Logger::TRACE, "readCalendarData: {$this->reader->command} {$this->reader->firstParam}" );    
            if( $this->reader->command==='X-WR-CALNAME' ) {
                $this->name = $this->parseText( $line );
            }
            if( $this->reader->command==='X-WR-TIMEZONE' ) {
                $this->timezone = $this->parseText( $line );
            }
            if( $this->reader->command==='X-WR-CALDESC' ) {
                $this->description = $this->parseText( $line );
            }
            if( $this->reader->command==='BEGIN' && $this->reader->firstParam==='VEVENT' ) {
                $this->readEventData();
            } 
        }
    }

    /**
     * v "events" musi dostat pripravene pole; to je nutne pro moznost scitani vice kalendaru
     */
    public function parse( $dateFrom, $dateToExclusive ) {
        $this->dateFrom = $dateFrom;
        $this->dateToExclusive = $dateToExclusive;
        $this->events = array();

        while( $this->reader->nextLineAvailable() ) {
            $line = $this->reader->getLine();
            //D/ Logger::log( 'app', Logger::TRACE, "parse: {$this->reader->command} {$this->reader->firstParam}" );    
            if( $this->reader->command==="BEGIN" && $this->reader->firstParam==="VCALENDAR" ) {
                $this->readCalendarData( $dateFrom, $dateToExclusive );
            }
        }
        //D/ $ct = count($this->events);
        //D/ Logger::log( 'app', Logger::DEBUG, "nacteno {$ct} udalosti" );    
        return $this->events;
    }

}