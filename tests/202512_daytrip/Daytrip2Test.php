<?php

/**
Schůzku jeden z povinných účastníků odmítne, tedy se nekoná, nemá být v kalendáři.
 */

/*
ORGANIZER;CN=karina.fidelman@daytrip.com:mailto:karina.fidelman@daytrip.com
UID:3pg3l6gajc5ijia09v68ak425t_R20250623T133000@google.com
ATTENDEE;CUTYPE=RESOURCE;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;CN=MERIDA r
 oom (4);X-NUM-GUESTS=0:mailto:c_1887j4fbf6l56j57jsjj0vo5k9q4g@resource.cale
 ndar.google.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;CN=tomasr
 umian@daytrip.com;X-NUM-GUESTS=0:mailto:tomasrumian@daytrip.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;CN=joy@da
 ytrip.com;X-NUM-GUESTS=0:mailto:joy@daytrip.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=DECLINED;CN=karina
 .fidelman@daytrip.com;X-NUM-GUESTS=0:mailto:karina.fidelman@daytrip.com

ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=DECLINED

 */

require '../bootstrap.php';
use Tester\Assert;
use \App\Services\IcalParser;
use \App\Services\Logger;
use \App\Services\IcalTools;

class Daytrip2Test extends Tester\TestCase
{
	public function testOdmitnutiSchuzky()
	{
		$dateFrom = Nette\Utils\DateTime::from('2025-12-01');
		$dateTo = Nette\Utils\DateTime::from('2025-12-02');
		
		$events = IcalTools::readEventsFromFile( 'odmitnuta.ics', $dateFrom, $dateTo );
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
			Assert::equal( $this->getSummary(), 'ma projit' ); 
			Assert::equal( $this->getStart()->getTimestamp(), Nette\Utils\DateTime::from('2025-12-01 16:30:00 +01:00')->getTimestamp() ); 
			Assert::equal( $this->getEnd()->getTimestamp(), Nette\Utils\DateTime::from('2025-12-01 17:30:00 +01:00')->getTimestamp() ); 
		});
	}


}

// Spuštění testovacích metod
(new Daytrip2Test)->run();