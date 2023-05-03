<?php
require __DIR__ . '/vendor/autoload.php';

use \phpseclib3\Net\SFTP;
use \MongoDB\Driver\Query;
use \MongoDB\BSON\Regex;

// SFTP credentials
$sftpHost = getenv('SFTP_HOST');
$sftpPort = getenv('SFTP_PORT');
$sftpUsername = getenv('SFTP_USERNAME');
$sftpPassword = getenv('SFTP_PASSWORD');
$sftpExportPath = getenv('SFTP_EXPORT_PATH');
$sftpCompletedPath = getenv('SFTP_COMPLETED_PATH');

// MongoDB credentials
$mongoUsername = getenv('MONGO_USERNAME');
$mongoPassword = getenv('MONGO_PASSWORD');
$mongoHost = getenv('MONGO_HOST');
$mongoClient = new MongoDB\Client("{$mongoHost}/admin?retryWrites=true&w=majority", [
    'username' => $mongoUsername,
    'password' => $mongoPassword,
]);
$mongoDb = $mongoClient->selectDatabase(getenv('MONGO_DB'));
$mongoCollection = $mongoDb->selectCollection(getenv('MONGO_COLLECTION'));

// Webhook URL
$webhookUrl = getenv('WEBHOOK_URL');

// connect to SFTP
$sftp = new SFTP($sftpHost, $sftpPort);
if (!$sftp->login($sftpUsername, $sftpPassword)) {
    exit('Login Failed');
}

// get the oldest CSV file in the exports directory
$files = $sftp->nlist($sftpExportPath);
$oldestFile = null;

foreach ($files as $file) {
    if (substr($file, -4) === '.csv' && ($oldestFile === null || $sftp->filemtime("$sftpExportPath/$file") < $sftp->filemtime("$sftpExportPath/$oldestFile"))) {
        $oldestFile = $file;
    }
}

if ($oldestFile === null) {
    exit('No CSV files found');
}

// read CSV data
$csvData = $sftp->get("$sftpExportPath/$oldestFile");
if ($csvData === false) {
    exit('Failed to read CSV file');
}

// parse CSV data into array
$dataArray = array_map('str_getcsv', explode("\n", trim($csvData)));
$headers = array_shift($dataArray);
$data = array();
foreach ($dataArray as $row) {
    if (!empty($row)) {
        $data[] = array_combine($headers, $row);
    }
}

// Function to process additional_attributes
function processAdditionalAttributes($additional_attributes)
{
    $attributes = explode(',', $additional_attributes);
    $result = [];

    foreach ($attributes as $attribute) {
        if (strpos($attribute, '=') !== false) {
            list($key, $value) = explode('=', $attribute, 2);
            $values = array_map(function($v){ return json_decode($v); }, explode('|', $value));
            $result[$key] =  count($values) <= 1 ? trim($values[0], '"') : $values;
        }
    }

    return $result;
}

// Initialize upsert status
$upsertSuccessful = true;

// upsert each document into MongoDB
foreach ($data as $row) {
    if (isset($row['additional_attributes'])) {
        $row['additional_attributes_expanded'] = processAdditionalAttributes($row['additional_attributes']);
        unset($row['additional_attributes']);
    }

    // Convert updated_at and created_at fields to date time fields
    $row['updated_at'] = new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat('m/d/y, g:i A', $row['updated_at'])->getTimestamp() * 1000);
    $row['created_at'] = new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat('m/d/y, g:i A', $row['created_at'])->getTimestamp() * 1000);

    $filter = ['sku' => $row['sku']];
    $options = ['upsert' => true];
    // Add 'mongo_updated_at' timestamp
    $row['mongo_updated_at'] = new MongoDB\BSON\UTCDateTime();
    $update = ['$set' => $row];
    $result = $mongoCollection->updateOne($filter, $update, $options);

    // Check upsert result and update the status
    if ($result->getModifiedCount() === 0 && $result->getUpsertedCount() === 0) {
        $upsertSuccessful = false;
    }
}

function sendWebhook($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Move CSV file to completed directory only if upsert is successful
if ($upsertSuccessful) {
    if (!$sftp->rename("$sftpExportPath/$oldestFile", "$sftpCompletedPath/$oldestFile")) {
        exit('Failed to move CSV file');
    }

    // Send webhook
    sendWebhook($webhookUrl);

    echo 'Data written to MongoDB and CSV file moved to completed directory.';
} else {
    echo 'Upsert failed. CSV file not moved.';
}
