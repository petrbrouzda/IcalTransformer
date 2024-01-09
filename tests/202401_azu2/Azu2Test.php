<?php

/**
 * Když mám každý den celodenní událost od 00:00:00 do D+1 00:00:00 a dám zobrazit všechny dnešní události, ukáže se mi jako první včerejší událost (končící dnešními 00:00:00).
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;

class Azu2Test extends Tester\TestCase
{
	public function testAzu1()
	{
		$dateFrom = Nette\Utils\DateTime::from('2024-01-05');
		$dateTo = Nette\Utils\DateTime::from('2024-01-07');
		
		$handle = fopen('azu2.ics','r+');
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

		Assert::equal( $parser->name, 'Vokolo.cz' );
		Assert::equal( $parser->description, 'Kamenná prodejna Vokolo.cz. Prodej Arduino, ESP a filamentů pro 3D tisk.' );
		Assert::equal( $parser->timezone, 'Europe/Prague' );

		Assert::equal( count($events), 2 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Otevřeno 10:00-12:20 a 13:20-17:30' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-05 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-06 00:00:00 +01:00')->getTimestamp() ); 
		});

		Assert::with($events[1], function () {
			Assert::equal( $this->getSummary(), '-Zavřeno-' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-06 00:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-01-07 00:00:00 +01:00')->getTimestamp() ); 
		});

	}
}

// Spuštění testovacích metod
(new Azu2Test)->run();