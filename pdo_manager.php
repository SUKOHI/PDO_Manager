<?php

class PDO_Manager {

    private $_db, $_date, $_last_query;
    private $_display = false;
    private $_insert_sequence_values = array();
    private $_insert_sequence_params = array();

    public function __construct($dsn, $username, $password, $encoding='utf8') {

        try {

	        $this->_db = new PDO($dsn, $username, $password);
	        $this->_db->query('SET NAMES '. $encoding);
	        $this->_date = time();
	        $this->_last_query = '';

        } catch (PDOException $e) {

        	die('Failed to Connect to Database.');

        }

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
        $sql = 'SELECT '. $fields .' FROM '. $table .' '. $where;
        $result = $this->query($sql, $params);
        
        while($row = $result->fetch($fetch_mode)) {

        	$return[] = $row;

        }

        return $return;

    }
    
    public function selectOne($table, $field, $where='', $params=array()) {

        $sql = 'SELECT '. $field .' FROM '. $table .' '. $where;
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

    	if(is_array($values)) {
    		
    		$params = $values;
    		$questions = array();
    		
    		foreach ($params as $value) {
    			
    			$questions[] = '?';
    			
    		}
    		
    		$values = implode(', ', $questions);
    		
    	}
    	
        $sql = 'INSERT INTO '. $table .' ('. $fields .') VALUES ('. $values .')';
        return $this->query($sql, $params);

    }

    public function update($table, $set_values, $where, $params=array()) {
    	
        $sql = 'UPDATE '. $table .' SET '. $set_values .' '. $where;
        return $this->query($sql, $params);

    }
    
    public function delete($table, $where, $params) {

        if($where == '') { die('Delete: There is no WHERE phrase.'); }

        $sql = 'DELETE FROM '. $table .' '. $where;
        return $this->query($sql, $params);

    }

    public function insertSelect($insert_table, $insert_fields, $select_table, $select_fields, $select_where, $select_params) {

        $sql = 'INSERT INTO '. $insert_table .' ('. $insert_fields .') SELECT '.
        $select_fields .' FROM '. $select_table .' '. $select_where;
        $result = $this->query($sql, $select_params);

    }

    public function insertOnDuplicateKey($table, $insert_fields, $insert_values, $update_set_values, $params=array()) {

        $sql = 'INSERT INTO '. $table .' ('. $insert_fields .') VALUES ('. $insert_values .') ON DUPLICATE KEY UPDATE '. $update_set_values;
        return $this->query($sql, $params);

    }

    public function insertSequence($values, $params) {

        $this->_insert_sequence_values[] = '('. $values .')';
        $this->_insert_sequence_params = array_merge($this->_insert_sequence_params, $params);

    }

    public function insertSequenceCommit($table, $fields) {

        $insert_sequence_values = $this->_insert_sequence_values;
        $insert_sequence_values_count = count($insert_sequence_values);
        $insert_sequence_params = $this->_insert_sequence_params;

        if($insert_sequence_values_count > 0) {

	        $values = implode(',', $insert_sequence_values);
	        $sql = 'INSERT INTO '. $table .' ('. $fields .') VALUES '. $values;
	        $this->query($sql, $insert_sequence_params);
	        $this->insertDbSequenceRemove();

        }

    }

    public function insertSequenceRemove() {

        $this->_insert_sequence_values = array();
        $this->_insert_sequence_params = array();

    }

    public function getLastInsertId($name='') {

        return $this->_db->lastInsertId($name);

    }
    
    public function getNotExitsValues($table, $field, $original_values) {
    	
    	$returns = $wheres = $params = array();
    	
    	foreach ($original_values as $original_value) {
    		
    		$wheres[] = $field .' = ?';
    		$params[] = $original_value;
    		
    	}
    	
    	$where = 'WHERE '. implode(' OR ', $wheres);
    	
    	$exists_values = $this->getDbSelect($table, $field, $where, $params);
    	$exists_values_count = count($exists_values);
    	
    	for($i = 0; $i < $exists_values_count; $i++) {
    		
    		$target_value = $exists_values[$i][$field];
    		$array_index = array_search($target_value, $original_values);
    		unset($original_values[$array_index]);
    		
    	}
    	
    	return array_values($original_values);
    	
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

}

/*** Sample

	$pdo = new PDO_Manager(DSN, DSN_USER, DSN_PASS);
	$pdo->test();
	
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

***/
