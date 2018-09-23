#!/bin/env php
<?php
/**
 * Скрипт выполняется каждые 10 минут
 */
require_once __DIR__ . '/../../init.php';

// define('DEBUG', 1);

$GLOBALS['DIR'] =  __DIR__ . '/../../cache/'.date('Y-m-d').'/'.date('H:i');
@mkdir($GLOBALS['DIR'], 0777, true);

$GLOBALS['CK']  = 0;
function getPage($url)
{
	if (defined('DEBUG'))
	{
		$md5 = md5($url);
		$fname = __DIR__ . '/../../cache/md5/'.substr($md5, 0, 2)."/$md5.html";
		@mkdir(dirname($fname), 0777, true);
		if (time() - @filemtime($fname) < 2*3600) return file_get_contents($fname);
	}

	$i = 0;
	while ($i++ < 3)
	{
		$err = 0; $st = @file_get_contents($url) or $err = 1;
		if (!$err) { sleep($i); continue; }
	}
	if (strpos($url, 'izbirkom.ru')) $st = iconv('cp1251', 'utf-8', $st);

	if (defined('DEBUG'))
	if ($st) file_put_contents($fname, $st);

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
	$res = ['time'=>time(), 'url'=>$url];
	$st = getPage($url);

	if (preg_match('#Для просмотра данных по участковым[^<]+<a href="([^"]+)#', $st, $m))
		return getResults(html_entity_decode($m[1]));

	if (preg_match('#href="([^"]+)">Сводная таблица предварительных#', $st, $m))
		return getResults(html_entity_decode($m[1]));

	if (preg_match('#href="([^"]+)">Сводная таблица результатов#', $st, $m))
		return getResults(html_entity_decode($m[1]));

	$x = strpos($st, 'Наименование избирательной комиссии');
	$x = strpos($st, '<table', $x);   // начало общей таблицы
	$x = strpos($st, '<table', $x+1); // подтаблица в левой колонке с именами столбцов
	$st = substr($st, $x+1);

	if (!preg_match('#.+?</table>#s', $st, $m)) return $res;
	$t = $m[0];

	// название строк
	$res['rows'] = [];
	if (preg_match_all('#<tr.+?<nobr>([^<]+).+?<nobr>([^<]+)#s', $t, $m, PREG_SET_ORDER))
	foreach ($m as $a)
		$res['rows'][] = $a[2];

	// результаты по участкам
	$res['uiks'] = [];
	if (!preg_match('#<table.+?</table>#s', $st, $m)) return $res;
	$t = $m[0];

	// заголовок таблицы
	if (preg_match_all('#УИК №(\d+)#s', $t, $m))
		$res['uiks'] = array_fill_keys($m[1], []);

	$row = 0;
	if (preg_match_all('#<tr.+?</tr#s', $t, $m, PREG_SET_ORDER))
	foreach ($m as $a)
	{
		if (strpos($a[0], 'УИК')) continue; // COMMENT: пропускаем первую строку с заголовоком
		$i = 0;
		// результаты по строкам
		if (preg_match_all('#<td.+?</td>#s', $a[0], $m))
		{
			if (strpos($a[0], 'colspan')) continue; // COMMENT: пустые строки пропускаем
			foreach ($res['uiks'] as $uik => $_)
				$res['uiks'][$uik][$row] = (int)strip_tags($m[0][$i++]);
			$row++;
		}
	}
	return $res;
}

function calcData($a)
{
	$rows = $a['rows'];
	$data = [];
	foreach ($a['uiks'] as $uik => $a)
	{
		$x = ['stat'=>[]];
		foreach ($rows as $i => $title)
		{
			$field = '';
			if (stripos($title, 'Число избирателей') !== false) $field = 'people';
			if (stripos($title, 'бюллетеней') !== false)
			{
				if (stripos($title, 'полученных') !== false) $field = 'papers_total';
				if (stripos($title, 'выданных') !== false)
				{
					if (stripos($title, 'в помещении')   !== false) $field = 'papers_gave_uik';
					if (stripos($title, 'вне помещения') !== false) $field = 'papers_gave_home';
				}
				if (stripos($title, 'погашенных')       !== false) $field = 'papers_destroed';
				if (stripos($title, 'в переносных')     !== false) $field = 'papers_in_home';
				if (stripos($title, 'в стационарных')   !== false) $field = 'papers_in_uik';
				if (stripos($title, 'действительных')   !== false) $field = 'papers_good'; // COMMENT: обязательно перед строкой "недействительных"
				if (stripos($title, 'недействительных') !== false) $field = 'papers_spoil';
				if (stripos($title, 'утраченных')       !== false) $field = 'papers_lost';
				if (stripos($title, 'не учтенных')      !== false) $field = 'papers_skip';
			}
			if (strpos($title, 'Фургал') !== false) $field = 'k1';
			if (strpos($title, 'Шпорт')  !== false) $field = 'k2';
			if (strpos($title, 'Орлова') !== false) $field = 'k1';
			if (strpos($title, 'Сипягин')!== false) $field = 'k2';

			if ($field)
			{
				if ($field == 'k1') $x['stat'][0] = $a[$i]; else
				if ($field == 'k2') $x['stat'][1] = $a[$i]; else
				$x[$field] = $a[$i];
			}
		}
		$data[$uik] = $x;
	}
	return $data;
}

function cacheData($a)
{
	if (empty($a)) return;
	@mkdir($GLOBALS['DIR'].'/data/');
	$st = json_encode($a, JSON_UNESCAPED_UNICODE)."\n";
	$st = str_replace('"url"',  "\n".'"url"',  $st);
	$st = str_replace('"rows"', "\n".'"rows"', $st);
	$st = str_replace('"uiks"', "\n".'"uiks"', $st);
	$st = preg_replace('/"\d+":\[/', "\n$0", $st);
	file_put_contents($GLOBALS['DIR'].'/data/'.$GLOBALS['CK'].'.json', $st);
}

function getSMSCIK($id)
{
	$res = [];
	if (!$id) return $res;
	$st = getPage('http://www.sms-cik.org/elections/'.$id.'/export_csv');
	$a = explode("\n", $st);
	for ($i=1; $i<count($a); $i++)
	{
		$row = explode(',', $a[$i]);
		$c = count($row);
		if ($c < 5) continue;
		$s = array_slice($row, 6, $c - 6 - 3);
		foreach ($s as $k => $v) $s[$k] = (int)$v;
		$res[$row[2]] = [
			'people'       => (int)$row[$c-3],
			'papers_spoil' => (int)$row[$c-2],
			'papers_good'  => (int)$row[$c-1],
			'stat'         => $s,
		];
	}
	return $res;
}

$sql = 'SELECT `date`,`region`,`url`,`smscikId` FROM `elections` WHERE `state` = "calc"';
foreach (mysql::getList($sql) as $_)
{
	$results = getSMSCIK($_['smscikId']);
	$data = [];
	foreach (getTIKs($_['url']) as $a)
	{
		if (defined('DEBUG')) echo $a['title'];
		$a = getResults($a['url']);
		if (defined('DEBUG')) echo ' - '.count($a['uiks'])."\n";
		cacheData($a);
		if (empty($data)) $data = $a;
		else foreach ($a['uiks'] as $k => $v) $data['uiks'][$k] = $v;
	}
	$a = calcData($data);
	foreach ($a as $k => $v) $results[$k] = $v;

	$fname = __DIR__ . '/../../'.date('Y/m/d', strtotime($_['date'])).'_'.$_['region'].'.js';
	@mkdir(dirname($fname), 0777, true);
	file_put_contents($fname, 'var data='.json_encode($results));
}
