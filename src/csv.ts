import fs from 'fs';
import csv from 'csv-parser';
import { rowDataTransformer } from './jobsDB.js';
import { DownloadedRemoteFile } from './sftp';
import PQueue from "p-queue";

type BatchState = {
    batches: any[];
};

// export const rowBatchProcessor = (
//     rowDataTransformer,
//     batchSize: number,
//     batchState: BatchState,
//     batchCallback: (any) => any,
// ) => {
//     return (row) => {
//         batchState.rows = [...batchState.rows, rowDataTransformer(row)];
//
//         if (batchState.rows.length === batchSize) {
//             batchCallback(batchState.rows);
//             batchState.rows = [];
//         }
//         return batchState;
//     };
// };

export type DownloadedFilesBatchProcessor = (
    downloadedRemoteFile: DownloadedRemoteFile,
) => Promise<DownloadedRemoteFile>;

export const processDownloadedFileRowsInBatches = (
    batchSize: number,
    batchCallback: (any) => any,
): DownloadedFilesBatchProcessor => {
    const batchState: BatchState = {batches: [[]] };
    const queue = new PQueue({concurrency: 1});
    return (downloadedRemoteFile) => {
        const { fileName, localFilePath } = downloadedRemoteFile;
        return new Promise((resolve, reject) =>
            fs
                .createReadStream(localFilePath)
                .pipe(csv())
                .on('data', (row) => {
                    const queueNum = batchState.batches.length;
                    const rows = batchState.batches[queueNum-1];
                    rows.push(rowDataTransformer(fileName, 'READY', new Date().toISOString())(row));
                    if (rows.length == batchSize) {
                        console.log(`Adding ${rows.length} rows to queue ${queueNum}`);
                        queue.add(() => {
                            console.log(`Preparing to process ${rows.length} rows from queue ${queueNum}`);
                            const startDate = new Date();
                            return batchCallback(rows).then(() => {
                                const endDate   = new Date();
                                const seconds = (endDate.getTime() - startDate.getTime()) / 1000;
                                console.log(`Finished queue ${queueNum} in ${seconds} seconds`);
                            })
                        })
                        batchState.batches.push([])
                    }
                })
                .on('end', () => {
                    // Insert any remaining rows
                    const queueNum = batchState.batches.length;
                    const rows = batchState.batches[batchState.batches.length-1];
                    if (rows.length > 0) {
                        queue.add(() => {
                            console.log(`Preparing to insert ${rows.length} rows in the jobs table`);
                            const startDate = new Date();
                            return batchCallback(rows).then(() => {
                                const endDate   = new Date();
                                const seconds = (endDate.getTime() - startDate.getTime()) / 1000;
                                console.log(`Finished queue ${queueNum} in ${seconds} seconds`);
                            })
                        });
                    }
                    resolve(downloadedRemoteFile);
                })
                .on('error', reject),
        );
    };
};

export const moveRemoteFileTo = (toDir: string) => {
    return async (downloadedRemoteFile: DownloadedRemoteFile) => {
        const { fileName, remoteDirName, sftp } = downloadedRemoteFile;
        await sftp.rename(`${remoteDirName}/${fileName}`, `${toDir}/${fileName}`);
        return downloadedRemoteFile;
    };
};

export const deleteLocalDownloadedFile = async (downloadedRemoteFile): Promise<DownloadedRemoteFile> => {
    const { localFilePath } = downloadedRemoteFile;
    await fs.unlinkSync(`${localFilePath}`);
    return downloadedRemoteFile;
};
