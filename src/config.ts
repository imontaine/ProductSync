import { ConnectConfig } from 'ssh2';
import { dirname } from 'path';
import { fileURLToPath } from 'url';

// SFTP config
export const sftpConfig: ConnectConfig = {
    host: process.env.SFTP_HOST,
    port: parseInt(process.env.SFTP_PORT || '22'),
    username: process.env.SFTP_USERNAME,
    password: process.env.SFTP_PASSWORD,
};

// SQLite database configuration
export const dbPath: string =
    dirname(fileURLToPath(import.meta.url)) + '/../' + (process.env.SQLITE_DB_PATH || 'ProductSync.sqlite');

// Processing configuration
export const batchSizeCSV: number = parseInt(process.env.BATCH_SIZE_CSV || '10000');
export const batchSizeMongo: number = parseInt(process.env.BATCH_SIZE_MONGO || '1000');
export const concurrencyLimit: number = parseInt(process.env.BATCH_CONCURRENCY_LIMIT || '4');

// SFTP export path
export const remoteDir: string = process.env.SFTP_EXPORT_PATH || '/exports/pending';

// Move the CSV file to a separate folder
export const processedDir: string = process.env.SFTP_COMPLETED_PATH || '/exports/completed';

// Local download path
export const localDownloadDir: string = process.env.LOCAL_DOWNLOAD_PATH || 'downloads';

// Mongo config
export const mongoUri = process.env.MONGO_URI || '';
export const mongoDatabaseName = process.env.MONGO_DB || '';
export const mongoCollectionName = process.env.MONGO_COLLECTION || '';

// Heartbeat url
export const heartbeatUrl: string = process.env.WEBHOOK_URL || '';
