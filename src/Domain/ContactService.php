<?php

namespace App\Domain;

use App\Infrastructure\MySQL\ContactRepository;
use App\Domain\Contact;

class ContactService implements InjectionServiceInterface
{
    private $contactRepository;

    public function __construct(ContactRepository $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }

    public function inject(array $properties, array $injectedIds): array
    {
        $contact = new Contact('contact_name', 'contact_alias');
        $ids = $this->contactRepository->inject($contact, $properties, $injectedIds);

        return $ids;
    }

    public function purge()
    {
        $this->contactRepository->purge();
    }
}
