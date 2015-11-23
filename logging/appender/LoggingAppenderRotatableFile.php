<?php

namespace Toby\Logging\Appender;

class LoggingAppenderRotatableFile extends \LoggerAppenderFile
{
    /**
     * check interval in seconds
     *
     * @var int
     */
    protected $checkThreshold = 30;

    /**
     * timestamp of last filecheck
     *
     * @var int
     */
    private $lastChecked;

    private $closeInProgress = false;

    protected function write($string)
    {
        if ($this->fp !== null && !$this->closeInProgress && time() - $this->lastChecked > $this->checkThreshold)
        {
            if ($this->isSameFile($this->getTargetFile(), $this->fp))
            {
                $this->lastChecked = time();
            }
            else
            {
                $this->closeInProgress = true;
                $this->close();
            }
        }
        parent::write($string);
    }

    public function close()
    {
        parent::close();
        $this->closeInProgress = false;
    }

    protected function openFile()
    {
        $result = parent::openFile();

        if ($result == true)
        {
            $this->lastChecked = time();
        }

        return $result;
    }

    private function isSameFile($filename, $fp)
    {
        $fileStat = stat($filename);
        $handleStat = fstat($fp);
        return $fileStat[1] == $handleStat[1];
    }
}
