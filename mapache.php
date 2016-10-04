#!/usr/bin/php
<?php

//
// mapache.php -- make apache web server (from scratch)
//
/**
 * @project	Mapache
 * @version 	Extremely Alpha 0.0.0
 * @author  	Javier Peces
 * @return      0 if OK, RC in any other case
 */

//
// diegracefully( )
// die with style   
// the php fn die() is not always returning zero
//
/**
 * @param  rc  	  code for exit()
 * @param  rv  	  message to be shown before terminating
 * @param  ro  	  affected resource
 * @return        rc
 */

function diegracefully( $rc, $rv, $ro )
{
	echo MYSELF . ": ERROR '{$rv}' in '${ro}'.\n";
	exit( $rc );
}

//
// safedeltree( )
// delete a (hopefully) temporary directory
//
/**
 * @param  dir    the victim
 */

function safedeltree( $dir ) 
{ 
	// echo "Calling safedeltree( {$dir} )...\n";

	if( $dir == "" )
	{
		diegracefully( 2, "internal", "safedeltree" );
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) ); 

	foreach ( $files as $file ) 
	{ 
		( is_dir( "$dir/$file" ) && !is_link( $dir ) ) ? 
			safedeltree( "$dir/$file" ) : unlink( "$dir/$file" ); 
	} 

	return rmdir( $dir ); 
}

//
// getwebcontents( )
// gets the content of a web page in an array
//
/**
 * @param  input  URL of the page to be downloaded
 * @return        FALSE or page text
 */

function getwebcontents( $input )
{
	try 
	{
		$curl = curl_init( );
		curl_setopt( $curl, CURLOPT_URL, $input );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		$output = curl_exec( $curl );
		curl_close( $curl );
	}
	catch( Exception $e )
	{
		$rv = $e->getMessage( );
		echo MYSELF . ": ERROR {$rv}\n";
		return( FALSE );
	}

	return( $output );
}

//
// getftpfile( )
// download a file using ftp
//
/**
 * @param  server   server address
 * @param  infile   filename in server
 * @param  outfile  assigned local filename
 * @return          TRUE or error message
 */

function getftpfile( $server, $infile, $outfile )
{
	$user = "anonymous";
	$pass = "myaddress@mydomain.org";

	try
	{
		$ftpc = ftp_connect( $server );
		$rv = ftp_login( $ftpc, $user, $pass);
		$rv = ftp_pasv( $ftpc, TRUE );
		$rv = ftp_get( $ftpc, $outfile, $infile, FTP_BINARY );
		$rv = ftp_close( $ftpc );
	}
	catch( Exception $e )
	{
		$rv = $e->getMessage( );
		echo MYSELF . ": ERROR '{$rv}'.\n";
		return( $rv );
	}

	return( TRUE );
}

//
// unpacklibrary( )
// uncompress a tar.gz
// uses xxxx.tar.gz without version, not xxxx-1.2.3.tar.gz
// because 'Phar' works less than well with excess of dots
//
/**
 * @param  infile   name of the tar.gz file
 * @param  outdest  target directory for decompression
 * @return          TRUE or error message
 */

function unpacklibrary( $infile, $outdest )
{
	$temp = basename( $infile, ".gz" );

	if( file_exists( $temp ) )
	{
		echo MYSELF . ": deleting existent {$temp}.\n";
		unlink( $temp );
	}

	if( is_dir( $outdest ) )
	{
		echo MYSELF . ": deleting existent {$outdest}.\n";
		safedeltree( $outdest );
	}

	try
	{
		$phar = new PharData( $infile );
		$phar->decompress( ); 
		$phar = new PharData( $temp );
		$phar->extractTo( $outdest );
	}
	catch( Exception $e )
	{
		$rv = $e->getMessage( );
		echo MYSELF . ": ERROR {$rv}\n";
		return( $rv );
	}

	return( TRUE );
}

//
// runshellcmd( )
// run command in a shell,
// store log file and return code $? of execution
//
/**
 * @param  shellcmd command to be executed
 * @param  workdir  working directory during execution
 * @param  lofgfile execution log filename
 * @return          command execution return code
 */

function runshellcmd( $shellcmd, $workdir, $logfile )
{
	$data = shell_exec( "cd {$workdir} && {$shellcmd} 2>&1; echo \"\$?\"" );
	file_put_contents( $logfile, $data, LOCK_EX ) 
		or diegracefully( 1, "writing log", $logfile );

	$lines = preg_split( "/\n/", $data, -1, PREG_SPLIT_NO_EMPTY );
	return( $lines[ count( $lines ) - 1 ] );
}

