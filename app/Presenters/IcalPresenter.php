<?php

/**
 * Prezenter.
 * - Pokud zaznam pro dane parametry nenajde v kesi:
 *      stahne aktualni soubor,
 *      zparsuje ho,
 *      vysledek ulozi do kese.
 * - Pokud najde, pouzije nakesovany.
 */

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils\Json;
use Nette\Utils\DateTime;

use \App\Services\Logger;
use \App\Services\IcalTools;
use \App\Services\SmartCache;

// je potreba kvuli konstantam
use Nette\Caching\Cache;

final class IcalPresenter extends Nette\Application\UI\Presenter
{
    use Nette\SmartObject;
    
    /** @var \App\Services\Downloader */
    private $downloader;

    /** @var \App\Services\SmartCache */
    private $cache;

    /** @var \App\Services\Config */
    private $config;

    public function __construct(?\App\Services\Downloader $downloader, ?\App\Services\SmartCache $cache , \App\Services\Config $config )
    {
        $this->downloader = $downloader;
        $this->cache = $cache;
        $this->config = $config;
    }




    public function renderForm()
    {
        // nic tu nedelame, je to jen HTML form
        $this->template->dateFrom = (new \DateTime())->modify('+1 day');
        $this->template->dateTo = (new \DateTime())->modify('+8 day');
    }


    public function isValidUrl( $url ) {

        if( substr($url,0,8) !== 'https://' ) {
            return false;
        }

        // nechceme, aby proslo URL s maskovanym jinym serverem pomoci loginu, tj. neco jako
		// https://calendar.google.com/calendar/ical/AAAA%40gmail.com/private-bbbbbbb51b2d990873dbbb/basic.ics:heslo@utocnikuvserver.net
        $p1 = strpos($url, '@');
        $p2 = strpos($url, ':', 9 );
        if( $p1!==false && $p2!==false && $p2<$p1 ) {
            Logger::log( 'app', Logger::WARNING ,  "URL vypada, ze obsahuje maskovane URL: [{$url}] {$p1} {$p2}" );
            return false;
        }

        foreach( $this->config->requiredUrlBases as $base ) {
            if( substr($url,0,strlen($base)) == $base ) {
                return true;
            }
        }

        // pro Apple to resime regexpem
        // https://p59-caldav.icloud.com/....
        preg_match(
            $this->config->requiredUrlBaseApple, 
            $url, 
            $output_array);
        if( isset($output_array[2]) ) {
            return true;
        }

        return false;
    }


    public static function prepareUrl( $url ) {

        // webcal://    
        if( substr($url,0,9)=='webcal://' ) {
            $url = 'https://' . substr($url,9);
        }
        return $url;

    }


    private function processData( $url, $dateFrom, $dateTo ) {

        $url = self::prepareUrl( $url );

        if( !$this->isValidUrl($url) ) {
            throw new \Exception( "URL musi byt validnim kalendarem Apple, Google ci Microsoft. Pokud chcete mit jine kalendare, nainstalujte si aplikaci u sebe a zmente konfiguraci. [{$url}]" );
        }

        $key = "o_{$url}_{$dateFrom}_{$dateTo}";
        $events = $this->cache->get( $key );
        if( $events==NULL ) {
            Logger::log( 'app', Logger::INFO ,  "parse: {$key}" );

            $data = $this->downloader->getData( $url );

            $handle = fopen('php://memory','r+');
            fwrite($handle, $data);
            rewind($handle);
    
            $parser = new \App\Services\IcalParser($handle);
            $events = $parser->parse( $dateFrom, $dateTo );
            fclose($handle);
            Logger::log( 'app', Logger::DEBUG ,  "name: {$parser->name}" );

            $this->cache->put( $key, $events,  [
                    Cache::EXPIRE => $this->config->outCacheValidity
                ]
            );
        } else {
            Logger::log( 'app', Logger::DEBUG ,  "out cache hit" );
        }

        return $events;
    }


