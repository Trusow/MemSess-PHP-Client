<?php

namespace MemSess;

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
