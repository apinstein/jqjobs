<?php

$schemaFile = dirname(__FILE__) . '/schema.xml';
$xml = simplexml_load_file($schemaFile);

// prune unused tables
$unusedTables = array(
    '//table[@name="mp_version"]',
);
foreach ($unusedTables as $xpath) {
    removeNode($xml, $xpath, 'all');
}

// fix JQStore case
$uniqueNode = $xml->xpath('//table[@name="jqstore_managed_job"]');
$uniqueNode[0]['phpName'] = 'JQStoreManagedJob';

// write out munged XML tree
file_put_contents($schemaFile, $xml->asXML());

function removeNode($xml, $path, $multi='one')
{
    $result = $xml->xpath($path);

    # for wrong $path
    if (!isset($result[0])) return false;

    switch ($multi) {
        case 'all':
            $errlevel = error_reporting(E_ALL & ~E_WARNING);
            foreach ($result as $r) unset ($r[0]);
            error_reporting($errlevel);
            return true;

        case 'child':
            unset($result[0][0]);
            return true;

        case 'one':
            if (count($result[0]->children())==0 && count($result)==1) {
                unset($result[0][0]);
                return true;
            }

        default:
            return false;             
    }
}  
