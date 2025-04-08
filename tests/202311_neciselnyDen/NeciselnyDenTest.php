<?php

/**
 * Testy pro měsíční opakování se dnem zadaným nečíselně:
 * RRULE:FREQ=MONTHLY;WKST=MO;BYMONTHDAY=-1 = poslední den v měsíci
 * RRULE:FREQ=MONTHLY;BYDAY=3TH = třetí čtvrtek v měsíci
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\IcalTools;

class NeciselnyDenTest extends Tester\TestCase
{
	public function testNeciselnyDen202312()
	{
		$dateFrom = Nette\Utils\DateTime::from('2023-12-01');
		$dateTo = Nette\Utils\DateTime::from('2024-01-01');
		
		$events = IcalTools::readEventsFromFile( 'NeciselnyDen.ics', $dateFrom, $dateTo );
		$events = IcalTools::sortEvents( $events );

		var_dump( $events );

		Assert::equal( count($events), 2 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Platba DPH' ); 
			// RRULE:FREQ=MONTHLY;BYDAY=3TH 
			// 3. ctvrtek v prosinci
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-21 12:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-21 13:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'Timetracker konec měsíce' ); 
			// RRULE:FREQ=MONTHLY;WKST=MO;BYMONTHDAY=-1
			// posledni den prosince
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-31 11:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2023-12-31 12:00:00 +01:00')->getTimestamp() ); 
		});

	}

	public function testNeciselnyDen202402()
	{
		$dateFrom = Nette\Utils\DateTime::from('2024-02-01');
		$dateTo = Nette\Utils\DateTime::from('2024-03-01');
		
		$events = IcalTools::readEventsFromFile( 'NeciselnyDen.ics', $dateFrom, $dateTo );
		$events = IcalTools::sortEvents( $events );

		var_dump( $events );

		Assert::equal( count($events), 2 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Platba DPH' ); 
			// RRULE:FREQ=MONTHLY;BYDAY=3TH 
			// 3. ctvrtek v unoru
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-02-15 12:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-02-15 13:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'Timetracker konec měsíce' ); 
			// RRULE:FREQ=MONTHLY;WKST=MO;BYMONTHDAY=-1
			// posledni den prosince
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-02-29 11:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-02-29 12:00:00 +01:00')->getTimestamp() ); 
		});

	}

	
}

// Spuštění testovacích metod
(new NeciselnyDenTest)->run();