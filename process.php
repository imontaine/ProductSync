<?php

include __DIR__.'/setup.php';

$urlParts = parse_url($_ENV['MONGO_HOST']);

$updater = new MongoUpdater(
    "{$urlParts['scheme']}://{$_ENV['MONGO_USERNAME']}:{$_ENV['MONGO_PASSWORD']}@{$urlParts['host']}",
    "{$_ENV['MONGO_DB']}.{$_ENV['MONGO_COLLECTION']}"
);

$lines = [];
while (FALSE !== ($line = fgets(STDIN))) {
    $lines[] = $line;
}

$documents = json_decode(implode('', $lines), true);

$result = $updater->run($documents);

echo json_encode($result);