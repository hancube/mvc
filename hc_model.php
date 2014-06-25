<?php

/**
 * Copyright (c) 2010, Sungok Lim, HanCube.com.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *	* Redistributions of source code must retain the above copyright
 *	  notice, this list of conditions and the following disclaimer.
 *
 *	* Redistributions in binary form must reproduce the above
 *	  copyright notice, this list of conditions and the following
 *	  disclaimer in the documentation and/or other materials provided
 *	  with the distribution.
 *
 *	* Neither the names of Sungok Lim or HanCube.com, nor
 *	  the names of its contributors may be used to endorse or promote
 *	  products derived from this software without specific prior
 *	  written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY
 * WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
 * OF SUCH DAMAGE.
 */

/*
 * This is a BSD License approved by the Open Source Initiative (OSI).
 * See:  http://www.opensource.org/licenses/bsd-license.php
 */

/**
 * Config Item
 * 
 * 'key' => array (
 *          'field'        => 'dollar',    // if it's empty, it's not a database field
 *          'operator'     => '='          // =(equal), %~(like '%~'), ~%(like '~%'), %~%(like '%~%')
 *                                         // if it's not setup and where is true, "=" is default
 *          'sailthru_var' => 'Dollar',
 *          'pk'           => TRUE,
 *          'autoinc'   => TRUE,           // Auto Increase - if it's true, it's not adding on "insert" fields even it has a value
 *          'type'         => 'file',      // Input Type and Value Type
 *          'default'      => '0'00',
 *          'required'     => TRUE,
 *          'rules'        => array (
 *              date          => TRUE,      // More rules in validate-1.0.0
 *              url           => TRUE,
 *              enum          => array(M,F),
 *              email         => TRUE,
 *              numeric       => TRUE,
 *              min_length    => TRUE,
 *              max_length    => 5
 *          ),
 *          value       => '2000',
 *          formatted   => '2,000.00',
 *          error       => 'ERROR_CODE',
 *          where       => TRUE         // if it's true, and there is value in config, then add where condition in query using operator
 *          )
        )
 */

class HCModel{
    public $table;  // Representative Table
    public $schema; // Table Fields Information
    public $fields; // Input Fields by Actions
    public $config; // Merged Fields Configuration for an Action

    public $action;
    public $data;
    public $db;
    public $memcache;

    public $start;
    public $limit;
    public $orderby;
    public $direction;

