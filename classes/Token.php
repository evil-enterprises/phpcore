<?php

/**
 * Token
 *
 * A JWT implementation
 * http://openid.net/specs/draft-jones-json-web-token-07.html
 *
 * @package core
 * @author stefano.azzolini@caffeinalab.com
 * @version 1.0
 * @copyright Caffeina srl - 2014 - http://caffeina.co
 */

namespace phpcore;

class Token
{

    protected static $options = array
    (
        'secret'          => 'CHANGE_ME_PLEASE',
        'signing_method'  => 'sha256',
        'verify'          => true, // flag to enable/disable signature verification
    );

    public static function init(array $options)
    {
        foreach($options as $key => $val)
        {
            static::$options[$key] = $val;
        }
    }

    public static function secret($secret)
    {
        static::$options['secret'] = $secret;
    }

    public static function parse($payload)
    {
        $packet = static::decode($payload);
        $data = $packet['d'];
        $signature = $packet['s'];
        if( !static::$options['verify'] || $signature === static::sign($data) )
        {
            return $data;
        }
        else
        {
            throw new Exception( 'Invalid payload signature or corrupted data.' );
        }
    }

    public static function pack($data)
    {
        return static::encode(array(
            'd' => $data,
            's' => static::$options['verify'] ? static::sign($data) : false,
        ));
    }

    protected static function sign($data)
    {
        return hash_hmac(static::$options['signing_method'],serialize($data),static::$options['secret']);
    }

    /**
     * @param $data
     * @return string
     */
    protected static function encode($data)
    {
        return rtrim(strtr(base64_encode(addslashes(json_encode($data))), '+/', '-_'),'=');
    }

    /**
     * @param $data
     * @return mixed
     */
    protected static function decode($data)
    {
        return json_decode(stripslashes(base64_decode(strtr($data, '-_', '+/'))));
    }

}

Token::secret(md5(__FILE__));
