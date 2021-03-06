<?php

/*
There are 4 really important functions in here:
-function efi_create_job($job_id, $extra_job_data = array())
 * This creates the job, sets some default that can be overwritten by 
 * $extra_job_data
-efi_update_job_part($job_id, $extra_part_data = NULL)
 * Once the job is created, the job part can be updated
 * $extra_part_data overides any defaults.
-function efi_create_job_paper($job_id, $extra_paper_data = array())
 * This sets the stock information for this pressrun
 * $extra_paper_data overides any defaults
-function efi_create_pressform($job_id, $extra_press_data = array())
 * Sets the press information for this pressrun
 * $extra_press_data overides any deafult data
*/

//Last Updated Sept 10 2013 3:36pm

if (gethostname() == 'Prepress-iMac-2.local'){
    $epacehost = "123.123.123.123";
    $apiusername = 'username';
    $apipassword = 'password'; 
} elseif (gethostname() == 'bob') {
    $epacehost = "967.967.967.967";
    $apiusername = 'u2';
    $apipassword = 'sdfreve';     
} else {
    $epacehost = "222.222.222.222";
    $apiusername = 'fsddsfsdf';
    $apipassword = 'dsfwefwe';  
}


///silly globals
$now = new DateTime('NOW');
$objDateTime = new DateTime('NOW');
$order_date = $objDateTime->format('c'); // now
$delivery_date = $objDateTime->add(new DateInterval('P1D')); // tomorrow
//use for testing
$debug = true;

/**
 * Prints all keys and values
 * @param type $value
 */
function show_all($value, $dump = false) {
    if(!$dump){
        if ($value) {
            foreach ($value as $keya => $valb)
                print "$keya=$valb<br />";
        } else {
            print "No values to display. <br>";
        }
    } else {
        var_dump($value);
    }
}

/**
 * makeProxyObject($name, $structString)
 * @param type $name
 * @param type $structString
 * @return \stdClass
 */
function makeProxyObject($name, $structString) {
    #echo "name: $name <BR>";
    #echo "struct: $structString <BR>";

    $OBJECT_NAME = substr($structString, 7, strpos($structString, '{') - 8);
    $OBJECT_STRUCT_STR = trim(trim(trim(strstr($structString, '{'), '{}')), ';');
    #var_dump($OBJECT_STRUCT_STR); echo "<BR><BR>";
    $SCHEMA_ARR = explode(';', $OBJECT_STRUCT_STR);

    $RESULT_OBJ = new stdClass;

    foreach ($SCHEMA_ARR as $a) {

        $element = explode(' ', trim($a));
        $dataType = $element[0];
        $fieldName = $element[1];

        if ($dataType == "boolean") {
            $RESULT_OBJ->$fieldName = False;
        } else {
            $RESULT_OBJ->$fieldName = "";
        }
    }
    return $RESULT_OBJ;
}


/**
 * Reclculates an estimate after updating
 * @param type $estimate_id
 * @return boolean
 */
function recalc_estimate($estimate_id){

    $readEstimate = new ReadObjectHelper();

    $estimate_fields = array("id" => $estimate_id);

    $estimateObject = $readEstimate->getObjectByArray('estimate', $estimate_fields);

    $recalc = new InvokeActionHelper();

    try {

        $recalc_result = $recalc->recalc_estimate($estimateObject);

        return true;

    } catch (Exception $e) {

        return false;
        //echo 'Recalc Estimate Error: '.$e->getMessage().'<br />';

    }
     
}





/**
 * Creates a base job from OLP on EFI
 * @global type $order_date  - todays date/time
 * @global type $delivery_date  - tommorrows date
 * @global boolean $debug  - turns on/off display of output - should be false in production
 * @param type $job_id  - The Pressrun ID from OLP
 * @param type $extra_job_data  - an array of key/values for EFI additional to ones coded !!! 
 * @return mixed true on success, exception message on fail
 */
