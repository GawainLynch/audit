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
        $context = [
            'datetime' => Carbon::createFromTimestamp($event->getDateTime()),
            'username' => $event->getUserName(),
            'address'  => $event->getClientIp(),
            'target'   => $event->getUri(),
        ];

        $this->getLogger()->info('Authentication success: ' . json_encode($context));
    }

    /**
     * AccessControlEvents::LOGIN_FAILURE event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onLoginFailure(AccessControlEvent $event)
    {
        $context = [
            'datetime' => Carbon::createFromTimestamp($event->getDateTime()),
            'username' => $event->getUserName(),
            'address'  => $event->getClientIp(),
            'target'   => $event->getUri(),
            'reason'   => $this->getReason($event->getReason()),
        ];

        $this->getLogger()->info('Authentication failure: ' . json_encode($context));
    }

    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addListener(AccessControlEvents::LOGIN_SUCCESS, [$this, 'onLoginSuccess']);
        $dispatcher->addListener(AccessControlEvents::LOGIN_FAILURE, [$this, 'onLoginFailure']);
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
}
