<?php

/**
 * Zpracování vícedenních událostí s ročním opakováním.
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\IcalTools;

class AzuTest extends Tester\TestCase
{
	public function testAzu1()
	{
		$dateFrom = Nette\Utils\DateTime::from('2024-01-01');
		$dateTo = Nette\Utils\DateTime::from('2024-01-08');
		
		$handle = fopen('azu.ics','r+');
		$parser = new \App\Services\IcalParser($handle);
		$events = $parser->parse( $dateFrom, $dateTo );
		fclose($handle);

		$events = IcalTools::sortEvents( $events );

		var_dump( $events );

		Assert::equal( count($events), 7 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), '-Zavřeno-' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-24 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-02 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-02 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-03 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[2], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-03 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-04 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[3], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-04 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-05 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[4], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-05 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-06 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[5], function () {
			Assert::equal( $this->getSummary(), '-Zavřeno-' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-06 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-07 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[6], function () {
			Assert::equal( $this->getSummary(), '-Zavřeno-' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-07 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-08 00:00:00 +01:00')->getTimestamp() ); 
		});

	}


	public function testAzu2()
	{
		$dateFrom = Nette\Utils\DateTime::from('2023-12-21');
		$dateTo = Nette\Utils\DateTime::from('2024-01-03');
		
		$handle = fopen('azu.ics','r+');
		$parser = new \App\Services\IcalParser($handle);
		$events = $parser->parse( $dateFrom, $dateTo );
		fclose($handle);

		$events = IcalTools::sortEvents( $events );

		var_dump( $events );

		Assert::equal( count($events), 5 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-21 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-22 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-22 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-23 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[2], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-23 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-24 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[3], function () {
			Assert::equal( $this->getSummary(), '-Zavřeno-' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-24 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-02 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[4], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-02 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-03 00:00:00 +01:00')->getTimestamp() ); 
		});

	}
}

// Spuštění testovacích metod
(new AzuTest)->run();