function efi_create_job($job_id, $extra_job_data = array()){
    
    global $order_date, $delivery_date, $debug;
    
    $base_job_data['job']=$job_id;
    $base_job_data['customer']='SINALITE';
    $base_job_data['description']='SINALITE PRESSRUN '.$job_id;
    $base_job_data['description2']='Generated by OLP';
    $base_job_data['jobType']=5013;
    $base_job_data['adminStatus']='O';
    $base_job_data['shipVia']=1;
    $base_job_data['terms']=1;
    $base_job_data['dateSetup']=$order_date;
    $base_job_data['timeSetUp']=$order_date;
    $base_job_data['poNum']= 'SL'.$job_id;
    $base_job_data['promiseDate']=$delivery_date;
    $base_job_data['promiseTime']=$delivery_date;
    $base_job_data['scheduledShipDate']=$delivery_date;
    $base_job_data['priceList']=1;
    $base_job_data['oversMethod']=1;
    $base_job_data['shipInNameOf']=1;
    //$job_data['numbersGuaranteed']=
    //$job_data['convertingToJob']=
    //$job_data['paceConnectOrderID']=1260;
    //$job_data['paceConnectUserId']=65;
    $base_job_data['comboJobPercentageCalculationType']=1;
    //$base_job_data['altCurrency']="USD";
    //$base_job_data['altCurrencyRate']=1.14159;
    //$base_job_data['comboTotal']=10000;
    //$base_job_data['totalPriceAllParts']=215000.000000;
    $base_job_data['readyToSchedule']=1;
    
    // CAUTION duplicate keys in $extra_job_data will overwrite $base_job_data
    $job_data = array_merge($base_job_data, $extra_job_data);

    //there could be more, added from the function args
    
    $job = new CreateObjectHelper();

    try 
    {
        $new_job = $job->createObject('job', $job_data, 'createJob');
        if($debug){
            show_all($new_job);
        }
        return true;  
    }
    catch (Exception $exc) 
    {
        //probably job exists
        return $exc->getMessage();
    }
}


/**
 * 
 * @global type $order_date
 * @global type $delivery_date
 * @global boolean $debug
 * @param type $job_id - The sinalite pressrun id
 * @param type $extra_part_data
 * @return mixed true on success, exception on fail
 */
function efi_update_job_part($job_id, $extra_part_data = NULL){
    
    global $order_date, $delivery_date, $debug;

    $job_part1 = new ReadObjectHelper();

    try
    {
        $base_part_data = $job_part1->getObject2('jobPart', 'job', 'jobPart', $job_id, '01');
    } 
    catch (Exception $exc) 
    {
        //probably part does not exist
        if($debug){
            echo $exc->getMessage();            
        }
        return $exc->getMessage();

    }

    ////ready to update part1
    $part_1_update = new UpdateObjectHelper();

    //$job = $readerObj->getObject('job', 'job', $id, 'readJob');
    $base_part_data->jobPart = '01';
    $base_part_data->colorsS1 = 4;
    $base_part_data->colorsS2 = 4;
    $base_part_data->colorsTotal = 8;
    $base_part_data->dateSetup = $order_date;
    $base_part_data->description = $job_id.' PART ONE';
    $base_part_data->qtyOrdered = 1000;
    $base_part_data->inkDescS1 = 'CMYK';
    $base_part_data->inkDescS2 = 'CMYK';
    $base_part_data->pages = 2;
    $base_part_data->plates = 8;
    $base_part_data->routingTemplate = 1    ;
    
    
    if($extra_part_data){
        $part_data = (object) array_merge((array) $base_part_data, (array) $extra_part_data);
    } else {
        $part_data = $base_part_data;
    }

    
    try
    {
        $update = $part_1_update->updateObject('jobPart', $part_data, 'updateJobPart'); 
        if($debug){
            show_all($part_data);
        }
        return true;
    } catch (Exception $exc) {
        echo $exc->getMessage();
        return false;
    }
    
    
}


/**
 * 
 * @global type $order_date
 * @global type $delivery_date
 * @global boolean $debug
 * @param type $job_if
 * @param type $extra_paper_data
 * @return boolean
 */
function efi_create_job_paper($job_id, $extra_paper_data = array()){
    
    global $order_date, $delivery_date, $debug;
    
    $base_paper_data['job']=$job_id;
    $base_paper_data['jobPart']='01';
    $base_paper_data['description']='Paper For Sinalite '.$job_id;
    //$base_paper_data['inventoryItem']='PPR10032  - OFFSET';
    //$base_paper_data['plannedQuantity']='10000';

    $materialObj = new CreateObjectHelper();
    
    // CAUTION duplicate keys in $extra_job_data will overwrite $base_job_data
    $paper_data = array_merge($base_paper_data, $extra_paper_data);

    try
    {
        $new_object = $materialObj->createObject('jobMaterial', $paper_data, 'createJobMaterial');
        if($debug){
            show_all($new_object);
        }
        return true;
    } catch (Exception $exc) {
        echo $exc->getMessage();
        return false;
    }    
   
}


