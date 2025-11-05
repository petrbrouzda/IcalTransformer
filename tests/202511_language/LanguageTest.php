<?php

/**
Pokud se záznam založí v Outlooku na iPhone, do položek vloží i definici jazyka "LANGUAGE=cs-CZ:O Maďarsko"
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\Logger;
use \App\Services\IcalTools;

class LanguageTest extends Tester\TestCase
{
	public function testEnddate()
	{
		// Logger::log( 'app', Logger::DEBUG , "testEnddate()" ); 

		$dateFrom = Nette\Utils\DateTime::from('2025-10-27');
		$dateTo = Nette\Utils\DateTime::from('2025-11-15');
		
		$events = IcalTools::readEventsFromFile( 'language.ics', $dateFrom, $dateTo );
		usort( $events, function($first,$second){
			if( $first->getStart() < $second->getStart() ) return -1;
			if( $first->getStart() > $second->getStart() ) return 1;
			if( $first->getEnd() < $second->getEnd() ) return -1;
			if( $first->getEnd() > $second->getEnd() ) return 1;
			return 0;
		});

		var_dump( $events );

		Assert::equal( count($events), 1 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'O: Maďarsko' ); 
			Assert::equal( $this->getDescription(), 'XXX' ); 
		});

		$result = IcalTools::convertEventsToJson( $events, 'todayplus', false, Nette\Utils\DateTime::from('2025-03-30 04:15:32') );
		var_dump( $result );

	}

}

// Spuštění testovacích metod
(new LanguageTest)->run();