<?php

/**
 * Microsoft kalendáře si definují vlastní timezony pojmenované jinak než systémové. Zatím nechci parsovat sekce VTIMEZONE, takže ošklivý hack.
 */

require '../bootstrap.php';
use Tester\Assert;
use App\Services\IcalParser;
use App\Model\IcalEvent;

class MsTest extends Tester\TestCase
{
	public function testZoneReplacing() {
		Assert::equal( IcalEvent::fixTimeZone(''), '' );
		Assert::equal( IcalEvent::fixTimeZone('Europe/Prague'), 'Europe/Prague' );
		Assert::equal( IcalEvent::fixTimeZone('Central Europe Standard Time'), 'Europe/Prague' );
	}

	public function testMscal()
	{
		$dateFrom = Nette\Utils\DateTime::from('2024-01-04');
		$dateTo = Nette\Utils\DateTime::from('2024-01-10');
		
		$handle = fopen('calendar.ics','r+');
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

		Assert::equal( $parser->name, 'Kalendář' );

		Assert::equal( count($events), 3 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Šlofíček' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-08 14:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-08 14:30:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), 'Nešlofíček' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-09 08:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-09 18:30:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[2], function () {
			Assert::equal( $this->getSummary(), 'Další událost' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-09 14:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-09 14:30:00 +01:00')->getTimestamp() ); 
		});

	}
}

// Spuštění testovacích metod
(new MsTest)->run();