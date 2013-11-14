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
$getopt->add( 'baseEmail:=s' , 'option with multiple value' );
$getopt->add( 'qty:=s' , 'option with multiple value' );
$getopt->add( 'role:=s' , 'option with multiple value' );
$getopt->add( 'd|debug'   , 'debug flag' );

$cli = $getopt->parse( $argv );

$bearerToken = $cli->bearer;
$stage = $cli->stage;
$debug = $cli->debug;
$baseEmail = $cli->baseEmail;
$quantity = $cli->qty;
$role = $cli->role;

$emailParts = explode('@', $baseEmail);
$failedLines = array();

$client = new Client("https://{$stage}.footprintsmd.com");

for($i=0; $i<$quantity; $i++)
{
    $UUID = rand(1,100000);

    $email = $emailParts[0] . "+bulk{$UUID}@" . $emailParts[1]; 

    $user = [
        "fullname" => "Bulk User {$UUID}",
        "email" => $email,
        "password" => "BulkUser123",
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