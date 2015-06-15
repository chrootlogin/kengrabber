<?php
/**
 * Copyright 2015 Simon Erhardt <me@rootlogin.ch>
 *
 * This file is part of kengrabber.
 * kengrabber is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * kengrabber is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with kengrabber.
 * If not, see http://www.gnu.org/licenses/.
 */

namespace rootLogin\Kengrabber;

use Cilex\Application;
use Cilex\Provider\ConfigServiceProvider;
use Cilex\Provider\DoctrineServiceProvider;
use Cilex\Provider\MonologServiceProvider;
use Cilex\Provider\TwigServiceProvider;
use rootLogin\Kengrabber\Command\BuildCommand;
use rootLogin\Kengrabber\Command\CleanUpCommand;
use rootLogin\Kengrabber\Command\ConfigureCommand;
use rootLogin\Kengrabber\Command\DownloadVideoListCommand;
use rootLogin\Kengrabber\Command\GrabVideoListCommand;
use rootLogin\Kengrabber\Command\RenderCommand;
use rootLogin\Kengrabber\Command\VerifyCommand;
use rootLogin\Kengrabber\Logger\MonologSQLLogger;
use rootLogin\Kengrabber\Provider\WrapperProvider;

class Kengrabber extends Application {

    const VERSION = "0.5";

    public function __construct($values = array())
    {
        parent::__construct("Kengrabber", self::VERSION, $values);

        $this->initialize();
    }

    private function initialize()
    {
        $this->initProviders();
        $this->loadConfig();
        $this->initAppDir();
        $this->initWebDir();
        $this->initDatabase();
        $this->loadCommands();
    }

    private function loadConfig()
    {
        $configPath = $this['app_root'] . "/config.yml";

        if(!file_exists($configPath))
        {
            // If config not existing create it
            copy($this['root'] . "/app/dist/config.yml", $configPath);
        }

        $this->register(new ConfigServiceProvider(), array('config.path' => $configPath));
    }

    private function initAppDir()
    {
        $this['app_dir'] = $this['app_root'] . "/data";
        $path = realpath($this['app_dir']);

        if($path === false || !is_dir($path)) {
            mkdir($this['app_dir'], 0777);
            mkdir($this['app_dir'] . "/ytdl_cache", 0777);
        }
    }

    private function initWebDir()
    {
        $this['web_dir'] = $this['app_root'] . "/web";
        $path = realpath($this['web_dir']);

        if($path === false || !is_dir($path)) {
            mkdir($this['web_dir'], 0777);
            mkdir($this['web_dir'] . "/media", 0777);
        }

    }

    private function loadCommands()
    {
        $this->command(new GrabVideoListCommand());
        $this->command(new DownloadVideoListCommand());
        $this->command(new CleanUpCommand());
        $this->command(new RenderCommand());
        $this->command(new VerifyCommand());
        $this->command(new BuildCommand());
        $this->command(new ConfigureCommand());
    }

    private function initDatabase()
    {
        $dbPath = $this['app_dir'] . '/data.sqlite';

        $this->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver'   => 'pdo_sqlite',
                'path'     => $dbPath,
            )
        ));

        if($this['debug']) {
            $this['db.config']->setSQLLogger(new MonologSQLLogger($this['monolog']));
        }

        if(!file_exists($dbPath)) {
            touch($dbPath);

            /** @var \Doctrine\DBAL\Connection $db */
            $db = $this['db'];

            $rows = $db->exec(file_get_contents($this['root'] . '/app/res/createdb.sql'));
            if($rows <= 0) {
                die("Can't create sql db!" . PHP_EOL);
            }
        }

        $this->register(new WrapperProvider());
    }

    protected function initProviders()
    {
        $this->register(new MonologServiceProvider(), array(
            'monolog.logfile' => $this['app_root'] . '/kengrabber.log',
        ));
    }
}