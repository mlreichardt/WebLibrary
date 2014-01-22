<?php
// VERSION: 1 (16/10/2011)

if(!defined("SHOP_LAYER")) {
	define("SHOP_LAYER","shop");
	 
	class shopConfig {
		var $action;
		var $shipping;
		var $auction;
		
		function shopConfig() {
			global $cfg;
			
			$this->action = array(
			//	'discount' => 0.10,
			//	'description' => "Openingskorting - KlokkieBigBen"
			);
		
			$this->shipping = array(
				1 => array('price' => 0.00, 'description' => "Post-NL:Normaal",'limit' => 0.00, 'fullprice' => 0.50),
				2 => array('price' => 3.95, 'description' => "Post-NL:Aangetekend", 'limit' => 50.00, 'fullprice' => 6.95),
				3 => array('price' => 6.50, 'description' => "Post-NL:Verzekerd aangetekend", 'limit' => 100.00, 'fullprice' => 12.95)
			);
			
			$this->auction = array(
				'open'	=> (19*60*60), //19:00
				'close'	=> (21*60*60), //21:00
				'day'	=> array(
					0 => true, //Zo
					1 => true, //Ma
					2 => true, //D1
					3 => true, //Wo
					4 => false, //Do
					5 => true, //Vr
					6 => true  //Za
				),
				'pricechange' => array ( 
					'secs'		=> 120,
					'amount'	=> 10.00
				)
			);
		}
		
		function o2a() {
		
			$_arr = array(
				'action' 	=>	$this->action,
				'shipping' 	=>	$this->shipping
			);
			
			return $_arr;
		}
	}
	
	class shopAuction {
		var $customer;
		var $now;
		var $starttime;
		var $closetime;
		//var $changetime;
		var $counter;
		var $interval;
		var $state;
		var $artlist;
	
		function shopAuction() {
			global $cfg, $clnt, $shopcfg;

			if(!is_object($shopcfg)) {
				$shopcfg = new shopConfig();
			}

			$this->now = time();
			if(date_default_timezone_get() == 'UTC') {
				$this->now += 3600;
			}

			$lt = localtime($this->now);
			$today = $this->now - (((($lt[2] * 60) + $lt[1]) * 60) + $lt[0]);
			
			$start_day = $today;
			$dow = $lt[6];
			for($i=0;$i<7;$i++) {
				if($shopcfg->auction['day'][$dow]) {
					break;
				} else {
					$start_day += 24 * 60 * 60;
				}
				$dow++;
				if($dow > 6) {
					$dow = 0;
				}
			}
	
			$this->starttime = $start_day + $shopcfg->auction['open'];
			$this->closetime = $start_day + $shopcfg->auction['close'];
			$this->interval = intval($shopcfg->auction['pricechange']['secs']);
			//$this->changetime = $this->starttime + $this->interval;
			//$this->counter = $this->changetime - $this->now;
			$this->counter = $this->starttime - $this->now;
			
			if($this->now < $this->starttime) {
				$this->state = 0; // Today
				if($this->counter > $this->interval) {
					$this->counter = $this->interval / 2;
				}
			} else {
				if($this->now > $this->closetime ) {
					$this->state = 2; // Next day
					$this->starttime += (24 * 60 * 60);
					$this->closetime += (24 * 60 * 60);
					//$this->changetime += (24 * 60 * 60);
					$this->counter = 60; //$this->interval / 2;
				} else {
					$this->state = 1; // Now
					if($this->interval > 0) {
						//$this->changetime = (intval($this->now / $this->interval) * $this->interval) + $this->interval;
						//$this->counter = $this->changetime - $this->now;
						$this->counter = $this->interval;
					}
					
				}
			}
			
			if($this->state == 1) {
				$this->update($this->now);
			}
			
			$this->customer = new shopCustomer($clnt->ct);
			
			$this->artlist = array();
						
			//if($this->customer->id > 0) {
				$this->load();
			//}	
		}
		
		function load() {
			global $shopcfg, $clnt, $db;
			
			$sql = "SELECT * FROM shoparticle WHERE arttype=2 AND artstatus>=2 AND  artstatus<=3 ORDER BY artstatus, id";
			if($result = $db->sql_query($sql)) {
				while ($row = $db->sql_fetchrow($result)) {
					$art = new shopArticle(0);
					$art->load($row);
					array_push($this->artlist,$art);
				}
			}			
		}
		
		function o2a($all = 1) {
		
			$art_list = array();
			if($all) {
				foreach($this->artlist as $art) {
					array_push($art_list,$art->o2a());
				}
			}
		
			$_arr = array(
				'state' 		=>	$this->state,
				'rtc' 			=>	time(),
				'starttime' 	=>	$this->starttime,
				'closetime' 	=>	$this->closetime,
				'changetime' 	=>	$this->changetime,
				'interval' 		=>	$this->interval,
				'counter' 		=>	$this->counter,
				'artlist'		=>	$art_list
			);
			
			return $_arr;
		}
		
		function update($now) {
			global $shopcfg, $clnt, $db;
			
			if($this->state == 1) { 

				$sql = "SELECT * FROM shoparticle WHERE arttype=2 AND artstatus=2 ORDER BY id";
				if($result = $db->sql_query($sql)) {
					while ($row = $db->sql_fetchrow($result)) {
						$art = new shopArticle(0);
						$art->load($row);
						
						if($art->pricechanged < $this->starttime) {
						
							$new_price = $art->price->val - $shopcfg->auction['pricechange']['amount'];
							if($new_price > 0) {
								$sql = "UPDATE shoparticle SET price=" . $new_price . " ,pricechanged=" . $this->starttime . " ,updated=" . time() . " WHERE id=" . $art->id;
								$res = $db->sql_query($sql);
								if($res) {
									$art->pricechanged = $this->starttime;
								}
							}
						}
/*						
						$x = $now - $art->pricechanged;
						$n = intval($x / $shopcfg->auction['pricechange']['secs']);
						if($n > 0) {
							$new_price = $art->price->val - ($n * $shopcfg->auction['pricechange']['amount']);
							if($new_price > 0) {
								$change = $art->pricechanged + ($n * $shopcfg->auction['pricechange']['secs']);
								$sql = "UPDATE shoparticle SET price=" . $new_price . ",pricechanged=" . $change . " ,updated=" . time() . " WHERE id=" . $art->id . " AND pricechanged=" . $art->pricechanged;
								$res = $db->sql_query($sql);
								if($res) {
								} else {
									var_dump($sql);
								}
							} else {
								$change = $art->pricechanged + ($n * $shopcfg->auction['pricechange']['secs']);
								$sql = "UPDATE shoparticle SET price=0.00,pricechanged=" . $change . " ,updated=" . time() . " WHERE id=" . $art->id . " AND pricechanged=" . $art->pricechanged;
								$res = $db->sql_query($sql);
								if($res) {
								} else {
									var_dump($sql);
								}
							}
						}
*/
					}
				}			
			} else {
			
			}
		}
	}
	
    class shopCart {
		var $sid;
		var $customer;
		var $ct;
		var $page;
		var $count;
		var $amount;
		var $shipping;
		var $shipmsg;
		var $shiptype;
		var $discount;
		var $discmsg;
		var $total;
		var $vat;
		var $list;
		
		function shopCart($session=0, $st=0) {
			global $shopcfg, $clnt, $db;

			$this->shiptype = $st;
			
			if(!is_object($shopcfg)) {
				$shopcfg = new shopConfig();
			}
			
			if($session > 0 ) {
				$this->sid = $session;
			} else {
				$this->sid = $clnt->sid;
			}
			
			$this->customer = new shopCustomer($clnt->ct);
			$this->page = $page;
			
			$this->load();
		}
		
		function load() {
			global $shopcfg, $clnt, $db;
			
			$this->count = 0;
			$this->amount = new shopPrice(0.00);
			$this->vat = new shopPrice(0.00);
			$this->discount = new shopPrice(0.00);
			$this->discmsg = "";
			$this->total = new shopPrice(0.00);
			
			$this->list = array();
			
			if($this->customer->id > 0) {
				$sql = "SELECT * FROM shopcart WHERE sid=" . $this->sid . " OR cid=" . $this->customer->id .  " ORDER BY itemno";
			} else {
				$sql = "SELECT * FROM shopcart WHERE sid=" . $this->sid . " ORDER BY itemno";
			}
			
			if($result = $db->sql_query($sql)) {
				while ($row = $db->sql_fetchrow($result)) {
					$art = new shopArticle($row['aid']);
					$cid = intval($row['cid']);
					if($this->customer->id > 0) {
						if($cid == 0) {
							if($art->artstatus->id == 2) {
								$art->setStatus(3,"In winkelwagen klant #" . $this->customer->id);
								$sql = "UPDATE shopcart SET cid=" . $this->customer->id . " WHERE sid=" . $this->sid . " AND aid=" . $art->id;
								$res = $db->sql_query($sql);
							} else {
								$sql  = "DELETE FROM shopcart WHERE sid=" . $this->sid . " AND aid=" . $art->id;
								$res = $db->sql_query($sql);
								$art = null;
							}
						}
					} else {
						if($art->artstatus->id == 3) {
							$sql  = "DELETE FROM shopcart WHERE sid=" . $this->sid . " AND aid=" . $art->id;
							$res = $db->sql_query($sql);
							$art = null;
						}
					}
					
					if(is_object($art) && ($art->id > 0)) {
						array_push($this->list,$art);
					}
				}
			}
			
			foreach($this->list as $art) {
				$this->amount->add($art->price->val);
				$this->count++;	
			}

			$this->total->add($this->amount->val);
			
			if($this->shiptype == 0) {
				foreach($shopcfg->shipping as $ship) {
					if($this->amount->val >= $ship['limit']) {
						$this->shiptype++;
					}
				}
			}
			
			if($this->amount->val >= $shopcfg->shipping[$this->shiptype]['limit']) {
				$this->shipping = new shopPrice($shopcfg->shipping[$this->shiptype]['price']);
			} else {
				$this->shipping = new shopPrice($shopcfg->shipping[$this->shiptype]['fullprice']);
			}
			$this->shipmsg = $shopcfg->shipping[$this->shiptype]['description'];
						
			$this->total->add($this->shipping->val);
			
			$this->discount = new shopPrice(0.00);
			$this->discmsg = "";

			if($this->amount->val > 0) {
				if($this->customer->discount > $shopcfg->action['discount']) {
					$this->discount->set($this->amount->val * $this->customer->discount * -1);
					$this->discmsg = "Klant korting (" . $this->customer->discount * 100 . "%)";
				} else {
					if($shopcfg->action['discount'] > 0.00) {
						$this->discount->set($this->amount->val * $shopcfg->action['discount'] * -1);
						$this->discmsg = $shopcfg->action['description'] . " (" . $shopcfg->action['discount'] * 100 . "%)";
					} 
				}
				$this->total->add($this->discount->val);
			}
		}
		
		function add($aid,$cnt=1) {
			global $db, $cfg;
			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
			$result['text'] = "";
			$result['cid'] = $this->customer->id;
			
			foreach($this->list as $art) {
				if($art->id == $aid) {
					$result['errno'] = -6;
					$result['errmsg'] = "Kavel #" . $aid . " zit al in uw winkelwagen!";
					$result['id'] = $aid;
					return $result;
				}
			}
			
			$article = new shopArticle($aid);
			if(is_object($article)) {	
				if($article->artstatus->id != 2) {
					$result['errno'] = -7;
					$result['errmsg'] = "NIET meer beschikbaar";
					$result['id'] = $aid;
					return $result;
				}
				
				$sql  = "INSERT INTO shopcart (sid,cid,aid,cnt) VALUES (" . $this->sid . "," . $this->customer->id . "," . $aid . "," . $cnt . ")";
				$res = $db->sql_query($sql);
				if($res) { 
					$aid = intval($db->sql_nextid());
					if($aid > 0) {
						$result['errmsg'] = "OK";
						$result['id'] = $aid;
						
						if($this->customer->id > 0) {
							$article->setStatus(3,"In winkelwagen klant #" . $this->customer->id);
						}
					}
					
					$this->load();
					$result['msg'] = $this->sumText();
				} else {
					$result['errno'] = -1;
					$result['errmsg'] = "Kavel " . $aid . " kan NIET worden toegevoegd.";
				}
			} else {
				$result['errno'] = -8;
				$result['errmsg'] = "Kavel #" . $aid . " is NIET niet gevonden!";
				$result['id'] = $aid;
			}
			
			return $result;
		}
		
		function remove($aid,$cnt=1) {
			global $db, $cfg;
			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
			$result['msg'] = "";
							
			$found = false;
			foreach($this->list as $art) {
				if($art->id == $aid) {
					$found = true;
				}
			}
			
			if(!$found) {
				$result['errno'] = -7;
				$result['errmsg'] = "Not in shopcart";
				$result['id'] = $aid;
					
				return $result;
			}

			$itemno = count($this->list) + 1;
			$sql  = "DELETE FROM shopcart WHERE ( cid = " . $this->customer->id . " OR sid=" . $this->sid . " ) AND aid=" . $aid;
			$res = $db->sql_query($sql);
			if($res) {
				$result['errmsg'] = "OK";
				$result['id'] = $aid;
				if($this->customer->id > 0) {
					$article = new shopArticle($aid);
					if(is_object($article)) {
						$article->setStatus(2,"Uit winkelwagen klant #" . $this->customer->id);
					}
				}
				$this->load();
				$result['msg'] = $this->sumText();
			} else {
				$result['errno'] = -1;
				$result['errmsg'] = "DELETE ERROR " . $sql;
			}
			
			return $result;
		
		}
		
		function sumText() {
		
			if($this->count > 0) {
				if($this->count > 1) {
					$text = $this->count . " kavels - " . $this->amount->html;
				} else {
					$text = "1 kavel - " . $this->amount->html;
				}
			} else {
				$text = "Geen kavels - &euro; 0,00";
			}
		
			return $text;
		}
		
		function o2a() {
		
			$_arr = array(
				'sid' => $this->sid,
				'count' => $this->count,
				'amount' => $this->amount->o2a(),
				'shiptype' => $this->shiptype,
				'shipping' => $this->shipping->o2a(),
				'discount' => $this->discount->o2a(),
				'discmsg' => $this->discmsg,
				'total' => $this->total->o2a()
			);
			
			return $_arr;
		}

	} // class shopcart
	
	class shopOrder {
		var $id;
		var $cid;
		var $status;
		var $rating;
		var $name;
		var $email;
		var $adress;
		var $zipcode;
		var $city;
		var $country;
		var $phone;
		var $mobile;
		var $company;
		var $debno;
		var $paymethod;
		var $deliver;
		var $message;
		var $transaction;
		var $delivertime;
		var $orderdate;
		var $orderchange;
		var $total;
		var $amount; 
		var $shipping;
		var $shipmsg;
		var $discount;
		var $discmsg;
		var $vat;
		var $lines = array(); 
		var $changes = array(); 

		function shoporder($id) {
			global $db, $clnt;
			
			$this->total = new shopPrice(0.00);
			$this->amount = new shopPrice(0.00);
			$this->vat = new shopPrice(0.00);
			$this->shipping = new shopPrice(0.00);
			$this->discount = new shopPrice(0.00);
			$this->lines = array(); 
			$this->changes = array();
	
			$sql = "SELECT * FROM shoporder WHERE id=" . $id . " ORDER BY id DESC";
			$result = $db->sql_query($sql);
			if($result)	{
				if($row = $db->sql_fetchrow($result))  {
					$this->load($row);
					
				} else {
					// Log error
				}
			}
		}
		
		function load($row) {
			$this->id = intval($row[id]);
			$this->cid = intval($row[cid]);
			$this->numlines = intval($row[numlines]);
			$this->email = $row[email];
			$this->name = utf8_encode($row['name']);
			$this->adress = utf8_encode($row[adress]);
			$this->zipcode = utf8_encode($row[zipcode]);
			$this->city = utf8_encode($row[city]);
			$this->country = utf8_encode($row[country]);
			$this->phone = utf8_encode($row[phone]);
			$this->orderdate = $row[orderdate];
			$this->total->set($row[total]);
			$this->amount->set($row[amount]);
			$this->vat->set($row[vat]);
			$this->shipping->set($row[shipping]);
			$this->shipmsg = $row[shipmsg];
			$this->discount->set($row[discount]);
			$this->discmsg = $row[discmsg];
			$this->status = intval($row[status]);
			$this->discount->set($row[discount]);
			$this->discmsg = $row[discmsg];
			$this->paymethod = $row[paymethod];
			$this->orderchange = $row[orderchange];
			$this->message = $row[message];
			$this->deliver = $row[deliver];
			$this->delivertime = $row[delivertime];
			$this->reference = $row[reference];			
			$this->rating = intval($row[rating]);
			
			$this->loadLines();
			$this->loadChanges();

		}

		function loadLines() {
			global $db;
			
			$sql = "SELECT * FROM shoporderline, shoparticle WHERE orderno=" . $this->id . " and shoporderline.aid = shoparticle.id ORDER BY lineno";
			$result = $db->sql_query($sql);
			$i=0;
			if($result) {
				while ($row = $db->sql_fetchrow($result)) {
					$line = array();
					$line['aid'] = $row['aid'];
					$line['artname'] = utf8_encode($row['artname']);
					$line['cnt'] = intval($row[cnt]);
					$line['price'] = new shopPrice(floatval($row['price']));
					$line['amount'] = new shopPrice(floatval($row['amount']));						
					$this->lines[$i++] = $line;
				}
			} 
		}
		
		function loadChanges() {
			global $db;
	
			$sql = "SELECT * FROM shoporderchange WHERE orderno=" . $this->id . " ORDER BY id";
			if( ($result = $db->sql_query($sql)) ) { 
				
				$this->changes[num] = $db->sql_numrows($result); 

				while ( $row = $db->sql_fetchrow($result) ) {
					$line = array();

					//$line[chgdate] = timestamp2datetime($row['changedate']);
					$line[chgdate] = $row['changedate'];
					$line[status] = intval($row['status_new']);
					$line[message] = $row['message'];

					array_push($this->changes,$line);
				}
			}
		}
		
		function checkOrder() {
		
			$errors = array();
			$result = array();
			$result['id'] = $this->id;

			
			foreach($this->lines as $line) {
				$result['errno'] = 0;
				$result['errmsg'] = "";

				$aid = $line['aid'];
				$kavel = new shopArticle($aid);
				if(is_object($kavel)) {

					switch($this->status) {
						case 0:
						case 1: 
						case 2:
							if($kavel->artstatus->id != 3) {
								$result['errno'] = 14; 
								$result['errmsg'] = "Kavel " . $aid . " status ". $kavel->artstatus->id . " is niet correct (moet zijn besteld:3)";
							}
							break;
						case 3:
						case 4:
						case 5:
						case 6:
						case 10:
							if($kavel->artstatus->id != 4) {
								$result['errno'] = 15;
								$result['errmsg'] = "Kavel " . $aid . " status " . $kavel->artstatus->id . " is niet correct (moet zijn verkocht:4)";
							}
						default:
							break;
					}
				} else {
					$result['errno'] = 13; 
					$result['errmsg'] = "Kavel " . $aid . " niet beschikbaar";
				}

				if($result['errno'] > 0) {
					array_push($errors,$result);
				}
			}
			
			return $errors;
		}
		
		function setStatus($status,$msg) {
			global $db;

			$now = date('Y-m-d H:i:s');

			if($status >= 0) {
			
				if($status > $this->status) {
					$sql = "UPDATE shoporder SET status=" . $status . " , orderchange='" . $now . "'  WHERE id=" . $this->id;
					$res = $db->sql_query($sql);
					if($res) $this->status = $status;

					$sql = "INSERT INTO shoporderchange (orderno,changedate,status_new,message) VALUES (" . $this->id . ",'" . $now . "'," . $status . ",'" . $msg . "')";
					$db->sql_query($sql);
				
					switch($status) {
						case 3: // Betaald
							foreach($this->lines as $line) {
								$art = new shopArticle($line['aid']);
								if(is_object($art)) {
									$art->setStatus(4,"Bestelling #" . $this->id . " is betaald");
									//$sql = "UPDATE shoparticle SET artstatus=4 WHERE id=" . $line['aid'];
									//$db->sql_query($sql);
								}
							}
							$this->sendMail(); /* Uw betaling is ontvangen */
							break;
						case 4: // Verzonden
							$this->sendMail(); /* Uw bestelling is verzonden */
							break;
						case 9: // Annulering
							foreach($this->lines as $line) {
							$art = new shopArticle($line['aid']);
								if(is_object($art)) {
									$art->setStatus(2,"Bestelling #" . $this->id . " is geannuleerd");
									//$sql = "UPDATE shoparticle SET artstatus=2 WHERE id=" . $line['aid'];
									//$db->sql_query($sql);
								}
							}
							$this->sendMail(); /* Uw bestelling is geannuleerd */
							break;
						
						default:
							break;
					}
				} else {
					// Geen terugweg mogelijk !!!
				}
			}
		}

		function setDebNo($dn)
		{
			global $db,$clnt;

			$this->debno = intval($dn);

			$sql = "UPDATE shop_order SET debno = " . $this->debno . " WHERE orderno=" . $this->orderno;

			$result = $db->sql_query($sql);

			return $result;
		}

		function setReference($ref) {
			global $db,$clnt;

			$this->reference = $ref;

			$sql = "UPDATE shop_order SET reference = '" . $this->reference . "' WHERE orderno=" . $this->orderno;

			$result = $db->sql_query($sql);

			return $result;
		}

		function setDelivertime($dt)
		{
			global $db,$clnt;

			$this->delivertime = intval($dt);

			$sql = "UPDATE shop_order SET delivertime = " . $this->delivertime . " WHERE orderno=" . $this->orderno;

			$result = $db->sql_query($sql);

			return $result;
		}

		function sendMail() {
			global $cfg;
			
			require_once("library/email.cls.php");
			
			$result = false;

			$template = "./templates/email.shoporder-" . $this->status . ".tmpl";
//var_dump($template);		
			
			$msg = $this->getMailMessage($template,$customer);
//var_dump($msg);
//$msg = "TEST SENDING";
			$to = $this->name . " <". $this->email . ">";
			$subject = "KlokkieBigBen::Bestelling #" . $this->id . " : " . shoporder::statusText($this->status);
			$email = new email();

			$email->sender($cfg->email_info);
			$email->receiver($to);
			$email->subject($subject);
			$email->message($msg);

			if($cfg->testmode) {
				//echo $msg;
			} else {
				$result = $email->send();
			}

			return $result;
		}
/*
		function getShippingArticle($country,$deliver)
		{
			$artno = 0;

			if( $deliver == "V" )
			{
				$artno = 12;
				if($country != "Nederland") $artno = 13; 
			}

			return $artno;
		}
*/
		function getMailMessage($template) {
			global $cfg;
			
			$msg = "file:" . $template . " NOT FOUND";
			
			$handle = fopen($template, "rb");
			if($handle) {
				$msg = fread($handle, filesize($template));
				fclose($handle);
			}	
		
			$msg = str_replace("[[order::id]]",$this->id,$msg);
			$msg = str_replace("[[order::name]]",$this->name,$msg);
			$msg = str_replace("[[order::email]]",$this->email,$msg);
			$msg = str_replace("[[order::adress]]",$this->adress,$msg);
			$msg = str_replace("[[order::zipcode]]",$this->zipcode,$msg);
			$msg = str_replace("[[order::city]]",$this->city,$msg);
			$msg = str_replace("[[order::country]]",$this->country,$msg);
			$msg = str_replace("[[order::phone]]",$this->phone,$msg);
			
			$msg = str_replace("[[order::table]]",$this->htmlTable(),$msg);
			
			$msg = str_replace("[[order::total]]",$this->total->html,$msg);
			$msg = str_replace("[[order::shipping]]",$this->shipping->html,$msg);
			$msg = str_replace("[[order::shipmsg]]",$this->shipmsg,$msg);
			$msg = str_replace("[[order::subtotal]]",$this->subtotal->html,$msg);
			
			$customer = new shopCustomer($this->cid);
			if(is_object($customer)) {
				$msg = str_replace("[[customer::token]]",$customer->token,$msg);
			}
			
			$msg = str_replace("[[cfg::siteurl]]",$cfg->siteurl,$msg);
			$msg = str_replace("[[cfg::bankno]]",$cfg->bankno,$msg);
			$msg = str_replace("[[cfg::bankname]]",$cfg->bankname,$msg);
			$msg = str_replace("[[cfg::iban]]",$cfg->iban,$msg);
			$msg = str_replace("[[cfg::bic]]",$cfg->bic,$msg);

			return $msg;
		}	
/*
		function info($template) {
			$tmpl_info = new template($template);

			$tmpl_info->replaceTag("name",$this->name);
			$tmpl_info->replaceTag("adress",$this->adress);
			$tmpl_info->replaceTag("zipcode",$this->zipcode);
			$tmpl_info->replaceTag("city",$this->city);
			$tmpl_info->replaceTag("email",$this->email);
			$tmpl_info->replaceTag("phone",$this->phone);
			$tmpl_info->replaceTag("mobile",$this->mobile);
			$tmpl_info->replaceTag("country",$this->country);
			$tmpl_info->replaceTag("message",$this->message);
			$tmpl_info->replaceTag("total",euroString($this->total,true));
			$tmpl_info->replaceTag("order_info_lines",$this->htmlOrderLines());

			return $tmpl_info;
		}
*/
		
		function htmlTable() {
			global $cfg,$clnt,$customer,$db;
	
			//$ac = $customer->adressComplete();
			
			$_html .= "<table>";
			$_html .= "<tbody>";
			
			foreach($this->lines as $line) {
				$_html .= $this->htmlTableLine($line);
			}
/*		
			$_html .= "<tr>";
			$_html .= "<td colspan='2'></td>";
			$_html .= "<td><p style='text-align: right;'>----------+</p></td>";
			$_html .= "</tr>";
*/
			$_html .= "<tr>";
			$_html .= "<td colspan='2'><p style='text-align: right;'>Subtotaal</p></td>";
			$_html .= "<td><p style='text-align: right; border-top: 1px dashed #000;'>" . $this->amount->html . "</p></td>";
			$_html .= "</tr>";
			$_html .= "<tr>";
			$_html .= "<td colspan='2'><p style='text-align: right;'>Verzendwijze: " .  $this->shipmsg . "</strong></td>";
			$_html .= "<td><p style='text-align: right;'>" . $this->shipping->html . "</string></td>";
			$_html .= "</tr>";
			if($this->discount != 0.00) {
				$_html .= "<tr>";
				$_html .= "<td colspan='2'><p style='text-align: right;'>" .  $this->discmsg . "</strong></td>";
				$_html .= "<td><p style='text-align: right;'>" . $this->discount->html . "</string></td>";
				$_html .= "</tr>";
			}
/*
			$_html .= "<tr>";
			$_html .= "<td colspan='2'></td>";
			$_html .= "<td><p style='text-align: right;'>=======+</p></td>";
			$_html .= "</tr>";
*/
			$_html .= "<tr>";
			$_html .= "<td colspan='2'><p style='text-align: right;'><strong>Totaal</strong></p></td>";
			$_html .= "<td><p style='text-align: right;  border-top: 1px solid #000;'><strong>" . $this->total->html . "</strong></p></td>";
			$_html .= "</tr>";
			$_html .= "</tbody>";
			$_html .= "</table>";

			return $_html;
		}

		function htmlTableLine($orderline) {
			global $cfg,$clnt,$db;
	
			$_html = "";
			$_html .= "<tr>";
			$_html .= "<td>";	
			$art = new shopArticle(intval($orderline['aid']));
			if(is_object($art->artimage)) {
				//var_dump($art->artimage->thumbnail);
				$_html .= "<img src='" . $cfg->siteurl . "/". $art->artimage->thumbnail . "' title='kavel-2' style='max-width: 40px; max-height: 40px;'>";
			}
						
			//$_html .= art->image->src;	
			
			$_html .= "</td>";
			$_html .= "<td>";	
			$_html .= $orderline['artname'];
			$_html .= " (kavel-" . $art->id . ")";
			$_html .= "</td>";
			$_html .= "<td><p style='text-align: right;'>";	
			$_html .= $orderline['amount']->html;
			$_html .= "</p></td>";
			$_html .= "</tr>";
			
			return $_html;
		}
/*
		function htmlOrderLines()
		{
			$tmpl_line = new template("order_info_line");
			$html_lines = "";

			foreach ( $this->lines as $line )
			{
				//if(TESTMODE) { echo "<hr>LINE:"; var_dump($line); }

				$count = $line[count];
				$price = ""; //$line[price] * 1.19;
				$amount = $line[amount] + $line[vat];
				if($count > 0) $price = $amount / $count;
				$html_price = euroString($price);
				$html_amount = euroString($amount);

				$tmpl = $tmpl_line;
				$tmpl->replaceTag("artcode",$line[artcode]);
				$tmpl->replaceTag("description",$line[description]);
				$tmpl->replaceTag("count",$count);
				$tmpl->replaceTag("price",$html_price);
				$tmpl->replaceTag("amount",$html_amount);
		
				$html_lines .= $tmpl->html;
			}

			return $html_lines;
		}

		function replaceTags($templ)
		{
		 	$templ->replaceTag("total",euroString($total));
		 	$templ->replaceTag("vat",euroString($vat));
		 	$templ->replaceTag("orderno",$this->orderno);
			$templ->replaceTag("name",$this->name);
			$templ->replaceTag("adress",$this->adress);
			$templ->replaceTag("zipcode",$this->zipcode);
			$templ->replaceTag("city",$this->city);
			$templ->replaceTag("country",$this->country);
			$templ->replaceTag("email",$this->email);
			$templ->replaceTag("phone",$this->phone);
			$templ->replaceTag("mobile",$this->mobile);
			$templ->replaceTag("message",$this->message);
			//$html = replaceTag($html,"order_form",showOrderForm($order));

			$destination = "DESTINATION";
			$templ->replaceTag("order_destination",$destination);

			return $templ->html;
		}
*/
		
		function o2a() {
		
//var_dump($this->lines);
				
			$_lines = array();

			foreach($this->lines as $line) {
				$price = $line['price'];//->o2a();
				$amount = $line['amount'];//->o2a();
				
				$_line = array(
					'aid'		=> $line['aid'],
					'artname'	=> $line['artname'],
					'cnt'		=> $line['cnt'],
					'price'		=> $price->o2a(),
					'amount'	=> $amount->o2a()
				);
				
				array_push($_lines,$_line);
			}
			
//var_dump($this->changes);
			$_history = array();

			foreach($this->changes as $chg) {
				
				$_change = array(
					'chgdate'	=> $chg['chgdate'],
					'status'	=> $chg['status'],
					'stattxt'	=> shopOrder::statusText($chg['status']),
					'message'	=> $chg['message']
				);
				
				if($chg['status'] > 0) {
					array_push($_history,$_change);
				}
			}
				
			$_arr = array(
				'id' 		=>	$this->id,
				'status' 	=>	$this->status,
				'cid' 	 	=>	$this->cid,
				'email' 	=>	$this->email,
				'pin' 		=>	$this->pin,
				'name' 		=>	$this->name,
				'adress' 	=>	$this->adress,
				'orderdate' =>	$this->orderdate,
				'zipcode' 	=>	$this->zipcode,
				'city' 		=>	$this->city,
				'country' 	=>	$this->country,
				'phone' 	=>	'#' . $this->phone,
				'numlines'	=> 	$this->numlines,
				'lines'		=>  $_lines,
				'amount' 	=>	$this->amount->o2a(),
				'discount' 	=>	$this->discount->o2a(),
				'discmsg' 	=>	$this->discmsg,
				'shipping' 	=>	$this->shipping->o2a(),
				'shipmsg' 	=>	$this->shipmsg,
				'total' 	=>	$this->total->o2a(),
				'history'	=>	$_history
			);		
			
			return $_arr;
		}
		
		function update($data) {
			global $clnt, $db;

			$result = array();

			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['data'] = array();

			
			if($this->id == 0) {
				$result['errno'] = 1;
				$result['errmsg'] = "Toevoegen NIET mogelijk";
			} else {
				$where = "id=" . $this->id;
/*
				if(array_key_exists("email",$data)) {
					$set = "email='" . utf8_decode($data['email']) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
*/
				if(array_key_exists("name",$data)) {
					$set = "name='" . utf8_decode($data['name']) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$this->name = $data['name'];
				}
				if(array_key_exists("adress",$data)) {
					$set = "adress='" . utf8_decode($data['adress']) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("zipcode",$data)) {
					$set = "zipcode='" . strtoupper(utf8_decode($data['zipcode'])) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("city",$data)) {
					$set = "city='" . utf8_decode($data['city']) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("country",$data)) {
					$set = "country='" . utf8_decode($data['country']) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("phone",$data)) {
					$set = "phone='" . utf8_decode($data['phone']) . "'";
					$result = $db->sql_update("shoporder",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}

				if(array_key_exists("status",$data)) {
					$new_status = intval($data['status']);
					switch($this->status) {
						case 1: // Nieuw
							if($new_status == 2) {
								 $this->setStatus($new_status,$this->name);
							} else {
								if($new_status == 3) {
									$this->setStatus($new_status,"Administratie");
								} else {
									if($new_status == 9) {
										$this->setStatus($new_status,"Administratie");
									} else {
										$result['errno'] = -99;
										$result['errmsg'] = "illegal status (" . $new_status . ")";
									}
								}
							}
							break;
							
						case 2: //Overgemaakt
							if($new_status == 3) {
								$this->setStatus($new_status,"Administratie");
							} else {
								if($new_status == 9) {
									$this->setStatus($new_status,"Administratie");
								} else {
									$result['errno'] = -99;
									$result['errmsg'] = "illegal status (" . $new_status . ")";
								}
							}
							break;
							
						case 3: //Betaald
							if($new_status == 4) {
								$this->setStatus($new_status,"door F. Bakker");
							} else {
								if($new_status == 9) {
									$this->setStatus($new_status,"Administratie");
								} else {
									$result['errno'] = -99;
									$result['errmsg'] = "illegal status (" . $new_status . ")";
								}
							}
							break;
							
						case 4: // Verzonden 
							if($new_status == 5) {
								$this->setStatus($new_status,$this->name);
							} else {
								if($new_status == 7) {
									$this->setStatus($new_status,"Administratie");
								} else {
									if($new_status == 10) {
										$this->setStatus($new_status,"Administratie");
									} else {
										$result['errno'] = -99;
										$result['errmsg'] = "illegal status (" . $new_status . ")";
									}
								}
							}
							break;
						
						case 5: // Ontvangen 
							if($new_status == 6) {
								$this->setStatus($new_status,"Administratie");
							} else {
								if($new_status == 10) {
									$this->setStatus($new_status,"Administratie");
								} else {
									$result['errno'] = -99;
									$result['errmsg'] = "illegal status (" . $new_status . ")";
								}
							}
							break;
							
						case 6: // Klacht 
						case 7: // Retour 
							if($new_status == 8) {
								$this->setStatus($new_status,"Administratie");
							} else {
								if($new_status == 9) {
									$this->setStatus($new_status,"Administratie");
								} else {
									if($new_status == 10) {
										$this->setStatus($new_status,"Administratie");
									} else {
										$result['errno'] = -99;
										$result['errmsg'] = "illegal status (" . $new_status . ")";
									}
								}
							}
							break;
							
						case 8: // Credit 
						case 9: // Geannuleerd
							if($new_status == 10) {
								$this->setStatus($new_status,"Administratie");
							} else {
								$result['errno'] = -99;
								$result['errmsg'] = "illegal status (" . $new_status . ")";
							}
							break;
						
						default:
							break;
					}
				}
			}

			return $result;
		}
		
		static function statusText($status) {
			switch ($status) {
				case 0: return "Aangemaakt, nog niet bevestigd";
				case 1: return "Aangemaakt";
				case 2: return "Overgemaakt";
				case 3: return "Betaald";
				case 4: return "Verzonden";
				case 5: return "Ontvangen";
				case 6: return "Klacht";
				case 7: return "Retour";
				case 8: return "Credit";
				case 9: return "Geannuleerd";
				case 10:return "Afgehandeld";
			}

			return "Onbekend(" . $status . ")";
		}
		
		
		static function create($data) {
			global $db, $cfg, $clnt;

			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
			
			if(strlen($clnt->ct) == 32) {
				$cust = new shopCustomer($clnt->ct);
				if($cust->id > 0) {
					$order_status = 1;
				
					$shiptype = intval($data['shiptype']);
					$shopcart = new shopCart(0,$shiptype);
					if(count($shopcart->list) == 0) {
						$result['errno'] = -21;
						$result['errmsg'] = "Er zijn geen artikelen in uw winkelwagen gevonden";
						return $result;
					} else {
						
						foreach($shopcart->list as $art) {
							if($art->artstatus->id != 3) {
								$result['errno'] = -17;
								$result['errmsg'] = "Kavel #" . $art->id . " '" . $art->artname() . "' is helaas NIET meer beschikbaar.<br>" .
													"Om uw bestelling te plaatsen, moet het kavel eerst verwijderd worden.";
								return $result;
							}	
						}
						
						$sql = "INSERT INTO shoporder(cid, email, name, adress, zipcode, city, phone, country) VALUES (" . $cust->id . ",'" . utf8_decode($cust->email) . "','" . utf8_decode($cust->name) . "','" . utf8_decode($cust->adress) . "','" . utf8_decode($cust->zipcode) . "','" . utf8_decode($cust->city) . "','" . utf8_decode($cust->phone) . "','" . utf8_decode($cust->country) . "')";
						$res = $db->sql_query($sql);
						if($res) { 
							$orderno = intval($db->sql_nextid());
							$numlines = 0;
							$line = 1;
							
							foreach($shopcart->list as $art) {
								$art->setStatus(3, "Toegevoegd aan bestelling #" . $orderno);
								$sql = "INSERT INTO shoporderline(orderno, lineno, aid, artname, cnt, price, amount) VALUES (" . 
									$orderno . "," . $line++ . "," . $art->id . ",'" . utf8_decode($art->artname())  . "',1," . $art->price->val . "," . $art->price->val . ")";									
								$db->sql_query($sql);
								$numlines++;
								//$sql  = "DELETE FROM shopcart WHERE sid=" . $shopcart->sid . " AND aid=" . $art->id;
								$sql  = "DELETE FROM shopcart WHERE ( cid=" . $cust->id . " OR sid=" . $shopcart->sid . " ) AND aid=" . $art->id;
								$db->sql_query($sql);
							}
							
							$where = "id=" . $orderno;
							$set = "total=" . $shopcart->total->val;
							$set .= ",amount=" . $shopcart->amount->val;
							$set .= ",shipping=" . $shopcart->shipping->val;
							$set .= ",shipmsg='" . $shopcart->shipmsg . "'";
							$set .= ",discount=" . $shopcart->discount->val;
							$set .= ",discmsg='" . $shopcart->discmsg . "'";
							$set .= ",status=" . $order_status;
							$set .= ",numlines=" . $numlines;
							
							$db->sql_update("shoporder",$where,$set);

							$order = new shopOrder($orderno);
							if(is_object($order) && ($order->id > 0)) {
								$order->sendMail();
							}
							
						} else {
							$result['errno'] = -22;
							$result['errmsg'] = "Fout bij aanmaken van de bestelling";
							return $result;
						}
					}
				} else {
					$result['errno'] = -19;
					$result['errmsg'] = "Klantgegevens zijn NIET gevonden";
					return $result;
				}
			} else {
				$result['errno'] = -20;
				$result['errmsg'] = "Klantgegevens zijn NIET beschikbaar";
				return $result;
			}

			return $result;
		}
	
	
		static function _list($inp) {
			global $db,$clnt,$cfg;
			
			$sort = $inp['sort'];
			
			$where = array();
			
			if(array_key_exists("status",$inp)) {
				$arg = explode('-' ,$inp['status']);
				if(count($arg) == 2) {
					array_push($where,"status>=".intval($arg[0]));
					array_push($where,"status<=".intval($arg[1]));
				} else {
					$x = intval($inp['status']);
					if($x > 0) {
						array_push($where,"status=".$x);
					}
				}
			}
			
			$sql = "SELECT * FROM shoporder";
			
			for($i=0;$i<count($where);$i++) {
				if($i == 0) {
					$sql .= " WHERE " . $where[0];
				} else {
					$sql .= " AND " . $where[$i];
				}
			}
			
			if(strlen($sort) > 0) {
				$sql .= " ORDER BY " . $sort;
			}

			$_list = array();
			
			if($res = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($res)) {	
					$order = new shopOrder($row['id']);
					$order->load($row);
					array_push($_list,$order->o2a());
				}
			}
				
			return $_list;
		}
		
		static function checkOrders() {	
			global $db,$clnt,$cfg;
			
			$errors = array();
			$inp['sort'] = "id DESC";
			$_list = shopOrder::_list($inp);			
			foreach($_list as $order) {
				$shoporder = new shopOrder($order['id']);
				if(is_object($shoporder)) {
					$errlst = $shoporder->checkOrder();
				} else {
					$errlst = array(
						array(
							'errno' => 101,
							'errmsg' => "Order NIET beschikbaar",
							'id' => $order['id']
						)
					);
				}
			
				foreach($errlst as $error) {
					array_push($errors,$error);
				}
			}
			
			return $errors;
		}
	}
/*
	function timestamp2datetime($timestamp) {
		if(strpos($timestamp,"-") > 0 ) {
			$year = substr($timestamp,0,4);
			$month = substr($timestamp,5,2);
			$day = substr($timestamp,8,2);
			$hour = substr($timestamp,11,2);
			$minute = substr($timestamp,14,2);
			$second = substr($timestamp,17,2);
		} else {
			$year = substr($timestamp,0,4);
			$month = substr($timestamp,4,2);
			$day = substr($timestamp,6,2);
			$hour = substr($timestamp,8,2);
			$minute = substr($timestamp,10,2);
			$second = substr($timestamp,12,2);
		}

		return sprintf("%s-%s-%s %s:%s",$day,$month,$year,$hour,$minute);
	}
*/
	class shopCustomer {
		var $id;
		var $session;
		var $email;
		var $name;
		var $pin;
		var $adress;
		var $zipcode;
		var $city;
		var $country;
		var $phone;
		var $bankno;
		var $discount;
		var $conditions;
		var $token;
		var $verified;
		var $blocked;
		var $orders;
		var $shopcart;

		function shopCustomer($cid) {
			global $db, $clnt;
			
			$this->id = 0;
			$this->session = 0;
			$this->name = "";
			$this->email = "";
			$this->pin = 0;
			$this->adress = "";
			$this->zipcode = "";
			$this->city = "";
			$this->country = "Nederland";
			$this->phone = "";
			$this->bankno = "";
			$this->discount = 0.00;
			$this->conditions = "";
			$this->active = 0;
			$this->verified = 0;
			$this->blocked = 0;
			$this->token = "";
			$this->orders = array();
			$this->shopcart = array();

			if(strpos($cid, '@') > 0) {
				$sql = "SELECT * FROM shopcustomer WHERE email = '" . strtolower($cid) . "'";
			} else {
				if(strlen($cid) == 32) {
					$sql = "SELECT * FROM shopcustomer WHERE token = '" . $cid . "'";
				} else {
					$sql = "SELECT * FROM shopcustomer WHERE id = " . intval($cid);
				}
			}

			if( ($result = $db->sql_query($sql)) ) {
				if ( $row = $db->sql_fetchrow($result) ) {
					$this->load($row);
				}
			}

		}
		
		function load($row) {
			global $db, $clnt;

			$this->id = intval($row['id']);
			$this->session = intval($row['session']);
			$this->name = utf8_encode($row['name']);
			$this->pin = $row['pin'];
			$this->email = utf8_encode($row['email']);
			$this->adress = utf8_encode($row['adress']);
			$this->zipcode = utf8_encode($row['zipcode']);
			$this->city = utf8_encode($row['city']);
			$this->country = utf8_encode($row['country']);
			$this->phone = strval(utf8_encode($row['phone']));
			$this->bankno = utf8_encode($row['bankno']);
			$this->conditions = utf8_encode($row['conditions']);
			$this->discount = $row['discount'];
			$this->active = intval($row['active']);
			$this->verified = intval($row['verified']);
			$this->blocked = intval($row['blocked']);
			$this->token = $row['token'];
			
			$this->loadOrders();
			$this->loadShopcart();
		}
		
		function adressComplete() {
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			
			if(strlen($this->adress) < 5) {
				$result['errno'] = -1;
				$result['errmsg'] = "Uw adres is NIET volledig ingevuld";
			} else if(strlen($this->zipcode) < 6) {
				$result['errno'] = -2;
				$result['errmsg'] = "Uw postcode is NIET volledig ingevuld";
			} else {
			}
			
			return $result;
		}	

		function o2a() {
		
			$_shopcart = array();
			foreach($this->shopcart as $art) {			
				array_push($_shopcart,$art->o2a());
			}
			
			$_orders = array();
			foreach($this->orders as $order) {			
				array_push($_orders,$order->o2a());
			}
			
			$_arr = array(
				'id' 		 =>	$this->id,
				'session' 	 =>	$this->session,
				'email' 	 =>	$this->email,
				'pin' 		 =>	$this->pin,
				'name' 		 =>	$this->name,
				'adress' 	 =>	$this->adress,
				'zipcode' 	 =>	$this->zipcode,
				'city' 		 =>	$this->city,
				'country' 	 =>	$this->country,
				'phone' 	 =>	'#' . $this->phone,
				'bankno' 	 =>	'#' . $this->bankno,
				'discount' 	 =>	$this->discount,
				'conditions' =>	$this->conditions,
				'verified'	 =>	$this->verified,
				'blocked'    =>	$this->blocked,
				'token'		 =>	$this->token,
				'orders'	 =>	$_orders,
				'shopcart'	 =>	$_shopcart
			);		
			
			return $_arr;
		}
		
		function loadOrders() {
			global $db;
			$sql = "SELECT * FROM shoporder WHERE cid=" . $this->id . " ORDER BY id DESC";
			if($result = $db->sql_query($sql)) {
				while ($row = $db->sql_fetchrow($result)) {
					$order = new shopOrder(0);
					$order->load($row);
					array_push($this->orders,$order);
				}
			}
		}
		
		function loadShopcart() {
			global $db;
			$sql = "SELECT * FROM shopcart WHERE cid=" . $this->id . " ORDER BY itemno";
			if($result = $db->sql_query($sql)) {
				while ($row = $db->sql_fetchrow($result)) {
					$article = new shopArticle($row['aid']);
					array_push($this->shopcart,$article);
				}
			}
		}
		
		function verified() {
			global $db;
			
			$sql = "SELECT * FROM shoporder WHERE cid=" . $this->id . " AND status=0 ORDER BY id DESC";
			if($result = $db->sql_query($sql)) {
				while ($row = $db->sql_fetchrow($result)) {
					$order = new shopOrder(0);
					$order->load($row);
					$order->setStatus(1,"Bevestiging");
				}
				
				$result = $db->sql_update("shopcustomer","id=" . $this->id,"verified=1");
				return $result;
			}		
		}
		
		function blocked($set) {
			global $db;
			
			$sql = "SELECT * FROM shoporder WHERE cid=" . $this->id;
			if($result = $db->sql_query($sql)) {				
				$result = $db->sql_update("shopcustomer","id=" . $this->id,"blocked=".$set);
				return $result;
			}		
		}
		
		function sendMail($msgid) {
			global $cfg;
			
			require_once("library/email.cls.php");
			
			$result = false;

			$template = "./templates/email.customer-" . $msgid . ".tmpl";			
			
			$msg = $this->getMailMessage($template);
			$to = $this->name . " <". $this->email . ">";
			$subject = "KlokkieBigBen::Bericht";
			$email = new email();

			$email->sender($cfg->email_info);
			$email->receiver($to);
			$email->subject($subject);
			$email->message($msg);

			if($cfg->testmode) {
				//echo $msg;
			} else {
				$result = $email->send();
			}

			return $result;
		}
/*
		function getShippingArticle($country,$deliver)
		{
			$artno = 0;

			if( $deliver == "V" )
			{
				$artno = 12;
				if($country != "Nederland") $artno = 13; 
			}

			return $artno;
		}
*/
		function getMailMessage($template) {
			global $cfg;
			
			$msg = "file:" . $template . " NOT FOUND";
			
			$handle = fopen($template, "rb");
			if($handle) {
				$msg = fread($handle, filesize($template));
				fclose($handle);
			}	
			
			$msg = str_replace("[[customer::id]]",$this->id,$msg);
			$msg = str_replace("[[customer::name]]",$this->name,$msg);
			$msg = str_replace("[[customer::email]]",$this->email,$msg);
			$msg = str_replace("[[customer::adress]]",$this->adress,$msg);
			$msg = str_replace("[[customer::zipcode]]",$this->zipcode,$msg);
			$msg = str_replace("[[customer::city]]",$this->city,$msg);
			$msg = str_replace("[[customer::country]]",$this->country,$msg);
			$msg = str_replace("[[customer::phone]]",$this->phone,$msg);
			$msg = str_replace("[[customer::pincode]]",$this->pin,$msg);
			$msg = str_replace("[[customer::token]]",$customer->token,$msg);
			$msg = str_replace("[[cfg::siteurl]]",$cfg->siteurl,$msg);
			
			return $msg;
		}	
			
		function update($data) {
			global $clnt, $db;

			$result = array();

			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['data'] = array();

			
			if($this->id == 0) {
				$result['errno'] = 1;
				$result['errmsg'] = "Toevoegen NIET mogelijk";
			} else {
				$where = "id=" . $this->id;

				if(array_key_exists("session",$data)) {
					$set = "session=" . intval($data['session']);
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("pin",$data)) {
					$set = "pin=" . intval($data['pin']);
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
/*
				if(array_key_exists("email",$data)) {
					$set = "email='" . utf8_decode($data['email']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
*/
				if(array_key_exists("name",$data)) {
					$set = "name='" . utf8_decode($data['name']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$where_order = "cid=" . $this->id . " AND status<5";
					$db->sql_update("shoporder",$where_order, $set);
				}
				if(array_key_exists("adress",$data)) {
					$set = "adress='" . utf8_decode($data['adress']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$where_order = "cid=" . $this->id . " AND status<5";
					$db->sql_update("shoporder",$where_order, $set);
				}
				if(array_key_exists("zipcode",$data)) {
					$set = "zipcode='" . strtoupper(utf8_decode($data['zipcode'])) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$where_order = "cid=" . $this->id . " AND status<5";
					$db->sql_update("shoporder",$where_order, $set);
				}
				if(array_key_exists("city",$data)) {
					$set = "city='" . utf8_decode($data['city']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$where_order = "cid=" . $this->id . " AND status<5";
					$db->sql_update("shoporder",$where_order, $set);
				}
				if(array_key_exists("country",$data)) {
					$set = "country='" . utf8_decode($data['country']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$where_order = "cid=" . $this->id . " AND status<5";
					$db->sql_update("shoporder",$where_order, $set);
				}
				if(array_key_exists("phone",$data)) {
					$set = "phone='" . utf8_decode($data['phone']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
					$where_order = "cid=" . $this->id . " AND status<5";
					$db->sql_update("shoporder",$where_order, $set);
				}
				if(array_key_exists("bankno",$data)) {
					$set = "bankno='" . utf8_decode($data['bankno']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("discount",$data)) {
					$discount = floatval($data['discount']);
					if($discount > 0.5) {
						$discount = $discount / 100;
					}
					$set = "discount=" . $discount;
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("conditions",$data)) {
					$set = "conditions='" . utf8_decode($data['conditions']) . "'";
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("active",$data)) {
					$set = "active=" . intval($data['active']);
					$result = $db->sql_update("shopcustomer",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
			}

			return $result;
		}
		
		function fieldset() {
			$set = array(
				array('name'=>'id','type'=>'hidden','value'=>$this->id),
				array('name'=>'name','label'=>'Uw naam','type'=>'text','size'=>25,'value'=>$this->name,'ac'=>'on'),
				array('name'=>'email','label'=>'Uw emailadres','type'=>'text','size'=>30,'value'=>$this->email,'ac'=>'on', 'errmsg' => 'Het door u ingevulde emailadres is niet correct.'),
				array('name'=>'adress','label'=>'Straat/huisnummer','type'=>'text','size'=>40,'value'=>$this->adress,'ac'=>'on', 'errmsg' => 'Het adres is nodig voor het versturen van uw bestelling.'),
				array('name'=>'zipcode','label'=>'Postcode','type'=>'text','size'=>10,'value'=>$this->zipcode,'ac'=>'on', 'errmsg' => 'Uw postcode is nodig voor het versturen van uw bestelling.'),
				array('name'=>'city','label'=>'Woonplaats','type'=>'text','size'=>30,'value'=>$this->city,'ac'=>'on', 'errmsg' => 'Uw woonplaats is nodig voor het versturen van uw bestelling.'),
				array('name'=>'country','label'=>'Land','type'=>'text','size'=>20,'value'=>$this->country,'ac'=>'on', 'errmsg' => 'Het land is nodig voor het versturen van uw bestelling.'),
				array('name'=>'phone','label'=>'Telefoon nr.','type'=>'text','size'=>20,'value'=>$this->phone,'ac'=>'on', 'errmsg' => 'Met uw telefoonnummer kunnen wij om u bereiken bij problemen met uw bestelling.')
			);
		
			return $set;
		}
		
		static function login($email, $pin, $recover=0) {
			global $db;
			
			$result = array();
			$result['errno'] = -1;
			$result['errmsg'] = "";
			$result['id'] = 0;

			$sql = "SELECT * FROM shopcustomer WHERE email='" . strtolower($email) . "'";
			if( ($res = $db->sql_query($sql)) ) {
				if ( $row = $db->sql_fetchrow($res) ) {
					if($pin == intval($row['pin'])) {
						$result['errno'] = 0;
						$result['errmsg'] = "Toegangscode correct.";
						$result['id'] = $row['id'];
						setcookie('ct', $row['token'],time()+365*24*60*60);
					} else {
						$result['errno'] = -2;
						$result['errmsg'] = "Toegangscode niet correct (controleer uw e-mail berichten!).";
						$cust = new shopCustomer($row['id']);
						if(is_object($cust) && ($cust->id > 0)) {
							$cust->sendMail(1);
						}
					}
				} else {
					$result['errno'] = -4;
					$result['errmsg'] = "Emailadres niet gevonden";
				}
			} else {
				$result['errno'] = -3;
				$result['errmsg'] = "";
				$result['sql'] = $sql;
			}

			return $result;
		}

		static function create($data) {
			global $db, $cfg, $clnt;			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
			
			if(array_key_exists("email",$data)) {
				$part = explode('@',$data['email']);
				if(count($part) != 2) {
					$result['errno'] = -11;
					$result['errmsg'] = "Emailadres in niet geldig (@).";
					return $result;
				}
				if((strlen($part[0]) < 3) || (strlen($part[1]) < 5)) {
					$result['errno'] = -11;
					$result['errmsg'] = "Emailadres in niet geldig.";
					return $result;
				}
				$urlpart = explode('.',$part[1]);
				if(count($urlpart) != 2) {
					$result['errno'] = -11;
					$result['errmsg'] = "Emailadres in niet geldig (geen domain extentie).";
					return $result;
				}
				
				$email = utf8_decode(strtolower($data['email']));
				$name = utf8_decode($part[0]);
				$pin = intval($data['pin']);
				
				$sql = "SELECT * FROM shopcustomer WHERE email = '" . $email . "'";
				if( ($res = $db->sql_query($sql)) ) {
					if($row = $db->sql_fetchrow($res) ) {
						$result['errno'] = -10;
						$result['errmsg'] = "Emailadres is al in gebruik.";	
						return $result;
					} 
				}
			
				$pin = rand(123456,987654);
				$token = md5($email . $pin);
			
				$sql = "INSERT INTO shopcustomer(session, email, pin, token, name) VALUES (" . $clnt->sid . ",'" . $email . "'," . $pin . ",'" . $token . "','" . $name . "')";
				$res = $db->sql_query($sql);
				if($res) { 
					$cid = intval($db->sql_nextid());
					if($cid > 0) {
						$result['errmsg'] = "OK";		
						$result['id'] = $cid;
						setcookie('ct', $token,time()+365*24*60*60);
						setcookie('email', $email,time()+365*24*60*60);
						
						$cust = new shopCustomer($cid);
						if(is_object($cust) && ($cust->id > 0)) {
							$cust->update($data);
							$cust->sendMail(0);
						}
					}
				} else {
					$result['errno'] = -2;
					$result['errmsg'] = "create error: " . $sql;
				}
			} else {
				$result['errno'] = -5;
				$result['errmsg'] = "email invalid";
			}

			return $result;
		}
		
		static function _list() {
			global $db,$clnt,$cfg;
	
			$_list = array();
			$sql = "SELECT * FROM shopcustomer ORDER BY email";
			if($res = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($res)) {	
					$customer = new shopCustomer($row['id']);
					$customer->load($row);
					array_push($_list,$customer->o2a());
				}
			}
			
			return $_list;
		}
	} // customer

	class shopArticle {
		var $id;
		var $artcountry;
		var $artcategory;
		var $nvphnr;
		var $pieces;
		var $title;
		var $year;
		var $artquality;
		var $qualitynote;
		var $note;
		var $cw;
		var $cwnote;
		var $price;
		var $startprice;
		var $stock;
		var $image;
		var $artstatus;
		var $delivertime;
		var $keywords;
		var $created;
		var $updated;
		var $onsale;
		var $pricechanged;
		var $numkey;
		
		//
		// Constructor
		//
		function shopArticle($aid) {
			global $db,$clnt,$cfg;
				
			if(!is_object($db)) $db = new database();

			$this->id = 0;
			$this->artcountry = new shopCountry(0);
			$this->artcategory = new shopCategory(0);
			$this->nvphnr = "";
			$this->pieces = 0;
			$this->title = "";
			$this->year = "";
			$this->artstatus = new shopArticleStatus(0);
			$this->arttype = new shopArticleType(0);
			$this->artimage = new shopImage(0);
			$this->artquality = new shopArticleQuality(0);
			$this->qualitynote = "";
			$this->note = "";
			$this->cw = new shopPrice(0);
			$this->cwnote = "";
			$this->price = new shopPrice(0);
			$this->startprice = new shopPrice(0);
			$this->keywords = "";
			$this->delivertime = null;
			$this->created = null;
			$this->updated = 0;
			$this->onsale = 0;
			$this->updated = 0;
			$this->pricechanged = 0;
			$this->numkey = "";
			
			$row = $this->find($aid);
			if($row) {	
				$this->load($row);
			}
		}
		
		function find($aid) {
			global $db,$clnt;

			$where = "id = " . $aid;
			$sql = "SELECT * FROM shoparticle WHERE id=" . $aid;
			if($result = $db->sql_query($sql)) {
				$row = $db->sql_fetchrow($result);
				if($row) {
					return $row;
				}
			}
			
			return false;
		}
		
		function label() {
			$label = $this->artcountry->label;
			if($this->artcategory->id > 0) {
				$label  .= "-" . $this->artcategory->label;
			}
			if($this->nvphnr > 0) {
				$label  .= "-" . $this->nvphnr;
			}
			if(strlen($this->title) > 0) {
				$label  .= "-" . strtolower($this->title);
			}
			
			$label = str_replace(" ","",$label);	
			$label = str_replace("/","",$label);
			$label = str_replace(":","",$label);
			$label = str_replace(";","",$label);
			$label = str_replace(".","",$label);
			
			return $label;
		}

		function artname() {
			$_name = $this->artcountry->description;		
			if($this->artcategory->id > 0) {
				$_name .= " " . $this->artcategory->description;
			}
			if(strlen($this->nvphnr) > 0) {
				$_name .= " " . $this->nvphnr;
			}
			if(strlen($this->title) > 0) {
				$_name .= " " . $this->title;
			}
			if(strlen($this->year) > 0) {
				$_name .= " " . $this->year;
			}
			

			return $_name;
		}
		
		function load($row) {
			global $cfg;
			
			if(is_array($row)) {	
				$this->id = intval($row['id']);
				$this->artcountry = new shopCountry(intval($row['artcountry']));
				$this->nvphnr = utf8_encode($row['nvphnr']);
				$this->pieces = intval($row['pieces']);
				$this->title = utf8_encode($row['title']);
				$this->year = utf8_encode($row['year']);
				$this->note = utf8_encode($row['note']);
				$this->cw = new shopPrice($row['cw']);
				$this->cwnote = utf8_encode($row['cwnote']);
				$this->qualitynote = utf8_encode($row['qualitynote']);
				$this->price = new shopPrice($row['price']);
				$this->startprice = new shopPrice($row['startprice']);
				$this->artcategory = new shopCategory(intval($row['artcategory']));
				$this->artstatus = new shopArticleStatus(intval($row['artstatus']));
				$this->arttype = new shopArticleType(intval($row['arttype']));
				$this->artquality = new shopArticleQuality(intval($row['artquality']));
				$this->artimage = new shopImage(intval($row['artimage']));
				$this->stock = intval($row['stock']);
				$this->delivertime = $row['delivertime'];
				$this->keywords = utf8_encode($row['keywords']);
				
				$this->created = $row['created'];
				if(intval($row['updated']) > 0) {
					$this->updated = strftime("%Y-%m-%d %H:%M:%S",intval($row['updated']));
				} else {
					$this->updated = null;
				}
				if(intval($row['onsale']) > 0) {
					$this->onsale = strftime("%Y-%m-%d %H:%M:%S",intval($row['onsale']));		
				} else {
					$this->onsale = null;
				}
				
				$this->pricechanged = intval($row['pricechanged']);				
				$this->numkey = intval($row['numkey']);
			}
		}
		
		static function create($id) {
			global $db, $cfg;
			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;
			
			$sql  = "INSERT INTO shoparticle (artcountry) VALUES ($id)";
			$res = $db->sql_query($sql);
			if($res) { 
				$aid = intval($db->sql_nextid());
				if($aid > 0) {
					$result['errmsg'] = "OK";		
					$result['id'] = $aid;
				}
			} else {
				$result['errno'] = -1;
				$result['errmsg'] = "INSERT ERROR " . $sql;
			}
			
			return $result;
		}
	
		function o2a() {
			global $db;
			
			$_arr = array(
				'id' 			=> 	$this->id,
				'artname' 		=>	$this->artname(),
				'country' 		=>	$this->artcountry->o2a(),				
				'nvphnr' 		=>	$this->nvphnr,
				'title' 		=>	$this->title,
				'year' 			=>	strval($this->year),
				'note' 			=>	$this->note,
				'pieces' 		=>	$this->pieces,
				'price' 		=>	$this->price->o2a(),
				'category' 		=>	$this->artcategory->o2a(),
				'status' 		=>	$this->artstatus->o2a(),
				'type' 			=>	$this->arttype->o2a(),
				'quality'		=>	$this->artquality->o2a(),
				'qualitynote'	=>	$this->qualitynote,
				'cw' 			=>	$this->cw->o2a(),
				'cwnote'		=>	$this->cwnote,
				'stock' 		=>	$this->stock,
				'delivertime' 	=>	$this->delivertime,
				'keywords' 		=>	$this->keywords,
				'image' 		=>	$this->artimage->o2a(),
				'created' 		=>	$this->created,
				'updated' 		=>	$this->updated,
				'onsale' 		=>	$this->onsale,
				'pricechanged'	=>	$this->pricechanged,
				'numkey' 		=>	$this->numkey
			);		
			
			return $_arr;
		}
		
		function numkey() {
			$country = intval($this->artcountry->id);
			$category = intval($this->artcategory->id);
			$nvphnr = 9999;
			$str = trim($this->nvphnr);
			$str = str_replace(",","-",$str);
			$str = str_replace("/","-",$str);
			$part= explode("-",$str);
			if(count($part) > 0) {
				$str = $part[0];
				if(is_numeric($str)) {
					$nvphnr = intval($str);
				} else {
					$num = "";
					for($i=0;$i<strlen($str);$i++) {
						$ch = substr($str,$i,1);
						if((ord($ch) >= 48) && (ord($ch) <= 57)) {
							$num .= $ch;
						} else {
							if(strlen($num) > 0) {
								break;
							}
						}
					}
					if(is_numeric($num)) {
						$nvphnr = intval($num);
					}
				} 
			}
			return ((($country * 100) + $category) * 10000) + $nvphnr;
		}

		function update($data) {
			global $clnt, $db;

			$result = array();

			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = $this->id;
			$result['data'] = array();

			if(!$clnt->adminEditor) {
				$result['errno'] = -1;
				$result['errmsg'] = "Geen authorisatie";

				return $result;
			}

			if($this->id == 0) {
				if(!$clnt->adminMaster) return "Toevoegen NIET mogelijk";

				$sql = "INSERT INTO shoparticle (label) VALUES ('new')";
				$res = $db->sql_query($sql);
				if($res) { 
					$this->id = intval($db->sql_nextid());
				}	
			}

			$updated = ",updated=" . time();

			if($this->id > 0) {
				$where = "id=" . $this->id;
				if(array_key_exists("title",$data)) {
					$title = utf8_decode($data['title']);
					if(strlen($title) == 0 ) {
						$title = $this->title;
					}
					$set = "title='" . $title . "'" . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("note",$data)) {
					$this->note = utf8_decode($data['note']);
					$set = "note='" . $this->note . "'" . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					} 
				}
				
				if(array_key_exists("artcategory",$data)) {
					$this->artcategory = new shopCategory(intval($data['artcategory']));
					$set = "artcategory=" . $this->artcategory->id . ",numkey=" . $this->numkey() . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("artstatus",$data)) {
					$newstatus = intval($data['artstatus']);
					
					$msg = "Admin (was=" . $this->artstatus->id . ")";
					$result = $this->setStatus($newstatus,$msg);
/*
					$updated = ",updated=" . time();
					$set = "artstatus=" . $artstatus . $updated;
					if($artstatus == 2) {
						$set .= ",onsale=" . time();
					}
					$result = $db->sql_update("shoparticle",$where,$set);
*/
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("arttype",$data)) {
					$arttype = intval($data['arttype']);
					$set = "arttype=" . $arttype . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("artquality",$data)) {
					$artquality = intval($data['artquality']);
					$set = "artquality=" . $artquality . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("qualitynote",$data)) {
					$this->qualitynote = utf8_decode($data['qualitynote']);
					$set = "qualitynote='" . $this->qualitynote . "'" . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					} 
				}
				
				if(array_key_exists("price",$data)) {
					$price = round(floatval(str_replace(",",".",$data['price'])),2);
					$set = "price=" . $price . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("cw",$data)) {
					$cw = round(floatval(str_replace(",",".",$data['cw'])),2);
					$set = "cw=" . $cw . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				
				if(array_key_exists("cwnote",$data)) {
					$this->cwnote = utf8_decode($data['cwnote']);
					$set = "cwnote='" . $this->cwnote . "'" . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					} 
				}
				
				if(array_key_exists("nvphnr",$data)) {
					$this->nvphnr = $data['nvphnr'];
					$set = "nvphnr='" . $this->nvphnr . "',numkey=" . $this->numkey() . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}
				}

				if(array_key_exists("year",$data)) {
					$year = $data['year'];
					$set = "year='" . $year . "'" . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}

				if(array_key_exists("artcountry",$data)) {
					$this->artcountry = new shopCountry(intval($data['artcountry']));
					$set = "artcountry=" . $this->artcountry->id . ",numkey=" . $this->numkey() . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}			

				if(array_key_exists("artimage",$data)) {
					$set = "artimage=" . intval($data['artimage']);
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}				
		
				if(array_key_exists("keywords",$data)) {
					$set = "keywords='" . utf8_decode($data['keywords']) . "'" . $updated;
					$result = $db->sql_update("shoparticle",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}		
				
				if(array_key_exists("images",$data)) {
					$imagelist = utf8_decode($data['images']);
					$result['errmsg'] = $imagelist;
					$images = split(",",$imagelist);
					
					foreach($images as $img) {
						list($aid,$iid,$sort) = split(":",$img);
						if($aid == $this->id) {
							$set = "article=" . intval($aid) . " ,sortorder=" . $sort;
							$result = $db->sql_update("shopimage","id=".$iid,$set);
						
							if($sort == 1) {
								$set = "artimage=" . intval($iid);
								$db->sql_update("shoparticle",$where,$set);
							}  
						}
					}
				}
			}

			return $result;
		}
		
		function setStatus($status,$msg) {
			global $db;

			$result['errno'] = 0;
			$result['errmsg'] = "OK";		

			
			$now = date('Y-m-d H:i:s');

			switch($status) {
				case 0: // Nieuw
					break;
				
				case 1: // Voorraad
					break;
				
				case 2: // Te koop
					$sql = "UPDATE shoparticle SET startprice=" . $this->price->val . " WHERE id=" . $this->id;
					$db->sql_query($sql);
					break;
				
				case 3: // Besteld
					break;
				
				case 4: // Verkocht
					break;
				
				case 5: // Niet verkocht
					break;
				
				default:
					$result['errno'] = -1;
					$result['errmsg'] = "Nieuwe status onbekend";
					return $result;
			}
			
			$sql = "UPDATE shoparticle SET artstatus=" . $status . " ,updated=" . time() . " WHERE id=" . $this->id;
			$res = $db->sql_query($sql);
			if($res) {
				$this->artstatus = new shopArticleStatus($status);
				$sql = "INSERT INTO shoparticlechange (aid,status_new,message) VALUES (" . $this->id . "," . $status . ",'" . $msg . "')";
				$db->sql_query($sql);			
			} else {
				$result['errno'] = -2;
				$result['errmsg'] = "Fout bij status wijziging.";
			}
			
			return $result;
		}
		
		function images($all=true) {
			global $db;
			
			$list = array();
			if($all) {	
				$sql = "SELECT * FROM shopimage WHERE article = " . $this->id . " ORDER BY sortorder";
			} else {
				$sql = "SELECT * FROM shopimage WHERE article = " . $this->id . " AND active=1 ORDER BY sortorder";
			}

			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result)) {	
					$img = new shopImage(0);
					if(is_object($img)) {
						$img->load($row);
						array_push($list,$img);
					}
				}
			}
				
			return $list;
		}
		
		static function _list($inp) {
			global $db,$clnt,$cfg;
			
			$sort = $inp['sort'];
			$where = array();
			
			if(array_key_exists("artcountry",$inp)) {
				$arg = explode('-' ,$inp['artcountry']);
				if(count($arg) == 2) {
					array_push($where,"artcountry>=".intval($arg[0]));
					array_push($where,"artcountry<=".intval($arg[1]));
				} else {
					$x = intval($inp['artcountry']);
					if($x > 0) {
						array_push($where,"artcountry=".$x);
					}
				}
			}
			
			if(array_key_exists("artcategory",$inp)) {
				$arg = explode('-' ,$inp['artcategory']);
				if(count($arg) == 2) {
					array_push($where,"artcategory>=".intval($arg[0]));
					array_push($where,"artcategory<=".intval($arg[1]));
				} else {
					$x = intval($inp['artcategory']);
					if($x > 0) {
						array_push($where,"artcategory=".$x);
					}
				}
			}
			
			if(array_key_exists("artstatus",$inp)) {
				$arg = explode('-' ,$inp['artstatus']);
				if(count($arg) == 2) {
					array_push($where,"artstatus>=".intval($arg[0]));
					array_push($where,"artstatus<=".intval($arg[1]));
				} else {
					$x = intval($inp['artstatus']);
					if($x >= 0) {
						array_push($where,"artstatus=".$x);
					}
				}
			}
			if(array_key_exists("artquality",$inp)) {
				$arg = explode('-' ,$inp['artquality']);
				if(count($arg) == 2) {
					array_push($where,"artquality>=".intval($arg[0]));
					array_push($where,"artquality<=".intval($arg[1]));
				} else {
					$x = intval($inp['artquality']);
					if($x >= 0) {
						array_push($where,"artquality=".$x);
					}
				}
			}
			
			if(array_key_exists("arttype",$inp)) {
				$arg = explode('-' ,$inp['arttype']);
				if(count($arg) == 2) {
					array_push($where,"arttype>=".intval($arg[0]));
					array_push($where,"arttype<=".intval($arg[1]));
				} else {
					$x = intval($inp['arttype']);
					if($x >= 0) {
						array_push($where,"arttype=".$x);
					}
				}
			}
			
			$words = "";
			if(array_key_exists("words",$inp)) {
				$words = $inp['words'];
			}

			$sql = "SELECT * FROM shoparticle";

			for($i=0;$i<count($where);$i++) {
				if($i == 0) {
					$sql .= " WHERE " . $where[0];
				} else {
					$sql .= " AND " . $where[$i];
				}
			}
			
			$sql .= " ORDER BY id DESC";

			$order = array();
			$_list = array();
			
			if($res = $db->sql_query($sql)) {		
				while($row = $db->sql_fetchrow($res)) {
				
					$art = new shopArticle($row['id']);
					$art->load($row);
					
					//$nr = implode("-",explode(" ",trim($art->nvphnr)));
					$nr = trim($art->nvphnr);
					foreach(explode(",",$nr) as $part) {
						foreach(explode("-",$part) as $num) {
							$nr = preg_replace("/[^0-9]/", "",$num);
							if(intval($nr) > 0) break;
						}
						if(intval($nr) > 0) break;
					}
					
					if(intval($nr) == 0) {
						$nr = 999999;
					}
				
					switch($sort) {
						case 'abc':
						case 'zyx':
							array_push($order, $art->artcountry->description . $art->artcategory->description . $art->title . $art->nvphnr);
							break;
						case '123':
						case '321':
							array_push($order, intval($nr)); //. " " . $art->artcountry->description . $art->artcategory->description. $art->title);		
							break;
						default:
							array_push($order, $row['id']);		
							break;
					}
					
					array_push($_list,$art->o2a());
				}
			}

			switch($sort) {
				case 'abc':
					array_multisort($order,SORT_ASC,SORT_STRING,$_list,SORT_ASC,SORT_REGULAR);
					break;
				case 'zyx':
					array_multisort($order,SORT_DESC,SORT_STRING,$_list,SORT_ASC,SORT_REGULAR);
					break;
				case '123':
					array_multisort($order,SORT_ASC,SORT_NUMERIC,$_list,SORT_ASC,SORT_REGULAR);
					break;
				case '321':
					array_multisort($order,SORT_DESC,SORT_NUMERIC,$_list,SORT_ASC,SORT_REGULAR);
					break;
				default:
					break;
			}
	
			return $_list;			
		}
		
	} // class shopArticle
	
	class shopImage {
		var $id;
		var $title;
		var $description;
		var $label;
		var $src;
		var $keywords;
		var $url;
		var $thumbnail;
		var $type;
		var $active;
		var $created;
		var $updated;

		function shopImage($id) {
			global $cfg, $db;

			if(!is_object($db)) $db = new database();

			$this->id = $id;

			$sql = "SELECT * FROM shopimage WHERE id = " . $id;
			if($result = $db->sql_query($sql)) {
				if($row = $db->sql_fetchrow($result)) {
					$this->load($row);
				} else {
					$this->id = 0;
					$this->title = "Afbeelding titel ...";
					$this->description = "Afbeelding beschrijving ...";
				}
			}
			
			$arg = explode("/",$this->src);
			$i = count($arg) - 1;
			if($i >= 0) {
				$this->thumbnail = $cfg->image->shop_thumbnail_path . $arg[$i];
			} else {
				$this->thumbnail = $cfg->image->shop_thumbnail_path . $this->src;			
			}
		}
		
		function load($row) {
			global $cfg, $db;
			
			if(is_array($row)) {
				$this->id = intval($row['id']);
				
				$this->title = utf8_encode($row['title']);
				$this->description = utf8_encode($row['description']);
				$this->label = $row['label'];
				$this->type = $row['type'];
				$this->keywords = $row['keywords'];
				$this->src = $row['src'];
				$this->active = intval($row['active']);
				$this->page = intval($row['page']);
				$this->sortorder = intval($row['sortorder']);
				$this->created = $row['created'];
				$this->updated = strftime("%d-%m-%y %H:%M:%S",intval($row['updated']));
			}
			
			$arg = explode("/",$this->src);
			$i = count($arg) - 1;
			if($i >= 0) {
				$this->thumbnail = $cfg->image->shop_thumbnail_path . $arg[$i];
			} else {
				$this->thumbnail = $cfg->image->shop_thumbnail_path . $this->src;			
			}
		}
		
		static function create($imgfile,$aid) {
			global $db, $cfg;
			
			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			$result['id'] = 0;

			$ftype = filetype($imgfile);
			if($ftype != "file") {
				$result['errno'] = -1;
				$result['errmsg'] = "'" . $imgfile ."' is geen bestand! (" . $ftype .")";
				return $result;
			}
			
			$imgtype = mime_content_type($imgfile);
			$ext = $cfg->image->validTypes[$imgtype];
			if($ext == NULL) {
				$result['errno'] = -2;
				$result['errmsg'] = "'" . $imgfile ."' heeft geen geldig type (" . $imgtype . ")";
				return $result;
			}
				
			$iid = 0;
			$so = 1;
			
			$sql = "SELECT sortorder from shopimage WHERE article = " . $aid . " ORDER BY sortorder DESC";
			$res = $db->sql_query($sql);
			if( $row = $db->sql_fetchrow($res) )	{
				$so = intval($row['sortorder']) + 1;
			}
		
			$fstr = explode("/",$imgfile);
			$n = count($fstr);
			$fname = explode(".",$fstr[$n-1]);
			$label = $fname[0];
			$title = ucfirst($label);
			$description = "...";
			

			$sql = "INSERT INTO shopimage (title, description, label, article, sortorder) VALUES ('$title','$description','$label','$aid','$so')";
			$res = $db->sql_query($sql);
			if($res) { 
				$iid = intval($db->sql_nextid());
			}

			if($iid > 0) {
				$imgsrc =  $cfg->image->shop_image_path . $iid . "_" . $label . "." . $ext; 
				
				if(shopImage::convert($imgfile,$imgsrc,$imgtype)) {
					$where = "id=" . $iid;
					$set = "src='" . $imgsrc . "',type='" . $imgtype . "',updated=" . time()  ;
					$res = $db->sql_update("shopimage",$where,$set);
				} else {
					/* TODO: REMOVE database record id = iid */
					$result['errno'] = -3;
					$result['errmsg'] = "Conversie '" . $imgfile ."' naar '" . $imgsrc . "' is geen mogelijk! (" . $imgtype . ")";				
				}
			}
			$result['errmsg'] = "OK";		
			$result['id'] = $iid;
			
			return $result;
		}

		static function convert($imgsrc,$imgdest,$imgtype) {
			global $cfg, $db, $clnt, $_GET, $id;

			$size = filesize($imgsrc);
			list($width, $height) = getimagesize($imgsrc);

			if($height > 0) {
				$ratio = $width / $height;
			} else {
				$ratio = 99;
			}
			
			$new_width = $width;
			$new_height = $height;

			if($ratio >= 1.0) {
				if($width > $cfg->image->max_width) {
					$new_width = $cfg->image->max_width;
					$new_height = round($cfg->image->max_width / $ratio,0);
				}
				$thumb_width = $cfg->image->max_thumb_width;
				$thumb_height = round($cfg->image->max_thumb_width / $ratio,0);	
			} else {
				if($height > $cfg->image->max_height ) {
					$new_height = $cfg->image->max_height;
					$new_width = round($cfg->image->max_height * $ratio,0);
				}								
				$thumb_height = $cfg->image->max_thumb_height;
				$thumb_width = round($cfg->image->max_thumb_height * $ratio,0);	
			}
			
			$arg = split("/",$imgdest);
			$i = count($arg) - 1;
			if($i >= 0) {
				$thumb_src = $cfg->image->shop_thumbnail_path . $arg[$i];
			} else { 
				$thumb_src = $cfg->image->shop_thumbnail_path . $imgdest;
			}
			
			switch($imgtype) {
				case "image/jpeg":
					$res = shopImage::copyJPEG($imgsrc, $width, $height, $imgdest, $new_width, $new_height, $cfg->image->max_size);
					if($res > 0) {
						shopImage::copyJPEG($imgdest, $new_width, $new_height, $thumb_src, $thumb_width, $thumb_height, $cfg->image->max_thumb_size);
					}
					break;
				case "image/png":
					$res = shopImage::copyPNG($imgsrc, $width, $height, $imgdest, $new_width, $new_height, $cfg->image->max_size);
					if($res > 0) {
						shopImage::copyPNG($imgdest, $new_width, $new_height, $thumb_src, $thumb_width, $thumb_height, $cfg->image->max_thumb_size);
					}
					break;
				case "image/gif":
					$res = shopImage::copyGIF($imgsrc, $width, $height, $imgdest, $new_width, $new_height, $cfg->image->max_size);
					if($res > 0) {
						shopImage::copyGIF($imgdest, $new_width, $new_height, $thumb_src, $thumb_width, $thumb_height, $cfg->image->max_thumb_size);
					}
					break;
				default:
					$res = 0;
					break;
			}
			
			return $res;
		}
		
		static function copyJPEG($src, $width, $height, $new_src, $new_width, $new_height, $max_size) {
			$quality = 0;
			
			$new_image = imagecreatetruecolor($new_width, $new_height);
			if($new_image) {

				$image = imagecreatefromjpeg($src);
				if($image) {

					imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					
					$quality = 99;
					while(imagejpeg($new_image,$new_src,$quality)) {
						clearstatcache();
						$new_size = filesize($new_src);
						if($new_size < $max_size) break;
						if(($new_size / $max_size) > 1.5) {
							$quality-=5;
						} else {
							$quality--;
						}
						if($quality < 20) break;
						//echo "Q=" . $quality . " SZ=" . $new_size . "\n";
					}

					imagedestroy($image);
				}	

				imagedestroy($new_image);
			}
				
			return $quality;
		}

		static function copyPNG($src, $width, $height, $new_src, $new_width, $new_height, $max_size) {
			$quality = 0;

			$new_image = imagecreatetruecolor($new_width, $new_height);
			if($new_image) {
				$image = imagecreatefrompng($src);
				if($image) {
					$res = imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					$quality = 9;
					while($res = imagepng($new_image,$new_src,$quality)) {
						clearstatcache();
						$new_size = filesize($new_src);
						if($new_size < $max_size) break;
						$quality--;
						if($quality < 1) break;
						//echo "Q=" . $quality . " SZ=" . $new_size . "\n";
					}
					imagedestroy($image);
				}
				imagedestroy($new_image);
			} else {
				var_dump($new_width);
				var_dump($new_height);
			}
				
			return $quality;
		}
		
		static function copyGIF($src, $width, $height, $new_src, $new_width, $new_height, $max_size) {
			$quality = 0;
			
			$new_image = imagecreatetruecolor($new_width, $new_height);
			if($new_image) {
				$image = imagecreatefromgif($src);
				if($image) {
					imagecopyresized($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					
					if(imagegif($new_image,$new_src)) {
						$quality = 100;
					}
					imagedestroy($image);
				}	
				imagedestroy($new_image);
			}
				
			return $quality;
		}
	

		function selectList() {
			global $cfg,$db;

			$_html = "";
			$sel = "";
			if($this->id == 0) $sel = "selected='selected'";
			
			$size = $cfg->image->size("tile");
			$perc = intval($size['percentage']);
			if($perc ==0) $perc = 100;
			//$perc = 40;
			
			$sql = "SELECT * FROM shopimage WHERE active = 1 ORDER BY id DESC";
			if($result = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($result))
				{	
					$id = intval($row[id]);
					$image = new shopImage($id);

					$arg = split("/",$image->src);
					$src = $arg[0] . "/thumbnails/" . $arg[1];
					if(!file_exists($src)) {
						$src = $image->src;
					}
					$_html .= "<option data-img-src='" . $src . "' value='" . $id . "'>" . $image->title . "</option>";

				}
			}

			return $_html;
		}

		function o2a() {
					
			$_arr = array(
				'id' 			=> 	$this->id,
				'title' 		=>	$this->title,				
				'description' 	=>	$this->description,
				'label' 		=>	$this->label,
				'type' 			=>	$this->type,
				'src' 			=>	$this->src ,
				'thumbnail' 	=>	$this->thumbnail,
				'keywords' 		=>	$this->keywords,
				'page' 			=>	$this->page,
				'sortorder'		=>	$this->sortorder,
				'active' 		=>	$this->active,
			);		
			
			return $_arr;
		}

		function update($data) {
			global $clnt, $db;

			$result = array();
			$result['errno'] = 0;
			$result['errmsg'] = "";
			
			$updated = ",updated=" . time();
			
			if($this->modify) {
				$result['errno'] = -1;
				$result['errmsg'] =  "Geen authorisatie";

				return $result;
			}

			if($this->id == 0) {
				if(!$this->modify) return "Toevoegen NIET mogelijk";

				$sql = "INSERT INTO shopimage (label) VALUES ('new')";
				$res = $db->sql_query($sql);
				if($res) { 
					$this->id = intval($db->sql_nextid());
				}	
			}

			if($this->id > 0) {
				$where = "id=" . $this->id;
				
				if(array_key_exists("title",$data)) {
					$set = "title='" . utf8_decode($data['title']) . "'" . $updated;
					$result = $db->sql_update("shopimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("description",$data)) {
					$set = "description='" . utf8_decode($data['description']) . "'" . $updated;
					$result = $db->sql_update("shopimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("src",$data)) {
					$set = "src='" . utf8_decode($data['src']) . "'" . $updated;
					$result = $db->sql_update("shopimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("label",$data)) {
					$set = "label='" . utf8_decode($data['label']) . "'" . $updated;
					$result = $db->sql_update("shopimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("type",$data)) {
					$set = "type='" . $data['type'] . "'";
					$result = $db->sql_update("shopimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("keywords",$data)) {
					$set = "keywords='" . $data['keywords'] . "'";
					$result = $db->sql_update("shopimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("article",$data)) {
					$set = "article='" . $data['article'] . "'";
					$result = $db->sql_update("shopimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("sortorder",$data)) {
					$set = "sortorder='" . $data['sortorder'] . "'";
					$result = $db->sql_update("shopimage",$where,$set) . $updated;
					if($result['errno'] != 0) {
						return $result;
					}  
				}
				if(array_key_exists("active",$data)) {
					$set = "active=" . (($data['active'] == "on") ? 1 : 0) . $updated; 
					$result = $db->sql_update("shopimage",$where,$set);
					if($result['errno'] != 0) {
						return $result;
					}  
				}
			}

			return $result;
		}

    } // class shopImage

	class shopCountry {
		var $id;
		var $label;
		var $description;
		var $parent;
		var $keywords;
		var $webpage;
		var $sortorder;
		
		//
		// Constructor
		//
		function shopCountry($cid) {
			global $db,$clnt,$cfg;

			if(!is_object($db)) $db = new database();

			$this->id = 0;
			$this->description = "";
			$this->label = "";
			$this->keywords = "";
			$this->active = 1;
			$this->webpage = 0;
			$this->sortorder = 1;
			
			$row = $this->find($cid);
			if($row) {	
				$this->load($row);
			}
		}
		
		function find($cid) {
			global $db,$clnt;

			if(is_numeric($cid)) {
				if($cid == 0) {
					return false;
				}
				
				$where = "id = " . $aid;
				$sql = "SELECT * FROM shopcountry WHERE id=" . $cid;
				if($result = $db->sql_query($sql)) {
					$row = $db->sql_fetchrow($result);
					if($row) {
						return $row;
					}
				}
			} 
			
			return false;
		}
		
		function load($row) {
			global $cfg;
			
			if(is_array($row)) {	
				$this->id = intval($row['id']);
				$this->description = utf8_encode($row['description']);
				$this->label = utf8_encode($row['label']);
				$this->keywords = utf8_encode($row['keywords']);
				$this->sortorder = intval($row['sortorder']);
				$this->webpage = intval($row['webpage']);
				$this->active = intval($row['active']);
			}
		}
		
		function o2a() {
			global $db;
		
			$_arr = array(
				'id' 			=> 	$this->id, 
				'description' 	=>	$this->description,
				'sortorder' 	=>	$this->sortorder,
				'label' 		=>	$this->label,
				'keywords' 		=>	$this->keywords,
				'webpage' 		=>	$this->webpage,
				'active' 		=>	$this->active
			);		
			
			return $_arr;
		}
		
		static function _list($parent = 0) {
			global $db,$clnt,$cfg;
	
			$_list = array();
			$sql = "SELECT * FROM shopcountry ORDER BY sortorder";
			if($res = $db->sql_query($sql)) {
				while($row = $db->sql_fetchrow($res)) {	
					$country = new shopCountry($row['id']);
					$country->load($row);
					array_push($_list,$country->o2a());
				}
			}
			
			return $_list;
		}
		
	} // class shopCountry
	
	class shopCategory {
		var $id;
		var $label;
		var $description;
		var $parent;
		var $country;
		var $keywords;
		
		//
		// Constructor
		//
		function shopCategory($cid) {
			global $db,$clnt,$cfg;

			if(!is_object($db)) $db = new database();

			$this->id = 0;
			$this->description = "";
			$this->label = "";
			$this->parent = 0;
			$this->country = "";
			$this->keywords = "";
			$this->active = 1;
			
			$row = $this->find($cid);
			if($row) {	
				$this->load($row);
			}
		}
		
		function find($cid) {
			global $db,$clnt;

			if(is_numeric($cid)) {
				if($cid == 0) {
					return false;
				}
				
				$where = "id = " . $aid;
				$sql = "SELECT * FROM shopcategory WHERE id=" . $cid;
				if($result = $db->sql_query($sql)) {
					$row = $db->sql_fetchrow($result);
					if($row) {
						return $row;
					}
				}
			} 
			
			return false;
		}
		
		function load($row) {
			global $cfg;
			
			if(is_array($row)) {	
				$this->id = intval($row['id']);
				$this->description = utf8_encode($row['description']);
				$this->label = utf8_encode($row['label']);
				$this->parent = intval($row['parent']);
				$this->sortorder = intval($row['sortorder']);
				$this->webpage = intval($row['webpage']);
				$this->active = intval($row['active']);
			}
		}
		
		function o2a() {
			global $db;
		
			$_arr = array(
				'id' 			=> 	$this->id, 
				'description' 	=>	$this->description,
				'parent' 		=>	$this->parent,
				'sortorder' 	=>	$this->sortorder,
				'label' 		=>	$this->label,
				'webpage' 		=>	$this->webpage,
				'active' 		=>	$this->active
			);		
			
			return $_arr;
		}
		
		static function _list($parent = 0) {
			global $db,$clnt,$cfg;
	
			$_list = array();
			if($parent > 0) {
				$sql = "SELECT * FROM shopcategory WHERE parent=" . $parent ." ORDER BY sortorder";
			} else {
				$sql = "SELECT * FROM shopcategory ORDER BY id";
			}
			if($res = $db->sql_query($sql)) {
				
				while($row = $db->sql_fetchrow($res)) {	
					$cat = new shopCategory($row['id']);
					$cat->load($row);
					$aCat = $cat->o2a();
					if($parent > 0) {
						$children = shopCategory::_list($cat->id);
						$aCat['children'] = $children;	
					}
					array_push($_list,$aCat);
				}
			}
			return $_list;
		}
		
	} // class shopCategory
	
	class shopArticleStatus {
		
		var $id;
		var $name;
		
		function shopArticleStatus($id) {
		
			$arr = shopArticleStatus::_list();
		
			$this->id = $id;
			$this->name = $arr[$id];
		}
		
		static function _list() {
			return array("Nieuw","Voorraad","Te koop","Besteld","Verkocht","Niet verkocht");
		}
		
		function o2a() {
			global $db;
		
			$_arr = array(
				'id' 	=> 	$this->id, 
				'name' 	=>	$this->name
			);		
			
			return $_arr;
		}
		
	} // class shopArticleStatus
	
	class shopArticleType {
		
		var $id;
		var $name;
		
		function shopArticleType($id) {
		
			$arr = shopArticleType::_list();
		
			$this->id = $id;
			$this->name = $arr[$id];
		}
		
		static function _list() {
			return array("Normaal","Aanbieding","Veiling");
		}
		
		function o2a() {
			global $db;
		
			$_arr = array(
				'id' 	=> 	$this->id, 
				'name' 	=>	$this->name
			);		
			
			return $_arr;
		}
		
	} // class shopArticleType
	
	class shopArticleQuality {
		
		var $id;
		var $name;
		
		function shopArticleQuality($id) {
		
			$arr = shopArticleQuality::_list();
		
			$this->id = $id;
			$this->name = $arr[$id];
		}
		
		static function _list() {
			return array("Alle kwaliteiten", "Gestempeld", "Ongebruikt", "Postfris", "Gevarieerd");
		}
		
		function o2a() {
			global $db;
		
			$_arr = array(
				'id' 	=> 	$this->id, 
				'name' 	=>	$this->name
			);		
			
			return $_arr;
		}
		
	} // class shopArticleQuality
	
	class shopPrice {
		
		var $val;
		var $html;
		
		function shopPrice($val) {
			$this->set($val);
			$this->html = str_replace(".",",",sprintf("&euro; %01.2f" ,$val));
		}
		
		function add($val) {
			$this->val += $val;
			$this->html = str_replace(".",",",sprintf("&euro; %01.2f" ,$this->val));
		}
		
		function set($val) {
			$this->val = $val;
			$this->html = str_replace(".",",",sprintf("&euro; %01.2f" ,$this->val));
		}
		
		function o2a() {
			global $db;
		
			$_arr = array(
				'val' 	=> 	$this->val, 
				'html' 	=>	$this->html
			);		
			
			return $_arr;
		}
		
	} // class shopPrice
	
} // defined

?>
