<?php

namespace Chteuchteu\SymExc2GtlbIsuBndle;

use Chteuchteu\SymExc2GtlbIsuBndle\DependencyInjection\SymfonyExceptions2GitLabIssuesExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyExceptions2GitLabIssuesBundle extends Bundle
{
    const DI_Alias = 'sym_exc_2_gtlb_isu_bndle';

    public function getContainerExtension()
    {
        if ($this->extension === null)
            $this->extension = new SymfonyExceptions2GitLabIssuesExtension();

        return $this->extension;
    }
}
