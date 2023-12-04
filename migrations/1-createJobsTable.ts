import { getJobsDB } from '../src/jobsDB';
import { dbPath } from '../src/config';

const db = getJobsDB(dbPath);

// Create table if not exists
db.run(`
  CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    csv_file_name TEXT,
    row_content TEXT,
    created_at DATETIME,
    status TEXT
  )
`);
