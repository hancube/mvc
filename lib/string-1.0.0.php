<?php
/**
 * @author HanCube.com
 */
class String {
    public static function HtmlToTxt($html){
        if (is_array($html)) return $html;
        $html = str_replace('<', '&lt;', $html);
        $html = str_replace('>', '&gt;', $html);
        /*$html = str_replace('"', '&quot;', $html);*/
        /*$html = str_replace("'", '&apos;', $html);*/
        $text = stripslashes($html);
        return $text;
    }
    public static function SpaceForWeb($text){
        $text = str_replace(' ', '&nbsp;', $text);
        $text = preg_replace('/\t/', '&nbsp;&nbsp;&nbsp;&nbsp;', $text);
        $html = preg_replace('/\n/', '<br>', $text);
        return $html;
    }
    public static function showHTMLCode($html) {
        return String::SpaceForWeb(String::HtmlToTxt($html));
    }
    public static function ArrayToXML($array) {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><root></root>");
        String::ArrayToXMLAddChild($array, $xml);
        return $xml->asXML();
    }
    public static function ArrayToXMLAddChild($array, & $xml) {
        if (!is_array($array)) return false;

        foreach($array as $key => $value) {
            if(is_array($value)) {
                if(is_numeric($key)) $key = 'item';
                $subnode = $xml->addChild("$key");
                String::ArrayToXMLAddChild($value, $subnode);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }
    public static function FilterInputData($str) {
        return $str;
    }

}

?>
