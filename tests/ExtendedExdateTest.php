<?php

/**
 * V EXDATE smi být více výjimek (alespoň u MS kalendářů se to vyskytuje)
 * EXDATE;TZID=Central Europe Standard Time:20230316T113000,20230706T113000,20230720T113000
 */

require 'bootstrap.php';
use Tester\Assert;
use App\Model\IcalEvent;
use App\Services\IcalParser;

class UrlParsingTest extends Tester\TestCase
{
	public function testExdate1()
	{
		$parser = new IcalParser( null );

		$event = new IcalEvent();
		$parser->parse1a( 'EXDATE;TZID=Central Europe Standard Time:20230316T113000' );
		$event->setExdate( $parser->commandAttributes, $parser->parameter );		

		var_dump( $event );

		Assert::with($event, function () {
			Assert::equal( count($this->exdates), 1 ); 
			Assert::equal( $this->exdates[0]->getTimestamp(), Nette\Utils\DateTime::from('2023-03-16 11:30:00 +01:00')->getTimestamp() ); 
		});

		$event = new IcalEvent();
		$parser->parse1a( 'EXDATE;TZID=Central Europe Standard Time:20230316T113000,20230706T113000,20230720T113000' );
        $event->setExdate( $parser->commandAttributes, $parser->parameter );	
		
		var_dump( $event );

		Assert::with($event, function () {
			Assert::equal( count($this->exdates), 3 ); 
			Assert::equal( $this->exdates[0]->getTimestamp(), Nette\Utils\DateTime::from('2023-03-16 11:30:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->exdates[1]->getTimestamp(), Nette\Utils\DateTime::from('2023-07-06 11:30:00 +02:00')->getTimestamp() ); 
			Assert::equal( $this->exdates[2]->getTimestamp(), Nette\Utils\DateTime::from('2023-07-20 11:30:00 +02:00')->getTimestamp() ); 
		});
		
	}
}

// Spuštění testovacích metod
(new UrlParsingTest)->run();