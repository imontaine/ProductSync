<?php
namespace Deployer;

require 'recipe/composer.php';

// Config

set('repository', 'https://github.com/imontaine/ProductSync.git');

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

host('137.184.75.81')
    ->set('remote_user', 'root')
    ->set('deploy_path', '/var/www/ProductSync')
    ->set('shared_files', ['.env']);

// Hooks

after('deploy:failed', 'deploy:unlock');
