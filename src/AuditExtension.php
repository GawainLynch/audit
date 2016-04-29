<?php

namespace Bolt\Extension\Bolt\Audit;

use Bolt\Events\AccessControlEvent;
use Bolt\Events\AccessControlEvents;
use Bolt\Extension\Bolt\Audit\Storage\Entity;
use Bolt\Extension\Bolt\Audit\Storage\Repository;
use Bolt\Extension\Bolt\Audit\Storage\Schema;
use Bolt\Extension\DatabaseSchemaTrait;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\StorageTrait;
use Carbon\Carbon;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Audit extension class loader.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AuditExtension extends SimpleExtension
{
    use StorageTrait;
    use DatabaseSchemaTrait;

    /**
     * AccessControlEvents::LOGIN_SUCCESS event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onLoginSuccess(AccessControlEvent $event)
    {
        $this->logEvent('Authentication success', $event);
    }

    /**
     * AccessControlEvents::LOGIN_FAILURE event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onLoginFailure(AccessControlEvent $event)
    {
        $this->logEvent('Authentication failure', $event);
    }

    /**
     * AccessControlEvents::ACCESS_CHECK_REQUEST event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onAccessCheckRequest(AccessControlEvent $event)
    {
        $this->logEvent('Access check request', $event);
    }

    /**
     * AccessControlEvents::ACCESS_CHECK_SUCCESS event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onAccessCheckSuccess(AccessControlEvent $event)
    {
        $this->logEvent('Access check success', $event);
    }

    /**
     * AccessControlEvents::ACCESS_CHECK_FAILURE event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onAccessCheckFailure(AccessControlEvent $event)
    {
        $this->logEvent('Access check failure', $event);
    }

    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addListener(AccessControlEvents::LOGIN_SUCCESS, [$this, 'onLoginSuccess']);
        $dispatcher->addListener(AccessControlEvents::LOGIN_FAILURE, [$this, 'onLoginFailure']);

        $dispatcher->addListener(AccessControlEvents::ACCESS_CHECK_REQUEST, [$this, 'onAccessCheckRequest']);
        $dispatcher->addListener(AccessControlEvents::ACCESS_CHECK_SUCCESS, [$this, 'onAccessCheckSuccess']);
        $dispatcher->addListener(AccessControlEvents::ACCESS_CHECK_FAILURE, [$this, 'onAccessCheckFailure']);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $this->extendDatabaseSchemaServices();
        $this->extendRepositoryMapping();

        $app['audit.logger'] = $app->share(
            function ($app) {
                $ident = sprintf('bolt.%s', $app['config']->get('general/branding/name', 'Bolt'));

                $log = new Logger('audit');
                $syslog = new SyslogHandler($ident, LOG_AUTH);
                $formatter = new LineFormatter('%channel%.%level_name%: %message% %extra%');
                $syslog->setFormatter($formatter);
                $log->pushHandler($syslog);

                return $log;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function registerExtensionTables()
    {
        return [
            'log_audit' => Schema\Table\AuditLog::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerRepositoryMappings()
    {
        return [
            'auditlog' => [Entity\AuditLog::class => Repository\AuditLog::class],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'target' => [
                'database' => true,
                'syslog'   => true,
            ],
        ];
    }

    /**
     * Return the logger service.
     *
     * @return Logger
     */
    private function getLogger()
    {
        $app = $this->getContainer();

        return $app['audit.logger'];
    }

    /**
     * Calculate the human readable reason that triggered a failure event.
     *
     * @param integer $code
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    private function getReason($code)
    {
        if ($code === AccessControlEvents::FAILURE_PASSWORD) {
            return 'Incorrect password';
        }
        if ($code === AccessControlEvents::FAILURE_INVALID) {
            return 'Account invalid';
        }
        if ($code === AccessControlEvents::FAILURE_DISABLED) {
            return 'Account disabled';
        }
        if ($code === AccessControlEvents::FAILURE_LOCKED) {
            return 'Account locked';
        }

        throw new \RuntimeException('Invalid audit reason.');
    }

    /**
     * Return a context array.
     *
     * @param AccessControlEvent $event
     *
     * @return array
     */
    private function getContext(AccessControlEvent $event)
    {
        return [
            'datetime' => Carbon::createFromTimestamp($event->getDateTime()),
            'username' => $event->getUserName(),
            'address'  => $event->getClientIp(),
            'target'   => $event->getUri(),
        ];
    }

    /**
     * Log the event.
     *
     * @param string             $title
     * @param AccessControlEvent $event
     */
    private function logEvent($title, AccessControlEvent $event)
    {
        $config = $this->getConfig();

        if ($config['target']['database']) {
        }
        if ($config['target']['syslog']) {
            $context = $this->getContext($event);
            $event->getReason() ? $context['reason'] = $this->getReason($event->getReason()) : null;
            $this->getLogger()->info(sprintf('%s: %s', $title, json_encode($context)));
        }
    }
}
