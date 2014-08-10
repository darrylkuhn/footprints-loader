#!/usr/bin/php

<?php

require 'vendor/autoload.php';

use Keboola\Csv\CsvFile;
use GetOptionKit\GetOptionKit;
use Guzzle\Http\Client;

$getopt = new GetOptionKit;

$getopt->add( 'node:=s', 'option with multiple value' );
$getopt->add( 'token:=s', 'option with multiple value' );
$getopt->add( 'account:=s', 'where to write csv data' );
$getopt->add( 'limit:=i', 'number of records to pull in total' );
$getopt->add( 'chunck:=i', 'number of records to pull at once' );
$getopt->add( 'd|debug', 'debug flag' );

$cli = $getopt->parse( $argv );

$node = $cli->node;
$bearerToken = $cli->token;
$debug = $cli->debug;
$account = $cli->account;
$limit = $cli->limit;
$chunck = $cli->chunck;

$timeIncrements = [
    '-1 week',
    '-2 week',
    '-1 month',
    '-4 month',
    '-1 year',
    '-2 year',
    '-5 year'
];

if ( strpos($node, '.') === false )
{
	$endpoint = "https://{$node}.salesforce.com";
}
else 
{
	$endpoint = "https://$node";
}

$client = new Client($endpoint);

$records =[];
$increment = 0;
$headersWritten = false;
$csvFile = new Keboola\Csv\CsvFile( __DIR__ .'/'. "{$account}UserReport.csv" );

echo "Writing User Report";

while( true )
{
    echo ".";

    $previousDateString = isset($dateString) ? $dateString : "";

    $date = new DateTime($previousDateString);
    $dateString = $date->modify($timeIncrements[$increment])->format(DateTime::ATOM);

    $dateBounds = "CreatedDate >= {$dateString}";
    if ($previousDateString ) $dateBounds .= " AND CreatedDate < {$previousDateString}";

    $request = $client->get(
        "/services/data/v29.0/query/?q=SELECT Name, Email, ID, Title, Department, Division, Manager.Name, City, PostalCode, State, Country, Phone, IsActive, Profile.Name, UserType, LastLoginDate, CreatedBy.Name, CreatedDate, LastModifiedBy.Name, LastModifiedDate FROM User WHERE {$dateBounds} ORDER BY CreatedDate", 
        [ 'Content-Type' => 'application/json',
          'Authorization' => "Bearer {$bearerToken}" ],
        [ 'debug' => $debug ] );

    try
    {
        $userRows = array();
        $response = $request->send()->json(); 

        // If there were no records then we're done
        if( count($response['records']) == 0 && $increment == count($timeIncrements)-1 ) 
        {
            break;
        }

        // Add in the headers first
        foreach( $response['records'] as $record )
        {
            $userRows[] = flatten( $record );
        }

        // If this is our first pass then prepend the CSV headers
        if ( count($userRows) > 0 && !$headersWritten )
        {
            array_unshift( $userRows, array_keys($userRows[0]) );
            $headersWritten = true;
        }

        foreach ( $userRows as $row ) 
        {
            $csvFile->writeRow($row);
        }

        if ( count($response['records']) < $chunck && $increment < count($timeIncrements)-1 ) $increment++;
    }
    catch( Guzzle\Http\Exception\ClientErrorResponseException $e )
    {
        if ( $e->getResponse()->getStatusCode() == 401 )
        {
            die( "Invalid access token\n" );
        }
        else 
        {
            die( "Request {$e->getRequest()} failed with response: ".$e->getResponse()."\n" );
        }
    }
}
echo " Complete\n";

// Write Task Report
$records =[];
$i = 0;
$increment = 0;
$headersWritten = false;
$dateString = null;
$previousDateString = null;
$csvFile = new Keboola\Csv\CsvFile( __DIR__ .'/'. "{$account}TaskReport.csv" );

