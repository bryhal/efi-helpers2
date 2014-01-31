<?php

ini_set("memory_limit", "256M");

include_once './EFI-helpers.php';

///////////////////////////////////////////////////////////////////////////////

$invokeService = new InvokeActionHelper();
$findService = new FindObjectsHelper();
$updateService = new UpdateObjectHelper();
$readService = new ReadObjectHelper();

// invokeAction() expects an array for the id...
$estimate['id'] = 28105; // an estimate id with 2 parts and 10 quantities, Estimate number is 23534 on our system

$qty_ordered = 2000; // a test value to try - this estimate has quantities of 500,1000,1500,2000,2500 ... 10000

// this is only to give the estimate a customer - not important here, but job requires it.
$fields = array('id' => 28105);
$readEstimate = $readService->getObjectByArray('estimate', $fields);
$readEstimate->customer = 'SINALITE';
$updateEstimate = $updateService->updateObject('estimate', $readEstimate, 'updateEstimate');
//


//// Main stuff....

// getEstimateConvertToJob Object
$EstimateConvertToJob = $invokeService->invokeAction('getEstimateConvertToJob', $estimate, NULL);

//var_dump($EstimateConvertToJob);

foreach ($EstimateConvertToJob->estimateConvertToJobParts->EstimateConvertToJobPart as $part) {

    $estimatePart_id = $part->estimatePart->id; // - the estimatePart id in this iteration
    $quantityToConvert_id = $part->quantityToConvert->id; // this gives me the current id of the quantityToConvert in this estimateJobPart

    // find the estimateQuantity for the requested quantity ordered
    $qty_ordered_id = $findService->getObjects('EstimateQuantity', "@estimatePart=$estimatePart_id and @quantityOrdered=$qty_ordered"); // the id of quantity ordered for this estimateJobPart

    //////////////////////////                                  THE PROBLEM AREA
    
    //   HOW TO SET THE quantityToConvert ??????????????????????????????????????
    
    //   This does not work....
    
    // $EstimateConvertToJob->quantityToConvert->$quantityToConvert_id = $qty_ordered_id;
    
    // this does not work....
    
    // $EstimateConvertToJob->quantityToConvert->id = $qty_ordered_id;
    
    // this does not work....
    
    $EstimateConvertToJob->quantityToConvert = $qty_ordered_id;
        
    //////////////////////////                       ///////////////////////////
    
}

//The rest of this works and will create a job, but always with a quantity of 500...

// needs a job type
$jobType = $readService->getObject('jobType', 'id', 5013);

// modify some object values...
$EstimateConvertToJob->description = 'Converted from estimate 23534, should be qty '.$qty_ordered;

// date crap
$now = new DateTime('NOW');
$promiseDate = $now->add(new DateInterval('P3D'))->format('c');
$EstimateConvertToJob->promiseDate = $promiseDate; // 3 days from now

$EstimateConvertToJob->createNewJob = true;
$EstimateConvertToJob->jobType = $jobType;

$job = $invokeService->invokeAction('convertEstimateToJob', $EstimateConvertToJob, NULL);

// So far, I get a job created, but the quantity is always 500. So I'm doing something wrong...



echo 'Created job:' . $job->job . '<br />';
echo '<pre>';
//var_dump($job);
echo '</pre>';
?>



