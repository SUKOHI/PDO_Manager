<?php

class PDO_Operation {

    var $db;
    var $date;

    /*  Constructor  */

        function __construct($encoding='utf8') {

            try {

                $this->db = new PDO(DSN, DSN_USER, DSN_PASS);
                $this->db->query('SET NAMES '. $encoding);
                $this->date = time();

            } catch (PDOException $e) {

                die('Failed to Connect to Database.');

            }

        }

        function getPrepare($sql, $params) {

            $params = array_map(array($this, 'getPrepareParam'), $params);

            $curpos = 0;
            $curph  = count($params)-1;

            for ($i=strlen($sql)-1; $i>0; $i--) {

              if ($sql[$i] !== '?')  continue;
              if ($curph < 0 || !isset($params[$curph]))
            $sql = substr_replace($sql, 'NULL', $i, 1);
              else
            $sql = substr_replace($sql, $params[$curph], $i, 1);

              $curph--;
            }
            unset($curpos, $curph, $params);
            return $sql;

        }

        function getPrepareParam($param) {

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

        public $last_query = '';

        function getDbQuery($sql, $params=array()) {

            $this->last_query = $this->getPrepare($sql, $params);

            if($this->_queryDisplay) {

                echo $this->last_query .'<hr>';

            }

            return $this->db->query($this->last_query);

        }

        function getDbSelect($table, $fields='*', $where='', $params=array(), $fetch_mode=PDO::FETCH_ASSOC) {

            $return = array();

            $sql = 'SELECT '. $fields .' FROM '. $table .' '. $where;
            $result = $this->getDbQuery($sql, $params);
            while($row = $result->fetch($fetch_mode)) {

                $return[] = $row;

            }

            return $return;

        }

        function getDbSelectOne($table, $field, $where='', $params=array()) {

            $sql = 'SELECT '. $field .' FROM '. $table .' '. $where;
            $result = $this->getDbQuery($sql, $params);
            $row = $result->fetch();
            return $row[0];

        }

        function getDbSelectCount($table, $where='', $params=array()) {

            $field = 'COUNT(*)';
            return $this->getDbSelectOne($table, $field, $where, $params);

        }

        function insertDb($table, $fields, $values, $params=array()) {

            $sql = 'INSERT INTO '. $table .' ('. $fields .') VALUES ('. $values .')';
            return $this->getDbQuery($sql, $params);

        }

        function updateDb($table, $set_values, $where, $params=array()) {

            $sql = 'UPDATE '. $table .' SET '. $set_values .' '. $where;
            return $this->getDbQuery($sql, $params);

        }
        
        function deleteDb($table, $where, $params) {

            if($where == '') { die('Delete: There is no WHERE phrase.'); }

            $sql = 'DELETE FROM '. $table .' '. $where;
            return $this->getDbQuery($sql, $params);

        }

        function insertSelectDb($insert_table, $insert_fields, $select_table, $select_fields, $select_where, $select_params) {

            $sql = 'INSERT INTO '. $insert_table .' ('. $insert_fields .') SELECT '.
                        $select_fields .' FROM '. $select_table .' '. $select_where;
            $result = $this->getDbQuery($sql, $select_params);

        }

        function insertDbOnDuplicateKey($table, $insert_fields, $insert_values, $update_set_values, $params=array()) {

            $sql = 'INSERT INTO '. $table .' ('. $insert_fields .') VALUES ('. $insert_values .') ON DUPLICATE KEY UPDATE '. $update_set_values;
            return $this->getDbQuery($sql, $params);

        }

        var $insert_sequence_values = array();
        var $insert_sequence_params = array();

        function insertDbSequence($values, $params) {

            $this->insert_sequence_values[] = '('. $values .')';
            $this->insert_sequence_params = array_merge($this->insert_sequence_params, $params);

        }

        function insertDbSequenceCommit($table, $fields) {

            $insert_sequence_values = $this->insert_sequence_values;
            $insert_sequence_values_count = count($insert_sequence_values);
            $insert_sequence_params = $this->insert_sequence_params;

            if($insert_sequence_values_count > 0) {

                $values = implode(',', $insert_sequence_values);
                $sql = 'INSERT INTO '. $table .' ('. $fields .') VALUES '. $values;
                $this->getDbQuery($sql, $insert_sequence_params);
                $this->insertDbSequenceRemove();

            }

        }

        function insertDbSequenceRemove() {

            $this->insert_sequence_values = array();
            $this->insert_sequence_params = array();

        }

        function getLastInsertId($name='') {

            return $this->db->lastInsertId($name);

        }

        function getLastQuery() {

            return $this->last_query;

        }

        private $_queryDisplay = false;

        function setQueryDisplay($flag=true) {

            $this->_queryDisplay = $flag;

        }

}
