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
    define("INTERFACE_DIR", "./interfaces/");
    $xml = $_POST["xml"];
    $json_project_name = ""; // to pass name from "workflow" to "layer"
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
      //$result = FormatJsonToAnalyser($json);
      $result = ConvertToPlantUml($json);
      
      fputs($fjson , $result);
      fclose($fxml);
      fclose($fjson);
    }
    
    
function ConvertToPlantUml($json){
    class cPlantUml_Componnets{        
        public $info;
        public $interfaces;
        public $components;        
        function __construct($attributes){            
            $this->info = ["Name" => $attributes['label']];
            $this->components = json_decode ("{}");
            $this->interfaces = json_decode ("{}");
        }     
        function GetName(){
            return $this->info["Name"];
        }
    }
    class cPlantUml { 
        public $info; 
        public $functionality; 
        public $components; 
        public $interfaces; 
        private $MxGraph_json;
        private $Project_Name;
        private $components_array_by_id = [array()];
        private $interfaces_array_by_id = [];
        function __construct($json) { 
            $this->MxGraph_json = json_decode($json,TRUE);
            
            foreach ($this->MxGraph_json["root"] as $key =>$val){
                switch($key){
                    case 'Workflow':
                        //id = 0
                        $id = $val["@attributes"]["id"];
                        $this->components_array_by_id[$id]["component"] = $val;
                        $this->components_array_by_id[$id]["parent"] = null;
                        $this->components_array_by_id[$id]["source"] = null;
                        $this->components_array_by_id[$id]["target"] = null;
                        break;
                    case 'Layer':
                        //id = 1
                        $id = $val["@attributes"]["id"];
                        $this->components_array_by_id[$id]["component"] = $val;
                        $this->components_array_by_id[$id]["parent"] = null;
                        $this->components_array_by_id[$id]["source"] = null;
                        $this->components_array_by_id[$id]["target"] = null;
                        break;
                    case 'Swimlane':
                        foreach($val as $list=>$record){                            
                            $id = $record["@attributes"]["id"];
                            $this->components_array_by_id[$id]["component"] = $record;                            
                            $this->components_array_by_id[$id]["parent"] = $record["mxCell"]["@attributes"]["parent"];
                            $this->components_array_by_id[$id]["source"] = $record["mxCell"]["@attributes"]["source"];
                            $this->components_array_by_id[$id]["target"] = $record["mxCell"]["@attributes"]["target"];
                        }                        
                        break;
                    case 'Task':
                        foreach($val as $list=>$record){                            
                            $id = $record["@attributes"]["id"];
                            $this->components_array_by_id[$id]["component"] = $record;                        
                            $this->components_array_by_id[$id]["parent"] = $record["mxCell"]["@attributes"]["parent"];
                            $this->components_array_by_id[$id]["source"] = $record["mxCell"]["@attributes"]["source"];
                            $this->components_array_by_id[$id]["target"] = $record["mxCell"]["@attributes"]["target"];
                        }                                                
                        break;
                    case 'Edge':
                        foreach($val as $list=>$record){                            
                            $id = $record["@attributes"]["id"];
                            $this->components_array_by_id[$id]["component"] = $record;
                            $this->components_array_by_id[$id]["parent"] = $record["mxCell"]["@attributes"]["parent"];
                            $this->components_array_by_id[$id]["source"] = $record["mxCell"]["@attributes"]["source"];
                            $this->components_array_by_id[$id]["target"] = $record["mxCell"]["@attributes"]["target"];                            
                            
                            $this->interfaces_array_by_id[$id] = $record;                        
                        }                                                
                        break;
                    default:
                }
            }
            $this->Project_Name = $this->components_array_by_id['0']["component"]['@attributes']['label'];
        } 
        function Generate_Info_Object() {             
            $workflow = $this->MxGraph_json["root"]["Workflow"];            
            return array("Name" => $workflow["@attributes"]["label"],"Description" =>$workflow["@attributes"]["description"]);
        }
        function Generate_functionality_Object() { 
            //empty for now - constructor generated empty object
            return json_decode ("{}");
        }
        function Generate_Components_Object() { 
            // to convert array to object before json encoding - use (object) 
            // http://php.net/manual/en/language.types.object.php            
            // cPlantUml 
            $components = [$this->Project_Name => new cPlantUml_Componnets($this->components_array_by_id[1]["component"]["@attributes"])];
            $current_parent = 1;
            $max_cnt = count($this->components_array_by_id);
            do {
                // cPlantUml_components
                $current_component = $this->GetComponentbyid($components,$current_parent);
                for ($i = 2; $i < $max_cnt;$i++ ){
                    // if $current_component is false - skip.
                    if ($current_component === false) break;                    
                    $parent_id = $this->GetParentbyid($i);
                    if($current_parent === (int)$parent_id) {
                        //current_parent is the "parent" of an object with ID = i
                        $ccc = $this->components_array_by_id[$i]["component"];
                        $c = count((array)$current_component);
                        if ($c ===0 ) {
                            $current_component = new cPlantUml_Componnets($ccc["@attributes"]);
                        } else {
                            $arr = (array)$current_component;
                            $narr = (array) (new cPlantUml_Componnets($ccc["@attributes"]));                            
                            $ar = array_push($arr,$narr);
                            $current_component = (object)($arr);
                        } 
                    }                    
                }  //for                         
                $current_parent = $current_parent +1;
            } while($current_parent < $max_cnt);
                        
            return (object)$components;
            //return new cPlantUml_Componnets();
        }
        
        function GetComponentbyid($cmp,$id){
            $name =  $this->components_array_by_id[$id]["component"]["@attributes"]["label"];
            foreach($cmp as $key=>$val){
                if (strcmp($name,$val->GetName()) === 0){
                    return $val->components;
                }
            }
            return false;
        }
        
        function GetParentbyid($id){
            $comp = $this->components_array_by_id[$id];
            if (empty($comp)) return false;
            $parent_id = $comp["parent"];
            if (empty($comp)) return false;
            return $parent_id;            
        }
        
        function Generate_Interfaces_Object() { 
            return array("info" => array("Name" => "cluster fuck"));
        }
        
    }
    
    $output = new cPlantUml ($json);
    $output->info = $output->Generate_Info_Object();    
    $output->functionality = $output->Generate_functionality_Object();

    $output->components  = $output->Generate_Components_Object();

    $output->interfaces  = $output->Generate_Interfaces_Object();
    
    $json_output = json_encode($output);
    return $json_output;
}
    
    
/*! \mainpage Cloud Maester
 *
 * To merge JSON objects need to use trick:
 * https://stackoverflow.com/questions/20286208/merging-two-json-in-php
 * json_encode(array_merge(json_decode($a, true),json_decode($b, true))) 
 * \NOTE: Do not usearry_merge, use siple array
 * first - generate array of json strings
 * second - merge them 
*/ 
   
