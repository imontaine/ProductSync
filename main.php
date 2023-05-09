<?php
require_once __DIR__ . '/setup.php';

use League\Csv\Reader;

// Download oldest CSV file from FTP
try{
    $localCsv = downloadOldestFTPFile();
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

foreach ($reader as $i => $row)
{
    // If reached the max processing limit, split the csv
    if ($i >= ($_ENV['CSV_SPLIT_LIMIT'] ?? 50000)) {
        $csvSplitFile = tmpfile();
        fputcsv($csvSplitFile, $row);
        continue;
    }
    // Prepare row
    $preparedRow = prepareRow($row);
    // Process row
    $batchProcessor->processDocument([['sku' => $row['sku']], ['$set' => $preparedRow], ['upsert' => true]]);
}

// Run batch if its has fewer documents then the size limit which triggers processing
if ($i % $batchProcessor->batchSize > 0) {
    $batchProcessor->addBatchToQueue($batchProcessor->currentBatch);
}

$batchProcessor->onFinish(function() use ($batchProcessor, $csvSplitFile) {
    // Move the file we just processed to the completed folder
    moveToCompletedFTP(getOldestFileFromFTP());

    // Move split file (if any)
    if (isset($csvSplitFile)) {
        $date = getDateFromFileName(getOldestFileFromFTP());
        $date->modify("+1 second");
        $remoteFileName = $date->format('Y-m-d_H-i-s')."_export_catalog_product-SPLIT.csv";
        rewind($csvSplitFile);
        uploadToFTP($remoteFileName, $csvSplitFile);
    }

    // Dispatch webhook
    sendWebhook($_ENV['WEBHOOK_URL']);
});