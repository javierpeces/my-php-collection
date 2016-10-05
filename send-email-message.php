<?php

//
// mandamail
// send an email from the inside
// if your home connection gets reset somehow
// and public address changes
//

// $self = preg_replace( '/\.php$/', '', __FILE__ ) . ": ";
$self = basename( __FILE__, ".php" ) . ": " ;
echo $self . date("Y-M-d D H:i:s") . "\n";
require 'Net/SMTP.php';

//
// CHECK THIS BEFORE RUNNING
//

$dname = "here.goes.yourdomain.name"; 	// your domain name
$dserv = "194.179.1.100"              	// nameserver of your choice
$host = 'smtp.gmail.com';	 	// smtp server of your choice
$from = 'your.account@gmail.com';	// your account in that server
$pass = 'your.password';		// password for that account
$rcpt = array('send.mail.to.this@gmail.com', 'send.also.to.this@hotmail.com');
$yname = 'Your Name Here';		// your name here

// $ipdns = "1.2.3.4";
// $ipdns = "404 not found";

//
// RUN ONLY IF PUBLIC IP IN DNS IS NOT THE ONE IN THE ROUTER
//

$ipdns = `dig +short {$dname} @{$dserv}`;
$ipchk = `wget -q -O - "http://myexternalip.com/raw"`;
$bipdns = "IP from DNS   = {$ipdns}";
$bipchk = "IP from check = {$ipchk}";

if( $ipdns == $ipchk )
{
        echo $self . "Addresses are the same. Leaving.\n";
        echo $bipdns . $bipchk . "\n";
        exit( 0 );
}

$head = "From: \"{$yname} (cron)\" \r\n" . "Subject: IP " . date("Y-M-d D H:i:s") . "\r\n";
$body = $bipdns . "\n" . $bipchk;

//
// CREATE NEW SMTP OBJECT
//

if (! ( $smtp = new Net_SMTP( $host ) ) ) 
{
    die( $self . "Unable to instantiate Net_SMTP object\n" );
}

$smtp->setDebug( true );

//
// CONNECT TO THE SMTP SERVER 
//

if ( PEAR::isError( $e = $smtp->connect() ) ) 
{
    die( $self . $e->getMessage() . "\n" );
}

$smtp->auth( $from, $pass );

//
// SEND THE 'MAIL FROM:' SMTP COMMAND
//

if ( PEAR::isError( $smtp->mailFrom( $from ) ) ) 
{
    die( $self . "Unable to set sender to <$from>\n" );
}

//
// ADDRESS THE MESSAGE TO EACH OF THE RECIPIENTS
//

foreach ( $rcpt as $to )
{
	if ( PEAR::isError( $res = $smtp->rcptTo( $to ) ) )
	{
		die( $self . "Unable to add recipient <$to>: " . $res->getMessage() . "\n" );
	}
}

//
// SEND THE BODY OF THE MESSAGE
//

if ( PEAR::isError( $smtp->data( $body, $head ) ) ) 
{
    die( $self . "Unable to send data\n" );
}

//
// DISCONNECT FROM SMTP SERVER
//

$smtp->disconnect();

?>
