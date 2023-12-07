import 'dotenv/config';
import {
    dbPath,
    concurrencyLimit,
    mongoUri,
    mongoDatabaseName,
    mongoCollectionName,
    batchSizeMongo,
    localDownloadDir,
} from './config.ts';
import { getReadyJobs, getJobsDB, jobsRemover } from './jobsDB.ts';
import {
    closeMongoConnectionOnQueueFinish,
    convertDocumentsToReplaceOperations,
    convertJobsToDocuments,
    getMongoCollection,
    replaceOperationsExecutor,
} from './mongo.ts';
import { createQueue, executeOperationsInQueue } from './queue.ts';
import { MongoClient } from 'mongodb';
import {heartbeat} from "./debug.ts";
import {checkLocalFileIsBeingProcessed} from "./csv";

const jobsDB = getJobsDB(dbPath);
const mongoClient = new MongoClient(mongoUri);
const mongoCollection = getMongoCollection(mongoClient, mongoDatabaseName, mongoCollectionName);
const queue = createQueue(concurrencyLimit);

let count = 0;
queue.on('active', () => {
    console.log(`Working on item #${++count}.  Size: ${queue.size}  Pending: ${queue.pending}`);
});


checkLocalFileIsBeingProcessed(localDownloadDir)
    .then(() => getReadyJobs(100000, getJobsDB(dbPath)))
    .then(convertJobsToDocuments)
    .then(convertDocumentsToReplaceOperations)
    .then(
        executeOperationsInQueue(
            batchSizeMongo,
            replaceOperationsExecutor(mongoCollection, jobsRemover(jobsDB)),
            queue,
        ),
    )
    .then(closeMongoConnectionOnQueueFinish(mongoClient))
    .then(heartbeat)
    .catch((e) => {
        console.log(e);
    });
