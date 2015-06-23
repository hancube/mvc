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

require_once (MVC_LIB.'/lib/version.php');
require_once (MVC_LIB.'/lib/debug.php');
require_once (MVC_LIB.'/lib/db.php');
require_once (MVC_LIB.'/lib/string.php');
require_once (MVC_LIB.'/lib/web.php');
require_once (MVC_LIB.'/lib/validate.php');

class HCController{
    public $args = array(
        'c'=>'',    // controller
        'a'=>'',    // action
        'f'=>'',    // format
        'v'=>'',    // view file name
        'cb'=>'',   // callback
        's'=>'',    // start
        'p'=>'',    // page
        'l'=>'',    // limit
        'o'=>'',    // order by
        'd'=>'',    // direction
        'i'=>''     // info(t:total)
    );

    public $controller;
    public $action;

    public $db;
    public $memcache;
    public $model;

    public $output;
    public $messages;

    public function __construct() {
        Debug::ttt('HCController::construct()');
        $this->setArgs();
        return true;
    }

    public function run() {
        Debug::ttt('HCController::run()');

        if (!$this->setDB()) {
            $this->Error('DB_ERRORS', 'ERROR_DB_CONNECTION');
            return false;
        }
        if (USE_MEMCACHE === true) $this->setMemcache();
        $this->setMessages();
        $this->setModel();
        if (!$this->Validate()) {
            $this->Error('INPUT_ERRORS');
            return false;
        }

        return true;
    }
    public function runAction($action) {
        Debug::ttt('HCController::runAction($action)');
        $result = true;

        if (method_exists($this, $action)) {
            // run set model
            $this->run();

            // if the action fields are not defined, do not execute action.
            if (isset($this->model->fields[$action])) {
                if (!$this->isError()) {
                    eval('$this->'.$action.'();');
                }else {
                    $result = false;
                }
            }else {
                $result = false;
            }
        }else {
            $result = false;
        }

        return $result;
    }
    public function setArgs() {
        Debug::ttt('HCController::setArgs()');

        $this->args = Web::getArgs();

        if (isset($this->args['v'])) {
            $this->args['v'] = str_replace('..', '', $this->args['v']);
        }

        foreach ($this->args as $key => $val) {
            $this->args[$key] = String::HtmlToTxt($val);
        }
        if (isset($this->args['p']) && !isset($this->args['s'])) {
            $this->args['s'] = $this->args['p'] * $this->args['l'] - $this->args['l'];
        }else if (!isset($this->args['p']) && isset($this->args['s'])) {
            if ($this->args['s'] == 0) {
                $this->args['p'] = 1;
            } else {
                $this->args['p'] = floor($this->args['s']/$this->args['l']) + 1;
            }
        }else {
            $this->args['s'] = 0;
            $this->args['p'] = 1;
        }
        $this->controller = $this->args['c'];
        $this->action = $this->args['a'];

        return $this->setExtraArgs();
    }
    public function setExtraArgs() {
        Debug::ttt('HCController::setExtraArgs()');

    }
    public function setDB() {
        Debug::ttt('HCController::setDB()');
        try {
            $this->db = new DB();
            if (!$this->db) {
                Debug::Error('ERROR_DB_CONNECTION');
                return false;
            }
        } catch (PDOException $e) {
            Debug::Error($e);
            return false;
        }
        $this->clearDBInfo();
        return true;
    }
    public function clearDBInfo() {
        $this->db->host = '';
        $this->db->port = '';
        $this->db->name = '';
        $this->db->user = '';
        $this->db->pass = '';

        return true;
    }
    public function setMemcache() {
        Debug::ttt('HCController::setMemcache()');
        $this->memcache = new Memcache;
        $this->memcache->connect(MEMCACHE_HOST, MEMCACHE_PORT) or die ("Could not connect to Memcache Server");
        return true;
    }
    public function setModel() {
        Debug::ttt('HCController::setModel()');
        if (empty($this->action)) return false;

        $model_file = ROOT.'/models/'.$this->controller.'_model.php';
        if (!file_exists($model_file)) {
            return false;
        }

        require_once ($model_file);

        try {
            eval('$this->model = new '.ucwords($this->controller).'Model(\''.$this->action.'\');');
            $this->model->setValues($this->args);
        } catch (Exception $exc) {
            Debug::Error($exc);
            return false;
        }

        $this->model->db = & $this->db;
        $this->model->memcache = & $this->memcache;
        $this->setSLOD($this->args, $this->model);

        return true;
    }
    public function setSLOD(& $args, & $model) {
        Debug::ttt('HCController::setSLOD()');
        if (isset($args['s']) && !empty($args['s'])) $model->start = $args['s'];
        if (isset($args['l']) && !empty($args['l'])) $model->limit = $args['l'];
        if (isset($args['o']) && !empty($args['o'])) {
            if (isset($model->schema[$args['o']]['field']) && !empty($model->schema[$args['o']]['field'])) {
                $model->orderby = $model->schema[$args['o']]['field'];
            }else {
                $model->orderby = $args['o'];
            }
        }
        if (isset($args['d']) && !empty($args['d'])) $model->direction = $args['d'];
        if (isset($args['i']) && !empty($args['i'])) $model->info = $args['i'];

        if (!isset($model->start) || $model->start < 0) $model->start = 0;
        if (!isset($model->limit) || $model->limit > SELECT_LIMIT) $model->limit = SELECT_LIMIT;
        return true;
    }
    public function addModel($controller, $action) {
        Debug::ttt('HCController::addModel('.$controller.', '.$action.')');
        if (empty($action)) return false;

        $model_file = ROOT.'/models/'.$controller.'_model.php';
        if (!file_exists($model_file)) {
            return false;
        }
        require_once ($model_file);

        try {
            eval('$model = new '.ucwords($controller).'Model(\''.$action.'\');');
            //$model->setValues($this->args); - 2014-09-12 removed
        } catch (Exception $exc) {
            Debug::Error($exc);
            return false;
        }

        $model->db = & $this->db;
        $model->memcache = & $this->memcache;

        return $model;
    }

