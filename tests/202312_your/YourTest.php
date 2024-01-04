<?php

/**
 * Testuje spravne zpracování výjimky v opakované události zadané pomocí nové události se stejným UID a RECURRENCE-ID.
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;

class YourTest extends Tester\TestCase
{
	public function testYour()
	{
		$dateFrom = Nette\Utils\DateTime::from('2023-12-08');
		$dateTo = Nette\Utils\DateTime::from('2023-12-12');
		
		$handle = fopen('your.ics','r+');
		$parser = new \App\Services\IcalParser($handle);
		$events = $parser->parse( $dateFrom, $dateTo );
		fclose($handle);

		usort( $events, function($first,$second){
			if( $first->getStart() < $second->getStart() ) return -1;
			if( $first->getStart() > $second->getStart() ) return 1;
			if( $first->getEnd() < $second->getEnd() ) return -1;
			if( $first->getEnd() > $second->getEnd() ) return 1;
			return 0;
		});

		var_dump( $events );

		Assert::equal( count($events), 4 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'test denne 12h' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-08 12:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-08 13:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'test denne 12h' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-09 12:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-09 13:00:00 +01:00')->getTimestamp() ); 
		});

		// tady je vyjimka, presun na 10:00
		Assert::with($events[2], function () {
			Assert::equal( $this->getSummary(), 'test denne 12h' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-10 10:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-10 11:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[3], function () {
			Assert::equal( $this->getSummary(), 'test denne 12h' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-11 12:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-11 13:00:00 +01:00')->getTimestamp() ); 
		});

	}


	
}

// Spuštění testovacích metod
(new YourTest)->run();