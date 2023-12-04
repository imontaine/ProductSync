import fs from 'fs';
import Client, { ConnectOptions } from 'ssh2-sftp-client';
import sftp from 'ssh2-sftp-client';
import { decorate } from './debug.ts';
import { localDownloadDir, remoteDir } from './config';

export function connect(sftpConfig: ConnectOptions): Promise<sftp> {
    const sftp = new Client();
    return sftp.connect(sftpConfig).then(() => sftp);
}

export const connectWithLogs = decorate(
    connect,
    ({ password, ...rest }) => console.log('Attempting to connect to the sft host', rest),
    () => console.log('Successfully Connected'),
);

export type RemoteDirectoryList = {
    sftp: sftp;
    remoteDirName: string;
    files: string[];
};

export type RemoteDirectoryLister = (sftp: sftp) => Promise<RemoteDirectoryList>;

export function listDirectory(remoteDirName: string): RemoteDirectoryLister {
    return (sftp: sftp) =>
        sftp.list(remoteDirName).then(
            (files) =>
                ({
                    sftp,
                    remoteDirName,
                    files: files.map((file) => file.name),
                }) as RemoteDirectoryList,
        );
}

export function listDirectoryWithLogs(remoteDirName: string): RemoteDirectoryLister {
    const dirLister = listDirectory(remoteDirName);
    return (sftp: sftp) => {
        console.log(`Listing remote ${remoteDirName} directory`);
        return dirLister(sftp).then((remoteDirList) => {
            console.log(`Found ${remoteDirList.files.length} files in the remote directory`);
            return remoteDirList;
        });
    };
}

export type SelectedRemoteFile = {
    sftp: sftp;
    remoteDirName: string;
    selectedFileName: string;
};

export type RemoteFileSelector = (remoteDirList: RemoteDirectoryList) => SelectedRemoteFile;

export const selectFileFromRemoteDir = (selector: selector): RemoteFileSelector => {
    return ({ files, sftp, remoteDirName }) => {
        const selectedFileName = selector(files);
        if (!selectedFileName) {
            throw new Error('Could not find remote file');
        }
        return { sftp, remoteDirName, selectedFileName } as SelectedRemoteFile;
    };
};

export function selectFileFromRemoteDirWithLogs(selector: selector): RemoteFileSelector {
    const fileSelector = selectFileFromRemoteDir(selector);
    return (remoteDirList) => {
        const selectedFile = fileSelector(remoteDirList);
        console.log(`File ${selectedFile.selectedFileName} selected`);
        return selectedFile;
    };
}

export type selector = (files: string[]) => string | null;

export const oldestFileSelector: selector = (files) =>
    files
        .filter((file) => file.includes('.csv'))
        .sort((a, b) => {
            return (parseInt(a.replace(/([^0-9]*)/g, '')) || 0) - (parseInt(b.replace(/([^0-9]*)/g, '')) || 0);
        })[0] || null;

export type DownloadedRemoteFile = {
    sftp: sftp;
    localDirName: string;
    fileName: string;
    localFilePath: string;
    remoteDirName: string;
};

export type RemoteFileDownloader = (selectedRemoteFile: SelectedRemoteFile) => Promise<DownloadedRemoteFile>;

export const downloadRemoteFile = (localDownloadDir: string): RemoteFileDownloader => {
    return async (selectedRemoteFile) => {
        console.log('Downloading remote file');
        const { sftp, remoteDirName, selectedFileName: remoteFileName } = selectedRemoteFile;
        // Check if the file is already downloaded
        const localFilePath = `${localDownloadDir}/${remoteFileName}`;
        if (fs.existsSync(localFilePath)) {
            throw new Error(
                `Can't download the file ${remoteFileName} as it already exists, likely being processed by another instance.`,
            );
        }
        //Downloads the file
        await sftp.get(`${remoteDirName}/${remoteFileName}`, localFilePath);

        console.log('Download finished');

        const downloadedRemoteFile: DownloadedRemoteFile = {
            sftp,
            localDirName: localDownloadDir,
            fileName: remoteFileName,
            localFilePath,
            remoteDirName,
        };
        return downloadedRemoteFile;
    };
};

export const closeDownloadedFileSFTPConnection = ({ sftp }: DownloadedRemoteFile) => sftp.end();
