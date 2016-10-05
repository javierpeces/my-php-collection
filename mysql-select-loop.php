<?php
        $jump = "\n"; $host = "localhost"; $user = "dbuser"; $pass = "dbpass"; $base = "dbclustertest";
        $link = mysql_connect( $host, $user, $pass );

        if( !$link )
                echo "Error en la conexi&oacute;n" . $jump;
        else
        {
                $rslt = mysql_select_db( $base );

                if( $rslt == FALSE )
                        die( "Error al seleccionar " . $base . $jump );

                echo "Seleccionada " . $base . $jump;
                $rslt = mysql_query( "select * from tab1" );

                if( $rslt == FALSE )
                        die( "Error en query: " . mysql_error( ) . $jump );

                $item = 0;
                while( $row = mysql_fetch_assoc( $rslt ) )
                        printf( "%4d: '%8d' '%8s' %s", $item ++, $row['id'], $row['tx'], $jump );

                $rslt = mysql_close( $link );
        }

        echo "Terminado" . $jump;
?>