    public function __construct($action) {
        Debug::ttt('HCModel::__construct("'.$action.'")');

        $this->action = $action;

        $this->mergeConfig();
        if ($this->action != 'doc') {
            unset($this->fields);
        }
    }
    public function setValues(& $args) {
        Debug::ttt('HCModel::setValues()');
        if (!isset($this->config) || !is_array($this->config)) return false;
        
        foreach ($this->config as $key => $val){
            if (isset($args[$key])) {
                $this->config[$key]['value'] = $args[$key];
            }
        }
        return true;
    }
    public function mergeConfig() {
        Debug::ttt('HCModel::mergeConfig()');
        if (empty($this->action)) return false;
        if (!isset($this->fields[$this->action]) || !is_array($this->fields[$this->action])) return false;
        foreach ($this->fields[$this->action] as $key => $val){
            if (isset($this->schema[$key])) {
                $this->config[$key] = array_merge($this->schema[$key], $this->fields[$this->action][$key]);
            }else {
                $this->config[$key] = $this->fields[$this->action][$key];
            }
        }
        return true;
    }
    public function insert($options = array()) {
        Debug::ttt('HCModel::insert()');
      /*
        * $options = array (
        *      'table' => 'table1'
        *      'id' => 'field1'
        *      'fields' => array(
        *          'field1' => 'value1',
        *          'field1' => 'value1',
        *          'field1' => 'value1'
        *      )
        * );
        */

        // Set Default Options
        if (!isset($options['table']) 
            || (isset($options['table']) && empty($options['table']))) {
            $options['table'] = $this->table;
        }
        
        if (!isset($options['fields']) 
            || (isset($options['fields']) && count($options['fields'])) <= 0) {
            foreach ($this->config as $key => $item) {
                if (isset($item['pk']) && $item['pk'] === true && isset($item['autoinc']) && $item['autoinc'] === true) {
                    // skip
                }else if (isset($item['field']) && isset($item['value'])) {
                    $options['fields'][$item['field']] = $item['value'];
                }
            }
        }

        if (!isset($options['id'])
            || (isset($options['id']) && empty($options['id']))) {
            foreach ($this->config as $key => $item) {
                if (isset($item['pk']) && $item['pk'] === true) {
                    $options['id'] = $item['field'];
                }
            }
        }
        Debug::ppp($options);

        $fields = '';
        $values = '';
        $values_memcache = '';
        foreach ($options['fields'] as $field => $value) {
            $fields .= $field.',';
            $values .= ':'.$field.',';
            $values_memcache .= '"'.$value.'",';
        }
        $fields = substr($fields, 0, -1);
        $values = substr($values, 0, -1);
        $values_memcache = substr($values_memcache, 0, -1);

        $query = 'insert into '.$options['table'].' ('.$fields.') values ('.$values.')';
        $query_memcache = 'insert into '.$options['table'].' ('.$fields.') values ('.$values_memcache.')';
        Debug::ppp($query);
        Debug::ppp($query_memcache);

        try {
            $stmt = $this->db->prepare($query);
            foreach ($options['fields'] as $field => $value) {
                $stmt->bindParam(':'.$field, $options['fields'][$field]);
                Debug::ppp(':'.$field.', '.$options['fields'][$field]);

                // I don't know why it doesn't work below:
                // $stmt->bindParam(':'.$field, $value);
            }
            $stmt->execute();

            // Get Inserted ID
            if (isset($options['id']) && empty($options['fields'][$options['id']])) {
                $config_id = strtolower($options['id']);
                $this->config[$config_id]['value'] = $this->db->lastInsertId();
                if (MVC_VERSION == '2.1.8') {
                    $this->data[$config_id] = $this->config[$config_id]['value'];
                }else if (MVC_VERSION == '3.0.0') {
                    $this->data['info'][$config_id] = $this->config[$config_id]['value'];
                }
                Debug::ppp($this->data);
            }

            if($stmt->rowCount() <= 0) {
                return 'ERROR_DB_NO_AFFECTED';
            }
        } catch (PDOException $exc) {
            Debug::Error($exc);
            return 'ERROR_DB_INSERT';
        }

        return true;
    }
    public function switchOperator($item) {
        Debug::ttt('HCModel::switchOperator()');
        Debug::ppp($item);
        if (!isset($item[2])) return $item;

        switch ($item[2]) {
            case '%~':
                $item[2] = 'like';
                $item[3] = '%'.$item[3];
                break;
            case '~%':
                $item[2] = 'like';
                $item[3] = $item[3].'%';
                break;
            case '%~%':
                $item[2] = 'like';
                $item[3] = '%'.$item[3].'%';
                break;
        }
        return $item;
    }
    public function update($options = array()) {
        Debug::ttt('HCModel::update()');
       /*
        * $options = array (
        *      'table' => 'table1'
        *      'fields' => array(
        *          'field1' => 'value1',
        *          'field1' => 'value1',
        *          'field1' => 'value1'
        *      ),
        *      'where' = array (
        *          array ('where','field1','=','value1'),
        *          array ('and','field1','=','value1'),
        *          array ('or','field1','=','value1')
        *      )
        * );
        *
        */
        if (!isset($options['where'])
            || isset($options['where'])
            && count($options['where']) <= 0
            && isset($this->config)
            && is_array($this->config)
            && count($this->config) > 0) {
            $conn = 'where';
            $config = $this->config;
            foreach ($config as $item) {
                if (isset($item['where']) && $item['where'] === true
                    && isset($item['value']) && !empty($item['value'])) {
                    if (!isset($item['operator'])) $item['operator'] = '=';
                    $tmp = array($conn, $item['field'], $item['operator'], $item['value']);
                    $tmp = $this->switchOperator($tmp);
                    $options['where'][] = $tmp;
                    $conn = 'and';
                }
            }
        }

        if (!isset($options['where'])
            || isset($options['where']) 
            && count($options['where']) <= 0) {
            return 'ERROR_DB_NO_CONDITION';
        }
        
        if (!isset($options['table']) 
            || isset($options['table']) 
            && empty($options['table'])) {
            $options['table'] = $this->table;
        }
        
        if (!isset($options['fields']) 
            || isset($options['fields']) 
            && count($options['fields']) <= 0) {
            foreach ($this->config as $key => $item) {
                if (isset($item['pk']) && $item['pk'] === true) continue;
                if (isset($item['field']) && isset($item['value'])) {
                    $options['fields'][$item['field']] = $item['value'];
                }
            }  
        }

        $set = '';
        foreach ($options['fields'] as $field => $value) {
            $set .= $field.' = :'.$field.',';
        }
        $set = substr($set, 0, -1);

        $where = '';
        if (isset($options['where'])) {
            for ($i=0; $i<count($options['where']); $i++) {
                $where .= ' '.$options['where'][$i][0];
                $where .= ' '.$options['where'][$i][1];
                $where .= ' '.$options['where'][$i][2];
                $where .= ' :w'.$i;
            }
        }

        $query = 'update '.$options['table'].' set '.$set.' '.$where;

        Debug::ppp($query);
        Debug::ppp($options);

        try {
            $stmt = $this->db->prepare($query);
            foreach ($options['fields'] as $field => $value) {
                $stmt->bindParam(':'.$field, $options['fields'][$field]);
                Debug::ppp(':'.$field.', '.$options['fields'][$field]);

                // I don't know why it doesn't work below:
                // $stmt->bindParam(':'.$field, $value);
            }
            for ($i=0; $i<count($options['where']); $i++) {
                $stmt->bindParam(':w'.$i, $options['where'][$i][3]);  
                Debug::ppp(':w'.$i.', '.$options['where'][$i][3]);
            }
            $stmt->execute();

            if($stmt->rowCount() <= 0) {
                return 'ERROR_DB_NO_AFFECTED';
            }
        } catch (PDOException $exc) {
            Debug::Error($exc);
            return 'ERROR_DB_UPDATE';
        }

        return true;
    }
    public function delete($options = array()) {
        Debug::ttt('HCModel::delete()');
      /*
         * $options = array (
         *      'table' => 'table1'
         *      'where' = array (
         *          array ('where','field1','=','value1'),
         *          array ('and','field1','=','value1'),
         *          array ('or','field1','=','value1')
         *      )
         * );
         *                                  
         */

        if (!isset($options['where'])
            || isset($options['where'])
            && count($options['where']) <= 0
            && isset($this->config)
            && is_array($this->config)
            && count($this->config) > 0) {
            $conn = 'where';
            $config = $this->config;
            foreach ($config as $item) {
                if (isset($item['where']) && $item['where'] === true
                    && isset($item['value']) && !empty($item['value'])) {
                    if (!isset($item['operator'])) $item['operator'] = '=';
                    $tmp = array($conn, $item['field'], $item['operator'], $item['value']);
                    $tmp = $this->switchOperator($tmp);
                    $options['where'][] = $tmp;
                    $conn = 'and';
                }
            }
        }

        if (!isset($options['where'])
            || isset($options['where'])
            && count($options['where']) <= 0) {
            return 'ERROR_DB_NO_CONDITION';
        }

        if (!isset($options['table']) 
            || isset($options['table']) 
            && empty($options['table'])) {
            $options['table'] = $this->table;
        }
        
        $where = '';
        if (isset($options['where'])) {
            for ($i=0; $i<count($options['where']); $i++) {
                $where .= ' '.$options['where'][$i][0];
                $where .= ' '.$options['where'][$i][1];
                $where .= ' '.$options['where'][$i][2];
                $where .= ' :w'.$i;
            }
        }
        
        $query = 'delete from '.$options['table'].' '.$where;
        Debug::ppp($query);
        Debug::ppp($options);
        
        try {
            $stmt = $this->db->prepare($query);
            for ($i=0; $i<count($options['where']); $i++) {
                $stmt->bindParam(':w'.$i, $options['where'][$i][3]);  
                Debug::ppp(':w'.$i.', '.$options['where'][$i][3]);
            }
            $stmt->execute();
            
            if($stmt->rowCount() <= 0) {
                return 'ERROR_DB_NO_AFFECTED';
            }
        } catch (PDOException $exc) {
            Debug::Error($exc);
            return 'ERROR_DB_DELETE';
        }

        return true;        
    }

