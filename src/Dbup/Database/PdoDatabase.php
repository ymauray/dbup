<?php

namespace Dbup\Database;

use Dbup\Exception\RuntimeException;
use PDO;

class PdoDatabase
{
    private \PDO|null $connection = null ;
    private string $dsn;
    private string|null $user;
    private string|null $password;
    private array|null $driverOptions;
    public function __construct(string $dsn, string|null $user, string|null $password, array|null $driverOptions = [])
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->driverOptions = $driverOptions;
    }

    public function connection($new = false): \PDO
    {
        if (null === $this->connection || true === $new) {
            try {
                $this->connection = new PDO($this->dsn, $this->user, $this->password, $this->driverOptions);
                $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
            } catch (\PDOException $e) {
                throw new RuntimeException($e->getMessage());
            }
        }
        return $this->connection;
    }
}