/**
 * 
 * @global type $order_date
 * @global type $delivery_date
 * @global boolean $debug
 * @param type $job_id
 * @param type $extra_press_data array  of additional press key->values
 * @return boolean tur on success or string exception message
 */
function efi_create_pressform($job_id, $extra_press_data = array()){

    global $order_date, $delivery_date, $debug;
    
    $base_press_data['job']=$job_id;
    $base_press_data['jobPart']='01';
    $base_press_data['formNum']=1;
    $base_press_data['description']='Sinalite '.$job_id; 

    $pressObj = new CreateObjectHelper();
    
    // CAUTION duplicate keys in $extra_job_data will overwrite $base_job_data
    if(count($extra_press_data) > 0){
        $press_data = array_merge($base_press_data, $extra_press_data);
    } else {
        $press_data = $base_press_data;
    }
    
    try 
    {
      $press_data =  $pressObj->createObject('jobPartPressForm', $press_data, 'createJobPartPressForm');

      if($debug){
        show_all($press_data);
        var_dump($press_data);
        return $press_data;
      } else {
          return $press_data;
      }
    } catch (Exception $exc) {
      //probably job exists
      if($debug){
          show_all($press_data);
        return $exc->getMessage();
      } else {
        return false;
      }
    }      
}


/**
 * 
 * @global type $order_date
 * @global type $delivery_date
 * @global boolean $debug
 * @param type $job_id
 * @param type $extra_press_data array  of additional press key->values
 * @return boolean tur on success or string exception message
 */
function efi_create_job_plan($job_id, $extra_plan_data = array()){

    global $order_date, $delivery_date, $debug;
    
    //$base_plan_data['job']=$job_id;
    //$base_plan_data['jobPart']='01';
    $base_plan_data['job']=$job_id;
    $base_plan_data['activityCode']= '21011';
    $base_plan_data['plannedHours']= .5;
    $base_plan_data['part']= '01';
    

    $planObj = new CreateObjectHelper();
    
    // CAUTION duplicate keys in $extra_job_data will overwrite $base_job_data
    if(count($extra_plan_data) > 0){
        $press_data = array_merge($base_plan_data, $extra_plan_data);
    } else {
        $plan_data = $base_plan_data;
    }
    
    try 
    {
      $plan_data =  $planObj->createObject('jobPlan', $plan_data, 'createJobPlan');

      if($debug){
        show_all($plan_data);
      } else {
          return true;
      }
    } catch (Exception $exc) {
      //probably job exists
      if($debug){
          show_all($plan_data);
        return $exc->getMessage();
      } else {
        return false;
      }
    }      
}



////////////////////////////////////////////////   CLASSES   ////////////////////////////

/**
 * Class ReadObjectHelper
 */
class ReadObjectHelper {

    var $soapclient;
    var $typeStructs;

    function ReadObjectHelper() {

        $wsdl_location = "http://" . $GLOBALS['apiusername'] . ":" 
                . $GLOBALS['apipassword'] 
                . "@" . $GLOBALS['epacehost'] 
                . "/rpc/services/ReadObject?wsdl";
        
        $location = "http://" . $GLOBALS['epacehost'] . "/rpc/services/ReadObject";

        $this->soapclient = new SoapClient($wsdl_location, 
                array("trace" => 1, 
                'login' => $GLOBALS['apiusername'],
                'password' => $GLOBALS['apipassword'],
                'location' => $location)
        );
        
        $this->typeStructs = $this->soapclient->__getTypes();
    }

    function getObject($objectname, $pkfield, $value) {


        $OBJ_NAME = ucfirst($objectname);
        $PROX = makeProxyObject($OBJ_NAME, current(preg_grep("/struct $OBJ_NAME {/", $this->typeStructs)));

        $funcName = "read" . $OBJ_NAME;

        //echo "pkfield = $pkfield <BR>";
        $PROX->$pkfield = $value;

        $obj = $this->soapclient->$funcName(array($objectname => (array) $PROX));
        $return_object = $obj->out;

        foreach (array_keys((array) $PROX) as $property) {


            if (!property_exists($return_object, $property)) {
                $return_object->$property = $PROX->$property;
            }
        }

        return $return_object;
    }