    public function Validate() {
        Debug::ttt('HCController::Validate()');
        if ($this->isError()) return false;
        if (empty($this->action)) return true;
        if (!isset($this->model->config)) return true;

        $ok = true;
        $config = & $this->model->config;
        foreach($config as $field => $options) {
            Debug::ppp($field);
            Debug::ppp($options);

            // Required
            if (isset($options['required']) && $options['required'] !== false) {
                if (isset($options['rules']['file']) && $options['rules']['file'] === true) {
                    if (!is_array($options['value'])
                        ||!isset($options['value']['name'])
                        ||empty($options['value']['name'])) {
                        $config[$field]['error'] = 'ERROR_REQUIRED';
                        $ok = false;
                    }
                }else if(isset($options['value']) && is_array($options['value'])) {
                    $tmp_ok = false;
                    for($i=0; $i<count($options['value']); $i++) {
                        if (isset($options['value'][$i]) && !empty($options['value'][$i])) $tmp_ok = true;
                    }
                    if (!$tmp_ok) {
                        $config[$field]['error'] = 'ERROR_REQUIRED';
                        $ok = false;
                    }
                }else if (isset($options['value']) && $options['value'] === '0') {
                    // value = 0 is not empty
                }else {
                    if (!isset($options['value']) || empty($options['value'])) {
                        $config[$field]['error'] = 'ERROR_REQUIRED';
                        $ok = false;
                    }
                }
            }

            // Check Next Field
            if (isset($config[$field]['error']) && !empty($config[$field]['error'])) continue;

            // Rules
            if (isset($options['rules']) && count($options['rules'])) {
                foreach ($options['rules'] as $rule => $rule_val) {
                    if (!isset($rule_val) || !$rule_val) continue;
                    if (!isset($options['value'])) continue;
                    if (isset($config[$field]['error']) && !empty($config[$field]['error'])) continue;

                    $validate = Validate::check($rule, $rule_val, $options['value']);

                    if (!$validate['result']) {
                        $config[$field]['error'] = $validate['error'];
                        $ok = false;
                        continue;
                    }else if (isset($validate['value']) && !empty($validate['value'])){
                        $config[$field]['value'] = $validate['value'];
                    }
                }
            }
        }

        if ($this->extraValidate() === false) {
            $ok = false;
        }
        return $ok;
    }
    public function extraValidate() {return true;}

    public function setMessages() {
        Debug::ttt('HCController::setMessages()');
        $ini_file = ROOT.'/lang/'.LANG.'/message.ini';
        $this->messages = parse_ini_file($ini_file);
        return true;
    }
    public function Render($filename = '') {
        Debug::ttt('HCController::Render('.$filename.')');

        if (isset($this->args['v']) && !empty($this->args['v'])) {
            $filename = $this->args['v'];
        }
        if (OUTPUT_VERSION >= '2.0.0') {
            $this->setReturnArgs();
        }
        $this->beforeRender();
        $this->staticRender($this->output, $filename);
        $this->afterRender();
        return true;
    }
    public function beforeRender() {}
    public function afterRender() {}

    public function setReturnArgs() {
        Debug::ttt('HCController::setReturnArgs()');
        if (!isset($this->model->config)) return false;

        if (defined('RETURN_ARGS') && RETURN_ARGS === TRUE) {
            $this->output['info']['args'] = $this->args;
        }

        return true;
    }

    public static function staticRender($output, $filename='') {
        Debug::ttt('HCController::staticRender()');

        if (OUTPUT_VERSION == '1.0.0') {
            $data = $output;
        }

        if (DEBUG <= 0 && TEST_CASE !== true) {
            header("Cache-Control: no-store, no-cache, must-revalidate, public, post-check=0, pre-check=0");
            header("Pragma: no-cache");
            header("Connection: close");
            header ("expires: " . gmdate ("D, d M Y H:i:s", time()) . " GMT");
        }

        if (Web::getArg('f') == 'json') {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/json; charset=utf-8');
            }
            echo json_encode($output);
            return true;
        }else if (Web::getArg('f') == 'jsonp') {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: application/x-javascript; charset=utf-8');
            }

