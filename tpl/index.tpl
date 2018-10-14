<!DOCTYPE html>
<html>
<head>
	<title>Анализ выборов в Российской Федерации</title>
	<style>@import URL('/i/style.css')</style>
	<script src="/i/ok.js"></script>
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
			<?$t = $t - get_tz_msk_offset($a['region']);?>
			<?if (time() < $t + 8*3600){?>
			<td data-time-left="<?=$t+8*3600-time()?>">до начала голосования</td>
			<?} elseif (time() < $t + 20*3600){?>
			<td data-time-left="<?=$t+20*3600-time()?>">до конца голосования</td>
			<?} else {?>
			<td><?=$a['result']?></td>
			<?}?>
		</tr>
	<?}?>
	</table>

<script>
var f, time = function() { return Math.round((new Date()).getTime() / 1000) }
var timeStart = time()
setInterval(f=function(){
	var i, t, a = document.querySelectorAll('[data-time-left]')
	for (i=0; i<a.length; i++)
	{
		if (!a[i].dataset.timeComment)
			a[i].dataset.timeComment = a[i].innerHTML
		t = a[i].dataset.timeLeft - (time() - timeStart)
		if (t > 24*3600) { t = Math.round(t / 24 / 3600); t += ok(t, ' день ', ' дня ', ' дней ') }
		else
		if (t > 3600) { t = Math.round(t / 3600); t += ok(t, ' час ', ' часа ', ' часов ') }
		else
		if (t > 60) { t = Math.round(t / 60); t += ok(t, ' минута ', ' минуты ', ' минут ') }
		else
			t = 'Меньше минуты '
		a[i].innerHTML = t + a[i].dataset.timeComment
	}
}, 21*1000);
f()
</script>

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
