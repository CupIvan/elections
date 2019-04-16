<?php
$id = $_REQUEST['id'];
$a = mysql::getItem(mysql::prepare('SELECT * FROM `elections` WHERE `id` = ?i', $id));
?>
<h1><?=$a['title']?> <small>(<a href="<?=$a['url']?>">Избирком</a>)</small></h1>
<script>var info={
	stat: ['Алексеев', 'Викулов', 'Волков', 'Красиков', 'Медведев'],
}</script>

<?php
$data=[];
foreach (mysql::getList(mysql::prepare('SELECT UIK,papers_good,papers_in_home,papers_in_uik,papers_spoil,papers_total,people,k1,k2,k3,k4,k5,k6,k7,k8
	FROM `results` WHERE `electionId` = ?i ORDER BY `UIK`,`timestamp`', $id)) as $a)
{
	$id = (int)$a['UIK'];
	$a[$x='papers_good']    = (int)$a[$x];
	$a[$x='papers_in_home'] = (int)$a[$x];
	$a[$x='papers_in_uik']  = (int)$a[$x];
	$a[$x='papers_spoil']   = (int)$a[$x];
	$a[$x='papers_total']   = (int)$a[$x];
	$a[$x='people']         = (int)$a[$x];
	$a['stat'][] = (int)$a['k1']; unset($a['k1']);
	$a['stat'][] = (int)$a['k2']; unset($a['k2']);
	$a['stat'][] = (int)$a['k3']; unset($a['k3']);
	$a['stat'][] = (int)$a['k4']; unset($a['k4']);
	$a['stat'][] = (int)$a['k5']; unset($a['k5']);
	$a['stat'][] = (int)$a['k6']; unset($a['k6']);
	$a['stat'][] = (int)$a['k7']; unset($a['k7']);
	$a['stat'][] = (int)$a['k8']; unset($a['k8']);
	$data[$id] = $a;
}
?>
<script>var data=<?=json_encode($data)?></script>

<style>
	h1       { text-align: center; max-width: 800px; margin: auto; }
	section  { display: table; margin: auto; }
	table    { border-collapse: collapse; }
	td, th   { padding: 2px 10px; text-align: center; border: 1px solid #999; }
	th       { background: #EEE; }
	tr:nth-child(odd) { background: #FAFAFA; }
	tr:hover { background: #EEE; }

	.dygraph-legend { left: 60px !important; width: auto; }
</style>

<section>

<div id="h"></div>

<div id="plot"></div>

<script>
var i, j, k, t, st = '', n=0, papers=0, people=0, spoils=0, stat=[]

var plot = []
for (j=0;j<info.stat.length;j++)
{
	stat[j] = 0
	plot[j] = []
	for (i=0;i<=100;i++) plot[j][i] = [0,0,0,100]
}

for (i in data)
{
	people += data[i].people
	papers += data[i].papers_in_uik + data[i].papers_in_home
	spoils += data[i].papers_spoil
	n++

	t = data[i].papers_in_uik + data[i].papers_in_home

	st += '<tr>'
		+ '<td>'+i+'</td>'
		+ '<td>'+data[i].people+'</td>'
		+ '<td>'+Math.round(t/data[i].people*100)+'%</td>'
		+ '<td>'+data[i].papers_in_uik+'</td>'
		+ '<td>'+data[i].papers_in_home+'</td>'

		+ '<td>'+data[i].papers_good+'</td>'
		+ '<td>'+data[i].papers_spoil+'</td>'

		+ '<td>'+data[i].papers_spoil+'</td><td><b>'+Math.round(data[i].papers_spoil/t*100)+'%</b></td>'
	for (j=0; j<info.stat.length; j++)
		st += '<td>'+data[i].stat[j]+'</td><td><b>'+Math.round(data[i].stat[j]/t*100)+'%</b></td>'
	st += '</tr>'

	var _j = Math.round(t/data[i].people*100)

	for (j=0; j<info.stat.length; j++)
	{
		stat[j] += data[i].stat[j]
		k = Math.round(data[i].stat[j]/t*100)
		plot[j][_j][0]++
		plot[j][_j][2] += k
		if (k < plot[j][_j][3]) plot[j][_j][3] = k
		if (k > plot[j][_j][1]) plot[j][_j][1] = k
	}
}
document.getElementById('h').innerHTML = ''
	+ '<p>Всего участков: '+n+'</p>'
	+ '<p>Явка: '+Math.round(papers/people*100)+'% ('+papers+' / '+people+')</p>'

var t = '<table>'
	+ '<tr>'
	+   '<th rowspan="2">УИК</th>'
	+   '<th colspan="2">Избирателей</th>'
	+   '<th colspan="2">Проголосовало</th>'
	+   '<th colspan="2">Статистика</th>'
	+   '<th colspan="12">Результаты</th>'
	+ '</tr>'
	+ '<tr>'
	+   '<th>всего</th><th>явка</th>'
	+   '<th>в УИК</th><th>на дому</th>'
	+   '<th title="действительных">действ.</th><th title="недействительных">нед.</th>'
	+   '<th colspan="2">Испорчено</th>'
for (j=0; j<info.stat.length; j++)
	t += '<th colspan="2">'+info.stat[j]+'</th>'

st = t+'</tr>'
	+ st
	+ '<tr>'
	+   '<th colspan="7"></th>'
	+   '<th>'+spoils +'</th><th><b>'+Math.round(spoils /papers*100)+'%</b></th>'
for (j=0; j<info.stat.length; j++)
st += '<th>'+stat[j]+'</th><th><b>'+Math.round(stat[j]/papers*100)+'%</b></th>'
st += '</tr>'
	+ '</table>'
document.write(st)

// рисуем график

// считаем средние показатели процентов по явке
for (j=0; j<info.stat.length; j++)
for (i=0; i<=100; i++) { /*plot[j][i][2] /= plot[j][i][0];*/ plot[j][i].shift() }

var plot_ = [], a, x
for (i=0; i<=100; i++)
{
	a = [i]; x=0
	for (j=0; j<info.stat.length; j++)
	{
		a[j+1] = plot[j][i][0]?plot[j][i]:null
		if (plot[j][i][0]) x=1
	}
	if (x) plot_.push(a)
}
var a = info.stat; a.unshift('Явка')
new Dygraph(
	document.getElementById("plot"),
	plot_,
	{
		width: '100%', height: '400',
		labels: a,
		customBars: true,
		legend: 'always',
		legendFormatter: legend,
		dateWindow: [0, 100],
	}
)

function legend(data)
{
	if (data.x == null)
		return '<br>' + data.series.map(function(series) { return series.dashHTML + ' ' + series.labelHTML }).join('<br>');

	var html = 'Участки с явкой <b>'+data.xHTML+'%</b>'
	data.series.forEach(function(series) {
		html += '<br>' + series.dashHTML + ' ' + series.labelHTML + ': ' + (series.yHTML||'0') //+'%'
	})
	return html
}
</script>

</section>
