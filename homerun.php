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

//if (!$dbh) { die("Database connection error\n"); }

$cities_base = 'http://www.homerun.com/';

$chtml = file_get_contents($cities_base);
$home = new simple_html_dom();
$home->load($chtml);

$subPages = array (
  'daily-steal',
  'city-sampler',
  'private-reserve',
  'hot-minute'
);
$citiesUrls = array();
$cities = $home->find('.region-picker .vertical-list a');
foreach($cities as $city) $citiesUrls[] = $city->getAttribute('href');

$RESULTDEALS = array();
foreach($citiesUrls as $cityUrl)
{
  $scrape_base = $cities_base.$cityUrl;
  foreach($subPages as $p) {
    $html = file_get_contents($scrape_base.$p);
    $dom = new simple_html_dom();
    $dom->load($html);
    switch($p) {
      case 'private-reserve':
      case 'hot-minute':
      case 'city-sampler':
      case 'daily-steal':
	$deals = $dom->find('.wrapper > .daily-deal');
	foreach($deals as $d)
	{
	  $off = $d->find('h2 a');
	  $off = $off[0];

	  $dealUrl = $off->getAttribute("href");
	  $dPHTML = file_get_contents($cities_base.$dealUrl);
	  $dpDom = new simple_html_dom();
	  $dpDom->load($dPHTML);

	  $subsecs = $dpDom->find('.subsection .val');
	  $price1 = str_replace('$','',$subsecs[0]->text());
	  $price2 = str_replace('$','',$subsecs[2]->text());

	  $res = array();
	  $section = $dpDom->find('.content-box .section');
	  preg_match_all("#(\d+)\s?(bought|sold)#", strip_tags(str_replace("\n", '', $dPHTML)), $res);
	  $d = $res[1][0];
	  
	  $active = !(strpos($dPHTML, 'Ended') > 0);
	  $RESULTDEAL = array(
	    'title' => current($dpDom->find('.content .title'))->text(),
	    'urlOfDeal' => $dealUrl,
	    'siteBaseUrl' => 'http://homerun.com',
	    'discoutPrice' => $price1-$price2,
	    'couponCount' => $d,
	    'statusIsActive' => $active,
	    'country' => 'US',
	    'retailPrice' => $price2,
	    'timestamp' => time(),
          );
	  foreach($RESULTDEAL as $k=>&$v) {
	    $v = str_replace("\n","",$v);
	  }
	  $RESULTDEALS[] = $RESULTDEAL;
	}
	break;

#title   /*usually found in header tags at the top of the deal page */
#urlOfDeal  /*ie http://homerun.com/deal/usa-him-istry-2 */
#siteBaseUrl /*ie http://homerun.com */
#discountPrice /* this is the price of the coupon */ 
#couponCount /*How many coupons have been sold */
#statusIsActive /* If the deal is active status == 1, if not status == 1 */#

#SCRAPING OPTIONAL#

#timeDateOfEndTimeOfDeal_univeralTime
#location.country   /* IE USA */
#location.city       /*Ie Boston */
#location.zip       /*zip code like 80302 for the united states */
#statusIsTipped   /*only applicable for some sites like groupon */
#retailPrice       /* if a $100 dollar sweater is selling for $50 -> 100 is the retail price */
	break;
    }
  }

}

  die(print_r($RESULTDEALS));

//  $query = "INSERT INTO travelzoo(deal_id, title, pricetext, valuetext, count, datetext, location, datadate, status, time) VALUES(?,?,?,?,?,?,?,?,?, NOW())";
//  $qth = $dbh->prepare($query);
//  $qth->execute($deal_id, $title, $pricetext, $valuetext, $count, $datetext, $location, $datadate, $status);


