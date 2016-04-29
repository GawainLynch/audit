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
use Doctrine\DBAL\Exception\TableNotFoundException;
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
     * AccessControlEvents::LOGOUT_SUCCESS event callback.
     *
     * @param AccessControlEvent $event
     */
    public function onLogoutSuccess(AccessControlEvent $event)
    {
        $this->logEvent('Logout success', $event);
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
        $config = $this->getConfig();

        if ($config['logging']['check']['request']) {
            $dispatcher->addListener(AccessControlEvents::ACCESS_CHECK_REQUEST, [$this, 'onAccessCheckRequest']);
        }
        if ($config['logging']['check']['success']) {
            $dispatcher->addListener(AccessControlEvents::ACCESS_CHECK_SUCCESS, [$this, 'onAccessCheckSuccess']);
        }
        if ($config['logging']['check']['failure']) {
            $dispatcher->addListener(AccessControlEvents::ACCESS_CHECK_FAILURE, [$this, 'onAccessCheckFailure']);
        }

        if ($config['logging']['login']['success']) {
            $dispatcher->addListener(AccessControlEvents::LOGIN_SUCCESS, [$this, 'onLoginSuccess']);
        }
        if ($config['logging']['login']['failure']) {
            $dispatcher->addListener(AccessControlEvents::LOGIN_FAILURE, [$this, 'onLoginFailure']);
        }

        if ($config['logging']['logout']['success']) {
            $dispatcher->addListener(AccessControlEvents::LOGOUT_SUCCESS, [$this, 'onLogoutSuccess']);
        }

        if ($config['logging']['reset']['request']) {
        }
        if ($config['logging']['reset']['success']) {
        }
        if ($config['logging']['reset']['failure']) {
        }
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
            'log_audit' => [Entity\AuditLog::class => Repository\AuditLog::class],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'logging' => [
                'check' => [
                    'request' => false,
                    'success' => true,
                    'failure' => true,
                ],
                'login' => [
                    'success' => true,
                    'failure' => true,
                ],
                'logout' => [
                    'success' => true,
                    'failure' => true,
                ],
                'reset' => [
                    'request' => true,
                    'success' => true,
                    'failure' => true,
                ],
            ],
            'target'  => [
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
            $app = $this->getContainer();
            $repo = $app['storage']->getRepository(Entity\AuditLog::class);
            $entity = new Entity\AuditLog([
                'event'    => $event->getName(),
                'reason'   => $event->getReason(),
                'datetime' => Carbon::createFromTimestamp($event->getDateTime()),
                'username' => $event->getUserName(),
                'ip'       => $event->getClientIp(),
                'uri'      => $event->getUri(),
                'message'  => true,
            ]);

            try {
                $repo->save($entity);
            } catch (TableNotFoundException $e) {
                $app['logger.system']->critical('Audit logging failure.', ['event' => 'exception', 'exception' => $e]);
            }
        }
        if ($config['target']['syslog']) {
            $context = $this->getContext($event);
            $event->getReason() ? $context['reason'] = $this->getReason($event->getReason()) : null;
            $this->getLogger()->info(sprintf('%s: %s', $title, json_encode($context)));
        }
    }
}
