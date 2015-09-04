<?php

//error_reporting(E_ALL|E_STRICT);
//ini_set( 'display_errors', 1);

require 'vendor/autoload.php';

// Your shell script
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use WS\Wasabi\Combination;

    $wasabi = new WsServer(new Combination);
    $wasabi->disableVersion(0); // old, bad, protocol version
    $wasabi->setEncodingChecks(false);

    // Make sure you're running this as root
    $server = IoServer::factory(new HttpServer($wasabi), 8787,  '0.0.0.0');
    $server->run();
