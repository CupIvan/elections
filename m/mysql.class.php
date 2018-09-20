<?php
/**
 * Класс для работы с MySQL
 * @author CupIvan <mail@cupivan.ru>
 */

require_once 'mysql.functions.trait.php';

class mysql
{
	static $connection = null;
	static $encoding   = 'utf-8';
	static $encoding_  = 'utf-8'; // предыдущая кодировка

	static $host = '127.0.0.1';
	static $user = 'root';
	static $pass = '';
	static $base = '';

	static $history = array();
	static $errors  = array();

	use mysql_functions;

	static $data = [];

	static public function setEncoding($encoding)
	{
		self::$encoding_= self::$encoding;
		self::connect();
		mysqli_set_charset(self::$connection, str_replace('utf-8', 'utf8', self::$encoding = $encoding));
	}
	static public function restoreEncoding()
	{
		self::setEncoding(self::$encoding_);
	}

	static function connect()
	{
		if (self::$connection !== null) return true;
		self::$connection = @mysqli_connect(self::$host, self::$user, self::$pass);
		if (!self::$connection)
			return false;
		if (!mysqli_select_db(self::$connection, self::$base))
		{
			$errno = mysqli_errno(self::$connection);
			if ($errno) array_push(self::$errors, $errno."\n".mysqli_error(self::$connection));
			return false;
		}
		self::setEncoding(self::$encoding);
		return true;
	}
	static function prepare_callback($x){ // use ($data) {
		$data = $GLOBALS['prepare_data'];
		$ind  =&$GLOBALS['prepare_index'];
		if ($x['field']) { $x['key'] = $x['field']; $x['eq'] = true; }
		$name = $x['key'];
		$eq   = $x['eq'] ? "`$name` = " : '';

		$value = '';

		if (is_null($data)) $GLOBALS['prepare_skip'] = true;
		else
		if (is_scalar($data)) $value = $data;
		else
		if (is_array($data))
		{
			if ($name)
				if (isset($data[$name])) $value = $data[$name];
				else
					$GLOBALS['prepare_skip'] = true;
			else
			if (isset($data[$ind]) && $data[$ind] !== NULL)  $value = $data[$ind++];
			else
			if (empty($name)) $value = $data;
			else
				$GLOBALS['prepare_skip'] = true;
		}
		else
		if ($data instanceOf Model && $x['type'] != 'kv')
		{
			if (is_null($value = $data->$name))
				$GLOBALS['prepare_skip'] = true;
		}

		if ($GLOBALS['prepare_skip']) return '';

		switch($x['type'])
		{
			case 'p': return $eq.$value;
			case 'i': return $eq.(int)$value;
			case 'f': return $eq.(float)$value;
			case 's':
			case 'S':
//				if ($value === '') $GLOBALS['prepare_skip'] = true;
				if ($x['type'] == 'S') { $value = "%$value%"; $eq = str_replace('=', 'LIKE', $eq); }
				return $eq.self::prepareString($value);
			case 'd':  return date('"Y-m-d"', is_numeric($value) ? $value : strtotime($value));
			case 'dt': return date('"Y-m-d H:i:s"', is_numeric($value) ? $value : strtotime($value));
			case 'ai': case 'as':
				if (empty($value)) { $GLOBALS['prepare_skip'] = true; return ''; }
				$a = array();
				if (is_scalar($value)) $value = is_array($data) ? $data : array($data);
				foreach ($value as $v)
					if ($x['type'] == 'ai')
					{
						if (is_numeric($v)) $a[$v] = 1;
					}
					else
					if ($x['type'] == 'as')
					{
						$v = self::prepareString($v);
						$a[$v] = 1;
					}
				return str_replace('=', 'IN', $eq).implode(',', array_keys($a));
			case 'kv': case 'kvf':
				$st = ''; $glue = ($x['type'] == 'kvf') ? ' AND ' : ', ';
				if ($value instanceOf Model) $value = $value->getData();
				else
				if ($data instanceOf Model) $value = $data->getData();

				if (is_array($value))
				foreach ($value as $k => $v)
				{
					$st .= ($st?$glue:'');
					if (is_array($v)) // задан диапазон значений
						$st .= "`$k` >= ".self::prepareString($v[0])." AND `$k` < ".self::prepareString($v[1]);
					else
					{
						$st .= strpos($v, '%') !== false && $GLOBALS['prepare_is_select'] ? "`$k` LIKE " : "`$k` = ";
						if (is_bool($v)) $v = $v ? 1 : 0;
						if (strpos($v, '=') === 0) $st .= substr($v, 1); // COMMENT: например '= NOW()'
						else
//						if (is_numeric($v)) $st .= $v;
//						else
						if (is_null($v))    $st .= 'NULL';
						else $st .= self::prepareString($v);
					}
				}
				if (!$st) $st = 1;
				return $st;
			default:
				return '';
		}
	}
	static function prepareString($st)
	{
		return '"'.addslashes($st).'"';
	}
	static function prepare($sql, $data='_stored', $d2='_stored', $d3=NULL, $d4=NULL, $d5=NULL, $d6=NULL)
	{
		$sql = "\n$sql ";
		if ($data === '_stored') $data = self::$data;

		if ($d2 !== '_stored')
			$data = array($data, $d2, $d3, $d4, $d5, $d6);

		self::$data = $data;

		// FIXME: убрать глобальные переменные
		$GLOBALS['prepare_is_select'] = stripos(" $sql", 'SELECT');
		$GLOBALS['prepare_data']  = $data;
		$GLOBALS['prepare_index'] = 0;
		$GLOBALS['prepare_skip']  = false;

		$sql = preg_replace_callback('#((?<field>[a-z0-9_]+)=)?\?(?<type>s|S|i|f|dt|d|p|ai|as|kvf|kv):?(?<key>[a-z0-9_]*)(?<eq>=?)#i',
			'mysql::prepare_callback', $sql);
		if ($GLOBALS['prepare_skip']) $sql = '';

		unset($GLOBALS['prepare_is_select']);
		unset($GLOBALS['prepare_data']);
		unset($GLOBALS['prepare_index']);
		unset($GLOBALS['prepare_skip']);

		return $sql;
	}
	static function query($sql)
	{
		$t = microtime(true);
		$sql_dump = mb_strlen($sql) > 5*1024 ? mb_substr($sql, 0, 5*1024 - 3).'...' : $sql;
		self::connect();
		$res  = @mysqli_query(self::$connection, $sql);
		$errno = mysqli_errno(self::$connection);
		if ($errno) array_push(self::$errors, $errno."\n".mysqli_error(self::$connection)."\n".$sql_dump);
		array_push(self::$history, $sql_dump.' -- '.$t.' + '.round(microtime(true)-$t, 4));
		return $errno ? false : $res;
	}
	static function fetch($res)
	{
		return mysqli_fetch_assoc($res);
	}

	static function getLastError()
	{
		$errno = mysqli_errno(self::$connection);
		return $errno ? array('errno' => $errno) : array();
	}
}
