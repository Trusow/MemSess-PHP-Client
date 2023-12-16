<?php

namespace MemSess;
 
class SerializationItem {
    public const TYPE_BYTE = 1;
    public const TYPE_INT = 2;
    public const TYPE_SHORT_INT = 3;
    public const TYPE_STRING = 4;
    public const TYPE_FIXED_STRING = 5;
    public const TYPE_STRING_WITH_NULL = 6;

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
            throw -1;
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

class Serialization {
    public static function pack( $items ) {
        $data = "";

        foreach( $items as $item ) {
            switch( $item->type ) {
            case SerializationItem::TYPE_BYTE:
                $data .= pack( 'C', $item->value );
                break;
            case SerializationItem::TYPE_INT:
                $data .= pack( 'N', $item->value );
                break;
            case SerializationItem::TYPE_SHORT_INT:
                $data .= pack( 'n', $item->value );
                break;
            case SerializationItem::TYPE_STRING:
                $data .= pack( 'N', strlen( $item->value ) );
                $data .= $item->value;
                break;
            case SerializationItem::TYPE_FIXED_STRING:
                $data .= $item->value;
                break;
            case SerializationItem::TYPE_STRING_WITH_NULL:
                $data .= $item->value."\0";
                break;
            }
        }

        return $data;
    }

    public static function unpack( $items, $encData ) {
        foreach( $items as $item ) {
            switch( $item->type ) {
            case SerializationItem::TYPE_BYTE:
                $item->value = unpack( 'Cdata', $encData )['data'];
                $encData = substr( $encData, $item->length );
                break;
            case SerializationItem::TYPE_INT:
                $item->value = unpack( 'Ndata', $encData )['data'];
                $encData = substr( $encData, $item->length );
                break;
            case SerializationItem::TYPE_SHORT_INT:
                $item->value = unpack( 'ndata', $encData )['data'];
                $encData = substr( $encData, $item->length );
                break;
            case SerializationItem::TYPE_STRING:
                $length = unpack( 'Nlength', $encData )['length'];
                $item->length = $length;
                $encData = substr( $encData, 4 );
                $item->value = substr( $encData, 0, $item->length );
                $encData = substr( $encData, $item->length );
                break;
            case SerializationItem::TYPE_FIXED_STRING:
                $item->value = substr( $encData, 0, $item->length );
                $encData = substr( $encData, $item->length );
                break;
            case SerializationItem::TYPE_STRING_WITH_NULL:
                $item->length = strlen( $encData );
                $item->value = substr( $encData, 0, $item->length );
                $encData = substr( $encData, $item->length );
                break;
            }
        }
    }
};
