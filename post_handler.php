<?php

/*! \mainpage Cloud Maester
 *
 * \section php_interface Using PHP
 *
 * Receive XML file from client to convert into JSON
 * Save both XML and JSON version because XML is to read back, so no needs to use 
 * XML->JSON->XML converters. Simply have both types.
 *
 * \section todo TODO
 *
 * \subsection error Need to add error checking
 * \subsection multi_user Need to add support of mutliple users
 * etc...
 */
    $xml = $_POST["xml"];
    
    $json_filename = "diagrams/cloudmaester.json";
    $xml_filename = "diagrams/cloudmaester.xml";

   
    if ($xml != null) {
      $fjson=fopen($json_filename,"w");
      $fxml=fopen($xml_filename,"w");

      if(( $fjson === false)OR ($fxml === false) ){
            return false; //TODO error processing if can't open files
      }
      //save XML
      $result = stripslashes($xml);
      fputs($fxml, $result);
      
      //save JSON
      $xml_string = simplexml_load_string($xml);
      //$json = json_encode($xml_string,JSON_UNESCAPED_UNICODE);
      $json = json_encode($xml_string); // escape UNICODE as \uXXXX
      $result = $json;
      
      fputs($fjson , $result);
      fclose($fxml);
      fclose($fjson);
    }
?>