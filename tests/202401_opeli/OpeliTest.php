<?php

/**
 * Opakované týdenní události bez zadaného BYDATE.
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\IcalTools;

class OpeliTest extends Tester\TestCase
{
	public function testOpeli()
	{
		$dateFrom = Nette\Utils\DateTime::from('2024-01-04');
		$dateTo = Nette\Utils\DateTime::from('2024-01-14');

		$events = IcalTools::readEventsFromFile( 'opeli.ics', $dateFrom, $dateTo );
		$events = IcalTools::sortEvents( $events );

		var_dump( $events );

		Assert::equal( count($events), 3 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Mozna ABC' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-08 17:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-08 23:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'Kone' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-09 15:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-09 17:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[2], function () {
			Assert::equal( $this->getSummary(), 'Fila - 17h Lego' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-10 17:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-10 18:00:00 +01:00')->getTimestamp() ); 
		});

	}
}

// Spuštění testovacích metod
(new OpeliTest)->run();