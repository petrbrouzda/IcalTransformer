<?php

/**
Pokud je
	DTSTART;VALUE=DATE:20250409
	DTEND;VALUE=DATE:20250416
tak událost je od 9.4.25 00:00:00 do 15.4.25 23:59.59.999,
ale 16.4. už v události není
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\Logger;
use \App\Services\IcalTools;

class EnddateTest extends Tester\TestCase
{
	/**
	 * V běžném výpise by měla být událost od '2025-04-09 00:00:00 +02:00' do '2025-04-16 00:00:00 +02:00'
	 */
	public function testEnddate()
	{
		// Logger::log( 'app', Logger::DEBUG , "testEnddate()" ); 

		$dateFrom = Nette\Utils\DateTime::from('2025-04-01');
		$dateTo = Nette\Utils\DateTime::from('2025-04-30');
		
		$events = IcalTools::readEventsFromFile( 'enddate.ics', $dateFrom, $dateTo );
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
			Assert::equal( $this->getSummary(), 'Test' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2025-04-09 00:00:00 +02:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2025-04-16 00:00:00 +02:00')->getTimestamp() ); 
		});

		$result = IcalTools::convertEventsToJson( $events, 'todayplus', false, Nette\Utils\DateTime::from('2025-03-30 04:15:32') );
		var_dump( $result );

		Assert::equal( $result[0]['time_start_t'], 'st 9.4.' ); 
		// tady je výsledek operace - úterý 15.4. a ne středa 16.4.
		Assert::equal( $result[0]['time_end_t'], 'út 15.4.' ); 
	}

	/** 
	 * ale pokud událost nekončí o půlnoci, operace se neprovádí
	 */
	public function testEnddate2()
	{
		// Logger::log( 'app', Logger::DEBUG , "testEnddate()" ); 

		$dateFrom = Nette\Utils\DateTime::from('2025-04-01');
		$dateTo = Nette\Utils\DateTime::from('2025-04-30');
		
		$events = IcalTools::readEventsFromFile( 'enddate2.ics', $dateFrom, $dateTo );
		usort( $events, function($first,$second){
			if( $first->getStart() < $second->getStart() ) return -1;
			if( $first->getStart() > $second->getStart() ) return 1;
			if( $first->getEnd() < $second->getEnd() ) return -1;
			if( $first->getEnd() > $second->getEnd() ) return 1;
			return 0;
		});

		var_dump( $events );

		Assert::equal( count($events), 3 );

		Assert::with($events[2], function () {
			Assert::equal( $this->getSummary(), 'Test' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2025-04-09 00:00:00 +02:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2025-04-16 08:30:00 +02:00')->getTimestamp() ); 
		});

		$result = IcalTools::convertEventsToJson( $events, 'todayplus', false, Nette\Utils\DateTime::from('2025-04-01 04:15:32') );
		var_dump( $result );

		Assert::equal( $result[0]['summary'], 'Dnes' ); 
		Assert::equal( $result[0]['time_start_t'], 'dnes' ); 
		Assert::equal( $result[0]['time_end_t'], 'dnes' ); 

		Assert::equal( $result[1]['summary'], 'Zitra' ); 
		Assert::equal( $result[1]['time_start_t'], 'zítra' ); 
		Assert::equal( $result[1]['time_end_t'], 'zítra' ); 

		Assert::equal( $result[2]['summary'], 'Test' ); 
		Assert::equal( $result[2]['time_start_t'], 'st 9.4.' ); 
		Assert::equal( $result[2]['time_end_t'], 'st 16.4. 08:30' ); 
	}

	/**
	 * Ve výpisu od 16.4. by být neměla
	 */
	public function testAfterEnd()
	{
		// Logger::log( 'app', Logger::DEBUG , "testAfterEnd()" ); 

		$dateFrom = Nette\Utils\DateTime::from('2025-04-16');
		$dateTo = Nette\Utils\DateTime::from('2025-04-30');

		$events = IcalTools::readEventsFromFile( 'enddate.ics', $dateFrom, $dateTo );
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
(new EnddateTest)->run();