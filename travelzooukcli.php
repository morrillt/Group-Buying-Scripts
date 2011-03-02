#!/usr/bin/php -q 
<?php
set_time_limit(0);  // funcion utilizada para que pasados 60msegundos no se termine el proceso, sino hasta que cumpla las condiciones del ciclo

//This script is a version modified by Alexander Sanz

// DB conector set up to AWS

define('DBHOST' , 'localhost');
define('DBUSER' , 'root');
define('DBPASS' , '');
define('DATABASE',  'htmlParser');

require_once "simple_html_dom.php";
require_once "dbi.php";

$dbh = DBI::connect('mysql', array('host'=>DBHOST,
                                   'user'=>DBUSER,
                               'password'=>DBPASS,
                               'database'=>DATABASE,
                               'persistent'=>FALSE));
                               
if (!$dbh) {
  die("Database connection error\n");
}

//Url parser from deal 1 to last deal


$local_deals_base = 'http://www.travelzoo.com/uk/local-deals/deal/';

//Need to check each url every run
$flag=0; //parametro que terminara el ciclo
$x = 300; // inciamos la variable que nos representara los deals, siemore debe ser 300 para uk primer dato regisrado 356
$datadate = date('Y-m-d');

while( $x < 10000000000) {  //aqui ponemos el limite superior hasta donde queremos que el script se ejecute


  $url = $local_deals_base.$x; // adicionamos ala Url base el deal
  $deal_id = $x; // actualizamos deal
  $x++;			// invrementamos deal
  $deal_html = file_get_contents($url); //obtenemos datos de la url
  if($http_response_header[0]!="HTTP/1.0 200 OK")
		{
		$flag++;
		echo $flag;
		if ($flag>200) // aqui verifco si hay mas de 50m registros vacion le digo al script que termine
		{
			break;
		}
		continue;
		}

  echo $x-1;
  $flag=0;
  echo "Good deal...\n";
  echo "</br></br>";
  $deal = new simple_html_dom();
  $deal->load($deal_html);
    
  $bought = $deal->find('span[id=ctl00_Main_LabelBought]');
  $bought = $bought[0];
  
  if(is_object($bought)){
    $bought = $bought->innertext;
  }else{
    echo $deal_id." has unknown purchasers\n";
  }
  
  $bought = explode("\n", $bought);
  $number = explode(' ', $bought[0]);
  $count = $number[0];
  
  $time = $deal->find('span[class=soldOutSecondLine]');
  $time = $time[0];
  
  if(is_object($time)){
    $timetext = $time->plaintext;
  }else{
      echo $deal_id." has unknown time\n"; //Error corrected by sanz
  }
  
  $datetime = explode(' ', $timetext);
  $datetext = date('Y-m-d',strtotime($datetime[7]." ".$datetime[8]));
    
  $price = $deal->find('span[id=ctl00_Main_OurPrice]');
  $pricetext = $price[0]->plaintext;
  $pricetext = trim($pricetext, "$");
 
  $value = $deal->find('span[id=ctl00_Main_PriceValue]');
  $valuetext = $value[0]->plaintext;
  $valuetext = trim($valuetext, "$");
  
  $title = $deal->find('span[id=ctl00_Main_LabelDealTitle]');
  $titletext = $title[0]->plaintext;
  $titletext = explode('--', $titletext);
  $title = array_pop($titletext);
  
  $timeLeft = array_shift($deal->find('span[id=ctl00_Main_LabelTimeLeft]'));
  $capReached = array_shift($deal->find('span[class=capReachedSecondLine]'));
  $soldOut = array_shift($deal->find('span[class=soldOutSecondLine]'));
  
  
  if(is_object($timeLeft)){
    $timeLeft = $timeLeft->plaintext; 
  }
  
  if(is_object($soldOut)){
    $soldOut = $soldOut->plaintext;
  }
    
  if(isset($timeLeft) && !is_object($timeLeft)){
    echo "deal ".$deal_id." is open\n"; //Error corrected by sanz
    $status =1;
  }
  
  if(isset($soldOut)  && !is_object($soldOut)){
     echo "deal ".$deal_id." is closed\n"; //Error corrected by sanz
     $status = 0;
  }
  
  if(!isset($soldOut) && !isset($timeLeft)){
    echo "deal ".$deal_id." is indeterminate\n"; //Error corrected by sanz
    $status = 0;

  }
  
  $location = $deal->find('div[class=smallMap] div p');
  
  if(!is_object($location[1])){
    $location = $location[0]->innertext;
  }else{
    $location = $location[1]->innertext;
  }
  $location = explode('<br>', $location);
  $location = implode(' ', $location);
  $location = urlencode($location);
  

  $yahoo = file_get_contents('http://where.yahooapis.com/geocode?appid=N5PlXD36&q='.$location.'&flags=J');
  $yahoo = json_decode($yahoo);
  
  
  $location = (string)$yahoo->ResultSet->Results[0]->uzip;


  $query = "INSERT INTO travelzoouk(deal_id, title, pricetext, valuetext, count, datetext, location, datadate, status, time) VALUES(?,?,?,?,?,?,?,?,?, NOW())";
  $qth = $dbh->prepare($query);
  $qth->execute($deal_id, $title, $pricetext, $valuetext, $count, $datetext, $location, $datadate, $status);

}
