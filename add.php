<?php

require_once 'init.php';

session_start();

if (isset($_POST['add']) && isset($_REQUEST['url']))
{
	$url = $_REQUEST['url'];
	$st = file_get_contents($url);
	$st = iconv('cp1251', 'utf-8', $st);
	$a = ['url'=>$url, 'state'=>'wait'];
	if (preg_match('#region=(\d+)#', $url, $m)) $a['region'] = $m[1];
	$a['type'] = empty($a['region']) ? 'federate' : 'regional';
	if (preg_match('#vrn=(\d+)#', $url, $m)) $a['uniq'] = $m[1];
	if (preg_match('#class="headers".+?class="headers"><b>(.+?)</b>#s', $st, $m)) $a['title'] = $m[1];
	if (preg_match('#Дата голосования.+?<td>([^<]+)#s', $st, $m)) $a['date'] = date('Y-m-d', strtotime($m[1]));

	$res = empty($a['title']) ? false : mysql::insert('elections', $a);
	$_SESSION['message'] = '<b>'.($res?'OK':'ERROR').'</b> '.$a['title'];
	header('Location: '.$_SERVER['REQUEST_URI']);
	exit;
}
?>

<h1>Ручное добавление выборов</h1>

<?if (isset($_SESSION['message'])){ echo '<div style="display: table; padding: 10px; background: #EEE; margin: 10px 0;">'.$_SESSION['message'].'</div>'; unset($_SESSION['message']);}?>
<form method="POST">
	<input type="url" name="url" placeholder="URL на сайт izbirkom.ru" style="width: 800px">
	<input type="submit" name="add" value="Добавить">
</form>
