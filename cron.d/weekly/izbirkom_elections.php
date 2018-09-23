#!/bin/env php
<?php
/**
 * Скрипт выполняется раз в неделю и мониторит новые выборы на сайте Избиркома.
 */
require_once __DIR__ . '/../../init.php';

function getElections($time)
{
	$res = [];
	$data = [
		'action'     => 'search_by_calendar',
		'start_date' => date('d.m.Y', $time),
		'end_date'   => date('d.m.Y', $time),
		'urovproved' => 2, // COMMENT: региональные
	];
	$data_url = http_build_query($data).'&urovproved=1'; // COMMENT: добавляем федеральные
	$data_len = strlen($data_url);
	$context  = stream_context_create(['http' => [
		'method'  => 'POST',
		'content' => $data_url,
		'header'  => "Connection: close\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $data_len\r\n",
	]]);
	$st =@file_get_contents('http://www.vybory.izbirkom.ru/region/izbirkom', false, $context);
	$st = iconv('cp1251', 'utf-8', $st);

	$offset = strpos($st, 'Всего найдено записей');
	if (!$offset) return $res;
	$st = substr($st, $offset);

	if (preg_match_all('#<tr.+?<a href="([^"]+).+?>(.+?)</a>#s', $st, $m, PREG_SET_ORDER))
	foreach ($m as $a)
	{
		$region = preg_match('/region=(\d+)/', $a[1], $m) ? $m[1] : 0;
		$res[] = [
			'date'   => date('Y-m-d', $time),
			'title'  => $a[2],
			'url'    => $a[1],
			'type'   => $region ? 'regional' : 'federate',
			'region' => $region,
			'uniq'   => preg_match('/vrn=(\d+)/',    $a[1], $m) ? $m[1] : 0,
		];
	}
	return $res;
}

for ($i=0; $i<14; $i++)
{
	$t = time() + 24*3600*($i+7); // COMMENT: мониторим первые две недели через неделю
	$a = getElections($t);
//	echo date("Y-m-d", $t); if ($x=count($a)) echo " - $x"; echo "\n";
	foreach ($a as $a)
		mysql::insert('elections', $a);
}
