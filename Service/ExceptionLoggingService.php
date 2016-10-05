<?php

namespace Chteuchteu\SymExc2GtlbIsuBndle\Service;

use Gitlab\Api\Issues;
use Gitlab\Api\Projects;
use Gitlab\Client;
use Gitlab\Model\Issue;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class ExceptionLoggingService
{
    const CommentPattern = "Thrown {count} time{plural}, last one was {datetime}";
    const CommentRegex = "/^Thrown (\\d*) times?, last one was (.*)$/";
    const UnknownLoggedInUser = "None";
    const IssueTitleMaxLength = 255;

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

    /** @var bool */
    private $reopenClosedIssues;

    /** @var array */
    private $excludedEnvironments;

    /** @var array */
    private $excludedExceptions;

    /** @var array */
    private $mentions;


    /** @var TokenStorage */
    private $tokenStorage;

    /** @var \Twig_Environment */
    private $twig;

    /** @var string */
    private $env;


    /** @var Client */
    private $gitLab;

    /**
     * GitLabHandler constructor.
     * @param string $gitlabAPIUrl GitLab's API URL
     * @param int $token GitLab API token
     * @param string $project GitLab repository name or id
     * @param bool $reopenClosedIssues
     * @param array $excludedEnvironments
     * @param array $excludedExceptions
     * @param array $mentions
     * @param TokenStorage $tokenStorage
     * @param \Twig_Environment $twig
     * @param string $env
     */
    public function __construct($gitlabAPIUrl, $token, $project, $reopenClosedIssues, $excludedEnvironments, $excludedExceptions, $mentions,
                                $tokenStorage, \Twig_Environment $twig, $env)
    {
        $this->gitLabAPIUrl = $gitlabAPIUrl;
        $this->token = $token;
        $this->project = $project;
        $this->reopenClosedIssues = $reopenClosedIssues;
        $this->excludedEnvironments = $excludedEnvironments;
        $this->excludedExceptions = $excludedExceptions;
        $this->mentions = $mentions;

        $this->twig = $twig;
        $this->env = $env;
    }

    public function logException(GetResponseForExceptionEvent $event)
    {
        $exceptionInfos = $this->getExceptionInformation($event);

        // Handle excluded environments
        if (in_array($this->env, $this->excludedEnvironments))
            return;

        // Handle excluded exceptions
        if (in_array($exceptionInfos['class'], $this->excludedExceptions))
            return;

        // Connect to GitLab's API
        $this->gitLab = new Client($this->gitLabAPIUrl);
        $this->gitLab->authenticate($this->token, Client::AUTH_URL_TOKEN);

        /** @var Issues $issuesApi */
        $issuesApi = $this->gitLab->api('issues');

        // Find project
        $project = $this->findProject();

        if ($project === null)
            return;

        $issue = $this->findIssue($exceptionInfos['title'], $project);
        if (!$issue) {
            // Issue does not exist yet, let's open it
            $issue = $issuesApi->create($project['id'], [
                'title' => $this->getIssueTitle($exceptionInfos),
                'description' => $this->getIssueBody($exceptionInfos)
            ]);

            // Add a comment with the exceptions count
            $issuesApi->addComment($project['id'], $issue['id'], $this->getCommentBody());
        } else {
            // Issue does exist, update it
            $params = [
                'description' => $this->getIssueBody($exceptionInfos)
            ];

            // Reopen issue if it was closed
            if ($issue['state'] != 'opened' && $this->reopenClosedIssues)
                $params['state_event'] = 'reopen';

            $issuesApi->update($project['id'], $issue['id'], $params);

            // Delete special comment it if exists, then (re-)create it
            $count = 1;
            foreach ($issuesApi->showComments($project['id'], $issue['id']) as $comment) {
                // Find the comment by testing its body against CommentRegex
                if (preg_match(self::CommentRegex, $comment['body'], $matches, PREG_OFFSET_CAPTURE)) {
                    // Use the count in comment's body
                    $count = intval($matches[1][0]) + 1;

                    // Delete comment
                    $issuesApi->removeComment($project['id'], $issue['id'], $comment['id']);
                    break;
                }
            }

            $issuesApi->addComment($project['id'], $issue['id'], $this->getCommentBody($count));
        }
    }

    private function getCommentBody($count=1)
    {
        $replacements = [
            '/{count}/' => $count,
            '/{plural}/' => $count > 1 ? 's' : '',
            '/{datetime}/' => (new \DateTime())->format('d/m/Y H:i:s')
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), self::CommentPattern);
    }

    /**
     * GitLab issues titles are limited to 255 chars, let's truncate it if necessary
     * @param array $exceptionInfos
     * @return string
     */
    private function getIssueTitle(array $exceptionInfos)
    {
        $title = $exceptionInfos['title'];

        if (strlen($title) > self::IssueTitleMaxLength)
            return substr($title, 0, self::IssueTitleMaxLength-3) . '...';

        return $title;
    }

    private function getIssueBody(array $exceptionInfos)
    {
        return $this->twig->render('@SymfonyExceptions2GitLabIssues/gitLabMessage.md.twig', array_merge($exceptionInfos, [
            'issueTitleMaxLength' => self::IssueTitleMaxLength,
            'mentions' => $this->mentions
        ]));
    }

    /**
     * @return array|null
     */
    private function findProject()
    {
        /** @var Projects $projectsApi */
        $projectsApi = $this->gitLab->api('projects');

        $projects = $projectsApi->search($this->project);
        return count($projects) > 0 ? $projects[0] : null;
    }

    /**
     * Try to find an issue with this $title
     * TODO handle pagination
     * @param $title
     * @param array $project
     * @return Issue|null
     */
    private function findIssue($title, array $project)
    {
        /** @var Issues $issuesApi */
        $issuesApi = $this->gitLab->api('issues');

        foreach ($issuesApi->all($project['id']) as $issue)
        {
            if ($issue['title'] == $title)
                return $issue;
        }
        return null;
    }

    private function getExceptionInformation(GetResponseForExceptionEvent $event)
    {
        $request = $event->getRequest();
        $exception = $event->getException();
        $user = $this->tokenStorage != null ? $this->tokenStorage->getToken()->getUsername() : self::UnknownLoggedInUser;
        $file = substr($exception->getFile(), strpos($exception->getFile(), 'src/') ?: 0);
        $line = $exception->getLine();

        return [
            'title' => 'Exception in ' . $file . ' on line ' . $line . ': ' . $exception->getMessage(),
            'file' => $file,
            'line' => $line,
            'user' => $user,
            'request' => $request,
            'message' => $exception->getMessage(),
            'stacktrace' => $exception->getTraceAsString(),
            'class' => get_class($exception)
        ];
    }
}
