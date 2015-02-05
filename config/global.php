<?php

use Interop\Container\ContainerInterface;
use Piwik\Cache\Eager;
use Piwik\SettingsServer;

return array(

    'path.root' => PIWIK_USER_PATH,

    'path.tmp' => function (ContainerInterface $c) {
        $root = $c->get('path.root');

        // TODO remove that special case and instead have plugins override 'path.tmp' to add the instance id
        if ($c->has('ini.General.instance_id')) {
            $instanceId = $c->get('ini.General.instance_id');
            $instanceId = $instanceId ? '/' . $instanceId : '';
        } else {
            $instanceId = '';
        }

        return $root . '/tmp' . $instanceId;
    },

    'path.cache' => function (ContainerInterface $c) {
        $root = $c->get('path.tmp');

        return $root . '/cache/tracker/';
    },

    'cache.backend' => function (ContainerInterface $c) {
        if (defined('PIWIK_TEST_MODE') && PIWIK_TEST_MODE) { // todo replace this with isTest() instead of isCli()
            $backend = 'file';
        } elseif (\Piwik\Development::isEnabled()) {
            $backend = 'array';
        } else {
            $backend = $c->get('ini.Cache.backend');
        }

        return $backend;
    },
    'Piwik\Cache\Lazy' => DI\object(),
    'Piwik\Cache\Transient' => DI\object(),
    'Piwik\Cache\Eager' => function (ContainerInterface $c) {

        $backend = $c->get('Piwik\Cache\Backend');

        if (defined('PIWIK_TEST_MODE') && PIWIK_TEST_MODE) {
            $cacheId = 'eagercache-test-';
        } else {
            $cacheId = 'eagercache-' . str_replace(array('.', '-'), '', \Piwik\Version::VERSION) . '-';
        }

        if (SettingsServer::isTrackerApiRequest()) {
            $eventToPersist = 'Tracker.end';
            $cacheId .= 'tracker';
        } else {
            $eventToPersist = 'Request.dispatch.end';
            $cacheId .= 'ui';
        }

        $cache = new Eager($backend, $cacheId);
        \Piwik\Piwik::addAction($eventToPersist, function () use ($cache) {
            $cache->persistCacheIfNeeded(43200);
        });

        return $cache;
    },
    'Piwik\Cache\Backend' => function (ContainerInterface $c) {

        $type    = $c->get('cache.backend');
        $backend = \Piwik\Cache::buildBackend($type);

        return $backend;
    },

    'Psr\Log\LoggerInterface' => DI\object('Psr\Log\NullLogger'),

    'Piwik\Translation\Loader\LoaderInterface' => DI\object('Piwik\Translation\Loader\LoaderCache')
        ->constructor(DI\link('Piwik\Translation\Loader\JsonFileLoader')),

);
