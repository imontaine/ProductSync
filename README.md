# ProductSync
Reads CSV files from an SFTP server and imports them into a DB. 

## Requirements
The ProductSync needs an SFTP server with two folders to hold the processed/unprocessed CSVs. The folder paths are set on the env file (see *Config* section).  
The CSV files names must begin with a date format of `Y-m-d_H-i-s`.  
A MongoDB instance, credentials are set on the env file (see *Config* section).

## Config
You can find relevant env vars at `.env.example`. Copy it as `.env` and fill the vars.

- **SFTP_EXPORT_PATH** Remote FTP folder that contains CSV files to be processed 
- **SFTP_COMPLETED_PATH** Remote FTP folder where processed files should be moved to
- **CSV_SPLIT_LIMIT** Limit of rows the script process on each run (see *CSV split* section)
- **BATCH_SIZE** How many rows should be inserted at once on the DB
- **BATCH_CONCURRENT_LIMIT** How many concurrent batches should be run in parallel

The other vars are self-explanatory.

# Running
The main entry point is `main.php`, it doesn't take any arguments.  
On run, it processes the oldest file available on the SFTP server.

## Deployment
Run `dep deploy` to deploy the code.  
Other useful commands are:

- `dep releases` to list last relases
- `dep rollback` to rollback a deployment
- `dep ssh` to ssh into the ProductSync server

Run `dep --help` to see other commands.

## CSV Split
When the number of rows of the CSV reaches the `CSV_SPLIT_LIMIT`, the program stops processing more rows
and saves the remaining rows into the FTP server's `SFTP_EXPORT_PATH`,
it adds one second to the date and appends it with `-SPLIT`.    
This is done so the split file is the next in line.
