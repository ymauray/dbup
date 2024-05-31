<?php

namespace Dbup\Util;

use Phar;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Finder\Finder;

class Compiler
{
    public function compile($pharFile = 'dbup.phar'): void
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $phar = new Phar($pharFile, 0, 'dbup.phar');
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $phar->startBuffering();
        // CLI Component files
        $output = new ConsoleOutput();
        $progress = new ProgressBar($output);
        $progress->start(count($this->getFiles()));

        foreach ($this->getFiles() as $file) {
            $phar->addFromString($file->getPathName(), file_get_contents($file));
            $progress->advance();
        }
        $progress->finish();

        $this->addDbup($phar);
        // Stubs
        $phar->setStub($this->getStub());
        $phar->stopBuffering();
        unset($phar);
        chmod($pharFile, 0777);
    }

    /**
     * Remove the shebang from the file before add it to the PHAR file.
     *
     * @param Phar $phar PHAR instance
     */
    protected function addDbup(\Phar $phar): void
    {
        $content = file_get_contents(__DIR__ . '/../../../dbup');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('dbup', $content);
    }

    protected function getStub(): string
    {
        return <<<EOL
#!/usr/bin/env php
<?php
Phar::mapPhar('dbup.phar');
require 'phar://dbup.phar/dbup';
__HALT_COMPILER();
EOL;
    }

    protected function getFiles(): array
    {
        $finder = new Finder();
        $srcIterator = $finder
            ->files()
            ->in([
                'vendor',
                'src'
            ])
            ->exclude([
                'phpunit',
                'phake',
                'hamcrest',
                'phpstan',
                'squizlabs',
                'phpmd'
            ])
            ->name('*.php');
        return iterator_to_array($srcIterator);
    }
}
