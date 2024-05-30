<?php

/*
 * This file is part of Dbup.
 *
 * (c) Masao Maeda <brt.river@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dbup;

use Dbup\Database\PdoDatabase;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Dbup\Exception\RuntimeException;

/**
 * @author Masao Maeda <brt.river@gmail.com>
 */
class Application extends BaseApplication
{
    const NAME = 'dbup';
    const VERSION = '0.6';
    /** sql file pattern */
    const PATTERN = '/^V(\d+?)__.*\.sql$/i';
    public PdoDatabase|null $pdo = null;
    public string $baseDir = '.';
    public string $sqlFilesDir;
    public string $appliedFilesDir;
    private static string $logo = <<<EOL
       _ _
     | | |
   __| | |__  _   _ _ __
  / _` | '_ \| | | | '_ \
 | (_| | |_) | |_| | |_) |
  \__,_|_.__/ \__,_| .__/
                   | |
                   |_|
 simple migration tool

EOL;

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        $this->sqlFilesDir =  $this->baseDir . '/sql';
        $this->appliedFilesDir =  $this->baseDir . '/.dbup/applied';
    }

    public function getIni(): string
    {
        return $this->baseDir . '/.dbup/properties.ini';
    }

    public function getFinder(): Finder
    {
        return new Finder();
    }

    public function createPdo(string $dsn, string|null $user, string|null $password, array|null $driverOptions): void
    {
        $this->pdo = new PdoDatabase($dsn, $user, $password, $driverOptions);
    }

    public function parseIniFile(string $path): array|false
    {
        $ini = file_get_contents($path);
        $replaced = preg_replace_callback('/%%(DBUP_[^%]+)%%/', function ($matches) {
            list($whole, $key) = $matches;
            return $_SERVER[$key] ?? $whole;
        }, $ini);

        return parse_ini_string($replaced, true);
    }

    public function setConfigFromIni(string $path): void
    {
        $parse = $this->parseIniFile($path);
        if (!isset($parse['pdo'])) {
            throw new RuntimeException('cannot find [pdo] section in your properties.ini');
        }
        $pdo = $parse['pdo'];
        $dsn = (isset($pdo['dsn'])) ? $pdo['dsn'] : '';
        $user = (isset($pdo['user'])) ? $pdo['user'] : '';
        $password = (isset($pdo['password'])) ? $pdo['password'] : '';
        $driverOptions = (isset($parse['pdo_options'])) ? $parse['pdo_options'] : [];

        if (isset($parse['path'])) {
            $path = $parse['path'];
            $this->sqlFilesDir = (isset($path['sql'])) ? $path['sql'] : $this->sqlFilesDir;
            $this->appliedFilesDir = (isset($path['applied'])) ? $path['applied'] : $this->appliedFilesDir;
        }

        $this->createPdo($dsn, $user, $password, $driverOptions);
    }

    public function getHelp(): string
    {
        return self::$logo . parent::getHelp();
    }

    /**
     * sort closure for Finder
     */
    public function sort(): \Closure
    {
        return function (\SplFileInfo $a, \SplFileInfo $b) {
            preg_match(self::PATTERN, $a->getFileName(), $version_a);
            preg_match(self::PATTERN, $b->getFileName(), $version_b);
            return ((int)$version_a[1] < (int)$version_b[1]) ? -1 : 1;
        };
    }

    /**
     * get sql files
     */
    public function getSqlFiles(): Finder
    {
        $sqlFinder = $this->getFinder();

        return $sqlFinder->files()
            ->in($this->sqlFilesDir)
            ->name(self::PATTERN)
            ->sort($this->sort())
        ;
    }

    /**
     * find sql file by the file name
     * @throws Exception\RuntimeException
     */
    public function getSqlFileByName(string $fileName)
    {
        $sqlFinder = $this->getFinder();

        $files = $sqlFinder->files()
            ->in($this->sqlFilesDir)
            ->name($fileName)
        ;

        if ($files->count() !== 1) {
            throw new RuntimeException('cannot find File:' . $fileName);
        }

        return $files[0];
    }

    /**
     * get applied files
     */
    public function getAppliedFiles(): Finder
    {
        $appliedFinder = $this->getFinder();

        return $appliedFinder->files()
            ->in($this->appliedFilesDir)
            ->name(self::PATTERN)
            ->sort($this->sort())
        ;
    }

    /**
     * get migration status
     * @return Status[] Statuses with applied datetime and file name
     */
    public function getStatuses(): array
    {
        $files = $this->getSqlFiles();
        $appliedFiles = $this->getAppliedFiles();

        /**
         * is file applied or not
         * @param $file
         * @return bool if applied, return true.
         */
        $isApplied = function ($file) use ($appliedFiles) {
            foreach ($appliedFiles as $appliedFile) {
                if ($appliedFile->getFileName() === $file->getFileName()) {
                    return true;
                }
            }
            return false;
        };

        $statuses = [];

        foreach ($files as $file) {
            $appliedAt = $isApplied($file) ? date('Y-m-d H:i:s', $file->getMTime()) : "";
            $statuses[] = new Status($appliedAt, $file);
        }

        return $statuses;
    }

    /**
     * get up candidates sql files
     * @return Status[]
     */
    public function getUpCandidates(): array
    {
        $statuses = $this->getStatuses();

        // search latest applied migration
        $latest = '';
        foreach ($statuses as $status) {
            if ($status->appliedAt !== "") {
                $latest = $status->file->getFileName();
            }
        }

        // make statuses without being applied
        $candidates = [];
        $isSkipped = !(($latest === ''));
        foreach ($statuses as $status) {
            if (false === $isSkipped) {
                $candidates[] = $status;
            }
            if ($status->file->getFileName() !== $latest) {
                continue;
            } else {
                $isSkipped = false;
            }
        }

        return $candidates;
    }

    /**
     * update database
     */
    public function up(SplFileInfo $file): void
    {
        $contents = file_get_contents($file->getPathName());
        if (false === $contents) {
            throw new RuntimeException($file->getPathName() . ' is not found.');
        }
        $queries = explode(';', $contents);

        /** @var \Pdo|null $dbh */
        $dbh = null;

        /** @var string|null $cleanedQuery */
        $cleanedQuery = null;

        try {
            $dbh = $this->pdo->connection(true);
            $dbh->beginTransaction();
            foreach ($queries as $query) {
                $cleanedQuery = trim($query);
                if ('' === $cleanedQuery) {
                    continue;
                }
                $stmt = $dbh->prepare($cleanedQuery);
                $stmt->execute();
            }
            if ($dbh->inTransaction()) {
                $dbh->commit();
            }
        } catch (\PDOException $e) {
            if ($dbh != null) {
                $dbh->rollBack();
            }
            throw new RuntimeException($e->getMessage() . PHP_EOL . (($cleanedQuery == null) ? "" : $cleanedQuery));
        }

        $this->copyToAppliedDir($file);
    }

    /**
     * copy applied sql file to the applied directory.
     */
    public function copyToAppliedDir(SplFileInfo $file): void
    {
        if (false === copy($file->getPathName(), $this->appliedFilesDir . '/' . $file->getFileName())) {
            throw new RuntimeException('cannot copy the sql file to applied directory. check the <info>' . $this->appliedFilesDir . '</info> directory.');
        }
    }
}
