#!/usr/bin/php -q
#
# list data in hex format
#
#
<?
	$maxlr = 32767;
	$ruler = "";

	for( $i = 0; $i < $maxlr; $i ++ )
	{
		if( $i % 10 == 0 )
		{
			$nchar = $i / 10;
			$nchar = substr( $nchar, strlen( $nchar ) - 1, 1 );
		}
		else
		{
			if( $i % 5 == 0 )
				$nchar = "+";
			else
				$nchar = ".";
		}

		$ruler = $ruler . $nchar;
	}

	if( $argc == 0 )
	{
		echo "Usage: " . $argv[ 0 ] . " inputfile\n";
		return 1;
	}

	$stdin = fopen( $argv[ 1 ], 'r' );

	if( !$stdin )
	{
		echo $argv[ 0 ] . ": Input file error\n";
		return 1;
	}

	$i = 1;

	while( $record = fgets( $stdin, $maxlr ) )
	{
		printf( "%04d: '%s'\n", $i ++, substr( $record, 0, strlen( $record ) - 1 ) );
		printf( "    : '%s'\n", substr( $ruler, 0, strlen( $record ) - 1 ) );
		$hex1 = "";
		$hex2 = "";

  		for( $j = 0; $j < strlen( $record ) - 1 && $j < $maxlr; $j ++ )
		{
			$jchar = substr( $record, $j, 1 );
			$jhex  = dechex( ord( $jchar ) );
			$hex1  = $hex1 . substr( $jhex, 0, 1 );
			$hex2  = $hex2 . substr( $jhex, 1, 1 );
		}

		printf( "    : '%s'\n", $hex1 );
		printf( "    : '%s'\n\n", $hex2 );
	}

	fclose( $stdin );
	return 0;
?>
