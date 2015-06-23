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
 *          'autoinc'      => TRUE,        // Auto Increase - if it's true, it's not adding on "insert" fields even it has a value
 *          'type'         => 'file',      // Input Type and Value Type
 *          'default'      => '0'00',
 *          'required'     => TRUE,
 *          'rules'        => array (
 *              date          => TRUE,      // YYYY-MM-DD
 *              date_mdy      => TRUE,      // MM-DD-YY
 *              datetime      => TRUE,      // YYYY-MM-DD HH:MI:SS
 *              url           => TRUE,
 *              slug          => TRUE,
 *              file          => TRUE,
 *              extensions    => array('jpg','gif'),
 *              file_type    => array('text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream', 'application/vnd.ms-excel'),
 *              mime_content_type    => array('text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream', 'application/vnd.ms-excel'),
 *              enum          => array(M,F),
 *              email         => TRUE,
 *              numeric       => TRUE,
 *              array         => TRUE,
 *              decimal       => TRUE,
 *              length        => 5,
 *              min_length    => 5,
 *              max_length    => 5,
 *              rgb_color     => TRUE,
 *
 *              uppercase     => TRUE,      // Transform the value
 *              lowercase     => TRUE,      // Transform the value
 *          ),
 *          'value'       => '2000',
 *          'value_from'  => 'userid',    // if the value is null, set the value from the sepecified id
 *          'formatted'   => '2,000.00',
 *          'error'       => 'ERROR_CODE',
 *          'where'       => TRUE         // if it's true, and there is value in config, then add where condition in query using operator
 *          )
)
 */

class HCModel{
    public $table;      // Representative Table
    public $schema;     // Table Fields Information
    public $fields;     // Input Fields by Actions
    public $ofields;    // Output Fields by Actions
    public $dfields;    // Fields by Document
    public $config;     // Merged Fields Configuration for an Action

    public $controller;
    public $action;
    public $data;
    public $db;
    public $memcache;

    public $start;
    public $limit;
    public $orderby;
    public $direction;
    public $info;

    public $use_encryption = false;

