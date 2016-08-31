<?php

namespace Toby\MySQL;

class MySQLStatement
{
    /**
     * @var \mysqli_stmt
     */
    private $stmt;

    /**
     * @var MySQL
     */
    private $parent;

    /**
     * @var string
     */
    private $query;

    /** @var \Logger */
    private $logger;

    public function __construct(\mysqli_stmt $stmt, MySQL $parent, $query)
    {
        $this->stmt = $stmt;
        $this->parent = $parent;
        $this->query = $query;
        $this->logger = \Logger::getLogger("toby.mysql.statement");
    }

    /**
     * binds parameters
     *
     * @param $typeDefinition
     * @param $parameters,...
     *
     * @throws MySQLException
     */
    public function bindParams($typeDefinition, $parameters)
    {
        if (func_num_args() > 2)
        {
            $parameters = func_get_args();
            array_shift($parameters);
        }
        elseif (!is_array($parameters))
        {
            $parameters = array($parameters);
        }

        $arguments = array($typeDefinition);
        foreach ($parameters as &$param)
        {
            $arguments[] = &$param;
        }

        if (call_user_func_array(array($this->stmt, 'bind_param'), $arguments) === false)
        {
            $error = $this->stmt->error;
            $errno = $this->stmt->errno;

            $this->logger->error("param bind failed ($errno: $error)");
            throw new MySQLException("$errno: $error", $errno);
        }
    }

    public function execute()
    {
        $start = microtime(true);

        if ($this->stmt->execute() === false)
        {
            $error = $this->stmt->error;
            $errno = $this->stmt->errno;

            $this->logger->error("executing statement failed ($errno: $error)");
            throw new MySQLException("$errno: $error", $errno);
        }

        if ($this->parent->isPerformanceTrackingEnabled())
        {
            $stop = microtime(true);
            $this->parent->addPerformanceData($this->query, $stop - $start);
        }
    }

    public function getInsertId()
    {
        return $this->stmt->insert_id;
    }

    public function close()
    {
        if ($this->stmt->close() === false)
        {
            $error = $this->stmt->error;
            $errno = $this->stmt->errno;

            $this->logger->error("closing statement failed ($errno: $error)");
            throw new MySQLException("$errno: $error", $errno);
        }
    }
}
