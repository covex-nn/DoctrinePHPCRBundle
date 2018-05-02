<?php

namespace Doctrine\Bundle\PHPCRBundle\Repository;

class NoneRepository
{
    public function login()
    {
        return null;
    }

    public function __call($name, $arguments)
    {
        throw new \RuntimeException('Configure real doctrine_phpcr session');
    }
}
