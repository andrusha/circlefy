<?php

/*
    Module handling all network stuff
*/
class Curl {
    private $curl = null;

    public function __construct($cookie_file = null) {
        if ($cookie_file == null)
            $cookie_file = tempnam('', 'cookie');

        $this->curl = curl_init();
        curl_setopt_array($this->curl,
            array(
                CURLOPT_AUTOREFERER => 1,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_COOKIEFILE => $cookie_file,
                CURLOPT_COOKIEJAR => $cookie_file,
                ));
    }

    /*
        Implode assoc array in url format
        key1=val1&key2=val2&...
    */
    private function urlImplode(array $params = array()) {
        $str = '';
        foreach ($params as $key => $value)
            $str .= "&$key=".urlencode($value);
        return substr($str, 1);
    }

    /*
        Formats url with get params
    */
    private function makeUrl($url, array $gets) {
        if (!empty($gets)) {
            $getstr = $this->urlImplode($gets);
            if (strpos($url, '?') === false)
                $url .= "?$getstr";
            else
                $url .= "&$getstr";
        }

        return $url;
    }

    /*
        Make http get request to provideed url
        $gets params automatically adds to
    */
    public function get($url, array $gets = array()) {
        $url = $this->makeUrl($url, $gets);
    
        curl_setopt_array($this->curl,
            array(
                CURLOPT_URL => $url,
                CURLOPT_HTTPGET => 1));

        $result = curl_exec($this->curl);

        curl_setopt($tis->curl, CURLOPT_HTTPGET, 0);

        return $result;
    }

    /*
        Make http post request
        note, to post a file pass $posts a '@/full/path' string
    */
    public function post($url, $posts = array(), array $gets = array()) {
        $url = $this->makeUrl($url, $gets);
        //we may not encode $posts, since curl supports assoc arrays
        $post_data = $this->urlImplode($posts);

        curl_setopt_array($this->curl,
            array(
                CURLOPT_URL => $url,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $post_data));

        $result = curl_exec($this->curl);

        curl_setopt_array($this->curl,
            array(CURLOPT_POST => 0,
                  CURLOPT_POSTFIELDS => NULL));


        return $result;
    }

    /*
        Python-style request
    */
    public function open($url, $posts = array()) {
        if (!empty($posts))
            return $this->post($url, $posts);
        else
            return $this->get($url);
    }

    /*
        Downloads specified file
    */
    public function saveFile($url, $fname, array $gets = array()) {
        $url = $this->makeUrl($url, $gets);

        $file = fopen($fname, 'w');

        curl_setopt_array($this->curl,
            array(
                CURLOPT_FILE => $file,
                CURLOPT_BINARYTRANSFER => 1,
                CURLOPT_HTTPGET => 1
            ));

        curl_exec($this->curl);

        curl_setopt_array($this->curl,
            array(
                CURLOPT_FILE => NULL,
                CURLOPT_BINARYTRANSFER => 0,
                CURLOPT_HTTPGET => 0
            ));

        fclose($file);

        return true;
    }
};
