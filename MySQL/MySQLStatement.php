<?php

namespace Toby\MySQL;

class MySQLStatement
{
    /**
     * @var \mysqli_stmt
     */
    private $stmt;

    /** @var \Logger */
    private $logger;

    public function __construct(\mysqli_stmt $stmt)
    {
        $this->stmt = $stmt;
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
        if ($this->stmt->execute() === false)
        {
            $error = $this->stmt->error;
            $errno = $this->stmt->errno;

            $this->logger->error("executing statement failed ($errno: $error)");
            throw new MySQLException("$errno: $error", $errno);
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
