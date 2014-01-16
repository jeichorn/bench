<?php

class Config
{
    // # of iterations (crawling is cached after the first iteration)
    public $iterations = 10;

    // http|https
    public $scheme = 'http';

    // set in the Host header in the request
    public $hostname = 'testdomain.com';

    // actual server to hit
    public $hosts = ['127.0.0.1'];

    // paths to start with
    public $paths = ["/"];

    // should we crawl pages
    public $crawl = true;

    // should we load assets on each page
    public $assets = true;

    // asset concurrency (so you act more like a browser)
    public $asset_concurrency = 4;

    // HttpRequest Options see http://us3.php.net/manual/en/http.request.options.php
    public $options = [
        'redirect' => 0
    ];

    // Headers you want to set on the request
    public $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36 bench/1.0'
    ];
}
