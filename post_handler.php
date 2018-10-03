<?php

/* 
 * Receive XML file from client to convert into JSON
 * TODO:
 * error checking
 * multi-user support
 */
    $xml = $_POST["xml"];

    if ($xml != null) {
      $fh=fopen("diagram.xml","w");
      fputs($fh, stripslashes($xml));
      fclose($fh);
    }
?>