//
//____________________________________________________________________________
// Main
//

	$myself = basename( $argv[0], ".php" );
	define( 'MYSELF', $myself );
	define( 'MYDEBUG', TRUE );
	$dtinit = date( "r" );
	echo "{$myself}: starting {$dtinit}.\n";

//
// Variables: 
// Apache httpd location (accesible by ftp)
// Working directory for httpd compilation
// Log files
//

	$htserver = "ftp.cixug.es";
	$htpath   = "apache/httpd";
	$htlocal  = "httpd.tar.gz";
	$htdest   = "./temp-http";
	$htconflog = "httpd-configure.log.txt";
	$htmakelog = "httpd-make.log.txt";
	$htinstlog = "httpd-makeinst.log.txt";

//
// Obtain httpd version number
//

	echo "{$myself}: obtaining httpd version.\n";

	$source  = "ftp://{$htserver}/{$htpath}/";
	$version = implode( "\n", preg_replace( "/CURRENT-IS-/", "", 
		preg_grep( "/CURRENT-IS-*/", 
		preg_split( "/[\s,]+/", 
		getwebcontents( $source ), -1, PREG_SPLIT_NO_EMPTY ) ) ) )
		or diegracefully( 1, "cannot obtain version number", "httpd" );

//
// Download httpd tar.gz unless already exists
//

	echo "{$myself}: downloading tar.gz version '{$version}' of httpd.\n";

	$htremote = "httpd-{$version}.tar.gz";

	if( MYDEBUG && file_exists( $htlocal ) )
	{
		echo "* DEBUG: A tar.gz of httpd version '{$version}' already exists.\n";
		$retval = TRUE;
	}
	else
	{
		$retval = getftpfile( $htserver, $htpath . "/" . $htremote, $htlocal ); 
	}

	if( $retval != TRUE )
		diegracefully( $retval, "download error", $htremote );

//
// Compare MD5 sum of downloaded file to the one published en apache.org
//

	echo "{$myself}: checking download sum.\n";

	$md5file   = "http://www.apache.org/dist/httpd/httpd-{$version}.tar.gz.md5";
	$md5data   = getwebcontents( $md5file );
	$md5remote = substr( $md5data, 0, strpos( $md5data, " " ) );
	$md5local  = md5_file( $htlocal );

	echo "{$myself}: httpd, remote sum '{$md5remote}', local sum '{$md5local}'.\n";

	if( $md5local != $md5remote )
		diegracefully( 1, "httpd verification sums", "md5sum" );

//
// Extract tar.gz contents
//

	echo "{$myself}: uncompressing {$htlocal} in {$htdest}.\n";

	if( ! unpacklibrary( $htlocal, $htdest ) )
		diegracefully( 1, "uncompression", $htlocal );

//
// Configure
//

	echo "{$myself}: httpd configure...\n";

	$where = $htdest . "/" . basename( $htremote, ".tar.gz" );
	$htcmd = "./configure --prefix=/opt/apache --with-ssl";
	$rv = runshellcmd( $htcmd, $where, $htconflog );

	if( $rv != "0" )
		diegracefully( $rv, "configure. See log contents", $htconflog );

//
// Make
//

	echo "{$myself}: httpd make...\n";

	$htcmd = "make";
	$rv = runshellcmd( $htcmd, $where, $htmakelog );

	if( $rv != "0" )
		diegracefully( $rv, "make. See log contents", $htmakelog );

//
// Make Install
//

	echo "{$myself}: httpd make install.\n";

	$htcmd = "sudo make install";
	$rv = runshellcmd( $htcmd, $where, $htinstlog );

	if( $rv != "0" )
		diegracefully( $rv, "make install. See log contents", $htinstlog );

//
// Obtain current php version
//

	echo "{$myself}: obtaining current php version.\n";

	$source = "http://php.net/releases/feed.php";
	$phfeed = getwebcontents( $source );
	$version = max( preg_split( "/[\s,]+/", 
		implode( "\n", 
		preg_replace( "/<title>PHP| released!<\/title>/", "", 
		preg_grep( "/PHP .* released!/", 
		preg_split( "/\n/", 
		strip_tags( $phfeed, "<title></title>" ), -1, PREG_SPLIT_NO_EMPTY ) 
		) ) ), -1, PREG_SPLIT_NO_EMPTY ) )
		or gracefully( $myself, 1, "cannot obtain php version", $source );

