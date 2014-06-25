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
        'd'=>''     // direction
    );

    public $controller;
    public $action;

    public $db;
    public $memcache;
    public $model;

    public $output;
    public $messages;

    public function __construct() {
        Debug::ttt('HCController::__construct()');
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

    public function setArgs() {
        Debug::ttt('HCController::setArgs()');

        $this->args = Web::getArgs();

        foreach ($this->args as $key => $val) {
            $this->args[$key] = String::HtmlToTxt($val);
        }
        if (isset($this->args['p']) && !isset($this->args['s'])) {
            $this->args['s'] = $this->args['p']*$this->args['l']-$this->args['l'];
        }else if (!isset($this->args['p']) && isset($this->args['s'])) {
            if ($this->args['s'] == 0) {
                $this->args['p'] = 1;
            } else {
                $this->args['p'] = floor($this->args['s']/$this->args['l']) + 1;
            }
        }
        $this->controller = $this->args['c'];
        $this->action = $this->args['a'];
        return true;
    }
    private function setDB() {
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
    private function setModel() {
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
            $model->setValues($this->args);
        } catch (Exception $exc) {
            Debug::Error($exc);
            return false;
        }

        $model->db = & $this->db;
        $model->memcache = & $this->memcache;
        return $model;
    }

    public function setSLOD(& $args, & $model) {
        Debug::ttt('HCController::setSLOD()');
        if (isset($args['s']) && !empty($args['s'])) $model->start = $args['s'];
        if (isset($args['l']) && !empty($args['l'])) $model->limit = $args['l'];
        if (isset($args['o']) && !empty($args['o'])
            && isset($model->schema[$args['o']])) {
            if (isset($model->schema[$args['o']]['field']) && !empty($model->schema[$args['o']]['field'])) {
                $model->orderby = $model->schema[$args['o']]['field'];
            }else {
                $model->orderby = $args['o'];
            }
        }
        if (isset($args['d']) && !empty($args['d'])) $model->direction = $args['d'];

        if (!isset($model->start) || $model->start < 0) $model->start = 0;
        if (!isset($model->limit) || $model->limit > SELECT_LIMIT) $model->limit = SELECT_LIMIT;
        return true;
    }

    private function Validate() {
        Debug::ttt('HCController::Validate()');
        if ($this->isError()) return false;
        if (empty($this->action)) return true;
        if (!isset($this->model->config)) return true;

        $ok = true;
        $config = & $this->model->config;
        foreach($config as $field => $options) {
            Debug::ppp($field);

            // Required
            if (isset($options['required']) && $options['required'] !== false) {
                if (isset($options['type']) && $options['type'] == 'file') {
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
                }else {
                    if (!isset($options['value']) || empty($options['value'])) {
                        $config[$field]['error'] = 'ERROR_REQUIRED';
                        $ok = false;
                    }
                }
            }

            // Check Next Field
            if (isset($config[$field]['error']) && !empty($config[$field]['error'])) continue;
            Debug::ppp($options);

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

    private function setMessages() {
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
            $this->returnArgs();
        }
        $this->beforeRender();
        $this->staticRender($this->output, $filename);
        $this->afterRender();
        return true;
    }
    public function beforeRender() {}

    public function afterRender() {}

    public function returnArgs() {
        Debug::ttt('HCController::returnArgs()');
        if (!isset($this->model->config)) return false;

        $this->output['data']['info']['args'] = $this->args;

        // Basic Args
        /*
        if (isset($this->args['c'])) {$this->output['data']['info']['controller'] = $this->args['c'];}
        if (isset($this->args['a'])) {$this->output['data']['info']['action'] = $this->args['a'];}
        if (isset($this->args['o'])) {$this->output['data']['info']['order'] = $this->args['o'];}
        if (isset($this->args['d'])) {$this->output['data']['info']['direction'] = $this->args['d'];}
        if (isset($this->args['s'])) {$this->output['data']['info']['start'] = $this->args['s'];}
        if (isset($this->args['p'])) {$this->output['data']['info']['page'] = $this->args['p'];}
        if (isset($this->args['l'])) {$this->output['data']['info']['limit'] = $this->args['l'];}
        if (isset($this->args['f'])) {$this->output['data']['info']['format'] = $this->args['f'];}
        if (isset($this->args['v'])) {$this->output['data']['info']['view'] = $this->args['v'];}
        if (isset($this->args['cb'])) {$this->output['data']['info']['callback'] = $this->args['cb'];}

        // Config Args
        foreach ($this->model->config as $key => $options) {
            if (isset($options['value']) && !empty($options['value'])
                && $key != 'token' && $key != 'referer') {
                $this->output['data']['info'][$key] = $options['value'];
            }else if (isset($options['value']) && ($options['value'] === 0 || $options['value'] === '0')) {
                $this->output['data']['info'][$key] = $options['value'];
            }
        }
        */
        return true;
    }

    public static function staticRender($output, $filename='') {
        Debug::ttt('HCController::staticRender()');

        if (OUTPUT_VERSION == '1.0.0') {
            $data = $output;
        }

        if (DEBUG <= 0 && TEST_CASE !== true) {
            header("Cache-Control: no-store, no-cache, must-revalidate, private, post-check=0, pre-check=0");
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
            if (isset($callback)) $callback = Web::getArg('cb');
            if (empty($callback)) $callback = 'callback';

            echo $callback.'('.json_encode($output).')';
        }else if (Web::getArg('f') == 'xml') {
            if (DEBUG <= 0 && TEST_CASE !== true) {
                header('Content-type: text/xml; charset=utf-8');
            }
            echo String::ArrayToXML ($output);
        }else if (Web::getArg('f') == 'html' && !empty($filename)) {
            if((@include ROOT.'/views/'.Web::getArg('c').'/'.Web::getArg('a').'/'.$filename) === false) {
                Controller::StaticError('INIT_ERRORS', 'ERROR_NO_VIEW_FILE');
            }
        }else if (Web::getArg('f') == 'path' && !empty($filename)) {
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
        $this->Render();
        return true;
    }
    public function recall($args = array()) {
        Debug::ttt('HCController::recall()');
        $url = $this->getURLbyArgs($args);
        $result = file_get_contents($url);
        Debug::ppp($url);
        return $result;
    }
    public function redirect($args = array()) {
        Debug::ttt('HCController::redirect()');
        $url = $this->getURLbyArgs($args);
        header('Location: '.$url) ;
    }
    public function getURLbyArgs($args = array()) {
        $url = HOME.'/?';
        foreach($args as $key => $val) {
            $url .= $key.'='.$val.'&';
        }
        $url = substr($url, 0, -1);
        return $url;
    }
}
?>
