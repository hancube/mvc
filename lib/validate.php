<?php
/**
 * @author Sungok Lim
 */

class Validate {
    
    public static function check($rule, $rule_value, $value) {
        $return = array('result' => TRUE, 'error' => '', 'value' => '');

        $value = trim($value);
        
        if (!isset($rule) || empty($rule)) return $return;
        if (!isset($value) || empty($value)) return $return;
        
        switch ($rule) {
            /* Transform */
            case 'uppercase':
                if ($rule_value) {
                    $return['value'] = strtoupper($value);
                    continue;
                }
                break;
            case 'lowercase':
                if ($rule_value) {
                    $return['value'] = strtolower($value);
                    continue;
                }
                break;

            /* Rules */
            case 'numeric':
                if (strtoupper($value) == 'NULL') {
                    // NULL Pass
                }else if (isset($value) && !empty($value) && !is_numeric($value)) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_NUMERIC';
                }
                break;
            case 'email':
                if(!filter_var($value, FILTER_VALIDATE_EMAIL)){
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_EMAIL_FORMAT';
                }else {
                    $arr_email = explode('@', $value);
                    $domain = $arr_email[1];
                    if(!checkdnsrr($domain,'MX')) {
                        $return['result'] = FALSE;
                        $return['error'] = 'ERROR_EMAIL_DNS';
                    }
                }
                break;
            case 'length':
                if (strlen($value) != $rule_value) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_LENGTH_'.$rule_value;
                }
                break;
            case 'max_length':
                if (strlen($value) > $rule_value) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_MAX_LENGTH';
                }
                break;
            case 'min_length':
                if (strlen($value) > $rule_value) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_MIN_LENGTH';
                }
                break;
            case 'rgb_color':
                if(!preg_match("/^([A-Fa-f0-9]{6})$/", $value)) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_RGB_COLOR';
                }
                break;
            case 'enum':
                if (!in_array($value, $rule_value)) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_NOT_ALLOWED_VALUE';
                }
                break;
            case 'url':
                if (filter_var($value, FILTER_VALIDATE_URL) === FALSE) {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_URL_FORMAT';
                }
                break;
            case 'date': /* YYYY-MM-DD */
                if (preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $value, $matches)) {
                    $yyyy = $matches[1];
                    $mm = $matches[2];
                    $dd = $matches[3];

                    if (!checkdate($mm, $dd, $yyyy)) {
                        $return['result'] = FALSE;
                        $return['error'] = 'ERROR_DATE_FORMAT';
                    }
                }else {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_DATE_FORMAT';
                }
                break;
            case 'date_mdy': /* MM-DD-YY */
                if (preg_match("/^(\d{2})-(\d{2})-(\d{2})$/", $value, $matches)) {
                    $mm = $matches[1];
                    $dd = $matches[2];
                    $yy = $matches[3];

                    $curr_yy = date('y');
                    $curr_yyyy = date('Y');
                    $curr_century = $curr_yyyy - $curr_yy;
                    if ($yy <= $curr_yy) {
                        $his_century = $curr_century;
                    }else {
                        $his_century = $curr_century - 100;
                    }
                    $yyyy = $his_century + $yy;

                    if (!checkdate($mm, $dd, $yyyy)) {
                        $return['result'] = FALSE;
                        $return['error'] = 'ERROR_DATE_FORMAT_MDY';
                    }else {
                        $return['value'] = $yyyy.'-'.$mm.'-'.$dd;
                    }
                }else {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_DATE_FORMAT_MDY';
                }
                break;
            case 'datetime': /* YYYY-MM-DD HH:MI:SS */
                if (preg_match("/^(\d{4})-(\d{2})-(\d{2}) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/", $value, $matches)) {
                    if (!checkdate($matches[2], $matches[3], $matches[1])) {
                        $return['result'] = FALSE;
                        $return['error'] = 'ERROR_DATE_FORMAT';
                    }
                }else {
                    $return['result'] = FALSE;
                    $return['error'] = 'ERROR_DATETIME_FORMAT';
                }
                break;
        }
        return $return;
    }
}
?>