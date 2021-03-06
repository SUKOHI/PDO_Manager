<?php

class PDO_Manager {

	public $date;
	private $_db, $_last_query;
	private $_display = false;
	private $_prefix = '';
	private $_insert_sequence_values = array();
	private $_insert_sequence_params = array();

	public function __construct($dsn, $username, $password, $encoding='utf8') {

		try {

			$this->_db = new PDO($dsn, $username, $password);
			$this->_db->query('SET NAMES '. $encoding);
			$this->_last_query = '';
			$this->date = time();

		} catch (PDOException $e) {

			die('Failed to Connect to Database.');

		}

	}

	public function setPrefix($prefix) {
	
		$this->_prefix = $prefix;
	
	}

	public function query($sql, $params=array()) {

		$this->last_query = $this->getPrepare($sql, $params);

		if($this->_display) {

			echo $this->last_query .'<hr>';

		}

		return $this->_db->query($this->last_query);

	}

	public function select($table, $fields='*', $where='', $params=array(), $fetch_mode=PDO::FETCH_ASSOC) {
		
		$return = array();
		$sql = 'SELECT '. $fields .' FROM '. $this->getTable($table) .' '. $where;
		$result = $this->query($sql, $params);
		
		while($row = $result->fetch($fetch_mode)) {

			$return[] = $row;

		}

		return $return;

	}
	
	public function selectOne($table, $field, $where='', $params=array()) {

		$sql = 'SELECT '. $field .' FROM '. $this->getTable($table) .' '. $where;
		$result = $this->query($sql, $params);
		$row = $result->fetch();
		return $row[0];

	}
	
	public function selectCount($table, $where='', $params=array(), $count_field='') {
		
		if($count_field == '') {
			
			$count_field = '*';
			
		}
		
		$field = 'COUNT('. $count_field .')';
		return $this->selectOne($table, $field, $where, $params);

	}

	public function insert($table, $fields, $values, $params=array()) {

		$insert_data = $this->correctInsertData($values, $params);
		$sql = 'INSERT INTO '. $this->getTable($table) .' ('. $fields .') VALUES ('. $insert_data['values'] .')';
		return $this->query($sql, $insert_data['params']);

	}

	public function update($table, $set_values, $where, $params=array()) {
		
		$sql = 'UPDATE '. $this->getTable($table) .' SET '. $set_values .' '. $where;
		return $this->query($sql, $params);

	}
	
	public function delete($table, $where, $params) {

		if($where == '') { die('Delete: There is no WHERE phrase.'); }

		$sql = 'DELETE FROM '. $this->getTable($table) .' '. $where;
		return $this->query($sql, $params);

	}
	
	public function beginTransaction() {
		
		return $this->_db->beginTransaction();
		
	}
	
	public function rollBack() {
		
		return $this->_db->rollBack();
		
	}
	
	public function commit() {
		
		return $this->_db->commit();
		
	}

	public function insertSelect($insert_table, $insert_fields, $select_table, $select_fields, $select_where, $select_params) {

		$sql = 'INSERT INTO '. $this->getTable($insert_table) .' ('. $insert_fields .') SELECT '.
		$select_fields .' FROM '. $this->getTable($select_table) .' '. $select_where;
		$result = $this->query($sql, $select_params);

	}

	public function insertOnDuplicateKey($table, $insert_fields, $insert_values, $update_set_values, $params=array()) {

		if(is_array($update_set_values)) {

			$params = $update_set_values;
			$update_set_values = $insert_values;
			
			$fields_count = substr_count($insert_fields, ',') + 1;
			$questions = array_pad(array(), $fields_count, '?');
			$insert_values = implode(', ', $questions);
		
		}
		
		$sql = 'INSERT INTO '. $this->getTable($table) .' ('. $insert_fields .') VALUES ('. $insert_values .') ON DUPLICATE KEY UPDATE '. $update_set_values;
		return $this->query($sql, $params);

	}

	public function insertSequence($values, $params=array()) {

		$insert_data = $this->correctInsertData($values, $params);
		$this->_insert_sequence_values[] = '('. $insert_data['values'] .')';
		$this->_insert_sequence_params = array_merge($this->_insert_sequence_params, $insert_data['params']);

	}

	public function insertSequenceCommit($table, $fields) {

		$insert_sequence_values = $this->_insert_sequence_values;
		$insert_sequence_values_count = count($insert_sequence_values);
		$insert_sequence_params = $this->_insert_sequence_params;

		if($insert_sequence_values_count > 0) {

			$values = implode(',', $insert_sequence_values);
			$sql = 'INSERT INTO '. $this->getTable($table) .' ('. $fields .') VALUES '. $values;
			$this->query($sql, $insert_sequence_params);
			$this->insertSequenceRemove();

		}

	}

	public function insertSequenceRemove() {

		$this->_insert_sequence_values = array();
		$this->_insert_sequence_params = array();

	}

	public function getLastInsertId($name='') {

		return $this->_db->lastInsertId($name);

	}
	
