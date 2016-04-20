<?php

namespace hc\mvc;

trait DomainSecurity {
    var $use_whitelist;
    var $whitelist;

    public function setWhitelist($use_whitelist, $whitelist) {
        $this->use_whitelist = $use_whitelist;
        $this->whitelist = $whitelist;
        return true;
    }
    public function checkDomain() {
        if ($this->use_whitelist !== true) return true;
        if (!isset($this->whitelist) || empty($this->whitelist)) return false;
        if (!isset($_SERVER['HTTP_REFERER'])|| empty($_SERVER['HTTP_REFERER'])) return false;

        $HTTP_REFERER = $_SERVER['HTTP_REFERER'];
        $curr_domain = parse_url($HTTP_REFERER, PHP_URL_HOST);
        $arr_curr_domain = explode('.', $curr_domain);
        for ($i=0; $i<count($this->whitelist); $i++) {
            $arr_allowed_domain = explode('.', $this->whitelist[$i]);
            $check = 'pass';
            for ($ii=0; $ii<count($arr_allowed_domain) && $check != 'next'; $ii++) {
                $ci = count($arr_curr_domain)-1-$ii;
                $ai = count($arr_allowed_domain)-1-$ii;

                if (isset($arr_curr_domain[$ci])) {
                    if ($arr_curr_domain[$ci] != $arr_allowed_domain[$ai]) {
                        if ($arr_allowed_domain[$ai] == '*') {
                            // pass when it meets *
                            return true;
                        }else {
                            $check = 'next';
                        }
                    }
                }else {
                    $check = 'next';
                }
            }
            if ($check == 'pass') {
                // pass when it's not different
                return true;
            }
        }
        return false;
    }

}


trait AuthSecurity {
    var $use_auth_users;
    var $auth_users;
    var $is_auth_user = false;
    var $auth_user_name = '';

    public function setAuthUsers($use_auth_users, $auth_users) {
        $this->use_auth_users = $use_auth_users;
        $this->auth_users = $auth_users;
        return true;
    }
    public function checkAuthentication() {
        if ($this->use_auth_users !== true) return true;
        if (!isset($this->auth_users) || empty($this->auth_users)) return false;

        if (!is_array($this->auth_users)) return false;
        $credential = $this->getAuthCredential();
        if ($credential == false) return false;

        if (in_array($credential, $this->auth_users)) {
            $this->is_auth_user = true;
            $tmp = explode(':', $credential);
            $this->auth_user_name = $tmp[0];
            $this->setCorsHeader();
            return true;
        } else {
            return false;
        }
    }
    public function setCorsHeader() {
        header("Access-Control-Allow-Origin: */*");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: X-PINGOTHER");
        header("Access-Control-Max-Age: 1728000"); // 20 days
    }
    public function isAuthUser() {
        return $this->is_auth_user;
    }
    private function getAuthCredential() {
        // mod_php
        if (isset($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_USER'])
            && isset($_SERVER['PHP_AUTH_PW']) && !empty($_SERVER['PHP_AUTH_PW'])) {
            return $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'];
        }

        // most other servers
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic') === 0) {
                return base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6));
            }
        }

        return false;
    }
}

trait IPSecurity {
    var $use_allowed_ips;
    var $allowed_ips;

    public function setAllowedIPs($use_allowed_ips, $allowed_ips) {
        $this->use_allowed_ips = $use_allowed_ips;
        $this->allowed_ips = $allowed_ips;
        return true;
    }
    public static function getClientIP() {
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = false;

        $arr_ipaddress = explode(',', $ipaddress);
        $ipaddress = trim($arr_ipaddress[0]);

        return $ipaddress;
    }

    public function checkIP() {
        if ($this->use_allowed_ips === TRUE) {
            $user_ip = $this->getClientIP();
            $ip_ok = false;
            if ($user_ip !== false) {
                $arr_ip_class = explode('.', $user_ip);
                for ($i=0; $i < count($this->allowed_ips); $i++) {
                    $arr_allowed_ip_class = explode('.', $this->allowed_ips[$i]);
                    if (($arr_ip_class[0] == $arr_allowed_ip_class[0] || $arr_allowed_ip_class[0] == '*')
                     && ($arr_ip_class[1] == $arr_allowed_ip_class[1] || $arr_allowed_ip_class[1] == '*')
                     && ($arr_ip_class[2] == $arr_allowed_ip_class[2] || $arr_allowed_ip_class[2] == '*')
                     && ($arr_ip_class[3] == $arr_allowed_ip_class[3] || $arr_allowed_ip_class[3] == '*')) {
                        $ip_ok = true;
                        break;
                    }
                }
            }
        }

        return $ip_ok;
    }
}

class Security {
    use DomainSecurity;
    use IPSecurity;
    use AuthSecurity;

    public function checkSecurity() {

        $result = false;

        // 1. Checking Domain
        if ($result === false) {
            $result = $this->checkDomain();
        }

        // 2. Checking IP Range
        if ($result === false) {
            $result = $this->checkIP();
        }

        // 3. Checking Authentication
        if ($result === false) {
            $result = $this->checkAuthentication();
        }

        return $result;

    }
}
?>