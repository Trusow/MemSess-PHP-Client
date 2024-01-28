<?php 
namespace MemSess;

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
    private const CMD_GET_STATISTICS = 19;

    private const SEC_IN_YEAR = 31536000;
    private const MAX_LIMIT = 65536;
    private const MAX_LIFETIME_LOCK = 60;
    private const MAX_ATTEMPTS_LOCK = 2147483647;
    private const MIN_TIMEOUT_LOCK = 10;
    private const MAX_TIMEOUT_LOCK = 10000;
    private const PREFIX_LOCK = '.';
    private const ANSWER_OK = 1;

    private $_uuid = '';
    private $_network = null;
    private $_keys = [];
    private $_locks = [];

    function __construct( $host, $port ) {
        $this->_network = new Network( $host, $port );
    }

    function __destruct() {
        foreach( $this->_locks as $key => $v ) {
            $this->unlock( $key );
        }
    }

    public function generate( $lifetime ) {
        if( $this->_uuid ) return $this->_uuid;

        Validation::validateInt( $lifetime, 0, self::SEC_IN_YEAR, Errors::E_WRONG_LIFETIME );

        $this->send([
            $this->getCmd( self::CMD_GENERATE ),
            $this->getLifetime( $lifetime ),
        ]);

        $result = $this->recv();

        $answer = ord( $result[0] );

        if( $answer == self::ANSWER_OK ) {
            $this->_uuid = UUID::toNormal( substr( $result, 1 ) );

            return $this->_uuid;
        } else {
            Errors::throwServerError( $answer );
        }
    }

    public function add( $uuid, $lifetime = 0 ) {
        Validation::validateInt( $lifetime, 0, self::SEC_IN_YEAR, Errors::E_WRONG_LIFETIME );
        Validation::validateUUID( $uuid, Errors::E_WRONG_UUID );

        $this->send([
            $this->getCmd( self::CMD_ADD_SESSION ),
            $this->getUUID( UUID::toBinary( $uuid ) ),
            $this->getLifetime( $lifetime ),
        ]);

        $this->validateResult( $this->recv() );

        $this->_uuid = $uuid;
    }

    public function init( $uuid ) {
        Validation::validateUUID( $uuid, Errors::E_WRONG_UUID );

        $this->send([
            $this->getCmd( self::CMD_INIT ),
            $this->getUUID( UUID::toBinary( $uuid ) ),
        ]);

        $result = $this->recv();
        $answer = ord( $result[0] );

        if( $answer == self::ANSWER_OK ) {
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
        Validation::validateInt( $lifetime, 0, self::SEC_IN_YEAR, Errors::E_WRONG_LIFETIME );

        $this->send([
            $this->getCmd( self::CMD_PROLONG ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getLifetime( $lifetime ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function addKey( $key, $value, $lifetime = 0 ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );
        Validation::validateInt( $lifetime, 0, self::SEC_IN_YEAR, Errors::E_WRONG_LIFETIME );

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

    public function addKeyToAll( $key, $value ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );

        $this->send([
            $this->getCmd( self::CMD_ALL_ADD_KEY ),
            $this->getKeyString( $key ),
            $this->getValueString( $value ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function getKey( $key, $limit = 0 ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );
        Validation::validateInt( $limit, 0, self::MAX_LIMIT, Errors::E_WRONG_LIMIT );

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

        return json_decode( $valueString->value, true );
    }

    public function setKey( $key, $value, $limit = 0 ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );
        Validation::validateInt( $limit, 0, self::MAX_LIMIT, Errors::E_WRONG_LIMIT );

        if( !isset( $this->_keys[$key] ) ) {
            Errors::throwError( Errors::E_SAVE_BEFORE_LOAD );
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

        $answer = ord( $this->recv()[0] );
        $result = false;

        if( $answer == self::ANSWER_OK ) {
            $result = true;
        } else if( $answer != Errors::E_RECORD_BEEN_CHANGED ) {
            Errors::throwServerError( $answer );
        }

        $this->_keys[$key]->idRecord++;

        return $result;
    }

    public function setForceKey( $key, $value, $limit = 0 ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );
        Validation::validateInt( $limit, 0, self::MAX_LIMIT, Errors::E_WRONG_LIMIT );

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
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );

        $this->send([
            $this->getCmd( self::CMD_REMOVE_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function removeKeyFromAll( $key ) {
        $this->send([
            $this->getCmd( self::CMD_ALL_REMOVE_KEY ),
            $this->getKeyString( $key ),
        ]);

        $this->validateResult( $this->recv() );
    }


    public function existKey( $key ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );

        $this->send([
            $this->getCmd( self::CMD_EXIST_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
        ]);

        $result = $this->recv();

        $answer = ord( $result[0] );

        if( $answer != self::ANSWER_OK ) {
            if( $answer != Errors::E_KEY_NONE ) Errors::throwServerError( $answer );

            return false;
        }

        return true;
    }

    public function prolongKey( $key, $lifetime ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );
        Validation::validateInt( $lifetime, 0, self::SEC_IN_YEAR, Errors::E_WRONG_LIFETIME );

        $this->send([
            $this->getCmd( self::CMD_PROLONG_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key ),
            $this->getLifetime( $lifetime ),
        ]);

        $this->validateResult( $this->recv() );
    }

    public function lock( $key, $lifetime = 1, $attempts = 1, $timeout = 10 ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );
        Validation::validateInt( $lifetime, 1, self::MAX_LIFETIME_LOCK, Errors::E_WRONG_LIFETIME );
        Validation::validateInt( $attempts, 0, self::MAX_ATTEMPTS_LOCK, Errors::E_WRONG_ATTEMPTS );
        Validation::validateInt( $timeout, self::MIN_TIMEOUT_LOCK, self::MAX_TIMEOUT_LOCK, Errors::E_WRONG_TIMEOUT );

        $i = 0;

        while( true ) {
            if( $attempts != 0 && $i == $attempts ) {
                return false;
            };


            $this->send([
                $this->getCmd( self::CMD_ADD_KEY ),
                $this->getUUID( UUID::toBinary( $this->_uuid ) ),
                $this->getKeyString( $key.self::PREFIX_LOCK ),
                $this->getValueString( '' ),
                $this->getLifetime( $lifetime ),
            ]);

            $answer = ord( $this->recv()[0] );

            if( $answer == self::ANSWER_OK ) {
                $this->_locks[$key] = true;

                return true;
            } else if( $answer != Errors::E_DUPCLICATE_KEY ) {
                Errors::throwServerError( $answer );
            }

            $i++;
            usleep( $timeout );
        }
    }

    public function unlock( $key ) {
        Validation::validateKey( $key, self::PREFIX_LOCK, Errors::E_WRONG_KEY );

        $this->send([
            $this->getCmd( self::CMD_REMOVE_KEY ),
            $this->getUUID( UUID::toBinary( $this->_uuid ) ),
            $this->getKeyString( $key.self::PREFIX_LOCK ),
        ]);

        unset( $this->_locks[$key] );

        $this->validateResult( $this->recv() );
    }

    public function getStatistics() {
        $cmd = $this->getCmd( self::CMD_GET_STATISTICS );
        $this->send([
            $cmd
        ]);

        $result = $this->recv();
        $this->validateResult( $result );

        $itemSendedBytes = $this->getStatisticValue();
        $itemReceivedBytes = $this->getStatisticValue();

        $itemPassedGenerate = $this->getStatisticValue();
        $itemPassedExist = $this->getStatisticValue();
        $itemPassedAdd = $this->getStatisticValue();
        $itemPassedProlong = $this->getStatisticValue();
        $itemPassedRemove = $this->getStatisticValue();
        $itemPassedAddKey = $this->getStatisticValue();
        $itemPassedExistKey = $this->getStatisticValue();
        $itemPassedRemoveKey = $this->getStatisticValue();
        $itemPassedProlongKey = $this->getStatisticValue();
        $itemPassedGetKey = $this->getStatisticValue();
        $itemPassedSetKey = $this->getStatisticValue();
        $itemPassedSetForceKey = $this->getStatisticValue();
        $itemPassedAddKeyToAll = $this->getStatisticValue();
        $itemPassedRemoveKeyFromAll = $this->getStatisticValue();

        $itemFailedGenerate = $this->getStatisticValue();
        $itemFailedExist = $this->getStatisticValue();
        $itemFailedAdd = $this->getStatisticValue();
        $itemFailedProlong = $this->getStatisticValue();
        $itemFailedRemove = $this->getStatisticValue();
        $itemFailedAddKey = $this->getStatisticValue();
        $itemFailedExistKey = $this->getStatisticValue();
        $itemFailedRemoveKey = $this->getStatisticValue();
        $itemFailedProlongKey = $this->getStatisticValue();
        $itemFailedGetKey = $this->getStatisticValue();
        $itemFailedSetKey = $this->getStatisticValue();
        $itemFailedSetForceKey = $this->getStatisticValue();
        $itemFailedAddKeyToAll = $this->getStatisticValue();
        $itemFailedRemoveKeyFromAll = $this->getStatisticValue();

        $itemErrorWrongCommand = $this->getStatisticValue();
        $itemErrorWrongParams = $this->getStatisticValue();
        $itemErrorSessionNone = $this->getStatisticValue();
        $itemErrorKeyNone = $this->getStatisticValue();
        $itemErrorLimitExceeded = $this->getStatisticValue();
        $itemErrorLifetimeExceeded = $this->getStatisticValue();
        $itemErrorDuplicateKey = $this->getStatisticValue();
        $itemErrorRecordBeenChanged = $this->getStatisticValue();
        $itemErrorLimitPerSecExceeded = $this->getStatisticValue();
        $itemErrorDuplicateSession = $this->getStatisticValue();
        $itemErrorDisconnection = $this->getStatisticValue();

        $itemDurationReceivingLess5ms = $this->getStatisticValue();
        $itemDurationReceivingLess10ms = $this->getStatisticValue();
        $itemDurationReceivingLess20ms = $this->getStatisticValue();
        $itemDurationReceivingLess50ms = $this->getStatisticValue();
        $itemDurationReceivingLess100ms = $this->getStatisticValue();
        $itemDurationReceivingLess200ms = $this->getStatisticValue();
        $itemDurationReceivingLess500ms = $this->getStatisticValue();
        $itemDurationReceivingLess1000ms = $this->getStatisticValue();
        $itemDurationReceivingMore1000ms = $this->getStatisticValue();

        $itemDurationProcessingLess5ms = $this->getStatisticValue();
        $itemDurationProcessingLess10ms = $this->getStatisticValue();
        $itemDurationProcessingLess20ms = $this->getStatisticValue();
        $itemDurationProcessingLess50ms = $this->getStatisticValue();
        $itemDurationProcessingLess100ms = $this->getStatisticValue();
        $itemDurationProcessingLess200ms = $this->getStatisticValue();
        $itemDurationProcessingLess500ms = $this->getStatisticValue();
        $itemDurationProcessingLess1000ms = $this->getStatisticValue();
        $itemDurationProcessingMore1000ms = $this->getStatisticValue();

        $itemDurationSendingLess5ms = $this->getStatisticValue();
        $itemDurationSendingLess10ms = $this->getStatisticValue();
        $itemDurationSendingLess20ms = $this->getStatisticValue();
        $itemDurationSendingLess50ms = $this->getStatisticValue();
        $itemDurationSendingLess100ms = $this->getStatisticValue();
        $itemDurationSendingLess200ms = $this->getStatisticValue();
        $itemDurationSendingLess500ms = $this->getStatisticValue();
        $itemDurationSendingLess1000ms = $this->getStatisticValue();
        $itemDurationSendingMore1000ms = $this->getStatisticValue();

        $itemTotalFreeSessions = $this->getStatisticValue();

        Serialization::unpack([
            $cmd,

            $itemSendedBytes,
            $itemReceivedBytes,

            $itemPassedGenerate,
            $itemPassedExist,
            $itemPassedAdd,
            $itemPassedProlong,
            $itemPassedRemove,
            $itemPassedAddKey,
            $itemPassedExistKey,
            $itemPassedRemoveKey,
            $itemPassedProlongKey,
            $itemPassedGetKey,
            $itemPassedSetKey,
            $itemPassedSetForceKey,
            $itemPassedAddKeyToAll,
            $itemPassedRemoveKeyFromAll,

            $itemFailedGenerate,
            $itemFailedExist,
            $itemFailedAdd,
            $itemFailedProlong,
            $itemFailedRemove,
            $itemFailedAddKey,
            $itemFailedExistKey,
            $itemFailedRemoveKey,
            $itemFailedProlongKey,
            $itemFailedGetKey,
            $itemFailedSetKey,
            $itemFailedSetForceKey,
            $itemFailedAddKeyToAll,
            $itemFailedRemoveKeyFromAll,

            $itemErrorWrongCommand,
            $itemErrorWrongParams,
            $itemErrorSessionNone,
            $itemErrorKeyNone,
            $itemErrorLimitExceeded,
            $itemErrorLifetimeExceeded,
            $itemErrorDuplicateKey,
            $itemErrorRecordBeenChanged,
            $itemErrorLimitPerSecExceeded,
            $itemErrorDuplicateSession,
            $itemErrorDisconnection,

            $itemDurationReceivingLess5ms,
            $itemDurationReceivingLess10ms,
            $itemDurationReceivingLess20ms,
            $itemDurationReceivingLess50ms,
            $itemDurationReceivingLess100ms,
            $itemDurationReceivingLess200ms,
            $itemDurationReceivingLess500ms,
            $itemDurationReceivingLess1000ms,
            $itemDurationReceivingMore1000ms,

            $itemDurationProcessingLess5ms,
            $itemDurationProcessingLess10ms,
            $itemDurationProcessingLess20ms,
            $itemDurationProcessingLess50ms,
            $itemDurationProcessingLess100ms,
            $itemDurationProcessingLess200ms,
            $itemDurationProcessingLess500ms,
            $itemDurationProcessingLess1000ms,
            $itemDurationProcessingMore1000ms,

            $itemDurationSendingLess5ms,
            $itemDurationSendingLess10ms,
            $itemDurationSendingLess20ms,
            $itemDurationSendingLess50ms,
            $itemDurationSendingLess100ms,
            $itemDurationSendingLess200ms,
            $itemDurationSendingLess500ms,
            $itemDurationSendingLess1000ms,
            $itemDurationSendingMore1000ms,

            $itemTotalFreeSessions,
        ], $result );

        return [
            'traffic' => [
                'sendedBytes' => $itemSendedBytes->value,
                'receivedBytes' => $itemReceivedBytes->value,
            ],
            'passedRequests' => [
                'generate' => $itemPassedGenerate->value,
                'init' => $itemPassedExist->value,
                'add' => $itemPassedAdd->value,
                'prolong' => $itemPassedProlong->value,
                'remove' => $itemPassedRemove->value,
                'addKey' => $itemPassedAddKey->value,
                'existKey' => $itemPassedExistKey->value,
                'removeKey' => $itemPassedRemoveKey->value,
                'prolongKey' => $itemPassedProlongKey->value,
                'getKey' => $itemPassedGetKey->value,
                'setKey' => $itemPassedSetKey->value,
                'setForceKey' => $itemPassedSetForceKey->value,
                'addKeyToAll' => $itemPassedAddKeyToAll->value,
                'removeKeyFromAll' => $itemPassedRemoveKeyFromAll->value,
            ],
            'failedRequests' => [
                'generate' => $itemFailedGenerate->value,
                'init' => $itemFailedExist->value,
                'add' => $itemFailedAdd->value,
                'prolong' => $itemFailedProlong->value,
                'remove' => $itemFailedRemove->value,
                'addKey' => $itemFailedAddKey->value,
                'existKey' => $itemFailedExistKey->value,
                'removeKey' => $itemFailedRemoveKey->value,
                'prolongKey' => $itemFailedProlongKey->value,
                'getKey' => $itemFailedGetKey->value,
                'setKey' => $itemFailedSetKey->value,
                'setForceKey' => $itemFailedSetForceKey->value,
                'addKeyToAll' => $itemFailedAddKeyToAll->value,
                'removeKeyFromAll' => $itemFailedRemoveKeyFromAll->value,
            ],
            'errors' => [
                'wrongCommand' => $itemErrorWrongCommand->value,
                'wrongParams' => $itemErrorWrongParams->value,
                'wrongSessionNone' => $itemErrorSessionNone->value,
                'wrongKeyNone' => $itemErrorKeyNone->value,
                'limitExceeded' => $itemErrorLimitExceeded->value,
                'lifetimeExceeded' => $itemErrorLifetimeExceeded->value,
                'duplicateKey' => $itemErrorDuplicateKey->value,
                'recordBeenChanged' => $itemErrorRecordBeenChanged->value,
                'limitPerSecExceeded' => $itemErrorLimitPerSecExceeded->value,
                'duplicateSession' => $itemErrorDuplicateSession->value,
                'disconnection' => $itemErrorDisconnection->value,
            ],
            'duration' => [
                'receiving' => [
                    'less5ms' => $itemDurationReceivingLess5ms->value,
                    'less10ms' => $itemDurationReceivingLess10ms->value,
                    'less20ms' => $itemDurationReceivingLess20ms->value,
                    'less50ms' => $itemDurationReceivingLess50ms->value,
                    'less100ms' => $itemDurationReceivingLess100ms->value,
                    'less200ms' => $itemDurationReceivingLess200ms->value,
                    'less500ms' => $itemDurationReceivingLess500ms->value,
                    'less1000ms' => $itemDurationReceivingLess1000ms->value,
                    'more100ms' => $itemDurationReceivingMore1000ms->value,
                ],
                'processing' => [
                    'less5ms' => $itemDurationProcessingLess5ms->value,
                    'less10ms' => $itemDurationProcessingLess10ms->value,
                    'less20ms' => $itemDurationProcessingLess20ms->value,
                    'less50ms' => $itemDurationProcessingLess50ms->value,
                    'less100ms' => $itemDurationProcessingLess100ms->value,
                    'less200ms' => $itemDurationProcessingLess200ms->value,
                    'less500ms' => $itemDurationProcessingLess500ms->value,
                    'less1000ms' => $itemDurationProcessingLess1000ms->value,
                    'more1000ms' => $itemDurationProcessingMore1000ms->value,
                ],
                'sending' => [
                    'less5ms' => $itemDurationSendingLess5ms->value,
                    'less10ms' => $itemDurationSendingLess10ms->value,
                    'less20ms' => $itemDurationSendingLess20ms->value,
                    'less50ms' => $itemDurationSendingLess50ms->value,
                    'less100ms' => $itemDurationSendingLess100ms->value,
                    'less200ms' => $itemDurationSendingLess200ms->value,
                    'less500ms' => $itemDurationSendingLess500ms->value,
                    'less1000ms' => $itemDurationSendingLess1000ms->value,
                    'more1000ms' => $itemDurationSendingMore1000ms->value,
                ],
            ],
            'totalFreeSessions' => $itemTotalFreeSessions->value,
        ];
    }

    private function validateResult( $result ) {
        $answer = ord( $result[0] );

        if( $answer != self::ANSWER_OK ) {
            Errors::throwServerError( $answer );
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
        $item->value = json_encode( $value );

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

    private function getStatisticValue() {
        return new SerializationItem( SerializationItem::TYPE_LONG_INT );
    }

    private function getLifetime( $value ) {
        return new SerializationItem( SerializationItem::TYPE_INT, $value );
    }

    private function send( $items ) {
        $data = Serialization::pack( $items );

        $final = new SerializationItem( SerializationItem::TYPE_STRING, $data, strlen( $data ) );

        if( !$this->_network->send( Serialization::pack( [$final] ) ) ) {
            Errors::throwError( Errors::E_SEND );
        }
    }

    private function recv() {
        $length = $this->_network->recv( 4 );

        $length = unpack( 'N', $length )[1];

        return $this->_network->recv( $length );
    }
};
