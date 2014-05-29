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
$getopt->add( 'role:=s' , 'option with multiple value' );
$getopt->add( 'd|debug'   , 'debug flag' );

$cli = $getopt->parse( $argv );

$bearerToken = $cli->bearer;
$stage = $cli->stage;
$debug = $cli->debug;
$role = $cli->role;

$failedLines = array();

$client = new Client("https://{$stage}.footprintsmd.com");

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
        $data[$i][$header[$e]] = $row[$e];
    }

    $i++;
}

foreach($data as $d)
{
    $user = [
        "fullname" => $d['fullname'],
        "email" => $d['email'],
        "role" => $role
    ];
    
    $content = json_encode($user);
    
    $request = $client->post(
        '/api/v1/users', 
        [ 'Content-Type' => 'application/json',
          'Authorization' => "Bearer {$bearerToken}" ],
        $content,
        [ 'debug' => $debug ]
    );
    
    try
    {
        $request->send();
    }
    catch( Exception $e )
    {
        $failedLines[$i] = $content;
    }
}

if ( count($failedLines) > 0 )
{
    echo "The following lines failed:\n";
    print_r($failedLines);
    echo "\n";
}