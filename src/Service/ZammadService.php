<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use DateTime;
use Exception;
use Minvws\Zammad\HTTPClient;
use Minvws\Zammad\Path;
use Minvws\Zammad\Resource\TicketHistory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use ZammadAPIClient\Client;
use ZammadAPIClient\Client\Response;
use ZammadAPIClient\Resource\Group;
use ZammadAPIClient\Resource\Tag;
use ZammadAPIClient\Resource\Ticket;
use ZammadAPIClient\Resource\TicketArticle;
use ZammadAPIClient\ResourceType;

class ZammadService
{
    protected Client $client;
    protected Generator $generator;
    protected OutputInterface $output;
    protected bool $verbose = false;
    protected ?ProgressBar $progressBar;

    protected array $groupCache = [];

    public function __construct(string $url, string $token, HtmlGeneratorService $generator)
    {
        $this->generator = $generator;
        $this->output = new NullOutput();
        $this->progressBar = null;

        $httpClient = new HttpClient([
            'url' => $url,
            'http_token' => $token,
            'connect_timeout' => 10,
            'read_timeout' => 10,
            'timeout' => 0,
            'debug' => false,
            'verify' => true,
            'progress' => function ($total, $downloaded) {
                if ($this->verbose && $this->progressBar) {
                    $this->progressBar->setProgress($downloaded);
                }
            }
        ]);

        $this->client = new Client([], $httpClient);
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
                    'id' => $ticket->getID(),
                    'title' => $ticket->getValue('title'),
                    'exported' => $do_export
                ];
                if (!$do_export) {
                    continue;
                }

                try {
                    if ($this->verbose) {
                        $this->output->writeln(
                            "* Dumping ticket " . $ticket->getID() . ' : ' . $ticket->getValue('title')
                        );

                        ProgressBar::setFormatDefinition('custom', ' %current% [%bar%] %elapsed:6s% -- %message%');
                        $this->progressBar = new ProgressBar($this->output);
                        $this->progressBar->start();
                        $this->progressBar->setFormat('custom');
                        $this->progressBar->setMessage('Exporting ticket ' . $ticket->getID());
                    }

                    $result = $this->exportTicket($ticket, $destPath, $result);
                } catch (Throwable $e) {
                    $this->output->writeln(
                        "* Error while dumping ticket " . $ticket->getID() . ' : ' . $e->getMessage()
                    );
                    $this->output->writeln("Export incomplete!");
                    exit;
                }

