<?php

require 'vendor/autoload.php';

// Your shell script
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use AB\Wasabi\Combination;
use AB\Wasabi\Product;
use AB\Wasabi\Cart;

    $wasabi = new WsServer(new Combination);
    $wasabi->disableVersion(0); // old, bad, protocol version
    $wasabi->setEncodingChecks(false);

    // Make sure you're running this as root
    $server = IoServer::factory(new HttpServer($wasabi), 8787,  '0.0.0.0');
    $server->run();

    $wasabi2 = new WsServer(new Product);
    $wasabi2->disableVersion(0); // old, bad, protocol version
    $wasabi2->setEncodingChecks(false);

    // Make sure you're running this as root
    $server2 = IoServer::factory(new HttpServer($wasabi2), 8788,  '0.0.0.0');
    $server2->run();

    $wasabi3 = new WsServer(new Cart);
    $wasabi3->disableVersion(0); // old, bad, protocol version
    $wasabi3->setEncodingChecks(false);

    // Make sure you're running this as root
    $server3 = IoServer::factory(new HttpServer($wasabi3), 8789,  '0.0.0.0');
    $server3->run();
