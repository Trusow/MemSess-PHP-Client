<?php 

namespace MemSess;

class Errors {
    public const E_WRONG_COMMAND = 2;
    public const E_WRONG_PARAMS = 3;
    public const E_SESSION_NONE = 4;
    public const E_KEY_NONE = 5;
    public const E_LIMIT_EXCEEDED = 6;
    public const E_LIFETIME_EXCEEDED = 7;
    public const E_DUPCLICATE_KEY = 8;
    public const E_RECORD_BEEN_CHANGED = 9;
    public const E_LIMIT_PER_SEC_EXCEEDED = 10;
    public const E_DUPLICATE_SESSION = 11;

    public const E_WRONG_LIFETIME = 1001;
    public const E_WRONG_TIMEOUT = 1002;
    public const E_WRONG_ATTEMPTS = 1003;
    public const E_WRONG_KEY = 1004;
    public const E_WRONG_UUID = 1005;
    public const E_WRONG_LIMIT = 1006;
    public const E_WRONG_SERAILIZATION_ITEM = 1007;

    public const E_UNKNOWN = 1101;
    public const E_SEND = 1102;
    public const E_SAVE_BEFORE_LOAD = 1103;

    public static function throwError( $error ) {
        throw new BaseException( $error );
    }

    public static function throwServerError( $error ) {
        switch( $error ) {
        case self::E_WRONG_COMMAND:
        case self::E_WRONG_PARAMS:
        case self::E_SESSION_NONE:
        case self::E_KEY_NONE:
        case self::E_LIMIT_EXCEEDED:
        case self::E_LIFETIME_EXCEEDED:
        case self::E_DUPCLICATE_KEY:
        case self::E_RECORD_BEEN_CHANGED:
        case self::E_LIMIT_PER_SEC_EXCEEDED:
        case self::E_DUPLICATE_SESSION:
            throw new BaseException( $error );
            break;
        default:
            throw new BaseException( self::E_UNKNOWN );
            break;
        }
    }

}
