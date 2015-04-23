<?php
if (isset($_REQUEST['debug'])) {
	echo "<!doctype html>\n<html>\n<head>\n<meta charset=\"utf-8\">\n<link rel='stylesheet' href='css/debug.css' />\n</head>\n<body>\n";
}

function debug( $object, $html_class='info' ) {
	if (isset($_REQUEST['debug'])) {
        if ( is_array( $object )) {
            $str = '<pre>' . print_r( $object, true ) . '</pre>';
        } else
        {
            $str = $object;
        }
		echo "<p class='debug-$html_class'>$str</p>\n";
	}
}

