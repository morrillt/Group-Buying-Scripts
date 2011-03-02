<p>#!/usr/bin/php -q 

<?php
error_reporting(0);
set_time_limit(0);  // funcion utilizada para que pasados 60msegundos no se termine el proceso, sino hasta que cumpla las condiciones del ciclo

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



$local_deals_base = 'http://spotlight.opentable.com/deal';

$cities = array('atlanta', 'boston', 'chicago', 'denver','los-angeles', 'new-york', 'philadelphia', 'san-francisco');

$citiespage = array('atlanta', 'boston', 'chicago', 'denver','losangeles', 'newyork', 'philadelphia', 'sanfrancisco');
$datadate = date('Y-m-d');

//Need to check each url every run
$flag=0; //parametro que terminara el ciclo



foreach($cities as $city){
$x = 0;
$flag=0;
$page = array_shift($citiespage);
  while($x<10000000000){
    $url = $local_deals_base.'/'.$city.'/'.$page.$x;
    $deal_id = $city.$x;
    $x++;
    $deal_html = file_get_contents($url);
    
   if($http_response_header[0]!="HTTP/1.1 200 OK")
		{
		$flag++;
		echo $flag;
		if ($flag>100)// aqui verifco si hay mas de 50m registros vacion le digo al script que 		termine
		{
			break;
		}
		continue;
		}
		echo $x-1;
  $flag=0;
  /* if($http_response_header[0]!="HTTP/1.1 200 OK")
      continue;*/
    echo "Good deal...\n";
    $deal = new simple_html_dom();
    $deal->load($deal_html);
    
    if(strpos($deal_html, "Alert Me When Live"))
      continue;
      
    $bought = $deal->find('[class=peoplePurchasedValue]');
    $count = $bought[0]->innertext;
  
    $time = $deal->find('div[class=gbStatusLabel]');
    $timetext = $time[0]->plaintext;
    $datetime = explode('&nbsp;', $timetext);
    $datetext = date('Y-m-d',strtotime($datetime[3]));
      
    $price = $deal->find('div[class=detailsPageDealInfoPrice]');
    $pricetext = $price[0]->plaintext;
    $pricetext = explode('&', $pricetext);
    $pricetext = explode("$", $pricetext[0]);
    $pricetext = $pricetext[1];
   
    $value = $deal->find('span[class=origPriceValue]');
    $valuetext = $value[0]->plaintext;
    $valuetext = trim($valuetext, "$");
    
    $title = $deal->find('div[id=content] h1');
    $titletext = $title[0]->plaintext;
    $titletext = explode('of', $titletext);
    $title = array_pop($titletext);
    $status = 0;
    
    $location = $deal->find('div[class=locationAddress]');
    $location = explode("\n", $location[0]->innertext);
        
    if(strlen($location[1])<2){
      $location = urlencode(trim($location[0]));
    }else{
      $location = urlencode(trim($location[1]));
    }
    
    $yahoo = file_get_contents('http://where.yahooapis.com/geocode?appid=N5PlXD36&q='.$location.'&flags=J');
    $yahoo = json_decode($yahoo);


    $location = (string)$yahoo->ResultSet->Results[0]->uzip;
        

     
    //$query = "INSERT INTO opentable(id, title, cost, value, total, date, location, datadate, status) VALUES(?,?,?,?,?,?,?,?,?)";
    $query = "INSERT INTO opentable(deal_id, title, pricetext, valuetext, count, datetext, location, datadate, status, time) VALUES(?,?,?,?,?,?,?,?,?,NOW())";
    $qth = $dbh->prepare($query);
    $qth->execute($deal_id, $title, $pricetext, $valuetext, $count, $datetext, $location, $datadate, $status);

  }
}
