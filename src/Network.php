<?php 
namespace MemSess;

class Network {
    private $_socket = null;
    private $_isConnect = false;

    function __construct( $address ) {
        $data = explode( ':', $address, 2 );

        $host = $data[0];
        $port = $data[1];

        $this->_socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
        socket_set_option( $this->_socket, SOL_TCP, TCP_NODELAY, true );
        $this->_isConnect = socket_connect( $this->_socket, $host, $port );
    }

    public function send( $data ) {
        if( !$this->_isConnect ) return false;

        $offset = 0;
        $length = strlen( $data );

        while( true ) {
            $l = socket_write(
                $this->_socket,
                substr( $data, $offset ),
                $length - $offset
            );

            if( $l === false ) return false;

            $offset += $l;

            if( $offset == $length ) return true;
        }
    }

    public function recv( $length ) {
        if( !$this->_isConnect ) return false;

        $data = '';

        while( true ) {
            $data .= socket_read( $this->_socket, $length - strlen( $data ) );

            if( strlen( $data ) == $length ) return $data;
        }
    }
};