	public function getNotExitsValues($table, $original_data, $delimiter=':') {
		
		$returns = $wheres = $params = $value_strings = array();
		$original_correct_data = array();
		$fields = array();
			
		foreach ($original_data as $field => $original_values) {
			
			$fields[] = $field;
			
			foreach ($original_values as $original_values_index => $original_value) {

				$original_correct_data[$original_values_index][$field] = $original_value;
				
			}
			
		}
		
		foreach ($original_correct_data as $index => $original_correct_values) {
			
			$field_clauses = array();
			$value_string_parts = array();
			
			foreach ($original_correct_values as $field => $original_correct_value) {
				
				$field_clauses[] = $field .' = ?';
				$params[] = $original_correct_value;
				$value_string_parts[] = $original_correct_value;
				
			}
			
			$value_strings[$index] = implode($delimiter, $value_string_parts);
			$wheres[] = (count($field_clauses) > 1) ? '('. implode(' AND ', $field_clauses) .')' : implode(' AND ', $field_clauses);
			
		}
		
		$where = 'WHERE '. implode(' OR ', $wheres);
		
		$exists_data = $this->select($table, implode(',', $fields), $where, $params);
		$exists_data_count = count($exists_data);
		
		foreach ($exists_data as $exists_values) {
			
			$exists_value_string = implode($delimiter, $exists_values);
			$array_index = array_search($exists_value_string, $value_strings);
			unset($original_correct_data[$array_index]);
			
		}
		
		return array_values($original_correct_data);
		
	}

	public function getLastQuery() {

		return $this->_last_query;

	}

	public function t($flag=true) {
		
		$this->test($flag);
		
	}
	
	public function test($flag=true) {

		$this->_display = $flag;

	}

	private function getPrepare($sql, $params) {

		$params = array_map(array($this, 'getPrepareParam'), $params);
		$curph  = count($params)-1;
		$start = strlen($sql)-1;

		for ($i = $start; $i > 0; $i--) {

			if ($sql[$i] !== '?')  continue;
			if ($curph < 0 || !isset($params[$curph])) {

		  		$sql = substr_replace($sql, 'NULL', $i, 1);
		  	
			} else {
				
				$sql = substr_replace($sql, $params[$curph], $i, 1);
				
			}
		
			$curph--;
		  
		}
		
		unset($curph, $params);
		return $sql;

	}

	private function getPrepareParam($param) {

		if(is_numeric($param)) {

			$return = $param;

		} else {

			$return = addslashes($param);
	
			if(strstr($return, '\'') || strstr($return, '"')) {
	
				$return = str_replace(array('\'', '"'), '', $return);
	
			}
	
			$return = '\''. $return .'\'';

		}

		return $return;

	}

	private function correctInsertData($values, $params) {
	
		if(is_array($values)) {
	
			$params = $values;
			$questions = array_pad(array(), count($params), '?');
			$values = implode(', ', $questions);
	
		}
	
		return array(
				'params' => $params,
				'values' => $values
		);
	
	}
	
	public function getTable($table) {
		
		return $this->_prefix . $table;
		
	}

}

/*** Sample

	$pdo = new PDO_Manager(DSN, DSN_USER, DSN_PASS);
	$pdo->setPrefix('pdo_');	// Skippable
	$pdo->t();					// Skippable
	
	// Select
	
	$data = $pdo->select('table', '*', 'WHERE id = ?', array(100));
	print_r($data);
	echo $pdo->selectOne('table', 'title', 'WHERE id = ?', array(100));
	echo $pdo->selectCount('table', 'WHERE title = ?', array('title'), 'id');	// The last arg is skippable.

	// Insert
	
	$pdo->insert('table', 'title, link, description', array('title', 'link', 'description'));
	$pdo->insert('table', 'title, link, description', '?, ?, ?', array('title', 'link', 'description'));

	// Update
	
	$pdo->update('table', 'updated = ?', 'WHERE id = ?', array(
			time(), 100
	));
	
	// Delete
	
	$pdo->delete('table', 'WHERE id = ?', array(100));
	
	// Insert on Duplicate Key
	
	$this->insertOnDuplicateKey('table', 'title, link, description', 'title = ?, link = ?, description = ?', $params=array(
	
		'title', 'link', 'description', 'title', 'link', 'description'
	
	));
	$this->insertOnDuplicateKey('table', 'title, link, description', '?,?,?', 'title = ?, link = ?, description = ?', $params=array(
	
		'title', 'link', 'description', 'title', 'link', 'description'
	
	));
	
	// Not exists values
	
	$values = $mw->getNotExitsValues('table_name', array(
			
			'title' => array('title1', 'title2', 'title3'), 
			'artist' => array('link1', 'link2', 'link3'), 
			
	), ':');	// $delimiter is skippable. (Default: ":")
	
	// Date
	
	echo $pdo->date;
	
	// Transaction
	
	try {
		
		// Insert, update etc...
		
		$pdo->commit();
		
	} catch (PDOException $Exception) {
		
		$pdo->rollBack();
		
	}

***/
