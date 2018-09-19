#!/bin/env php
<?php

function getPage($url)
{
	$cache = md5($url); $cache = './cache/'.substr($cache, 0, 2)."/$cache.html";
	if (!file_exists($dir = dirname($cache))) mkdir($dir, 0777, true);
	if (file_exists($cache))
	if (time() - filemtime($cache) < 24*3600) return file_get_contents($cache);
	$st = file_get_contents($url);
	$st = iconv('cp1251', 'utf-8', $st);
	if ($st) file_put_contents($cache, $st);
	return $st;
}

function getTIKs($region, $url)
{
	$url = str_replace('action=ik&', "action=ikTree&region=$region&", $url);
	$st = getPage($url);

	$res = [];
	$url = preg_replace('#vrn=.+#', 'onlyChildren=true&vrn=', $url);

	$a = json_decode($st, true);
	foreach ($a[0]['children'] as $a)
		$res[] = [
			'id'   => $a['id'],
			'name' => $a['text'],
			'url'  => $url.$a['id'],
		];
	return $res;
}

function getUIKs($url)
{
	$st = getPage($url);

	$res = [];
	$url = preg_replace('#action.+#', 'action=ik&vrn=', $url);

	$a = json_decode($st, true);
	foreach ($a as $a)
		$res[] = [
			'id'   => $a['id'],
			'name' => str_replace('Участковая избирательная комиссия', 'УИК', $a['text']),
			'uik'  => preg_replace('/.+?№\s*/', '', $a['text']),
			'url'  => $url.$a['id'],
		];
	return $res;
}

function getResults($url)
{
	$st = getPage($url);

	$res = [];

	if (preg_match('#coordlat="([^"]+).+?coordlon="([^"]+)#', $st, $m))
	{
		$res['lat'] = $m[1];
		$res['lon'] = $m[2];
	}

	// вырезаем нужную таблицу
	// TODO: члены комиссии

	return $res;
}

$res = [];

//foreach (getTIKs(33, 'http://www.vladimir.vybory.izbirkom.ru/region/vladimir/?action=ik&vrn=2332000871582') as $a)
foreach (getTIKs(52, 'http://www.nnov.vybory.izbirkom.ru/region/nnov/?action=ik&vrn=25220001509813') as $a)
foreach (getUIKs($a['url']) as $a)
{
	echo $a['name'];
	$x = getResults(str_replace('type=0', 'type=234', $a['url']));
	if (empty($x['lat']))
	{
		echo ' - UNKNOW SKIP: '.$a['url']."\n";
		continue;
	}
	else
		echo ' - OK'."\n";
	$x['uik'] = $a['uik'];
	$res[] = $x;
}

file_put_contents('./data/uiks/52.js', 'var data='.json_encode($res, JSON_NUMERIC_CHECK));
