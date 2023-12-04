import { Job } from './jobsDB.ts';
import { MongoClient, ReplaceOneModel } from 'mongodb';
import _ from 'underscore';
import { OperationsExecutor } from './queue';

export type JobDocument = {
    job: Job;
    document: any;
};

export const convertJobsToDocuments = (jobs: Job[]): JobDocument[] => jobs.map(convertJobToDocument);

export const convertJobToDocument = (job: Job): JobDocument => {
    const { additional_attributes: rawAdditionalAttributes, ...product } = JSON.parse(job.row_content);
    const additionalAttributes = processAdditionalAttributes(rawAdditionalAttributes || '');
    return {
        job: job,
        document: {
            ...product,
            ...additionalAttributes,
        },
    };
};

export interface JobOperation {
    job: Job;
    operation: any;
}

export interface JobReplaceOperation extends JobOperation {
    job: Job;
    operation: {
        replaceOne: ReplaceOneModel;
    };
}

export const convertDocumentsToReplaceOperations = (jobDocuments: JobDocument[]): JobReplaceOperation[] =>
    jobDocuments.map(convertDocumentToReplaceOperation);

export const convertDocumentToReplaceOperation = (jobDocument: JobDocument): JobReplaceOperation => {
    return {
        job: jobDocument.job,
        operation: {
            replaceOne: {
                filter: { sku: jobDocument.document.sku },
                replacement: jobDocument.document,
                upsert: true,
            },
        },
    };
};

//tes case: affirm_express="Yes",featured_product_image="no_selection",features="Digital"|"Alarm"|"Chronograph"|"Rubber",features_text="Alarm;Chronograph;Rubber;Digital",functions="Chronograph, Day, Date, Hour, Minute, Second, Alarm, Backlight",gift_wrapping_price="0.000000",savings="37.50",shipperhq_shipping_group="UNDER_1LBS"|"NOT_ON_SHELF",shipping="$5.99 ",url_path="adidas-watch-adh2728",warranty="2 Year Jomashop Warranty",watchsize="40-45 mm",watch_color="Blue",water_resistant="50 meters / 165 feet"
export const processAdditionalAttributes = (additionalAttributes: string): { [key: string]: string } =>
    additionalAttributes.split('",').reduce((acc, pair) => {
        const [key, rawValue] = pair.split('="');
        return {
            [key]: (rawValue || '').replaceAll('"|"', '|'),
            ...acc,
        };
    }, {});

export const replaceOperationsExecutor = (mongoCollection, jobsRemover): OperationsExecutor => {
    return (replaceOperations: JobReplaceOperation[]): Promise<any> => {
        console.log(`Bulk Writing ${replaceOperations.length} items into mongo`);
        const timerName = 'bulk-write-' + replaceOperations[0].job.id;
        console.time(timerName);
        return mongoCollection
            .bulkWrite(_.pluck(replaceOperations, 'operation'), { ordered: false, writeConcern: { w: 0, j: 0 } })
            .then((result) => {
                console.timeEnd(timerName);
                jobsRemover(_.pluck(replaceOperations, 'job'));
                return result;
            });
    };
};

export const getMongoCollection = (mongoClient, database, collection) =>
    mongoClient.db(database).collection(collection);

export const closeMongoConnectionOnQueueFinish = (mongoClient) => (queue) =>
    queue.on('idle', () => mongoClient.close());
