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

    public function __construct(\App\Services\Downloader $downloader, \App\Services\SmartCache $cache , \App\Services\Config $config )
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


    private function processData( $url, $dateFrom, $dateTo ) {

        if( substr($url,0,strlen($this->config->requiredUrlBase)) !== $this->config->requiredUrlBase ) {
            throw new \Exception( "URL musi zacinat {$this->config->requiredUrlBase} . Pokud chcete mit jine kalendare, nainstalujte si aplikaci u sebe a zmente konfiguraci." );
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


    private $dny = [ ' ', 'po', 'út', 'st', 'čt', 'pá', 'so', 'ne' ];
    private $dnyDlouhe = [ ' ', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle' ];

    private function hezkeDatum( $date )
    {
        $today = new Nette\Utils\DateTime();
        $today->setTime( 0, 0, 0, 0 );

        $dateT = $date->format('Y-m-d');

        if( strcmp( $today->format('Y-m-d') , $dateT)==0 ) {
            return "" . $date->format('H:i');
        }

        if( strcmp( $today->modifyClone('+1 day')->format('Y-m-d') , $dateT)==0 ) {
            return "zítra " . $date->format('H:i');
        }

        $datum = '';

        if( $today->getTimestamp() >= $date->getTimestamp() ) {
            // v minulosti
            $datum = $this->dny[$date->format('N')] . ' ' . $date->format( 'j.n.' );
        } else {
            // v budoucnosti
            $interval = $today->diff( $date );
            $days = $interval->y * 365 + $interval->m * 31 + $interval->d + 1;
            if( $days < 6 ) {
                $datum = $this->dnyDlouhe[$date->format('N')];
            } else {
                $datum = $this->dny[$date->format('N')] . ' ' . $date->format( 'j.n.' );
            }
        }
        $cas = $date->format('H:i');
        if( $cas==='00:00' ) {
            // zacina o pulnoci, nebudeme cas udavat
        } else {
            $datum = $datum . ' ' . $cas;
        }
        return $datum;
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
                
                preg_match(
                    '/^(https:\/\/calendar\.google\.com\/calendar\/ical\/)([A-Za-z\.\%0-9\-\_]+)(.*)$/', 
                    $url, 
                    $output_array);
                if( isset($output_array[2]) ) {
                    // tohle by se melo vzdy vypisovat
                    Logger::log( 'app', Logger::DEBUG,  "calendar {$output_array[2]}" );
                } else {
                    // tohle jen pri zadani spatneho kalendare
                    Logger::log( 'app', Logger::DEBUG,  "calendar {$url}" );
                }
                // logovani pro pripad hledani chyby
                Logger::log( 'app', Logger::TRACE,  "calendar {$url}" );

                $jednyEventy = $this->processData( $url, $this->template->dateFrom, $this->template->dateTo , $events );
                $events = array_merge( $events, $jednyEventy );
            }

            usort( $events, function($first,$second){
                if( $first->getStart() < $second->getStart() ) return -1;
                if( $first->getStart() > $second->getStart() ) return 1;
                if( $first->getEnd() < $second->getEnd() ) return -1;
                if( $first->getEnd() > $second->getEnd() ) return 1;
                return 0;
            });

            if( $format==="html" ) {
                $this->template->events = $events;
            }

            $result = array();

            // pokud je pozadavek na JSON vystup, provest transformaci
            if( $format==="json") {
                foreach( $events as $event ) {
                    $rc = array();
                    $rc['description'] = $event->getDescription( $this->template->htmlAllowed );
                    $rc['location'] = $event->getLocation( $this->template->htmlAllowed );
                    $rc['summary'] = $event->getSummary( $this->template->htmlAllowed );
                    $rc['time_start_i'] = $event->getStart()->format('c');
                    $rc['time_start_e'] = $event->getStart()->getTimestamp();

                    if( $mode==="todayplus" ) {
                        // pokud se ptáme ode dneška a začátek je v minulosti, speciální formátování
                        $dnesek = new Nette\Utils\DateTime();
                        $dnesek->setTime( 0, 0, 0, 0 );
                        if( $dnesek->getTimestamp() > $event->getStart()->getTimestamp() ) {
                            $interval = $event->getStart()->diff( new Nette\Utils\DateTime() );
                            // zakladni vypocet, o presnost nejde
                            $days = $interval->y * 365 + $interval->m * 31 + $interval->d + 1;
                            $rc['time_start_t'] = "({$days}. den)";
                        } else {
                            $rc['time_start_t'] = $this->hezkeDatum( $event->getStart() );
                        }
                    } else {
                        $rc['time_start_t'] = $this->hezkeDatum( $event->getStart() );
                    }

                    $rc['time_end_i'] = $event->getEnd()->format('c');
                    $rc['time_end_e'] = $event->getEnd()->getTimestamp();
                    $rc['time_end_t'] = $this->hezkeDatum( $event->getEnd() );

                    // speciální formátování pro jednodenní celodenní události - nebude fungovat pro dny se změnou času
                    if( $rc['time_end_e']-$rc['time_start_e']==86400) {
                        if( $rc['time_start_t']==='00:00' ) {
                            $rc['time_start_t']='dnes';
                            $rc['time_end_t']='dnes';
                        }
                        if( $rc['time_start_t']==='zítra 00:00' ) {
                            $rc['time_start_t']='zítra';
                            $rc['time_end_t']='zítra';
                        }
                    }

                    $result[] = $rc;
                }
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
