#!/bin/env php
<?php
/**
 * Скрипт выполняется каждые 10 минут
 */
require_once __DIR__ . '/../../init.php';

// define('DEBUG', 1);


$sql = 'SELECT `id`,`date`,`region`,`url`,`smscikId`,`state` FROM `elections` WHERE `state` IN ("calc", "recalc")';
foreach (mysql::getList($sql) as $_)
{
	// СМС ЦИК
	$t = time();
	foreach (getSMSCIK($_['smscikId']) as $uik => $a)
	{
		$a['md5']        = md5(json_encode($a));
		$a['electionId'] = $_['id'];
		$a['UIK']        = $uik;
		$a['timestamp']  = date('Y-m-d H:i:s', $t);
		$a['source']     = 'smscik';
		mysql::insert('results', $a);
	}
	// Избирком
	$t = time();
	$data = getResults($_['url']);

	if ($data['state'] == 'cancel')
	{
		mysql::update('elections', ['id'=>$_['id'], 'state'=>'cancel']);
		continue;
	}

	foreach (calcData($data) as $uik => $a)
	{
		$a['md5']        = md5(json_encode($a));
		$a['electionId'] = $_['id'];
		$a['UIK']        = $uik;
		$a['timestamp']  = date('Y-m-d H:i:s', $t);
		$a['source']     = 'izbirkom';
		mysql::insert('results', $a);
	}
	if ($_['state'] == 'recalc')
		mysql::update('elections', ['id'=>$_['id'], 'state'=>'end']);
}

// ------ functions ------

function getPage($url)
{
	if (defined('DEBUG'))
	{
		$md5 = md5($url);
		$fname = __DIR__ . '/../../cache/md5/'.substr($md5, 0, 2)."/$md5.html";
		@mkdir(dirname($fname), 0777, true);
		if (time() - @filemtime($fname) < 6*3600) return file_get_contents($fname);
	}

	$i = 0;
	while ($i++ < 3)
	{
		$err = 0; $st = @file_get_contents($url) or $err = 1;
		if (!$err) break;
		sleep($i);
	}
	if (strpos($url, 'izbirkom.ru')) $st = iconv('cp1251', 'utf-8', $st);

	if (defined('DEBUG'))
	if ($st) file_put_contents($fname, $st);

	return $st;
}

function getResults($url)
{
	if (defined('DEBUG')) echo $url."\n";
	$res = ['time'=>time(), 'state'=> 'calc', 'url'=>$url];
	$st = getPage($url);

	if (strpos($st, 'организовывались, но не проводились'))
	{
		$res['state'] = 'cancel';
		return $res;
	}

	if (strpos($st, 'Нижестоящие избирательные комиссии'))
	if (strpos($st, 'УИК №') === false) // COMMENT: если страница со списком УИКов - то ниже не проваливаемся
	{
		if (!preg_match('#<select.+?</select>#', $st, $m)) return $res;
		if (!preg_match_all('#<option value="([^"]+)">(\d+) (.+?)</option>#', $st, $m, PREG_SET_ORDER)) return $res;
		foreach ($m as $a)
		{
			if (defined('DEBUG')) echo $a[3]."\n";
			$url = html_entity_decode($a[1]);
			$x = getResults($url);
			$rows = $x['rows'];
			foreach ($x['uiks'] as $k => $v) $res['uiks'][$k] = $v;
			$res['rows'] = $rows; // COMMENT: фиксируем, чтобы не размножался список строк
		}
		return $res;
	}

	if (preg_match('#Для просмотра данных по участковым[^<]+<a href="([^"]+)#', $st, $m))
		return getResults(html_entity_decode($m[1]));

	if (preg_match('#href="([^"]+)">Сводная таблица предварительных#', $st, $m))
		return getResults(html_entity_decode($m[1]));

	// TODO: нужно как-то разделять единый округ, одномандатный и многомандатный
	if (preg_match('#href="([^"]+)">Сводная таблица результатов выборов по единому округу#', $st, $m))
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
	if (preg_match_all('#<tr.+?<nobr>([^<]+).+?<nobr>([^<]+)#s', $t, $m, PREG_SET_ORDER))
	foreach ($m as $a)
		$res['rows'][] = $a[2];

	// результаты по участкам
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
	if (defined('DEBUG')) echo implode(' ', array_keys($res['uiks']))."\n";
	return $res;
}

function calcData($a)
{
	$rows = $a['rows'];
	$data = [];

	// определяем строки с фамилиями кандидатов - перебираем массив с конца
	// и прекращаем, если найдём строку с числом, затем инвертируем массив
	$candidates = []; $cId = 0; $_ = [];
	for ($i=count($rows)-1; $i>=0; $i--) if (strpos($rows[$i], 'Число') !== false) break; else $_[] = $i;
	$_ = array_reverse($_);
	foreach ($_ as $_) $candidates[$_] = 'k'.(++$cId);

	foreach ($a['uiks'] as $uik => $a)
	{
		$x = [];
		foreach ($rows as $i => $title)
		{
			$field = '';
			if (stripos($title, 'Число избирателей') !== false) $field = 'people';
			if (stripos($title, 'бюллетеней') !== false)
			{
				if (stripos($title, 'полученных') !== false) $field = 'papers_total';
				if (stripos($title, 'выданных') !== false)
				{
					if (stripos($title, 'в помещении')   !== false) $field = 'papers_in_uik';
					if (stripos($title, 'вне помещения') !== false) $field = 'papers_in_home';
				}
				if (stripos($title, 'в переносных')     !== false) $field = 'papers_in_home';
				if (stripos($title, 'в стационарных')   !== false) $field = 'papers_in_uik';
				if (stripos($title, 'действительных')   !== false) $field = 'papers_good'; // COMMENT: обязательно перед строкой "недействительных"
				if (stripos($title, 'недействительных') !== false) $field = 'papers_spoil';
			}
			if (isset($candidates[$i])) $field = $candidates[$i];
			if ($field) $x[$field] = $a[$i];
		}
		$data[$uik] = $x;
	}
	return $data;
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
		$uik = $row[2];
		$res[$uik] = [
			'people'       => (int)$row[$c-3],
			'papers_spoil' => (int)$row[$c-2],
			'papers_good'  => (int)$row[$c-1],
		];
		$s = array_slice($row, 6, $c - 6 - 3);
		foreach ($s as $k => $v) $res[$uik]['k'.($k+1)] = (int)$v;
	}
	return $res;
}

