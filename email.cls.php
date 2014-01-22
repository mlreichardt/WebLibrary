<?php
// VERSION: 1 (21/01/2012)

if(!defined("EMAIL_LAYER"))
{
	define("EMAIL_LAYER","email");

	class email	{
		var $from;
		var $to;
		var $sub;
		var $msg;
		var $frm;
		var $html;

		function email($x = "html") {
			$this->frm = $x;
		}

		function subject($x) {
			$this->sub = $x;
		}

		function message($x) {
			$this->msg = $x;
		}

		function sender($x) {
			$this->from = $x;
		}

		function receiver($x) {
			$this->to = $x;
		}

		function header() {
        		$head  = "MIME-Version: 1.0\n";
        		$head .= "Content-type: text/html; charset=iso-8859-1\n";
        		$head .= "From: " . $this->from . "\n";

			return $head;
		}

		function htmlMessage() {
        		$message = "<html>";
        		$message .= "<head><title>" . $this->sub . "</title></head>";
        		$message .= "<body>";
        		$message .= $this->msg;
        		$message .= "</body>";
        		$message .= "</html>";

			return $message;
		}

		function send() {
			$message = "empty";

			switch($this->frm)
			{
				case "html":
				default:
					$message = $this->htmlMessage();
					break;
			}

       		return mail($this->to, $this->sub, $message, $this->header());
		}

	} // email

} // defined

?>
