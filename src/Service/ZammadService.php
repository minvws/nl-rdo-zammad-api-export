<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use DateTime;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Minvws\Zammad\Path;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use ZammadAPIClient\Core\Auth;
use ZammadAPIClient\Core\Contracts\RequestHandlerInterface;
use ZammadAPIClient\Core\Transport\RequestHandler;
use ZammadAPIClient\Exceptions\ZammadException;

/**
 * Talks to the Zammad REST API through the low-level RequestHandler of
 * zammad-api-client-php v3 and works on the raw response arrays. The typed
 * DTOs of v3 drop null fields and move custom fields into a separate key,
 * which would change the exported JSON files.
 */
class ZammadService
{
    protected const PER_PAGE = 100;

    protected RequestHandlerInterface $client;
    protected Generator $generator;
    protected ArticleAttachmentLinkService $articleAttachmentLinkService;
    protected OutputInterface $output;
    protected bool $verbose = false;
    protected ?ProgressBar $progressBar;

    protected array $groupCache = [];

    public function __construct(
        string $url,
        string $token,
        HtmlGeneratorService $generator,
        ArticleAttachmentLinkService $articleAttachmentLinkService,
    ) {
        $this->generator = $generator;
        $this->articleAttachmentLinkService = $articleAttachmentLinkService;
        $this->output = new NullOutput();
        $this->progressBar = null;

        // Guzzle applies these options to every PSR-18 sendRequest() call.
        $httpClient = new GuzzleClient([
            'connect_timeout' => 10,
            'read_timeout' => 10,
            'timeout' => 0, // this is also the default and makes long downloads possible
            'debug' => false,
            'verify' => true,
            'progress' => function ($total, $downloaded) {
                if ($this->verbose && $this->progressBar) {
                    $this->progressBar->setProgress($downloaded);
                }
            }
        ]);

        $this->client = new RequestHandler($httpClient, new HttpFactory(), $url, auth: Auth::token($token));
    }

    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    public function export(
        array $groupNames,
        array $excludeGroupNames,
        string $destinationPath,
        int $percentage,
        string $search = ''
    ): void {
        $destPath = Path::fromString($destinationPath);

        $groups = $this->fetchGroups($groupNames, $excludeGroupNames);

        $result = [];
        $full_results = [];

        $page = 1;
        while (true) {
            if ($this->verbose) {
                $this->output->writeln("");
                $this->output->writeln("Processing page $page");
            }

            $tickets = $this->getTickets($page, $search);
            if (count($tickets) == 0) {
                if ($this->verbose) {
                    $this->output->writeln("");
                    $this->output->writeln("No tickets found on page $page");
                }
                break;
            }

            foreach ($tickets as $ticket) {
                $do_export = $this->shouldExport($ticket, $groups, $percentage);
                $full_results[] = [
                    'id' => $ticket['id'],
                    'title' => $ticket['title'],
                    'exported' => $do_export
                ];
                if (!$do_export) {
                    continue;
                }

                try {
                    if ($this->verbose) {
                        $this->output->writeln(
                            "* Dumping ticket " . $ticket['id'] . ' : ' . $ticket['title']
                        );

                        ProgressBar::setFormatDefinition('custom', ' %current% [%bar%] %elapsed:6s% -- %message%');
                        $this->progressBar = new ProgressBar($this->output);
                        $this->progressBar->start();
                        $this->progressBar->setFormat('custom');
                        $this->progressBar->setMessage('Exporting ticket ' . $ticket['id']);
                    }

                    $result = $this->exportTicket($ticket, $destPath, $result);
                } catch (Throwable $e) {
                    $this->output->writeln(
                        "* Error while dumping ticket " . $ticket['id'] . ' : ' . $e->getMessage()
                    );
                    $this->output->writeln("Export incomplete!");
                    exit(1);
                }

                if ($this->verbose) {
                    $this->progressBar->finish();
                    $this->output->writeln("");
                }
            }
            $page++;
        }

        if (count($result) === 0) {
            $this->output->writeln("No tickets exported!");
            return;
        }

        foreach ($result as $group) {
            $this->generator->generateGroupIndex($group['path'], $group);
        }

        $this->generator->generateIndex($destPath, $result);
        if ($percentage < 100) {
            $this->generator->generateFullIndex($destPath, $full_results);
        }

        if ($this->verbose) {
            $this->output->writeln("");
            $this->output->writeln("Done!");
        }
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    protected function getTickets(int $page, string $search = ''): array
    {
        $query = [
            'expand' => true,
            'page' => $page,
            'per_page' => self::PER_PAGE,
        ];

        try {
            if (!empty($search)) {
                return $this->client->get('tickets/search', ['query' => $search] + $query);
            }

            return $this->client->get('tickets', $query);
        } catch (ZammadException $e) {
            $this->output->writeln(
                "Error while fetching tickets: {$e->getMessage()}. Maybe an incorrect or missing authorization key?"
            );
            exit(1);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getGroup(string $groupName): ?array
    {
        $page = 1;
        do {
            $groups = $this->client->get('groups', [
                'expand' => true,
                'page' => $page,
                'per_page' => self::PER_PAGE,
            ]);

            foreach ($groups as $group) {
                if (strtolower($group['name']) == strtolower($groupName)) {
                    return $group;
                }
            }
            $page++;
        } while (count($groups) === self::PER_PAGE);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getGroupById(int $groupId): array
    {
        if (!isset($this->groupCache[$groupId])) {
            $this->groupCache[$groupId] = $this->client->get("groups/$groupId", ['expand' => true]);
        }

        return $this->groupCache[$groupId];
    }

    /**
     * @param array<string, mixed> $ticket
     */
    protected function exportTicket(array $ticket, Path $basepath, array $result): array
    {
        $date = new DateTime($ticket['created_at']);

        $contentClasses = $ticket['content_class'] ?? null;
        if (!$contentClasses) {
            $contentClasses = [""];
        }

        foreach ($contentClasses as $contentClass) {
            $ticketGroup = $this->getGroupById((int) $ticket['group_id']);

            $ticketPath = $basepath
                ->add($ticketGroup['name'])
                ->add($contentClass)
                ->add($date->format('Y-m'))
                ->add($ticket['number']);
            $ticketLink = (new Path(null, $ticketGroup['name']))
                ->add($contentClass)
                ->add($date->format('Y-m'))
                ->add($ticket['number']);

            // these temporary folders gets umasked to something sane
            @mkdir($ticketPath->getPath(), 0777, true);
            @mkdir($ticketPath->add('articles')->getPath(), 0777, true);

            // Dump ticket data
            $data = json_encode($ticket, JSON_PRETTY_PRINT);
            file_put_contents($ticketPath->add('ticket.json')->getPath(), $data);

            $ticketGroupName = $ticketGroup['name'];
            if (!isset($result[$ticketGroupName])) {
                $result[$ticketGroupName] = [
                    'tickets' => [],
                    'name' => $ticketGroupName,
                    'path' => $basepath->add($ticketGroupName),
                ];
            }
            $result[$ticketGroupName]['tickets'][] = [
                'data' => $ticket,
                'path' => $ticketLink,
            ];

            // Dump tags
            $tags = $this->getTagsForTicketId((int) $ticket['id']);
            file_put_contents($ticketPath->add('tags.json')->getPath(), json_encode($tags, JSON_PRETTY_PRINT));

            // Dump history
            $history = $this->getTicketHistoryForTicketId((int) $ticket['id']);
            file_put_contents($ticketPath->add('history.json')->getPath(), json_encode($history, JSON_PRETTY_PRINT));

            // Articles
            $ticketArticles = $this->getArticlesForTicketId((int) $ticket['id']);

            $articles = [];
            foreach ($ticketArticles as $article) {
                $articlePath = $ticketPath->add('articles')->add($article['id']);
                @mkdir($articlePath->getPath(), 0777, true);

                // Attachments
                $attachments = [];
                foreach ($article['attachments'] ?? [] as $attachment) {
                    try {
                        $content = $this->client->getRaw(
                            "ticket_attachment/{$article['ticket_id']}/{$article['id']}/{$attachment['id']}"
                        );
                        if ($content === '') {
                            throw new Exception('Could not get attachment content');
                        }
                    } catch (Exception $e) {
                        $this->outputWhileProgressbar(
                            "Error while loading attachment content for attachment " .
                            "{$attachment['id']} of ticket {$ticket['id']}: {$e->getMessage()}"
                        );
                        continue;
                    }

                    // Set filename to sanitized path
                    $attachment['filename'] = (new Path(null, $attachment['filename']))->getPath();
                    file_put_contents($articlePath->add($attachment['filename'])->getPath(), $content);

                    $attachments[] = $attachment;
                }
                $article['attachments'] = $attachments;

                // Replace attachment links in article body
                $article['body'] = $this->articleAttachmentLinkService->replaceAttachmentLinks(
                    (string) $article['body'],
                    $article,
                );

                // Save article data
                $data = json_encode($article, JSON_PRETTY_PRINT);
                file_put_contents($articlePath->add('article.json')->getPath(), $data);

                $articles[] = $article;
            }

            $this->generator->generateTicket($ticketPath, $ticket, $articles, $tags, $history);
        }

        return $result;
    }

    protected function shouldExport(array $ticket, array $groups, int $percentage): bool
    {
        if (
            ! $this->inIncludeGroup(strval($ticket['group_id']), $groups) ||
            $this->inExcludeGroup(strval($ticket['group_id']), $groups)
        ) {
            if ($this->verbose) {
                $this->output->writeln(
                    "* Skipping ticket(other group) " . $ticket['id'] . ' : ' . $ticket['title']
                );
            }
            return false;
        }

        // Fetch the MD5 of the ticket number (md5 should be evenly distributed). Fetch the first 2 digits to get
        // a 0..255 value.  use the percentage (ie: 10% = 25, 50% =  128) to check if this ticket needs to be
        // exported or not
        $hash = md5($ticket['number']);
        $hashValue = hexdec(substr($hash, 0, 2));
        $minValue = intval(round($percentage * 2.55));
        if ($hashValue > $minValue) {
            return false;
        }

        return true;
    }

    /**
     * @param string[] $groupNames
     * @param string[] $excludeGroupNames
     */
    protected function fetchGroups(array $groupNames, array $excludeGroupNames): array
    {
        $groups = [
            'include' => [],
            'exclude' => [],
        ];

        foreach ($groupNames as $groupName) {
            $group = $this->getGroup($groupName);
            if (!is_null($group)) {
                $groups['include'][] = $group;
            }
        }

        foreach ($excludeGroupNames as $groupName) {
            $group = $this->getGroup($groupName);
            if (!is_null($group)) {
                $groups['exclude'][] = $group;
            }
        }

        return $groups;
    }

    protected function inIncludeGroup(string $id, array $groups): bool
    {
        if (count($groups['include']) == 0) {
            // No include groups means all groups
            return true;
        }

        foreach ($groups['include'] as $group) {
            if ($group['id'] == $id) {
                return true;
            }
        }

        return false;
    }

    protected function inExcludeGroup(string $id, array $groups): bool
    {
        foreach ($groups['exclude'] as $group) {
            if ($group['id'] == $id) {
                return true;
            }
        }

        return false;
    }

    protected function outputWhileProgressbar(string $output): void
    {
        $this->progressBar?->clear();
        $this->output->writeln($output);
        $this->progressBar?->display();
    }

    protected function getArticlesForTicketId(int $id): array
    {
        try {
            return $this->client->get("ticket_articles/by_ticket/$id", ['expand' => true]);
        } catch (ZammadException $e) {
            $this->outputWhileProgressbar("Error while loading ticket articles for ticket $id: {$e->getMessage()}");
            return [];
        }
    }

    protected function getTagsForTicketId(int $id): array
    {
        try {
            $tags = $this->client->get('tags', ['object' => 'Ticket', 'o_id' => $id]);
        } catch (ZammadException $e) {
            $this->outputWhileProgressbar("Error while loading tags for ticket $id: {$e->getMessage()}");
            return [];
        }

        return $tags['tags'] ?? [];
    }

    protected function getTicketHistoryForTicketId(int $id): array
    {
        try {
            $history = $this->client->get("ticket_history/$id", ['expand' => true]);
        } catch (ZammadException $e) {
            $this->outputWhileProgressbar("Error while loading history for ticket $id: {$e->getMessage()}");
            return [];
        }

        return $history['history'] ?? [];
    }
}
