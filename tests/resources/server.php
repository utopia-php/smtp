<?php

require __DIR__ . '/../../vendor/autoload.php';

use Utopia\SMTP\Adapter\Native;
use Utopia\SMTP\Adapter\Swoole;
use Utopia\SMTP\Handler\Memory;
use Utopia\SMTP\Server;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$port = (int) (getenv('PORT') ?: 2525);
$handler = new Memory(
    allowedRecipients: ['inbox@appwrite.test', 'team@appwrite.test'],
    allowedSenders: ['relay@appwrite.test', 'sender@appwrite.test'],
);

$adapter = getenv('ADAPTER') === 'swoole' && extension_loaded('swoole')
    ? new Swoole('0.0.0.0', $port)
    : new Native('0.0.0.0', $port);

$server = new Server($adapter, $handler, 'mail.appwrite.test');
$server->setDebug((bool) (getenv('DEBUG') ?: false));
$server->start();
