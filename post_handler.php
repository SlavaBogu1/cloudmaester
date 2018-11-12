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
 * 
 * \section debug url parameter
 * ?XDEBUG_SESSION_START=netbeans-xdebug
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
      $result = FormatJsonToAnalyser($json);
      
      fputs($fjson , $result);
      fclose($fxml);
      fclose($fjson);
    }
    
/*! \mainpage Cloud Maester
 *
 * To merge JSON objects need to use trick:
 * https://stackoverflow.com/questions/20286208/merging-two-json-in-php
 * json_encode(array_merge(json_decode($a, true),json_decode($b, true))) 
 * 
 * first - generate array of json strings
 * second - merge them 
*/ 
   
function FormatJsonToAnalyser ($json) {
    $json_input = json_decode($json,TRUE);
    
    /*
     * \TODO check $json_input["root"] is not null and so on.    
     */
    $jam_info = JsonAnalyticModel_Info( $json_input["root"]["Workflow"]);
    $jam_func = JsonAnalyticModel_Func($json_input);
    $jam_comp = JsonAnalyticModel_Comp($json_input["root"]);
    $jam_infc = JsonAnalyticModel_Infc();
    
    $json_output = json_encode(array_merge($jam_info,$jam_func,$jam_comp,$jam_infc));
    return $json_output;
}

function JsonAnalyticModel_Info($workflow){
    /*
     * \TODO check $workflow["@attributes"] is not null and so on.    
     * href - lost
     */    
    return array("info" => array("Name" => $workflow["@attributes"]["label"],"Description" =>$workflow["@attributes"]["description"]));
}
function JsonAnalyticModel_Func($func){
    /*
     * Store a full-copy of the original MxGraph json (for debug purpose)
     */    
    return array("functionality"=> $func);
}
function JsonAnalyticModel_Comp($comp){
    /*
     * \TODO check $workflow["@attributes"] is not null and so on.    
     * skip "Workflow", it has been processed
     * "Layer" is the primary component - gloabl container. Multi-layer is not supported yet
     */    
    $comp_out = null;
    foreach($comp as $key => $arr){
        switch($key){
            case 'Workflow':
                break;
            case 'Layer':
                $comp_out = TransferLayerToPlantUml($comp_out,$arr);
                break;
            case 'Swimlane':
                $comp_out = TransferSwimlaneToPlantUml($comp_out,$arr);
                break;
            case 'Task':
                $comp_out = TransferTaskToPlantUml($comp_out,$arr);
                break;
            default:
                /*
                 * Add "NotSupported" component .. just one, so multiple issues will be overrided
                 */                
                $comp_out["components"] = array_merge(
                        $comp_out["components"],
                        array("NotSupported"=>
                               array("info"=>$key,
                                     "components"=>null,
                                     "interfaces"=>null
                                )
                            )
                    );
        }
    }        
    return $comp_out;
}

function TransferLayerToPlantUml($out,$arr){
    /*
     * \TODO check $array["@attributes"] is not null and so on
     * "label" -> "info" 
     * "id" -> "interfaces"
     * "components" - is the container for other nested components;
     */    
    $newout = array("components" => 
                    array("Layer"=>
                        array("info"=>$arr["@attributes"]["label"],
                               "interfaces"=>"mxgid_".$arr["@attributes"]["id"],
                                "components"=>null
                        )
                    )
                );
    return $newout;
}
function TransferSwimlaneToPlantUml($out,$arr){
    /*
     * \TODO check $array["@attributes"] is not null and so on
     * "Swimlanes" are other containers inside "Layer"
     */
        foreach($arr as $key => $val){
        $swimlane = array($val["@attributes"]["label"] => 
                        array("info"=>array("Name" =>$val["@attributes"]["label"]),
                            "interfaces"=>"mxgid_".$val["@attributes"]["id"],
                            "components"=>null
                        )
                    );
        if( is_null($out["components"]["Layer"]["components"]) ) {
            // first components at Layer
            $out["components"]["Layer"]["components"] = $swimlane;
        } else {
            //there are other components at Layer already
            $out["components"]["Layer"]["components"] = array_merge($out["components"]["Layer"]["components"],$swimlane);
        }
    }
    return $out;
}
function TransferTaskToPlantUml($out,$arr){
    /*
     * \TODO check $array["@attributes"] is not null and so on
     * "Tasks" are someting inside swimlane or Layer
     * "Task" may have interfaces (connectors)
     */    
    $newout = $out;
    return $newout;
}


function JsonAnalyticModel_Infc(){
    return array("interfaces"=> array("if1" => "interface 1","if2" => "interface 2"));
}

?>