
# ProductSync
This repository contains code for ProductSync. To run this code, you need to set the following environment variables.

```
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
```

Please create a .env file with the above variables and place it in a folder named /config outside the project directory. The folder structure should be as follows:

```
config/
│   └── .env
project/
│   └── main.php
│   └── composer.json
```
