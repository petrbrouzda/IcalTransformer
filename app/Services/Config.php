<?php

declare(strict_types=1);

namespace App\Services;

use Nette;

class Config
{
    use Nette\SmartObject;

    /**
     * URL kalendare musi zacinat takto
     */
    public $requiredUrlBase = 'https://calendar.google.com/calendar/ical';

    /**
     * Jak dlouho plati stazeny soubor kalendare, sekundy
     */
    public $fileValiditySec = 3600;

    /**
     * Jak dlouho se maji drzet nakesovane vysledky parsovani kalendare, textove
     */
    public $outCacheValidity = '59 minutes';

    /**
     * Root adresar aplikace
     */
    public function getAppDir()
    {
        return substr( __DIR__, 0, strlen(__DIR__)-strlen('/app/Services/')+1 );
    }
}




