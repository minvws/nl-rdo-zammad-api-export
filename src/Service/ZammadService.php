<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use Minvws\Zammad\HTTPClient;
use Minvws\Zammad\Path;
use Minvws\Zammad\Resource\TicketHistory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\Group;
use ZammadAPIClient\Resource\Tag;
use ZammadAPIClient\Resource\Ticket;
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

    public function setVerbose(bool $verbose)
    {
        $this->verbose = $verbose;
    }

    public function export(string $groupName, string $destinationPath, int $percentage, string $search = '')
    {
        $destPath = Path::fromString($destinationPath);
            
        $group = $this->getGroup($groupName);
        if (!empty($groupName) && is_null($group)) {
            throw new \Exception("Group $groupName not found");
        }

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
                break;
            }

            foreach ($tickets as $ticket) {
                $do_export = $this->shouldExport($ticket, $group, $percentage);
                $full_results[] = array( 'id' => $ticket->getID(), 'title' => $ticket->getValue('title'), 'exported' => $do_export);
                if (!$do_export) {
                    continue;
                }

                try {
                    if ($this->verbose) {
                        $this->output->writeln("* Dumping ticket ".$ticket->getID().' : '.$ticket->getValue('title'));

                        ProgressBar::setFormatDefinition('custom', ' %current% [%bar%] %elapsed:6s% -- %message%');
                        $this->progressBar = new ProgressBar($this->output);
                        $this->progressBar->start();
                        $this->progressBar->setFormat('custom');
                        $this->progressBar->setMessage('Exporting ticket '.$ticket->getID());
                    }

                    $result = $this->exportTicket($ticket, $destPath, $result);
                } catch (\Throwable $e) {
                    $this->output->writeln("* Error while dumping ticket ".$ticket->getID().' : '.$e->getMessage());
                    $this->output->writeln("Export incomplete!");
                    exit;
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
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    protected function getTickets(int $page, string $search = ''): array
    {
        $resource = $this->client->resource(ResourceType::TICKET);
        if (!empty($search)) {
            return $resource->search($search, $page, 100);
        }

        $result = $resource->all($page, 100);
        if ($this->client->getLastResponse()->getStatusCode() >= 400) {
            $this->output->writeln("Error while fetching ticket. Maybe an incorrect or missing authorization key?");
            return [];
        }

        return $result;
    }

    protected function getGroup(string $groupName): ?Group
    {
        $groups = $this->client->resource(ResourceType::GROUP)->all();
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

        $group = $this->client->resource(ResourceType::GROUP)->get($groupId);
        if (!$group) {
            return null;
        }

        $this->groupCache[$groupId] = $group;
        return $group;
    }

    protected function exportTicket(Ticket $ticket, Path $basepath, array $result): array
    {
        $date = new \DateTime($ticket->getValue('created_at'));

        $ticketGroup = $this->getGroupById($ticket->getValue('group_id'));

        $ticketPath = $basepath
            ->add($ticketGroup->getValue('name'))
            ->add($date->format('Y-m'))
            ->add($ticket->getValue('number'))
        ;
        $ticketLink = (new Path(null, $ticketGroup->getValue('name')))
            ->add($date->format('Y-m'))
            ->add($ticket->getValue('number'))
        ;


        @mkdir($ticketPath->getPath(), 0777, true);
        @mkdir($ticketPath->add('articles')->getPath(), 0777, true);

        // Dump ticket data
        $data = json_encode($ticket->getValues(), JSON_PRETTY_PRINT);
        file_put_contents($ticketPath->add('ticket.json')->getPath(), $data);

        $ticketGroupName = $ticketGroup->getValue('name');
        if (! isset($result[$ticketGroupName])) {
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
        /** @var Tag $tag */
        $tag = $this->client->resource(ResourceType::TAG)->get($ticket->getID(), 'Ticket');
        $tags = $tag->getValue('tags');
        file_put_contents($ticketPath->add('tags.json')->getPath(), json_encode($tags, JSON_PRETTY_PRINT));

        // Dump history
        $history = $this->client->resource(TicketHistory::class)->get($ticket->getID());
        $history = $history->getValues()['history'] ?? [];
        file_put_contents($ticketPath->add('history.json')->getPath(), json_encode($history, JSON_PRETTY_PRINT));

        // Articles
        $articles = $ticket->getTicketArticles();
        foreach($articles as $article) {
            $data = json_encode($article->getValues(), JSON_PRETTY_PRINT);

            // Save article data
            $articlePath = $ticketPath->add('articles')->add($article->getID());
            @mkdir($articlePath->getPath(), 0777, true);

            file_put_contents($articlePath->add('article.json')->getPath(), $data);

            // Attachments
            foreach($article->getValue('attachments') as $attachment) {
                $content = $article->getAttachmentContent($attachment['id']);
                file_put_contents($articlePath->add($attachment['filename'])->getPath(), $content);
            }
        }

        $this->generator->generateTicket($ticketPath, $ticket, $tags, $history);

        return $result;
    }

    protected function shouldExport(mixed $ticket, ?Group $group, int $percentage): bool
    {
        /** @var Ticket $ticket */
        if ($group && $ticket->getValue('group_id') != $group->getID()) {
            if ($this->verbose) {
                $this->output->writeln("* Skipping ticket(other group) " . $ticket->getID() . ' : '. $ticket->getValue('title'));
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
}
