<?php

// Function to process additional_attributes
use phpseclib3\Net\SFTP;

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

function prepareRow($row) {

    if (isset($row['additional_attributes'])) {
        $row['additional_attributes_expanded'] = processAdditionalAttributes($row['additional_attributes']);
        unset($row['additional_attributes']);
    }

    // Convert updated_at and created_at fields to date time fields
    if ($row['updated_at']) {
        $row['updated_at'] = new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat('m/d/y, g:i A',
                $row['updated_at'])->getTimestamp() * 1000);
    }

    if ($row['created_at']) {
        new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat('m/d/y, g:i A',
                $row['created_at'])->getTimestamp() * 1000);
    }

    // Add 'mongo_updated_at' timestamp
    $row['mongo_updated_at'] = new MongoDB\BSON\UTCDateTime();

    // Remove sku from document payload for mongo performance reasons
    unset($row['sku']);

    return $row;
}

function sendWebhook($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function loginFTP() {
    // connect to SFTP
    static $sftp;

    if ($sftp) {
        return $sftp;
    }

    $sftpHost = $_ENV['SFTP_HOST'];
    $sftpPort = $_ENV['SFTP_PORT'];
    $sftpUsername = $_ENV['SFTP_USERNAME'];
    $sftpPassword = $_ENV['SFTP_PASSWORD'];


    $sftp = new SFTP($sftpHost, $sftpPort);
    if (!$sftp->login($sftpUsername, $sftpPassword)) {
        throw new Exception('Login Failed');
    }

    return $sftp;
}

function downloadFTPFile($oldestFile, $localPath) {
    $sftp = loginFTP();
    if (!$sftp->get($_ENV['SFTP_EXPORT_PATH']."/$oldestFile", $localPath)) {
        throw new Exception('Errow downloading file from FTP');
    }
}

function downloadOldestFTPFile() {
    $oldestFile = getOldestFileFromFTP();
    $localCsv = tmpfile();
    downloadFTPFile($oldestFile, $localCsv);
    return $localCsv;
}

function uploadToFTP($remoteFileName, $localFile){
    $sftp = loginFTP();
    $sftpExportPath = $_ENV['SFTP_EXPORT_PATH'];
    if (!$sftp->put("$sftpExportPath/$remoteFileName", $localFile, SFTP::SOURCE_LOCAL_FILE)) {
        exit('Failed to upload split CSV file');
    }
}

function moveToCompletedFTP($oldestFile) {
    $sftp = loginFTP();
    $sftpExportPath = $_ENV['SFTP_EXPORT_PATH'];
    $sftpCompletedPath = $_ENV['SFTP_COMPLETED_PATH'];
    if (!$sftp->rename("$sftpExportPath/$oldestFile", "$sftpCompletedPath/$oldestFile")) {
        exit('Failed to move CSV file');
    }
}

function getOldestFileFromFTP($cache=true) {

    $sftp = loginFTP();
    $sftpExportPath = $_ENV['SFTP_EXPORT_PATH'];

    static $oldestFile;

    if ($oldestFile && $cache) {
        return $oldestFile;
    }

    // get the oldest CSV file in the exports directory
    $files = $sftp->nlist($sftpExportPath);
    $filesWithDate = array_filter(array_map(function ($file){
        $date = getDateFromFileName($file);
        return $date ? [
            'file' => $file,
            'date' => $date->getTimestamp()
        ] : null;
    }, $files));

    usort($filesWithDate, fn($a, $b) => $a['date'] <=> $b['date']);

    $oldestFile = isset($filesWithDate[0]) ? $filesWithDate[0]['file'] : null;

    if (!$oldestFile) {
        throw new Exception('No CSV files found');
    }

    return $oldestFile;
}

function getDateFromFileName($fileName) {
    $match = preg_match('/\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/', $fileName, $matches);
    return $match ? DateTime::createFromFormat('Y-m-d_H-i-s', $matches[0]) : null;
}

function time_elapsed() {
    static $times = [];
    static $last = null;

    $now = microtime(true);

    if ($last != null) {
        $diff = ($now - $last);
        $times[] = $diff;
        echo '<!-- ' . $diff . " -->\n";
    }

    $last = $now;
}

function array_every(array $arr, callable $predicate) {
    foreach ($arr as $e) {
        if (!call_user_func($predicate, $e)) {
            return false;
        }
    }

    return true;
}