            $callback = Web::getArg('cb');
            if (!isset($callback) || empty($callback)) {
                $callback = Web::getArg('callback');
            }
            if (!isset($callback) || empty($callback)) {
                $callback = 'callback';
            }
            // Sanitizing Callback Function Name
            if (isset($callback) && !empty($callback)) {
                $callback = String::removeSpecialCharaters($callback);
            }

            echo $callback.'('.json_encode($output).')';
        }else if (Web::getArg('f') == 'xml') {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/xml; charset=utf-8');
            }
            echo String::ArrayToXML ($output);
        }else if (Web::getArg('f') == 'html' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/html; charset=utf-8');
            }
            if((@include ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === false) {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'xls' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                $tmp = explode('/', $filename);
                $download_filename = $tmp[count($tmp)-1];
                header('Content-Disposition: attachment; filename='.$download_filename);
                header('Content-type: application/vnd.ms-excel; charset=utf-8');
            }
            if((@include ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === false) {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'xlsx' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                $tmp = explode('/', $filename);
                $download_filename = $tmp[count($tmp)-1];
                header('Content-Disposition: attachment; filename='.$download_filename);
                header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
            }
            if((@include ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === false) {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'path' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/html; charset=utf-8');
            }
            if((@include ROOT.'/views/'.$filename) === false) {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/plain; charset=utf-8');
            }
            print_r($output);
        }
        return true;
    }
    public function Error($cate, $error = '') {
        Debug::ttt('HCController::Error("'.$cate.'", "'.$error.'")');
        if ($this->isError()) return false; // to make sure call only one time

        $this->output['result'] = 0;

        switch (OUTPUT_VERSION) {
            case '1.0.0':
                switch ($cate) {
                    case 'INPUT_ERRORS': // input error need to show multiple errors
                        if (isset($this->model->config)) {
                            foreach ($this->model->config as $key => $options) {
                                if (isset($options['error'])) {
                                    $this->output['errors'][$key] = $options['error'];
                                }
                            }
                        }
                        break;
                    default :
                        $this->output['errors'][strtolower($cate)] = $error;
                        break;
                }
                break;
            default:
                switch ($cate) {
                    case 'INPUT_ERRORS': // input error need to show multiple errors
                        $this->output['errors']['code'] = 'ERROR_INPUT';
                        $this->output['errors']['text'] = $this->StaticGetMessageg($cate, $this->output['errors']['code']);
                        if (isset($this->model->config)) {
                            foreach ($this->model->config as $key => $options) {
                                if (isset($options['error'])) {
                                    if (!isset($options['value'])) $options['value'] = '';
                                    $this->output['errors']['fields'][$key]['code'] = $options['error'];
                                    $this->output['errors']['fields'][$key]['text'] = $this->getMessage($cate, $options['error'], $options['value']);
                                }
                            }
                        }
                        break;
                    default :
                        if (!empty($error)) {
                            $this->output['errors']['code'] = strtoupper($error);
                        }else {
                            $this->output['errors']['code'] = strtoupper($cate);
                        }
                        $this->output['errors']['text'] = $this->StaticGetMessageg($cate, $error);
                        break;
                }
                break;
        }

        $this->Render();

        Debug::ttt('total');

        exit;

        return true;
    }
    public function getMessage($cate, $code, $value) {
        $message = $this->StaticGetMessageg($cate, $code);
        $message = str_replace('#value#', $value, $message);
        return $message;
    }

    public static function StaticGetMessageg($cate, $code) {
        $ini_file = ROOT.'/lang/'.LANG.'/message.ini';
        $messages = parse_ini_file($ini_file);
        if (isset($messages[$code])) return $messages[$code];
        else return $code;
    }

    public static function StaticError($cate, $error = '') {
        Debug::ttt('HCController::InitError("'.$cate.'", "'.$error.'")');

        switch (OUTPUT_VERSION) {
            case '1.0.0':
                $output = array('result' => 0,
                    'errors' => array(strtolower($cate) => $error));
                break;
            default:
                $error_msg = HCController::StaticGetMessageg($cate, $error);
                $output = array('result' => 0,
                    'errors' => array(
                        'code' => $error,
                        'text' => $error_msg
                    ));
        }

        // Render
        HCController::staticRender($output);

        Debug::ttt('total');

        exit;

        return true;
    }
    public function isError() {
        Debug::ttt('HCController::isError()');

        if (isset($this->output['result'])
            && $this->output['result'] == 0
            && isset($this->output['errors'])) {
            return true;
        }else {
            return false;
        }
    }

    public function index() {
        $this->Render();
        return true;
    }

    public function add() {
        Debug::ttt('HCController::add()');
        $result = $this->model->insert();
        if ($result !== true) {
            $this->Error('DB_ERRORS', $result);
            return false;
        }

        $this->output['result'] = 1;
        if (isset($this->model->data) && is_array($this->model->data)) {
            switch(OUTPUT_VERSION) {
                case '1.0.0':
                    $this->output = array_merge($this->output, $this->model->data);
                    break;
                default:
                    if (isset($this->output['data'])) {
                        $this->output['data'] = array_merge($this->output['data'], $this->model->data);
                    }else {
                        $this->output['data'] = $this->model->data;
                    }
                    break;
            }
        }
        $this->Render();
        return true;
    }

    public function edit() {
        Debug::ttt('HCController::edit()');
        $result = $this->model->update();
        if ($result !== true) {
            $this->Error('DB_ERRORS', $result);
            return false;
        }
        $this->output['result'] = 1;
        $this->Render();
        return true;
    }
    public function del() {
        Debug::ttt('HCController::del()');
        $result = $this->model->delete();
        if ($result !== true) {
            $this->Error('DB_ERRORS', $result);
            return false;
        }
        $this->output['result'] = 1;
        $this->Render();
        return true;
    }

    public function get() {
        Debug::ttt('HCController::get()');
        $result = $this->model->select();
        if ($result !== true) {
            $this->Error('DB_ERRORS', $result);
            return false;
        }

        $this->output['result'] = 1;
        switch (OUTPUT_VERSION) {
            case '1.0.0':
                $this->output = $this->model->data;
                break;
            default:
                $this->output['data'] = $this->model->data;
                break;
        }
        $this->Paging();
        $this->Render();
        return true;
    }
    public function recall($args = array()) {
        Debug::ttt('HCController::Recall()');
        $url = $this->getURLbyArgs($args);
        $result = file_get_contents($url);
        Debug::ppp($url);
        return $result;
    }
    public function Redirect($args = array()) {
        Debug::ttt('HCController::Redirect()');
        $url = $this->getURLbyArgs($args);
        header('Location: '.$url) ;
    }
    public function getURLbyArgs($args = array()) {
        Debug::ttt('HCController::getURLbyArgs()');
        $url = HOME.'/?';
        foreach($args as $key => $val) {
            $url .= $key.'='.$val.'&';
        }
        $url = substr($url, 0, -1);
        return $url;
    }

    public function wiki() {
        Debug::ttt('Controller::wiki()');
        $arr_fields = array();
        foreach ($this->model->fields as $action => $config){
            foreach ($config as $field => $item){
                $arr_fields[$action][$field] = array_merge($item, $this->model->schema[$field]);
            }
        }

        $table = '<style>table{border-collapse:collapse;}table,th, td{border: 1px solid black;}.data{text-align:center;}</style>';
        $wikitable = '{| class="wikitable"<br>';
        $table .= '<table>';
        $wikitable .= '|-<br>';
        $table .= '<tr>';
        $table .= '<td>Actions&rarr;<br>Data&darr;</td>';
        $wikitable .= '! Actions&rarr;&lt;br&gt;Data&darr;';
        foreach($this->model->fields as $action => $config) {
            $table .= '<td>'.$action.'</td>';
            $wikitable .= '!!'.$action;
        }
        $table .= '</tr>';
        $wikitable .= '<br>';

        foreach($this->model->schema as $field => $item) {
            $wikitable .= '|-<br>';
            $table .= '<tr>';
            $table .= '<td>'.$field.'</td>';
            $wikitable .= '| '.$field;
            foreach($this->model->fields as $action => $config) {
                if (isset($config[$field]['required']) && $config[$field]['required'] === true) {
                    $required = '●';
                }else if (isset($config[$field]) && !isset($config[$field]['required']) || $config[$field]['required'] === false) {
                    $required = '○';
                }else {
                    $required = '';
                }
                $table .= '<td class="data">'.$required.'</td>';
                $wikitable .= '||'.$required;
            }
            $table .= '</tr>';
            $wikitable .= '<br>';
        }
        $table .= '</table>';
        $wikitable .= '|}';

        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
        echo '<h1>'.$this->controller.'</h1>';
        echo $table;
        echo '<br>';
        echo '(●: required, ○:optional)<br>';
        echo $wikitable;

    }

    public function ref() {
        Debug::ttt('Controller::ref()');

        echo "<html>";
        echo "<head>";
        echo "<title>API Reference - ".ucfirst($this->controller)."</title>";
        $indent = 15;
        echo "
        <style>
        body{font-size: 12px;line-height: 12px;}
        h1 {font-size: 18px;}
        ul,ol {padding-left: ".($indent)."px;}
        pre {margin: 0px;}

        h2 {font-size: 14px;padding-left: ".($indent)."px;}
        .field_id {padding-left: ".($indent*3)."px;}

        h3 {font-size: 12px;padding-left: ".($indent*2)."px;}
        .h3 .field_id {padding-left: ".($indent*4)."px;}
        .example {font-size: 11px;margin-left: ".($indent*2)."px;border: 1px dotted #000;padding: 10px;width: auto;}

        .default_info {padding-left: ".($indent)."px;font-size: 14px;}
        .point {color: #605ca8;font-weight: bold;}
        .gray {color: #aaa;}
        .desc {margin-left: 7px;}
        .calltitle a{color: #4F81BD;text-decoration:none;}
        .calltitle a.test {font-size:8px; color: #ccc; margin-left: 7px;}
        </style>
        ";
        echo "</head>";
        echo "<body>";

        // 1. Controller
        echo '<h1>'.ucfirst($this->controller).'</h1>';

        // 1. Contents
        $arr_fields = array();
        $contents = '';
        foreach ($this->model->fields as $action => $config){
            // Except Document Actions
            $arr_doc_action = explode(',',DOC_ACTIONS);
            if (in_array($action, $arr_doc_action)) {
                continue;
            }
            $arr_action = explode('_',$action);
            $calltitle = ucfirst($this->controller).'::';
            for($i=0; $i<count($arr_action); $i++) {
                $calltitle .= ucfirst($arr_action[$i]);
            }
            $contents .= '<a href="#'.$calltitle.'">'.$calltitle.'</a><br>';
        }
        echo $contents;

        // 1-1. Field Properties
        echo '<h2>Field Properties</h2>';
        $this->RefFields($this->model->schema, $action);

        $num = 0;
        // Each Action
        foreach ($this->model->fields as $action => $config){
            // Except Document Actions
            $arr_doc_action = explode(',',DOC_ACTIONS);
            if (in_array($action, $arr_doc_action)) {
                continue;
            }

            $num++;
            $arr_action = explode('_',$action);
            $calltitle = ucfirst($this->controller).'::';
            for($i=0; $i<count($arr_action); $i++) {
                $calltitle .= ucfirst($arr_action[$i]);
            }

            // 2. ControllerAction
            echo '<h2 class="calltitle"><a href="#" name="'.$calltitle.'" class="black">'.$calltitle.'</a><a class="test" href="?c='.$this->controller.'&a=test#'.$calltitle.'">test</a></h2>';

            // 2-1. Default Input Parameters
            echo '<h3>Default Input Parameters</h3>';
            echo '<div class="h3">';
            echo '<ul class="field_id">';
            $args = array(
                'c'=>'Controller',
                'a'=>'Action',
                'f'=>'Output format - json, jsonp, xml, html, path',
                'v'=>'View File Name or Path - list.html or cms/2.0/add.html',
                'cb'=>'Callback Function name (using only for jsonp format) - callback (default)',
                's'=>'Start - 0(default) - taking "s" first',
                'p'=>'Page - 1(default) - taking "s" first then "p"',
                'l'=>'Limit of Rows',
                'o'=>'Order by Field',
                'd'=>'Direction - ASC, DESC',
                'i'=>'Info(t:Total)'
            );
            $arr_get_parameter = array('s','p','l','o','d','i');
            foreach ($args as $field_id => $desc){
                if (in_array($field_id, $arr_get_parameter) && strpos($action,'get') === false) {
                    continue;
                }
                echo '<li>'.$field_id.' <span class="desc">- '.$desc.'</span></li>';
            }
            echo '</ul>';
            echo '</div>';

            // 2-2. Fixed Input Parameters
            echo '<h3>Fixed Input Parameters</h3>';
            echo '<div class="h3">';
            echo '<ul class="field_id">';
            echo '<li>c='.$this->controller.'</li>';
            echo '<li>a='.$action.'</li>';
            echo '</ul>';
            echo '</div>';

            // 2-3. Additional Input Parameters
            echo '<h3>Additional Input Parameters</h3>';
            echo '<div class="h3">';
            $this->RefFields($config, $action);
            echo '</div>';

            // 2-4. Input Examples
            echo '<h3>Input Examples</h3>';
            $input_example = array();
            $input_example[0] = API_PROTOCOL.'://'.DOMAIN_LIVE.'/'.API_FOLDER.'/?c='.$this->controller.'&a='.$action;
            foreach ($config as $field_id => $options){
                if (isset($options['required']) && $options['required'] === TRUE) {
                    $input_example[0] .= '&'.$field_id.'=value';
                }
            }
            foreach ($config as $field_id => $options){
                if (!isset($options['required']) || $options['required'] !== TRUE) {
                    $input_example[] = $input_example[count($input_example)-1].'&'.$field_id.'=value';
                }
            }
            echo '<div class="example">';
            for ($i=0; $i<count($input_example); $i++){
                echo '<a href="'.$input_example[$i].'">'.$input_example[$i].'</a><br>';
            }
            echo '</div>';


            // 2-4. Output Parameters
            echo '<h3>Output Parameters</h3>';
            echo '<div class="h3">';
            echo '<ul class="field_id">';
            if (isset($this->model->ofields[$action])) {
                foreach ($this->model->ofields[$action] as $field_id => $options){
                    echo '<li>'.$field_id.'</li>';
                }
            }else {
                echo '<li>Not defined</li>';
            }
            echo '</ul>';
            echo '</div>';

            // 2-4. Output Example
            echo '<h3>Output Example</h3>';
            $output_example =  '<div class="example">';
            $output_example .=  'Array (<br>';
            $output_example .=  '    [result] => 1,<br>';
            /*$output_example .=  '    [result] => 1 <span class="gray">or 0</span>,<br>';
            $output_example .=  '    <span class="gray">[errors] => Object (<br>';
            $output_example .=  '        [code] => ERROR_NO_CONTROLLER_CLASS,<br>';
            $output_example .=  '        [text] => You have to do something,<br>';
            $output_example .=  '        [info] => Object (<br>';
            $output_example .=  '            [info1] => info1value<br>';
            $output_example .=  '        ),<br>';
            $output_example .=  '        [fields] => Object (<br>';
            $output_example .=  '            [field1] => Object (<br>';
            $output_example .=  '                [code] => ERROR_REQUIRED,<br>';
            $output_example .=  '                [text] => You have to do something<br>';
            $output_example .=  '            ),<br>';
            $output_example .=  '            [field2] => Object (<br>';
            $output_example .=  '                [code] => ERROR_REQUIRED,<br>';
            $output_example .=  '                [text] => You have to do something<br>';
            $output_example .=  '            )<br>';
            $output_example .=  '        )<br>';
            $output_example .=  '    ),</span><br>';*/
            $output_example .=  '    [data] => Array (<br>';
            //$output_example .=  '        [info] => Object (<br>';
            //$output_example .=  '            [total] => 1,<br>';
            //$output_example .=  '        ),<br>';
            $output_example .=  '        [items] => Array (<br>';
            $output_example .=  '            [0] => Array (<br>';
            if (isset($this->model->ofields[$action])) {
                foreach ($this->model->ofields[$action] as $field_id => $options){
                    $output_example .=  '                ['.$field_id.'] => value,<br>';
                }
            }else {
                $output_example .=  '                Not defined<br>';
            }
            $output_example .=  '            )<br>';
            $output_example .=  '        )<br>';
            $output_example .=  '    )<br>';
            $output_example .=  ')';
            $output_example .=  '</div>';
            echo str_replace('  ','&nbsp;&nbsp;',$output_example);
        }

        echo "</body>";
        echo "</html>";
    }

    public function RefFields($config, $action='') {
        Debug::ttt('Controller::RefFields($config, $action)');
        $field_num = 0;
        echo '<ul class="field_id">';
        // Field List
        foreach ($config as $field_id => $options){
            if ($field_id == 'id' && strpos($action,'add') !== false) {
                continue;
            }
            $field_num++;
            echo '<li>'.$field_id.'</li>';
            // Option List
            echo '<ul class="options">';
            foreach ($options as $option_id => $values){
                switch ($option_id) {
                    case 'field':
                        echo '<li>Database Field Name: '.$values.'</li>';
                        break;
                    case 'operator':
                        echo '<li>Filtering Operator: '.$values.'</li>';
                        break;
                    case 'sailthru_var':
                        echo '<li>Sailthru Variable Name: '.$values.'</li>';
                        break;
                    case 'type':
                        echo '<li>Type: '.$values.'</li>';
                        break;
                    case 'value':
                        echo '<li>Value: '.$values.'</li>';
                        break;
                    case 'error':
                        echo '<li>Error: '.$values.'</li>';
                        break;
                    case 'formatted':
                        echo '<li>Formatted Value: '.$values.'</li>';
                        break;
                    case 'default':
                        echo '<li>Default Value: '.$values.'</li>';
                        break;
                    case 'pk':
                        if ($values === true) {
                            echo '<li>Primary Key of Database Table</li>';
                        }
                        break;
                    case 'autoinc':
                        if ($values === true) {
                            echo '<li>Auto Increasing Field</li>';
                        }
                        break;
                    case 'required':
                        if ($values === true) {
                            echo '<li>Required Field</li>';
                        }
                        break;
                    case 'value_from':
                        if (isset($values) && !empty($values)) {
                            echo '<li>If you don\'t pass the value, the value is copied from '.$values.'</li>';
                        }
                        break;
                    case 'where':
                        if ($values === true) {
                            echo '<li>Using as Search Condition</li>';
                        }
                        break;
                    case 'rules':
                        echo '<li>Rules:';
                        echo '<ul class="rules">';
                        // Rule list
                        foreach ($values as $rule_id => $rule_values){
                            switch($rule_id) {
                                case 'array':
                                    echo '<li>The value must be Array type</li>';
                                    break;
                                case 'token':
                                    echo '<li>Token Verification Required with '.$rule_values.'</li>';
                                    break;
                                case 'rgb_color':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be RGB Color Format</li>';
                                    }
                                    break;
                                case 'date':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be Date Format (YYYY-MM-DD)</li>';
                                    }
                                    break;
                                case 'date_mdy':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be Date Format (MM-DD-YY)</li>';
                                    }
                                    break;
                                case 'datetime':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be DateTime Format (YYYY-MM-DD HH:MI:SS)</li>';
                                    }
                                    break;
                                case 'url':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be URL Format</li>';
                                    }
                                    break;
                                case 'file':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be File Type</li>';
                                    }
                                    break;
                                case 'email':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be E-Mail Format</li>';
                                    }
                                    break;
                                case 'numeric':
                                    if ($rule_values === true) {
                                        echo '<li>The value must be Numeric</li>';
                                    }
                                    break;
                                case 'length':
                                    echo '<li>The value cannot be exceeded '.$rule_values.'</li>';
                                    break;
                                case 'max_length':
                                    echo '<li>The max length of the value is '.$rule_values.'</li>';
                                    break;
                                case 'min_length':
                                    echo '<li>The length of the value must be at least '.$rule_values.'</li>';
                                    break;
                                case 'admin':
                                    echo '<li>The call is allowed to the admins only</li>';
                                    break;
                                case 'permission':
                                    echo '<li>The call is allowed to the admins who has permission only</li>';
                                    break;
                                case 'owner':
                                    echo '<li>The call is allowed to the admins who own only</li>';
                                    break;
                                case 'enum':
                                    if (is_array($rule_values)) {
                                        $enum_values = '';
                                        for($i=0;$i<count($rule_values); $i++) {
                                            if ($enum_values != '') $enum_values .= ', ';
                                            $enum_values .= $rule_values[$i];
                                        }
                                        echo '<li>The value must be one of '.$enum_values.'</li>';
                                    }
                                    break;
                                default:
                                    echo '<li>'.$rule_id.': '.$rule_values.'</li>';
                                    break;
                            }
                        }// Rule List
                        echo '</li>';
                        echo '</ul>'; // Rules Close
                        break;
                    default:
                        echo '<li>'.$option_id.': '.$values.'</li>';
                        break;
                }
            }// Option List
            echo '</ul>'; // Option Close
        }
        echo '</ul>'; // Field Close
    }

    public function test() {
        Debug::ttt('Controller::test()');

        echo '<html>';
        echo '<head>';
        echo '<title>API Test - '.ucfirst($this->controller).'</title>';
        echo "
        <style>
        body{font-size: 12px;line-height: 12px;}
        div {vertical-align: top;}
        .label {width: 100px;}
        .label,.input,.note{display:inline-block;}
        .note {font-size: 12px;display:none;}
        .input input {width: 300px;}
        .submit {font-size: 24px;margin-left: 15px;}
        .calltitle a{color: #4F81BD;text-decoration:none;}
        .calltitle a.ref {font-size:8px; color: #ccc;margin-left:7px;}
        h1 {font-size:18px;}
        h2 {font-size:14px;}
        .boldedbox {background-color: #FFFFE2}
        .boldedlabel {font-weight: bold; color:#660033;font-size:13px;}
        </style>
        ";
        echo '</head>';
        echo '<body>';

        // 1. Controller
        echo '<h1>'.ucfirst($this->controller).'</h1>';


        // 1. Merge Schema and Fields
        $arr_fields = array();
        $contents = '';
        foreach ($this->model->fields as $action => $config){
            // Except Document Actions
            $arr_doc_action = explode(',',DOC_ACTIONS);
            if (in_array($action, $arr_doc_action)) {
                continue;
            }
            foreach ($config as $field => $item){
                $arr_fields[$action][$field] = array_merge($item, $this->model->schema[$field]);
            }
            $arr_action = explode('_',$action);
            $calltitle = ucfirst($this->controller).'::';
            for($i=0; $i<count($arr_action); $i++) {
                $calltitle .= ucfirst($arr_action[$i]);
            }
            $contents .= '<a href="#'.$calltitle.'">'.$calltitle.'</a><br>';
        }
        echo $contents.'<br>';

        // 2. Loop Actions
        foreach ($arr_fields as $action => $config){
            // Except Document Actions
            $arr_doc_action = explode(',',DOC_ACTIONS);
            if (in_array($action, $arr_doc_action)) {
                continue;
            }

            $arr_action = explode('_',$action);
            $calltitle = ucfirst($this->controller).'::';
            for($i=0; $i<count($arr_action); $i++) {
                $calltitle .= ucfirst($arr_action[$i]);
            }

            $is_file = false;
            foreach ($config as $field_id => $options){
                if ($options['rules']['file'] === true) {
                    $is_file = true;
                }
            }
            if ($is_file === true) {
                echo '<form method="post" enctype="multipart/form-data">';
            }else {
                echo '<form>';
            }
            echo '<h2 class="calltitle"><a href="#" name="'.$calltitle.'">'.$calltitle.'</a><a class="ref" href="?c='.$this->controller.'&a=ref#'.$calltitle.'">ref</a></h2>';
            echo '<ul>';

            // 3. Loop Default Parameters
            $args = array(
                'c'=>'Controller',
                'a'=>'Action',
                'f'=>'Output format - json, jsonp, xml, html, path',
                'v'=>'View File Name or Path - list.html or cms/2.0/add.html',
                'cb'=>'Callback Function name (using only for jsonp format) - callback (default)',
                's'=>'Start - 0(default) - taking "s" first',
                'p'=>'Page - 1(default) - taking "s" first then "p"',
                'l'=>'Limit of Rows',
                'o'=>'Order by Field',
                'd'=>'Direction - ASC, DESC',
                'i'=>'Info(t:Total)'
            );
            $arr_get_parameter = array('s','p','l','o','d','t');
            foreach ($args as $field_id => $note){
                $value='';
                if ($field_id == 'a') {
                    $value = $action;
                }else if ($field_id == 'l') {
                    //$value = '10';
                }else if ($field_id == 'f') {
                    $value = 'path';
                }else if ($field_id == 'v') {
                    $value = 'cms/list.html';
                }else if (isset($this->args[$field_id])) {
                    $value = $this->args[$field_id];
                }
                if (in_array($field_id, $arr_get_parameter) && strpos($action,'get') === false) {
                    continue;
                }
                echo '    <li>';
                echo '    <div class="label">'.$field_id.'</div> <div class="input"><input type="text" name="'.$field_id.'" value="'.$value.'"/></div> <div class="note">'.$note.'</div>';
                echo '    </li>';
            }

            // 4. Loop Config
            foreach ($config as $field_id => $options){
                if ($field_id == 'id' && strpos($action,'add') !== false) {
                    continue;
                }

                $note = '';
                // 5. Loop Options
                foreach ($options as $option_id => $values){
                    switch ($option_id) {
                        case 'field':
                            $note .= 'Database Field Name: '.$values.'<br>';
                            break;
                        case 'operator':
                            $note .= 'Filtering Operator: '.$values.'<br>';
                            break;
                        case 'sailthru_var':
                            $note .= 'Sailthru Variable Name: '.$values.'<br>';
                            break;
                        case 'type':
                            $note .= 'Type: '.$values.'<br>';
                            break;
                        case 'value':
                            $note .= 'Value: '.$values.'<br>';
                            break;
                        case 'error':
                            $note .= 'Error: '.$values.'<br>';
                            break;
                        case 'formatted':
                            $note .= 'Formatted Value: '.$values.'<br>';
                            break;
                        case 'default':
                            $note .= 'Default Value: '.$values.'<br>';
                            break;
                        case 'pk':
                            if ($values === true) {
                                $note .= 'Primary Key of Database Table<br>';
                            }
                            break;
                        case 'autoinc':
                            if ($values === true) {
                                $note .= 'Auto Increasing Field<br>';
                            }
                            break;
                        case 'required':
                            if ($values === true) {
                                $note .= 'Required Field<br>';
                            }
                            break;
                        case 'value_from':
                            if (isset($values) && !empty($values)) {
                                $note .= 'If you don\'t pass the value, the value is copied from '.$values.'<br>';
                            }
                            break;
                        case 'where':
                            if ($values === true) {
                                $note .= 'Using as Search Condition<br>';
                            }
                            break;
                        case 'rules':
                            $note .= 'Rules:';
                            // 6. Loop Rules
                            foreach ($values as $rule_id => $rule_values){
                                switch($rule_id) {
                                    case 'token':
                                        $note .= 'Token Verification Required with '.$rule_values.'<br>';
                                        break;
                                    case 'rgb_color':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be RGB Color Format<br>';
                                        }
                                        break;
                                    case 'date':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be Date Format (YYYY-MM-DD)<br>';
                                        }
                                        break;
                                    case 'date_mdy':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be Date Format (MM-DD-YY)<br>';
                                        }
                                        break;
                                    case 'datetime':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be DateTime Format (YYYY-MM-DD HH:MI:SS)<br>';
                                        }
                                        break;
                                    case 'url':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be URL Format<br>';
                                        }
                                        break;
                                    case 'file':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be File Type<br>';
                                        }
                                        break;
                                    case 'email':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be E-Mail Format<br>';
                                        }
                                        break;
                                    case 'numeric':
                                        if ($rule_values === true) {
                                            $note .= 'The value must be Numeric<br>';
                                        }
                                        break;
                                    case 'length':
                                        $note .= 'The value cannot be exceeded '.$rule_values.'<br>';
                                        break;
                                    case 'max_length':
                                        $note .= 'The max length of the value is '.$rule_values.'<br>';
                                        break;
                                    case 'min_length':
                                        $note .= 'The length of the value must be at least '.$rule_values.'<br>';
                                        break;
                                    case 'enum':
                                        if (is_array($rule_values)) {
                                            $enum_values = '';
                                            for($i=0;$i<count($rule_values); $i++) {
                                                if ($enum_values != '') $enum_values .= ', ';
                                                $enum_values .= $rule_values[$i];
                                            }
                                            $note .= 'The value must be one of '.$enum_values.'<br>';
                                        }
                                        break;
                                    default:
                                        $note .= ''.$rule_id.': '.$rule_values.'<br>';
                                        break;
                                }
                            }// Rule
                            break;
                        default:
                            $note .= ''.$option_id.': '.$values.'<br>';
                            break;
                    }
                }// Options Looping End
                $value='';
                if (isset($this->args[$field_id])) {
                    $value = $this->args[$field_id];
                }
                echo '    <li>';
                echo '    <div class="label boldedlabel">'.$field_id.'</div> <div class="input">';
                if ($options['rules']['file'] === true) {
                    echo '<input type="file" name="'.$field_id.'" value="'.$value.'" class="boldedbox"/>';
                }else {
                    echo '<input type="text" name="'.$field_id.'" value="'.$value.'" class="boldedbox"/>';
                }
                echo '</div> <div class="note">'.$note.'</div>';

                echo '    </li>';
            } // Config Looping End

            // Debug
            echo '    <li>';
            echo '    <div class="label">debug</div> <div class="input"><input type="text" name="debug" value="ye"/></div> <div class="note"></div>';
            echo '    </li>';
            echo '</ul>';
            echo '<input type="submit" class="submit" value="Submit" /><br><br><br>';
            echo '</form>';
        } // Actions Looping End
        echo '</body>';
        echo '</html>';
    }


}
?>