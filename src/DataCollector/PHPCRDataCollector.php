<?php

namespace Doctrine\Bundle\PHPCRBundle\DataCollector;

use Doctrine\Common\Persistence\ManagerRegistry;
use Jackalope\Query\Query;
use Jackalope\Transport\Logging\DebugStack;
use PHPCR\Query\QueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * PHPCRDataCollector.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class PHPCRDataCollector extends DataCollector
{
    private $registry;

    private $connections;

    private $managers;

    private $loggers = [];

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
        $this->connections = $registry->getConnectionNames();
        $this->managers = $registry->getManagerNames();
    }

    /**
     * Adds the stack logger for a connection.
     *
     * @param string     $name
     * @param DebugStack $logger
     */
    public function addLogger($name, DebugStack $logger)
    {
        $this->loggers[$name] = $logger;
    }

    public function getManagers()
    {
        return $this->data['managers'];
    }

    public function getConnections()
    {
        return $this->data['connections'];
    }

    public function getCallCount()
    {
        return array_sum(array_map('count', $this->data['calls']));
    }

    public function getCalls()
    {
        return $this->data['calls'];
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['calls'] as $calls) {
            foreach ($calls as $call) {
                $time += $call['executionMS'];
            }
        }

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'phpcr';
    }

    private function sanitizeCalls($calls)
    {
        foreach ($calls as $i => $call) {
            $calls[$i] = $this->sanitizeCall($call);
        }

        return $calls;
    }

    private function sanitizeCall($call)
    {
        $call['params'] = (array) $call['params'];
        foreach ($call['params'] as &$param) {
            $param = $this->sanitizeParam($param);
        }
        $call['env'] = (array) $call['env'];

        return $call;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the call).
     *
     * @param mixed $var
     *
     * @return array
     */
    private function sanitizeParam($var)
    {
        if (is_object($var)) {
            if ($var instanceof QueryInterface) {
                $query = [
                    'querystring' => $var->getStatement(),
                    'language' => $var->getLanguage(),
                ];
                if ($var instanceof Query) {
                    $query['limit'] = $var->getLimit();
                    $query['offset'] = $var->getOffset();
                }

                return $query;
            }

            return [sprintf('Object(%s)', get_class($var)), false];
        }

        if (is_array($var)) {
            $a = [];
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return [$a, $original];
        }

        if (is_resource($var)) {
            return [sprintf('Resource(%s)', get_resource_type($var)), false];
        }

        return [$var, true];
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $calls = [];
        foreach ($this->loggers as $name => $logger) {
            $calls[$name] = $this->sanitizeCalls($logger->calls);
        }

        $this->data = [
            'calls' => $calls,
            'connections' => $this->connections,
            'managers' => $this->managers,
        ];

        $documents = [];

        foreach ($this->registry->getManagers() as $name => $em) {
            $documents[$name] = [];
            /** @var $factory \Doctrine\ODM\PHPCR\Mapping\ClassMetadataFactory */
            $factory = $em->getMetadataFactory();

            /** @var $class \Doctrine\ODM\PHPCR\Mapping\ClassMetadata */
            foreach ($factory->getLoadedMetadata() as $class) {
                $documents[$name][] = $class->getName();
            }
        }

        $this->data['documents'] = $documents;
    }

    public function getDocuments()
    {
        return $this->data['documents'];
    }

    public function reset()
    {
        $this->data = [];

        foreach ($this->loggers as $logger) {
            $logger->queries = [];
            $logger->currentQuery = 0;
        }
    }
}
