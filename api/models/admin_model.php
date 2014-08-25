<?php

class AdminModel extends Model{
    public $table = 'Admins';

    public $schema = array(
        'id' => array (
            'field'     => 'Id',
            'where'     => TRUE,
            'pk'        => TRUE,
            'rules'     => array (
                'numeric'    => TRUE,
                'max_length' => 11
            )
        ),
        'userid' => array (
            'field'     => 'userId',
            'rules'     => array (
                'numeric'    => TRUE,
                'max_length' => 11
            )
        ),
        'name' => array (
            'field'     => 'Name',
            'rules'     => array (
                'max_length' => 255
            )
        )
    );

    public $fields = array(
        'add' => array(
            'id' => array (
                'required' => FALSE,
            ),
            'userid' => array (
                'required' => TRUE,
            ),
            'name' => array (
                'required' => TRUE,
            )
        ),
        'edit' => array(
            'id' => array (
                'required' => TRUE,
            ),
            'name' => array (
                'required' => TRUE,
            )
        ),
        'del' => array(
            'id' => array (
                'required' => TRUE,
            )
        ),
        'get' => array(
            'id' => array (
                'required' => FALSE,
            )
        )
    );

    public function update() {
        $options = array(
            'where' => array(
                array('where', $this->config['id']['field'], '=', $this->config['id']['value']),
            )
        );
        return parent::update($options);
    }
    public function delete() {
        $options = array(
            'where' => array(
                array('where', $this->config['id']['field'], '=', $this->config['id']['value'])
            )
        );
        return parent::delete($options);
    }
    public function select() {
        $options = array();
        if (!empty($this->config['id']['value'])) {
            $options['where'][] = array('where', 'i.'.$this->config['id']['field'], '=', $this->config['id']['value']);
        }

        return parent::select($options);
    }
}

?>