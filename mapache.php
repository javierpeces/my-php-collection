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
// muere con estilo 
// la funcion die() de php siempre devuelve cero
//
/**
 * @param  rc  	  codigo para la salida exit()
 * @param  rv  	  mensaje para mostrar antes de terminar
 * @param  ro  	  recurso afectado
 * @return        rc
 */

function diegracefully( $rc, $rv, $ro )
{
	echo MYSELF . ": ERROR '{$rv}' en '${ro}'.\n";
	exit( $rc );
}

//
// safedeltree( )
// borra un directorio (esperemos que) temporal
//
/**
 * @param  dir    la victima
 */

function safedeltree( $dir ) 
{ 
	// echo "Calling safedeltree( {$dir} )...\n";

	if( $dir == "" )
	{
		diegracefully( 2, "interno", "safedeltree" );
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
// obtiene el contenido de una pagina web en un array
//
/**
 * @param  input  URL de la pagina a descargar
 * @return        FALSE o texto de la pagina descargada
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
// descarga un fichero por ftp
//
/**
 * @param  server   direccion del servidor
 * @param  infile   nombre del fichero en el servidor
 * @param  outfile  nombre que se asigna al fichero descargado
 * @return          TRUE o mensaje de error
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
// descomprime un tar.gz
// Se usa xxxx.tar.gz sin version, y no xxxx-1.2.3.tar.gz
// porque 'Phar' no se lleva bien con el exceso de puntos
//
/**
 * @param  infile   nombre del fichero tar.gz
 * @param  outdest  directorio en el que se descomprime
 * @return          TRUE o mensaje de error
 */

function unpacklibrary( $infile, $outdest )
{
	$temp = basename( $infile, ".gz" );

	if( file_exists( $temp ) )
	{
		echo MYSELF . ": eliminando {$temp} existente.\n";
		unlink( $temp );
	}

	if( is_dir( $outdest ) )
	{
		echo MYSELF . ": eliminando {$outdest} existente.\n";
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
// ejecuta una orden en una shell,
// guarda el log y el retorno $? de la orden
//
/**
 * @param  shellcmd orden a ejecutar
 * @param  workdir  directorio en el que se ejecuta
 * @param  lofgfile nombre del fichero en el que se deja el log
 * @return          retorno de la orden ejecutada
 */

function runshellcmd( $shellcmd, $workdir, $logfile )
{
	$data = shell_exec( "cd {$workdir} && {$shellcmd} 2>&1; echo \"\$?\"" );
	file_put_contents( $logfile, $data, LOCK_EX ) 
		or diegracefully( 1, "escribiendo en el log", $logfile );

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
	echo "{$myself}: iniciando {$dtinit}.\n";

//
// Variables: 
// Localizacion de apache httpd accesible por ftp
// Directorio de trabajo para compilar httpd
// Ficheros de log
//

	$htserver = "ftp.cixug.es";
	$htpath   = "apache/httpd";
	$htlocal  = "httpd.tar.gz";
	$htdest   = "./temp-http";
	$htconflog = "httpd-configure.log.txt";
	$htmakelog = "httpd-make.log.txt";
	$htinstlog = "httpd-makeinst.log.txt";

//
// Obtener el numero de version actual de httpd
//

	echo "{$myself}: obteniendo version de httpd.\n";

	$source  = "ftp://{$htserver}/{$htpath}/";
	$version = implode( "\n", preg_replace( "/CURRENT-IS-/", "", 
		preg_grep( "/CURRENT-IS-*/", 
		preg_split( "/[\s,]+/", 
		getwebcontents( $source ), -1, PREG_SPLIT_NO_EMPTY ) ) ) )
		or diegracefully( 1, "no se puede obtener el numero de version", "httpd" );

//
// Descargar el tar.gz de httpd
//

	echo "{$myself}: descargando tar.gz de la version '{$version}' de httpd.\n";

	$htremote = "httpd-{$version}.tar.gz";

	if( MYDEBUG && file_exists( $htlocal ) )
	{
		echo "* DEBUG: Ya existe el tar.gz de la version '{$version}' de httpd.\n";
		$retval = TRUE;
	}
	else
	{
		$retval = getftpfile( $htserver, $htpath . "/" . $htremote, $htlocal ); 
	}

	if( $retval != TRUE )
		diegracefully( $retval, "error en la descarga", $htremote );

//
// Comparar la suma MD5 del fichero descargado con la publicada en apache.org
//

	echo "{$myself}: comprobando la descarga.\n";

	$md5file   = "http://www.apache.org/dist/httpd/httpd-{$version}.tar.gz.md5";
	$md5data   = getwebcontents( $md5file );
	$md5remote = substr( $md5data, 0, strpos( $md5data, " " ) );
	$md5local  = md5_file( $htlocal );

	echo "{$myself}: httpd, suma remota '{$md5remote}', suma local '{$md5local}'.\n";

	if( $md5local != $md5remote )
		diegracefully( 1, "sumas de verificacion de httpd", "md5sum" );

//
// Extraer el contenido del tar.gz
//

	echo "{$myself}: descomprimiendo {$htlocal} en {$htdest}.\n";

	if( ! unpacklibrary( $htlocal, $htdest ) )
		diegracefully( 1, "descompresion", $htlocal );

//
// Configure
//

	echo "{$myself}: httpd configure...\n";

	$where = $htdest . "/" . basename( $htremote, ".tar.gz" );
	$htcmd = "./configure --prefix=/opt/apache --with-ssl";
	$rv = runshellcmd( $htcmd, $where, $htconflog );

	if( $rv != "0" )
		diegracefully( $rv, "configure. Vea el contenido del log", $htconflog );

//
// Make
//

	echo "{$myself}: httpd make...\n";

	$htcmd = "make";
	$rv = runshellcmd( $htcmd, $where, $htmakelog );

	if( $rv != "0" )
		diegracefully( $rv, "make. Vea el contenido del log", $htmakelog );

//
// Make Install
//

	echo "{$myself}: httpd make install.\n";

	$htcmd = "sudo make install";
	$rv = runshellcmd( $htcmd, $where, $htinstlog );

	if( $rv != "0" )
		diegracefully( $rv, "make install. Vea el contenido del log", $htinstlog );

//
// Obtener version actual de php
//

	echo "{$myself}: obteniendo version de php.\n";

	$source = "http://php.net/releases/feed.php";
	$phfeed = getwebcontents( $source );
	$version = max( preg_split( "/[\s,]+/", 
		implode( "\n", 
		preg_replace( "/<title>PHP| released!<\/title>/", "", 
		preg_grep( "/PHP .* released!/", 
		preg_split( "/\n/", 
		strip_tags( $phfeed, "<title></title>" ), -1, PREG_SPLIT_NO_EMPTY ) 
		) ) ), -1, PREG_SPLIT_NO_EMPTY ) )
		or gracefully( $myself, 1, "no se puede obtener la version", $source );

//
// Obtener tar.gz de php, esta vez usando http. 
// Ajustar tamano de bloque en caso de memoria escasa.
//

	echo "{$myself}: descargando php {$version}.\n";

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
		echo "* DEBUG: Ya existe el tar.gz de la version '{$version}' de php.\n";
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
// Verificar la descarga
//

	echo "{$myself}: verificando php {$version}.\n";

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
	echo "{$myself}: php, suma remota '{$md5remote}', suma local '{$md5local}'.\n";

	if( $md5remote != $md5local ) 
		diegracefully( 1, "sumas de verificacion", $phlocal . " <> " . $phremote );

//
// Descomprimir
//

	echo "{$myself}: descomprimiendo {$phlocal} en {$phdest}.\n";

	if( ! unpacklibrary( $phlocal, $phdest ) )
		diegracefully( 1, "descompresion", $phlocal );

//
// Configure
//

	echo "{$myself}: php configure...\n";

	$where = $phdest . "/" . basename( $phremote, ".tar.gz" );
	$phcmd = "./configure --prefix=/opt/php --with-apxs2=/opt/apache/bin/apxs"
		. " --with-openssl --with-zlib --with-mysqli --enable-embedded-mysqli";
	$rv = runshellcmd( $phcmd, $where, $phconflog );

	if( $rv != "0" )
		diegracefully( $rv, "configure. Vea el contenido del log", $phconflog );

//
// Make
//

	echo "{$myself}: php make...\n";

	$phcmd = "make";
	$rv = runshellcmd( $phcmd, $where, $phmakelog );

	if( $rv != "0" )
		diegracefully( $rv, "make. Vea el contenido del log", $phmakelog );

//
// Make Install
//

	echo "{$myself}: php make install.\n";

	$phcmd = "sudo make install";
	$rv = runshellcmd( $phcmd, $where, $phinstlog );

	if( $rv != "0" )
		diegracefully( $rv, "make install. Vea el contenido del log", $phinstlog );

//
// Cambiar propietario de httpd.conf por un momento
//

	$conffile  = "/opt/apache/conf/httpd.conf";
	$chown1log = "chown1.log.txt";
	$username  = getenv('USERNAME') ?: getenv('USER');
	$rv = runshellcmd( "sudo chown {$username} {$conffile}", ".", $chown1log );

	if( $rv != "0" )
		diegracefully( $rv, "chown ha fallado. Vea el contenido del log", $chownlog );

//
// Handler para scripts .php en httpd.conf
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
// Dejar como estaba el propietario de httpd.conf
//

	$chown2log = "chown2.log.txt";
	$rv = runshellcmd( "sudo chown root {$conffile}", ".", $chown2log );

	if( $rv != "0" )
		diegracefully( $rv, "chown ha fallado. Vea el contenido del log", $chownlog );

//
// Terminacion normal
//

	$dtterm = date( "r" );
	echo "{$myself}: terminado {$dtterm}.\n";
	return( "0" );
?>
