import PQueue from 'p-queue';
import { JobOperation } from './mongo';
import _ from 'underscore';

export type OperationsExecutor = (arg0: JobOperation[]) => Promise<any>;
export type QueuedOperationsExecutor = (
    batchSize: number,
    operationsExecutor: OperationsExecutor,
    queue: PQueue,
) => (arg0: JobOperation[]) => PQueue;

export const createQueue = (concurrencyLimit) => {
    return new PQueue({ concurrency: concurrencyLimit });
};

export const executeOperationsInQueue: QueuedOperationsExecutor =
    (batchSize, operationsExecutor, queue) => (jobOperations) => {
        _.chunk(jobOperations, batchSize).map((jobOperationsChunk: JobOperation[]) => {
            queue.add(() => operationsExecutor(jobOperationsChunk));
        });
        return queue;
    };