    /**
     * Odstrani hacky a carky, pokud je to pozadovane.
     */
    private function textCnv( $text ) 
    {
        return !$this->odhackuj ? $text : iconv("utf-8", "us-ascii//TRANSLIT", $text );
    }


    


    public function renderData( $url, $mode, $format, $from, $to, $rangeDays, $htmlAllowed )
    {
        $time_start = microtime(true); 

        try {
            $this->template->error = null;
            $this->template->htmlAllowed = ( $htmlAllowed!=="no");

            if( $mode==="daterange") {
                Logger::log( 'app', Logger::DEBUG,  "+++ Req: daterange f={$format} [{$from}] - [{$to}], [{$this->getHttpRequest()->getRemoteAddress()}]" );
                $this->template->dateFrom = Nette\Utils\DateTime::from($from);
                $this->template->dateTo = Nette\Utils\DateTime::from($to);
            } else if( $mode==="todayplus" ) {
                Logger::log( 'app', Logger::DEBUG,  "+++ Req: todayplus f={$format} range={$rangeDays}, [{$this->getHttpRequest()->getRemoteAddress()}]" );
                $this->template->dateFrom = new Nette\Utils\DateTime();
                // zarovname na zacatek hodiny, aby se dalo kesovat
                $this->template->dateFrom->setTime( intval($this->template->dateFrom->format('G')), 0, 0, 0 );

                $param = '+' . (intval($rangeDays)+1) . ' day';
                $this->template->dateTo = $this->template->dateFrom->modifyClone( $param );
                $this->template->dateTo->setTime( 0, 0, 0, 0 );

                Logger::log( 'app', Logger::DEBUG,  "{$this->template->dateFrom} - {$this->template->dateTo}" );
            } else {
                Logger::log( 'app', Logger::INFO,  "+++ Req: unknown mode [{$mode}], [{$this->getHttpRequest()->getRemoteAddress()}]" );
                throw new \Exception( "unknown mode [{$mode}]");
            }

            $urls = explode( '|', $url );
            $this->template->urls = $urls;

            $events = array();
            
            foreach( $urls as $url ) {
                Logger::log( 'app', Logger::DEBUG,  "calendar {$url}" );
                $jednyEventy = $this->processData( $url, $this->template->dateFrom, $this->template->dateTo , $events );
                $events = array_merge( $events, $jednyEventy );
            }


            $events = IcalTools::sortEvents( $events );

            if( $format==="html" ) {
                $this->template->events = $events;
            }

            $result = array();

            // pokud je pozadavek na JSON vystup, provest transformaci
            if( $format==="json") {
                $result = IcalTools::convertEventsToJson( $events, $mode, $this->template->htmlAllowed, new Nette\Utils\DateTime() );
            }

            $time_end = microtime(true);
            $execution_time_ms = number_format( ($time_end - $time_start) * 1000.0, 1, '.', ' ' );
            $ct = count($events);
            Logger::log( 'app', Logger::DEBUG,  "OK, {$execution_time_ms} ms, {$ct} udalosti" );
            
            // pokud je pozadavek na JSON vystup
            if( $format==="json") {
                $response = $this->getHttpResponse();
                $response->setHeader('Cache-Control', 'no-cache');
                $response->setExpiration('1 sec'); 
                $this->sendJson($result);
            }

        } catch (\Nette\Application\AbortException $e ) {
            // normalni scenar pro sendJson()
            throw $e;
        } catch (\Exception $e) {
            Logger::log( 'app', Logger::ERROR,  "ERR: " . get_class($e) . ": " . $e->getMessage() . " [{$this->getHttpRequest()->getRemoteAddress()}]" );

            if( $format==="json" )  {
                $httpResponse = $this->getHttpResponse();
                $httpResponse->setCode(Nette\Http\Response::S500_INTERNAL_SERVER_ERROR );
                $httpResponse->setContentType('text/plain', 'UTF-8');
                $response = new \Nette\Application\Responses\TextResponse("ERR {$e->getMessage()}");
                $this->sendResponse($response);
                $this->terminate();
            } else {
                $this->template->error =  get_class($e) . ": " . $e->getMessage();
            }
        }
    }


}
