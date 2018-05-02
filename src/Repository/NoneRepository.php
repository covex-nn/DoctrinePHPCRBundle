<?php

namespace Doctrine\Bundle\PHPCRBundle\Repository;

class NoneRepository
{
    public function __call($name, $arguments)
    {
        throw new \RuntimeException('Configure real doctrine_phpcr session');
    }
}
