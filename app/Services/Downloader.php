<?php

/**
 * Zajisti stazeni souboru ze serveru a ulozeni do pracovniho adresare.
 * Pokud se stazeni nepodari a v adresari je stara verze, vrati alespon ji.
 */

declare(strict_types=1);

namespace App\Services;

use Nette;
use Nette\Utils\Strings;
use Nette\Utils\DateTime;

use \App\Services\Logger;

use \App\Services\SmartCache;
// je potreba kvuli konstantam
use Nette\Caching\Cache;

class Downloader 
{
    use Nette\SmartObject;

    /** @var \App\Services\Config */
    private $config;

    /** @var \App\Services\SmartCache */
    private $cache;
    
	public function __construct( \App\Services\Config $config, \App\Services\SmartCache $cache )
	{
        $this->config = $config;
        $this->cache = $cache;
	}

    private function download( $url )
    {
        $ua = "IcalTransformer; {$_SERVER['SERVER_NAME']}; github.com/petrbrouzda/IcalTransformer";

        Logger::log( 'app', Logger::DEBUG ,  "dwnl: stahuji $url [$ua]" ); 

        set_error_handler(
            function ($severity, $message, $file, $line) {
                throw new \Exception( "Nemohu stahnout kalendar: {$message}" );
            }
        );

        $data = file_get_contents( $url, false, stream_context_create([
            'http' => [
                'protocol_version' => 1.1,
                'header'           => [
                    'Connection: close',
                    "User-agent: $ua"
                ],
                "timeout" => 30,
            ],
        ]));

        restore_error_handler();
        
        return $data;
    }

    /**
     * Vraci primo obsah dat - ICAL
     */
    public function getData(  $url  )
    {
        $key = "src_{$url}";

        $val = $this->cache->get( $key );
        if( $val==NULL ) {
            $val = $this->download( $url );
            $this->cache->put($key, $val, [
                Cache::EXPIRE => "{$this->config->fileValiditySec} seconds"
            ]);
        } else {
            Logger::log( 'app', Logger::DEBUG ,  "dwnl: cache hit" ); 
        }
        return $val;
    }
}