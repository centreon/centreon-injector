<?php

namespace App\Domain;

class HostDiscoJob
{
    private $id;

    private $name;
    private $description;

    public function __construct(
      string $alias,
      int $provider_id,
      int $execution_mode,
      int $analysis_mode,
      int $save_mode,
      int $status,
      int $duration,
      ?string $message,
      int $monitoring_server_id
    )
    {
        $this->alias = $alias;
        $this->provider_id = $provider_id;
        $this->execution_mode = $execution_mode;
        $this->analysis_mode = $analysis_mode;
        $this->save_mode = $save_mode;
        $this->status = $status;
        $this->duration = $duration;
        $this->message = $message;
        $this->monitoring_server_id = $monitoring_server_id;
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
        return $this->provider_id;
    }

    public function getExecutionMode()
    {
        return $this->execution_mode;
    }

    public function getAnalysisMode()
    {
        return $this->analysis_mode;
    }

    public function getSaveMode()
    {
        return $this->save_mode;
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
        return $this->monitoring_server_id;
    }
}
