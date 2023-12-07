<?php
namespace Deployer;

require 'recipe/composer.php';
require 'recipe/provision/nodejs.php';

// Config

set('repository', 'https://github.com/imontaine/ProductSync.git');

add('shared_files', []);
add('shared_dirs', ['downloads','db']);
add('writable_dirs', []);

task('npm:install', function(){
    run('cd {{deploy_path}}/current && npm install');
});

// Hosts

host('137.184.75.81')
    ->set('remote_user', 'root')
    ->set('deploy_path', '/var/www/ProductSync')
    ->set('shared_files', ['.env']);

// Hooks
after('deploy', 'npm:install');
after('deploy:failed', 'deploy:unlock');