echo "Writing Task Report";
while( $i < $limit )
{
    echo ".";
    $previousDateString = isset($dateString) ? $dateString : "";

    $date = new DateTime($previousDateString);
    $dateString = $date->modify($timeIncrements[$increment])->format(DateTime::ATOM);

    $dateBounds = "CreatedDate >= {$dateString}";
    if ($previousDateString ) $dateBounds .= " AND CreatedDate < {$previousDateString}";

    $request = $client->get(
        "/services/data/v29.0/query/?q=SELECT Subject, Owner.Name, ActivityDate, What.Name, Who.Name, Description, Status, IsClosed, CreatedBy.Name, CreatedDate, LastModifiedBy.Name, LastModifiedDate from Task WHERE {$dateBounds} ORDER BY CreatedDate DESC", 
        [ 'Content-Type' => 'application/json',
          'Authorization' => "Bearer {$bearerToken}" ],
        [ 'debug' => $debug ] );

    try
    {
        $taskRows = array();
        $response = $request->send()->json(); 

        // If there were no records then we're done
        if( count($response['records']) == 0 && $increment == count($timeIncrements)-1 ) 
        {
            break;
        }

        foreach( $response['records'] as $record )
        {
            $taskRows[] = flatten( $record );
        }

        // If this is our first pass then prepend the CSV headers
        if ( count($taskRows) > 0 && !$headersWritten )
        {
            array_unshift( $taskRows, array_keys($taskRows[0]) );
            $headersWritten = true;
        }

        foreach ( $taskRows as $row ) 
        {
            $csvFile->writeRow($row);
        }

        if ( count($response['records']) < $chunck && $increment < count($timeIncrements)-1 ) $increment++;
        $i += count($response['records']);
    }
    catch( Guzzle\Http\Exception\ClientErrorResponseException $e )
    {
        if ( $e->getResponse()->getStatusCode() == 401 )
        {
            die( "Invalid access token\n" );
        }
        else 
        {
            die( "Request failed: ".$e->getResponse()->getReasonPhrase()."\n" );
        }
    }
}

echo " Complete\n";

// Write Account Report
$records =[];
$i = 0;
$increment = 0;
$headersWritten = false;
$dateString = null;
$previousDateString = null;
$csvFile = new Keboola\Csv\CsvFile( __DIR__ .'/'. "{$account}AccountReport.csv" );

echo "Writing Account Report";
while( $i < $limit )
{
    echo ".";
    $previousDateString = isset($dateString) ? $dateString : "";

    $date = new DateTime($previousDateString);
    $dateString = $date->modify($timeIncrements[$increment])->format(DateTime::ATOM);

    $dateBounds = "CreatedDate >= {$dateString}";
    if ($previousDateString ) $dateBounds .= " AND CreatedDate < {$previousDateString}";

    $request = $client->get(
        "/services/data/v29.0/query/?q=SELECT Id, Name, Type, Industry, Description, Owner.Id, Owner.Name, BillingCity, BillingState, BillingPostalCode, BillingCountry, Phone, CreatedDate from Account WHERE {$dateBounds} ORDER BY CreatedDate DESC", 
        [ 'Content-Type' => 'application/json',
          'Authorization' => "Bearer {$bearerToken}" ],
        [ 'debug' => $debug ] );

    try
    {
        $accountRows = array();
        $response = $request->send()->json(); 

        // If there were no records then we're done
        if( count($response['records']) == 0 && $increment == count($timeIncrements)-1 ) 
        {
            break;
        }

        foreach( $response['records'] as $record )
        {
            $accountRows[] = flatten( $record );
        }

        // If this is our first pass then prepend the CSV headers
        if ( count($accountRows) > 0 && !$headersWritten )
        {
            array_unshift( $accountRows, array_keys($accountRows[0]) );
            $headersWritten = true;
        }

        foreach ( $accountRows as $row ) 
        {
            $csvFile->writeRow($row);
        }

        if ( count($response['records']) < $chunck && $increment < count($timeIncrements)-1 ) $increment++;
        $i += count($response['records']);
    }
    catch( Guzzle\Http\Exception\ClientErrorResponseException $e )
    {
        if ( $e->getResponse()->getStatusCode() == 401 )
        {
            die( "Invalid access token\n" );
        }
        else 
        {
            die( "Request failed: ".$e->getResponse()->getReasonPhrase()."\n" );
        }
    }
}

echo " Complete\n";


