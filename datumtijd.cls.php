<?php
// VERSION: 2 (02/07/2013)

if(!defined("DATUMTIJD_LAYER"))
{
        define("DATUMTIJD_LAYER","datumtijd");

        class datumtijd
        {
		var $dtm;
		var $tm;

		function datumtijd($t = 0)
		{
			if(is_string($t))
			{
				$this->dtm = strtotime($t);
			}
			else
			{
				if($t == 0) 
				{
					$this->dtm = time();
				}
				else
				{
					$this->dtm = $t;
				}
			}

			$this->tm = localtime($this->dtm);		
		}

		function jaar()
		{
			return (1900 + $this->tm[5]);
		}

		function maand()
		{
			return ($this->tm[4] + 1);
		}

		function dag()
		{
			return ($this->tm[3]);
		}

		function dvdw()
		{
			return intval($this->tm[6]);
		}

		function uur()
		{
			return intval($this->tm[2]);
		}

		function minuut()
		{
			return intval($this->tm[1]);
		}

		function seconde()
		{
			return intval($this->tm[0]);
		}

		function week()
		{
			$day1 = $this->startWeek1($this->jaar());

			$days = ($this->dtm - $day1) / ( 24 * 60 * 60 );

			$wk = intval($days / 7) + 1;

			return $wk;
		}

		function startWeek1($jaar = 0)
		{
			if($jaar == 0)
			{
				$d1 = mktime(0,0,0,1,1,$this->jaar());
			}
			else
			{
				$d1 = mktime(0,0,0,1,1,$jaar);
			}

			$tm_d1 = localtime($d1);

			$wd = $tm_d1[6];

			if($wd > 3)
			{
				$w1 = $d1 + ((7 - $wd) * 24 * 60 * 60 );
			}
			else
			{
				$w1 = $d1 - ($wd * 24 * 60 * 60 );
			}

			return $w1;
		}

		function eersteDagVanDeWeek($week,$jaar = 0)
		{
			$week1 = $this->startWeek1($jaar);

			$day1 = $week1 + (($week -1) * ( 7 * 24 * 60 * 60));

			return $day1;	
		}

		function naamVanDeDag()
		{
			switch($this->tm[6])
			{
				case 1: $descr = "Maandag";
					break; 
				case 2: $descr = "Dinsdag";
					break; 
				case 3: $descr = "Woensdag";
					break; 
				case 4: $descr = "Donderdag";
					break; 
				case 5: $descr = "Vrijdag";
					break; 
				case 6: $descr = "Zaterdag";
					break; 
				default: $descr = "Zondag";
					break; 
			}			

			return $descr;
		}

		function eersteDagVanDeMaand($maand,$jaar = 0)
		{
			$d1 = mktime(0,0,0,$maand,1,$jaar);

			return $d1;	
		}

		function naamVanDeMaand()
		{
			$months = Array("Januari", "Februari", "Maart", "April", "Mei", "Juni", "Juli", "Augustus", "September", "Oktober", "November", "December");

			$m = $this->tm[4];

			return $months[$m];
		}

		function laatsteDagVanDeMaand()
		{
		        $days = 31;

			if( $this->maand() == 2 )
        		{
				if ( (($this->jaar() % 4 == 0) && ($this->jaar() % 100 != 0)) || ($this->jaar() % 400 == 0) )
            			{
					$days = 29;
		                } else {
					$days = 28;
		                }
       		 	}

			if( ($this->maand() == 4) || ($this->maand() == 6) || ($this->maand() == 9) || ($this->maand() == 11) )
			{
				$days = 30;
			}

			return $days;
		}

		function dagNummer()
		{
			return ($this->jaar() * 10000) + ($this->maand() * 100) + $this->dag();
		}

		function datumString($full = false)
		{
			if($full)
			{
				$str = $this->dag() . " " . $this->naamVanDeMaand() . " " . $this->jaar();
			}
			else
			{	
				$str= $this->dag() . "/" . $this->maand() . "/" . $this->jaar();
			}

			return $str;
		}

		function tijdString()
		{
			return sprintf("%02d:%02d:%02d",$this->uur(), $this->minuut(), $this->seconde());
		}

		function dtmFormat($frmstr = "%Y-%m-%d")
		{
			return strftime($frmstr,$this->dtm);
		}

	} // class

} // define

?>
