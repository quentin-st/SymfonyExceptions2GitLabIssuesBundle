<?php

namespace Chteuchteu\SymExc2GtlbIsuBndle\Service;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ExceptionLoggingService
{
    /**
     * GitLab's API URL, defaults to hosted
     * @var string
     */
    private $gitLabAPIUrl;

    /**
     * GitLab API token
     * @var string
     */
    private $token;

    /**
     * GitLab repository name or id
     * @var string
     */
    private $project;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * GitLabHandler constructor.
     * @param string $gitlabAPIUrl GitLab's API URL
     * @param int $token GitLab API token
     * @param bool $project GitLab repository name or id
     * @param \Twig_Environment $twig
     */
    public function __construct($gitlabAPIUrl, $token, $project, \Twig_Environment $twig)
    {
        $this->token = $token;
        $this->project = $project;
        $this->gitLabAPIUrl = $gitlabAPIUrl;
        $this->twig = $twig;
    }

    public function logException(GetResponseForExceptionEvent $event)
    {
        // TODO
    }
}