    function getObject2($objectname, $pkfield, $pkfield2,  $value, $value2) {


        $OBJ_NAME = ucfirst($objectname);
        $PROX = makeProxyObject($OBJ_NAME, current(preg_grep("/struct $OBJ_NAME {/", $this->typeStructs)));

        $funcName = "read" . $OBJ_NAME;

        //echo "pkfield = $pkfield <BR>";
        $PROX->$pkfield = $value;
        $PROX->$pkfield2 = $value2;

        $obj = $this->soapclient->$funcName(array($objectname => (array) $PROX));
        $return_object = $obj->out;

        foreach (array_keys((array) $PROX) as $property) {


            if (!property_exists($return_object, $property)) {
                $return_object->$property = $PROX->$property;
            }
        }

        return $return_object;
    }
    
    
    function getObjectByArray($objectname, $fields = array()) {


        $OBJ_NAME = ucfirst($objectname);
        $PROX = makeProxyObject($OBJ_NAME, current(preg_grep("/struct $OBJ_NAME {/", $this->typeStructs)));

        $funcName = "read" . $OBJ_NAME;
        
        /*
        //echo "pkfield = $pkfield <BR>";
        $PROX->$pkfield = $value;
        $PROX->$pkfield2 = $value2;
        */
       
        foreach($fields as $k => $v){
            $PROX->$k = $v;
        }
        
        $obj = $this->soapclient->$funcName(array($objectname => (array) $PROX));
        $return_object = $obj->out;

        foreach (array_keys((array) $PROX) as $property) {


            if (!property_exists($return_object, $property)) {
                $return_object->$property = $PROX->$property;
            }
        }

        return $return_object;
    }      
    
    function getObjectByOperation($objectname, $pkfield, $value, $funcName) {
        $obj = $this->soapclient->$funcName(array($objectname => array($pkfield => $value)));
        return $obj->out;
    }

    function getCompositeKeyObject($key, $arr, $funcName) {
        $obj = $this->soapclient->$funcName(array($key => $arr));
        return $obj->out;
    }

    function debug() {

        echo "<BR><BR>";
        echo "BEGIN DEBUG<BR>";
        echo htmlentities($this->soapclient->__getLastRequest());
        echo "<BR><BR>";

        echo htmlentities($this->soapclient->__getLastResponse());


        echo "<BR><BR>";
        echo "END DEBUG<BR>";
    }

}

class CreateObjectHelper {

    var $soapclient;

    function CreateObjectHelper() {

        $location = "http://" . $GLOBALS['epacehost'] . "/rpc/services/CreateObject";
        $this->soapclient = new SoapClient("http://" . $GLOBALS['apiusername'] 
                . ":" . $GLOBALS['apipassword'] 
                . "@" . $GLOBALS['epacehost'] 
                . "/rpc/services/CreateObject?wsdl",
                array("trace" => 1,
                      'login' => $GLOBALS['apiusername'], 'password' => $GLOBALS['apipassword'], 'location' => $location)
        );
    }

    function createObject($key, $value, $funcName) {

        $obj = $this->soapclient->$funcName(array($key => $value));

        /*
          foreach ($returnObj as $key=>$val) {
          if ($val == "false") {
          $returnObj[ $key ] = "0";
          }
          }
         */

        return $this->object2array($obj->out);
    }

    function object2array($object) {
        if (is_object($object)) {
            foreach ($object as $key => $value) {
                $array[$key] = $value;

                //        print "$key=$value<br>";
            }
        } else {

            $array = $object;
        }
        return $array;
    }

    function debug() {
        echo htmlentities($this->soapclient->__getLastRequest());
    }

}

class DeleteObjectHelper {

    var $soapclient;

    function DeleteObjectHelper() {
        $location = "http://" . $GLOBALS['epacehost'] . "/rpc/services/DeleteObject";
        $this->soapclient = new SoapClient("http://" . $GLOBALS['apiusername'] 
                . ":" . $GLOBALS['apipassword'] 
                . "@" . $GLOBALS['epacehost'] 
                . "/rpc/services/DeleteObject?wsdl", 
                array("trace" => 1, 
                      'login' => $GLOBALS['apiusername'], 'password' => $GLOBALS['apipassword'], 'location' => $location)
        );
    }

    function deleteObject($object, $id) {

        $obj = $this->soapclient->deleteObject(array('in0' => $object, 'in1' => $id));
    }

    function debug() {
        echo htmlentities($this->soapclient->__getLastRequest());
    }

}

