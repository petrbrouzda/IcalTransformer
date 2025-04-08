<?php

/**
 * Časová zóna Eastern Standard Time
 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\IcalTools;

class PkloskoTest extends Tester\TestCase
{
	public function testPklosko()
	{
		$dateFrom = Nette\Utils\DateTime::from('2024-02-04');
		$dateTo = Nette\Utils\DateTime::from('2024-03-14');
		
		$handle = fopen('pklosko.ics','r+');
		$parser = new \App\Services\IcalParser($handle);
		$events = $parser->parse( $dateFrom, $dateTo );
		fclose($handle);

		$events = IcalTools::sortEvents( $events );

		var_dump( $events );

		Assert::equal( count($events), 1 );

		Assert::with($events[0], function () {
			Assert::equal( $this->getSummary(), 'Test' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2024-02-29 18:00:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2024-02-29 19:30:00 +01:00')->getTimestamp() ); 
		});


	}
}

// Spuštění testovacích metod
(new PkloskoTest)->run();