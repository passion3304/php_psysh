<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;
use Psy\Configuration;
use Psy\ExecutionLoop\ProcessForker;
use Psy\Output\PassthruPager;
use Psy\Output\ShellOutput;
use Psy\VersionUpdater\GitHubChecker;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigurationTest extends \PHPUnit\Framework\TestCase
{
    private function getConfig($configFile = null)
    {
        return new Configuration([
            'configFile' => $configFile ?: __DIR__ . '/fixtures/empty.php',
        ]);
    }

    public function testDefaults()
    {
        $config = $this->getConfig();

        $this->assertSame(\function_exists('readline'), $config->hasReadline());
        $this->assertSame(\function_exists('readline'), $config->useReadline());
        $this->assertSame(ProcessForker::isSupported(), $config->hasPcntl());
        $this->assertSame($config->hasPcntl(), $config->usePcntl());
        $this->assertFalse($config->requireSemicolons());
        $this->assertSame(Configuration::COLOR_MODE_AUTO, $config->colorMode());
        $this->assertNull($config->getStartupMessage());
    }

    public function testGettersAndSetters()
    {
        $config = $this->getConfig();

        $this->assertNull($config->getDataDir());
        $config->setDataDir('wheee');
        $this->assertSame('wheee', $config->getDataDir());

        $this->assertNull($config->getConfigDir());
        $config->setConfigDir('wheee');
        $this->assertSame('wheee', $config->getConfigDir());
    }

    /**
     * @dataProvider directories
     */
    public function testFilesAndDirectories($home, $configFile, $historyFile, $manualDbFile)
    {
        $oldHome = \getenv('HOME');
        \putenv("HOME=$home");

        $config = new Configuration();
        $this->assertSame(\realpath($configFile),   \realpath($config->getConfigFile()));
        $this->assertSame(\realpath($historyFile),  \realpath($config->getHistoryFile()));
        $this->assertSame(\realpath($manualDbFile), \realpath($config->getManualDbFile()));

        \putenv("HOME=$oldHome");
    }

    public function directories()
    {
        $base = \realpath(__DIR__ . '/fixtures');

        return [
            [
                $base . '/default',
                $base . '/default/.config/psysh/config.php',
                $base . '/default/.config/psysh/psysh_history',
                $base . '/default/.local/share/psysh/php_manual.sqlite',
            ],
            [
                $base . '/legacy',
                $base . '/legacy/.psysh/rc.php',
                $base . '/legacy/.psysh/history',
                $base . '/legacy/.psysh/php_manual.sqlite',
            ],
            [
                $base . '/mixed',
                $base . '/mixed/.psysh/config.php',
                $base . '/mixed/.psysh/psysh_history',
                null,
            ],
        ];
    }

    public function testLoadConfig()
    {
        $config  = $this->getConfig();
        $cleaner = new CodeCleaner();
        $pager   = new PassthruPager(new ConsoleOutput());

        $config->loadConfig([
            'useReadline'       => false,
            'usePcntl'          => false,
            'codeCleaner'       => $cleaner,
            'pager'             => $pager,
            'requireSemicolons' => true,
            'errorLoggingLevel' => E_ERROR | E_WARNING,
            'colorMode'         => Configuration::COLOR_MODE_FORCED,
            'startupMessage'    => 'Psysh is awesome!',
        ]);

        $this->assertFalse($config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame($cleaner, $config->getCodeCleaner());
        $this->assertSame($pager, $config->getPager());
        $this->assertTrue($config->requireSemicolons());
        $this->assertSame(E_ERROR | E_WARNING, $config->errorLoggingLevel());
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $config->colorMode());
        $this->assertSame('Psysh is awesome!', $config->getStartupMessage());
    }

    public function testLoadConfigFile()
    {
        $config = $this->getConfig(__DIR__ . '/fixtures/config.php');

        $runtimeDir = $this->joinPath(\realpath(\sys_get_temp_dir()), 'psysh_test', 'withconfig', 'temp');

        $this->assertStringStartsWith($runtimeDir, \realpath($config->getTempFile('foo', 123)));
        $this->assertStringStartsWith($runtimeDir, \realpath(\dirname($config->getPipe('pipe', 123))));
        $this->assertStringStartsWith($runtimeDir, \realpath($config->getRuntimeDir()));

        $this->assertSame(\function_exists('readline'), $config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame(E_ALL & ~E_NOTICE, $config->errorLoggingLevel());
    }

    public function testLoadLocalConfigFile()
    {
        $oldPwd = \getcwd();
        \chdir(\realpath(__DIR__ . '/fixtures/project/'));

        $config = new Configuration();

        // When no configuration file is specified local project config is merged
        $this->assertTrue($config->requireSemicolons());
        $this->assertFalse($config->useUnicode());

        $config = new Configuration(['configFile' => __DIR__ . '/fixtures/config.php']);

        // Defining a configuration file skips loading local project config
        $this->assertFalse($config->requireSemicolons());
        $this->assertTrue($config->useUnicode());

        \chdir($oldPwd);
    }

    /**
     * @expectedException \Psy\Exception\DeprecatedException
     */
    public function testBaseDirConfigIsDeprecated()
    {
        $config = new Configuration(['baseDir' => 'fake']);
    }

    private function joinPath()
    {
        return \implode(DIRECTORY_SEPARATOR, \func_get_args());
    }

    public function testConfigIncludes()
    {
        $config = new Configuration([
            'defaultIncludes' => ['/file.php'],
            'configFile'      => __DIR__ . '/fixtures/empty.php',
        ]);

        $includes = $config->getDefaultIncludes();
        $this->assertCount(1, $includes);
        $this->assertSame('/file.php', $includes[0]);
    }

    public function testGetOutput()
    {
        $config = $this->getConfig();
        $output = $config->getOutput();

        $this->assertInstanceOf(ShellOutput::class, $output);
    }

    public function getOutputDecoratedProvider()
    {
        return [
            'auto' => [
                null,
                Configuration::COLOR_MODE_AUTO,
            ],
            'forced' => [
                true,
                Configuration::COLOR_MODE_FORCED,
            ],
            'disabled' => [
                false,
                Configuration::COLOR_MODE_DISABLED,
            ],
        ];
    }

    /** @dataProvider getOutputDecoratedProvider */
    public function testGetOutputDecorated($expectation, $colorMode)
    {
        if ($colorMode === Configuration::COLOR_MODE_AUTO) {
            $this->markTestSkipped('This test won\'t work on CI without overriding pipe detection');
        }

        $config = $this->getConfig();
        $config->setColorMode($colorMode);

        $this->assertSame($expectation, $config->getOutputDecorated());
    }

    public function setColorModeValidProvider()
    {
        return [
            'auto'     => [Configuration::COLOR_MODE_AUTO],
            'forced'   => [Configuration::COLOR_MODE_FORCED],
            'disabled' => [Configuration::COLOR_MODE_DISABLED],
        ];
    }

    /** @dataProvider setColorModeValidProvider */
    public function testSetColorModeValid($colorMode)
    {
        $config = $this->getConfig();
        $config->setColorMode($colorMode);

        $this->assertSame($colorMode, $config->colorMode());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid color mode: some invalid mode
     */
    public function testSetColorModeInvalid()
    {
        $config = $this->getConfig();
        $config->setColorMode('some invalid mode');
    }

    public function getOutputVerbosityProvider()
    {
        return [
            'quiet'        => [OutputInterface::VERBOSITY_QUIET, Configuration::VERBOSITY_QUIET],
            'normal'       => [OutputInterface::VERBOSITY_NORMAL, Configuration::VERBOSITY_NORMAL],
            'verbose'      => [OutputInterface::VERBOSITY_VERBOSE, Configuration::VERBOSITY_VERBOSE],
            'very_verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, Configuration::VERBOSITY_VERY_VERBOSE],
            'debug'        => [OutputInterface::VERBOSITY_DEBUG, Configuration::VERBOSITY_DEBUG],
        ];
    }

    /** @dataProvider getOutputVerbosityProvider */
    public function testGetOutputVerbosity($expectation, $verbosity)
    {
        $config = $this->getConfig();
        $config->setVerbosity($verbosity);

        $this->assertSame($expectation, $config->getOutputVerbosity());
    }

    public function setVerbosityValidProvider()
    {
        return [
            'quiet'        => [Configuration::VERBOSITY_QUIET],
            'normal'       => [Configuration::VERBOSITY_NORMAL],
            'verbose'      => [Configuration::VERBOSITY_VERBOSE],
            'very_verbose' => [Configuration::VERBOSITY_VERY_VERBOSE],
            'debug'        => [Configuration::VERBOSITY_DEBUG],
        ];
    }

    /** @dataProvider setVerbosityValidProvider */
    public function testSetVerbosityValid($verbosity)
    {
        $config = $this->getConfig();
        $config->setVerbosity($verbosity);

        $this->assertSame($verbosity, $config->verbosity());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid verbosity level: some invalid verbosity
     */
    public function testSetVerbosityInvalid()
    {
        $config = $this->getConfig();
        $config->setVerbosity('some invalid verbosity');
    }

    public function getInputInteractiveProvider()
    {
        return [
            'auto' => [
                null,
                Configuration::INTERACTIVE_MODE_AUTO,
            ],
            'forced' => [
                true,
                Configuration::INTERACTIVE_MODE_FORCED,
            ],
            'disabled' => [
                false,
                Configuration::INTERACTIVE_MODE_DISABLED,
            ],
        ];
    }

    /** @dataProvider getInputInteractiveProvider */
    public function testGetInputInteractive($expectation, $interactive)
    {
        if ($interactive === Configuration::INTERACTIVE_MODE_AUTO) {
            $this->markTestSkipped('This test won\'t work on CI without overriding pipe detection');
        }

        $config = $this->getConfig();
        $config->setInteractiveMode($interactive);

        $this->assertSame($expectation, $config->getInputInteractive());
    }

    public function setInteractiveModeValidProvider()
    {
        return [
            'auto'     => [Configuration::INTERACTIVE_MODE_AUTO],
            'forced'   => [Configuration::INTERACTIVE_MODE_FORCED],
            'disabled' => [Configuration::INTERACTIVE_MODE_DISABLED],
        ];
    }

    /** @dataProvider setInteractiveModeValidProvider */
    public function testsetInteractiveModeValid($interactive)
    {
        $config = $this->getConfig();
        $config->setInteractiveMode($interactive);

        $this->assertSame($interactive, $config->interactiveMode());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid interactive mode: nope
     */
    public function testsetInteractiveModeInvalid()
    {
        $config = $this->getConfig();
        $config->setInteractiveMode('nope');
    }

    public function testSetCheckerValid()
    {
        $config  = $this->getConfig();
        $checker = new GitHubChecker();

        $config->setChecker($checker);

        $this->assertSame($checker, $config->getChecker());
    }

    public function testSetFormatterStyles()
    {
        $config = $this->getConfig();
        $config->setFormatterStyles([
            'mario' => ['white', 'red'],
            'luigi' => ['white', 'green'],
        ]);

        $formatter = $config->getOutput()->getFormatter();

        $this->assertTrue($formatter->hasStyle('mario'));
        $this->assertTrue($formatter->hasStyle('luigi'));

        $mario = $formatter->getStyle('mario');
        $this->assertEquals("\e[37;41mwheee\e[39;49m", $mario->apply('wheee'));

        $luigi = $formatter->getStyle('luigi');
        $this->assertEquals("\e[37;42mwheee\e[39;49m", $luigi->apply('wheee'));
    }

    public function testSetFormatterStylesRuntimeUpdates()
    {
        $config = $this->getConfig();
        $formatter = $config->getOutput()->getFormatter();

        $this->assertFalse($formatter->hasStyle('mario'));
        $this->assertFalse($formatter->hasStyle('luigi'));

        $config->setFormatterStyles([
            'mario' => ['white', 'red'],
            'luigi' => ['white', 'green'],
        ]);

        $this->assertTrue($formatter->hasStyle('mario'));
        $this->assertTrue($formatter->hasStyle('luigi'));

        $mario = $formatter->getStyle('mario');
        $this->assertEquals("\e[37;41mwheee\e[39;49m", $mario->apply('wheee'));

        $luigi = $formatter->getStyle('luigi');
        $this->assertEquals("\e[37;42mwheee\e[39;49m", $luigi->apply('wheee'));

        $config->setFormatterStyles([
            'mario' => ['red', 'white'],
            'luigi' => ['green', 'white'],
        ]);

        $mario = $formatter->getStyle('mario');
        $this->assertEquals("\e[31;47mwheee\e[39;49m", $mario->apply('wheee'));

        $luigi = $formatter->getStyle('luigi');
        $this->assertEquals("\e[32;47mwheee\e[39;49m", $luigi->apply('wheee'));
    }

    /**
     * @dataProvider invalidStyles
     */
    public function testSetFormatterStylesInvalid($styles, $msg)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage($msg);
        } else {
            $this->setExpectedException(\InvalidArgumentException::class, $msg);
        }

        $config = $this->getConfig();
        $config->setFormatterStyles($styles);
    }

    public function invalidStyles()
    {
        return [
            [
                ['error' => ['burgundy', null, ['bold']]],
                'Invalid foreground color specified: "burgundy". Expected one of',
            ],
            [
                ['error' => ['red', 'ink', ['bold']]],
                'Invalid background color specified: "ink". Expected one of',
            ],
            [
                ['error' => ['black', 'red', ['marquee']]],
                'Invalid option specified: "marquee". Expected one of',
            ],
        ];
    }
}