class UpdateObjectHelper {

    var $soapclient;

    function UpdateObjectHelper() {
        $location = "http://" . $GLOBALS['epacehost'] . "/rpc/services/UpdateObject";
        $this->soapclient = new SoapClient("http://" . $GLOBALS['apiusername'] 
                . ":" . $GLOBALS['apipassword'] 
                . "@" . $GLOBALS['epacehost'] 
                . "/rpc/services/UpdateObject?wsdl", 
                array("trace" => 1, 
                      'login' => $GLOBALS['apiusername'], 'password' => $GLOBALS['apipassword'], 'location' => $location)
        );
    }

    function updateObject($key, $value, $funcName) {
        
        if($obj = $this->soapclient->$funcName(array($key => $value))){
            return true;
        }
        return false;
    }

    function setGLAccount($glAccount) {
        $this->soapclient->updateGLAccount(array('gLAccount' => $glAccount));
    }

    function debug() {
        echo htmlentities($this->soapclient->__getLastRequest());
    }

}

class FindObjectsHelper {

    var $soapclient;

    function FindObjectsHelper() {
        $location = "http://" . $GLOBALS['epacehost'] . "/rpc/services/FindObjects";
        $this->soapclient = new SoapClient("http://" . $GLOBALS['apiusername'] 
                . ":" . $GLOBALS['apipassword'] 
                . "@" . $GLOBALS['epacehost'] 
                . "/rpc/services/FindObjects?wsdl", 
                array("trace" => 1, 
                      'login' => $GLOBALS['apiusername'], 'password' => $GLOBALS['apipassword'], 'location' => $location)
                );
    }

    function getObjects($object, $filter) {

        $ret = $this->soapclient->find(array('in0' => $object, 'in1' => $filter));
        return $ret->out->string;
    }

    function getObjectList($descriptor) {
        $ret = $this->soapclient->loadValueObjects(array('in0' => $descriptor));
        return $ret->out;
    }

    function debug() {
        echo htmlentities($this->soapclient->__getLastRequest());
    }

}




class InvokeProcessObjectHelper
{
    var $soapclient;

    public function InvokeProcessObjectHelper()
    {
        $location = "http://".$GLOBALS['epacehost']."/rpc/services/CloneObject";
        $this->soapclient = new SoapClient("http://".$GLOBALS['apiusername'].":".$GLOBALS['apipassword']."@".$GLOBALS['epacehost']."/rpc/services/InvokeProcess?wsdl", array("trace"=> 1,'login' => $GLOBALS['apiusername'], 'password' => $GLOBALS['apipassword'], 'location' => $location));
    }

    function refreshJobPlan( $jobPartObject )
    {
        //$ret = $this->soapclient->$actionName(array('Job' => $object,'in1' => null, 'in2' => null, 'in3' => $newObject ));
        $ret = $this->soapclient->refreshJobPlan(array('jobPart' => $jobPartObject));
        return $ret->out;
    }
 

    function cloneObject2( $actionName, $object, $newPKey, $newParent, $newObject )
    {
        //$ret = $this->soapclient->$actionName(array('Job' => $object,'in1' => null, 'in2' => null, 'in3' => $newObject ));
        $ret = $this->soapclient->$actionName(array('Job' => $object,'newPrimaryKey' => $newPKey, 'newParent' => $newParent, 'JobAttributesToOverride' => $newObject ));
        return $ret->out;
    }
}



class InvokeActionHelper
{
    var $soapclient;

    
    function InvokeActionHelper()
    {
        $location = "http://".$GLOBALS['epacehost']."/rpc/services/InvokeAction";
        $this->soapclient = new SoapClient("http://".$GLOBALS['apiusername'].":".$GLOBALS['apipassword']."@".$GLOBALS['epacehost']."/rpc/services/InvokeAction?wsdl", array("trace"=> 1,'login' => $GLOBALS['apiusername'], 'password' => $GLOBALS['apipassword'], 'location' => $location));
    }
    
    // somehow I finally figured this out
    function recalc_estimate( $estimate_object )
    {
        $ret = $this->soapclient->calculateEstimate(array('in0' => $estimate_object, 'in1' => $estimate_object->id));
        return $ret->out;
    }
    
        function invokeAction( $actionName, $object, $filter )
    {
        $ret = $this->soapclient->$actionName(array('in0' => $object,'in1' => $filter ));
        return $ret->out;
    }
    
    
}



?>
