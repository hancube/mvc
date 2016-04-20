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
namespace hc\mvc;

require_once (MVC_LIB.'/lib/version.php');
require_once (MVC_LIB.'/lib/debug.php');
require_once (MVC_LIB.'/lib/db.php');
require_once (MVC_LIB.'/lib/string.php');
require_once (MVC_LIB.'/lib/web.php');
require_once (MVC_LIB.'/lib/validate.php');
require_once (MVC_LIB.'/lib/json_encode.php');
if (USE_MEMCACHED) require_once (MVC_LIB.'/lib/memcached.php');

class Controller{
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
    public $memcached;
    public $model;

    public $output = array(
        'result' => 0
    );
    public $messages;

    public function __construct() {
        Debug::ttt('hc\mvc\Controller::construct()');
        $this->setArgs();
        return true;
    }

    public function run() {
        Debug::ttt('hc\mvc\Controller::run()');

        try{
            if (!$this->setDB()) {
                $this->Error('DB_ERRORS', 'ERROR_DB_CONNECTION');
                return false;
            }
        }catch (PDOException $e) {
            $this->Error('DB_ERRORS', 'ERROR_DB_CONNECTION');
            return false;
        }
        if (USE_MEMCACHED === true) $this->setMemcached();
        $this->setMessages();
        $this->setModel();
        if (!$this->Validate($this->model->config)) {
            $this->Error('INPUT_ERRORS');
            return false;
        }

        return true;
    }
    public function runAction($action) {
        Debug::ttt('hc\mvc\Controller::runAction($action)');
        $result = true;
        if (method_exists($this, $action)) {
            // run set model
            $this->run();
            // if the action fields are not defined, do not execute action.
            if (isset($this->model->config)) {
                if (!$this->isError()) {
                    try {
                        eval('$this->'.$action.'();');
                    }catch (ErrorException $e) {
                        $result = 'ERROR_NO_ACTION';
                    }
                }else {
                    return $this->output['errors']['code'];
                }
            }else {
                $this->output['errors']['code'] = 'ERROR_NO_ACTION_FIELDS_DEFINED';
                return $this->output['errors']['code'];
            }
        }else {
            $result = false;
        }

        return $result;
    }
    public function setArgs() {
        Debug::ttt('hc\mvc\Controller::setArgs()');

        $this->args = Web::getArgs();

        if (isset($this->args['v'])) {
            $this->args['v'] = str_replace('..', '', $this->args['v']);
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
        Debug::ttt('hc\mvc\Controller::setExtraArgs()');

    }
    public function setDB() {
        Debug::ttt('hc\mvc\Controller::setDB()');
        try {
            $this->db = new DB(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
            if (!$this->db) {
                Debug::Error('ERROR_DB_CONNECTION');
                return false;
            }
            $stmt = $this->db->prepare("SET character_set_connection = 'utf8'");
            $stmt->execute();
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
    public function setMemcached() {
        Debug::ttt('hc\mvc\Controller::setMemcached()');
        global $_MEMCACHED_HOST;
        Debug::ppp($_MEMCACHED_HOST);

        if (!class_exists('Memcached')) return true;
        if (isset($this->memcached)) return true;

        $this->memcached = new Memcached($_MEMCACHED_HOST, MEMCACHED_PORT, MEMCACHED_TIME);

        return true;
    }
    public function getModel($controller, $action, &$db = NULL, &$args = NULL) {
        Debug::ttt('hc\mvc\Controller::getModel('.$controller.', '.$action.')');
        if (empty($action)) return false;

        $model_file = API_ROOT.'/models/'.$controller.'_model.php';
        if (!file_exists($model_file)) {
            return false;
        }
        require_once ($model_file);

        try {
            $model_class = Web::getClassName($controller);
            $model_class .= 'Model';
            eval('$model = new '.$model_class.'(\''.$action.'\');');
            if ($args !== NULL) {
                $model->setValues($this->args);
            }
        } catch (Exception $exc) {
            Debug::Error($exc);
            return false;
        }

        if (is_null($db)) {
            $model->db = & $this->db;
        }else {
            $model->db = & $db;
        }

        $model->memcached = & $this->memcached;

        return $model;
    }
    public function setModel() {
        Debug::ttt('hc\mvc\Controller::setModel()');

        $this->model = $this->getModel($this->controller, $this->action, $this->db, $this->args);
        $this->setSLOD($this->args, $this->model);

        return true;
    }
    public function setSLOD(& $args, & $model) {
        Debug::ttt('hc\mvc\Controller::setSLOD()');
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
    public function Validate(& $config) {
        Debug::ttt('hc\mvc\Controller::Validate()');
        if ($this->isError()) return false;
        if (empty($this->action)) return true;
        if (!isset($config)) return true;

        $ok = true;
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
        Debug::ttt('hc\mvc\Controller::setMessages()');
        $ini_file = API_ROOT.'/lang/'.LANG.'/message.ini';
        $this->messages = parse_ini_file($ini_file);
        return true;
    }
    public function Render($filename = '') {
        Debug::ttt('hc\mvc\Controller::Render('.$filename.')');

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
        Debug::ttt('hc\mvc\Controller::setReturnArgs()');
        if (!isset($this->model->config)) return false;

        if (defined('RETURN_ARGS') && RETURN_ARGS === TRUE) {
            $this->output['info']['args'] = $this->args;
        }

        return true;
    }

    public static function staticRender($output, $filename='') {
        Debug::ttt('hc\mvc\Controller::staticRender()');

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
            $je = new JSONEncode();
            echo $je->runEncode($output);
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
            $je = new JSONEncode();
            echo $callback.'('.$je->runEncode($output).')';
        }else if (Web::getArg('f') == 'xml') {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/xml; charset=utf-8');
            }
            echo String::ArrayToXML ($output);
        }else if (Web::getArg('f') == 'html' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/html; charset=utf-8');
            }
            if((@include API_ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === false) {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'xls' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                $tmp = explode('/', $filename);
                $download_filename = $tmp[count($tmp)-1];
                header('Content-Disposition: attachment; filename='.$download_filename);
                header('Content-type: application/vnd.ms-excel; charset=utf-8');
            }
            if(count($tmp) > 1 && file_exists(ROOT.'/views/'.$filename) === true) {
                include ROOT.'/views/'.$filename;
            } else if(file_exists(ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === true) {
                include ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename;
            } else {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'xlsx' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                $tmp = explode('/', $filename);
                $download_filename = $tmp[count($tmp)-1];
                header('Content-Disposition: attachment; filename='.$download_filename);
                header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
            }
            if(count($tmp) > 1 && file_exists(ROOT.'/views/'.$filename) === true) {
                include ROOT.'/views/'.$filename;
            } else if(file_exists(ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === true) {
                include ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename;
            } else {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'path' && !empty($filename)) {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/html; charset=utf-8');
            }
            if((@include API_ROOT.'/views/'.$filename) === false) {
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
        Debug::ttt('hc\mvc\Controller::Error("'.$cate.'", "'.$error.'")');
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
        self::log($this->output);

        exit;

        return true;
    }
    public function getMessage($cate, $code, $value) {
        $message = $this->StaticGetMessageg($cate, $code);
        if (!is_array($value)) {
            $message = str_replace('#value#', $value, $message);
        }
        return $message;
    }

    public static function StaticGetMessageg($cate, $code) {
        $ini_file = API_ROOT.'/lang/'.LANG.'/message.ini';
        $messages = parse_ini_file($ini_file);
        if (isset($messages[$code])) return $messages[$code];
        else return $code;
    }

    public static function StaticError($cate, $error = '') {
        Debug::ttt('hc\mvc\Controller::InitError("'.$cate.'", "'.$error.'")');

        switch (OUTPUT_VERSION) {
            case '1.0.0':
                $output = array('result' => 0,
                    'errors' => array(strtolower($cate) => $error));
                break;
            default:
                $error_msg = Controller::StaticGetMessageg($cate, $error);
                $output = array('result' => 0,
                    'errors' => array(
                        'code' => $error,
                        'text' => $error_msg
                    ));
        }

        // Render
        Controller::staticRender($output);

        self::log($output);
        Debug::ttt('total');

        exit;

        return true;
    }
    public function isError() {
        Debug::ttt('hc\mvc\Controller::isError()');

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
        Debug::ttt('hc\mvc\Controller::add()');
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
        Debug::ttt('hc\mvc\Controller::edit()');
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
        Debug::ttt('hc\mvc\Controller::del()');
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
        Debug::ttt('hc\mvc\Controller::get()');
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
    /*
     $option = array(
        'url'    => '',
        'method' => 'GET'
        'params' => {
            'c' => '',
            'a' => '',
            'f' => 'JSON'
        }
     );
     */
    public function recall($option = array()) { // GET Call Only
        Debug::ttt('hc\mvc\Controller::Recall()');

        // getAPIURL
        if (isset($option['url']) && !empty($option['url'])) {
            $url = $option['url'].'/?';
            unset($option['url']);
        }else {
            $url = API_HOME.'/?';
        }

        // getMethod
        if (isset($option['method']) && strtoupper($option['method']) == 'POST') {
            $method = 'POST';
        }else {
            $method = 'GET';
        }

        // getParams
        if (isset($option['params']) && !empty($option['params'])) {
            $params = $option['params'];
        }else {
            $params = array();
        }

        // mergeParam
        if (!isset($params) || empty($params)) return '';
        $merged_params = '';
        foreach ($params as $key => $val) {
            $merged_params .= $key.'='.$val.'&';
        }
        $merged_params = rtrim($merged_params, '&');

        // sendRequest
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (isset($params) && !empty($params)) {
            curl_setopt($ch, CURLOPT_POST, count($params));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $merged_params);
        }
        $response = curl_exec ($ch);

        if (curl_errno($ch)) {
            //print curl_error($ch);
            return false;
        } else {
            curl_close($ch);
            return $response;
        }
    }
    public function Redirect($args = array()) {
        Debug::ttt('hc\mvc\Controller::Redirect()');
        $url = $this->getURLbyArgs($args);
        header('Location: '.$url) ;
    }
    public function getURLbyArgs($args = array()) {
        Debug::ttt('hc\mvc\Controller::getURLbyArgs($args)');
        if (isset($args['API_HOME']) && !empty($args['API_HOME'])) {
            $url = $args['API_HOME'].'/?';
            unset($args['API_HOME']);
        }else {
            $url = API_HOME.'/?';
        }
        foreach($args as $key => $val) {
            $url .= $key.'='.$val.'&';
        }
        $url = substr($url, 0, -1);
        return $url;
    }

    public static function log($output) {
        if (USE_APILOG !== true) return false;
        if (SERVICE !== SERVICE_LIVE) return false;

        global $controller, $action;
        if (!isset($output['result']) || $output['result'] == 0) {
            $result = 0;
            $error_code = $output['errors']['code'];
            if (isset($output['errors']['fields']) && !empty($output['errors']['fields'])) {
                $field_error = '';
                foreach($output['errors']['fields'] as $field => $error) {
                    if ($field_error != '') $field_error .= ',';
                    $field_error .= $field.':'.$error['code'];
                }
                $error_code = $error_code.'['.$field_error.']';
            }
        }else {
            $result = 1;
            $error_code = '';
        }
        $serialized = serialize($output);
        if (function_exists('mb_strlen')) {
            $data_size = mb_strlen($serialized, '8bit');
        } else {
            $data_size = strlen($serialized);
        }

        $data = array(
            'controller'  => $controller,
            'action'      => $action,
            'result'      => $result,
            'error_code'  => $error_code,
            'data_size'   => $data_size
        );
        Log::sendLog($data);
    }
    public function ref() {
        Debug::ttt('Controller::ref()');

        $default_args = array(
            'c'=>'Controller',
            'a'=>'Action',
            'f'=>'Output format - json, jsonp, xml, html, path',
            'v'=>'HTML Filename or Path - list.html or cms/list.html',
            'cb'=>'Callback Function name (using only for jsonp format) - callback (default)',
            's'=>'Start - 0(default) - taking "s" first',
            'p'=>'Page - 1(default) - taking "s" first then "p"',
            'l'=>'Limit of Rows',
            'o'=>'Order by Field',
            'd'=>'Direction - ASC, DESC',
            'i'=>'Info(t:Total)'
        );
        $default_list_args = array('s','p','l','o','d','i');

        $arr_doc_action = explode(',',DOC_ACTIONS);

        $controller = array(
            'name' => $this->controller,
            'title' => strtoupper(PREFIX).ucfirst(CNAME),
        );

        $actions = array();
        foreach ($this->model->fields as $action => $action_fields){
            if (in_array($action, $arr_doc_action)) continue;

            // $action_title
            $action_title = '';
            $arr_action = explode('_', $action);
            for($i=0; $i<count($arr_action); $i++) {
                $action_title .= ucfirst($arr_action[$i]);
            }

            // $api_title
            $api_title = $controller['title'].'::'.$action_title;
            $api_url = $controller['name'].'/'.$action;

            // $desc
            $desc = '';
            if (isset($this->model->ofields[$action]['desc'])) {
                $desc = $this->model->ofields[$action]['desc'];
            }

            // default fields
            $fields = array();
            foreach ($default_args as $field_id => $field_desc){
                if (in_array($field_id, $default_list_args)
                    && (!isset($this->model->ofields[$action]['list'])
                    || $this->model->ofields[$action]['list'] !== TRUE))  {
                    continue;
                }else {
                    $fields[$field_id] = array(
                        'api_default_field' => true,
                        'desc'              => $field_desc
                    );
                }
            }

            // field and schema into the default field
            foreach ($action_fields as $field_id => $options){
                if (!isset($fields[$field_id])) $fields[$field_id] = array();
                // field
                if (isset($options)) {
                    $fields[$field_id] = array_merge($fields[$field_id], $options);
                }
                // schema
                if (isset($this->model->schema[$field_id])) {
                    $fields[$field_id] = array_merge($fields[$field_id], $this->model->schema[$field_id]);
                }
            }

            $actions[$action] = array(
                'api_title'    => $api_title,
                'api_url'      => $api_url,
                'name'         => $action,
                'title'        => $action_title,
                'desc'         => $desc,
                'fields'       => $fields,
            );
        }

        $this->output['info']['controller'] = $controller;
        $this->output['info']['actions'] = $actions;
        $this->output['info']['token'] = $this->model->config['token']['value'];
        $this->output['info']['referer'] = $this->model->config['referer']['value'];
        $this->output['info']['userid'] = $this->model->config['userid']['value'];

        $this->output['result'] = 1;
        $this->Render();
        return true;
    }

}
?>