<?php
date_default_timezone_set('UTC');


// extensions check
if (!extension_loaded('curl'))
	die('"curl" extension not loaded or installed :( ');

// function for email notification
function sendEmailAlert($to, $subject, $message, $headers) {
   mail(
   $to,
   $subject,
   $message,
   $headers
   );
}

// function for simple TCP connection test
function tcp_check($host, $port, $connect_timeout=1) {
   $start = microtime(TRUE);

   if (preg_replace("/[^0-9a-z.-]/",'', $host) !== $host) {
      return "0,InvalidHost,0";
   }

   try {
      $fp = fsockopen($host, $port, $errno, $errstr, $connect_timeout);
      fclose($fp);
   } catch (Exception $e){
   }

   if(!empty($errstr))
      $conresult = "ConnectFail"; 
   else
      $conresult = "ConnectOK";

   $end = microtime(TRUE);
   $duration = intval( ($end - $start)*1000 );

   return "$duration,$conresult,$errstr";
}


// function for URL check using cURL
function url_check($url, $string='<html', $connect_timeout=5) {

   $start = microtime(TRUE);

   if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return "0,InvalidURL,0";
   }

   $c = curl_init();
   curl_setopt($c, CURLOPT_URL, $url);
   curl_setopt($c, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:39.0) Gecko/20100101 Firefox/39.0');
   curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
   curl_setopt($c, CURLOPT_TIMEOUT, $connect_timeout);
   curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
   curl_setopt($c, CURLOPT_SSL_VERIFYPEER, FALSE);
   curl_setopt($c, CURLOPT_FOLLOWLOCATION, FALSE);
   curl_setopt($c, CURLOPT_FORBID_REUSE, TRUE);
   curl_setopt($c, CURLOPT_FRESH_CONNECT, TRUE);
   curl_setopt($c, CURLOPT_HEADER, FALSE);
   curl_setopt($c, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP | CURLPROTO_SFTP);
   $data= curl_exec($c);
   curl_close($c); 

   if(!$data)
      $conresult = "ConnectFail"; 
   else
      $conresult = "ConnectOK";

   if (strpos($data, $string))
      $result="StringOK";
   else
      $result="StringNotFound";

   $end = microtime(TRUE);
   $duration = intval( ($end - $start)*1000 );

   return "$duration,$conresult,$result";
}

// ----  begin output processing ----

// initialize and fetch DB 
/*$db = 'db.json';
if (!file_exists('db.json'))
   touch('db.json');
$json = json_decode(file_get_contents($db)); */

// pdo mysql andrija1987 added
$pdo = new PDO("mysql:dbname=uptimer;host=localhost", "root", "Lokarda7!");
$statement = $pdo->prepare("SELECT * FROM servers ORDER BY id ASC");
$statement->execute();
$results = $statement->fetchAll(PDO::FETCH_ASSOC);
$jsondata = json_encode($results);
$json = json_decode($jsondata);
  

//  let's loop in the checks
$checkExecuted = FALSE;
foreach($json as $address) {
   if(( time() - $address->lastCheck > $address->checkInterval) or $address->lastResultConnection != 'ConnectOK' or $address->lastResultString == 'StringNotFound') { // execute if check interval expired OR if previous check found error
      if($address->type == 'url') {
         $result = url_check($address->address, $address->findString, $address->timeout);
      }
      else {
         $result = tcp_check($address->address, $address->findString, $address->timeout);
      }

      $result = explode(',', $result);
      $address->lastResultDuration = $result[0];
      $address->lastResultConnection = $result[1];
      $address->lastResultString = $result[2];
      $address->lastCheck = time();

      $checkExecuted = TRUE;
   }
}

// write out JSON data only if any checks have been executed
if($checkExecuted == TRUE)
   file_put_contents($db, json_encode($json,  JSON_PRETTY_PRINT), LOCK_EX);


// DEBUG: output DB array and die
//header('Content-type: text/plain');
//print_r($json); die;


// ============
//  HTML OUTPUT
$checkOutput='';
$refreshCookie='';
$stylefile='style.css';
$downTime = FALSE;
$textNotFound = FALSE;


// let's populate the table with the checks
foreach($json as $check) {
   if($check->lastResultConnection == 'ConnectOK')
      $lastResultConnection = '<b class="ok">✔</b>';
   else {
      $downTime = TRUE;
      sendEmailAlert(
         $check->emailNotification,
         'connection failed: '.$check->address,
         "Hello, I am ".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\r\nSorry to bother you, but I couldn't connect to ".$check->address.", it told me: ".$check->lastResultConnection.' with string '.$check->lastResultString,
         "From: php-uptime-monitor@".$_SERVER['SERVER_NAME']."\r\n"
      );
      $lastResultConnection = '<b class="err">✖ '.$check->lastResultConnection.'</b>';
   }
   if($check->lastResultString == 'StringOK')
      $lastResultString = '<b class="ok">✔</b>';
   else if (empty($check->lastResultString))
      $lastResultString = '·';
   else {
      $textNotFound = TRUE;
      sendEmailAlert(
         $check->emailNotification,
         'text not found: '.$check->address,
         "Hello, I am ".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\r\nSorry to bother you, but I couldn't find the text string \"".$check->findString."\" on page ".$check->address." :(",
         "From: php-uptime-monitor@".$_SERVER['SERVER_NAME']."\r\n"
      );
      $lastResultString = '<b class="err">✖'.'</b>';
   }

$checkOutput.='
<tr>
   <td>
    <b>·'.$check->type.'·</b> '.$check->address.' : '.$check->findString.'
   </td>
   <td>
   <progress value="'.((time() - $check->lastCheck)).'" max="'.$check->checkInterval.'"></progress> <abbr title="'.$check->lastCheck.' ('.date('r', $check->lastCheck).')">'.((time() - $check->lastCheck)).'s ago (interval: '.$check->checkInterval.'s)</abbr>  
   </td>
   <td>
   <b>'.$check->lastResultDuration.' ms</b>
   </td>
   <td>
   '.$lastResultConnection.'
   </td>
   <td>
   '.$lastResultString.'
   </td>

</tr>
';
}

