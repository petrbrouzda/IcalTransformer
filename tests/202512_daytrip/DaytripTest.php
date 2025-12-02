<?php

/**
Periodická schůzka každé úterý 13:00.
K její instanci 2.12. 13:00 je přesunutí na pondělí 1.12.
Když vypíšu kalendář od 1.12., vidím ji správně v pondělí (a v úterý není),
ale když vypíšu kalendář od 1.12., ta přesunovací instance se vůbec nezohlední
                                   a tak se schůzka vykreslí v úterý 13:00.
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\Logger;
use \App\Services\IcalTools;

class DaytripTest extends Tester\TestCase
{
	public function testPresunutePosledniSchuzky()
	{
		// Logger::log( 'app', Logger::DEBUG , "testEnddate()" ); 

		$dateFrom = Nette\Utils\DateTime::from('2025-12-02');
		$dateTo = Nette\Utils\DateTime::from('2025-12-03');
		
		$events = IcalTools::readEventsFromFile( 'jana-palo.ics', $dateFrom, $dateTo );
		usort( $events, function($first,$second){
			if( $first->getStart() < $second->getStart() ) return -1;
			if( $first->getStart() > $second->getStart() ) return 1;
			if( $first->getEnd() < $second->getEnd() ) return -1;
			if( $first->getEnd() > $second->getEnd() ) return 1;
			return 0;
		});

		var_dump( $events );

		Assert::equal( count($events), 0 );

	}

}

// Spuštění testovacích metod
(new DaytripTest)->run();