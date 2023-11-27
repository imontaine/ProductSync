<?php
require_once __DIR__ . '/setup.php';

use League\Csv\Reader;

// Download oldest CSV file from FTP
try{
    $localCsv = downloadOldestFTPFile();
    $workingFileName = getOldestFileFromFTP();
    echo "working on the file ".$workingFileName."\n";
} catch (Exception $e) {
    exit($e->getMessage());
}

// Setup batch processor
$batchProcessor = new \BatchProcessor($_ENV['BATCH_SIZE'], $_ENV['BATCH_CONCURRENT_LIMIT']);
$csvSplitFile = null;

// Reads the csv
try{
    $reader = Reader::createFromStream($localCsv);
    $reader->setHeaderOffset(0);
} catch (Exception $e) {
    exit($e->getMessage());
}

$columnsToExclude = array_combine(MongoUpdater::$EXCLUDE_FIELDS, array_fill(0, count(MongoUpdater::$EXCLUDE_FIELDS), ''));

foreach ($reader as $i => $row)
{
    // If reached the max processing limit, split the csv
    if ($i >= ($_ENV['CSV_SPLIT_LIMIT'] ?? 50000)) {
        if (!$csvSplitFile) {
            $csvSplitFile = tmpfile();
            fputcsv($csvSplitFile, $reader->getHeader());
        }
        fputcsv($csvSplitFile, $row);
        continue;
    }
    // Prepare row
    $internalMetaData =  ['last_found_in_file' => $workingFileName];
    $preparedRow = prepareRow(array_merge($row, ['_internal_metadata' => $internalMetaData]));
    // Process row
    $batchProcessor->processDocument([['sku' => $row['sku']], ['$set' => $preparedRow, '$unset' => $columnsToExclude], ['upsert' => true]]);
}

if (!isset($i)) {
    onFinish($csvSplitFile);
    exit;
}

// Run batch if its has fewer documents then the size limit which triggers processing
if ($i % $batchProcessor->batchSize > 0) {
    $batchProcessor->addBatchToQueue($batchProcessor->currentBatch);
}

$batchProcessor->onFinish(function() use ($batchProcessor, $csvSplitFile) {
    onFinish($csvSplitFile);
});