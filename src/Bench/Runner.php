<?php
namespace Bench;
use HttpRequest;
use DomDocument;
use DomXPath;

class Runner
{
    protected $config;
    protected $report = [];
    protected $count = 0;
    protected $urls = [];
    protected $ran = [];
    protected $iterationTime = 0;

    public function __construct($config)
    {
        $this->config= $config;
    }

    public function run()
    {
        libxml_use_internal_errors(true);

        // once with crawl
        $this->urls = array_flip($this->config->paths);
        $this->go();
        $this->config->crawl = false;

        for($i = 1; $i < $this->config->iterations; $i++)
        {
            $this->go();
        }

        $this->report();
    }

    public function go()
    {
        $this->ran = [];
        $this->iterationTime = 0;
        do
        {
            echo ".";
            $urlcount = count($this->urls);;

            foreach($this->config->hosts as $host)
            {
                $this->runHost($host);
            }
        }
        while($urlcount < count($this->urls));

        $this->report['iterations'][] = $this->iterationTime;
    }

    public function report()
    {
        echo "\nReport\n";
        @$this->report['success_average'] = $this->report['success_time'] / $this->report['success'];
        @$this->report['failure_average'] = $this->report['failure_time'] / $this->report['failure'];
        foreach($this->report as $k => $v)
        {
            if (!is_array($v))
            {
                echo str_pad($k,20,' ',STR_PAD_LEFT).": $v\n";
            }
        }

        // histogram iterations
        $iterations = $this->report['iterations'];
        sort($iterations);
        $min = $this->down($iterations[0], 100);
        $max = $this->up($iterations[($this->config->iterations - 1)], 100);


        $size = ($max-$min)/10;

        $histogram = [];
        for($i = 0; $i < 11; $i++)
        {
            $i = (float)$i;
            $key = round((($i*$size)+$min),3);
            $histogram[(string)$key] = 0;
        }

        foreach($iterations as $iter)
        {
            $iter = (float)$iter;
            $key = (string)round(($iter - ($iter%$size)),3);
            @$histogram[$key]++;
        }
        $this->report['iterations'] = $histogram;

        foreach(['cache','byCode'] as $k)
        {
            echo "\n$k\n";
            foreach($this->report[$k] as $status => $value)
            {
                echo str_pad($status,10,' ',STR_PAD_LEFT).": $value\n";
            }
        }

        echo "\nIteration Histogram\n";
        foreach($histogram as $bucket => $value)
        {
            echo str_pad($bucket,10,' ',STR_PAD_LEFT).": "
                    .str_pad($value,3,' ')." "
                    .str_repeat('#',$value)."\n";
        }

    }

    public function runHost($host)
    {
        foreach($this->urls as $path => $bool)
        {
            if (!isset($this->ran[$path]))
            {
                $this->ran[$path] = true;
                $this->runPath($host, $path);
            }
        }
    }

    public function runPath($host, $path)
    {
        $this->count++;

        $request = new HttpRequest();
        $url = "{$this->config->scheme}://$host$path";
        $request->setUrl($url);
        $request->setOptions($this->config->options);
        $headers = $this->config->headers;
        $headers['Host'] = $this->config->hostname;
        $request->setHeaders($headers);

        $good = true;
        try {
            $this->start = microtime(true);
            $response = $request->send();
        }
        catch(\Exception $e)
        {
            $good = false;
            echo "Bad url $url ".get_class($e)." ".$e->getMessage()."\n";
            exit;
        }

        switch($response->getResponseCode())
        {
            case 301;
            case 302;
                $this->processRedirect($host, $response);
                return;
            break;
            case 200;
            case 404;
            break;
            default:
                $good = false;
        }

        if ($good)
            $this->processSuccess($response, $path);
        else
            $this->processFailure($response, $path);
    }

    public function processRedirect($host, $response)
    {
        $headers = $response->getHeaders();
        if (preg_match("@http://([^/]+)(.+)@", $headers['Location'], $match))
        {
            $this->runPath($host, $match[2]);
        }
        else
        {
            echo "Bad redirect\n";
            var_dump($headers);
            exit;
        }
    }

    public function processFailure($response, $path, $type = 'page')
    {
        $time = round(microtime(true)-$this->start,3) * 1000;
        @$this->report['failure']++;
        @$this->report['failure_time'] += $time;
        @$this->report[$type]['byCode'][$response->getResponseCode()]++;
        @$this->report['byCode'][$response->getResponseCode()]++;

        file_put_contents("/tmp/failure.txt", "\n\n\n\n$path\n".$response->toString());
    }

    public function processSuccess($response, $path, $type = 'page')
    {
        $time = round(microtime(true)-$this->start,3) * 1000;
        $this->iterationTime += $time;
        @$this->report['success']++;
        @$this->report['success_time'] += $time;
        @$this->report[$type]['success']++;
        @$this->report[$type]['byCode'][$response->getResponseCode()]++;
        @$this->report['byCode'][$response->getResponseCode()]++;

        $cache_status = '-';
        $headers = $response->getHeaders();
        if (isset($headers['X-Cache-Status']))
        {
            $cache_status = $headers['X-Cache-Status'];
        }
        else
        {
           // file_put_contents("/tmp/302.txt", "\n\n\n\n$path\n".$response->toString());
           // echo $path."\n";
        }
        @$this->report[$type]['cache'][$cache_status]++;
        @$this->report['cache'][$cache_status]++;
        $body = $response->getBody();
        $this->report['log'][$this->count]['length'] = strlen($body);
        $this->report['log'][$this->count]['code'] = $response->getResponseCode();

        if ($this->config->crawl)
        {
            $this->crawl($body, $path);
        }
    }

    public function crawl($body, $path)
    {
        $dom = new DomDocument();
        $dom->loadHtml($body);
        $xpath = new DOMXpath($dom);

        $r = $xpath->evaluate('//a');
        foreach($r as $node)
        {
            $href = $node->getAttribute('href');
            $parts = parse_url($href);
            if ((empty($parts['host']) || $parts['host'] == $this->config->hostname) && !empty($parts['path']))
            {
                // mailto ftp etc
                if (!empty($parts['scheme']) && $parts['scheme'] != 'http')
                    continue;

                // relative path (you can mess with this with html tags ignoring that for now)
                if (substr($parts['path'], 0, 1) != '/')
                {
                    $url = "$path/$parts[path]";

                }
                else
                {
                    $url = $parts['path'];
                }

                if (!empty($parts['query']))
                {
                    $url .= '?'.$parts['query'];
                }
                $this->urls[$url] = true;
                //echo "found $url\n";
            }
        }

        if ($this->config->assets)
        {
            $this->loadAssets($dom);
        }
    }

    public function loadAssets($dom)
    {
    }

    public function down($value, $clamp)
    {
        $mod = $value % $clamp;
        return $value - $mod;
    }

    public function up($value, $clamp)
    {
        $mod = $value % $clamp;
        return $value - $mod + $clamp;
    }
}
