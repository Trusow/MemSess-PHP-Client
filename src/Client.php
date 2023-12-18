<?php 
namespace MemSess;

include_once 'Serialization.php';

class SessionKey {
    public $idKey;
    public $counterRecord;
};

class Client {
    private const CMD_GENERATE = 1;
    private const CMD_INIT = 2;
    private const CMD_REMOVE = 3;
    private const CMD_PROLONG = 4;
    private const CMD_ADD_KEY = 5;
    private const CMD_GET_KEY = 6;
    private const CMD_SET_KEY = 7;
    private const CMD_SET_FORCE_KEY = 8;
    private const CMD_REMOVE_KEY = 9;
    private const CMD_EXIST_KEY = 10;
    private const CMD_PROLONG_KEY = 11;
    private const CMD_ALL_ADD_KEY = 14;
    private const CMD_ALL_REMOVE_KEY = 15;
    private const CMD_ADD_SESSION = 18;

    private $_uuid = '';
    private $_network = null;
    private $_keys = [];

    function __construct( $address ) {
        $this->_network = new Network( $address );
    }

    public function generate( $lifetime ) {
        if( $this->_uuid ) return $this->_uuid;

        $cmd = $this->getCmd( self::CMD_GENERATE );
        $prolong = $this->getProlong( $lifetime );

        $data = Serialization::pack( [$cmd, $prolong] );

        if( !$this->send( Serialization::pack( [$cmd, $prolong] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $data = $this->recv();

        $answer = ord( $data[0] );

        if( $answer == Codes::OK ) {
            $this->_uuid = UUID::toNormal( substr( $data, 1 ) );

            return $this->_uuid;
        } else {
            $this->throwError( $answer );
        }
    }

    public function add( $uuid ) {
        $cmd = $this->getCmd( self::CMD_ADD_SESSION );
        $itemUuid = $this->getUUID( UUID::toBinary( $uuid ) );

        if( !$this->send( Serialization::pack( [$cmd, $itemUuid] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );

        $this->_uuid = $uuid;
    }

    public function init( $uuid ) {
        $cmd = $this->getCmd( self::CMD_INIT );
        $itemUuid = $this->getUUID( UUID::toBinary( $uuid ) );

        if( !$this->send( Serialization::pack( [$cmd, $itemUuid] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $data = $this->recv();
        $answer = ord( $data[0] );

        if( $answer == Codes::OK ) {
            $this->_uuid = $uuid;
            return true;
        }

        return false;
    }

    public function remove() {
        $cmd = $this->getCmd( self::CMD_REMOVE );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );

        if( !$this->send( Serialization::pack( [$cmd, $uuid] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );

        $this->_uuid = '';
    }

    public function prolong( $lifetime ) {
        $cmd = $this->getCmd( self::CMD_PROLONG );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $lifetimeInt = $this->getValueInt( $lifetime );

        if( !$this->send( Serialization::pack( [$cmd, $uuid, $lifetimeInt] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );
    }

    public function addKey( $key, $value, $lifetime = 0 ) {
        $cmd = $this->getCmd( self::CMD_ADD_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );
        $valueString = $this->getValueString( $value );
        $lifetimeInt = $this->getValueInt( $lifetime );

        $data = Serialization::pack( [
            $cmd,
            $uuid,
            $keyString,
            $valueString,
            $lifetimeInt,
        ] );

        if( !$this->send( $data ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );

        $clsKey = new SessionKey();

        $idKeyInt = $this->getValueInt( 0 );
        $idRecordInt = $this->getValueInt( 0 );
        $arr = [ $cmd, $idKeyInt, $idRecordInt ];

        Serialization::unpack( $arr, $result );

        $clsKey->idKey = $idKeyInt->value;
        $clsKey->idRecord = $idRecordInt->value;

        $this->_keys[$key] = $clsKey;
    }

    public function addAllKey( $key, $value ) {
        $cmd = $this->getCmd( self::CMD_ALL_ADD_KEY );
        $keyString = $this->getKeyString( $key );
        $valueString = $this->getValueString( $value );

        $data = Serialization::pack( [
            $cmd,
            $keyString,
            $valueString,
        ] );

        if( !$this->send( $data ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );
    }

    public function getKey( $key, $limit = 0 ) {
        $cmd = $this->getCmd( self::CMD_GET_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );
        $limitInt = $this->getValueShortInt( $limit );

        if( !$this->send( Serialization::pack( [$cmd, $uuid, $keyString, $limitInt] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );

        $clsKey = new SessionKey();

        $idKeyInt = $this->getValueInt( 0 );
        $idRecordInt = $this->getValueInt( 0 );
        $valueString = $this->getValueString( '' );
        $arr = [ $cmd, $valueString, $idKeyInt, $idRecordInt ];

        Serialization::unpack( $arr, $result );

        $clsKey->idKey = $idKeyInt->value;
        $clsKey->idRecord = $idRecordInt->value;

        $this->_keys[$key] = $clsKey;

        return $valueString->value;
    }

    public function setKey( $key, $value, $limit = 0 ) {
        $cmd = $this->getCmd( self::CMD_SET_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );
        $valueString = $this->getValueString( $value );
        $limitInt = $this->getValueShortInt( $limit );

        if( !isset( $this->_keys[$key] ) ) {
            $this->throwError( Codes::E_NOT_FOUND_KEY );
        }

        $keyId = $this->getValueInt( $this->_keys[$key]->idKey );
        $counterRecord = $this->getValueInt( $this->_keys[$key]->idRecord );

        $data = [ $cmd, $uuid, $keyString, $valueString, $keyId, $counterRecord, $limitInt ];

        if( !$this->send( Serialization::pack( $data ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );

        $this->_keys[$key]->idRecord++;
    }

    public function setForceKey( $key, $value, $limit = 0 ) {
        $cmd = $this->getCmd( self::CMD_SET_FORCE_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );
        $valueString = $this->getValueString( $value );
        $limitInt = $this->getValueShortInt( $limit );

        $data = [ $cmd, $uuid, $keyString, $valueString, $limitInt ];

        if( !$this->send( Serialization::pack( $data ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );
    }

    public function removeKey( $key ) {
        $cmd = $this->getCmd( self::CMD_REMOVE_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );

        if( !$this->send( Serialization::pack( [$cmd, $uuid, $keyString] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );
    }

    public function removeAllKey( $key ) {
        $cmd = $this->getCmd( self::CMD_ALL_REMOVE_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );

        if( !$this->send( Serialization::pack( [$cmd, $keyString] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );
    }


    public function existKey( $key ) {
        $cmd = $this->getCmd( self::CMD_EXIST_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );

        if( !$this->send( Serialization::pack( [$cmd, $uuid, $keyString] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();

        $answer = ord( $result[0] );

        if( $answer != Codes::OK ) {
            if( $answer != Codes::E_KEY_NONE ) $this->throwError( $answer );

            return false;
        }

        return true;
    }

    public function prolongKey( $key, $lifetime ) {
        $cmd = $this->getCmd( self::CMD_PROLONG_KEY );
        $uuid = $this->getUUID( UUID::toBinary( $this->_uuid ) );
        $keyString = $this->getKeyString( $key );
        $lifetimeInt = $this->getValueInt( $lifetime );

        if( !$this->send( Serialization::pack( [$cmd, $uuid, $keyString, $lifetimeInt] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }

        $result = $this->recv();
        $this->validateResult( $result );
    }

    private function validateResult( $result ) {
        $answer = ord( $result[0] );

        if( $answer != Codes::OK ) {
            $this->throwError( $answer );
        }
    }

    private function throwError( $error ) {
        switch( $error ) {
        case Codes::E_WRONG_COMMAND:
        case Codes::E_WRONG_PARAMS:
        case Codes::E_SESSION_NONE:
        case Codes::E_KEY_NONE:
        case Codes::E_LIMIT:
        case Codes::E_LIFETIME:
        case Codes::E_DUPCLICATE_KEY:
        case Codes::E_RECORD_BEEN_CHANGED:
        case Codes::E_LIMIT_PER_SEC:
        case Codes::E_SEND:
        case Codes::E_NOT_FOUND_KEY:
        case Codes::E_DUPLICATE_SESSION:
            throw new BaseException( $error );
            break;
        default:
            throw new BaseException( Codes::E_UNKNOWN );
            break;
        }
    }

    private function getCmd( $code ) {
        return new SerializationItem( SerializationItem::TYPE_BYTE, $code );
    }

    private function getUUID( $value = '' ) {
        $uuid = new SerializationItem( SerializationItem::TYPE_FIXED_STRING );
        $uuid->length = UUID::LENGTH_RAW;

        if( strlen( $value ) != 0 ) {
            $uuid->value = $value;
        }

        return $uuid;
    }

    private function getKeyString( $value ) {
        $item = new SerializationItem( SerializationItem::TYPE_STRING_WITH_NULL );
        $item->value = $value;

        return $item;
    }

    private function getValueString( $value ) {
        $item = new SerializationItem( SerializationItem::TYPE_STRING );
        $item->value = $value;

        return $item;
    }

    private function getValueInt( $value ) {
        $item = new SerializationItem( SerializationItem::TYPE_INT );
        $item->value = $value;

        return $item;
    }

    private function getValueShortInt( $value ) {
        $item = new SerializationItem( SerializationItem::TYPE_SHORT_INT );
        $item->value = $value;

        return $item;
    }

    private function getProlong( $value ) {
        return new SerializationItem( SerializationItem::TYPE_INT, $value );
    }

    private function send( $value ) {

        $item = new SerializationItem( SerializationItem::TYPE_STRING, $value, strlen( $value ) );

        $data = Serialization::pack( [$item] );

        return $this->_network->send( $data );
    } 

    private function recv() {
        $length = $this->_network->recv( 4 );

        $length = unpack( 'N', $length )[1];

        return $this->_network->recv( $length );
    }
};
