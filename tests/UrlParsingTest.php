<?php

/**
 * Testy zpracování URL kalendáře
 */

require 'bootstrap.php';
use Tester\Assert;
use App\Presenters\IcalPresenter;

class UrlParsingTest extends Tester\TestCase
{
	public function testWebcalHandling()
	{
		$url = 'https://outlook.live.com/owa/calendar/....';
		$url2 = IcalPresenter::prepareUrl( $url );
		Assert::equal( $url2, $url );

		$url = 'webcal://outlook.live.com/owa/calendar/....';
		$urlTarget = 'https://outlook.live.com/owa/calendar/....';
		$url2 = IcalPresenter::prepareUrl( $url );
		Assert::equal( $url2, $urlTarget );

	}

	public function testUrlValidity()
	{
		$config = new \App\Services\Config();
		$presenter = new IcalPresenter( null, null , $config );

		$url = 'https://www.seznam.cz/123456';
		Assert::false( $presenter->isValidUrl($url), $url );

		$url = 'https://p59-caldav.icloud.com/published/2/NDM2MjE4NzgxNDM2MjE4N3ZH2xrQSlqEf0RdPhrlA';
		Assert::true( $presenter->isValidUrl($url), $url );

		$url = 'https://calendar.google.com/calendar/ical/AAAA%40gmail.com/private-bbbbbbb51b2d990873dbbb/basic.ics';
		Assert::true( $presenter->isValidUrl($url), $url );

		$url = 'https://outlook.live.com/owa/calendar/00000000-0000-0000-0000-000000000000/66666-c568-4b54-bd39-65465454645/cid-ABCDE0123/calendar.ics';
		Assert::true( $presenter->isValidUrl($url), $url );

		// nechceme, aby proslo URL s maskovanym jinym serverem pomoci loginu
		$url = 'https://calendar.google.com/calendar/ical/AAAA%40gmail.com/private-bbbbbbb51b2d990873dbbb/basic.ics:heslo@utocnikuvserver.zlo';
		Assert::false( $presenter->isValidUrl($url), $url );

	}
}

// Spuštění testovacích metod
(new UrlParsingTest)->run();