//
// Obtain php tar.gz, this time using http. 
// Adjust block size in case of lack of RAM.
//

	echo "{$myself}: downloading php {$version}.\n";

	$phserver = "es1.php.net";
	$phpref   = "get";
	$phsuff   = "from/this/mirror";
	$phremote = "php-{$version}.tar.gz";
	$phurl    = "http://{$phserver}/{$phpref}/{$phremote}/{$phsuff}";
	$phlocal  = "php.tar.gz";	
	$phdest   = "./temp-php";
	$phconflog = "php-configure.log.txt";
	$phmakelog = "php-make.log.txt";
	$phinstlog = "php-makeinst.log.txt";

	if( MYDEBUG && file_exists( $phlocal ) )
	{
		echo "* DEBUG: A tar.gz of php version '{$version}' already exists.\n";
	}
	else
	{
		$infile  = fopen( $phurl, 'rb' );
		$outfile = fopen( $phlocal, 'w+b' );
		$blksize = 16384;

		while ( !feof( $infile ) )
		{
			if( fwrite( $outfile, fread( $infile, $blksize ) ) === FALSE )
				diegracefully( 1, "descarga", $phurl );

			flush( ); 
		}

		fclose( $infile );
		fclose( $outfile );
	}	

//
// Verify download
//

	echo "{$myself}: verifying php {$version}.\n";

	$phdata = preg_split( "/\n/", $phfeed, -1, PREG_SPLIT_NO_EMPTY );
	$found = FALSE;
	$md5remote = "";

	foreach( $phdata as $numb => $line )
	{
		if( strstr( $line, "PHP {$version} (tar.gz)" ) ) 
			$found = TRUE;

		if( $found )
		{
			if( strstr( $line, "<php:md5>" ) )
			{
				$md5remote = str_replace( " ", "", 
					strtr( $line, 
						array( "<php:md5>" => " ", "</php:md5>" => " " ) 
					) 
				);
				break;
			}
		}
	}

	$md5local = md5_file( $phlocal );
	echo "{$myself}: php, remote sum '{$md5remote}', local sum '{$md5local}'.\n";

	if( $md5remote != $md5local ) 
		diegracefully( 1, "verification sums", $phlocal . " <> " . $phremote );

//
// Uncompress
//

	echo "{$myself}: uncompressing {$phlocal} to {$phdest}.\n";

	if( ! unpacklibrary( $phlocal, $phdest ) )
		diegracefully( 1, "uncompression", $phlocal );

//
// Configure
//

	echo "{$myself}: php configure...\n";

	$where = $phdest . "/" . basename( $phremote, ".tar.gz" );
	$phcmd = "./configure --prefix=/opt/php --with-apxs2=/opt/apache/bin/apxs"
		. " --with-openssl --with-zlib --with-mysqli --enable-embedded-mysqli";
	$rv = runshellcmd( $phcmd, $where, $phconflog );

	if( $rv != "0" )
		diegracefully( $rv, "configure. See log contents", $phconflog );

//
// Make
//

	echo "{$myself}: php make...\n";

	$phcmd = "make";
	$rv = runshellcmd( $phcmd, $where, $phmakelog );

	if( $rv != "0" )
		diegracefully( $rv, "make. See log contents", $phmakelog );

//
// Make Install
//

	echo "{$myself}: php make install.\n";

	$phcmd = "sudo make install";
	$rv = runshellcmd( $phcmd, $where, $phinstlog );

	if( $rv != "0" )
		diegracefully( $rv, "make install. See log contents", $phinstlog );

//
// Change httpd.conf owner for a while
//

	$conffile  = "/opt/apache/conf/httpd.conf";
	$chown1log = "chown1.log.txt";
	$username  = getenv('USERNAME') ?: getenv('USER');
	$rv = runshellcmd( "sudo chown {$username} {$conffile}", ".", $chown1log );

	if( $rv != "0" )
		diegracefully( $rv, "chown failed. See log contents", $chownlog );

//
// Handler for php scripts in httpd.conf
//

	$conftext = array( 
		'<IfModule php5_module>' . "\n",
		'  <FilesMatch \.php$>' . "\n",
		'    SetHandler application/x-httpd-php' . "\n",
		'  </FilesMatch>' . "\n",
		'</IfModule>' . "\n"
	);

	if( ! file_put_contents ( $conffile, $conftext, FILE_APPEND ) )
		diegracefully( $rv, "append SetHandler", $conffile );

//
// Restore httpd.conf previous owner
//

	$chown2log = "chown2.log.txt";
	$rv = runshellcmd( "sudo chown root {$conffile}", ".", $chown2log );

	if( $rv != "0" )
		diegracefully( $rv, "chown failed. See log contents", $chownlog );

//
// Normal termination
//

	$dtterm = date( "r" );
	echo "{$myself}: ended {$dtterm}.\n";
	return( "0" );
?>
