<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Version;
use Sunlight\Core;
use Sunlight\Database\Database;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Doctrine\ORM\Tools\Console\ConsoleRunner as DoctrineConsole;
use Composer\Script\Event;

abstract class Console
{
    /**
     * @param EntityManager $em
     * @return Application
     */
    static function createApplication(EntityManager $em)
    {
        $cli = new Application('Doctrine Command Line Interface', sprintf('%s (SunLight CMS %s)', Version::VERSION, Core::VERSION));

        $cli->setCatchExceptions(true);
        $cli->setHelperSet(DoctrineConsole::createHelperSet($em));
        DoctrineConsole::addCommands($cli);

        return $cli;
    }

    static function runAsComposerScript(Event $e)
    {
        require __DIR__ . '/../../../bootstrap.php';
        Core::init('./', array('content_type' => false, 'session_enabled' => false));

        $argv = array_merge(array(__FILE__), $e->getArguments());

        $cli = static::createApplication(Database::getEntityManager());

        $cli->run(new ArgvInput($argv));
    }
}
