<?php
namespace MemSess;

class Validation {
    public static function validateInt( $value, $min, $max, $error = 0 ) {
        if( !is_numeric( $value ) ) return self::throwError( $error );

        if( $value < $min || $value > $max ) return self::throwError( $error );

        return true;
    }

    public static function validateKey( $value, $prefixLock, $error = 0 ) {
        if( !is_string( $value ) || $value === '' ) return self::throwError( $error );

        $offset = strrpos( $value, $prefixLock );

        if( $offset === false ) return true;

        if( $offset + strlen( $prefixLock ) == strlen( $value ) ) return self::throwError( $error );

        return true;
    }

    public static function validateUUID( $value, $error = 0 ) {
        $regExp = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';

        if( !preg_match( $regExp, $value ) ) return self::throwError( $error );

        return true;
    }

    private static function throwError( $error ) {
        if( !$error ) return false;

        throw new BaseException( $error );
    }
}
