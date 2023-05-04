<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/config');
$dotenv->load();

use \phpseclib3\Net\SFTP;
use \MongoDB\Driver\Query;
use \MongoDB\BSON\Regex;

// SFTP credentials
$sftpHost = $_ENV['SFTP_HOST'];
$sftpPort = $_ENV['SFTP_PORT'];
$sftpUsername = $_ENV['SFTP_USERNAME'];
$sftpPassword = $_ENV['SFTP_PASSWORD'];
$sftpExportPath = $_ENV['SFTP_EXPORT_PATH'];
$sftpCompletedPath = $_ENV['SFTP_COMPLETED_PATH'];

// MongoDB credentials
$mongoUsername = $_ENV['MONGO_USERNAME'];
$mongoPassword = $_ENV['MONGO_PASSWORD'];
$mongoHost = $_ENV['MONGO_HOST'];
$mongoClient = new MongoDB\Client("{$mongoHost}/admin?retryWrites=true&w=majority", [
    'username' => $mongoUsername,
    'password' => $mongoPassword,
]);
$mongoDb = $mongoClient->selectDatabase($_ENV['MONGO_DB']);
$mongoCollection = $mongoDb->selectCollection($_ENV['MONGO_COLLECTION']);

// Webhook URL
$webhookUrl = $_ENV['WEBHOOK_URL'];

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
$dataArray = parseCsv($csvData);
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

function parseCsv($csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true, $trim_csv = true)  {
    return array_map(
        function ($line) use ($delimiter, $trim_fields) {
            return array_map(
                function ($field) {
                    return str_replace('!!Q!!', '"', utf8_decode(urldecode($field)));
                },
                $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line)
            );
        },
        preg_split(
            $skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s',
            preg_replace_callback(
                '/"(.*?)"/s',
                function ($field) {
                    return urlencode(utf8_encode($field[1]));
                },
                $enc = preg_replace('/(?<!")""/', '!!Q!!', $trim_csv ? trim($csv_string) : $csv_string)
            )
        )
    );
}
