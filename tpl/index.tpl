<!DOCTYPE html>
<html>
<head>
	<title>Анализ выборов в Российской Федерации</title>
	<style>@import URL('/i/style.css')</style>
	<meta charset="utf-8">
</head>
<body>
	<h1>Таблица проведённых и будущих голосований избирателей России</h1>
	<p>Информация взята с официального сайта <a href="http://izbirkom.ru/region/izbirkom">избиркома России</a>.</p>
	<style>
		tr.inactive { color: #AAA; }
		.t tr td              { text-align: center; }
		.t tr td:nth-child(3) { text-align: left; }
	</style>
	<table class="t">
	<tr>
		<th>Дата</th>
		<th>Регион</th>
		<th>Выборы</th>
		<th>Статус</th>
		<th>Явка</th>
		<th>Результат</th>
	</tr>
	<?foreach (mysql::getList('SELECT * FROM `elections` ORDER BY `date` DESC, `region` LIMIT 40') as $a) {
		$t=strtotime($a['date']);
	?>
		<tr <?=time() < $t?' class="inactive"':''?>>
			<td><?=date('d.m.y', $t)?></td>
			<td><?=$a['region']?></td>
			<td><?=$a['title']?></td>
			<td><?=_s($a['state'])?></td>
			<td><?=$a['turnout']?>%</td>
			<td><?=$a['result']?></td>
		</tr>
	<?}?>
	</table>
</body>
</html>

<?
function _s($x)
{
	if ($x == 'wait')   return 'подготовка';
	if ($x == 'vote')   return 'голосование';
	if ($x == 'calc')   return 'подсчёт голосов';
	if ($x == 'end')    return 'завершены';
	if ($x == 'cancel') return 'отменены';
	return $x;
}
