<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AsusWRT Merlin Firmware Notifier</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:;base64,=">
</head>
<body>
<?php
error_reporting(E_ALL);
require_once(__DIR__ . '/../vendor/autoload.php');

use Pushbullet\Exceptions\PushbulletException;
use Symfony\Component\Dotenv\Dotenv;

$config = new Dotenv();
$config->load(__DIR__ . '/../config/.env');

// Fetch the latest entries from SourceForce
// https://sourceforge.net/projects/asuswrt-merlin/rss?path=/RT-AC88U/Release
$url = $_ENV['RSS_URL'] . '/rss?path=/' . $_ENV['ROUTER_MODEL'] . '/Release';
try {
    $feed = Laminas\Feed\Reader\Reader::import($url);
} catch (Laminas\Feed\Reader\Exception\RuntimeException $e) {
    // feed import failed
    echo "Exception caught importing feed: {$e->getMessage()}\n";
    exit;
}

$data = [];
// Get version from local file
$file = __DIR__ . '/version.txt';
$compare = file_get_contents($file);

// Loop over each channel item/entry and store relevant data for each
foreach ($feed as $item) {
    $data['items'][] = [
        'description' => $item->getDescription(),
    ];
}

$version_temp = str_replace('/' . $_ENV['ROUTER_MODEL'] . '/Release/' . $_ENV['ROUTER_MODEL'] . '_', '', $data['items']['0']['description']);
$version = str_replace('.zip', '', $version_temp);

// Get your access token here: https://www.pushbullet.com/account
try {
    $pb = new Pushbullet\Pushbullet($_ENV['PUSH_BULLET_TOKEN']);
} catch (PushbulletException $e) {
    echo "ERR : Exception caught with Pushbullet : {$e->getMessage()}\n";
}
$devices = $pb->getDevices();

if ($version !== $compare) {
    // Write version to local file
    try {
        $write_file = file_put_contents($file,$version);
    } catch (Exception $e) {
        echo "ERR : Error writing on local file version.";
        exit;
    }
    if (is_array($devices)) {
        foreach ($devices as $device) {
            $result = $pb->device($device->iden)->pushNote("Asus Firmware Update", "Latest version : " . $version);
            if ($result) {
                echo "OK: Notification sent to " . $device->iden . " (" . $device->nickname . ")<br>";
            }
        }
    } else {
        echo "ERR: No notification sent : No devices found in your Pushbullet account.";
    }
} else {
    echo "OK: Nothing to send, no new versions released.";
}
?>
</body>
</html>
