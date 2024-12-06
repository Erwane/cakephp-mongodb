<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Error\Debug\TextFormatter;
use Cake\Log\Log;
use Cake\Utility\Security;
use CakeMongo\Database\Driver\Mongodb;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', dirname(__DIR__));

define('TMP', sys_get_temp_dir() . DS);
const LOGS = TMP . 'logs' . DS;
const CACHE = TMP . 'cache' . DS;

const CAKE_CORE_INCLUDE_PATH = ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp';
const CORE_PATH = CAKE_CORE_INCLUDE_PATH . DS;
const CAKE = CORE_PATH . 'src' . DS;

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
ini_set('intl.default_locale', 'en_US');
ini_set('session.gc_divisor', '1');
ini_set('assert.exception', '1');

Configure::write('debug', true);
Configure::write('Debugger.exportFormatter', TextFormatter::class);

Log::reset();
Security::setSalt('a-long-but-not-random-value');

// Ensure default test connection is defined
if (!getenv('DB_URL')) {
    putenv('DB_URL=mongodb://localhost/tests');
}
ConnectionManager::setDsnClassMap(['mongodb' => Mongodb::class]);
ConnectionManager::setConfig('test', ['url' => getenv('DB_URL')]);
