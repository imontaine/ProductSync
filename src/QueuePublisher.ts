import 'dotenv/config';
import { sftpConfig, dbPath, batchSizeCSV, remoteDir, processedDir, localDownloadDir } from './config.ts';
import {
    connectWithLogs as connect,
    listDirectoryWithLogs as listDirectory,
    selectFileFromRemoteDirWithLogs as selectFileFromRemoteDir,
    downloadRemoteFile,
    oldestFileSelector,
    closeDownloadedFileSFTPConnection,
} from './sftp.ts';
import { processDownloadedFileRowsInBatches, moveRemoteFileTo, deleteLocalDownloadedFile } from './csv.ts';
import { batchCallback, closeDbConnection, getJobsDB } from './jobsDB.js';
import sftp from "ssh2-sftp-client";

const jobsDB = getJobsDB(dbPath);
let sftpConnection: sftp | null = null;

connect(sftpConfig)
    .then((sftp) => {
        sftpConnection = sftp;
        return sftp;
    })
    .then(listDirectory(remoteDir))
    .then(selectFileFromRemoteDir(oldestFileSelector))
    .then(downloadRemoteFile(localDownloadDir))
    .then(processDownloadedFileRowsInBatches(batchSizeCSV, batchCallback(jobsDB)))
    .then(moveRemoteFileTo(processedDir))
    .then(deleteLocalDownloadedFile)
    .catch((err) => {
        console.error('Error:', err.message);
    })
    .finally(() => {
        closeDbConnection(jobsDB)
        sftpConnection && sftpConnection.end();
    });
