#!/usr/bin/php

<?php

require 'vendor/autoload.php';

use Keboola\Csv\CsvFile;
use GetOptionKit\GetOptionKit;
use Guzzle\Http\Client;

$getopt = new GetOptionKit;

$getopt->add( 'i|input:=s' , 'option with multiple value' );
$getopt->add( 'bearer:=s' , 'option with multiple value' );
$getopt->add( 'stage:=s' , 'option with multiple value' );
$getopt->add( 'd|debug'   , 'debug flag' );

$cli = $getopt->parse( $argv );

$bearerToken = $cli->bearer;
$stage = $cli->stage;
$debug = $cli->debug;

$csvFile = new CsvFile( $cli->input );

$header = $csvFile->getHeader();
$data = array();
$i=0;

foreach($csvFile as $row) 
{
    # skip the header
    if ($i == 0) 
    {
        $i++;
        continue;
    }

    for($e=0; $e<count($header); $e++) 
    {
        if ( $header[$e] == 'id' )
        {
            $column = 'provider_id';
        }
        else 
        {
            $column = $header[$e];
        }

        $data[$i][$column] = $row[$e];
    }

    $i++;
}

$client = new Client("https://{$stage}.footprintsmd.com");

$filePath = explode('/', $cli->input);
$fileName = $filePath[count($filePath)-1];

if ( $fileName == 'Locations.csv' )
{
    pushLocations($client, $data);
}
else 
{
    $parameterType = substr( $fileName, 0, -4);
    pushParameters($client, $data, $parameterType);
}

function pushLocations($client, $data)
{
    global $debug, $bearerToken;

    $failedLines = array();
    $lineNumber = 0;

    foreach($data as $d)
    {
        $d['radius'] = 50;
        $d['name'] = str_replace('-', '', $d['name']);
        if ( strlen($d['zipcode']) < 5 )
        {
            $d['zipcode'] = str_pad($d['zipcode'], 5, "0", STR_PAD_LEFT);
        }

        $content = json_encode($d);
        $request = $client->post(
            '/api/v1/locations', 
            [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$bearerToken}"
            ],
            $content,
            [ 
                'debug' => $debug
            ]
        );
        
        try
        {
            $request->send();
        }
        catch( Exception $e )
        {
            $failedLines[$lineNumber] = $content;
        }

        $lineNumber++;
    }

    if ( count($failedLines) > 0 )
    {
        echo "The following lines failed:\n";
        print_r($failedLines);
        echo "\n";
    }
}

function pushParameters($client, $data, $parameterType)
{
    global $debug, $bearerToken;

    $failedLines = array();
    $lineNumber = 0;
    
    foreach($data as $d)
    {
        $req['provider_id'] = $d['provider_id'];
        
        if ( $parameterType != 'General' )
        {
            $d['ParameterType'] = $parameterType;
        }

        unset($d['provider_id']);
        $req['parameters'] = [$d];

        $content = json_encode($req);
        
        $request = $client->post(
            '/api/v1/locations/create_parameters', 
            [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$bearerToken}"
            ],
            $content,
            [ 
                'debug' => $debug
            ]
        );
        
        try
        {
            $request->send();
        }
        catch( Exception $e )
        {
            $failedLines[$lineNumber] = $content;
        }

        $lineNumber++;
    }

        if ( count($failedLines) > 0 )
    {
        echo "The following lines failed:\n";
        print_r($failedLines);
        echo "\n";
    }
}