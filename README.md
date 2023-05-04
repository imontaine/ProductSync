# ProductSync

Code is set to use environment variables, create a .env file with the following:

# SFTP credentials
SFTP_HOST=example.com
SFTP_PORT=22
SFTP_USERNAME=user
SFTP_PASSWORD=password
SFTP_EXPORT_PATH=/exports
SFTP_COMPLETED_PATH=/completed

# MongoDB credentials
MONGO_HOST=mongodb://localhost:27017
MONGO_USERNAME=user
MONGO_PASSWORD=password
MONGO_DB=mydatabase
MONGO_COLLECTION=mycollection

# Webhook URL
WEBHOOK_URL=https://example.com/webhook



The env file is set to be placed outside the directory in a folder named /config

config/
│   └── .env
project/
│   └── main.php
    └── composer.json
