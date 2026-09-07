<?php

namespace BoldMinded\Speedy\Service\Csrf;

use ExpressionEngine\Service\Database\Connection;

/**
 * A clone of EE's native class, but with no EE dependencies
 */
class SpeedyDatabase implements SpeedyCsrfStorageInterface
{
    const GC_PROBABILITY = 5;

    private Connection $connection;

    private SpeedyCookie $cookieCsrf;

    private string $databasePrefix;

    private string $sessionId;

    public function __construct(array $options = [], string $sessionId = '')
    {
        $appPath = $options['appPath'] ?? '';
        $basePath = $options['basePath'] ?? '';
        $configPath = $options['configPath'] ?? '';
        $systemPath = $options['systemPath'] ?? '';
        $this->databasePrefix = $options['databasePrefix'] ?? '';
        $this->sessionId = $sessionId;

        $this->cookieCsrf = new SpeedyCookie($options);

        if (!preg_match('/^[A-Za-z0-9]{'. SpeedyCsrf::TOKEN_LENGTH .'}$/', $sessionId)) {
            throw new \Exception('Session ID does not match expected format.');
        }

        // So we can directly include the config file to get the DB connection info without bootstrapping all of EE.
        if (!defined('BASEPATH')) {
            define('BASEPATH', $basePath);
        }

        try {
            if (file_exists($systemPath .'../.env.php')) {
                require $systemPath . 'ee/vendor-build/autoload.php';
                $dotenv = ExpressionEngine\Dependency\Dotenv\Dotenv::createImmutable($systemPath .'../', '.env.php');
                $dotenv->load();
            } else {
                // The old Focus Lab Master Config uses this. We're making the assumption that if you're using .env.php
                // then you're not referencing APPPATH in config files. https://boldminded.com/support/ticket/2851
                if (!defined('APPPATH')) {
                    define('APPPATH', $appPath);
                }
            }
        } catch (\Exception $e) {
        }

        include_once $systemPath . 'ee/ExpressionEngine/Service/Database/Connection.php';
        include $configPath;

        // Take defaults from EE core, merge the site's db config into it, and create a db connection.
        $dbConfig = array_merge([
            'port' => 3306,
            'hostname' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'database' => '',
            'dbdriver' => 'mysqli',
            'pconnect' => false,
            'dbprefix' => 'exp_',
            'swap_pre' => 'exp_',
            'db_debug' => true,
            'cache_on' => false,
            'autoinit' => false,
            'char_set' => 'utf8',
            'dbcollat' => 'utf8_unicode_ci',
            'cachedir' => rtrim($appPath, '/') . '/user/cache/db_cache/',
        ], $config['database']['expressionengine'] ?? []);

        $this->connection = new Connection($dbConfig);
        $this->connection->open();

        // Unset what we loaded, just in-case.
        unset($config);
    }

    /**
     * Get the expiration value
     *
     * @return int expiration in seconds
     */
    public function getExpiration()
    {
        return 0; // never - times out with session
    }

    /**
     * Set the token cookie
     *
     * @param string $token New token value
     * @return void
     */
    public function storeToken($token)
    {
        $query = sprintf(
            "insert into %ssecurity_hashes set `date` = '%s', `hash` = '%s', `session_id` = '%s'",
            $this->databasePrefix,
            time(),
            $this->connection->escape($token),
            $this->connection->escape($this->sessionId)
        );

        // Keep it in-sync
        $this->cookieCsrf->storeToken($token);

        $this->connection->query($query);
    }

    /**
     * Delete the token cookie
     *
     * @return void
     */
    public function deleteToken()
    {
        $query = sprintf(
            "delete from %ssecurity_hashes where `session_id` = '%s'",
            $this->databasePrefix,
            $this->connection->escape($this->sessionId)
        );

        $this->connection->query($query);

        $this->collectGarbage();
    }

    /**
     * Fetch the current session token from the cookie.
     *
     * @return string Stored token
     */
    public function fetchToken()
    {
        $query = sprintf(
            "select * from %ssecurity_hashes where `session_id` = '%s'",
            $this->databasePrefix,
            $this->connection->escape($this->sessionId)
        );

        /** @var PDOStatement $statement */
        $statement = $this->connection->query($query);
        $result = $statement->fetchObject();

        return empty($result) ? false : $result->hash;
    }

    /**
     * Refresh the current token
     * @return bool
     */
    public function refreshToken()
    {
        return true;
    }

    /**
     * Run garbage collection for old tokens on 5% of requests
     *
     * We no longer have anonymous data in this table, so the most that can
     * build up is users that logged in in the last 2 days that did not come
     * back or log out. Previously garbage collection only ran every 7 days
     * and the table was so big that it could take down a site.
     */
    private function collectGarbage()
    {
        srand(time());

        if ((rand() % 100) < self::GC_PROBABILITY) {
            // delete any csrf tokens whose associated session cannot be found in the session table.
            $query = sprintf(
                "delete sh from %ssecurity_hashes sh left join %ssessions s on s.session_id = sh.session_id where s.session_id is null",
                $this->databasePrefix,
                $this->databasePrefix
            );

            $this->connection->query($query);
        }
    }
}
