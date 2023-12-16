<?php 

namespace MemSess;

class UUID {
    public const LENGTH = 36;
    public const LENGTH_RAW = 16;

    private static function getInt( $val ) {
        switch( $val ) {
            case '0':
                return 0;
            case '1':
                return 1;
            case '2':
                return 2;
            case '3':
                return 3;
            case '4':
                return 4;
            case '5':
                return 5;
            case '6':
                return 6;
            case '7':
                return 7;
            case '8':
                return 8;
            case '9':
                return 9;
            case 'a': case 'A':
                return 10;
            case 'b': case 'B':
                return 11;
            case 'c': case 'C':
                return 12;
            case 'd': case 'D':
                return 13;
            case 'e': case 'E':
                return 14;
            case 'f': case 'F':
                return 15;
            default:
                return -1;
        }
    }

    private static function getChar( $val ) {
        switch( $val ) {
            case 0:
                return '0';
            case 1:
                return '1';
            case 2:
                return '2';
            case 3:
                return '3';
            case 4:
                return '4';
            case 5:
                return '5';
            case 6:
                return '6';
            case 7:
                return '7';
            case 8:
                return '8';
            case 9:
                return '9';
            case 10:
                return 'a';
            case 11:
                return 'b';
            case 12:
                return 'c';
            case 13:
                return 'd';
            case 14:
                return 'e';
            case 15:
                return 'f';
            default:
                return 0;
        }
    }

    public static function toBinary( $data ) {
        $isEven = true;
        $val = 0;
        $resultData = '';
        $parseVal = 0;

        for( $i = 0; $i < self::LENGTH; $i++ ) {

            if( $i == 8 || $i == 13 || $i == 18 || $i == 23 ) {
                continue;
            }

            $parseVal = self::getInt( $data[$i] );

            if( $parseVal < 0 ) {
                return '';
            }

            if( $isEven ) {
                $val = $parseVal * self::LENGTH_RAW;
            } else {
                $val += $parseVal;

                $resultData .= chr( $val );
                $val = 0;
            }

            $isEven = !$isEven;
        }

        return $resultData;
    }

    public static function toNormal( $data ) {
        $offset = 0;
        $offsetSeparator = 0;
        $resultData = '';

        for( $i = 0; $i < self::LENGTH_RAW; $i++ ) {
            $ch = ord( $data[$i] );

            if( $ch < self::LENGTH_RAW ) {
                $resultData[$offset+$offsetSeparator] = '0';
                $resultData[$offset+$offsetSeparator+1] = self::getChar( $ch );
            } else {
                $resultData[$offset+$offsetSeparator] = self::getChar( (int)( $ch / self::LENGTH_RAW ) );
                $resultData[$offset+$offsetSeparator+1] = self::getChar( $ch % self::LENGTH_RAW );
            }

            if( $i == 3 || $i == 5 || $i == 7 || $i == 9 ) {
                $resultData[$offset+$offsetSeparator+2] = '-';
                $offsetSeparator++;
            }

            $offset += 2;
        }

        return $resultData;
    }
};