    public function __construct($action) {
        Debug::ttt('HCModel::__construct("'.$action.'")');

        $this->action = $action;

        $this->mergeConfig();
        $DOC_ACTIONS = explode(',', DOC_ACTIONS);
        if (isset($DOC_ACTIONS)
            && count($DOC_ACTIONS) > 0
            && in_array($this->action, $DOC_ACTIONS)) {
            // Pass
        }else {
            unset($this->fields);
        }
    }
    public function setValues(& $args) {
        Debug::ttt('HCModel::setValues()');
        if (!isset($this->config) || !is_array($this->config)) return false;
        if (isset($args['c'])) {
            $this->controller = $args['c'];
        }

        // Set Values
        foreach ($this->config as $key => $options){
            if (isset($args[$key])) {
                $this->config[$key]['value'] = $args[$key];
            }
        }

        // Set value from id
        foreach ($this->config as $key => $options){
            if(!isset($options['value']) && empty($options['value'])
                && isset($options['value_from']) && !empty($options['value_from'])
                && isset($this->config[$options['value_from']]['value']) && !empty($this->config[$options['value_from']]['value'])) {
                $this->config[$key]['value'] = $this->config[$options['value_from']]['value'];
            }
        }

        Debug::ppp($this->config);
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
    public function insert($options = array(), $mode = '', $function_off = false) {
        Debug::ttt('HCModel::insert()');
        /*
        $options = array (
            'table' => 'table1',
            'id' => 'field1',
            'fields' => array(
                'field1' => 'value1',
                'field2' => 'value1',
                'field3' => 'value1'
            ),
            'encrypt' => array(
                'encrypted_field1',
                'encrypted_field2'
            ),
            'encrypt_key' => array(
                'public_key' => '',
                'private_key' => ''
            )
        );
        */
        $this->use_encryption = false;
        if (isset($options['encrypt_key']['public_key']) && !empty($options['encrypt_key']['public_key'])
            && isset($options['encrypt_key']['private_key']) && !empty($options['encrypt_key']['private_key'])) {
            $this->use_encryption = true;
        }
        $encryption_key_bind = false;

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

        Debug::ppp('use_encryption: '.$this->use_encryption);
        if ($this->use_encryption === true && (!isset($options['encrypt'])
                                           || (isset($options['encrypt']) && count($options['encrypt'])) <= 0)) {
            foreach ($this->config as $key => $item) {
                Debug::ppp($key);
                if (isset($item['encrypt']) && $item['encrypt'] === true
                && isset($item['field']) && isset($item['value'])) {
                    $options['encrypt'][] = $item['field'];
                }
            }
        }
        Debug::ppp($options);

        $fields = '';
        $values = '';
        $values_memcache = '';
        foreach ($options['fields'] as $field => $value) {
            $fields .= $field.',';
            if ($function_off === false && (strpos($value,'(') !== false || strpos($value,')') !== false)) {
                $values .= $value.',';
                $values_memcache .= $value.',';
            }else {
                if ($this->use_encryption === true && isset($options['encrypt']) && in_array($field, $options['encrypt'])) {
                    $alias = str_replace('.','_',$field);
                    $values .= 'AES_ENCRYPT(:'.$alias.', SHA2(CONCAT(:PublicKey,:PrivateKey),512)),';
                    $values_memcache .= 'AES_ENCRYPT("'.$value.'", SHA2(CONCAT(:PublicKey,:PrivateKey),512)),';
                    $encryption_key_bind = true;
                }else {
                    $values .= ':'.str_replace('.','_',$field).',';
                    $values_memcache .= '"'.$value.'",';
                }
            }
        }
        $fields = substr($fields, 0, -1);
        $values = substr($values, 0, -1);
        $values_memcache = substr($values_memcache, 0, -1);

        $sql_cmd = '';
        switch ($mode) {
            case 'replace':
                $sql_cmd = 'replace';
                break;
            default:
                $sql_cmd = 'insert';
                break;
        }
        $query = $sql_cmd.' into '.$options['table'].' ('.$fields.') values ('.$values.')';
        $query_memcache = $sql_cmd.' into '.$options['table'].' ('.$fields.') values ('.$values_memcache.')';
        Debug::ppp($query);
        Debug::ppp($query_memcache);

        try {
            $stmt = $this->db->prepare($query);
            foreach ($options['fields'] as $field => $value) {
                if ($function_off === false && (strpos($value,'(') !== false || strpos($value,')') !== false)) {
                    Debug::ppp(':'.$field.', '.$value);
                }else {
                    $stmt->bindParam(':'.str_replace('.','_',$field), $options['fields'][$field]);
                    Debug::ppp(':'.str_replace('.','_',$field).', '.$options['fields'][$field]);
                }

                // I don't know why it doesn't work below:
                // $stmt->bindParam(':'.$field, $value);
            }
            if ($this->use_encryption === true && $encryption_key_bind === true) {
                Debug::ppp(':PublicKey, '.$options['encrypt_key']['public_key']);
                $stmt->bindParam(':PublicKey', $options['encrypt_key']['public_key']);
                Debug::ppp(':PrivateKey, '.$options['encrypt_key']['private_key']);
                $stmt->bindParam(':PrivateKey', $options['encrypt_key']['private_key']);
            }
            $stmt->execute();

            // Get Inserted ID
            if ($mode == '' && isset($options['id']) && empty($options['fields'][$options['id']])) {
                $config_id = strtolower($options['id']);
                $this->config[$config_id]['value'] = $this->db->lastInsertId();
                switch (OUTPUT_VERSION) {
                    case '1.0.0':
                        $this->data[$config_id] = $this->config[$config_id]['value'];
                        break;
                    case '2.0.0':
                        $this->data['info'][$config_id] = $this->config[$config_id]['value'];
                        break;
                }
                Debug::ppp($this->data);
            }

            $row_count = $stmt->rowCount();
            if($row_count <= 0) {
                return 'ERROR_DB_NO_AFFECTED';
            }else {
                $this->data['info']['row_count'] = $row_count;
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
         $options = array (
              'table' => 'table1',
              'fields' => array(
                  'field1' => 'value1',
                  'field1' => 'value1',
                  'field1' => 'value1'
              ),
              'where' => array (
                  array ('where','field1','=','value1'),
                  array ('and','field1','=','value1'),
                  array ('or','field1','=','value1'),
                  array ('or','field1','in',array())
              ),
             'encrypt' => array(
                 'encrypted_field1',
                 'encrypted_field2'
              ),
             'encrypt_key' => array(
                 'public_key' => '',
                 'private_field' => ''
              )
         );
        */
        $this->use_encryption = false;
        if (isset($options['encrypt_key']['public_key']) && !empty($options['encrypt_key']['public_key'])
            && isset($options['encrypt_key']['private_field']) && !empty($options['encrypt_key']['private_field'])) {
            $this->use_encryption = true;
        }
        $encryption_key_bind = false;

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
                    if (!isset($item['operator'])) {
                        if (strtoupper($item['value']) == 'NULL') {
                            $item['operator'] = 'is';
                        }else {
                            $item['operator'] = '=';
                        }
                    }
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

        if ($this->use_encryption === true && (!isset($options['encrypt'])
                || (isset($options['encrypt']) && count($options['encrypt'])) <= 0)) {
            foreach ($this->config as $key => $item) {
                if (isset($item['encrypt']) && $item['encrypt'] === true
                    && isset($item['field']) && isset($item['value'])) {
                    $options['encrypt'][] = $item['field'];
                }
            }
        }

        $set = '';
        foreach ($options['fields'] as $field => $value) {
            if (isset($options['encrypt_key']['private_field']) && $field == $options['encrypt_key']['private_field']) {
                // Never update Private Key
                continue;
            }
            if (strpos($value,'(') !== false || strpos($value,')') !== false ) {
                $set .= $field.' = '.$value.',';
            }else {
                if ($this->use_encryption === true && isset($options['encrypt']) && in_array($field, $options['encrypt'])) {
                    $alias = str_replace('.','_',$field);
                    $set .= $field.' = AES_ENCRYPT(:'.$alias.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512)),';
                    $encryption_key_bind = true;
                }else {
                    $alias = str_replace('.','_',$field);
                    $set .= $field.' = :'.$alias.',';
                }
            }

        }
        $set = substr($set, 0, -1);

        $where = '';
        if (isset($options['where'])) {
            for ($i=0; $i<count($options['where']); $i++) {
                $conjunction = $options['where'][$i][0];
                $field = $options['where'][$i][1];
                $operator = $options['where'][$i][2];
                $value = $options['where'][$i][3];

                // decrypt the search field
                if ($this->use_encryption === true && isset($options['encrypt']) && in_array($field, $options['encrypt'])) {
                    $field = 'AES_DECRYPT('.$field.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512))';
                    $encryption_key_bind = true;
                }

                $where .= ' '.$conjunction;
                $where .= ' '.$field.'';
                $where .= ' '.$operator;

                if (is_array($value)) {
                    // Case of "Field in (value1, value2, ..)"
                    $where .= ' (';
                    for ($ii=0; $ii<count($value); $ii++) {
                        $where .= ' :w'.$i.'a'.$ii.',';
                    }
                    $where = substr($where, 0, -1);
                    $where .= ') ';
                }else {
                    // Case of including mysql functions
                    if (strpos($value,'(') !== false || strpos($value,')') !== false ) {
                        $where .= $value;
                    }else {
                        $where .= ' :w'.$i;
                    }
                }
            }
        }
        $query = 'update '.$options['table'].' set '.$set.' '.$where;

