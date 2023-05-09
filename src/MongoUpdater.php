<?php

class MongoUpdater
{
    public function __construct(
        protected readonly string $uri,
        protected readonly string $namespace
    ) {
    }

    public function run(array $documents): array
    {
        $manager = new MongoDB\Driver\Manager($this->uri);
        $bulk = new MongoDB\Driver\BulkWrite(['ordered' => false]);

        foreach ($documents as $document) {
            $bulk->update($document[0], $document[1], $document[2]);
        }

        $execute = $manager->executeBulkWrite($this->namespace, $bulk);
        $result = [
            'inserted' => $execute->getInsertedCount(),
            'matched' => $execute->getMatchedCount(),
            'modified' => $execute->getModifiedCount(),
            'upserted' => $execute->getUpsertedCount(),
            'deleted' => $execute->getDeletedCount()
        ];

        return $result;
    }
}