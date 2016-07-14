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
    const IssueTitleMaxLength = 256;

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

    /** @var \Twig_Environment */
    private $twig;

    /** @var TokenStorage */
    private $tokenStorage;

    /** @var Client */
    private $gitLab;

    /**
     * GitLabHandler constructor.
     * @param string $gitlabAPIUrl GitLab's API URL
     * @param int $token GitLab API token
     * @param bool $project GitLab repository name or id
     * @param \Twig_Environment $twig
     */
    public function __construct($gitlabAPIUrl, $token, $project, $tokenStorage, \Twig_Environment $twig)
    {
        $this->token = $token;
        $this->project = $project;
        $this->gitLabAPIUrl = $gitlabAPIUrl;
        $this->twig = $twig;
    }

    public function logException(GetResponseForExceptionEvent $event)
    {
        // Connect to GitLab's API
        $this->gitLab = new Client($this->gitLabAPIUrl);
        $this->gitLab->authenticate($this->token, Client::AUTH_URL_TOKEN);

        /** @var Issues $issuesApi */
        $issuesApi = $this->gitLab->api('issues');

        // Find project
        $project = $this->findProject();

        if ($project === null)
            return;

        $exceptionInfos = $this->getExceptionInformation($event);

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
            if ($issue['state'] != 'opened')
                $params['state_event'] = 'reopen';

            $issuesApi->update($project['id'], $issue['id'], $params);

            // Update special comment it if exists (or create it)
            $foundComment = false;
            foreach ($issuesApi->showComments($project['id'], $issue['id']) as $comment) {
                if (preg_match(self::CommentRegex, $comment['body'], $matches, PREG_OFFSET_CAPTURE)) {
                    // Found comment, update it
                    $issuesApi->updateComment($project['id'], $issue['id'], $comment['id'], $this->getCommentBody(intval($matches[1][0]) + 1));
                    $foundComment = true;
                    break;
                }
            }

            if (!$foundComment) {
                // Could not find comment, let's create it back
                $issuesApi->addComment($project['id'], $issue['id'], $this->getCommentBody());
            }
        }
    }

    private function getCommentBody($count=1)
    {
        $replacements = [
            '/{count}/' => $count,
            '/{plural}/' => $count > 1 ? 's' : '',
            '/{datetime}/' => (new \DateTime())->format('d/m/Y h:i:s')
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), self::CommentPattern);
    }

    /**
     * GitLab issues titles are limited to 256 chars, let's truncate it if necessary
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
            'issueTitleMaxLength' => self::IssueTitleMaxLength
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
            'stacktrace' => $exception->getTraceAsString()
        ];
    }
}
