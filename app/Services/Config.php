<?php

declare(strict_types=1);

namespace App\Services;

use Nette;

class Config
{
    use Nette\SmartObject;

    /**
     * Povolena URL
     */
    public $requiredUrlBases = array(
        'https://calendar.google.com/calendar/ical',    // google 
        'https://outlook.live.com/owa/calendar/',        // ms outlook (live)
        'https://outlook.office365.com/owa/calendar/'   // ms outlook (office 365)
    );

    /*
    * URL kalendare musi zacinat takto - Apple
        https://p59-caldav.icloud.com/published/2/NDM2M....
    */
    public $requiredUrlBaseApple = '/^(https:\/\/[a-z0-9\-]+\.icloud\.com)\/(.+)$/';

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




