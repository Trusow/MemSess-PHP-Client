<?php

namespace MemSess;
 
class SerializationItem {
    public const TYPE_BYTE = 1;
    public const TYPE_INT = 2;
    public const TYPE_SHORT_INT = 3;
    public const TYPE_LONG_INT = 4;
    public const TYPE_STRING = 5;
    public const TYPE_FIXED_STRING = 6;
    public const TYPE_STRING_WITH_NULL = 7;

    private $length;
    private $value;
    private $type;

    function __construct( $_type, $_value = '', $_length = 0 ) {
        switch( $_type ) {
        case self::TYPE_BYTE:
            $this->length = 1;
            $this->type = $_type;
            if( $_value ) {
                $this->value = $_value;
            }
            break;
        case self::TYPE_INT:
            $this->length = 4;
            $this->type = $_type;
            if( $_value ) {
                $this->value = $_value;
            }
            break;
        case self::TYPE_SHORT_INT:
            $this->length = 2;
            $this->type = $_type;
            if( $_value ) {
                $this->value = $_value;
            }
            break;
        case self::TYPE_LONG_INT:
            $this->length = 8;
            $this->type = $_type;
            if( $_value ) {
                $this->value = $_value;
            }
            break;
        case self::TYPE_STRING:
        case self::TYPE_FIXED_STRING:
            $this->type = $_type;
            if( $_value ) {
                $this->length = $_length;
                $this->value = $_value;
            }
            break;
        case self::TYPE_STRING_WITH_NULL:
            $this->type = $_type;
            if( $_value ) {
                $this->setValue( $_value );
            }
            break;
        default:
            Errors::throwError( Errors::E_WRONG_SERAILIZATION_ITEM );
        }
    }

    public function __get( $property ) {
        switch( $property ) {
        case 'length':
            return $this->length;
        case 'value':
            return $this->value;
        case 'type':
            return $this->type;
        }
    }

    public function __set( $property, $value ) {
        switch( $property ) {
        case 'length':
            $this->length = $value;
            break;
        case 'value':
            $this->setValue( $value );
            break;
        }
    }

    public function setValue( $_value ) {
        if( $this->type == self::TYPE_STRING_WITH_NULL ) {
            $this->length = strlen( $_value );
        }

        $this->value = $_value;
    }
};
