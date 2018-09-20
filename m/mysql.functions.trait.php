<?php
/**
 * Дополнительные функции высокоуровневого API
 * @author CupIvan <mail@cupivan.ru>
 */
trait mysql_functions
{
	static $count = 0;

	// GET
	static function getList($sql)
	{
		$is_sql_count = false;
		if (strpos($sql, 'SELECT') !== false && strpos($sql, 'LIMIT') !== false)
		{
			$sql = preg_replace('/SELECT /', 'SELECT SQL_CALC_FOUND_ROWS ', $sql, 1);
			$is_sql_count = true;
		}

		$res = self::query($sql);
		if (!$res) return array();
		$ret = array();
		while ($row = mysqli_fetch_assoc($res))
			$ret[] = $row;
		self::$count = $is_sql_count ? (int)self::getValue('SELECT FOUND_ROWS()') : count($ret);
		return $ret;
	}
	static function getItem($sql)
	{
		$res = self::query($sql);
		if ($res === false) return array();
		$res = mysqli_fetch_assoc($res); if (is_null($res)) $res = array();
		return $res;
	}
	static function getValue($sql)
	{
		$res = self::query($sql);
		if (!is_object($res)) return NULL;
		$res = mysqli_fetch_array($res); $res = $res[0];
		return $res;
	}
	static function getValues($sql, $field)
	{
		$res = self::getList($sql);
		foreach ($res as $i => $a)
			$res[$i] = $a[$field];
		return $res;
	}
	static function getListCount()
	{
		return self::$count;
	}
	static function get($table, $id, $fields = NULL)
	{
		if (empty($fields)) $fields = '*';
		$p = is_numeric($id) ? '`id` = ?i' : '?p';
		$sql = mysql::prepare("SELECT $fields FROM `$table` WHERE $p LIMIT 1", $id);
		return mysql::getItem($sql);
	}

	// INSERT
	static function insert($table, $a)
	{
		$sql = self::prepare("INSERT INTO `$table` SET ?kv", $a);
		$res = mysql::query($sql);
		return mysqli_errno(self::$connection) ? false : mysql::getLastInsertId();
	}
	static function getLastInsertId() { return mysqli_insert_id(self::$connection); }
	static function insert_duplicate($table, $a)
	{
		$sql = self::prepare("INSERT INTO `$table` SET ?kv ON DUPLICATE KEY UPDATE ?kv", $a, $a);
		$res = mysql::query($sql);
		return mysqli_errno(self::$connection) ? false : self::getRowsCount();
	}
	// UPDATE
	static function update($table, $a, $where = NULL)
	{
		// передали id записи, которую нужно изменить
		if (is_numeric($where))
		{
			$a['id'] = $where;
			$where   = NULL;
		}
		// передали условие выборки записей на изменение
		if (is_null($where))
		{
			if (empty($a['id'])) return NULL;
			$where = self::prepare("`id` = ?i", $a['id']);
			unset($a['id']);
		}
		// $where в качестве хеша
		if (is_array($where))
		{
			$where = mysql::prepare('?kv', $where);
		}
		if (empty($a)) return 0;
		$sql = self::prepare("UPDATE `$table` SET ?kv WHERE ?p", $a, $where);
		$res = self::query($sql);
		if ($res === false) return false;
		return self::getRowsCount();
	}
	static function delete($table, $sql)
	{
		if (empty($sql)) return false;
		if (is_array($sql))   $sql = mysql::prepare('?kvf', $sql);
		if (is_numeric($sql)) $sql = mysql::prepare("`id` = ?i", $sql); // передали число
		$sql = self::prepare("DELETE FROM `$table` WHERE ?p", $sql);
		return self::query($sql);
	}
	static function replace($table, $a)
	{
		$sql = self::prepare("REPLACE INTO `$table` SET ?kv", $a);
		$res = mysql::query($sql);
		return mysqli_errno(self::$connection) ? false : true;
	}
	/** кол-во изменённых рядов */
	static function getRowsCount()
	{
		return mysqli_affected_rows(self::$connection);
	}

	// SEARCH
	static function search($table, $a, $fields = NULL, $orderBy = NULL)
	{
		if ( empty($fields))  $fields = '*';
		if (!empty($orderBy)) $orderBy = "ORDER BY $orderBy";
		$sql = mysql::prepare("SELECT $fields FROM `$table` WHERE ?kvf $orderBy", $a);
		return mysql::getList($sql);
	}
}
