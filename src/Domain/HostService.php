<?php

namespace App\Domain;

use App\Infrastructure\MySQL\HostRepository;
use App\Infrastructure\MySQL\PollerRepository;
use App\Domain\Host;

class HostService implements InjectionServiceInterface
{
    private $hostRepository;
    private $pollerRepository;

    public function __construct(HostRepository $hostRepository, PollerRepository $pollerRepository)
    {
        $this->hostRepository = $hostRepository;
        $this->pollerRepository = $pollerRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $injectedIds['poller'] = $this->pollerRepository->getPollerIds();

        $host = new Host('host_name', 'host_alias', '127.0.0.1');
        $ids = $this->hostRepository->inject($host, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->hostRepository->purge();
    }
}
