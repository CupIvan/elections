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

function getTIKs($url)
{
	$st = getPage($url);

	$res = [];

	if (!preg_match('#<select name="gs">.+?</select>#', $st, $m)) return $res;
	if (preg_match_all('#<option value="(.+?)">(\d+) (.+?)</option>#', $m[0], $m, PREG_SET_ORDER))
	foreach ($m as $m)
		$res[$m[2]] = [
			'ref'  => $m[2],
			'name' => $m[3],
			'url'  => html_entity_decode($m[1]),
		];
	return $res;
}

function getUIKs($url)
{
	$st = getPage($url);

	if (preg_match('#Для просмотра данных по участковым избирательным комиссиям перейдите на <a href="([^"]+)#su', $st, $m))
		$st = getPage(html_entity_decode($m[1]));

	$res = [];

	if (!preg_match('#<select name="gs">.+?</select>#', $st, $m)) return $res;
	if (preg_match_all('#<option value="(.+?)">(\d+) (.+?)</option>#', $m[0], $m, PREG_SET_ORDER))
	foreach ($m as $m)
		$res[$m[2]] = [
			'ref'  => $m[2],
			'name' => $m[3],
			'uik'  => preg_replace('/.+?№/', '', $m[3]),
			'url'  => html_entity_decode($m[1]),
		];
	return $res;
}

function getResults($url)
{
	$st = getPage($url);

	$res = [];

	// вырезаем нужную таблицу
	$x = strpos($st, 'Число избирателей');
	$st = substr($st, $from = strrpos(substr($st, 0, $x), '<table'), strpos($st, '</table', $x) - $from);
	if (preg_match_all('#<b>(\d+)</b>#', $st, $m))
	{
		$m = $m[1]; array_unshift($m, 'skip_zero_index');
		$res = [
			'people'           => $m[1],
			'papers_total'     => $m[2],
			'papers_gave_uik'  => $m[4],
			'papers_gave_home' => $m[5],
			'papers_destroed'  => $m[6],

			'papers_in_home'   => $m[7],
			'papers_in_uik'    => $m[8],
			'papers_spoil'     => $m[9],
			'papers_good'      => $m[10],
			'papers_lost'      => $m[11],
			'papers_skip'      => $m[12],
		];
		$res['stat'] = array_slice($m, 13);
	}
	return $res;
}

$res = [];

$url = 'http://www.nnov.vybory.izbirkom.ru/region/nnov'
	.'?action=show&root_a=152406012&vrn=25220001737786&region=52&global=&type=0&sub_region=52&prver=0&pronetvd=null';
$url = 'http://www.primorsk.vybory.izbirkom.ru/region/izbirkom'
	.'?action=show&root_a=null&vrn=22520001508349&region=25&global=null&type=0&prver=0&pronetvd=null';
$url = 'http://www.primorsk.vybory.izbirkom.ru/region/izbirkom'
	.'?action=show&root_a=null&vrn=22520001430251&region=25&global=null&type=0&prver=0&pronetvd=null';
foreach (getTIKs($url) as $a)
foreach (getUIKs($a['url']) as $a)
{
	echo $a['name'];
	$x = getResults(str_replace('type=0', 'type=234', $a['url']));
	if (empty($x['people']))
		echo " - UNKNOW SKIP\n";
	else
		echo ' - '.round(($x['papers_in_uik'] + $x['papers_in_home']) / $x['people'] * 100, 2).'%'."\n";
	$res[$a['uik']] = $x;
}

file_put_contents('2018/09/09_25.js', 'var data='.json_encode($res, JSON_NUMERIC_CHECK));