function FormatJsonToAnalyser ($json) {
    $json_input = json_decode($json,TRUE);
    
    /*
     * \TODO check $json_input["root"] is not null and so on.    
     */
    $jam_comp = JsonAnalyticModel_Comp($json_input["root"]);
    $jam_infc = JsonAnalyticModel_Infc();
    
    $json_output = json_encode(array_merge($jam_info,$jam_func,$jam_comp,$jam_infc));
    return $json_output;
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
                $comp_out["components"] = array(
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
    global $json_project_name; // костыль - use global name instead layer name $arr["@attributes"]["label"]  
    $layer_interface = "layer_interface.json";
    $layer = ["Layer"=> 
                    ["info"=>$json_project_name,
                        "interfaces"=>[
                            "mxgraph_id" => $arr["@attributes"]["id"],"layer"=>LoadInterfaces($layer_interface),
                            "components"=>json_decode("{}")
                        ]
                    ]
                ];
    $newout = ["components" => $layer];
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
                            "interfaces"=>array("mxgraph_id" => $val["@attributes"]["id"]),
                            "components"=>null
                        )
                    );
        if( is_null($out["components"]["Layer"]["components"]) ) {
            // first components at Layer
            $out["components"]["Layer"]["components"] = $swimlane;
        } else {
            //there are other components at Layer already
            $out["components"]["Layer"]["components"] = array($out["components"]["Layer"]["components"],$swimlane);
        }
    }
    return $out;
}
function TransferTaskToPlantUml($out,$arr){
    /*
     * \TODO check $array["@attributes"] is not null and so on
     * "Task" are someting inside swimlane or Layer
     * "Task" may have interfaces (connectors)
     */    
    $res = null;
    foreach($arr as $key => $val){
        // label
        $comp_name = str_replace("\n"," ",$val["@attributes"]["label"]);
        
        // description
        $comp_descr = $val["@attributes"]["description"];
        
        // href
        $comp_href = $val["@attributes"]["href"];

        // id
        $comp_id = $val["@attributes"]["id"];
        
        $new_comp = array($comp_name => 
                        array("Info" => 
                            array("Name"=>$val["@attributes"]["template"],
                                "Description" => $comp_descr,
                                "Link" => $comp_href
                            )                            
                        )
                    );
        
        // parent id - the id of owner of this component 
        // TODO - search and allocate properly .. using graphs
        $parent_id = $val["mxCell"]["@attributes"]["parent"];
        
        // create interface name to import it from file
        switch ($val["@attributes"]["template"]) {
            case "aws_compute":
                $interface_name = "aws_compute_".ExtractTaskType($val["@attributes"]).".json";                
                break;
            case "aws_storage":
                $interface_name = "aws_storage_".ExtractTaskType($val["@attributes"]).".json";                
                break;            
            default:
                $interface_name ="default_interface.json";
        }
    
        $infc = array(LoadInterfaces($interface_name));
        
        $new_comp[$comp_name]["Interfaces"] = $infc;
        $comp = $new_comp;        
        if($res===null){
            $res = $comp;
        } else {
            $res = array($res,$comp);
        }
    }
    
    // TODO - add components inside Layer.
    $out["components"]["Layer"]["components"] = $res;
            
    return $out;
}

function ExtractTaskType($desc){
    return ltrim(str_replace($desc["type"], "", $desc["label"]),"\n");
}

function JsonAnalyticModel_Infc(){
    return array("interfaces"=> array("if1" => "interface 1","if2" => "interface 2"));
}


function LoadInterfaces($interface_name){    
    $out = null;
    $fname = INTERFACE_DIR.$interface_name;
    if (file_exists($fname)) {
        $finfc = fopen($fname,"r");
        
        if( $finfc === false){
            return false; //TODO error processing if can't open files
        }
        while (($buffer = fgets($finfc) ) !== false) {
            $out = $out.trim($buffer);
        }
        if (!feof($finfc)) {
            // "Error: unexpected fgets() fail\n";
            return false; //TODO error processing if can't open files
        }                
        fclose($finfc);
    } else {
        // "Error: file doesn't exists";
        return false; //TODO error processing if can't open files
    }
    
    return json_decode($out,true);
}
?>