        Debug::ppp($query);
        Debug::ppp($options);

        try {
            $stmt = $this->db->prepare($query);
            foreach ($options['fields'] as $field => $value) {
                if (strtoupper($options['fields'][$field]) == 'NULL') {
                    $options['fields'][$field] = NULL;
                }
                if (strpos($value,'(') !== false || strpos($value,')') !== false ) {
                    Debug::ppp(':'.$field.', '.$value);
                }else {
                    $stmt->bindParam(':'.str_replace('.','_',$field), $options['fields'][$field]);
                    Debug::ppp(':'.str_replace('.','_',$field).', '.$options['fields'][$field]);
                }
                // I don't know why it doesn't work below:
                // $stmt->bindParam(':'.$field, $value);
            }
            if (isset($options['where'])) {
                for ($i=0; $i<count($options['where']); $i++) {
                    if (is_array($options['where'][$i][3])) {
                        for ($ii=0; $ii<count($options['where'][$i][3]); $ii++) {
                            if (strtoupper($options['where'][$i][3][$ii]) == 'NULL') $options['where'][$i][3][$ii] = NULL;
                            $stmt->bindParam(':w'.$i.'a'.$ii, $options['where'][$i][3][$ii]);
                            Debug::ppp(':w'.$i.'a'.$ii.', '.$options['where'][$i][3][$ii]);
                        }
                    }else {
                        if (strtoupper($options['where'][$i][3]) == 'NULL') $options['where'][$i][3] = NULL;
                        $stmt->bindParam(':w'.$i, $options['where'][$i][3]);
                        Debug::ppp(':w'.$i.', '.$options['where'][$i][3]);
                    }
                }
            }
            if ($this->use_encryption === true && $encryption_key_bind === true) {
                Debug::ppp(':PublicKey, '.$options['encrypt_key']['public_key']);
                $stmt->bindParam(':PublicKey', $options['encrypt_key']['public_key']);
            }
            $stmt->execute();

            $row_count = $stmt->rowCount();
            if($row_count <= 0) {
                return 'ERROR_DB_NO_AFFECTED';
            }else {
                $this->data['info']['row_count'] = $row_count;
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
        $options = array (
            'table' => 'table1',
            'where' => array (
                array ('where','field1','=','value1'),
                array ('and','field1','=','value1'),
                array ('or','field1','=','value1')
            ),
            'encrypt' => array(
                'encrypted_field1',
                'encrypted_field2'
            ),
            'encrypt_key' => array(
                'public_key' => '',
                'private_field' => ''
            )
        );
        */
        $this->use_encryption = false;
        if (isset($options['encrypt_key']['public_key']) && !empty($options['encrypt_key']['public_key'])
            && isset($options['encrypt_key']['private_field']) && !empty($options['encrypt_key']['private_field'])) {
            $this->use_encryption = true;
        }

        $encryption_key_bind = false;

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

        if ($this->use_encryption === true && (!isset($options['encrypt'])
                || (isset($options['encrypt']) && count($options['encrypt'])) <= 0)) {
            foreach ($this->config as $key => $item) {
                if (isset($item['encrypt']) && $item['encrypt'] === true
                    && isset($item['field']) && isset($item['value'])) {
                    $options['encrypt'][] = $item['field'];
                }
            }
        }

        $where = '';
        if (isset($options['where'])) {
            for ($i=0; $i<count($options['where']); $i++) {
                $conjunction = $options['where'][$i][0];
                $field = $options['where'][$i][1];
                $operator = $options['where'][$i][2];
                $value = $options['where'][$i][3];

                // decrypt the search field
                if ($this->use_encryption === true && isset($options['encrypt']) && in_array($field, $options['encrypt'])) {
                    $field = 'AES_DECRYPT('.$field.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512))';
                    $encryption_key_bind = true;
                }

                $where .= ' '.$conjunction;
                $where .= ' '.$field.'';
                $where .= ' '.$operator;

                if (is_array($value)) {
                    // Case of "Field in (value1, value2, ..)"
                    $where .= ' (';
                    for ($ii=0; $ii<count($value); $ii++) {
                        $where .= ' :w'.$i.'a'.$ii.',';
                    }
                    $where = substr($where, 0, -1);
                    $where .= ') ';
                }else {
                    // Case of including mysql functions
                    if (strpos($value,'(') !== false || strpos($value,')') !== false ) {
                        $where .= $value;
                    }else {
                        $where .= ' :w'.$i;
                    }
                }
            }
        }