// Write Task Report
$records =[];
$i = 0;
$increment = 0;
$headersWritten = false;
$dateString = null;
$previousDateString = null;
$csvFile = new Keboola\Csv\CsvFile( __DIR__ .'/'. "{$account}OpportunityReport.csv" );

echo "Writing Opportunity Report";
while( $i < $limit )
{
    echo ".";
    $previousDateString = isset($dateString) ? $dateString : "";

    $date = new DateTime($previousDateString);
    $dateString = $date->modify($timeIncrements[$increment])->format(DateTime::ATOM);

    $dateBounds = "CreatedDate >= {$dateString}";
    if ($previousDateString ) $dateBounds .= " AND CreatedDate < {$previousDateString}";

    $request = $client->get(
        "/services/data/v29.0/query/?q=SELECT Id, Name, Account.Name, Type, StageName, Owner.Id, Owner.Name, CreatedBy.Name, CreatedDate, LastModifiedBy.Name, LastModifiedDate, CloseDate from Opportunity WHERE {$dateBounds} ORDER BY CreatedDate DESC", 
        [ 'Content-Type' => 'application/json',
          'Authorization' => "Bearer {$bearerToken}" ],
        [ 'debug' => $debug ] );

    try
    {
        $opportunityRows = array();
        $response = $request->send()->json(); 

        // If there were no records then we're done
        if( count($response['records']) == 0 && $increment == count($timeIncrements)-1 ) 
        {
            break;
        }

        foreach( $response['records'] as $record )
        {
            $opportunityRows[] = flatten( $record );
        }

        // If this is our first pass then prepend the CSV headers
        if ( count($opportunityRows) > 0 && !$headersWritten )
        {
            array_unshift( $opportunityRows, array_keys($opportunityRows[0]) );
            $headersWritten = true;
        }

        foreach ( $opportunityRows as $row ) 
        {
            $csvFile->writeRow($row);
        }

        if ( count($response['records']) < $chunck && $increment < count($timeIncrements)-1 ) $increment++;
        $i += count($response['records']);
    }
    catch( Guzzle\Http\Exception\ClientErrorResponseException $e )
    {
        if ( $e->getResponse()->getStatusCode() == 401 )
        {
            die( "Invalid access token\n" );
        }
        else 
        {
            die( "Request failed: ".$e->getResponse()->getReasonPhrase()."\n" );
        }
    }
}

echo " Complete\n";

// There is only ever one organization record
echo "Writing Organization Report";
$csvFile = new Keboola\Csv\CsvFile( __DIR__ .'/'. "{$account}OrgReport.csv" );

$request = $client->get(
    "/services/data/v29.0/query/?q=SELECT Id, Name, OrganizationType, PrimaryContact, Division, City, State, PostalCode, Country, Phone, CreatedDate from Organization", 
    [ 'Content-Type' => 'application/json',
      'Authorization' => "Bearer {$bearerToken}" ],
    [ 'debug' => $debug ] );

try
{
    $response = $request->send()->json(); 
    $organizationDetails[] = flatten( $response['records'][0] );
    array_unshift( $organizationDetails, array_keys($organizationDetails[0]) );

    foreach ( $organizationDetails as $row ) 
    {
        $csvFile->writeRow($row);
    }
}
catch( Guzzle\Http\Exception\ClientErrorResponseException $e )
{
    if ( $e->getResponse()->getStatusCode() == 401 )
    {
        die( "Invalid access token\n" );
    }
    else 
    {
        die( "Request failed: ".$e->getResponse()->getReasonPhrase()."\n" );
    }
}

echo " Complete\n";

function flatten( $record, $parentKey=null )
{
    $flatRecord = [];

    unset( $record['attributes'] );

    foreach( $record as $key => $value ) 
    {
        if ( $key == 'attributes' )
        {
            continue;
        }
        elseif( is_array($value) )
        {
            $flattenedValues = flatten( $value, $parentKey.$key );
            $flatRecord = array_merge( $flatRecord, $flattenedValues );
        }
        else
        {
            $flatRecord[$parentKey.$key] = $value;
        }

    }

    return $flatRecord;
}