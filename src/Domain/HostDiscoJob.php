<?php

namespace App\Domain;

class HostDiscoJob
{
    private $id;

    private $name;
    private $description;

    public function __construct(
      string $alias,
      int $providerId,
      int $executionMode,
      int $analysisMode,
      int $saveMode,
      int $status,
      int $duration,
      ?string $message,
      int $monitoringServerId
    )
    {
        $this->alias = $alias;
        $this->providerId = $providerId;
        $this->executionMode = $executionMode;
        $this->analysisMode = $analysisMode;
        $this->saveMode = $saveMode;
        $this->status = $status;
        $this->duration = $duration;
        $this->message = $message;
        $this->monitoringServerId = $monitoringServerId;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId(?int $id)
    {
        $this->id = $id;
        return $this;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getProviderId()
    {
        return $this->providerId;
    }

    public function getExecutionMode()
    {
        return $this->executionMode;
    }

    public function getAnalysisMode()
    {
        return $this->analysisMode;
    }

    public function getSaveMode()
    {
        return $this->saveMode;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getMonitoringServerId()
    {
        return $this->monitoringServerId;
    }
}
