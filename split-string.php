#!/usr/bin/php
<?php
        $f = file( getenv( "HOME" ) . "/.pass" );

        foreach( $f as $v )
        {
                $l = explode( " ", $v );
                echo "Host: '" . $l[0] . "'\n";
                echo "User: '" . $l[1] . "'\n";
                echo "Pass: '" . $l[2] . "'\n";
                echo "\n";
        }

        foreach( $f as $v )
        {
                $l = preg_split( "/[\s,\n]+/", $v );
                echo "Host: '" . $l[0] . "'\n";
                echo "User: '" . $l[1] . "'\n";
                echo "Pass: '" . $l[2] . "'\n";
                echo "\n";
        }

        foreach( $f as $v )
        {
                list( $h, $u, $p ) = explode( " ", trim( $v, "\n" ) );

                echo "Host: '" . $h . "'\n";
                echo "User: '" . $u . "'\n";
                echo "Pass: '" . $p . "'\n";
                echo "\n";
        }
?>
