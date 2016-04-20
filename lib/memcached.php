<?php

namespace hc\mvc;

class Memcached {

    public $memcached;

    private $hosts;
    private $port;
    private $time;

    public function __construct($hosts, $port, $time) {
        Debug::ttt('Memcached::__construct()');

        $this->hosts = $hosts;
        $this->port = $port;
        $this->time = $time + time();
        $this->setMemcached();
        return true;
    }
    private function setMemcached() {
        Debug::ttt('Memcached::setMemcached()');
        $this->memcached = new \Memcached;
        Debug::eee('Memcache Hosts:', 'green');
        foreach($this->hosts as $host) {
            $this->memcached->addServer($host, $this->port);
            Debug::eee($host, 'green');
        }
    }
    public function set($key, $value) {
        Debug::ttt('Memcached::set()');

        $hash_key = $this->makeHashKey($key);
        Debug::ppp('Set Memcache Key: '.$hash_key.'('.$key.')', 'blue');
        Debug::eee('Set Memcache Value: ', 'blue');
        Debug::ppp($value, 'blue');

        return $this->memcached->set($hash_key, $value, $this->time);
    }
    public function get($key) {
        Debug::ttt('Memcached::get()');
        $hash_key = $this->makeHashKey($key);

        if (!($result = $this->memcached->get($hash_key))) {
            Debug::ppp('No Memcache Key: '.$hash_key.'('.$key.')', 'blue');
            return false;
        }else {
            Debug::ppp('Memcache Key: '.$hash_key.'('.$key.')', 'Fuchsia');
            Debug::ppp($result, 'Fuchsia');
            return $result;
        }
    }
    public function del($key) {
        Debug::ttt('Memcached::del($key)');
        $hash_key = $this->makeHashKey($key);
        return $this->memcached->delete($hash_key);
    }
    public function flush() {
        Debug::ttt('Memcached::flush()');
        return $this->memcached->flush();
    }
    private function makeHashKey($str) {
        return hash_hmac('ripemd160', $str, 'secret');
    }
}

?>