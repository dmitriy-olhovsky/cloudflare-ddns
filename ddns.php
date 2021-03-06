#!/usr/bin/env php
<?php

require __DIR__ . '/Cloudflare.php';

$confFile = __DIR__ . '/config.php';
if (!file_exists($confFile))
{
  echo "Missing config file. Please copy config.php.skel to config.php and fill out the values therein.\n";
  return 1;
}

$config = require $confFile;

foreach (['cloudflare_email', 'cloudflare_api_key', 'domain', 'record_name', 'ttl', 'protocol'] as $key)
{
  if (!isset($config[$key]) || $config[$key] === '')
  {
    echo "config.php is missing the '$key' config value\n";
    return 1;
  }
}

$api = new Cloudflare($config['cloudflare_email'], $config['cloudflare_api_key']);

$domain     = $config['domain'];
$recordName = $config['record_name'];

$ip = getIP($config['protocol']);

$verbose = !isset($argv[1]) || $argv[1] != '-s';

try
{
  $zone = $api->getZone($domain);
  if (!$zone)
  {
    echo "domain $domain not found\n";
    return 1;
  }

  $records = $api->getZoneDnsRecords($zone['id'], ['name' => $recordName]);
  $record  = $records && $records[0]['name'] == $recordName ? $records[0] : null;

  if (!$record)
  {
    if ($verbose) echo "No existing record found. Creating a new one\n";
    $ret = $api->createDnsRecord($zone['id'], 'A', $recordName, $ip, ['ttl' => $config['ttl']]);
  }
  elseif ($record['type'] != 'A' || $record['content'] != $ip || $record['ttl'] != $config['ttl'])
  {
    if ($verbose) echo "Updating record.\n";
    $ret = $api->updateDnsRecord($zone['id'], $record['id'], [
      'type'    => 'A',
      'name'    => $recordName,
      'content' => $ip,
      'ttl'     => $config['ttl'],
    ]);
  }
  else
  {
    if ($verbose) echo "Record appears OK. No need to update.\n";
  }
  return 0;
}
catch (Exception $e)
{
  echo "Error: " . $e->getMessage() . "\n";
  return 1;
}


// http://stackoverflow.com/questions/3097589/getting-my-public-ip-via-api
// http://major.io/icanhazip-com-faq/
function getIP($protocol)
{
  $prefixes = ['ipv4' => 'ipv4.', 'ipv6' => 'ipv6.', 'auto' => ''];
  if (!isset($prefixes[$protocol]))
  {
    throw new Exception('Invalid "protocol" config value.');
  }
  return trim(file_get_contents('http://' . $prefixes[$protocol] . 'icanhazip.com'));
}