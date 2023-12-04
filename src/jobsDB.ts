import sqlite3 from 'sqlite3';
import _ from 'underscore';

export type Job = {
    id: number;
    csv_file_name: string;
    row_content: string;
    created_at: string;
    status: string;
};

export const rowDataTransformer = (localFileName, status, date) => (row) => [
    localFileName,
    JSON.stringify(row),
    date,
    status,
];

export const insertBatch = (db, rows) =>
    new Promise((resolve, reject) => {
        const stmt = db.prepare(
            `INSERT INTO jobs (csv_file_name, row_content, created_at, status) VALUES (?, ?, ?, ?)`,
        );

        rows.map((row) => {
            stmt.run(row);
        });

        stmt.finalize(() => resolve(true), (err) => reject(err));
    })

export const batchCallback = (db) => (rows) => insertBatch(db, rows);

export const closeDbConnection = (db) => db.close();

export const getJobsDB = (dbPath) => new sqlite3.Database(dbPath);

export const getReadyJobs = (limit, db): Promise<Job[]> =>
    new Promise((resolve, reject) =>
        db.all(`SELECT * FROM jobs WHERE status = 'READY' ORDER BY id ASC LIMIT ${limit}`, (err, rows) =>
            err ? reject(err) : resolve(rows),
        ),
    );

export const jobsRemover = (db) => (jobs: Job[]) =>
    new Promise((resolve, reject) =>
        db.all('DELETE FROM jobs WHERE id IN (' + _.pluck(jobs, 'id').join(',') + ')', (err, result) =>
            err ? reject(err) : resolve(result),
        ),
    );
