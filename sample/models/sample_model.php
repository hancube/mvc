<?php

class SampleModel extends Model{
    public $table = 'Sample';

    public $schema = array(
        'id' => array (
            'field'     => 'Id',
            'where'     => WHERE_SELECT_UPDATE_DELETE,
            'pk'        => TRUE,
            'rules'     => array (
                'numeric'    => TRUE,
                'max_length' => 11
            )
        ),
        'sample' => array (
            'field'     => 'Sample',
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
            'sample' => array (
                'required' => TRUE,
            )
        ),
        'edit' => array(
            'id' => array (
                'required' => TRUE,
            ),
            'sample' => array (
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
