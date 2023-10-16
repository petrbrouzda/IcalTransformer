<?php

/**
 */

declare(strict_types=1);

namespace App\Model;

use Nette;

class KeyValueAttribute
{
    use Nette\SmartObject;

    public $key;
    public $value;

	public function __construct( $key, $value )
	{
        $this->key = $key;
        $this->value = $value;
	}

    /**
     * Pokud nenajde, vraci prazdny retezec, ne null
     */
    public static function findValue( $attributes, $name ) 
    {
        foreach( $attributes as $attr ) {
            if( $attr->key === $name ) {
                return $attr->value;
            }
        }
        return "";
    }

    /**
     * Najde atributy, ktere NEJSOU v seznamu
     */
    public static function findUnknownKeys( $attributes, $allowedKeys ) {
        $keys = explode( ',', $allowedKeys );
        $out = array();
        foreach( $attributes as $attr ) {
            if( ! in_array($attr->key, $keys) ) {
                $out[] = $attr->key;
            }
        }
        return $out;
    }
}
