<?php

namespace App\Domain;

use App\Infrastructure\MySQL\HostRepository;
use App\Domain\Host;

class HostService implements InjectionServiceInterface
{
    private $hostRepository;

    public function __construct(HostRepository $hostRepository)
    {
        $this->hostRepository = $hostRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $host = new Host('host_name', 'host_alias', '127.0.0.1');
        $ids = $this->hostRepository->inject($host, $properties['host']['count'], $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->hostRepository->purge();
    }
}