                if ($this->verbose) {
                    if ($this->progressBar) {
                        $this->progressBar->finish();
                    }
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

    /**
     * @psalm-suppress UndefinedDocblockClass
     */
    protected function getTickets(int $page, string $search = ''): array
    {
        /** @var Ticket $resource */
        $resource = $this->client->resource(ResourceType::TICKET);
        if (!empty($search)) {
            return $resource->search($search, $page, 100);
        }

        $result = $resource->all($page, 100);

        /** @var Response|null $resp */
        $resp = $this->client->getLastResponse();
        if (!$resp || $resp->getStatusCode() >= 400) {
            $this->output->writeln("Error while fetching ticket. Maybe an incorrect or missing authorization key?");
            return [];
        }

        return $result;
    }

    protected function getGroup(string $groupName): ?Group
    {
        /** @var Group $resource */
        $resource = $this->client->resource(ResourceType::GROUP);
        $groups = $resource->all();

        foreach ($groups as $group) {
            if (strtolower($group->getValue('name')) == strtolower($groupName)) {
                return $group;
            }
        }

        return null;
    }

    protected function getGroupById(int $groupId): ?Group
    {
        if (isset($this->groupCache[$groupId])) {
            return $this->groupCache[$groupId];
        }

        /** @var Group $resource */
        $resource = $this->client->resource(ResourceType::GROUP);

        /** @var Group|null $group */
        $group = $resource->get($groupId);
        if (!$group) {
            return null;
        }

        $this->groupCache[$groupId] = $group;
        return $group;
    }

    protected function exportTicket(Ticket $ticket, Path $basepath, array $result): array
    {
        $date = new DateTime($ticket->getValue('created_at'));

        $contentClasses = $ticket->getValue('content_class');
        if (!$contentClasses) {
            $contentClasses = [""];
        }

        foreach ($contentClasses as $contentClass) {
            /** @var Group $ticketGroup */
            $ticketGroup = $this->getGroupById($ticket->getValue('group_id'));

            $ticketPath = $basepath
                ->add($ticketGroup->getValue('name'))
                ->add($contentClass)
                ->add($date->format('Y-m'))
                ->add($ticket->getValue('number'));
            $ticketLink = (new Path(null, $ticketGroup->getValue('name')))
                ->add($contentClass)
                ->add($date->format('Y-m'))
                ->add($ticket->getValue('number'));

            // these temporary folders gets umasked to something sane
            @mkdir($ticketPath->getPath(), 0777, true);
            @mkdir($ticketPath->add('articles')->getPath(), 0777, true);

            // Dump ticket data
            $data = json_encode($ticket->getValues(), JSON_PRETTY_PRINT);
            file_put_contents($ticketPath->add('ticket.json')->getPath(), $data);

            $ticketGroupName = $ticketGroup->getValue('name');
            if (!isset($result[$ticketGroupName])) {
                $result[$ticketGroupName] = [
                    'tickets' => [],
                    'name' => $ticketGroupName,
                    'path' => $basepath->add($ticketGroupName),
                ];
            }
            $result[$ticketGroupName]['tickets'][] = [
                'data' => $ticket->getValues(),
                'path' => $ticketLink,
            ];

            // Dump tags
            $tags = $this->getTagsForTicketId((int) $ticket->getID());
            file_put_contents($ticketPath->add('tags.json')->getPath(), json_encode($tags, JSON_PRETTY_PRINT));

            // Dump history
            $history = $this->getTicketHistoryForTicketId((int) $ticket->getID());
            file_put_contents($ticketPath->add('history.json')->getPath(), json_encode($history, JSON_PRETTY_PRINT));

            // Articles
            /** @var TicketArticle[] $articles */
            $articles = $ticket->getTicketArticles();
            if ($ticket->getError()) {
                $this->outputWhileProgressbar(
                    "Error while loading ticket articles for ticket {$ticket->getID()}: {$ticket->getError()}"
                );
            }

            foreach ($articles as $article) {
                $data = json_encode($article->getValues(), JSON_PRETTY_PRINT);

                // Save article data
                $articlePath = $ticketPath->add('articles')->add($article->getID());
                @mkdir($articlePath->getPath(), 0777, true);

                file_put_contents($articlePath->add('article.json')->getPath(), $data);

                // Attachments
                foreach ($article->getValue('attachments') as $attachment) {
                    try {
                        $content = $article->getAttachmentContent($attachment['id']);
                        if (!$content) {
                            throw new Exception('Could not get attachment content');
                        }
                    } catch (Exception $e) {
                        $this->outputWhileProgressbar(
                            "Error while loading attachment content for attachment " .
                            "{$attachment['id']} of ticket {$ticket->getID()}: {$e->getMessage()}"
                        );
                        continue;
                    }
                    file_put_contents($articlePath->add($attachment['filename'])->getPath(), $content);
                }
            }

            $this->generator->generateTicket($ticketPath, $ticket, $tags, $history);
        }

        return $result;
    }

    protected function shouldExport(mixed $ticket, array $groups, int $percentage): bool
    {
        if (
            ! $this->inIncludeGroup(strval($ticket->getValue('group_id')), $groups) ||
            $this->inExcludeGroup(strval($ticket->getValue('group_id')), $groups)
        ) {
            /** @var Ticket $ticket */
            if ($this->verbose) {
                $this->output->writeln(
                    "* Skipping ticket(other group) " . $ticket->getID() . ' : ' . $ticket->getValue('title')
                );
            }
            return false;
        }

        // Fetch the MD5 of the ticket number (md5 should be evenly distributed). Fetch the first 2 digits to get
        // a 0..255 value.  use the percentage (ie: 10% = 25, 50% =  128) to check if this ticket needs to be
        // exported or not
        $hash = md5($ticket->getValue('number'));
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
            if ($group->getId() == $id) {
                return true;
            }
        }

        return false;
    }

    protected function inExcludeGroup(string $id, array $groups): bool
    {
        foreach ($groups['exclude'] as $group) {
            if ($group->getId() == $id) {
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

    protected function getTagsForTicketId(int $id): array
    {
        /** @var Tag $resource */
        $resource = $this->client->resource(ResourceType::TAG);

        /** @var Tag $tag */
        $tag = $resource->get($id);

        if ($tag->getError()) {
            $this->outputWhileProgressbar("Error while loading tags for ticket $id: {$tag->getError()}");
            return [];
        }
        return $tag->getValue('tags');
    }

    protected function getTicketHistoryForTicketId(int $id): array
    {
        /** @var TicketHistory $resource */
        $resource = $this->client->resource(TicketHistory::class);

        /** @var TicketHistory $history */
        $history = $resource->get($id);
        if ($history->getError()) {
            $this->outputWhileProgressbar("Error while loading history for ticket $id: {$history->getError()}");
            return [];
        }

        return $history->getValues()['history'] ?? [];
    }
}
