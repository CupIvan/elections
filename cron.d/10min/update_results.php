#!/bin/env php
<?php
/**
 * Скрипт выполняется каждые 10 минут
 */
require_once __DIR__ . '/../../init.php';

$GLOBALS['DIR'] =  __DIR__ . '/../../cache/'.date('Y-m-d').'/'.date('H:i');
@mkdir($GLOBALS['DIR'], 0777, true);

$GLOBALS['CK']  = 0;
function getPage($url)
{
	$st = file_get_contents($url);
	$st = iconv('cp1251', 'utf-8', $st);
	$js = ['timestamp'=>time(), 'url'=>$url, 'data'=>trim($st)];
	$js = json_encode($js, JSON_UNESCAPED_UNICODE);

	file_put_contents($GLOBALS['DIR'].'/'.$GLOBALS['CK'].'.json', $js);
	$GLOBALS['CK']++;
	return $st;
}

function getTIKs($url)
{
	$res = [];
	$st = getPage($url);
	if (!preg_match('#<select.+?</select>#', $st, $m)) return $res;
	$st = $m[0];
	if (!preg_match_all('#<option value="([^"]+)">(\d+) (.+?)</option>#', $st, $m, PREG_SET_ORDER)) return $res;
	foreach ($m as $a)
	{
		$res[] = [
			'id'    => $a[2],
			'title' => $a[3],
			'url'   => html_entity_decode($a[1]),
		];
	}
	return $res;
}

function getResults($url)
{
	$res = ['time'=>time(), 'url'=>$url, 'uiks'=>[]];
	$st = getPage($url);
	if (preg_match('#Для просмотра данных по участковым[^<]+<a href="([^"]+)#', $st, $m))
	{
		$res['url'] = html_entity_decode($m[1]);
		$st = getPage($res['url']);
	}
	if (!preg_match('#href="([^"]+)">Сводная таблица предварительных#', $st, $m)) return $res;
	$res['url'] = html_entity_decode($m[1]);
	$st = getPage($res['url']);
	return $res;
}


$url = 'http://www.vybory.izbirkom.ru/region/izbirkom?action=show&vrn=2272000913544&region=27&prver=0&pronetvd=null';
foreach (getTIKs($url) as $a)
{
	$a = getResults($a['url']);
}
