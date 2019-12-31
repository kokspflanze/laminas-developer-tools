<?php

/**
 * @see       https://github.com/laminas/laminas-developer-tools for the canonical source repository
 * @copyright https://github.com/laminas/laminas-developer-tools/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-developer-tools/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\DeveloperTools;

use BjyProfiler\Db\Adapter\ProfilingAdapter;
use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\BootstrapListenerInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\ModuleManager\Feature\InitProviderInterface;
use Laminas\ModuleManager\Feature\ServiceProviderInterface;
use Laminas\ModuleManager\Feature\ViewHelperProviderInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManagerInterface;

class Module implements
    InitProviderInterface,
    ConfigProviderInterface,
    ServiceProviderInterface,
    BootstrapListenerInterface,
    ViewHelperProviderInterface
{
    /**
     * Initialize workflow
     *
     * @param  ModuleManagerInterface $manager
     */
    public function init(ModuleManagerInterface $manager)
    {
        defined('REQUEST_MICROTIME') || define('REQUEST_MICROTIME', microtime(true));

        if (PHP_SAPI === 'cli') {
            return;
        }

        $eventManager = $manager->getEventManager();
        $eventManager->attach(
            ModuleEvent::EVENT_LOAD_MODULES_POST,
            [$this, 'onLoadModulesPost'],
            -1100
        );
    }

    /**
     * loadModulesPost callback
     *
     * @param  $event
     */
    public function onLoadModulesPost($event)
    {
        $eventManager  = $event->getTarget()->getEventManager();
        $configuration = $event->getConfigListener()->getMergedConfig(false);

        if (isset($configuration['laminas-developer-tools']['profiler']['enabled'])
            && $configuration['laminas-developer-tools']['profiler']['enabled'] === true
        ) {
            $eventManager->trigger(ProfilerEvent::EVENT_PROFILER_INIT, $event);
        }
    }

    /**
     * Laminas\Mvc\MvcEvent::EVENT_BOOTSTRAP event callback
     *
     * @param  EventInterface $event
     * @throws Exception\InvalidOptionException
     * @throws Exception\ProfilerException
     */
    public function onBootstrap(EventInterface $event)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $app = $event->getApplication();
        $em  = $app->getEventManager();
        $sem = $em->getSharedManager();
        $sm  = $app->getServiceManager();

        $options = $sm->get('Laminas\DeveloperTools\Config');

        if (!$options->isToolbarEnabled()) {
            return;
        }

        $report = $sm->get('Laminas\DeveloperTools\Report');

        if ($options->canFlushEarly()) {
            $flushListener = $sm->get('Laminas\DeveloperTools\FlushListener');
            $flushListener->attach($em);
        }

        if ($options->isStrict() && $report->hasErrors()) {
            throw new Exception\InvalidOptionException(implode(' ', $report->getErrors()));
        }

        if ($options->eventCollectionEnabled()) {
            $eventLoggingListener = $sm->get('Laminas\DeveloperTools\EventLoggingListenerAggregate');
            $eventLoggingListener->attachShared($sem);
        }

        $profilerListener = $sm->get('Laminas\DeveloperTools\ProfilerListener');
        $profilerListener->attach($em);

        if ($options->isToolbarEnabled()) {
            $toolbarListener = $sm->get('Laminas\DeveloperTools\ToolbarListener');
            $toolbarListener->attach($em);
        }

        if ($options->isStrict() && $report->hasErrors()) {
            throw new Exception\ProfilerException(implode(' ', $report->getErrors()));
        }
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getViewHelperConfig()
    {
        return [
            // Legacy Zend Framework aliases
            'aliases' => [
                'ZendDeveloperToolsTime' => 'LaminasDeveloperToolsTime',
                'ZendDeveloperToolsMemory' => 'LaminasDeveloperToolsMemory',
                'ZendDeveloperToolsDetailArray' => 'LaminasDeveloperToolsDetailArray',
            ],
            'invokables' => [
                'LaminasDeveloperToolsTime'        => 'Laminas\DeveloperTools\View\Helper\Time',
                'LaminasDeveloperToolsMemory'      => 'Laminas\DeveloperTools\View\Helper\Memory',
                'LaminasDeveloperToolsDetailArray' => 'Laminas\DeveloperTools\View\Helper\DetailArray',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getServiceConfig()
    {
        return [
            'aliases' => [
                'Laminas\DeveloperTools\ReportInterface' => 'Laminas\DeveloperTools\Report',

                // Legacy Zend Framework aliases
                'ZendDeveloperTools\ReportInterface' => 'Laminas\DeveloperTools\ReportInterface',
                'ZendDeveloperTools\Report' => 'Laminas\DeveloperTools\Report',
                'ZendDeveloperTools\EventCollector' => 'Laminas\DeveloperTools\EventCollector',
                'ZendDeveloperTools\ExceptionCollector' => 'Laminas\DeveloperTools\ExceptionCollector',
                'ZendDeveloperTools\RouteCollector' => 'Laminas\DeveloperTools\RouteCollector',
                'ZendDeveloperTools\RequestCollector' => 'Laminas\DeveloperTools\RequestCollector',
                'ZendDeveloperTools\ConfigCollector' => 'Laminas\DeveloperTools\ConfigCollector',
                'ZendDeveloperTools\MailCollector' => 'Laminas\DeveloperTools\MailCollector',
                'ZendDeveloperTools\MemoryCollector' => 'Laminas\DeveloperTools\MemoryCollector',
                'ZendDeveloperTools\TimeCollector' => 'Laminas\DeveloperTools\TimeCollector',
                'ZendDeveloperTools\FlushListener' => 'Laminas\DeveloperTools\FlushListener',
                'ZendDeveloperTools\Profiler' => 'Laminas\DeveloperTools\Profiler',
                'ZendDeveloperTools\Config' => 'Laminas\DeveloperTools\Config',
                'ZendDeveloperTools\Event' => 'Laminas\DeveloperTools\Event',
                'ZendDeveloperTools\StorageListener' => 'Laminas\DeveloperTools\StorageListener',
                'ZendDeveloperTools\ToolbarListener' => 'Laminas\DeveloperTools\ToolbarListener',
                'ZendDeveloperTools\ProfilerListener' => 'Laminas\DeveloperTools\ProfilerListener',
                'ZendDeveloperTools\EventLoggingListenerAggregate' => 'Laminas\DeveloperTools\EventLoggingListenerAggregate',
                'ZendDeveloperTools\DbCollector' => 'Laminas\DeveloperTools\DbCollector',
            ],
            'invokables' => [
                'Laminas\DeveloperTools\Report'             => 'Laminas\DeveloperTools\Report',
                'Laminas\DeveloperTools\EventCollector'     => 'Laminas\DeveloperTools\Collector\EventCollector',
                'Laminas\DeveloperTools\ExceptionCollector' => 'Laminas\DeveloperTools\Collector\ExceptionCollector',
                'Laminas\DeveloperTools\RouteCollector'     => 'Laminas\DeveloperTools\Collector\RouteCollector',
                'Laminas\DeveloperTools\RequestCollector'   => 'Laminas\DeveloperTools\Collector\RequestCollector',
                'Laminas\DeveloperTools\ConfigCollector'    => 'Laminas\DeveloperTools\Collector\ConfigCollector',
                'Laminas\DeveloperTools\MailCollector'      => 'Laminas\DeveloperTools\Collector\MailCollector',
                'Laminas\DeveloperTools\MemoryCollector'    => 'Laminas\DeveloperTools\Collector\MemoryCollector',
                'Laminas\DeveloperTools\TimeCollector'      => 'Laminas\DeveloperTools\Collector\TimeCollector',
                'Laminas\DeveloperTools\FlushListener'      => 'Laminas\DeveloperTools\Listener\FlushListener',
            ],
            'factories' => [
                'Laminas\DeveloperTools\Profiler' => function ($sm) {
                    $a = new Profiler($sm->get('Laminas\DeveloperTools\Report'));
                    $a->setEvent($sm->get('Laminas\DeveloperTools\Event'));
                    return $a;
                },
                'Laminas\DeveloperTools\Config' => function ($sm) {
                    $config = $sm->get('Configuration');
                    $config = isset($config['laminas-developer-tools']) ? $config['laminas-developer-tools'] : null;

                    return new Options($config, $sm->get('Laminas\DeveloperTools\Report'));
                },
                'Laminas\DeveloperTools\Event' => function ($sm) {
                    $event = new ProfilerEvent();
                    $event->setReport($sm->get('Laminas\DeveloperTools\Report'));
                    $event->setApplication($sm->get('Application'));

                    return $event;
                },
                'Laminas\DeveloperTools\StorageListener' => function ($sm) {
                    return new Listener\StorageListener($sm);
                },
                'Laminas\DeveloperTools\ToolbarListener' => function ($sm) {
                    return new Listener\ToolbarListener(
                        $sm->get('ViewRenderer'),
                        $sm->get('Laminas\DeveloperTools\Config')
                    );
                },
                'Laminas\DeveloperTools\ProfilerListener' => function ($sm) {
                    return new Listener\ProfilerListener($sm, $sm->get('Laminas\DeveloperTools\Config'));
                },
                'Laminas\DeveloperTools\EventLoggingListenerAggregate' => function ($sm) {
                    $config = $sm->get('Laminas\DeveloperTools\Config');

                    return new Listener\EventLoggingListenerAggregate(
                        array_map([$sm, 'get'], $config->getEventCollectors()),
                        $config->getEventIdentifiers()
                    );
                },
                'Laminas\DeveloperTools\DbCollector' => function ($sm) {
                    $p  = false;
                    $db = new Collector\DbCollector();

                    if ($sm->has('Laminas\Db\Adapter\Adapter')) {
                        $adapter = $sm->get('Laminas\Db\Adapter\Adapter');
                        if ($adapter instanceof ProfilingAdapter) {
                            $p = true;
                            $db->setProfiler($adapter->getProfiler());
                        }
                    }

                    if (! $p && $sm->has('Laminas\Db\Adapter\AdapterInterface')) {
                        $adapter = $sm->get('Laminas\Db\Adapter\AdapterInterface');
                        if ($adapter instanceof ProfilingAdapter) {
                            $p = true;
                            $db->setProfiler($adapter->getProfiler());
                        }
                    }

                    if (! $p && $sm->has('Laminas\Db\Adapter\ProfilingAdapter')) {
                        $adapter = $sm->get('Laminas\Db\Adapter\ProfilingAdapter');
                        if ($adapter instanceof ProfilingAdapter) {
                            $db->setProfiler($adapter->getProfiler());
                        }
                    }

                    return $db;
                },
            ],
        ];
    }
}
