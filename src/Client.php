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

    function __construct( $host, $port ) {
        $this->_network = new Network( $host, $port );
    }

    public function generate( $lifetime ) {
        if( $this->_uuid ) return $this->_uuid;

        $this->send([
            $this->getCmd( self::CMD_GENERATE ),
            $this->getLifetime( $lifetime ),
        ]);

        $result = $this->recv();

        $answer = ord( $result[0] );

        if( $answer == Codes::OK ) {
            $this->_uuid = UUID::toNormal( substr( $result, 1 ) );

            return $this->_uuid;
        } else {
            $this->throwError( $answer );
        }
    }

    public function add( $uuid, $lifetime = 0 ) {
        $this->send([
            $this->getCmd( self::CMD_ADD_SESSION ),
            $this->getUUID( UUID::toBinary( $uuid ) ),
            $this->getLifetime( $lifetime ),
        ]);

        $this->validateResult( $this->recv() );

        $this->_uuid = $uuid;
    }

    public function init( $uuid ) {
        $this->send([
            $this->getCmd( self::CMD_INIT ),
            $this->getUUID( UUID::toBinary( $uuid ) ),
        ]);

        $result = $this->recv();
        $answer = ord( $result[0] );

        if( $answer == Codes::OK ) {
            $this->_uuid = $uuid;
            return true;
        }

        return false;
    }

    public function remove() {
        $this->send([
            $this->getCmd( self::CMD_REMOVE ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
        ]);

        $this->validateResult( $this->recv() );

        $this->_uuid = '';
    }

    public function prolong( $lifetime ) {
        $this->send([
            $this->getCmd( self::CMD_PROLONG ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getLifetime( $lifetime ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function addKey( $key, $value, $lifetime = 0 ) {
        $this->send([
            $this->getCmd( self::CMD_ADD_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
            $this->getValueString( $value ),
            $this->getLifetime( $lifetime ),
        ]);

        $result = $this->recv();
        $this->validateResult( $result );

        $clsKey = new SessionKey();

        $cmd = $this->getCmd( 0 );
        $idKeyInt = $this->getInt( 0 );
        $idRecordInt = $this->getInt( 0 );

        Serialization::unpack( [ $cmd, $idKeyInt, $idRecordInt ], $result );

        $clsKey->idKey = $idKeyInt->value;
        $clsKey->idRecord = $idRecordInt->value;

        $this->_keys[$key] = $clsKey;
    }

    public function addAllKey( $key, $value ) {
        $this->send([
            $this->getCmd( self::CMD_ALL_ADD_KEY ),
            $this->getKeyString( $key ),
            $this->getValueString( $value ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function getKey( $key, $limit = 0 ) {
        $this->send([
            $this->getCmd( self::CMD_GET_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
            $this->getLimit( $limit ),
        ]);

        $result = $this->recv();
        $this->validateResult( $result );

        $clsKey = new SessionKey();

        $cmd = $this->getCmd( 0 );
        $idKeyInt = $this->getInt( 0 );
        $idRecordInt = $this->getInt( 0 );
        $valueString = $this->getValueString( '' );

        Serialization::unpack( [ $cmd, $valueString, $idKeyInt, $idRecordInt ], $result );

        $clsKey->idKey = $idKeyInt->value;
        $clsKey->idRecord = $idRecordInt->value;

        $this->_keys[$key] = $clsKey;

        return $valueString->value;
    }

    public function setKey( $key, $value, $limit = 0 ) {
        if( !isset( $this->_keys[$key] ) ) {
            $this->throwError( Codes::E_NOT_FOUND_KEY );
        }

        $this->send([
            $this->getCmd( self::CMD_SET_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
            $this->getValueString( $value ),
            $this->getInt( $this->_keys[$key]->idKey ),
            $this->getInt( $this->_keys[$key]->idRecord ),
            $this->getLimit( $limit ),
        ]);

        $this->validateResult( $this->recv() );

        $this->_keys[$key]->idRecord++;
    }

    public function setForceKey( $key, $value, $limit = 0 ) {
        $this->send([
            $this->getCmd( self::CMD_SET_FORCE_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
            $this->getValueString( $value ),
            $this->getLimit( $limit ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function removeKey( $key ) {
        $this->send([
            $this->getCmd( self::CMD_REMOVE_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function removeAllKey( $key ) {
        $this->send([
            $this->getCmd( self::CMD_ALL_REMOVE_KEY ),
            $this->getKeyString( $key ),
        ]);

        $this->validateResult( $this->recv() );
    }


    public function existKey( $key ) {
        $this->send([
            $this->getCmd( self::CMD_EXIST_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
        ]);

        $result = $this->recv();

        $answer = ord( $result[0] );

        if( $answer != Codes::OK ) {
            if( $answer != Codes::E_KEY_NONE ) $this->throwError( $answer );

            return false;
        }

        return true;
    }

    public function prolongKey( $key, $lifetime ) {
        $this->send([
            $this->getCmd( self::CMD_PROLONG_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
            $this->getLifetime( $lifetime ),
        ]);

        $this->validateResult( $this->recv() );
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

    private function getInt( $value ) {
        $item = new SerializationItem( SerializationItem::TYPE_INT );
        $item->value = $value;

        return $item;
    }

    private function getLimit( $value ) {
        $item = new SerializationItem( SerializationItem::TYPE_SHORT_INT );
        $item->value = $value;

        return $item;
    }

    private function getLifetime( $value ) {
        return new SerializationItem( SerializationItem::TYPE_INT, $value );
    }

    private function send( $items ) {
        $data = Serialization::pack( $items );

        $final = new SerializationItem( SerializationItem::TYPE_STRING, $data, strlen( $data ) );

        if( !$this->_network->send( Serialization::pack( [$final] ) ) ) {
            $this->throwError( Codes::E_SEND );
        }
    }

    private function recv() {
        $length = $this->_network->recv( 4 );

        $length = unpack( 'N', $length )[1];

        return $this->_network->recv( $length );
    }
};