    public function isSetVal($config_key) {
        $result = false;

        if (isset($this->config[$config_key]['type']) && !empty($this->config[$config_key]['type'])) {
            $value_type = $this->config[$config_key]['type'];
        }else {
            $value_type = '';
        }

        switch ($value_type) {
            case 'file':
                if (isset($this->config[$config_key]['value'])
                    && is_array($this->config[$config_key]['value'])
                    && $this->config[$config_key]['value']['size'] > 0
                    && $this->config[$config_key]['value']['error'] == 0) {
                    $result = true;
                }
                break;
            default:
                if (isset($this->config[$config_key]['value']) && !empty($this->config[$config_key]['value'])) {
                    $result = true;
                }
        }

        return $result;
    }
    public function select($options = array()) {
        Debug::ttt('HCModel::select()');
        
        /*
         * $options = array (
         *      'select' => array (
         *          'a.field1', 
         *          'field1', 
         *          'field1', 
         *          'format(field1)'
         *      ),
         *      'from' => array (
         *          'Lists l',
         *          'left join',
         *          'Items i',
         *          'on l.Id = i.listId'
         *      ),
         *      'where' => array (
         *          array ('where','field1','=','value1'),
         *          array ('and','field1','=','value1'),
         *          array ('or','field1','=','value1')
         *      )
         * )
         *
         */
        Debug::ppp($options);

        if (!isset($options['where'])
            || isset($options['where'])
            && count($options['where']) <= 0
            && isset($this->config)
            && is_array($this->config)
            && count($this->config) > 0) {
            $conn = 'where';
            $config = $this->config;
            foreach ($config as $item) {
                if (isset($item['where']) && $item['where'] === true
                    && isset($item['value']) && !empty($item['value'])) {
                    if (!isset($item['operator'])) $item['operator'] = '=';
                    $tmp = array($conn, $item['field'], $item['operator'], $item['value']);
                    $tmp = $this->switchOperator($tmp);
                    $options['where'][] = $tmp;
                    $conn = 'and';
                }
            }
        }

        $select = '';
        if (isset($options['select'])) {
            for ($i=0; $i<count($options['select']); $i++) {
                $select .= $options['select'][$i].',';
            }
        }else {
            foreach ($this->schema as $key => $item) {
                if (isset($item['field']) && !empty($item['field'])) {
                    $select .= $item['field'].' as '.$key.',';
                }
            }
        }
        $select = substr($select, 0, -1);

        $from = '';
        if (isset($options['from'])) {
            for ($i=0; $i<count($options['from']); $i++) {
                $from .= ' '.$options['from'][$i];
            }
        }else {
            $from = $this->table;
        }

        $query_memcache  = ' select '.$select;
        $query_memcache .= ' from '.$from;

        $where = '';
        if (isset($options['where'])) {
            for ($i=0; $i<count($options['where']); $i++) {
                $where .= ' '.$options['where'][$i][0];
                $where .= ' '.$options['where'][$i][1];
                $where .= ' '.$options['where'][$i][2];
                $where .= ' :w'.$i;

                $query_memcache .= ' '.$options['where'][$i][0];
                $query_memcache .= ' '.$options['where'][$i][1];
                $query_memcache .= ' '.$options['where'][$i][2];
                $query_memcache .= ' '.$options['where'][$i][3];
            }
        }

        $order = '';
        if (isset($this->orderby)) {
            if (!isset($this->direction)) $this->direction = 'ASC';
            $order = ' order by '.$this->orderby.' '.$this->direction;
        }
        
        $limit = '';
        if (isset($this->start) && isset($this->limit)) {
            $limit = ' limit '.$this->start.', '.$this->limit;
        }

        $query  = ' select '.$select;
        $query .= ' from '.$from;
        $query .= ' '.$where;
        $query .= ' '.$order;
        $query .= ' '.$limit;

        $query_memcache .= ' '.$order;
        $query_memcache .= ' '.$limit;
        Debug::ppp($query);
        Debug::ppp($query_memcache);

        if (USE_MEMCACHE) {
            try {
                $cached_data = $this->memcache->get($query_memcache);
            } catch (Exception $e) {}
        }

        if (isset($cached_data) && is_array($cached_data)) {
            Debug::ppp('HCModel::select::Memcached_data', 'Fuchsia');
            Debug::ppp('MemCache_key: '.$query_memcache, 'Fuchsia');

            if (MVC_VERSION == '2.1.8') {
                $this->data = $cached_data;
            }else if (MVC_VERSION == '3.0.0') {
                $this->data['items'] = $cached_data;
            }
            Debug::ppp($this->data, 'Fuchsia');
        }else {
            try {
                $stmt = $this->db->prepare($query);
                if (isset($options['where'])) {
                    for ($i=0; $i<count($options['where']); $i++) {
                        $stmt->bindParam(':w'.$i, $options['where'][$i][3]);
                        Debug::ppp(':w'.$i.', '.$options['where'][$i][3]);
                    }
                }
                $stmt->execute();

                if (MVC_VERSION == '2.1.8') {
                    $this->data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }else if (MVC_VERSION == '3.0.0') {
                    $this->data['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                if (USE_MEMCACHE) {
                    try {
                        if (MVC_VERSION == '2.1.8') {
                            $this->memcache->set($query_memcache, $this->data, false, 60);
                        }else if (MVC_VERSION == '3.0.0') {
                            $this->memcache->set($query_memcache, $this->data['items'], false, 60);
                        }

                        Debug::ppp($query_memcache, 'Fuchsia');
                    } catch (Exception $e) {}
                }

            } catch (PDOException $exc) {
                Debug::Error($exc);
                return 'ERROR_DB_SELECT';
            }
        }

        if (MVC_VERSION == '2.1.8') {
            if(!isset($this->data) || !is_array($this->data) || count($this->data) <= 0) {
                return 'ERROR_DB_NO_DATA';
            }
        }else if (MVC_VERSION == '3.0.0') {
            if(!isset($this->data['items']) || !is_array($this->data['items']) || count($this->data['items']) <= 0) {
                return 'ERROR_DB_NO_DATA';
            }
        }

        return true;
    }

    public function query($query = '') {
        Debug::ttt('HCModel::query()');
        if (empty($query)) return false;

        Debug::ppp($query);
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            if (MVC_VERSION == '2.1.8') {
                $this->data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }else if (MVC_VERSION == '3.0.0') {
                $this->data['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            Debug::ppp($this->data);
        } catch (PDOException $exc) {
            Debug::Error($exc);
            return 'ERROR_DB_SELECT';
        }
        if (MVC_VERSION == '2.1.8') {
            if(!isset($this->data) || !is_array($this->data) || count($this->data) <= 0) {
                return 'ERROR_DB_NO_DATA';
            }
        }else if (MVC_VERSION == '3.0.0') {
            if(!isset($this->data['items']) || !is_array($this->data['items']) || count($this->data['items']) <= 0) {
                return 'ERROR_DB_NO_DATA';
            }
        }
        return true;
    }
}
?>