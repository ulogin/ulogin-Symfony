<?php

namespace Ulogin\AuthBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Ulogin\AuthBundle\DependencyInjection\AuthExtension;
use Ulogin\AuthBundle\DependencyInjection\Compiler\ValidationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class UloginAuthBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new AuthExtension();
    }
}
