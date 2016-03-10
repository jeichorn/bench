<?php
require 'vendor/autoload.php';

$CONF = require $argv[1];

use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

$client = new Client([
    // Base URI is used with relative requests
    'base_uri' => $CONF['base_uri'],
    // You can set any number of default request options.
    'timeout'  => 10.0,
    'on_stats' => function (TransferStats $stats) {
	    $stats = $stats->getHandlerStats();
	    $time = round($stats['total_time'], 3);
	    $url = parse_url($stats['url'], PHP_URL_PATH);
	    echo date('Y-m-d H:i:s')." $stats[http_code] $url $time\n";
    }

]);


$batch = [];
for($i = 0; $i < $CONF['iterations']; $i++)
{
	$fp = fopen($CONF['log'], 'r');
	while($line = fgets($fp))
	{
		if (preg_match($CONF['regex_filter'], $line))
		{
			if (preg_match($CONF['regex_split'], $line, $match))
			{
				echo $match[1]."\n";
				$batch[] = new Request('GET', $match[1]);
			}
		}
	}
}

$pool = new Pool($client, $batch, [
    'concurrency' => $CONF['concurrency'],
    'fulfilled' => function ($response, $index) {
        // this is delivered each successful response
//echo($response->getbody());
    },
    'rejected' => function ($reason, $index) {
        // this is delivered each failed request
	var_dump($reason->getMessage());
exit;
    },
]);
$promise = $pool->promise();
$t = microtime(true);
$promise->wait();
echo (microtime(true)-$t)."\n";
