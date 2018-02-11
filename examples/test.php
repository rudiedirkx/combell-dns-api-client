<?php

use rdx\combelldns;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/env.php';

$client = new combelldns\Client(new combelldns\WebAuth(COMBELL_BASE, COMBELL_USER, COMBELL_PASS));
var_dump($client->logIn());

echo "\n";

$domains = $client->getDomains();
// print_r($domains);

$domain = array_reduce($domains, function($result, combelldns\Domain $domain) {
	return sha1($domain->name) == '3b1fab5b80b7da9ef662aafd8b1162a3f3c6b422' ? $domain : $result;
}, null);
var_dump($domain);

if ( !$domain ) return;

$records = $client->getDnsRecords($domain, 'txt');
print_r($records);

$delete = array_filter($records, function(combelldns\DnsRecord $record) {
	return preg_match('#(^|\.)dns\d+\.#', $record->name) > 0;
});
$delete = $delete ? $delete[ array_rand($delete) ] : null;
var_dump($delete);

if ( $delete ) {
	var_dump($client->deleteDnsRecord($domain, $delete));
}

$add = new combelldns\DnsRecord(0, 'dns' . rand(101, 999) . '.' . $domain->name, 'txt', rand(), 300);
print_r($add);
var_dump($client->addDnsRecord($domain, $add));
