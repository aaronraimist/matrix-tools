<?php

class Scanner
{
    var $domain;
    var $homeserver;
    var $software;
    var $well_known = false;
    var $srv_record = false;

    function __construct( $domain )
    {
        $this->domain     = $domain;
        $this->homeserver = $this->get_homeserver( $this->domain );
        $this->software   = $this->get_software( $this->homeserver );
    }

    function http_get_json( $url )
    {
        $result = @file_get_contents
        (
            $url,
            false,
            stream_context_create
            (
                array
                (
                    'http' => array( 'timeout' => 2 ),
                    'ssl'  => array( 'verify_peer' => false,
                        'verify_peer_name' => false )
                )
            )
        );

        if ( false === $result )
            return false;

        $json = @json_decode( $result );

        if ( ! is_object( $json ) )
            return false;

        return $json;
    }

    function get_well_known( $domain )
    {
        $result = $this->http_get_json(
            sprintf( 'http://%s/.well-known/matrix/server', $domain ) );
    
        if ( false === $result )
            return false;

        if ( ! isset( $result->{ 'm.server' } ) )
            return false;
   
        $this->well_known = true;

        return $result->{ 'm.server' };
    }
    
    function get_srv_record( $domain )
    {
        $result = @dns_get_record(
            sprintf( '_matrix._tcp.%s', $domain ), DNS_SRV );

        if ( false === $result )
            return false;

        if ( ! isset( $result[ 0 ] ) or ! is_array( $result[ 0 ] ) )
            return false;

        $this->srv_record = true;

        return sprintf( '%s:%s', $result[ 0 ][ 'target' ],
            $result[ 0 ][ 'port' ] );
    }

    function get_homeserver( $domain )
    {
        $result = $this->get_well_known( $domain );

        if ( false !== $result )
            return $result;

        $result = $this->get_srv_record( $domain );

        if ( false !== $result )
            return $result;

        return sprintf( '%s:8448', $domain );
    }

    function get_software( $homeserver )
    {
        $result = $this->http_get_json(
            sprintf( 'https://%s/_matrix/federation/v1/version', $homeserver ) );

        if ( false === $result )
            return false;

        if ( ! isset( $result->server) )
            return false;

        return sprintf( '%s/%s', $result->server->name, $result->server->version );
    }
}

function scan( $domain )
{
    $log = $domain;

    $scanner = new Scanner( $domain );

    $log .= sprintf( ' <%s>', $scanner->homeserver );

    if ( false === $scanner->software )
    {
        echo $log . ' is dead' . PHP_EOL;
        return;
    }

    $db = new SQLite3( 'scanner.db' );

    while ( true )
    {
        $check = @$db->querySingle( sprintf(
            'select * from scanner where domain = \'%s\' order by id desc limit 1',
            $db->escapeString( $scanner->domain ) ), true );

        if ( false === $check )
            sleep( 1 );
        else
            break;
    }

    if ( empty( $check ) or $check[ 'software' ] != $scanner->software )
    {
        $log .= sprintf( ' upgraded to %s', $scanner->software );

        while ( true )
        {
            $insert = @$db->query( sprintf( 'insert into scanner
                ( first_time, last_time, domain, homeserver, well_known, srv_record, software )
                values ( datetime(), datetime(), \'%s\', \'%s\', \'%s\', \'%s\', \'%s\' )',
                $db->escapeString( $scanner->domain ),
                $db->escapeString( $scanner->homeserver ),
                $db->escapeString( $scanner->well_known ),
                $db->escapeString( $scanner->srv_record ),
                $db->escapeString( $scanner->software ) ) );
 
            if ( false === $insert )
                sleep( 1 );
            else
                break;
        }
    }

    else
    {
        $log .= sprintf( ' is running %s', $scanner->software );

        while ( true )
        {
            $update = @$db->query( sprintf( 'update scanner set
                last_time = datetime() where id = \'%s\'', $check[ 'id' ] ) );

            if ( false === $update )
                sleep( 1 );
            else
                break;
        }
    }

    if ( $scanner->well_known )
        $m = '.well-known';
    elseif ( $scanner->srv_record )
        $m = 'srv record';
    else
        $m = 'fallback method';
    
    $log .= sprintf( ' (discovered using %s)', $m );

    echo $log . PHP_EOL;
}

if ( isset( $argv[ 1 ] ) )
{
    if ( is_file( $argv[ 1 ] ) )
    {
        $f = file( $argv[ 1 ] );

        foreach ( $f as $l )
        {
            $l = trim( $l );

            if ( empty( $l ) )
                continue;

            scan( $l );
        }
    }

    else scan( $argv[ 1 ] );
}
