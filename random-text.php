<?php

ini_set('max_execution_time', '1700');
set_time_limit(1700);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

function send_bearer($url, $token, $type = "GET", $param = []){
    $descriptor = curl_init($url);
     curl_setopt($descriptor, CURLOPT_POSTFIELDS, json_encode($param));
     curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($descriptor, CURLOPT_HTTPHEADER, array("User-Agent: M-Soft Integration", "Content-Type: application/json", "Authorization: Bearer ".$token)); 
     curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $type);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}
if( !function_exists('mb_str_split')){
    function mb_str_split(  $string = '', $length = 1){
        if(!empty($string)){
            $split = array();
            $mb_strlen = mb_strlen($string);
            for($pi = 0; $pi < $mb_strlen; $pi += $length){
                $substr = mb_substr($string, $pi,$length);
                if( !empty($substr)){
                    $split[] = $substr;
                }
            }
        }
        return $split;
    }
}

// Перевірка вхідних даних
$input = json_decode(file_get_contents("php://input"), true);
if ($input["userId"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'userId' is missing";
}
if ($input["file"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'file' is missing";
} else if (file_exists($input["file"]) != true) {
    $result["state"] = false;
    $result["error"]["message"][] = "'file' not found";
}
if($input["mode"] == "send" && $input["token"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "in the 'send' mode, 'token' is missing";
}
if ($result["state"] === false) {
    echo json_encode($result);
    exit;
}

// Підготовка даних
if (file_exists("collection") != true) {
    mkdir("collection");
}
if ($input["global"]) {
    $filename = "collection/global-".$input["file"];
} else {
    $filename = "collection/".$input["userId"]."-".$input["file"];
}
if (file_exists($filename) && !$input["clear"]) {
    $result["load"] = "old";
    $thisFile = json_decode(file_get_contents($filename), true);
    unlink($filename);
} else {
    $result["load"] = "new";
    $getFile = file_get_contents($input["file"]);
    $parseFile = json_decode($getFile, true);
    if ($parseFile == NULL) {
        $explodeFile = explode("\r\n--\r\n", $getFile);
        foreach($explodeFile as $string) {
            $thisFile[] = ["type"=>"text", "content"=>$string];
        }
    } else {
        $thisFile = $parseFile;
    }
}
shuffle($thisFile);
$thisElem = $thisFile[0];
unset($thisFile[0]);
$thisFile = array_values($thisFile);
if (count($thisFile) >= 1) {
    file_put_contents($filename, json_encode($thisFile, JSON_UNESCAPED_UNICODE));
}

// Відправка
if ($input["mode"] == "send") {
    $thisElem["watermark"] = 1;
    $result["send"] = json_decode(send_bearer("https://api.smartsender.com/v1/contacts/".$input["userId"]."/send", $input["token"], "POST", $thisElem), true);
}

// Генерація відповіді
if ($thisElem["type"] == "text") {
    $result["random"]["content"] = mb_str_split($thisElem["content"], 250);
} else {
    $result["random"] = $thisElem;
    if ($thisElem["caption"] != NULL) {
        $result["random"]["content"] = mb_str_split($thisElem["caption"], 250);
    }
}
$result["random"]["total"] = count($result["random"]["content"]);

echo json_encode($result, JSON_UNESCAPED_UNICODE);