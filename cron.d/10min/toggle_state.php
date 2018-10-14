#!/bin/env php
<?php
/**
 * Переключение статусов выборов: ожидание, голосование, подсчёт, завершение
 * Скрипт выполняется каждые 10 минут
 */
require_once __DIR__ . '/../../init.php';

$sql = 'SELECT `id`,`date`,`region`,`state` FROM `elections` WHERE `state` IN ("wait", "vote", "calc")';
foreach (mysql::getList($sql) as $a)
{
	$old_state = $a['state'];
	$t = time() + get_tz_msk_offset($a['region']);
	if ($a['state'] == 'wait') if ($t > strtotime($a['date']) +  8*3600) $a['state'] = 'vote';
	if ($a['state'] == 'vote') if ($t > strtotime($a['date']) + 20*3600) $a['state'] = 'calc';
	if ($a['state'] == 'calc') if ($t > strtotime($a['date']) + 32*3600) $a['state'] = 'end'; // COMMENT: 08:00 следующего дня
	if ($old_state != $a['state'])
		mysql::update('elections', $a);
}
