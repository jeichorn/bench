<?php
return [
	'base_uri' => 'http://localhost',
	'iterations' => 1,
	'concurrecny' => 1,
	'log' => '/mnt/log/nginx/gateway.access.log',
	'regex_filter' => '/pressdns/',
	'regex_split' => '@ "[A-Z]+ (.+) HTTP@',
];
