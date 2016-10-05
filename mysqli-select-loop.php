<html>
<body>
<?php
    $host = 'debmysql';
    $user = 'datauser';
    $pass = 'datapass';
    $base = 'carpediem';
    $mysqli = new mysqli( $host, $user, $pass, $base );

    if ( $mysqli->connect_errno )
    {
        echo "Falló la conexión con MySQL: ("
        . $mysqli->connect_errno . ") "
        . $mysqli->connect_error;
    }

    $qry = "SELECT current_date AS col1"
        . " UNION SELECT current_time AS col1"
        . " UNION SELECT current_timestamp AS col1";

    $res = $mysqli->query( $qry );

    while ( $fila = $res->fetch_assoc( ) )
    {
        echo " <" . $fila['col1'] . "> <br />\n";
    }
?>
</body>
</html>
