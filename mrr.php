<?php
/**
 * Created by IntelliJ IDEA.
 * User: svet
 * Date: 22.02.18
 * Time: 16:03
 */

require_once 'mrr_config.php';
class mrr {
    //Root URI for the api
    public $root_uri = "https://www.miningrigrentals.com/api/v2";

    public $decode = true;
    public $pretty = false;
    public $print = false;


    private $key;
    private $secret;

    function __construct($key,$secret) {
        //define the api_key and api_secret on construct
        $this->key = $key;
        $this->secret = $secret;
    }

    //Raw query function -- includes signing the request
    function query($type,$endpoint,$parms=array()) {
        $ch = curl_init();

        $rest = "";
        //if there is any url params, remove it for the signature
        if(strpos($endpoint,"?")!==false){
            $arr = explode("?",$endpoint);
            $endpoint = $arr[0];
            $rest = "?".$arr[1];
        }

        //URI is our root_uri + the endpoint
        $uri = $this->root_uri.$endpoint.$rest;

        switch($type) {
            case 'GET':
                $parms = json_encode($parms);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
                break;
            case 'POST':
                $parms = json_encode($parms);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
                break;
            case 'DELETE':
            case 'HEAD':
            case 'PUT':
                $parms = json_encode($parms);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
        }

        if($this->pretty) {
            if(strpos($uri,"?") == false) $uri.='?pretty';
            else $uri.="&pretty";
        }

        //Get an incrementing/uinque nonce
        $mtime = explode('.',microtime(true));
        $mtime[1] = str_pad($mtime[1], 4, 0, STR_PAD_RIGHT);
        $nonce = implode('',$mtime);

        //String to sign is api_key + nonce + endpoint
        $sign_string = $this->key.$nonce.$endpoint;

        //Sign the string using a sha1 hmac
        $sign = hash_hmac("sha1", $sign_string, $this->secret);

        //Headers to include our key, signature and nonce
        $headers = array(
            'Content-Type: application/json',
            'x-api-key: '.$this->key,
            'x-api-sign: '.$sign,
            'x-api-nonce: '.$nonce,
        );

        //Curl request
        curl_setopt($ch, CURLOPT_URL,$uri);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MRR API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');


        $response  = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        //echo $parms."\n";
        if($this->print) {
            echo "$type $uri\n";
        }
        return array(
            'status'=>$info['http_code'],
            'header'=>trim(substr($response,0,$info['header_size'])),
            'data'=>substr($response,$info['header_size'])
        );
    }
    function parseReturn($array) {
        $data = array();
        if($array["status"] != 200) {
            $data = $array;
        }else {
            if($this->decode)
                $data = json_decode($array["data"],true);
            else
                $data = $array["data"];
        }
        if($this->print) {
            echo "\nReturned Data::\n";
            if(is_array($data))
                print_r($data);
            else
                echo $data;
            echo "\n";
        } else {
            return $data;
        }
    }
    //helper aliases just to make things easier
    function get($endpoint,$parms=array()) {
        return $this->parseReturn($this->query("GET",$endpoint,$parms));
    }
    function post($endpoint,$parms=array()) {
        return $this->parseReturn($this->query("POST",$endpoint,$parms));
    }
    function put($endpoint,$parms=array()) {
        return $this->parseReturn($this->query("PUT",$endpoint,$parms));
    }
    function delete($endpoint,$parms=array()) {
        return $this->parseReturn($this->query("DELETE",$endpoint,$parms));
    }
}

if (php_sapi_name() != "cli") {
    die;
}
$shortopts  = "";

$longopts  = array(
    "algo:",     // Required value
    "currency:",
    "rigID:",
    "updatePrice:",
    "modifier::",
);
$options = getopt($shortopts, $longopts);
foreach ($options as $var => $option) {
    $$var = $option;
}

$mrr = new mrr($key,$secret);
$mrr->decode=true;
$mrr->pretty=false;
$mrr->print=false;

$modifier = isset($modifier) ? $modifier : 0;
$currency = isset($currency) ? $currency : 'LTC';
$algo = isset($algo) ? $algo : 'scrypt';
$rigID = isset($rigID) ? $rigID : 12345;
$updatePrice = isset($updatePrice) ? $updatePrice : false;

$algoInfo = $mrr->get("/info/algos/$algo", ['currency' => $currency]);
$suggestedPrice = 0;
if (isset($algoInfo['success']) and $algoInfo['success'] == true) {
    if (!empty($data=$algoInfo['data']) and !empty($suggestedPrice=$data['suggested_price'])) {
        echo 'Suggested price from MRR: ' . $suggestedPrice['amount'] . PHP_EOL;
        $suggestedPrice = $suggestedPrice['amount'] * (1+$modifier);
    }
}

$currentPrice = 0;
if ($suggestedPrice) {
    $l3 = $mrr->get("/rig/$rigID");
    if ($l3 and !empty($status['success']) and !empty(($data=$l3['data']) and !empty($price=$data['price']) and !empty($currencyPrice=$price[$currency])))
        $currentPrice = !empty($currencyPrice['price']) ? $currencyPrice['price'] : 0;

    $params = array(
//        "hash"=>array(
//            "hash"=>50,
//            "type"=>"gh",
//        ),
//        "type"=>"scrypt",
//        "server"=>"us-central01.miningrigrentals.com",
        "price"=>array(
//            "type"=>"gh",
            $currency=>array(
                "price"=>$suggestedPrice,
            )
        ),
//        "minhours"=>4,
//        "maxhours"=>24,
//        "status"=>"disabled"
    );

    if ($updatePrice) {
        try {
            $status = $mrr->put("/rig/$rigID", $params);
            if (!empty($status['success']))
                echo 'Update price: ' . $suggestedPrice . PHP_EOL;
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

}
