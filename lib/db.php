<?php
class DB extends PDO{
    public $engine;
    public $host; 
    public $port; 
    public $name; 
    public $user; 
    public $pass; 

    public function __construct(){ 
        $this->engine = 'mysql'; 
        $this->host = DB_HOST;
        $this->port = DB_PORT;
        $this->name = DB_NAME; 
        $this->user = DB_USER; 
        $this->pass = DB_PASS; 
        $dns = $this->engine.':dbname='.$this->name.";host=".$this->host.";port=".$this->port;
        try {
            parent::__construct($dns, $this->user, $this->pass);
        }catch (PDOException $e) {
            return false;
        }
    }
}
?>