// if there is a visitor cookie AND there is no downtime to notify, then let's refresh the page
if(isset($_COOKIE['refresh']) and $downTime== FALSE) {
 $refreshCookie='<meta http-equiv="refresh" content="'.(int)$_COOKIE['refresh'].'">';
}

// now for the actual HTML
print <<<EOT
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <title>uptimer</title>
    <link rel='stylesheet' href='{$stylefile}' type='text/css' media='screen' />
    <meta name="viewport" content="initial-scale=1.0, user-scalable=yes">
     {$refreshCookie}
    <meta name="robots" content="noindex" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container">
        <a class="navbar-brand" href="#">{uptimer}</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExample07" aria-controls="navbarsExample07" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarsExample07">
          <ul class="navbar-nav mr-auto">
            <li class="nav-item active">
              <a class="nav-link" href="index.php">Home <span class="sr-only">(current)</span></a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="vue/index.php">vue</a>
            </li>
            <!--<li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="https://example.com" id="dropdown07" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Dropdown</a>
              <div class="dropdown-menu" aria-labelledby="dropdown07">
                <a class="dropdown-item" href="#">Action</a>
                <a class="dropdown-item" href="#">Another action</a>
                <a class="dropdown-item" href="#">Something else here</a>
              </div>
            </li>-->
          </ul>
        </div>
      </div>
    </nav>
	<div class="highlight-clean">
        <div class="box container" id="main-container">
      <br> <div class="time" style="position: fixed;
    bottom: 50px;
    width: 100%;">
EOT;
// proceed with the rest of the page
$pageCall=date(DATE_ATOM, time());
      print <<<EOT
{$pageCall} &nbsp;

</div>

		<div class="buttons" style="text-align:center;"><button class="btn btn-primary" type="button" onclick="document.location.reload(true)">reload</button>
   <button class="btn btn-warning" onclick="document.cookie = 'refresh=10;expires=0;';location.reload(true);">reload every 10s</button>
</div>
         
         
            <div class="box-content container">
                <table class="table" style="margin-top:20px;">
 <thead>
  <tr>
     <th scope="col">address + port/string</th>

     <th scope="col">last check</th>
     <th scope="col">last response time</th>
     <th scope="col">last connect</th>
     <th scope="col">last text search</th>
  </tr>
 </thead>
                    <tbody>{$checkOutput}</tbody>
                </table>
            </div>
        </div>
</body>
</html>
EOT;

// ======= THAT'S ALL FOLKS ! Some data specs below:

/*
====== DATA FORMAT ======

   [address] => ftp://ftp.mozilla.org/
      → host name, or URL in HTTP, HTTPS, FTP or SFTP schema
   [type] => url
      → address type url | host
   [findString] => pub
      → port number (host) or text string in web page (HTTP/HTTPS) or file/folder name (FTP & SFTP)
   [timeout] => 3
      → time out delay in seconds
   [checkInterval] => 600
      → check interval in seconds
   [lastCheck] => 1437670605
      → timestamp of the last check
   [lastResultDuration] => 1650
      → last check duration (response time) in milliseconds
   [lastResultConnection] => ConnectOK
      → connection status of the last check {ConnectOK | ConnectFail} or {InvalidHost | InvalidURL} if input rejected by filter
   [lastResultString] => StringOK
      → finding of the text string in last check  {StringOK | StringNotFound | -empty-}


====== EXAMPLES to put in "db.json" =======

[
  {
    "address": "https://www.wikipedia.org/",
    "type": "url",
    "findString": "Wikibooks",
    "timeout": 3,
    "checkInterval": 600,
    "lastCheck": 1438185116,
    "lastResultDuration": "235",
    "lastResultConnection": "ConnectOK",
    "lastResultString": "StringOK",
    "emailNotification": "email@nowhere.local"
  },
  {
    "address": "www.google.com",
    "type": "host",
    "findString": "80",
    "timeout": 2,
    "checkInterval": 120,
    "lastCheck": 1438185126,
    "lastResultDuration": "286",
    "lastResultConnection": "ConnectOK",
    "lastResultString": "",
    "emailNotification": "email@nowhere.local"
  },
  {
    "address": "ftp://ftp.mozilla.org/",
    "type": "url",
    "findString": "pub",
    "timeout": 3,
    "checkInterval": 600,
    "lastCheck": 1438185064,
    "lastResultDuration": "1684",
    "lastResultConnection": "ConnectOK",
    "lastResultString": "StringOK",
    "emailNotification": "email@nowhere.local"
  }
]


*/

?>