        $query = 'delete from '.$options['table'].' '.$where;
        Debug::ppp($query);
        Debug::ppp($options);

        try {
            $stmt = $this->db->prepare($query);
            for ($i=0; $i<count($options['where']); $i++) {
                if (strtoupper($options['where'][$i][3]) == 'NULL') $options['where'][$i][3] = NULL;
                $stmt->bindParam(':w'.$i, $options['where'][$i][3]);
                Debug::ppp(':w'.$i.', '.$options['where'][$i][3]);
            }
            if ($this->use_encryption === true && $encryption_key_bind === true) {
                Debug::ppp(':PublicKey, '.$options['encrypt_key']['public_key']);
                $stmt->bindParam(':PublicKey', $options['encrypt_key']['public_key']);
            }
            $stmt->execute();

            $row_count = $stmt->rowCount();
            if($row_count <= 0) {
                return 'ERROR_DB_NO_AFFECTED';
            }else {
                $this->data['info']['row_count'] = $row_count;
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
        $options = array (
             'select' => array (
                 'a.field1',
                 'field1',
                 'field1',
                 'format(field1)'
             ),
             'from' => array (
                 'Lists l',
                 'left join',
                 'Items i',
                 'on l.Id = i.listId'
             ),
             'where' => array (
                 array ('where','field1','=','value1'),
                 array ('and','field1','=','value1'),
                 array ('or','field1','=','value1'),
                 array ('or','field1','in',array())
             ),
             'groupby' => array (
                 'field1',
                 'field1'
             ),
             'orderby' => array (
                 array ('field1','asc'),
                 array ('field1','desc')
             ),
             'limit' => array (0,10),
             'encrypt' => array(
                 'encrypted_field1',
                 'encrypted_field2'
              ),
             'encrypt_key' => array(
                'public_key' => '',
                'private_field' => ''
             )
        );
        */
        $this->use_encryption = false;
        if (isset($options['encrypt_key']['public_key']) && !empty($options['encrypt_key']['public_key'])
            && isset($options['encrypt_key']['private_field']) && !empty($options['encrypt_key']['private_field'])) {
            $this->use_encryption = true;
        }

        // when encryption is not setup, check each field in config if the encryt property is true
        if ($this->use_encryption === true && (!isset($options['encrypt'])
                || (isset($options['encrypt']) && count($options['encrypt'])) <= 0)) {
            foreach ($this->schema as $key => $item) {
                if (isset($item['encrypt']) && $item['encrypt'] === true && isset($item['field'])) {
                    $options['encrypt'][] = $item['field'];
                }
            }
        }
        Debug::ppp($options);

        if (!isset($options['where'])
            || isset($options['where'])
            && count($options['where']) <= 0
            && isset($this->config)
            && is_array($this->config)
            && count($this->config) > 0) {
            $conn = 'where';
            $config = $this->config;
            if (isset($config) && is_array($config)) {
                foreach ($config as $item) {
                    if (isset($item['where']) && $item['where'] === true
                        && isset($item['field']) && !empty($item['field'])
                        && isset($item['value']) && !empty($item['value'])) {
                        if (!isset($item['operator'])) {
                            if (strtoupper($item['value']) == 'NULL') {
                                $item['operator'] = 'is';
                            }else {
                                $item['operator'] = '=';
                            }
                        }
                        $tmp = array($conn, $item['field'], $item['operator'], $item['value']);
                        $tmp = $this->switchOperator($tmp);
                        $options['where'][] = $tmp;
                        $conn = 'and';
                    }
                }
            }
        }

        if (!isset($options['select'])
            || (isset($options['select']) && count($options['select'])) <= 0) {
            foreach ($this->schema as $key => $item) {
                if (isset($item['field']) && !empty($item['field'])) {
                    $options['select'][] = $item['field'];
                }
            }
        }
        Debug::ppp($options);

        $encryption_key_bind = false;
        $select = '';
        if (isset($options['select'])) {
            for ($i=0; $i<count($options['select']); $i++) {
                $field = $options['select'][$i];

                    if (preg_match('/^([^\.]*\.?[^ ]+)[ |\t]+as[ |\t]+(.+)$/i', $field, $matches)) {
                        $field_name = $matches[1];
                        $field_alias = $matches[2];
                        // 1. Table.Field as alias
                        if ($this->use_encryption === true && isset($options['encrypt']) && in_array($field_name, $options['encrypt'])) {
                            $encryption_key_bind = true;
                            $select .= 'AES_DECRYPT('.$field_name.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512)) as '.$field_alias.',';

                        }else {
                            $select .= ''.$field.',';
                        }
                    }else {
                        if ($this->use_encryption === true && isset($options['encrypt']) && in_array($field, $options['encrypt'])) {
                            $encryption_key_bind = true;
                            $select .= 'AES_DECRYPT('.$field.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512)) as '.$field.',';
                        }else {
                            $select .= ''.$field.',';
                        }

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
                $conjunction = $options['where'][$i][0];
                $field = $options['where'][$i][1];
                $operator = $options['where'][$i][2];
                $value = $options['where'][$i][3];

                // decrypt the search field
                if ($this->use_encryption === true && isset($options['encrypt'])) {
                    if (in_array($field, $options['encrypt'])) {
                        $field = 'AES_DECRYPT('.$field.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512))';
                        $encryption_key_bind = true;
                    }else if (strpos($field,'(') !== false || strpos($field,')') !== false ) {
                        for ($j=0;$j<count($options['encrypt']);$j++) {
                            $encrypt_field = $options['encrypt'][$j];
                            if (strpos($field, '('.$encrypt_field.')') !== false) {
                                $from = '('.$encrypt_field.')';
                                $to = '('.'AES_DECRYPT('.$encrypt_field.', SHA2(CONCAT(:PublicKey, '.$options['encrypt_key']['private_field'].'),512))'.')';
                                $field = str_replace($from, $to, $field);
                                $encryption_key_bind = true;
                                break;
                            }
                        }
                    }
                }

                $where .= ' '.$conjunction;
                $where .= ' '.$field.'';
                $where .= ' '.$operator;
                if (is_array($value)) {
                    // Case of "Field in (value1, value2, ..)"
                    $where .= ' (';
                    for ($ii=0; $ii<count($value); $ii++) {
                        $where .= ' :w'.$i.'a'.$ii.',';
                    }
                    $where = substr($where, 0, -1);
                    $where .= ') ';
                }else {
                    // Case of including mysql functions
                    if (strpos($value,'(') !== false || strpos($value,')') !== false ) {
                        $where .= $value;
                    }else {
                        $where .= ' :w'.$i;
                    }
                }

                $query_memcache .= ' '.$conjunction;
                $query_memcache .= ' '.$field.'';
                $query_memcache .= ' '.$operator;

                if (is_array($options['where'][$i][3])) {
                    $query_memcache .= ' (';
                    for ($ii=0; $ii<count($options['where'][$i][3]); $ii++) {
                        $query_memcache .= ' "'.$options['where'][$i][3][$ii].'",';
                    }
                    $query_memcache = substr($query_memcache, 0, -1);
                    $query_memcache .= ') ';
                } else {
                    $query_memcache .= ' '.$options['where'][$i][3];
                }
            }
        }

        $groupby = '';
        if (isset($options['groupby']) && !empty($options['groupby'])) {
            for ($i=0; $i<count($options['groupby']); $i++) {
                if ($groupby == '') {
                    $groupby = ' group by ';
                }else {
                    $groupby .= ',';
                }
                $groupby .= $options['groupby'][$i];
            }
        }

        $orderby = '';
        if (isset($options['orderby']) && !empty($options['orderby'])) {
            for ($i=0; $i<count($options['orderby']); $i++) {
                if ($orderby == '') {
                    $orderby = ' order by ';
                }else {
                    $orderby .= ',';
                }
                $orderby .= $options['orderby'][$i][0].' '.$options['orderby'][$i][1];
            }
        }else if (isset($this->orderby)) {
            if (!isset($this->direction)) $this->direction = 'ASC';
            $orderby = ' order by '.$this->orderby.' '.$this->direction;
        }

        $limit = '';
        if (isset($options['limit']) && !empty($options['limit'])) {
            $limit .= $options['limit'][$i][0];
            if (isset($options['limit'][$i][1])) {
                $limit .= ', '.$options['limit'][$i][1];
            }
        }else if (isset($this->start) && isset($this->limit)) {
            $limit = ' limit '.$this->start.', '.$this->limit;
        }

        $SQL_CALC_FOUND_ROWS = '';
        if (isset($this->info) && strpos($this->info, 't') !== false) {
            $SQL_CALC_FOUND_ROWS = ' SQL_CALC_FOUND_ROWS ';
        }
        $query  = ' select '.$SQL_CALC_FOUND_ROWS.$select;
        $query .= ' from '.$from;
        $query .= ' '.$where;
        $query .= ' '.$groupby;
        $query .= ' '.$orderby;
        $query .= ' '.$limit;

        $query_memcache .= ' '.$orderby;
        $query_memcache .= ' '.$limit;
        Debug::ppp($query);
        Debug::ppp($query_memcache);

        if (USE_MEMCACHE) {
            try {
                $cached_data = $this->memcache->get($query_memcache);
            } catch (Exception $e) {
                return $e;
            }
        }

        if (isset($cached_data) && is_array($cached_data)) {
            Debug::ppp('HCModel::select::Memcached_data', 'Fuchsia');
            Debug::ppp('MemCache_key: '.$query_memcache, 'Fuchsia');
            switch (OUTPUT_VERSION) {
                case '1.0.0':
                    $this->data = $cached_data;
                    break;
                case '2.0.0':
                    $this->data['items'] = $cached_data;
                    break;
            }
            Debug::ppp($this->data, 'Fuchsia');
        }else {
            try {
                $stmt = $this->db->prepare($query);
                if (isset($options['where'])) {
                    for ($i=0; $i<count($options['where']); $i++) {
                        if (is_array($options['where'][$i][3])) {
                            for ($ii=0; $ii<count($options['where'][$i][3]); $ii++) {
                                if (strtoupper($options['where'][$i][3][$ii]) == 'NULL') $options['where'][$i][3][$ii] = NULL;
                                $stmt->bindParam(':w'.$i.'a'.$ii, $options['where'][$i][3][$ii]);
                                Debug::ppp(':w'.$i.'a'.$ii.', '.$options['where'][$i][3][$ii]);
                            }
                        }else {
                            if (strpos($options['where'][$i][3],'(') !== false || strpos($options['where'][$i][3],')') !== false ) {
                                // bind pass
                            }else {
                                if (strtoupper($options['where'][$i][3]) == 'NULL') $options['where'][$i][3] = NULL;
                                $stmt->bindParam(':w'.$i, $options['where'][$i][3]);
                            }
                            Debug::ppp(':w'.$i.', '.$options['where'][$i][3]);
                        }
                    }
                }
                if ($this->use_encryption === true && $encryption_key_bind === true) {
                    Debug::ppp(':PublicKey, '.$options['encrypt_key']['public_key']);
                    $stmt->bindParam(':PublicKey', $options['encrypt_key']['public_key']);
                }
                $stmt->execute();

                switch (OUTPUT_VERSION) {
                    case '1.0.0':
                        $this->data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                    case '2.0.0':
                        $this->data['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                }

                if (USE_MEMCACHE) {
                    try {
                        switch (OUTPUT_VERSION) {
                            case '1.0.0':
                                $this->memcache->set($query_memcache, $this->data, false, 60);
                                break;
                            case '2.0.0':
                                $this->memcache->set($query_memcache, $this->data['items'], false, 60);
                                break;
                        }

                        Debug::ppp($query_memcache, 'Fuchsia');
                    } catch (Exception $e) {}
                }

            } catch (PDOException $exc) {
                Debug::Error($exc);
                return 'ERROR_DB_SELECT';
            }
        }

        switch (OUTPUT_VERSION) {
            case '1.0.0':
                if(!isset($this->data) || !is_array($this->data) || count($this->data) <= 0) {
                    return 'ERROR_DB_NO_DATA';
                }
                break;
            case '2.0.0':
                if(!isset($this->data['items']) || !is_array($this->data['items']) || count($this->data['items']) <= 0) {
                    return 'ERROR_DB_NO_DATA';
                }
                break;
        }

        if (isset($this->info) && strpos($this->info, 't') !== false) {
            $result = $this->get_total();
            if ($result !== false) {
                $this->data['info']['total'] = $result;
            }
        }
        return true;
    }

    public function query($query = '', $bind = array()) {
        Debug::ttt('HCModel::query($query, $bind)');
        if (empty($query)) return false;
        $command = strtoupper(substr(trim($query), 0, 6));
        if (isset($this->info) && strpos($this->info, 't') !== false && $command == 'SELECT' && strpos($query, 'FOUND_ROWS') === false) {
            $query = str_replace(substr(trim($query), 0, 6), substr(trim($query), 0, 6).' SQL_CALC_FOUND_ROWS', $query);
        }

        Debug::ppp($query);
        Debug::ppp($bind);
        try {
            $stmt = $this->db->prepare($query);
            if (isset($bind) && count($bind) > 0) {
                foreach ($bind as $key => $val) {
                    if (strtoupper($val) == 'NULL') $val = NULL;
                    $stmt->bindParam($key, $val);
                    Debug::ppp($key.', '.$val);
                }
            }

            $stmt->execute();
            switch ($command) {
                case 'SELECT':
                    switch (OUTPUT_VERSION) {
                        case '1.0.0':
                            $this->data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;
                        default:
                            $this->data['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;
                    }
                    break;
                default:
                    $row_count = $stmt->rowCount();
                    if($row_count <= 0) {
                        return 'ERROR_DB_NO_AFFECTED';
                    }else {
                        $this->data['info']['row_count'] = $row_count;
                    }
                    break;
            }
            Debug::ppp($this->data);
        } catch (PDOException $exc) {
            Debug::Error($exc);
            return 'ERROR_DB_QUERY';
        }

        if ($command == 'SELECT') {
            switch (OUTPUT_VERSION) {
                case '1.0.0':
                    if(!isset($this->data) || !is_array($this->data) || count($this->data) <= 0) {
                        return 'ERROR_DB_NO_DATA';
                    }
                    break;
                case '2.0.0':
                    if(!isset($this->data['items']) || !is_array($this->data['items']) || count($this->data['items']) <= 0) {
                        return 'ERROR_DB_NO_DATA';
                    }
                    break;
            }
            if (isset($this->info) && strpos($this->info, 't') !== false && strpos($query, 'FOUND_ROWS') === false) {
                $this->data['info']['total'] = $this->get_total();
            }
        }

        return true;
    }
    public function get_search_fields($prefix = '') {
        Debug::ttt('HCModel::get_search_fields()');

        $arr_search_fields = array();
        foreach ($this->schema as $key => $val) {

            if (isset($val['search']) && $val['search'] === true) {
                if (isset($prefix) && !empty($prefix)) {
                    $arr_search_fields[] = $prefix.$key;
                }else {
                    $arr_search_fields[] = $key;
                }
            }
        }
        $arr_search_fields[] = $prefix.'l';
        $arr_search_fields[] = $prefix.'s';
        $arr_search_fields[] = $prefix.'p';
        Debug::ppp($arr_search_fields);
        return $arr_search_fields;
    }
    public function get_total() {
        Debug::ttt('HCModel::get_total()');
        // After Specifying SQL_CALC_FOUND_ROWS in your select query
        // i.e.) select SQL_CALC_FOUND_ROWS * from Table
        $tmp = $this->data;
        if ($this->query('SELECT FOUND_ROWS() as total')) {
            $total = $this->data['items'][0]['total'];
            $this->data = $tmp;
            return $total;
        }
        return false;
    }
}
?>